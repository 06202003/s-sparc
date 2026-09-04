import time
import uuid
import hashlib
from fastapi import APIRouter, Depends, HTTPException, Request, BackgroundTasks
from pydantic import BaseModel
from typing import Optional
from backend.core.db import (
    get_db_connection, 
    resolve_user_uuid, 
    get_user_api_key,
    increment_user_query_count,
    get_user_query_quota
)
from backend.api.auth import get_current_user_id
from backend.services.gamification import get_user_token_info
from backend.services.prompt_linter import PromptLinter
from backend.services.learning_analytics import LearningAnalyticsService
import logging

try:
    from backend.core.queue import task_queue
    from redis.exceptions import ConnectionError
except ImportError:
    task_queue = None

router = APIRouter()

# Rate limit cooldown store: user_id -> timestamp (in seconds)
_USER_LAST_REQUEST_TIME = {}
LIVE_AI_COOLDOWN_SECONDS = 60
CACHE_COOLDOWN_SECONDS = 15
MIN_PROMPT_LENGTH = 200
MAX_PROMPT_LENGTH = 2000

class GenerateRequest(BaseModel):
    prompt: str
    assessment_id: Optional[str] = None
    course_id: Optional[str] = None
    language: Optional[str] = None
    response_mode: Optional[str] = "code"
    force_cloud: Optional[bool] = False

class LintPromptRequest(BaseModel):
    prompt: str

_in_memory_jobs = {}

def insert_gpt_job(user_id: str, prompt: str, gpt_prompt: str, status="pending", lock_timeout=10) -> str:
    user_id = resolve_user_uuid(user_id)
    if not isinstance(gpt_prompt, str) or not gpt_prompt.strip():
        raise ValueError("Prompt must be non-empty string.")
    norm_prompt = gpt_prompt.strip()
    if len(norm_prompt) > 4096:
        raise ValueError("Prompt too long.")

    job_id = str(uuid.uuid4())
    _in_memory_jobs[job_id] = {
        "job_id": job_id,
        "user_id": user_id,
        "prompt": norm_prompt,
        "status": status,
        "created_at": time.time()
    }

    conn = get_db_connection()
    if conn is None:
        return job_id
        
    try:
        with conn.cursor() as cur:
            cur.execute(
                "INSERT INTO gpt_jobs (job_id, user_id, prompt, status, created_at, updated_at) "
                "VALUES (%s, %s, %s, %s, NOW(), NOW())",
                (job_id, user_id, norm_prompt, status)
            )
            conn.commit()
    except Exception as e:
        logging.warning(f"insert_gpt_job DB write warning: {e}")
    finally:
        try:
            conn.close()
        except Exception:
            pass
    return job_id


def get_gpt_job(job_id: str):
    if job_id in _in_memory_jobs:
        return _in_memory_jobs[job_id]
    conn = get_db_connection()
    if conn is None:
        return _in_memory_jobs.get(job_id)
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT * FROM gpt_jobs WHERE job_id=%s", (job_id,))
            row = cur.fetchone()
            if row:
                return row
            return _in_memory_jobs.get(job_id)
    except Exception:
        return _in_memory_jobs.get(job_id)
    finally:
        try:
            conn.close()
        except Exception:
            pass

from backend.services.ai_service import process_chat_job

@router.post(
    "/generate-code",
    summary="Generate Code / Dispatch Adaptive Router AI Job",
    description="Processes coding prompts with user-specific Gemini API Key and fallback to system pool & local Ollama.",
    response_description="Job metadata, job_id, status mode, and real-time gamification indicators"
)
async def generate_code(request: Request, data: GenerateRequest, background_tasks: BackgroundTasks, user_id: str = Depends(get_current_user_id)):
    # 1. Validate Prompt Length (min 10, max 2000 chars)
    raw_prompt = (data.prompt or "").strip()
    if len(raw_prompt) < MIN_PROMPT_LENGTH:
        raise HTTPException(
            status_code=400, 
            detail=f"Prompt terlalu pendek. Minimal {MIN_PROMPT_LENGTH} karakter."
        )
    if len(raw_prompt) > MAX_PROMPT_LENGTH:
        raise HTTPException(
            status_code=400, 
            detail=f"Prompt terlalu panjang. Maksimal {MAX_PROMPT_LENGTH} karakter (saat ini: {len(raw_prompt)})."
        )

    # 2. Check Cooldown Rate Limit (60s for Live AI, 15s for DB Cache)
    now = time.time()
    last_req = _USER_LAST_REQUEST_TIME.get(user_id)
    if isinstance(last_req, dict):
        last_time = last_req.get("time", 0)
        last_cooldown = last_req.get("cooldown", LIVE_AI_COOLDOWN_SECONDS)
    elif isinstance(last_req, (int, float)):
        last_time = last_req
        last_cooldown = LIVE_AI_COOLDOWN_SECONDS
    else:
        last_time = 0
        last_cooldown = LIVE_AI_COOLDOWN_SECONDS

    elapsed = now - last_time
    if elapsed < last_cooldown:
        remaining_seconds = int(last_cooldown - elapsed)
        raise HTTPException(
            status_code=429,
            detail=f"Rate limit aktif. Harap tunggu {remaining_seconds} detik sebelum mengirim prompt berikutnya.",
            headers={"Retry-After": str(remaining_seconds)}
        )

    # 3. Check User API Key Registration
    user_api_key = get_user_api_key(user_id, provider="gemini")
    if not user_api_key:
        raise HTTPException(
            status_code=400,
            detail="Google Gemini API Key is required. Please register your personal API Key first."
        )

    markers = []
    if data.force_cloud:
        markers.append("[FORCE_GPT:true]")
    if data.language:
        markers.append(f"[LANG:{data.language}]")
    if data.response_mode:
        markers.append(f"[MODE:{data.response_mode}]")
    if data.assessment_id:
        markers.append(f"[ASSESSMENT_ID:{data.assessment_id}]")
    gpt_prompt_marked = "\n".join(markers) + "\n" + raw_prompt
    
    try:
        job_id = insert_gpt_job(user_id, raw_prompt, gpt_prompt_marked)
        # Execute chat process job in thread pool to prevent blocking FastAPI async event loop
        import asyncio
        await asyncio.to_thread(process_chat_job, job_id, gpt_prompt_marked)
    except Exception as e:
        logging.error(f"Error processing AI chat job: {e}")
        raise HTTPException(status_code=500, detail=f"Failed to generate AI response: {e}")

    job_data = get_gpt_job(job_id) or {}
    ai_response = job_data.get("code") or job_data.get("raw_response") or "Solution processing completed."
    sim_score = 0.0 if data.force_cloud else float(job_data.get("similarity") or 0.0)
    is_retrieval = False if data.force_cloud else bool(sim_score >= 0.88)

    cooldown_secs = CACHE_COOLDOWN_SECONDS if is_retrieval else LIVE_AI_COOLDOWN_SECONDS
    _USER_LAST_REQUEST_TIME[user_id] = {"time": now, "cooldown": cooldown_secs}
    
    session_id = "127.0.0.1"
    assessment_id_from_session = data.assessment_id
    try:
        if "session" in request.scope:
            session_id = request.session.get('session_id') or getattr(request.client, 'host', '127.0.0.1')
            assessment_id_from_session = request.session.get('assessment_id') or data.assessment_id
        else:
            session_id = getattr(request.client, 'host', '127.0.0.1')
    except Exception:
        session_id = getattr(request.client, 'host', '127.0.0.1')

    try:
        gamification = get_user_token_info(user_id, session_id, assessment_id_from_session)
    except Exception as ge:
        logging.warning(f"Gamification calculation fallback: {ge}")
        gamification = {"token_threshold": 0, "points": 100, "gpt_tokens_used": 0}

    req_tokens = 0 if is_retrieval else int(job_data.get("tokens_used") or 0)

    increment_user_query_count(user_id)
    query_quota = get_user_query_quota(user_id)

    # 4. Perform Real-Time Prompt Literacy & C-I-O-E Linting
    prompt_analytics = PromptLinter.analyze(raw_prompt)

    # 5. Log Educational Learning Analytics Event
    LearningAnalyticsService.record_learning_event(
        session_id=session_id,
        user_id=user_id,
        prompt_analysis=prompt_analytics,
        bloom_mode=data.response_mode or "code",
        is_fast_path=is_retrieval,
        tokens_consumed=req_tokens,
        latency_ms=round((time.time() - now) * 1000, 2),
        course_id=int(data.course_id) if data.course_id and data.course_id.isdigit() else None
    )

    return {
        "mode": "success",
        "job_id": job_id,
        "code": ai_response,
        "text": ai_response,
        "message": ai_response,
        "request_tokens_used": req_tokens,
        "session_cumulative_tokens": gamification.get("used_tokens", req_tokens),
        "is_retrieval": is_retrieval,
        "similarity": sim_score,
        "gamification": gamification,
        "cooldown_seconds": cooldown_secs,
        "query_quota": query_quota,
        "prompt_analytics": prompt_analytics
    }

@router.post(
    "/enqueue-gpt",
    summary="Enqueue Direct Gemini / Ollama Inference Job",
    description="Forces direct Gemini / Ollama hybrid inference bypassing the semantic search cache, calculating tokens and logging environmental footprints.",
    response_description="Queued job details and gamification metrics"
)
async def enqueue_gpt(request: Request, data: GenerateRequest, background_tasks: BackgroundTasks, user_id: str = Depends(get_current_user_id)):
    raw_prompt = (data.prompt or "").strip()
    if len(raw_prompt) < MIN_PROMPT_LENGTH:
        raise HTTPException(
            status_code=400, 
            detail=f"Prompt terlalu pendek. Minimal {MIN_PROMPT_LENGTH} karakter."
        )
    if len(raw_prompt) > MAX_PROMPT_LENGTH:
        raise HTTPException(
            status_code=400, 
            detail=f"Prompt terlalu panjang. Maksimal {MAX_PROMPT_LENGTH} karakter (saat ini: {len(raw_prompt)})."
        )

    now = time.time()
    last_req_time = _USER_LAST_REQUEST_TIME.get(user_id, 0)
    elapsed = now - last_req_time
    if elapsed < RATE_LIMIT_COOLDOWN_SECONDS:
        remaining_seconds = int(RATE_LIMIT_COOLDOWN_SECONDS - elapsed)
        raise HTTPException(
            status_code=429,
            detail=f"Rate limit active. Please wait {remaining_seconds} seconds before sending the next prompt.",
            headers={"Retry-After": str(remaining_seconds)}
        )

    user_api_key = get_user_api_key(user_id, provider="gemini")
    if not user_api_key:
        raise HTTPException(
            status_code=400,
            detail="Google Gemini API Key is required. Please register your personal API Key first."
        )

    _USER_LAST_REQUEST_TIME[user_id] = now
        
    markers = []
    if data.language:
        markers.append(f"[LANG:{data.language}]")
    if data.response_mode:
        markers.append(f"[MODE:{data.response_mode}]")
    if data.assessment_id:
        markers.append(f"[ASSESSMENT_ID:{data.assessment_id}]")
    markers.append("[FORCE_GPT:true]")
    markers.append("[AUTO_FALLBACK:true]")
    gpt_prompt_marked = "\n".join(markers) + "\n" + raw_prompt
    
    try:
        job_id = insert_gpt_job(user_id, raw_prompt, gpt_prompt_marked)
        process_chat_job(job_id, gpt_prompt_marked)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Failed to create job: {e}")

    job_data = get_gpt_job(job_id) or {}
    ai_response = job_data.get("code") or job_data.get("raw_response") or "Inference completed."

    session_id = request.session.get('session_id') or getattr(request.client, 'host', '127.0.0.1')
    assessment_id_from_session = request.session.get('assessment_id')
    gamification = get_user_token_info(user_id, session_id, assessment_id_from_session)
    
    increment_user_query_count(user_id)
    query_quota = get_user_query_quota(user_id)

    prompt_analytics = PromptLinter.analyze(raw_prompt)
    LearningAnalyticsService.record_learning_event(
        session_id=session_id,
        user_id=user_id,
        prompt_analysis=prompt_analytics,
        bloom_mode=data.response_mode or "code",
        is_fast_path=False,
        tokens_consumed=int(job_data.get("tokens_used") or 0),
        latency_ms=round((time.time() - now) * 1000, 2),
        course_id=int(data.course_id) if data.course_id and data.course_id.isdigit() else None
    )

    return {
        "mode": "success",
        "job_id": job_id,
        "code": ai_response,
        "text": ai_response,
        "message": ai_response,
        "gamification": gamification,
        "cooldown_seconds": RATE_LIMIT_COOLDOWN_SECONDS,
        "query_quota": query_quota,
        "prompt_analytics": prompt_analytics
    }


@router.get(
    "/check-status/{job_id}",
    summary="Check Queued Job Status & Results",
    description="Polls background job execution state (pending, running, done, error). When done, returns extracted code, raw response, and similarity matching score.",
    response_description="Execution status and generated code content"
)
async def check_status(job_id: str):
    job = get_gpt_job(job_id)
    if not job:
        raise HTTPException(status_code=404, detail="Job ID tidak ditemukan.")
        
    if job["status"] == "pending":
        return {"status": "pending", "message": "Pertanyaan Anda masih dalam antrian, silakan tunggu."}
    if job["status"] == "running":
        return {"status": "running", "message": "Pertanyaan Anda sedang diproses, silakan tunggu."}
    if job["status"] == "done":
        return {
            "status": "done",
            "message": "Proses selesai.",
            "mode": "gpt",
            "code": job.get("code"),
            "raw_response": job.get("raw_response"),
            "similarity": float(job.get("similarity") or 0.0),
            "prompt_matched": job.get("prompt_matched")
        }
    if job["status"] == "error":
        return {"status": "error", "error": job.get("error") or "Unknown error"}
        
    return {"status": "unknown"}

# ==========================================================
# EDUCATIONAL ANALYTICS & AI LITERACY ENDPOINTS
# ==========================================================

@router.post(
    "/educational/lint-prompt",
    summary="Real-Time Pre-Flight C-I-O-E & Shannon Entropy Prompt Linter",
    description="Analyzes prompt in real time for C-I-O-E protocol completeness, technical keyword density, and Shannon entropy, returning constructive pedagogical feedback before submission.",
    response_description="Prompt quality metrics and pedagogical improvement suggestions"
)
async def lint_prompt_endpoint(data: LintPromptRequest):
    return PromptLinter.analyze(data.prompt)

@router.get(
    "/educational/student-profile/{user_id}",
    summary="Get Student AI Literacy & Cognitive Progression Profile",
    description="Returns student cumulative C-I-O-E adherence, average prompt density, cognitive independence index, and dynamic literacy badges.",
    response_description="Student AI literacy profile"
)
async def student_profile_endpoint(user_id: str):
    return LearningAnalyticsService.get_student_profile(user_id)

@router.get(
    "/educational/summary",
    summary="Get Aggregated Class Educational Analytics",
    description="Returns class-wide C-I-O-E adherence rate, Bloom mode distribution, zero-token fast path utilization, and empirical research validation telemetry.",
    response_description="Aggregated educational analytics for faculty and UNU competition evaluation"
)
async def educational_summary_endpoint(course_id: Optional[int] = None):
    return LearningAnalyticsService.get_class_analytics_summary(course_id)


