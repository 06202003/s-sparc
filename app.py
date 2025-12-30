import sys
import datetime
import hashlib
import pymysql
import os
import openai
import logging
import joblib
import numpy as np
import pandas as pd
import threading
import queue
import time
import uuid
import atexit
import json
from sqlalchemy import create_engine
from flask import Flask, request, jsonify, session
from flask_cors import CORS
from flask_limiter import Limiter
from flask_limiter.util import get_remote_address
from dotenv import load_dotenv
from langdetect import detect
try:
    from codecarbon import OfflineEmissionsTracker
except ImportError:
    OfflineEmissionsTracker = None

# === GLOBAL EMISSIONS TRACKER ===
global_tracker = None
if OfflineEmissionsTracker is not None:
    global_tracker = OfflineEmissionsTracker(
        measure_power_secs=10,  # lebih jarang, supaya ringan
        log_level="error",
        country_iso_code="IDN",
        output_dir="."
    )

# Load environment variables from .env file
load_dotenv()

# === Environmental Impact Calculation Constants and Function (Refactored) ===
# All values derived from energy consumption using PUE, WUE, and CIF. No legacy constants remain.

OPENAI_MODEL = "gpt-4"  # ganti dari gpt-3.5-turbo ke gpt-4
DEFAULT_LIMITS = ["100 per day", "10 per minute"]

# Scientific constants (do not modify)
PUE = 1.12 #1.32
WUE_SITE_L_PER_KWH = 0.30
WUE_SOURCE_L_PER_KWH = 4.35
CIF_KG_PER_KWH = 0.384

# Energy per token (Wh)
# Sumber estimasi energi per token:
# - Jegham et al., 2023. "HowHungry is AI? Benchmarking Energy, Water, and Carbon Footprint of LLM Inference" (arXiv:2505.09598)
# - Strubell et al., 2019. "Energy and Policy Considerations for Deep Learning in NLP" (ACL 2019)
# - Angka ini disesuaikan dengan range konsumsi energi inference model GPT-3/4 pada cloud (lihat Tabel 1 & 2 Lottick et al. 2023, serta diskusi Section 4.2)
ENERGY_PER_TOKEN_WH_SHORT = 0.0021775
ENERGY_PER_TOKEN_WH_MEDIUM = 0.0015805
ENERGY_PER_TOKEN_WH_LONG = 0.00042026

def update_user_total_points_if_new_week(user_id, tokens_to_add):
    """Tambah poin akumulatif berdasarkan jumlah token yang dipakai.

    Sebelumnya fungsi ini mencoba mengakumulasi "sisa kuota mingguan" per minggu.
    Untuk menyederhanakan dan menghindari duplikasi, sekarang setiap aksi akan
    menambah total_points dengan tokens_to_add secara langsung.
    """
    if not user_id or not tokens_to_add or tokens_to_add <= 0:
        return
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            now = datetime.datetime.now()
            cur.execute("SELECT total_points FROM user_points WHERE user_id=%s", (user_id,))
            row = cur.fetchone()
            if not row:
                cur.execute(
                    "INSERT INTO user_points (user_id, total_points, updated_at) VALUES (%s, %s, %s)",
                    (user_id, tokens_to_add, now),
                )
            else:
                cur.execute(
                    "UPDATE user_points SET total_points = total_points + %s, updated_at = %s WHERE user_id = %s",
                    (tokens_to_add, now, user_id),
                )
        conn.commit()
    finally:
        conn.close()

# === Gamification Utilities: Insert per aksi, agregat mingguan ===
def log_token_usage(user_id, session_id, tokens_used):
    """
    Insert log penggunaan token ke session_tokens (sekarang sebagai log/audit trail).
    Setiap aksi, insert baris baru dengan tokens_used dan used_at.
    """
    if not user_id or not session_id:
        raise ValueError("user_id and session_id are required for token usage log")
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            now = datetime.datetime.now()
            # Kolom id (PK, UUID), tokens_used, used_at harus ada di tabel session_tokens
            cur.execute(
                "INSERT INTO session_tokens (id, user_id, session_id, tokens_used, used_at) VALUES (%s, %s, %s, %s, %s)",
                (str(uuid.uuid4()), user_id, session_id, tokens_used, now)
            )
        conn.commit()
    finally:
        conn.close()

# === Gamification Utilities ===
def get_user_token_info(user_id, session_id):
    """
    Always session-based. If no row, create with full tokens for this session. Never allow session_id=None.
    """
    if not user_id or not session_id:
        raise ValueError("user_id and session_id are required for token info")
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            now = datetime.datetime.now()
            # Hitung total token yang sudah dipakai minggu ini (semua session user)
            cur.execute(
                "SELECT COALESCE(SUM(tokens_used), 0) AS used_this_week "
                "FROM session_tokens WHERE user_id=%s AND YEARWEEK(used_at, 1) = YEARWEEK(%s, 1)",
                (user_id, now),
            )
            row = cur.fetchone() or {"used_this_week": 0}
            used_this_week = row.get("used_this_week", 0) or 0

            # Pastikan nilai yang dikirim ke frontend tidak melebihi kuota mingguan
            # sehingga kartu tidak pernah menampilkan "Sudah terpakai: 2000" jika
            # sebenarnya total pemakaian < 2000.
            capped_used = min(used_this_week, 2000)
            remaining_tokens = max(0, 2000 - capped_used)

            return {
                "total_tokens": 2000,
                "used_tokens": capped_used,
                "remaining_tokens": remaining_tokens,
                # Poin aktif didefinisikan sebagai sisa kuota minggu ini
                "points": remaining_tokens,
                # Opsional: kirim juga nilai mentah untuk debugging/analisis jika dibutuhkan
                "used_tokens_raw": used_this_week,
            }
    finally:
        conn.close()


def insert_environmental_impact_log(user_id, job_id, course_id, assessment_id, impact):
    """Simpan satu baris jejak environmental impact untuk sebuah job.

    Dipisahkan dari logika penyimpanan embedding supaya selalu tercatat,
    meskipun embedding duplikat atau tidak disimpan.
    """
    if not user_id or not job_id or not impact:
        return
    try:
        energy_wh = float(impact.get("energy_wh", 0.0))
        energy_kwh = float(impact.get("energy_kwh", 0.0))
        carbon_kg = float(impact.get("carbon_kg", 0.0))
        water_ml = float(impact.get("water_ml", 0.0))
    except Exception as e:
        print(f"[WARNING] Invalid impact payload, skip log: {e}")
        return

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            impact_id = str(uuid.uuid4())
            try:
                # Skema baru dengan course_id dan assessment_id
                cur.execute(
                    "INSERT INTO environmental_impact_logs (id, user_id, job_id, course_id, assessment_id, energy_wh, energy_kwh, carbon_kg, water_ml, created_at) "
                    "VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())",
                    (
                        impact_id,
                        user_id,
                        job_id,
                        course_id,
                        assessment_id,
                        energy_wh,
                        energy_kwh,
                        carbon_kg,
                        water_ml,
                    ),
                )
            except Exception as e:
                # Fallback: jika kolom course_id/assessment_id belum ada, simpan tanpa kolom tersebut
                msg = str(e)
                if "Unknown column 'course_id'" in msg or "Unknown column 'assessment_id'" in msg:
                    print("[WARNING] environmental_impact_logs without course/assessment columns, using legacy insert")
                    cur.execute(
                        "INSERT INTO environmental_impact_logs (id, user_id, job_id, energy_wh, energy_kwh, carbon_kg, water_ml, created_at) "
                        "VALUES (%s, %s, %s, %s, %s, %s, %s, NOW())",
                        (
                            impact_id,
                            user_id,
                            job_id,
                            energy_wh,
                            energy_kwh,
                            carbon_kg,
                            water_ml,
                        ),
                    )
                else:
                    raise
        conn.commit()
    except Exception as e:
        print(f"[WARNING] Could not insert environmental_impact_logs: {e}")
    finally:
        conn.close()



# === Chat History Utilities ===
def save_chat_message(user_id, session_id, role, content, assessment_id=None):
    """Simpan 1 pesan chat.

    assessment_id digunakan untuk mengelompokkan chat per assessment/mata kuliah.
    """
    conn = get_db_connection()
    import uuid
    try:
        with conn.cursor() as cur:
            cur.execute(
                "INSERT INTO chat_history (id, user_id, session_id, assessment_id, role, content) "
                "VALUES (%s, %s, %s, %s, %s, %s)",
                (str(uuid.uuid4()), user_id, session_id, assessment_id, role, content)
            )
        conn.commit()
    finally:
        conn.close()


def get_chat_history(user_id, session_id, assessment_id=None, limit=10):
    """Ambil riwayat chat terakhir.

    Jika assessment_id diberikan, filter juga berdasarkan assessment_id
    supaya riwayat per assessment terpisah.
    """
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            if assessment_id is not None:
                cur.execute(
                    "SELECT role, content FROM chat_history "
                    "WHERE user_id=%s AND session_id=%s AND assessment_id=%s "
                    "ORDER BY created_at DESC LIMIT %s",
                    (user_id, session_id, assessment_id, limit)
                )
            else:
                cur.execute(
                    "SELECT role, content FROM chat_history "
                    "WHERE user_id=%s AND session_id=%s "
                    "ORDER BY created_at DESC LIMIT %s",
                    (user_id, session_id, limit)
                )
            rows = cur.fetchall()
            return rows[::-1]  # oldest to newest
    finally:
        conn.close()

# SQLAlchemy engine for pandas read_sql (best practice)
def get_sqlalchemy_engine():
    # Use env vars for connection
    user = os.getenv("MYSQL_USER", "root")
    password = os.getenv("MYSQL_PASSWORD", "")
    host = os.getenv("MYSQL_HOST", "localhost")
    db = os.getenv("MYSQL_DB", "db_semantic")
    return create_engine(f"mysql+pymysql://{user}:{password}@{host}/{db}?charset=utf8mb4")

# === Environmental Impact Calculation Function ===
def compute_environmental_impact(token_count: int) -> dict:
    """
    Compute the environmental impact of a model inference based on token count.
    All values are derived from energy consumption using PUE, WUE, and CIF.

    Methodology:
        - Energy (Wh, kWh) is calculated using per-token energy rates by prompt size bucket.
        - Carbon (kg CO2e) is calculated as: energy_kwh * CIF_KG_PER_KWH
        - Water (mL) is calculated as:
            water_L = (energy_kwh / PUE) * WUE_SITE_L_PER_KWH + energy_kwh * WUE_SOURCE_L_PER_KWH
            water_ml = water_L * 1000.0


    Sources:
        - PUE: "Data Center Power Usage Effectiveness Trends" (Uptime Institute, 2022), https://uptimeinstitute.com/about-ui/press-releases/uptime-institute-2022-data-center-industry-survey-results
        - WUE: "Water Usage Effectiveness (WUE) in Data Centers: 2022 Update" (Uptime Institute, 2022), https://uptimeinstitute.com/2022-water-usage-effectiveness
        - CIF: IEA Emissions Factors 2023 (Indonesia grid), https://www.iea.org/data-and-statistics/data-product/emissions-factors-2023
        - LCA for AI: "The Carbon Footprint of ChatGPT" (Lottick et al., 2023), https://arxiv.org/abs/2304.03271

    Args:
        token_count (int): Number of tokens processed. Must be > 0.
    Returns:
        dict: {
            "energy_wh": float,   # Watt-hours
            "energy_kwh": float,  # Kilowatt-hours
            "carbon_kg": float,   # kg CO2e
            "water_ml": float     # milliliters
        }
    Raises:
        ValueError: If token_count <= 0
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
    val = os.getenv(var)
    if not val and default is None:
        print(f"[WARNING] Environment variable {var} is not set!")
    return val or default

app = Flask(__name__)
CORS(app, supports_credentials=True)
app.secret_key = _warn_env("FLASK_SECRET_KEY", "supersecretkey")

def get_db_connection():
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


@app.route('/courses', methods=['GET'])
@require_login
def list_courses():
    """Kembalikan daftar mata kuliah untuk user yang sedang login.

    Data diambil berdasarkan relasi IAM di tabel user_courses.
    """
    user_id = session.get("user_id")
    if not user_id:
        return jsonify({"error": "Unauthorized. Silakan login."}), 401

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            # Ambil hanya mata kuliah yang terhubung dengan user di tabel user_courses
            cur.execute(
                """
                SELECT c.course_id, c.code, c.name
                FROM courses c
                INNER JOIN user_courses uc ON uc.course_id = c.course_id
                WHERE uc.user_id = %s
                ORDER BY c.name ASC
                """,
                (user_id,)
            )
            rows = cur.fetchall() or []
        return jsonify({"courses": rows}), 200
    except Exception as e:
        print(f"[ERROR] list_courses: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        conn.close()


@app.route('/assessments', methods=['GET'])
@require_login
def list_assessments():
    """Kembalikan daftar assessment.

    Jika query param course_id diisi, hanya kembalikan assessment untuk course tersebut."""
    course_id = request.args.get('course_id')
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            if course_id:
                cur.execute(
                    "SELECT assessment_id, course_id, code, name FROM assessments "
                    "WHERE course_id=%s ORDER BY created_at ASC",
                    (course_id,)
                )
            else:
                cur.execute(
                    "SELECT assessment_id, course_id, code, name FROM assessments "
                    "ORDER BY created_at ASC"
                )
            rows = cur.fetchall() or []
        return jsonify({"assessments": rows}), 200
    except Exception as e:
        print(f"[ERROR] list_assessments: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        conn.close()


@app.route('/gamification', methods=['GET'])
@require_login
def get_gamification():
    """Kembalikan informasi gamifikasi/token untuk user saat ini.

    Data diambil dari log penggunaan token mingguan.
    """
    user_id = session.get("user_id")
    if not user_id:
        return jsonify({"error": "Unauthorized. Silakan login."}), 401

    # Gunakan session_id Flask jika ada, fallback ke IP client
    session_id = session.get("session_id") or request.remote_addr
    try:
        gamification = get_user_token_info(user_id, session_id)
        return jsonify({"gamification": gamification}), 200
    except ValueError as e:
        return jsonify({"error": str(e)}), 400

# Initialize Flask-Limiter
limiter = Limiter(
    get_remote_address,
    app=app,
    default_limits=DEFAULT_LIMITS,
)

# Configure OpenAI API key
OPENAI_API_KEY = os.getenv("OPENAI_API_KEY")
openai.api_key = OPENAI_API_KEY

# Create OpenAI client for openai>=1.0.0
try:
    client = openai.OpenAI(api_key=OPENAI_API_KEY) if OPENAI_API_KEY else openai.OpenAI()
except Exception:
    client = openai.OpenAI()  # fallback

# --- DB-based GPT Job and Session Token Management ---

def insert_gpt_job(user_id, prompt, gpt_prompt, status="pending", lock_timeout=10):
    """Insert GPT job dengan proteksi race condition.

    Menggunakan MySQL advisory lock berbasis hash prompt sehingga
    dua request dengan prompt yang sama tidak akan membuat dua job berbeda.
    """
    # Normalisasi prompt yang akan dipakai sebagai key
    if not isinstance(gpt_prompt, str) or not gpt_prompt.strip():
        raise ValueError("Prompt must be non-empty string.")
    norm_prompt = gpt_prompt.strip()
    if len(norm_prompt) > 4096:
        raise ValueError("Prompt too long.")

    # Gunakan hash sebagai key lock, pastikan <= 64 char (batas MySQL GET_LOCK)
    # hexdigest SHA-256 panjangnya 64, jadi kita pakai prefix pendek + potongan hash
    full_hash = hashlib.sha256(norm_prompt.encode("utf-8")).hexdigest()
    lock_name = "gpt:" + full_hash[:60]  # total panjang 64

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            # Coba ambil advisory lock untuk prompt ini
            try:
                cur.execute("SELECT GET_LOCK(%s, %s)", (lock_name, lock_timeout))
                row = cur.fetchone() or {}
                got_lock = list(row.values())[0] if row else 0
            except Exception as e:
                print(f"[WARNING] GET_LOCK failed, fallback to simple insert: {e}")
                got_lock = 0

            # Kalau tidak dapat lock (timeout/failed), fallback ke perilaku lama
            if got_lock != 1:
                job_id = str(uuid.uuid4())
                try:
                    cur.execute(
                        "INSERT INTO gpt_jobs (job_id, user_id, prompt, status, created_at, updated_at) "
                        "VALUES (%s, %s, %s, %s, NOW(), NOW())",
                        (job_id, user_id, norm_prompt, status)
                    )
                    conn.commit()
                except Exception as e:
                    print(f"[ERROR] insert_gpt_job (no-lock): {e}")
                    raise
                return job_id

            # DAPAT LOCK → cek dulu apakah sudah ada job pending untuk prompt ini
            cur.execute(
                "SELECT job_id FROM gpt_jobs WHERE prompt=%s AND status='pending' "
                "ORDER BY created_at ASC LIMIT 1",
                (norm_prompt,)
            )
            existing = cur.fetchone()
            if existing and existing.get("job_id"):
                job_id = existing["job_id"]
            else:
                job_id = str(uuid.uuid4())
                try:
                    cur.execute(
                        "INSERT INTO gpt_jobs (job_id, user_id, prompt, status, created_at, updated_at) "
                        "VALUES (%s, %s, %s, %s, NOW(), NOW())",
                        (job_id, user_id, norm_prompt, status)
                    )
                except Exception as e:
                    print(f"[ERROR] insert_gpt_job (locked insert): {e}")
                    raise
            conn.commit()
    finally:
        # Lepaskan lock kalau mungkin
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT RELEASE_LOCK(%s)", (lock_name,))
        except Exception:
            pass
        conn.close()
    return job_id

def update_gpt_job(job_id, code=None, status=None, error=None, similarity=None, prompt_matched=None):
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
    import uuid
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT total_tokens FROM session_tokens WHERE session_id=%s", (session_id,))
            row = cur.fetchone()
            if row:
                cur.execute("UPDATE session_tokens SET total_tokens=total_tokens+%s, updated_at=NOW() WHERE session_id=%s", (token_count, session_id))
            else:
                # If session_id is not a valid UUID, generate a new one
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
    """
    Worker sederhana: ambil job 'pending', jalankan GPT, update hasil ke DB.
    Jalankan di thread/terminal terpisah.
    """
    print("[WORKER] GPT job worker started.")
    while True:
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT job_id, user_id, prompt FROM gpt_jobs WHERE status='pending' ORDER BY created_at ASC LIMIT 1")
                job = cur.fetchone()
            if not job:
                conn.close()
                time.sleep(sleep_time)
                continue
            job_id = job['job_id']
            user_id = job['user_id']
            prompt = job['prompt']
            print(f"[WORKER] Processing job {job_id}")
            # Ambil session_id dan assessment_id dari chat_history jika ada, fallback ke user_id
            session_id = None
            assessment_id = None
            try:
                conn2 = get_db_connection()
                with conn2.cursor() as cur2:
                    cur2.execute(
                        "SELECT session_id, assessment_id FROM chat_history "
                        "WHERE user_id=%s ORDER BY created_at DESC LIMIT 1",
                        (user_id,)
                    )
                    row = cur2.fetchone()
                    if row and row.get("session_id"):
                        session_id = row["session_id"]
                        assessment_id = row.get("assessment_id")
            except Exception:
                session_id = None
            finally:
                try:
                    conn2.close()
                except Exception:
                    pass
            if not session_id:
                session_id = user_id
            # Jalankan GPT
            try:
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
                # For openai>=1.0.0 (correct usage)
                response = openai.chat.completions.create(
                    model=OPENAI_MODEL,
                    messages=messages,
                    temperature=0.2,
                    max_tokens=512,
                )
                code = response.choices[0].message.content.strip()
                # Simpan jawaban assistant ke chat_history jika session_id tersedia
                if user_id and session_id:
                    save_chat_message(user_id, session_id, "assistant", code, assessment_id)
                # Hitung total token (system + user) dan update session tokens
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
                # Update session tokens jika user_id tersedia
                if user_id:
                    update_session_tokens(user_id, session_id or user_id, token_count)
                update_gpt_job(job_id, code=code, status="done")
                print(f"[WORKER] Job {job_id} done. Token used: {token_count}")
            except Exception as e:
                print(f"[WORKER] Error running GPT for job {job_id}: {e}")
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


@app.route('/generate-code', methods=['POST'])
@limiter.limit("6 per minute")
def generate_code():
    data = request.get_json(silent=True) or {}
    prompt = data.get("prompt")
    assessment_id = data.get("assessment_id")  # penanda assessment/mata kuliah
    if not prompt or not isinstance(prompt, str):
        return jsonify({"error": "Missing or invalid 'prompt' in request body"}), 400

    # Flag khusus: paksa langsung ke GPT, lewati retrieval
    force_gpt = False
    FORCE_PREFIX = "__force_gpt__ "
    if isinstance(prompt, str) and prompt.startswith(FORCE_PREFIX):
        force_gpt = True
        prompt = prompt[len(FORCE_PREFIX):].strip()

    # SEMANTIC RETRIEVAL (always refresh from DB)
    retrieval_model = refresh_retrieval_model_from_db()
    if (not force_gpt) and retrieval_model is not None and retrieval_model.index is not None and not retrieval_model.df.empty:
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

        # Always include system prompt in token counting for retrieval
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

        # Jelaskan bahwa token output tidak dihitung di retrieval mode
        retrieval_token_info = {
            "token_input": token_count,
            "token_output": 0,
            "token_count": token_count,
            "note": "Output code diambil dari database, tidak ada proses generasi model. Hanya token input yang dihitung."
        }

        def _read_last_emissions_csv():
            import csv
            import os
            csv_path = os.path.join(os.getcwd(), "emissions.csv")
            if not os.path.exists(csv_path):
                return None
            try:
                with open(csv_path, "r", encoding="utf-8") as f:
                    rows = list(csv.reader(f))
                    if len(rows) < 2:
                        return None
                    header = rows[0]
                    last_row = rows[-1]
                    # Find the index for 'emissions' and 'energy_consumed'
                    try:
                        idx_emissions = header.index("emissions")
                        idx_energy = header.index("energy_consumed")
                        idx_duration = header.index("duration")
                        idx_cpu_energy = header.index("cpu_energy")
                        idx_gpu_energy = header.index("gpu_energy")
                        idx_ram_energy = header.index("ram_energy")
                    except Exception:
                        return None
                    try:
                        return {
                            "energy_wh": float(last_row[idx_energy]),
                            "carbon_kg": float(last_row[idx_emissions]),
                            "duration_s": float(last_row[idx_duration]),
                            "cpu_energy_wh": float(last_row[idx_cpu_energy]),
                            "gpu_energy_wh": float(last_row[idx_gpu_energy]),
                            "ram_energy_wh": float(last_row[idx_ram_energy]),
                            "water_ml": 0
                        }
                    except Exception:
                        return None
            except Exception:
                return None

        def _format_impact(emissions):
            if emissions is None:
                return None
            return {
                "energy_wh": getattr(emissions, "energy_consumed", 0),
                "carbon_kg": getattr(emissions, "emissions", 0),
                "duration_s": getattr(emissions, "duration", 0),
                "cpu_energy_wh": getattr(emissions, "cpu_energy", 0),
                "gpu_energy_wh": getattr(emissions, "gpu_energy", 0),
                "ram_energy_wh": getattr(emissions, "ram_energy", 0),
                "water_ml": 0
            }


        def _get_impact(emissions, token_count=None):
            """
            Compute environmental impact using only compute_environmental_impact and true token_count.
            Ignores emissions/carbon from CodeCarbon; all values are derived from energy.
            Args:
                emissions: (ignored, kept for API compatibility)
                token_count: (int) Number of tokens. Must be provided.
            Returns:
                dict: {"energy_wh", "energy_kwh", "carbon_kg", "water_ml"}
            """
            if token_count is None:
                # Try to infer from code_retrieved if available
                try:
                    import tiktoken
                    encoding = tiktoken.encoding_for_model("gpt-4")
                    num_tokens = 4 + len(encoding.encode(str(code_retrieved))) + 2
                    token_count = num_tokens
                except Exception:
                    token_count = len(str(code_retrieved).split())
            impact = compute_environmental_impact(token_count)
            return impact

        if similarity >= 0.95:
            impact = _get_impact(emissions)
            # Jawaban dari database bersifat gratis: tidak mengurangi kuota/poin
            user_id = session.get("user_id")
            session_id = session.get("session_id") or request.remote_addr
            gamification = get_user_token_info(user_id, session_id)
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
            impact = _get_impact(emissions)
            user_id = session.get("user_id")
            session_id = session.get("session_id") or request.remote_addr
            # Suggestion dari database juga gratis, hanya memberi kode referensi
            gamification = get_user_token_info(user_id, session_id)
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
        # else: similarity < 0.8, fallback to GPT

    # Fallback ke GPT jika similarity < 0.8 atau user balas 'GPT Mode'
    if not openai.api_key:
        return jsonify({"error": "OpenAI API key not configured"}), 500

    user_id = session.get("user_id")
    if not user_id:
        return jsonify({"error": "Unauthorized. Silakan login."}), 401

    # Gunakan session_id dari session Flask, atau fallback ke remote_addr
    session_id = session.get("session_id")
    if not session_id:
        session_id = request.remote_addr
        session["session_id"] = session_id

    # Cek trigger GPT Mode: ambil prompt terakhir dari chat_history jika user hanya mengetik "GPT Mode"
    if prompt.strip().lower() == "gpt mode":
        gpt_prompt = None
        try:
            conn_last = get_db_connection()
            with conn_last.cursor() as curl:
                if assessment_id:
                    curl.execute(
                        "SELECT content FROM chat_history WHERE user_id=%s AND session_id=%s AND role='user' AND content <> %s AND assessment_id=%s ORDER BY created_at DESC LIMIT 1",
                        (user_id, session_id, "GPT Mode", assessment_id),
                    )
                else:
                    curl.execute(
                        "SELECT content FROM chat_history WHERE user_id=%s AND session_id=%s AND role='user' AND content <> %s ORDER BY created_at DESC LIMIT 1",
                        (user_id, session_id, "GPT Mode"),
                    )
                row_last = curl.fetchone()
                if row_last and row_last.get("content"):
                    gpt_prompt = row_last["content"]
        except Exception as e_last:
            print(f"[WARNING] Failed to resolve last prompt for GPT Mode: {e_last}")
        finally:
            try:
                conn_last.close()
            except Exception:
                pass
        if not gpt_prompt:
            gpt_prompt = "Silakan masukkan ulang permintaan Anda."
    else:
        gpt_prompt = prompt

    # Simpan prompt user ke chat_history, dikelompokkan per assessment
    save_chat_message(user_id, session_id, "user", gpt_prompt, assessment_id)

    # Ambil riwayat chat terakhir (misal 10), difilter per assessment
    chat_history = get_chat_history(user_id, session_id, assessment_id, limit=10)

    system_content = (
        "You are an expert programming assistant helping undergraduate computer science students. "
        "You must only answer questions that are strictly about programming or code. "
        "If the user's request is not about programming or code, politely reply: 'Sorry, I can only help with programming/code questions.' "
        "Your task is to generate only the source code that solves the user's request. "
        "Output only the code, no explanation, no comments, no markdown."
    )

    # Gabungkan system prompt + chat history
    messages = [{"role": "system", "content": system_content}]
    for row in chat_history:
        messages.append({"role": row["role"], "content": row["content"]})

    # --- Queue GPT request ---
    # Menggunakan insert_gpt_job dengan advisory lock untuk menghindari
    # duplikasi job jika ada beberapa user menanyakan prompt yang sama
    job_id = insert_gpt_job(user_id, prompt, gpt_prompt, status="pending")
    # (worker thread/async processing not shown here)
    # Untuk GPT, token akan dikurangi saat job selesai (di /check-status)
    gamification = get_user_token_info(user_id, session_id)
    return jsonify({
        "mode": "gpt-queued",
        "job_id": job_id,
        "message": "Permintaan Anda sedang diproses karena antrian atau rate limit. Silakan cek status dengan job_id ini di endpoint /check-status/{job_id}.",
        "gamification": gamification
    }), 202


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
            # Tambah poin berdasarkan token yang dipakai
            update_user_total_points_if_new_week(user_id, token_count)
            gamification = get_user_token_info(user_id, session_id)
            # Coba deteksi assessment_id & course_id terbaru dari riwayat chat user
            assessment_id = None
            course_id = None
            try:
                conn_meta = get_db_connection()
                with conn_meta.cursor() as curm:
                    curm.execute(
                        "SELECT assessment_id FROM chat_history WHERE user_id=%s ORDER BY created_at DESC LIMIT 1",
                        (user_id,),
                    )
                    row_m = curm.fetchone()
                    if row_m and row_m.get("assessment_id"):
                        assessment_id = row_m["assessment_id"]
                        # Dari assessment_id ambil course_id
                        curm.execute(
                            "SELECT course_id FROM assessments WHERE assessment_id=%s LIMIT 1",
                            (assessment_id,),
                        )
                        row_c = curm.fetchone()
                        if row_c and row_c.get("course_id"):
                            course_id = row_c["course_id"]
            except Exception as e_meta:
                print(f"[WARNING] Could not resolve course/assessment for impact log: {e_meta}")
            finally:
                try:
                    conn_meta.close()
                except Exception:
                    pass
            # Catat environmental impact untuk job ini (selalu, terlepas dari embedding)
            insert_environmental_impact_log(user_id, job.get("job_id"), course_id, assessment_id, impact)
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
            # Catatan: gpt_jobs tidak lagi dihapus otomatis.
            # Riwayat job disimpan sebagai log, dan environmental_impact_logs
            # terhubung ke job_id untuk pelacakan.
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


@app.route('/impact-summary', methods=['GET'])
@require_login
def impact_summary():
    """Kembalikan ringkasan environmental impact untuk user yang sedang login.

    Data diambil dari tabel environmental_impact_logs dan diringkas per user.
    Saat ini range waktu default adalah 30 hari terakhir.
    """
    user_id = session.get("user_id")
    if not user_id:
        return jsonify({"error": "Unauthorized. Silakan login."}), 401

    days_param = request.args.get("days")
    try:
        days = int(days_param) if days_param is not None else 30
        if days <= 0:
            days = 30
    except ValueError:
        days = 30

    scope = request.args.get("scope", "all").lower().strip() or "all"
    course_id = request.args.get("course_id")
    assessment_id = request.args.get("assessment_id")

    where_clauses = ["user_id = %s", "created_at >= NOW() - INTERVAL %s DAY"]
    params_totals = [user_id, days]

    if scope == "course" and course_id:
        where_clauses.append("course_id = %s")
        params_totals.append(course_id)
    elif scope == "assessment" and assessment_id:
        where_clauses.append("assessment_id = %s")
        params_totals.append(assessment_id)

    where_sql = " AND ".join(where_clauses)

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            # Total agregat dalam range waktu + filter (all / course / assessment)
            cur.execute(
                """
                SELECT
                    COALESCE(SUM(energy_wh), 0) AS energy_wh,
                    COALESCE(SUM(energy_kwh), 0) AS energy_kwh,
                    COALESCE(SUM(carbon_kg), 0) AS carbon_kg,
                    COALESCE(SUM(water_ml), 0) AS water_ml
                FROM environmental_impact_logs
                WHERE """ + where_sql + """
                """,
                tuple(params_totals),
            )
            totals = cur.fetchone() or {
                "energy_wh": 0,
                "energy_kwh": 0,
                "carbon_kg": 0,
                "water_ml": 0,
            }

            # Breakdown harian untuk grafik/tabel (menggunakan WHERE yang sama)
            params_daily = list(params_totals)
            cur.execute(
                """
                SELECT
                    DATE(created_at) AS day,
                    COALESCE(SUM(energy_wh), 0) AS energy_wh,
                    COALESCE(SUM(energy_kwh), 0) AS energy_kwh,
                    COALESCE(SUM(carbon_kg), 0) AS carbon_kg,
                    COALESCE(SUM(water_ml), 0) AS water_ml
                FROM environmental_impact_logs
                WHERE """ + where_sql + """
                GROUP BY DATE(created_at)
                ORDER BY DATE(created_at) ASC
                """,
                tuple(params_daily),
            )
            rows = cur.fetchall() or []

        return jsonify({
            "range_days": days,
            "scope": scope,
            "course_id": course_id,
            "assessment_id": assessment_id,
            "totals": totals,
            "daily": rows,
        }), 200
    except Exception as e:
        print(f"[ERROR] impact_summary: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        conn.close()

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
