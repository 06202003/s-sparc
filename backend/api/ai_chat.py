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
import logging

try:
    from backend.core.queue import task_queue
    from redis.exceptions import ConnectionError
except ImportError:
    task_queue = None

router = APIRouter()

# Rate limit cooldown store: user_id -> timestamp (in seconds)
_USER_LAST_REQUEST_TIME = {}
RATE_LIMIT_COOLDOWN_SECONDS = 60
MIN_PROMPT_LENGTH = 10
MAX_PROMPT_LENGTH = 2000

class GenerateRequest(BaseModel):
    prompt: str
    assessment_id: Optional[str] = None
    course_id: Optional[str] = None
    language: Optional[str] = None
    response_mode: Optional[str] = "code"

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

    full_hash = hashlib.sha256(norm_prompt.encode("utf-8")).hexdigest()
    lock_name = "gpt:" + full_hash[:60]

    conn = get_db_connection()
    if conn is None:
        return job_id
        
    try:
        with conn.cursor() as cur:
            try:
                cur.execute("SELECT GET_LOCK(%s, %s)", (lock_name, lock_timeout))
                row = cur.fetchone() or {}
                got_lock = list(row.values())[0] if row else 0
            except Exception as e:
                logging.warning(f"GET_LOCK failed: {e}")
                got_lock = 0

            if got_lock != 1:
                cur.execute(
                    "INSERT INTO gpt_jobs (job_id, user_id, prompt, status, created_at, updated_at) "
                    "VALUES (%s, %s, %s, %s, NOW(), NOW())",
                    (job_id, user_id, norm_prompt, status)
                )
                conn.commit()
                return job_id

            cur.execute(
                "SELECT job_id FROM gpt_jobs WHERE prompt=%s AND status='pending' "
                "ORDER BY created_at ASC LIMIT 1",
                (norm_prompt,)
            )
            existing = cur.fetchone()
            if existing and existing.get("job_id"):
                job_id = existing["job_id"]
            else:
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
            with conn.cursor() as cur:
                cur.execute("SELECT RELEASE_LOCK(%s)", (lock_name,))
        except Exception:
            pass
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

    # 2. Check 1-Minute Cooldown Rate Limit (60 seconds per user)
    now = time.time()
    last_req_time = _USER_LAST_REQUEST_TIME.get(user_id, 0)
    elapsed = now - last_req_time
    if elapsed < RATE_LIMIT_COOLDOWN_SECONDS:
        remaining_seconds = int(RATE_LIMIT_COOLDOWN_SECONDS - elapsed)
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
            detail="API Key Google Gemini belum dimasukkan. Silakan daftarkan API key Anda terlebih dahulu."
        )

    # Register request timestamp for rate limiting
    _USER_LAST_REQUEST_TIME[user_id] = now
        
    markers = []
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
    sim_score = float(job_data.get("similarity") or 0.0)
    is_retrieval = bool(sim_score >= 0.88)
    
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
        "cooldown_seconds": RATE_LIMIT_COOLDOWN_SECONDS,
        "query_quota": query_quota
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
            detail=f"Rate limit aktif. Harap tunggu {remaining_seconds} detik sebelum mengirim prompt berikutnya.",
            headers={"Retry-After": str(remaining_seconds)}
        )

    user_api_key = get_user_api_key(user_id, provider="gemini")
    if not user_api_key:
        raise HTTPException(
            status_code=400,
            detail="API Key Google Gemini belum dimasukkan. Silakan daftarkan API key Anda terlebih dahulu."
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

    return {
        "mode": "success",
        "job_id": job_id,
        "code": ai_response,
        "text": ai_response,
        "message": ai_response,
        "gamification": gamification,
        "cooldown_seconds": RATE_LIMIT_COOLDOWN_SECONDS,
        "query_quota": query_quota
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

