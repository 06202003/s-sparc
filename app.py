import sys
import re
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


app = Flask(__name__)
CORS(app, supports_credentials=True)

# --- Helper: Validate env and warn if missing (moved early so secret_key can use it)
def _warn_env(var, default=None):
    val = os.getenv(var)
    if not val and default is None:
        print(f"[WARNING] Environment variable {var} is not set!")
    return val or default

app.secret_key = _warn_env("FLASK_SECRET_KEY", "supersecretkey")
def require_login(func):
    from functools import wraps
    @wraps(func)
    def wrapper(*args, **kwargs):
        if "user_id" not in session:
            return jsonify({"error": "Unauthorized. Silakan login."}), 401
        return func(*args, **kwargs)
    return wrapper

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


@app.route('/assessment-leaderboard', methods=['GET'])
@require_login
def assessment_leaderboard():
    """
    Return leaderboard for a given assessment_id.
    Params: assessment_id (query) or use session['assessment_id'] if present.
    Response: { assessment_id, leaderboard: [{user_id, username, points, rank}], user_rank }
    """
    user_id = session.get('user_id')
    if not user_id:
        return jsonify({"error": "Unauthorized. Silakan login."}), 401

    assessment_id = request.args.get('assessment_id') or session.get('assessment_id')
    if not assessment_id:
        return jsonify({"error": "Missing assessment_id"}), 400

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            now = datetime.datetime.now()
            # Compute per-user remaining for the specific assessment (same logic as token_usage_breakdown.by_assessment)
            try:
                cur.execute(
                    "SELECT st.user_id AS user_id, COALESCE(u.username,'') AS username, COALESCE(SUM(st.tokens_used),0) AS total_used "
                    "FROM session_tokens st LEFT JOIN users u ON st.user_id = u.user_id "
                    "WHERE st.assessment_id = %s AND YEARWEEK(st.used_at, 1) = YEARWEEK(%s, 1) "
                    "GROUP BY st.user_id ORDER BY total_used DESC",
                    (assessment_id, now),
                )
                rows = cur.fetchall() or []
                leaderboard = []
                for r in rows:
                    used = int(r.get('total_used', 0) or 0)
                    remaining = max(0, 2000 - used)
                    leaderboard.append({
                        'user_id': r.get('user_id'),
                        'username': r.get('username') or None,
                        'points': remaining,
                        'total_used': used,
                    })

                # Do NOT include users with no usage rows; leaderboard should only show
                # users who have usage entries for this assessment. Users without any
                # record will not appear here.

                # Sort by remaining points desc and compute dense ranks
                leaderboard.sort(key=lambda x: x['points'], reverse=True)
                prev_points = None
                rank = 0
                dense_rank = 0
                for item in leaderboard:
                    dense_rank += 1
                    if prev_points is None or item['points'] < prev_points:
                        rank = dense_rank
                    prev_points = item['points']
                    item['rank'] = rank

            except Exception as e:
                # Fallback to user_points_assessment if session_tokens per-assessment not available
                print(f"[WARNING] session_tokens per-assessment query failed, falling back: {e}")
                cur.execute(
                    "SELECT upa.user_id AS user_id, COALESCE(u.username,'') AS username, COALESCE(upa.total_points,0) AS total_used "
                    "FROM user_points_assessment upa LEFT JOIN users u ON upa.user_id = u.user_id "
                    "WHERE upa.assessment_id = %s ORDER BY total_used DESC",
                    (assessment_id,)
                )
                rows = cur.fetchall() or []
                leaderboard = []
                for r in rows:
                    used = int(r.get('total_used', 0) or 0)
                    remaining = max(0, 2000 - used)
                    leaderboard.append({
                        'user_id': r.get('user_id'),
                        'username': r.get('username') or None,
                        'points': remaining,
                        'total_used': used,
                    })

                # compute ranks
                leaderboard.sort(key=lambda x: x['points'], reverse=True)
                prev_points = None
                rank = 0
                dense_rank = 0
                for item in leaderboard:
                    dense_rank += 1
                    if prev_points is None or item['points'] < prev_points:
                        rank = dense_rank
                    prev_points = item['points']
                    item['rank'] = rank

        # find current user's rank
        user_rank = None
        for item in leaderboard:
            if str(item['user_id']) == str(user_id):
                user_rank = item
                break

        return jsonify({
            'assessment_id': assessment_id,
            'leaderboard': leaderboard,
            'user_rank': user_rank,
        }), 200
    except Exception as e:
        print(f"[ERROR] assessment_leaderboard: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        conn.close()


def update_user_points_for_assessment(user_id, assessment_id, course_id, points_to_add):
    """Update or insert per-assessment points for a user.

    This function will create a row in `user_points_assessment` if missing,
    otherwise it will add to `total_points`.
    """
    if not user_id or not assessment_id:
        return
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            now = datetime.datetime.now()
            cur.execute("SELECT total_points FROM user_points_assessment WHERE user_id=%s AND assessment_id=%s", (user_id, assessment_id))
            row = cur.fetchone()
            if not row:
                cur.execute(
                    "INSERT INTO user_points_assessment (id, user_id, assessment_id, course_id, total_points, updated_at) VALUES (%s, %s, %s, %s, %s, %s)",
                    (str(uuid.uuid4()), user_id, assessment_id, course_id, points_to_add, now)
                )
            else:
                cur.execute(
                    "UPDATE user_points_assessment SET total_points = total_points + %s, updated_at = %s WHERE user_id = %s AND assessment_id = %s",
                    (points_to_add, now, user_id, assessment_id)
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
    # Accept optional assessment_id and course_id (pass as kwargs)
def log_token_usage(user_id, session_id, tokens_used, assessment_id=None, course_id=None):
    if not user_id or not session_id:
        raise ValueError("user_id and session_id are required for token usage log")
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            now = datetime.datetime.now()
            # Try to insert with assessment/course columns if they exist, fallback to legacy insert
            try:
                cur.execute(
                    "INSERT INTO session_tokens (id, user_id, session_id, assessment_id, course_id, tokens_used, used_at) VALUES (%s, %s, %s, %s, %s, %s, %s)",
                    (str(uuid.uuid4()), user_id, session_id, assessment_id, course_id, tokens_used, now)
                )
            except Exception:
                # Fallback for older schemas without assessment/course columns
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


@app.route('/token-usage-daily', methods=['GET'])
@require_login
def token_usage_daily():
    """
    Return daily token usage for the current user for the current week.
    """
    user_id = session.get("user_id")
    if not user_id:
        return jsonify({"error": "Unauthorized. Silakan login."}), 401

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            now = datetime.datetime.now()
            cur.execute(
                """
                SELECT DATE(used_at) AS day, COALESCE(SUM(tokens_used), 0) AS tokens_used
                FROM session_tokens
                WHERE user_id=%s AND YEARWEEK(used_at, 1) = YEARWEEK(%s, 1)
                GROUP BY DATE(used_at)
                ORDER BY DATE(used_at) ASC
                """,
                (user_id, now),
            )
            rows = cur.fetchall() or []
            daily_stats = []
            total_used = 0
            for r in rows:
                day = r.get('day')
                day_str = day.strftime('%Y-%m-%d') if hasattr(day, 'strftime') else str(day)
                used = int(r.get('tokens_used', 0) or 0)
                daily_stats.append({"date": day_str, "tokens_used": used})
                total_used += used
            remaining = max(0, 2000 - total_used)
        return jsonify({"daily_stats": daily_stats, "total_used": total_used, "remaining_tokens": remaining}), 200
    except Exception as e:
        print(f"[ERROR] token_usage_daily: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        conn.close()


@app.route('/token-usage-breakdown', methods=['GET'])
@require_login
def token_usage_breakdown():
    """
    Return token usage breakdown for current week: total, by_course, by_assessment.
    Gracefully fallback if schema does not have course_id/assessment_id.
    """
    user_id = session.get("user_id")
    if not user_id:
        return jsonify({"error": "Unauthorized. Silakan login."}), 401

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            now = datetime.datetime.now()
            # Total used this week
            cur.execute(
                "SELECT COALESCE(SUM(tokens_used), 0) AS total_used "
                "FROM session_tokens WHERE user_id=%s AND YEARWEEK(used_at, 1) = YEARWEEK(%s, 1)",
                (user_id, now),
            )
            row = cur.fetchone() or {"total_used": 0}
            total_used = int(row.get('total_used', 0) or 0)
            remaining = max(0, 2000 - total_used)

            by_course = []
            by_assessment = []

            # Try per-course breakdown (if column exists)
            try:
                cur.execute(
                    "SELECT st.course_id AS course_id, COALESCE(c.name, '') AS course_name, COALESCE(SUM(st.tokens_used),0) AS total_used, COUNT(DISTINCT st.assessment_id) AS assessments_count "
                    "FROM session_tokens st LEFT JOIN courses c ON st.course_id = c.course_id "
                    "WHERE st.user_id=%s AND YEARWEEK(st.used_at, 1) = YEARWEEK(%s, 1) "
                    "GROUP BY st.course_id ORDER BY total_used DESC",
                    (user_id, now),
                )
                rows = cur.fetchall() or []
                for r in rows:
                    count = int(r.get('assessments_count') or 0)
                    by_course.append({
                        "course_id": r.get('course_id'),
                        "course_name": r.get('course_name') or None,
                        "assessments_count": count,
                        "total_used": int(r.get('total_used', 0) or 0),
                        "remaining": max(0, count * 2000 - int(r.get('total_used', 0) or 0)),
                    })
            except Exception:
                by_course = []

            # Fallback: if no rows found in session_tokens, try reading from user_points_assessment grouped by course
            if not by_course:
                try:
                    # Prefer aggregating via assessments -> courses mapping to avoid incorrect upa.course_id
                    cur.execute(
                        "SELECT a.course_id AS course_id, COALESCE(c.name,'') AS course_name, COALESCE(SUM(upa.total_points),0) AS total_used, COUNT(DISTINCT upa.assessment_id) AS assessments_count "
                        "FROM user_points_assessment upa JOIN assessments a ON upa.assessment_id = a.assessment_id "
                        "LEFT JOIN courses c ON a.course_id = c.course_id "
                        "WHERE upa.user_id=%s GROUP BY a.course_id ORDER BY total_used DESC",
                        (user_id,)
                    )
                    rows = cur.fetchall() or []
                    for r in rows:
                        count = int(r.get('assessments_count') or 0)
                        by_course.append({
                            "course_id": r.get('course_id'),
                            "course_name": r.get('course_name') or None,
                            "assessments_count": count,
                            "total_used": int(r.get('total_used', 0) or 0),
                            "remaining": max(0, count * 2000 - int(r.get('total_used', 0) or 0)),
                        })
                except Exception as e:
                    print(f"[DEBUG] fallback by_course failed: {e}")
                    by_course = []

            # Try per-assessment breakdown
            try:
                cur.execute(
                    "SELECT st.assessment_id AS assessment_id, COALESCE(a.name, '') AS assessment_name, COALESCE(SUM(st.tokens_used),0) AS total_used "
                    "FROM session_tokens st LEFT JOIN assessments a ON st.assessment_id = a.assessment_id "
                    "WHERE st.user_id=%s AND YEARWEEK(st.used_at, 1) = YEARWEEK(%s, 1) "
                    "GROUP BY st.assessment_id ORDER BY total_used DESC",
                    (user_id, now),
                )
                rows = cur.fetchall() or []
                for r in rows:
                    by_assessment.append({
                        "assessment_id": r.get('assessment_id'),
                        "assessment_name": r.get('assessment_name') or None,
                        "total_used": int(r.get('total_used', 0) or 0),
                        "remaining": max(0, 2000 - int(r.get('total_used', 0) or 0)),
                    })
            except Exception:
                by_assessment = []

            # Fallback: if no rows found in session_tokens, try reading from user_points_assessment
            if not by_assessment:
                try:
                    cur.execute(
                        "SELECT upa.assessment_id AS assessment_id, COALESCE(a.name,'') AS assessment_name, COALESCE(upa.total_points,0) AS total_used "
                        "FROM user_points_assessment upa LEFT JOIN assessments a ON upa.assessment_id = a.assessment_id "
                        "WHERE upa.user_id=%s ORDER BY total_used DESC",
                        (user_id,)
                    )
                    rows = cur.fetchall() or []
                    for r in rows:
                        by_assessment.append({
                            "assessment_id": r.get('assessment_id'),
                            "assessment_name": r.get('assessment_name') or None,
                            "total_used": int(r.get('total_used', 0) or 0),
                            "remaining": max(0, 2000 - int(r.get('total_used', 0) or 0)),
                        })
                except Exception as e:
                    print(f"[DEBUG] fallback by_assessment failed: {e}")
                    by_assessment = []

        return jsonify({
            "total": {"total_used": total_used, "remaining": remaining},
            "by_course": by_course,
            "by_assessment": by_assessment,
        }), 200
    except Exception as e:
        print(f"[ERROR] token_usage_breakdown: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        conn.close()

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

def update_gpt_job(job_id, code=None, status=None, error=None, similarity=None, prompt_matched=None, raw_response=None):
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
            if raw_response is not None:
                fields.append("raw_response=%s")
                values.append(raw_response)
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
                # Parse simple markers at start of prompt like [MODE:code] [LANG:Python] [AUTO_FALLBACK:true]
                markers = {}
                import re as _re
                marker_pattern = _re.compile(r"^([\s\S]*?)")
                # find all markers anywhere in the beginning lines
                found = _re.findall(r"\[([A-Z0-9_]+):([^\]]+)\]", prompt)
                for k, v in found:
                    markers[k.upper()] = v.strip()
                # Remove markers from prompt text for the user message
                prompt_clean = _re.sub(r"\[([A-Z0-9_]+):([^\]]+)\]\s*", "", prompt).strip()

                mode = (markers.get('MODE') or markers.get('MODE'.upper()) or '').lower() or 'code'
                lang_hint = markers.get('LANG') or markers.get('LANG'.upper()) or ''

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

                # Add language hint to system prompt if provided
                if lang_hint:
                    system_content += f" Use the following language when generating code: {lang_hint}."

                messages = [
                    {"role": "system", "content": system_content},
                    {"role": "user", "content": prompt_clean},
                ]
                # For openai>=1.0.0 (correct usage)
                temp = 0.0 if mode == 'code' else 0.2
                response = openai.chat.completions.create(
                    model=OPENAI_MODEL,
                    messages=messages,
                    temperature=temp,
                    max_tokens=1024,
                )
                response_text = response.choices[0].message.content

                # Helper: extract only code from model output (prefer fenced code blocks)
                def _extract_code_from_text(txt: str) -> str:
                    if not txt or not isinstance(txt, str):
                        return ''
                    import re as _re
                    # 1) fenced code block ```lang\n...``` -> capture inner
                    m = _re.search(r'```(?:[a-zA-Z0-9_+-]*)\n([\s\S]*?)\n```', txt)
                    if m:
                        return m.group(1).strip()
                    # 2) inline fence without trailing newline
                    m2 = _re.search(r'```([\s\S]*?)```', txt)
                    if m2:
                        return m2.group(1).strip()
                    # 3) look for largest contiguous block with code-like indicators
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
                    # 4) fallback: return empty
                    return ''

                code_only = _extract_code_from_text(response_text)
                # Decide what to store based on requested mode:
                # - 'code' -> store extracted code only (if present), else full response
                # - 'summary' -> store full response (no code expected)
                # - 'summary_code_explanation' -> store full response (summary + code + explanation)
                if mode == 'code':
                    code = code_only.strip() if code_only else response_text.strip()
                else:
                    # For summary or summary_code_explanation, preserve full model output
                    code = response_text.strip()
                # Simpan jawaban assistant (simpan according to mode so UI can render summary+explanation when requested)
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
                update_gpt_job(job_id, code=code, status="done", raw_response=response_text)
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
@limiter.limit("1 per minute")
def generate_code():
    data = request.get_json(silent=True) or {}
    prompt = data.get("prompt")
    assessment_id = data.get("assessment_id")  # penanda assessment/mata kuliah
    # Optional client hints
    language = (data.get("language") or '').strip()
    response_mode = (data.get("response_mode") or 'code').strip()
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
            "You must only answer questions that are about programming or code; if the user's request is not technical programming-related, reply: 'Sorry, I can only help with programming/code questions.' "
            "Respect any markers in the user's prompt such as [LANG:...] (preferred programming language) and [MODE:...] (one of 'code','summary','summary_code_explanation'). "
            "If a language is specified, produce code only in that language. For retrieval results the output may be taken from the database and no generation occurs. Keep outputs concise and focused on the assessment context if provided."
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

        # Helper: simple heuristic to detect if retrieved text looks like code
        def _is_code_like(text: str) -> bool:
            if not text or not isinstance(text, str):
                return False
            t = text.strip()
            # fenced code blocks
            if t.startswith('```') or '```' in t:
                return True
            indicators = ['\ndef ', '\nclass ', ';', '{', '}', 'function ', 'import ', '#include', 'return ', 'console.log', '=>', 'public ', 'private ', 'static ', 'def '] 
            for ind in indicators:
                if ind in t:
                    return True
            # also check for multiple newlines and indentation suggesting code
            if t.count('\n') >= 2 and any(line.startswith('    ') or line.startswith('\t') for line in t.split('\n')):
                return True
            return False

        is_code = _is_code_like(code_retrieved)

        # If user asked explicitly for code but retrieved item seems descriptive,
        # treat it as a suggestion rather than returning it as final code.
        if similarity >= 0.95 and (response_mode != 'code' or is_code):
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
        elif similarity >= 0.95 and response_mode == 'code' and not is_code:
            # High similarity but retrieved content not code — automatically queue GPT job
            impact = _get_impact(emissions)
            user_id = session.get("user_id")
            session_id = session.get("session_id") or request.remote_addr
            # Build a marked prompt for the queued job to respect language/mode
            markers = []
            try:
                if language:
                    markers.append(f"[LANG:{language}]")
            except Exception:
                pass
            markers.append("[MODE:code]")
            markers.append("[AUTO_FALLBACK:true]")
            job_prompt = "\n".join(markers) + "\n" + prompt
            try:
                job_id = insert_gpt_job(user_id, prompt, job_prompt, status="pending")
            except Exception as e_job:
                # If job creation fails, fallback to suggestion response
                gamification = get_user_token_info(user_id, session_id)
                return jsonify({
                    "mode": "suggestion",
                    "similarity": similarity,
                    "prompt_matched": prompt_retrieved,
                    "code": code_retrieved,
                    "message": "Ditemukan entri mirip di database tetapi isinya deskriptif. Gagal mengantri permintaan ke ChatGPT: " + str(e_job),
                    "environmental_impact": impact,
                    "token_info": retrieval_token_info,
                    "gamification": gamification
                }), 200

            gamification = get_user_token_info(user_id, session_id)
            return jsonify({
                "mode": "gpt-queued_auto",
                "similarity": similarity,
                "prompt_matched": prompt_retrieved,
                "job_id": job_id,
                "message": "DB only contained descriptive text. Request automatically queued to ChatGPT to generate code (this will use quota).",
                "environmental_impact": impact,
                "token_info": retrieval_token_info,
                "gamification": gamification
            }), 202
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

    # Additional server-side validation and markers for GPT usage
    def _contains_emoji(s: str) -> bool:
        try:
            emoji_re = re.compile(r"[\U0001F300-\U0001F5FF\U0001F600-\U0001F64F\U0001F680-\U0001F6FF\U0001F1E0-\U0001F1FF]")
            return bool(emoji_re.search(s))
        except re.error:
            return any(ord(c) > 0x1F000 for c in s)

    if response_mode not in ("code", "summary", "summary_code_explanation"):
        response_mode = "code"

    def get_assessment_name(aid):
        if not aid:
            return ''
        try:
            conn_a = get_db_connection()
            with conn_a.cursor() as cur_a:
                cur_a.execute("SELECT name FROM assessments WHERE id=%s LIMIT 1", (aid,))
                r = cur_a.fetchone()
                if r and r.get('name'):
                    return str(r['name'])
        except Exception:
            pass
        finally:
            try:
                conn_a.close()
            except Exception:
                pass
        return ''

    assessment_name = get_assessment_name(assessment_id)

    # Enforce minimal length and disallow emoji for GPT submissions
    if not gpt_prompt or len(gpt_prompt.strip()) < 100:
        return jsonify({"error": "Prompt too short. Please provide at least 100 characters."}), 400
    if _contains_emoji(gpt_prompt):
        return jsonify({"error": "Prompt contains unsupported characters (emoji). Please remove them."}), 400

    # Prefix markers so downstream worker/system can pick language/mode/assessment
    markers = []
    if language:
        markers.append(f"[LANG:{language}]")
    if response_mode:
        markers.append(f"[MODE:{response_mode}]")
    if assessment_name:
        markers.append(f"[ASSESSMENT:{assessment_name}]")
    if markers:
        gpt_prompt_marked = "\n".join(markers) + "\n" + gpt_prompt
    else:
        gpt_prompt_marked = gpt_prompt

    # Simpan prompt user ke chat_history, dikelompokkan per assessment
    save_chat_message(user_id, session_id, "user", gpt_prompt, assessment_id)

    # Ambil riwayat chat terakhir (misal 10), difilter per assessment
    chat_history = get_chat_history(user_id, session_id, assessment_id, limit=10)

    system_content = (
        "You are an expert programming assistant helping undergraduate computer science students. "
        "You must only answer questions that are about programming or code; if the user's request is not technical programming-related, reply: 'Sorry, I can only help with programming/code questions.' "
        "Respect any markers the user may include such as [LANG:...] to indicate the desired programming language and [MODE:...] to indicate 'code','summary', or 'summary_code_explanation'. "
        "If an assessment context is provided (e.g., [ASSESSMENT:Implementasi Fungsi]), tailor the answer to that assessment focus."
    )

    # Gabungkan system prompt + chat history
    messages = [{"role": "system", "content": system_content}]
    for row in chat_history:
        messages.append({"role": row["role"], "content": row["content"]})

    # --- Queue GPT request ---
    # Menggunakan insert_gpt_job dengan advisory lock untuk menghindari
    # duplikasi job jika ada beberapa user menanyakan prompt yang sama
    job_id = insert_gpt_job(user_id, prompt, gpt_prompt_marked, status="pending")
    # (worker thread/async processing not shown here)
    # Untuk GPT, token akan dikurangi saat job selesai (di /check-status)
    gamification = get_user_token_info(user_id, session_id)
    return jsonify({
        "mode": "gpt-queued",
        "job_id": job_id,
        "message": "Permintaan Anda sedang diproses karena antrian atau rate limit. Silakan cek status dengan job_id ini di endpoint /check-status/{job_id}.",
        "gamification": gamification
    }), 202


@app.route('/enqueue-gpt', methods=['POST'])
def enqueue_gpt():
    """Enqueue a GPT job without applying the route rate limit.

    Intended for explicit "Generate with ChatGPT" actions originating from retrieval.
    The caller must be authenticated (session user_id).
    """
    data = request.get_json(silent=True) or {}
    prompt = data.get('prompt')
    assessment_id = data.get('assessment_id')
    language = (data.get('language') or '').strip()
    response_mode = (data.get('response_mode') or 'code').strip()

    user_id = session.get('user_id')
    if not user_id:
        return jsonify({"error": "Unauthorized. Silakan login."}), 401

    # Basic validation (similar to generate_code)
    if not prompt or not isinstance(prompt, str) or len(prompt.strip()) < 10:
        return jsonify({"error": "Missing or invalid 'prompt' in request body"}), 400

    # Enforce per-user cooldown for manual GPT enqueue: prevent >1 enqueue per minute
    try:
        conn_c = get_db_connection()
        with conn_c.cursor() as curc:
            curc.execute(
                "SELECT COUNT(*) AS cnt FROM gpt_jobs WHERE user_id=%s AND created_at >= NOW() - INTERVAL %s SECOND",
                (user_id, 60)
            )
            rowc = curc.fetchone() or {}
            if int(rowc.get('cnt', 0) or 0) > 0:
                # Inform client about cooldown
                return jsonify({"error": "Rate limit: only one manual ChatGPT generation allowed per minute."}), 429, {"Retry-After": "60"}
    except Exception as e_cd:
        print(f"[WARNING] Could not enforce enqueue cooldown: {e_cd}")
    finally:
        try:
            conn_c.close()
        except Exception:
            pass

    # Build markers and marked prompt for worker
    markers = []
    if language:
        markers.append(f"[LANG:{language}]")
    if response_mode:
        markers.append(f"[MODE:{response_mode}]")
    if assessment_id:
        markers.append(f"[ASSESSMENT_ID:{assessment_id}]")
    markers.append("[AUTO_FALLBACK:true]")
    gpt_prompt_marked = "\n".join(markers) + "\n" + prompt

    try:
        job_id = insert_gpt_job(user_id, prompt, gpt_prompt_marked, status="pending")
    except Exception as e:
        return jsonify({"error": f"Failed to create job: {e}"}), 500

    session_id = session.get('session_id') or request.remote_addr
    gamification = get_user_token_info(user_id, session_id)
    return jsonify({
        "mode": "gpt-queued_manual",
        "job_id": job_id,
        "message": "Request queued to ChatGPT (manual generate).",
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
                {"role": "system", "content": "You are an expert programming assistant helping undergraduate computer science students. Respect any [MODE:] or [LANG:] markers in the prompt; follow them when deciding output format (code/summary/summary+code+explanation)."},
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

            # Log token usage with assessment/course when available
            try:
                log_token_usage(user_id, session_id, token_count, assessment_id, course_id)
            except Exception as e_log:
                print(f"[WARNING] Failed to log token usage: {e_log}")

            # Tambah poin per-assessment
            try:
                if assessment_id:
                    update_user_points_for_assessment(user_id, assessment_id, course_id, token_count)
                else:
                    # Fallback to adding to overall points
                    update_user_total_points_if_new_week(user_id, token_count)
            except Exception as e_up:
                print(f"[WARNING] Failed to update user points: {e_up}")

            gamification = get_user_token_info(user_id, session_id)
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
                "raw_response": job.get("raw_response"),
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
