import sys
import os
import time
import uuid
import json
import logging
import threading
import queue
import atexit
import hashlib
import datetime
import numpy as np
import pandas as pd
import pymysql
from sqlalchemy import create_engine
from flask import Flask, request, jsonify, session
from flask_cors import CORS
from flask_limiter import Limiter
from flask_limiter.util import get_remote_address
from dotenv import load_dotenv
try:
    from codecarbon import OfflineEmissionsTracker
except ImportError:
    OfflineEmissionsTracker = None
try:
    from langdetect import detect
except ImportError:
    detect = None


# === GLOBAL EMISSIONS TRACKER ===
global_tracker = None
if OfflineEmissionsTracker is not None:
    global_tracker = OfflineEmissionsTracker(
        measure_power_secs=10,
        log_level="error",
        country_iso_code="IDN",
        output_dir="."
    )


# Load environment variables from .env file
load_dotenv()


# === Constants & Config ===
OPENAI_MODEL = "gpt-4"
DEFAULT_LIMITS = ["100 per day", "10 per minute"]
PUE = 1.12
WUE_SITE_L_PER_KWH = 1.9
WUE_SOURCE_L_PER_KWH = 2.271
CIF_KG_PER_KWH = 0.8176
ENERGY_PER_TOKEN_WH_SHORT = 0.0010525
ENERGY_PER_TOKEN_WH_MEDIUM = 0.0006070
ENERGY_PER_TOKEN_WH_LONG = 0.0001555

def update_user_total_points_if_new_week(user_id, cur_points):
    """Update user points if new week has started."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT updated_at FROM user_points WHERE user_id=%s", (user_id,))
            row = cur.fetchone()
            now = datetime.datetime.now()
            week_now = now.isocalendar()[1]
            last_week = -1
            if row and row["updated_at"]:
                last_week = row["updated_at"].isocalendar()[1]
            if week_now != last_week and cur_points > 0:
                cur.execute("SELECT total_points FROM user_points WHERE user_id=%s", (user_id,))
                row2 = cur.fetchone()
                if not row2:
                    cur.execute("INSERT INTO user_points (user_id, total_points, updated_at) VALUES (%s, %s, %s)", (user_id, cur_points, now))
                else:
                    cur.execute("UPDATE user_points SET total_points=total_points+%s, updated_at=%s WHERE user_id=%s", (cur_points, now, user_id))
            conn.commit()
    finally:
        conn.close()

# === Gamification Utilities: Insert per aksi, agregat mingguan ===
def log_token_usage(user_id, session_id, tokens_used):
    """Log token usage for user/session."""
    if not user_id or not session_id:
        raise ValueError("user_id and session_id are required for token usage log")
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            now = datetime.datetime.now()
            cur.execute(
                "INSERT INTO session_tokens (id, user_id, session_id, tokens_used, used_at) VALUES (%s, %s, %s, %s, %s)",
                (str(uuid.uuid4()), user_id, session_id, tokens_used, now)
            )
        conn.commit()
    finally:
        conn.close()

# === Gamification Utilities ===
def get_user_token_info(user_id, session_id=None):
    """Get user token info for current session/week."""
    if not user_id:
        raise ValueError("user_id is required for token info")
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            now = datetime.datetime.now()
            cur.execute("SELECT COALESCE(SUM(tokens_used), 0) AS used_this_week FROM session_tokens WHERE user_id=%s AND YEARWEEK(used_at, 1) = YEARWEEK(%s, 1)", (user_id, now))
            used_this_week = cur.fetchone()["used_this_week"]
            remaining_tokens = max(0, 2000 - used_this_week)
            points = remaining_tokens
            return {
                "total_tokens": 2000,
                "remaining_tokens": remaining_tokens,
                "points": points
            }
    finally:
        conn.close()



# === Chat History Utilities ===
def save_chat_message(user_id, session_id, role, content):
    """Save chat message to history."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "INSERT INTO chat_history (id, user_id, session_id, role, content) VALUES (%s, %s, %s, %s, %s)",
                (str(uuid.uuid4()), user_id, session_id, role, content)
            )
        conn.commit()
    finally:
        conn.close()

def get_chat_history(user_id, session_id, limit=10):
    """Get last N chat history for user/session."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT role, content FROM chat_history WHERE user_id=%s AND session_id=%s ORDER BY created_at DESC LIMIT %s",
                (user_id, session_id, limit)
            )
            rows = cur.fetchall()
            return rows[::-1]
    finally:
        conn.close()

# SQLAlchemy engine for pandas read_sql (best practice)
def get_sqlalchemy_engine():
    """Get SQLAlchemy engine from env."""
    user = os.getenv("MYSQL_USER", "root")
    password = os.getenv("MYSQL_PASSWORD", "")
    host = os.getenv("MYSQL_HOST", "localhost")
    db = os.getenv("MYSQL_DB", "db_semantic")
    return create_engine(f"mysql+pymysql://{user}:{password}@{host}/{db}?charset=utf8mb4")

# === Environmental Impact Calculation Function ===
def compute_environmental_impact(token_count: int) -> dict:
    """
    Compute environmental impact of model inference based on token count.
    Returns: dict with energy, carbon, water usage.
    """
    if token_count <= 0:
        raise ValueError("token_count must be greater than 0")
    if token_count <= 400:
        wh_per_token = ENERGY_PER_TOKEN_WH_SHORT
    elif token_count <= 2000:
        wh_per_token = ENERGY_PER_TOKEN_WH_MEDIUM
    else:
        wh_per_token = ENERGY_PER_TOKEN_WH_LONG
    energy_wh = token_count * wh_per_token
    energy_kwh = energy_wh / 1000.0
    carbon_kg = energy_kwh * CIF_KG_PER_KWH
    water_L = (energy_kwh / PUE) * WUE_SITE_L_PER_KWH + energy_kwh * WUE_SOURCE_L_PER_KWH
    water_ml = water_L * 1000.0
    return {
        "energy_wh": energy_wh,
        "energy_kwh": energy_kwh,
        "carbon_kg": carbon_kg,
        "water_ml": water_ml
    }

# --- Helper: Validate env and warn if missing ---
def _warn_env(var, default=None):
    """Warn if env var missing."""
    val = os.getenv(var)
    if not val and default is None:
        print(f"[WARNING] Environment variable {var} is not set!")
    return val or default


app = Flask(__name__)
CORS(app, supports_credentials=True)
app.secret_key = _warn_env("FLASK_SECRET_KEY", "supersecretkey")

def get_db_connection():
    """Get pymysql DB connection from env."""
    try:
        return pymysql.connect(
            host=_warn_env("MYSQL_HOST", "localhost"),
            user=_warn_env("MYSQL_USER", "root"),
            password=_warn_env("MYSQL_PASSWORD", ""),
            database=_warn_env("MYSQL_DB", "db_semantic"),
            charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor
        )
    except Exception as e:
        print(f"[ERROR] DB connection failed: {e}")
        raise

def hash_password(password: str) -> str:
    """Hash password using SHA256."""
    return hashlib.sha256(password.encode("utf-8")).hexdigest()

@app.route('/register', methods=['POST'])
def register():
    import uuid
    data = request.get_json(silent=True) or {}
    username = data.get("username")
    email = data.get("email")
    password = data.get("password")
    if not username or not email or not password:
        return jsonify({"error": "Username, email, dan password wajib diisi."}), 400
    password_hash = hash_password(password)
    user_id = str(uuid.uuid4())
    try:
        conn = get_db_connection()
        with conn.cursor() as cur:
            cur.execute("SELECT user_id FROM users WHERE username=%s OR email=%s", (username, email))
            if cur.fetchone():
                return jsonify({"error": "Username atau email sudah terdaftar."}), 409
            cur.execute(
                "INSERT INTO users (user_id, username, email, password_hash) VALUES (%s, %s, %s, %s)",
                (user_id, username, email, password_hash)
            )
            conn.commit()
        return jsonify({"message": "Registrasi berhasil."}), 201
    except Exception as e:
        return jsonify({"error": str(e)}), 500
    finally:
        conn.close()

@app.route('/login', methods=['POST'])
def login():
    data = request.get_json(silent=True) or {}
    username = data.get("username")
    password = data.get("password")
    if not username or not password:
        return jsonify({"error": "Username dan password wajib diisi."}), 400
    password_hash = hash_password(password)
    try:
        conn = get_db_connection()
        with conn.cursor() as cur:
            cur.execute("SELECT user_id FROM users WHERE username=%s AND password_hash=%s", (username, password_hash))
            user = cur.fetchone()
            if not user:
                return jsonify({"error": "Username atau password salah."}), 401
            session["user_id"] = user["user_id"]
        return jsonify({"message": "Login berhasil."}), 200
    except Exception as e:
        return jsonify({"error": str(e)}), 500
    finally:
        conn.close()

@app.route('/logout', methods=['POST'])
def logout():
    session.pop("user_id", None)
    return jsonify({"message": "Logout berhasil."}), 200

def require_login(func):
    from functools import wraps
    @wraps(func)
    def wrapper(*args, **kwargs):
        if "user_id" not in session:
            return jsonify({"error": "Unauthorized. Silakan login."}), 401
        return func(*args, **kwargs)
    return wrapper


# Initialize Flask-Limiter
limiter = Limiter(
    get_remote_address,
    app=app,
    default_limits=DEFAULT_LIMITS,
)


# Configure OpenAI API key
OPENAI_API_KEY = os.getenv("OPENAI_API_KEY")
openai.api_key = OPENAI_API_KEY
try:
    client = openai.OpenAI(api_key=OPENAI_API_KEY) if OPENAI_API_KEY else openai.OpenAI()
except Exception:
    client = openai.OpenAI()

def insert_gpt_job(user_id, prompt, gpt_prompt, status="pending"):
    """Insert GPT job to DB."""
    job_id = str(uuid.uuid4())
    if not isinstance(prompt, str) or not isinstance(gpt_prompt, str):
        raise ValueError("Prompt must be string.")
    if len(prompt) > 2048 or len(gpt_prompt) > 4096:
        raise ValueError("Prompt too long.")
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "INSERT INTO gpt_jobs (job_id, user_id, prompt, status, created_at, updated_at) VALUES (%s, %s, %s, %s, NOW(), NOW())",
                (job_id, user_id, gpt_prompt, status)
            )
        conn.commit()
    except Exception as e:
        print(f"[ERROR] insert_gpt_job: {e}")
        raise
    finally:
        conn.close()
    return job_id

def update_gpt_job(job_id, code=None, status=None, error=None, similarity=None, prompt_matched=None):
    """Update GPT job in DB."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            sql = "UPDATE gpt_jobs SET "
            fields = []
            values = []
            if code is not None:
                fields.append("code=%s")
                values.append(code)
            if status is not None:
                fields.append("status=%s")
                values.append(status)
            if error is not None:
                fields.append("error=%s")
                values.append(error)
            if similarity is not None:
                fields.append("similarity=%s")
                values.append(similarity)
            if prompt_matched is not None:
                fields.append("prompt_matched=%s")
                values.append(prompt_matched)
            fields.append("updated_at=NOW()")
            sql += ", ".join(fields) + " WHERE job_id=%s"
            values.append(job_id)
            cur.execute(sql, tuple(values))
        conn.commit()
    except Exception as e:
        print(f"[ERROR] update_gpt_job: {e}")
        raise
    finally:
        conn.close()

def get_gpt_job(job_id):
    """Get GPT job from DB."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT * FROM gpt_jobs WHERE job_id=%s", (job_id,))
            return cur.fetchone()
    except Exception as e:
        print(f"[ERROR] get_gpt_job: {e}")
        return None
    finally:
        conn.close()

def update_session_tokens(user_id, session_id, token_count):
    """Update session tokens for user/session."""
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT total_tokens FROM session_tokens WHERE session_id=%s", (session_id,))
            row = cur.fetchone()
            if row:
                cur.execute("UPDATE session_tokens SET total_tokens=total_tokens+%s, updated_at=NOW() WHERE session_id=%s", (token_count, session_id))
            else:
                try:
                    uuid.UUID(str(session_id))
                    session_uuid = str(session_id)
                except Exception:
                    session_uuid = str(uuid.uuid4())
                cur.execute("INSERT INTO session_tokens (session_id, user_id, total_tokens, updated_at) VALUES (%s, %s, %s, NOW())", (session_uuid, user_id, token_count))
        conn.commit()
    except Exception as e:
        print(f"[ERROR] update_session_tokens: {e}")
    finally:
        conn.close()


def gpt_job_worker(sleep_time=2):
    """Worker: process pending GPT jobs for OpenAI, DeepSeek, Qwen."""
    print("[WORKER] GPT job worker started.")
    DEEPSEEK_API_KEY = os.getenv("DEEPSEEK_API_KEY")
    while True:
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT job_id, user_id, prompt, status, code, error, similarity, prompt_matched FROM gpt_jobs WHERE status='pending' ORDER BY created_at ASC LIMIT 1")
                job = cur.fetchone()
            if not job:
                conn.close()
                time.sleep(sleep_time)
                continue
            job_id = job['job_id']
            user_id = job['user_id']
            prompt = job['prompt']
            print(f"[WORKER] Processing job {job_id}")
            session_id = None
            try:
                conn2 = get_db_connection()
                with conn2.cursor() as cur2:
                    cur2.execute("SELECT session_id FROM chat_history WHERE user_id=%s ORDER BY created_at DESC LIMIT 1", (user_id,))
                    row = cur2.fetchone()
                    if row and row.get("session_id"):
                        session_id = row["session_id"]
            except Exception:
                session_id = None
            finally:
                try:
                    conn2.close()
                except Exception:
                    pass
            if not session_id:
                session_id = user_id

            # Model selection: default openai, bisa simpan di DB jika ingin
            model_type = 'openai'
            # Coba ambil dari kolom 'model' jika ada
            try:
                with conn.cursor() as cur:
                    cur.execute("SHOW COLUMNS FROM gpt_jobs LIKE 'model'")
                    if cur.fetchone():
                        cur.execute("SELECT model FROM gpt_jobs WHERE job_id=%s", (job_id,))
                        model_row = cur.fetchone()
                        if model_row and model_row.get('model'):
                            model_type = model_row['model']
            except Exception:
                pass

            try:
                # Parse optional markers like [MODE:...], [LANG:...]
                markers = {}
                try:
                    import re as _re
                    found = _re.findall(r"\[([A-Z0-9_]+):([^\]]+)\]", prompt)
                    for k, v in found:
                        markers[k.upper()] = v.strip()
                    # Remove markers from user prompt for clarity
                    prompt_clean = _re.sub(r"\[([A-Z0-9_]+):([^\]]+)\]\s*", "", prompt).strip()
                except Exception:
                    prompt_clean = prompt

                mode = (markers.get('MODE') or '').lower() or 'code'
                lang_hint = markers.get('LANG') or ''

                if mode == 'code':
                    system_content = (
                        "You are an expert programming assistant. The user requests CODE output. "
                        "Produce only the source code that directly solves the user's request. "
                        "Wrap the code inside triple-backticks (```), and do not include any prose, explanation, or commentary outside the fenced code block. If a programming language is specified, include it after the opening fence (e.g. ```python)."
                    )
                elif mode == 'summary':
                    system_content = (
                        "You are an expert programming assistant. The user requests a SHORT SUMMARY (2-3 sentences). "
                        "Provide a concise programming-focused summary. Do not include code blocks."
                    )
                elif mode == 'summary_code_explanation':
                    system_content = (
                        "You are an expert programming assistant. The user requests SUMMARY + CODE + EXPLANATION. "
                        "First give a brief (1-2 sentence) summary, then output the minimal code required, then a concise explanation."
                    )
                else:
                    system_content = (
                        "You are an expert programming assistant helping undergraduate computer science students. "
                        "Answer concisely and focus on programming."
                    )

                if lang_hint:
                    system_content += f" Use the following language when generating code: {lang_hint}."

                messages = [
                    {"role": "system", "content": system_content},
                    {"role": "user", "content": prompt_clean},
                ]

                code = None
                token_count = 0
                if model_type == 'openai':
                    response = openai.chat.completions.create(
                        model=OPENAI_MODEL,
                        messages=messages,
                        temperature=0.0 if mode == 'code' else 0.2,
                        max_tokens=1024,
                    )
                    response_text = response.choices[0].message.content
                    # Helper: extract code if fenced, else best contiguous code-like block
                    def _extract_code_from_text(txt: str) -> str:
                        if not txt or not isinstance(txt, str):
                            return ''
                        import re as _re
                        m = _re.search(r'```(?:[a-zA-Z0-9_+-]*)\n([\s\S]*?)\n```', txt)
                        if m:
                            return m.group(1).strip()
                        m2 = _re.search(r'```([\s\S]*?)```', txt)
                        if m2:
                            return m2.group(1).strip()
                        lines = txt.split('\n')
                        best_block = []
                        current = []
                        indicators = ['def ', 'class ', 'return ', ';', '{', '}', 'import ', 'from ', 'console.log', 'function ', '=>', '#include', 'printf(', 'cout<<']
                        for line in lines:
                            if any(ind in line for ind in indicators) or line.strip().startswith(('    ', '\t')):
                                current.append(line)
                            else:
                                if len(current) > len(best_block):
                                    best_block = current[:]
                                current = []
                        if len(current) > len(best_block):
                            best_block = current[:]
                        if best_block:
                            return '\n'.join(best_block).strip()
                        return ''

                    # Decide what to store
                    code_only = _extract_code_from_text(response_text)
                    if mode == 'code':
                        code = code_only.strip() if code_only else response_text.strip()
                    else:
                        code = response_text.strip()
                    def count_tokens(messages, model="gpt-4"):
                        try:
                            import tiktoken
                        except ImportError:
                            return 0
                        try:
                            encoding = tiktoken.encoding_for_model(model)
                        except Exception:
                            encoding = tiktoken.get_encoding("cl100k_base")
                        num_tokens = 0
                        for msg in messages:
                            num_tokens += 4
                            for key, value in msg.items():
                                num_tokens += len(encoding.encode(str(value)))
                        num_tokens += 2
                        return num_tokens
                    token_count = count_tokens(messages)
                elif model_type == 'deepseek':
                    # DeepSeek API call via OpenAI SDK
                    from openai import OpenAI
                    deepseek_client = OpenAI(api_key=DEEPSEEK_API_KEY, base_url="https://api.deepseek.com")
                    response = deepseek_client.chat.completions.create(
                        model="deepseek-chat",
                        messages=messages,
                        temperature=0.2,
                        max_tokens=512,
                        stream=False
                    )
                    code = response.choices[0].message.content.strip()
                    token_count = getattr(response, 'usage', {}).get('total_tokens', 0)
                elif model_type == 'qwen':
                    # Qwen API call (dummy, isi detail endpoint jika sudah ada)
                    # import requests
                    # qwen_url = "https://api.qwen.com/v1/chat/completions"
                    # headers = {...}
                    # payload = {...}
                    # resp = requests.post(qwen_url, headers=headers, json=payload)
                    # if resp.status_code == 200:
                    #     result = resp.json()
                    #     code = result['choices'][0]['message']['content'].strip()
                    #     token_count = result.get('usage', {}).get('total_tokens', 0)
                    # else:
                    #     raise Exception(f"Qwen API error: {resp.text}")
                    code = "[Qwen API belum diintegrasikan]"
                    token_count = 0
                else:
                    raise Exception(f"Model tidak dikenali: {model_type}")

                    if user_id and session_id:
                        save_chat_message(user_id, session_id, "assistant", code)
                if user_id:
                    update_session_tokens(user_id, session_id or user_id, token_count)
                # Persist raw_response for debugging/troubleshooting
                try:
                    update_gpt_job(job_id, code=code, status="done", raw_response=response_text)
                except Exception:
                    # Fallback to older call if schema not migrated yet
                    update_gpt_job(job_id, code=code, status="done")
                print(f"[WORKER] Job {job_id} done. Token used: {token_count}")
            except Exception as e:
                print(f"[WORKER] Error running {model_type} for job {job_id}: {e}")
                update_gpt_job(job_id, status="error", error=str(e))
        except Exception as e:
            print(f"[WORKER] DB error: {e}")
        finally:
            try:
                conn.close()
            except Exception:
                pass
        time.sleep(sleep_time)

from semantic_similarity.retrieval_utils import SemanticRetrievalModel
try:
    # --- Copied get_ensemble_embedding from main.ipynb ---
    from langdetect import detect
    # --- Load local models from pretrained_model (no downloads) ---
    from sentence_transformers import SentenceTransformer
    from transformers import pipeline
    import torch
    import os

    MODEL_DIR = 'pretrained_model'
    def _local_path(subdir: str) -> str:
        return os.path.join(MODEL_DIR, subdir)
    def _find_st_model(subdir: str) -> str:
        import glob
        base = _local_path(subdir)
        snapshot_glob = os.path.join(base, 'models--*', 'snapshots', '*')
        candidates = glob.glob(snapshot_glob)
        indicators = {'sentence_bert_config.json', 'config_sentence_transformers.json', 'modules.json', 'model.safetensors', 'pytorch_model.bin'}
        for cand in candidates:
            files = set(os.listdir(cand))
            if indicators & files:
                return cand
        files = set(os.listdir(base)) if os.path.isdir(base) else set()
        if indicators & files:
            return base
        return base

    model1_path = _find_st_model('paraphrase-multilingual-mpnet-base-v2')
    model2_path = _find_st_model('LaBSE')
    model3_path = _find_st_model('multilingual-e5-base')
    print(f"[DEBUG] model1_path: {os.path.abspath(model1_path)}")
    print(f"[DEBUG] model2_path: {os.path.abspath(model2_path)}")
    print(f"[DEBUG] model3_path: {os.path.abspath(model3_path)}")
    model1 = SentenceTransformer(model1_path)
    model2 = SentenceTransformer(model2_path)
    model3 = SentenceTransformer(model3_path)
    translator = pipeline('translation', model=_local_path('opus-mt-id-en'), tokenizer=_local_path('opus-mt-id-en'), device=0 if torch.cuda.is_available() else -1)

    # Set best weights (should be tuned elsewhere and imported/configured as needed)
    best_weights = (0.5, 0.5, 1.5)  # Update as needed

    def get_ensemble_embedding(text, weights):
        global model1, model2, model3, translator
        try:
            lang = detect(text)
        except Exception:
            lang = 'en'
        if lang == 'id':
            text = translator(text)[0]['translation_text']
        emb1 = model1.encode([text], convert_to_numpy=True)
        emb2 = model2.encode([text], convert_to_numpy=True)
        emb3 = model3.encode([text], convert_to_numpy=True)
        emb1 = emb1 / np.linalg.norm(emb1, axis=1, keepdims=True)
        emb2 = emb2 / np.linalg.norm(emb2, axis=1, keepdims=True)
        emb3 = emb3 / np.linalg.norm(emb3, axis=1, keepdims=True)
        emb1 = emb1 * weights[0]
        emb2 = emb2 * weights[1]
        emb3 = emb3 * weights[2]
        emb = np.concatenate([emb1, emb2, emb3], axis=1)
        emb = emb / np.linalg.norm(emb, axis=1, keepdims=True)
        return emb

    # REMOVE static PKL load. Always refresh from DB for up-to-date retrieval
    retrieval_model = None

    def refresh_retrieval_model_from_db():
        import faiss
        import json
        import warnings
        engine = get_sqlalchemy_engine()
        df = pd.read_sql("SELECT prompt, code, embedding FROM code_embeddings", engine)
        if df.empty:
            # Return empty model
            return SemanticRetrievalModel(df, None, None, get_ensemble_embedding, weights=best_weights)
        # Parse embeddings from JSON string to numpy (safe, skip empty/invalid)
        valid_rows = []
        valid_embeddings = []
        for i, row in df.iterrows():
            emb_str = row['embedding']
            if not emb_str or not isinstance(emb_str, str) or emb_str.strip() == '':
                continue
            try:
                emb_arr = np.array(json.loads(emb_str), dtype=np.float32)
                if emb_arr.size == 0:
                    continue
                valid_rows.append(row)
                valid_embeddings.append(emb_arr)
            except Exception as e:
                warnings.warn(f"Invalid embedding at row {i}: {e}")
                continue
        if not valid_embeddings:
            # No valid embeddings
            return SemanticRetrievalModel(pd.DataFrame(columns=df.columns), None, None, get_ensemble_embedding, weights=best_weights)
        embeddings = np.vstack(valid_embeddings)
        # Normalize embeddings
        embeddings = embeddings / np.linalg.norm(embeddings, axis=1, keepdims=True)
        # Build FAISS index
        dim = embeddings.shape[1]
        index = faiss.IndexFlatL2(dim)
        index.add(embeddings)
        valid_df = pd.DataFrame(valid_rows, columns=df.columns).reset_index(drop=True)
        return SemanticRetrievalModel(valid_df, index, embeddings, get_ensemble_embedding, weights=best_weights)

except Exception as e:
    retrieval_model = None
    print(f"[WARNING] semantic_retrieval_mode_rev.pkl not loaded: {e}")

# Configure logging
logging.basicConfig(level=logging.INFO)




def handle_semantic_retrieval(prompt):
    """Handle semantic retrieval logic."""
    retrieval_model = refresh_retrieval_model_from_db()
    if retrieval_model is None or retrieval_model.index is None or retrieval_model.df.empty:
        return None
    tracker = None
    emissions = None
    if OfflineEmissionsTracker is not None:
        tracker = OfflineEmissionsTracker(
            measure_power_secs=1,
            log_level="error",
            country_iso_code="IDN",
            output_dir="."
        )
        tracker.start()
    retrieval_results = retrieval_model.search(prompt, top_k=1)
    top_row = retrieval_results.iloc[0]
    similarity = float(top_row['score'])
    code_retrieved = top_row['code']
    prompt_retrieved = top_row['prompt']
    if tracker is not None:
        emissions = tracker.stop()
    def count_tokens(messages, model="gpt-4"):
        try:
            import tiktoken
        except ImportError:
            return 0
        try:
            encoding = tiktoken.encoding_for_model(model)
        except Exception:
            encoding = tiktoken.get_encoding("cl100k_base")
        num_tokens = 0
        for msg in messages:
            num_tokens += 4
            for key, value in msg.items():
                num_tokens += len(encoding.encode(str(value)))
        num_tokens += 2
        return num_tokens
    system_content = (
        "You are an expert programming assistant helping undergraduate computer science students. "
        "You must only answer questions that are strictly about programming or code. "
        "If the user's request is not about programming or code, politely reply: 'Sorry, I can only help with programming/code questions.' "
        "Your task is to generate only the source code that solves the user's request. "
        "Output only the code, no explanation, no comments, no markdown."
    )
    messages = [
        {"role": "system", "content": system_content},
        {"role": "user", "content": prompt},
    ]
    token_count = count_tokens(messages)
    retrieval_token_info = {
        "token_input": token_count,
        "token_output": 0,
        "token_count": token_count,
        "note": "Output code diambil dari database, tidak ada proses generasi model. Hanya token input yang dihitung."
    }
    def _get_impact(emissions, token_count=None):
        if token_count is None:
            try:
                import tiktoken
                encoding = tiktoken.encoding_for_model("gpt-4")
                num_tokens = 4 + len(encoding.encode(str(code_retrieved))) + 2
                token_count = num_tokens
            except Exception:
                token_count = len(str(code_retrieved).split())
        impact = compute_environmental_impact(token_count)
        return impact
    return {
        "similarity": similarity,
        "code_retrieved": code_retrieved,
        "prompt_retrieved": prompt_retrieved,
        "retrieval_token_info": retrieval_token_info,
        "impact": _get_impact(emissions)
    }

def handle_model_selection(model_choice, user_id, session_id, prompt, chat_history):
    """Handle model selection and response."""
    system_content = (
        "You are an expert programming assistant helping undergraduate computer science students. "
        "You must only answer questions that are strictly about programming or code. "
        "If the user's request is not about programming or code, politely reply: 'Sorry, I can only help with programming/code questions.' "
        "Your task is to generate only the source code that solves the user's request. "
        "Output only the code, no explanation, no comments, no markdown."
    )
    messages = [{"role": "system", "content": system_content}]
    for row in chat_history:
        messages.append({"role": row["role"], "content": row["content"]})
    if model_choice == "openai":
        job_id = insert_gpt_job(user_id, prompt, prompt, status="pending")
        gamification = get_user_token_info(user_id, session_id)
        return jsonify({
            "mode": "gpt-queued",
            "job_id": job_id,
            "message": "Permintaan Anda sedang diproses oleh ChatGPT. Silakan cek status dengan job_id ini di endpoint /check-status/{job_id}.",
            "gamification": gamification
        }), 202
    elif model_choice == "qwen":
        return jsonify({
            "mode": "qwen",
            "message": "Qwen API belum diintegrasikan. Silakan tambahkan endpoint Qwen di sini.",
            "code": None
        }), 501
    elif model_choice == "deepseek":
        return jsonify({
            "mode": "deepseek",
            "message": "DeepSeek API belum diintegrasikan. Silakan tambahkan endpoint DeepSeek di sini.",
            "code": None
        }), 501
    else:
        return jsonify({"error": "Model tidak dikenali. Pilih salah satu: openai, qwen, deepseek."}), 400

@app.route('/generate-code', methods=['POST'])
@limiter.limit("6 per minute")
def generate_code():
    data = request.get_json(silent=True) or {}
    prompt = data.get("prompt")
    model_choice = data.get("model", "openai")
    if not prompt or not isinstance(prompt, str):
        return jsonify({"error": "Missing or invalid 'prompt' in request body"}), 400

    retrieval_result = handle_semantic_retrieval(prompt)
    if retrieval_result:
        similarity = retrieval_result["similarity"]
        code_retrieved = retrieval_result["code_retrieved"]
        prompt_retrieved = retrieval_result["prompt_retrieved"]
        retrieval_token_info = retrieval_result["retrieval_token_info"]
        impact = retrieval_result["impact"]
        user_id = session.get("user_id")
        session_id = session.get("session_id") or request.remote_addr
        if similarity >= 0.95:
            log_token_usage(user_id, session_id, retrieval_token_info["token_count"])
            gamification = get_user_token_info(user_id)
            update_user_total_points_if_new_week(user_id, gamification["points"])
            return jsonify({
                "mode": "retrieval",
                "similarity": similarity,
                "prompt_matched": prompt_retrieved,
                "code": code_retrieved,
                "message": "Kode ditemukan di database dengan similarity >=95%. Jawaban diambil dari database.",
                "environmental_impact": impact,
                "token_info": retrieval_token_info,
                "gamification": gamification
            }), 200
        elif similarity >= 0.8:
            log_token_usage(user_id, session_id, retrieval_token_info["token_count"])
            gamification = get_user_token_info(user_id, session_id)
            update_user_total_points_if_new_week(user_id, gamification["points"])
            return jsonify({
                "mode": "suggestion",
                "similarity": similarity,
                "prompt_matched": prompt_retrieved,
                "code": code_retrieved,
                "message": "Ditemukan kode mirip di database (similarity 80–95%). Jika ingin jawaban lebih spesifik, balas dengan 'GPT Mode'.",
                "environmental_impact": impact,
                "token_info": retrieval_token_info,
                "gamification": gamification
            }), 200
        # else: similarity < 0.8, fallback ke model pilihan

    user_id = session.get("user_id")
    if not user_id:
        return jsonify({"error": "Unauthorized. Silakan login."}), 401
    session_id = session.get("session_id")
    if not session_id:
        session_id = request.remote_addr
        session["session_id"] = session_id
    save_chat_message(user_id, session_id, "user", prompt)
    chat_history = get_chat_history(user_id, session_id, limit=10)
    return handle_model_selection(model_choice, user_id, session_id, prompt, chat_history)


@app.route('/check-status/<job_id>', methods=['GET'])
def check_status(job_id):
    job = get_gpt_job(job_id)
    if not job:
        return jsonify({"status": "not_found", "message": "Job ID tidak ditemukan."}), 404
    if job["status"] == "pending":
        return jsonify({"status": "pending", "message": "Pertanyaan Anda masih dalam antrian, silakan tunggu."}), 200
    if job["status"] == "done":
        # Simpan code dan embedding ke code_embeddings, environmental impact ke environtmental_impact_logs
        try:
            # import uuid
            # import json
            # from langdetect import detect
            # Pastikan code dan prompt tidak kosong
            code = job.get("code")
            prompt = job.get("prompt")
            if not code or not prompt:
                print(f"[ERROR] Empty code or prompt for job {job.get('job_id')}")
                return jsonify({"status": "error", "message": "Empty code or prompt."}), 500
            emb = get_ensemble_embedding(prompt, weights=best_weights)
            emb = emb[0] if hasattr(emb, '__len__') and len(emb.shape) > 1 else emb
            emb_list = [float(x) for x in emb]
            # Hitung token_count dan environmental impact
            def count_tokens(messages, model="gpt-4"):
                try:
                    import tiktoken
                except ImportError:
                    return 0
                try:
                    encoding = tiktoken.encoding_for_model(model)
                except Exception:
                    encoding = tiktoken.get_encoding("cl100k_base")
                num_tokens = 0
                for msg in messages:
                    num_tokens += 4
                    for key, value in msg.items():
                        num_tokens += len(encoding.encode(str(value)))
                num_tokens += 2
                print(f"[DEBUG] count_tokens: {num_tokens}")
                return num_tokens
            def count_tokens_text(text, model="gpt-4"):
                try:
                    import tiktoken
                except ImportError:
                    return len(str(text).split())
                try:
                    encoding = tiktoken.encoding_for_model(model)
                except Exception:
                    encoding = tiktoken.get_encoding("cl100k_base")
                print(f"[DEBUG]: {len(encoding.encode(str(text)))}")
                return len(encoding.encode(str(text)))
            messages = [
                {"role": "system", "content": "You are an expert programming assistant helping undergraduate computer science students. Output only the code, no explanation, no comments, no markdown."},
                {"role": "user", "content": prompt},
            ]
            # Token input (prompt)
            token_input = count_tokens(messages)
            # Token output (code generated by GPT)
            token_output = count_tokens_text(code)
            token_count = token_input + token_output
            impact = compute_environmental_impact(token_count)
            # Update token user (kurangi token setelah GPT selesai)
            user_id = job["user_id"]
            session_id = request.remote_addr or "default"
            log_token_usage(user_id, session_id, token_count)
            gamification = get_user_token_info(user_id, session_id)
            update_user_total_points_if_new_week(user_id, gamification["points"])
            # VALIDASI: Jangan insert jika embedding kosong/null/array kosong
            if emb_list is None or not isinstance(emb_list, list) or len(emb_list) == 0:
                print(f"[WARNING] Embedding kosong, tidak disimpan ke code_embeddings untuk job {job.get('job_id')}")
            else:
                conn = get_db_connection()
                try:
                    with conn.cursor() as cur:
                        # Cek apakah sudah ada entry dengan user_id, prompt, dan code yang sama
                        cur.execute(
                            "SELECT id FROM code_embeddings WHERE user_id=%s AND prompt=%s AND code=%s LIMIT 1",
                            (job["user_id"], prompt, code)
                        )
                        exists = cur.fetchone()
                        if exists:
                            print(f"[INFO] Duplicate entry detected, skip insert for job {job.get('job_id')}")
                        else:
                            embedding_id = str(uuid.uuid4())
                            # Simpan ke code_embeddings (prompt, code, embedding)
                            # DEBUG: Print active DB and table structure before insert
                            cur.execute("SELECT DATABASE() AS db")
                            db_row = cur.fetchone()
                            print(f"[DEBUG] Active DB: {db_row['db']}")
                            cur.execute("SHOW CREATE TABLE code_embeddings;")
                            table_row = cur.fetchone()
                            print(f"[DEBUG] SHOW CREATE TABLE code_embeddings: {table_row}")
                            # Lakukan insert
                            cur.execute(
                                "INSERT INTO code_embeddings (id, user_id, prompt, code, embedding, created_at) VALUES (%s, %s, %s, %s, %s, NOW())",
                                (
                                    embedding_id,
                                    job["user_id"],
                                    prompt,
                                    code,
                                    json.dumps(emb_list)
                                )
                            )
                            # Simpan environmental impact ke environtmental_impact_logs
                            impact_id = str(uuid.uuid4())
                            cur.execute(
                                "INSERT INTO environmental_impact_logs (id, user_id, job_id, energy_wh, energy_kwh, carbon_kg, water_ml, created_at) VALUES (%s, %s, %s, %s, %s, %s, %s, NOW())",
                                (
                                    impact_id,
                                    job["user_id"],
                                    job["job_id"],
                                    float(impact["energy_wh"]),
                                    float(impact["energy_kwh"]),
                                    float(impact["carbon_kg"]),
                                    float(impact["water_ml"])
                                )
                            )
                            # Log local carbon emission to local_carbon_logs (if available)
                            try:
                                import csv, os
                                csv_path = os.path.join(os.getcwd(), "emissions.csv")
                                if os.path.exists(csv_path):
                                    with open(csv_path, "r", encoding="utf-8") as f:
                                        rows = list(csv.reader(f))
                                        if len(rows) >= 2:
                                            header = rows[0]
                                            last_row = rows[-1]
                                            idx_emissions = header.index("emissions")
                                            local_carbon_kg = float(last_row[idx_emissions])
                                            # Insert to local_carbon_logs
                                            local_id = str(uuid.uuid4())
                                            server_name = os.getenv("SERVER_NAME", "default_server")
                                            cur.execute(
                                                "INSERT INTO local_carbon_logs (id, server_name, carbon_kg, created_at) VALUES (%s, %s, %s, NOW())",
                                                (local_id, server_name, local_carbon_kg)
                                            )
                            except Exception as e:
                                print(f"[WARNING] Could not log local carbon emission: {e}")
                    conn.commit()
                finally:
                    conn.close()
            # Environmental impact calculation for GPT result
            code = job.get("code")
            session_id = request.remote_addr or "default"
            update_session_tokens(job["user_id"], session_id, token_count)
            # Setelah semua proses selesai, hapus job dari gpt_jobs
            try:
                conn_cleanup = get_db_connection()
                with conn_cleanup.cursor() as cur:
                    cur.execute("DELETE FROM gpt_jobs WHERE job_id=%s", (job_id,))
                conn_cleanup.commit()
            except Exception as e:
                print(f"[WARNING] Failed to cleanup gpt_jobs: {e}")
            finally:
                try:
                    conn_cleanup.close()
                except Exception:
                    pass
            return jsonify({
                "status": "done",
                "code": job["code"],
                "environmental_impact": impact,
                "gamification": gamification
            }), 200
        except Exception as e:
            print(f"[ERROR] Could not save GPT answer to embedding DB: {e}")
            return jsonify({"status": "error", "message": "Internal error saving GPT answer."}), 500
    if job["status"] == "error":
        return jsonify({"status": "error", "message": job.get("error", "Unknown error")}), 500

def save_global_emissions():
    if global_tracker is not None:
        try:
            emissions = global_tracker.stop()
            # Simpan ke DB atau file
            if emissions is not None:
                import uuid, os
                conn = None
                try:
                    conn = get_db_connection()
                    with conn.cursor() as cur:
                        impact_id = str(uuid.uuid4())
                        server_name = os.getenv("SERVER_NAME", "default_server")
                        cur.execute(
                            "INSERT INTO local_carbon_logs (id, server_name, carbon_kg, created_at) VALUES (%s, %s, %s, NOW())",
                            (impact_id, server_name, getattr(emissions, "emissions", 0))
                        )
                    conn.commit()
                except Exception as e:
                    print(f"[WARNING] Could not log global carbon emission: {e}")
                finally:
                    if conn:
                        conn.close()
        except Exception as e:
            print(f"[WARNING] Error stopping global emissions tracker: {e}")

if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('--worker', action='store_true', help='Run GPT job worker')
    args = parser.parse_args()
    if global_tracker is not None:
        try:
            global_tracker.start()
        except Exception as e:
            print(f"[WARNING] Could not start global emissions tracker: {e}")
    atexit.register(save_global_emissions)
    if args.worker:
        gpt_job_worker()
    else:
        app.run(debug=True)
