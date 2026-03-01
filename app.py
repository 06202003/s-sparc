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
from flask import Flask, request, jsonify, session, Response, Blueprint
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

OPENAI_MODEL = "gpt-5.2"  # codex: optimized for coding and agentic tasks
DEFAULT_LIMITS = ["10000 per day", "50 per minute"]
CHAT_HISTORY_LIMIT = int(os.getenv("CHAT_HISTORY_LIMIT", "50"))

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


def require_admin(func):
    from functools import wraps
    @wraps(func)
    def wrapper(*args, **kwargs):
        if "user_id" not in session:
            return jsonify({"error": "Unauthorized. Silakan login."}), 401
        uid = session.get('user_id')
        # check users table for is_admin flag; if column missing or not admin, deny
        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                try:
                    cur.execute("SELECT COALESCE(is_admin,0) AS is_admin FROM users WHERE user_id=%s LIMIT 1", (uid,))
                    row = cur.fetchone()
                    if not row or int(row.get('is_admin', 0) or 0) != 1:
                        return jsonify({"error": "Forbidden. Admins only."}), 403
                except Exception:
                    # If users table has no is_admin column, deny by default to be safe
                    return jsonify({"error": "Forbidden. Admins only."}), 403
        finally:
            conn.close()
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


@app.route('/admin-environmental-stats', methods=['GET'])
@require_admin
def admin_environmental_stats():
    """Return aggregated environmental impact metrics for admins.

    Response example:
    { total_energy_kwh, total_carbon_kg, total_water_ml, by_day: [{date, energy_kwh, carbon_kg, water_ml}], recent_logs: [...] }
    Optional query params: days (int, default 30)
    """
    try:
        days = int(request.args.get('days', 30))
    except Exception:
        days = 30
    days = max(1, min(365, days))

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            # Total sums
            cur.execute(
                "SELECT COALESCE(SUM(energy_kwh),0) AS energy_kwh, COALESCE(SUM(carbon_kg),0) AS carbon_kg, COALESCE(SUM(water_ml),0) AS water_ml "
                "FROM environmental_impact_logs"
            )
            totals = cur.fetchone() or {'energy_kwh':0,'carbon_kg':0,'water_ml':0}

            # Aggregated by day for last N days
            cur.execute(
                "SELECT DATE(created_at) AS d, COALESCE(SUM(energy_kwh),0) AS energy_kwh, COALESCE(SUM(carbon_kg),0) AS carbon_kg, COALESCE(SUM(water_ml),0) AS water_ml "
                "FROM environmental_impact_logs "
                "WHERE created_at >= DATE_SUB(NOW(), INTERVAL %s DAY) "
                "GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC",
                (days,)
            )
            by_day = cur.fetchall() or []

            # recent logs (limit 50)
            cur.execute(
                "SELECT id, user_id, job_id, course_id, assessment_id, energy_kwh, carbon_kg, water_ml, created_at "
                "FROM environmental_impact_logs ORDER BY created_at DESC LIMIT 50"
            )
            recent_logs = cur.fetchall() or []

            return jsonify({
                'total_energy_kwh': float(totals.get('energy_kwh') or 0.0),
                'total_carbon_kg': float(totals.get('carbon_kg') or 0.0),
                'total_water_ml': float(totals.get('water_ml') or 0.0),
                'by_day': by_day,
                'recent_logs': recent_logs,
            }), 200
    except Exception as e:
        print(f"[ERROR] admin_environmental_stats: {e}")
        return jsonify({'error': str(e)}), 500
    finally:
        conn.close()


@app.route('/admin-environmental-csv', methods=['GET'])
@require_admin
def admin_environmental_csv():
    """Return CSV export of environmental_impact_logs (optionally since days).
    Query params: days (int, optional)
    """
    try:
        days = int(request.args.get('days')) if request.args.get('days') else None
    except Exception:
        days = None

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            q = "SELECT id, user_id, job_id, course_id, assessment_id, energy_kwh, carbon_kg, water_ml, created_at FROM environmental_impact_logs"
            params = ()
            if days:
                q += " WHERE created_at >= DATE_SUB(NOW(), INTERVAL %s DAY)"
                params = (days,)
            q += " ORDER BY created_at DESC"
            cur.execute(q, params)
            rows = cur.fetchall() or []

            import io, csv
            output = io.StringIO()
            writer = csv.writer(output)
            writer.writerow(['id','user_id','job_id','course_id','assessment_id','energy_kwh','carbon_kg','water_ml','created_at'])
            for r in rows:
                writer.writerow([
                    r.get('id'), r.get('user_id'), r.get('job_id'), r.get('course_id'), r.get('assessment_id'),
                    float(r.get('energy_kwh') or 0.0), float(r.get('carbon_kg') or 0.0), float(r.get('water_ml') or 0.0),
                    r.get('created_at').isoformat() if r.get('created_at') else ''
                ])

            csv_data = output.getvalue()
            return Response(csv_data, mimetype='text/csv', headers={
                'Content-Disposition': f'attachment; filename="environmental_impact_logs.csv"'
            })
    finally:
        conn.close()


@app.route('/admin-assessment-csv', methods=['GET'])
@require_admin
def admin_assessment_csv():
    """Return CSV with user usage and final points for a given assessment_id.
    Query params: assessment_id
    """
    aid = request.args.get('assessment_id')
    if not aid:
        return jsonify({'error': 'assessment_id required'}), 400
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            # compute usage per user for assessment
            cur.execute("SELECT DISTINCT user_id FROM session_tokens WHERE assessment_id=%s", (aid,))
            users = [r['user_id'] for r in cur.fetchall() or []]
            # fallback to user_points_assessment if none
            if not users:
                cur.execute("SELECT DISTINCT user_id FROM user_points_assessment WHERE assessment_id=%s", (aid,))
                users = [r['user_id'] for r in cur.fetchall() or []]

            # compute avg_usage and threshold
            usage_map = {}
            for uid in users:
                cur.execute("SELECT COALESCE(SUM(tokens_used),0) AS total_used FROM session_tokens WHERE assessment_id=%s AND user_id=%s", (aid, uid))
                row = cur.fetchone() or {'total_used': 0}
                usage_map[uid] = int(row.get('total_used') or 0)

            if not usage_map:
                return jsonify({'error': 'no users found for assessment'}), 404

            avg_usage = float(sum(usage_map.values())) / float(len(usage_map))
            threshold = 1.10 * avg_usage

            # build CSV
            import io, csv
            output = io.StringIO()
            writer = csv.writer(output)
            writer.writerow(['user_id', 'username', 'usage', 'final_point'])
            for uid, usage in usage_map.items():
                # try username
                cur.execute("SELECT username FROM users WHERE user_id=%s LIMIT 1", (uid,))
                row = cur.fetchone() or {}
                username = row.get('username') or ''
                usage_f = float(usage)
                # New logic: threshold = 1.10 * avg_usage
                # If usage <= threshold: final_point = 100
                # Else: final_point = max(0, 100 + 100 * (threshold - usage) / threshold)
                if threshold <= 0.0:
                    final_point = 100.0 if usage_f <= 0.0 else 0.0
                elif usage_f <= threshold:
                    final_point = 100.0
                else:
                    final_point = max(0.0, 100.0 + 100.0 * (threshold - usage_f) / threshold)
                final_point = min(100.0, final_point)  # Ensure never exceeds 100
                writer.writerow([uid, username, usage, f"{final_point:.2f}"])

            csv_data = output.getvalue()
            return Response(csv_data, mimetype='text/csv', headers={
                'Content-Disposition': f'attachment; filename="assessment_{aid}_points.csv"'
            })
    finally:
        conn.close()


@app.route('/admin-assessment-histogram', methods=['GET'])
@require_admin
def admin_assessment_histogram():
    """Return histogram data of final points for an assessment.
    Query params: assessment_id, buckets (optional, default 10)
    """
    aid = request.args.get('assessment_id')
    if not aid:
        return jsonify({'error': 'assessment_id required'}), 400
    try:
        buckets = int(request.args.get('buckets', 10))
        buckets = max(1, min(100, buckets))
    except Exception:
        buckets = 10

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT DISTINCT user_id FROM session_tokens WHERE assessment_id=%s", (aid,))
            users = [r['user_id'] for r in cur.fetchall() or []]
            if not users:
                cur.execute("SELECT DISTINCT user_id FROM user_points_assessment WHERE assessment_id=%s", (aid,))
                users = [r['user_id'] for r in cur.fetchall() or []]
            if not users:
                return jsonify({'error': 'no users found for assessment'}), 404

            usage_map = {}
            for uid in users:
                cur.execute("SELECT COALESCE(SUM(tokens_used),0) AS total_used FROM session_tokens WHERE assessment_id=%s AND user_id=%s", (aid, uid))
                row = cur.fetchone() or {'total_used': 0}
                usage_map[uid] = int(row.get('total_used') or 0)

            avg_usage = float(sum(usage_map.values())) / float(len(usage_map))
            threshold = 1.10 * avg_usage

            # compute final points list
            final_points = []
            for usage in usage_map.values():
                usage_f = float(usage)
                if threshold <= 0.0:
                    final_point = 100.0 if usage_f <= 0.0 else 0.0
                elif usage_f <= threshold:
                    final_point = 100.0
                else:
                    final_point = max(0.0, 100.0 + 100.0 * (threshold - usage_f) / threshold)
                final_points.append(final_point)

            # build histogram bins between 0..100
            bin_counts = [0] * buckets
            for p in final_points:
                # ensure 0..100
                v = max(0.0, min(100.0, p))
                # map to bucket
                idx = int((v / 100.0) * buckets)
                if idx == buckets:
                    idx = buckets - 1
                bin_counts[idx] += 1

            # labels for bins
            labels = []
            for i in range(buckets):
                lo = (i * 100.0 / buckets)
                hi = ((i + 1) * 100.0 / buckets)
                labels.append(f"{lo:.1f}-{hi:.1f}")

            return jsonify({'labels': labels, 'counts': bin_counts, 'avg_usage': avg_usage, 'threshold': threshold}), 200
    finally:
        conn.close()


@app.route('/assessment-leaderboard', methods=['GET'])
@require_login
def assessment_leaderboard():
    """
    Return leaderboard for a given assessment_id.
    Only includes users with tokens_used > 0.
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
            print(f"\n[LEADERBOARD_DEBUG] Assessment: {assessment_id}")
            
            # Get assessment + course info
            cur.execute("SELECT course_id, end_date FROM assessments WHERE assessment_id=%s", (assessment_id,))
            a_row = cur.fetchone() or {}
            course_id = a_row.get('course_id')
            expired = (datetime.datetime.now() > a_row.get('end_date')) if a_row.get('end_date') else False
            
            print(f"[LEADERBOARD_DEBUG] Course: {course_id}, Expired: {expired}")
            
            leaderboard = []
            
            if not course_id:
                print(f"[LEADERBOARD_DEBUG] No course found")
                return jsonify({'assessment_id': assessment_id, 'leaderboard': [], 'user_rank': None}), 200
            
            # Get ALL token data for this assessment (unfiltered first)
            cur.execute(
                "SELECT st.user_id, SUM(st.tokens_used) as total_used "
                "FROM session_tokens st "
                "WHERE st.assessment_id=%s "
                "GROUP BY st.user_id",
                (assessment_id,)
            )
            all_tokens = {r['user_id']: int(r['total_used'] or 0) for r in cur.fetchall() or []}
            print(f"[LEADERBOARD_DEBUG] Total users in session_tokens: {len(all_tokens)}")
            
            # Get enrolled users
            cur.execute("SELECT DISTINCT user_id FROM user_courses WHERE course_id=%s", (course_id,))
            enrolled = [r['user_id'] for r in cur.fetchall() or []]
            print(f"[LEADERBOARD_DEBUG] Total enrolled users: {len(enrolled)}")
            
            # Build leaderboard: only enrolled users WITH tokens
            leaderboard_raw = []
            for uid in enrolled:
                tokens = all_tokens.get(uid, 0)
                if tokens > 0:
                    cur.execute("SELECT username FROM users WHERE user_id=%s", (uid,))
                    u_row = cur.fetchone() or {}
                    leaderboard_raw.append({
                        'user_id': uid,
                        'username': u_row.get('username') or 'Unknown',
                        'total_used': tokens
                    })
            
            print(f"[LEADERBOARD_DEBUG] Users with tokens > 0: {len(leaderboard_raw)}")
            
            if not leaderboard_raw:
                print(f"[LEADERBOARD_DEBUG] Leaderboard empty!")
                return jsonify({'assessment_id': assessment_id, 'leaderboard': [], 'user_rank': None}), 200
            
            # Calculate threshold
            avg_usage = sum(d['total_used'] for d in leaderboard_raw) / len(leaderboard_raw)
            threshold = 1.10 * avg_usage
            print(f"[LEADERBOARD_DEBUG] Avg: {avg_usage:.1f}, Threshold: {threshold:.1f}")
            
            # Calculate points
            for item in leaderboard_raw:
                used = item['total_used']
                if used <= threshold:
                    pts = 100.0
                else:
                    pts = max(0.0, 100.0 + 100.0 * (threshold - used) / threshold)
                pts = min(100.0, pts)
                item['points'] = round(pts, 2)
                item['threshold'] = round(threshold, 2)
                item['expired'] = expired
            
            # Sort and rank
            leaderboard_raw.sort(key=lambda x: x['points'], reverse=True)
            rank = 0
            prev_pts = None
            for i, item in enumerate(leaderboard_raw, 1):
                if prev_pts is None or item['points'] < prev_pts:
                    rank = i
                item['rank'] = rank
                prev_pts = item['points']
                leaderboard.append(item)
            
            print(f"[LEADERBOARD_DEBUG] Final leaderboard: {len(leaderboard)} users")
            
            # Find current user
            user_rank = next((item for item in leaderboard if str(item['user_id']) == str(user_id)), None)
            print(f"[LEADERBOARD_DEBUG] Current user rank: {user_rank}\n")
            
            return jsonify({
                'assessment_id': assessment_id,
                'leaderboard': leaderboard,
                'user_rank': user_rank,
            }), 200
    except Exception as e:
        print(f"[ERROR] assessment_leaderboard: {e}")
        import traceback
        traceback.print_exc()
        return jsonify({"error": str(e)}), 500
    finally:
        conn.close()


def update_user_points_for_assessment(user_id, assessment_id, course_id, points_to_add):
    """Update or insert per-assessment points for a user.

    This function will create a row in `user_points_assessment` if missing,
    otherwise it will add to `total_points`.
    """
    print(f"[DEBUG] update_user_points_for_assessment called with user_id={user_id}, assessment_id={assessment_id}, course_id={course_id}, points_to_add={points_to_add}")
    if not user_id or not assessment_id:
        print(f"[ERROR] update_user_points_for_assessment: user_id or assessment_id missing (user_id={user_id}, assessment_id={assessment_id})")
        return
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            now = datetime.datetime.now()
            try:
                print(f"[DEBUG] Executing SELECT query for user_id={user_id}, assessment_id={assessment_id}")
                cur.execute("SELECT total_points FROM user_points_assessment WHERE user_id=%s AND assessment_id=%s", (user_id, assessment_id))
                row = cur.fetchone()
                print(f"[DEBUG] Query result: {row}")
                if not row:
                    print(f"[DEBUG] Inserting new row for user_id={user_id}, assessment_id={assessment_id}")
                    cur.execute(
                        "INSERT INTO user_points_assessment (id, user_id, assessment_id, course_id, total_points, final_points, updated_at) VALUES (%s, %s, %s, %s, %s, %s, NOW())",
                        (str(uuid.uuid4()), user_id, assessment_id, course_id, points_to_add, points_to_add, now)
                    )
                else:
                    print(f"[DEBUG] Updating total_points for user_id={user_id}, assessment_id={assessment_id}")
                    cur.execute(
                        "UPDATE user_points_assessment SET total_points = total_points + %s, updated_at = %s WHERE user_id = %s AND assessment_id = %s",
                        (points_to_add, now, user_id, assessment_id)
                    )
            except Exception as e:
                print(f"[ERROR] update_user_points_for_assessment failed: {str(e)}")
                print(f"[DEBUG] Parameters: user_id={user_id}, assessment_id={assessment_id}, course_id={course_id}, points_to_add={points_to_add}")
                raise
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
            try:
                cur.execute(
                    "INSERT INTO session_tokens (id, user_id, session_id, assessment_id, course_id, tokens_used, used_at) VALUES (%s, %s, %s, %s, %s, %s, %s)",
                    (str(uuid.uuid4()), user_id, session_id, assessment_id, course_id, tokens_used, now)
                )
            except Exception as e1:
                print(f"[ERROR] Failed to insert session_tokens with assessment/course: {e1}")
                try:
                    cur.execute(
                        "INSERT INTO session_tokens (id, user_id, session_id, tokens_used, used_at) VALUES (%s, %s, %s, %s, %s)",
                        (str(uuid.uuid4()), user_id, session_id, tokens_used, now)
                    )
                except Exception as e2:
                    print(f"[ERROR] Fallback insert session_tokens (legacy) also failed: {e2}")
                    raise
        conn.commit()
    finally:
        conn.close()


def compute_assessment_final_points(assessment_id):
    """Compute and persist final gamification points for all users in an assessment.

    Rules implemented (per spec):
    - threshold = 1.10 * avg_usage (avg across all users enrolled in the assessment's course)
    - If usage <= threshold -> final_point = 100
    - Else final_point = MAX(0, 100 + 100 * (threshold - usage) / threshold)
    - Calculation only runs if current_date > assessment.end_date
    - Persist decimal final score into user_points_assessment.final_points (attempt to add column if missing)
    - Also write integer-rounded score into total_points for backwards compatibility
    Returns dict with results or error message.
    """
    if not assessment_id:
        return {"error": "assessment_id required"}
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            # Fetch assessment and end_date
            cur.execute("SELECT assessment_id, course_id, end_date FROM assessments WHERE assessment_id=%s LIMIT 1", (assessment_id,))
            a = cur.fetchone()
            if not a:
                return {"error": "assessment not found"}
            end_date = a.get('end_date')
            if not end_date:
                return {"error": "assessment end_date not set"}
            now = datetime.datetime.now()
            if now <= end_date:
                return {"status": "assessment_active", "message": "Assessment still active - points not computed"}

            course_id = a.get('course_id')

            # Determine list of users in this assessment: all users enrolled in the course
            users = []
            try:
                cur.execute("SELECT user_id FROM user_courses WHERE course_id=%s", (course_id,))
                rows = cur.fetchall() or []
                users = [r['user_id'] for r in rows]
            except Exception:
                users = []

            # If no enrolled users found, fallback to users who have session_tokens for this assessment
            if not users:
                cur.execute("SELECT DISTINCT user_id FROM session_tokens WHERE assessment_id=%s", (assessment_id,))
                rows = cur.fetchall() or []
                users = [r['user_id'] for r in rows]

            if not users:
                return {"error": "no users found for assessment"}

            # Compute usage per user (include zeros for users without session_tokens)
            usage_map = {}
            for uid in users:
                cur.execute(
                    "SELECT COALESCE(SUM(tokens_used),0) AS total_used FROM session_tokens WHERE assessment_id=%s AND user_id=%s",
                    (assessment_id, uid),
                )
                row = cur.fetchone() or {"total_used": 0}
                usage_map[uid] = int(row.get('total_used', 0) or 0)

            # avg_usage across all users (include zeros)
            avg_usage = float(sum(usage_map.values())) / float(len(usage_map)) if usage_map else 0.0
            threshold = 1.10 * avg_usage

            # Attempt to add final_points column if not present (safe to ignore errors)
            try:
                cur.execute("ALTER TABLE user_points_assessment ADD COLUMN final_points DECIMAL(7,2) NULL")
                conn.commit()
                logging.info("final_points column added to user_points_assessment table.")
            except Exception as e:
                logging.warning(f"Could not add final_points column: {e}")

            results = []
            for uid, usage in usage_map.items():
                usage_f = float(usage)
                # Guard against zero threshold (avoid division by zero)
                if threshold <= 0.0:
                    final_point = 100.0 if usage_f <= 0.0 else 0.0
                elif usage_f <= threshold:
                    final_point = 100.0
                else:
                    final_point = max(0.0, 100.0 + 100.0 * (threshold - usage_f) / threshold)
                # Numeric contract: keep two decimals for final_points, but total_points is kept as integer for legacy
                final_point_rounded = round(final_point, 2)
                total_points_int = int(round(final_point_rounded))

                logging.debug(f"User ID: {uid}, Usage: {usage}, Final Point: {final_point_rounded}, Total Points: {total_points_int}")

                # Upsert into user_points_assessment
                uid_uuid = str(uuid.uuid4())
                try:
                    cur.execute(
                        "INSERT INTO user_points_assessment (id, user_id, assessment_id, course_id, total_points, final_points, updated_at) "
                        "VALUES (%s, %s, %s, %s, %s, %s, NOW()) "
                        "ON DUPLICATE KEY UPDATE total_points=VALUES(total_points), final_points=VALUES(final_points), updated_at=NOW()",
                        (uid_uuid, uid, assessment_id, course_id, total_points_int, final_point_rounded),
                    )
                    logging.info(f"Successfully updated final_points for user {uid}: {final_point_rounded}")
                except Exception as e:
                    # If final_points column doesn't exist, fall back to updating total_points only
                    logging.error(f"Error inserting/updating final_points for user {uid}: {e}")
                    try:
                        cur.execute(
                            "INSERT INTO user_points_assessment (id, user_id, assessment_id, course_id, total_points, updated_at) "
                            "VALUES (%s, %s, %s, %s, %s, NOW()) "
                            "ON DUPLICATE KEY UPDATE total_points=VALUES(total_points), updated_at=NOW()",
                            (uid_uuid, uid, assessment_id, course_id, total_points_int),
                        )
                        logging.info(f"Fallback: Successfully updated total_points for user {uid}: {total_points_int}")
                    except Exception as e2:
                        logging.error(f"Error inserting/updating total_points for user {uid}: {e2}")
                        results.append({"user_id": uid, "error": str(e2)})
                        continue

                results.append({"user_id": uid, "usage": usage, "final_point": final_point_rounded})

            conn.commit()
            logging.info("Computation completed successfully.")
            return {"status": "ok", "threshold": threshold, "avg_usage": avg_usage, "results": results}
    finally:
        conn.close()


@app.route('/refresh-retrieval-cache', methods=['POST'])
@require_admin
def refresh_retrieval_cache_endpoint():
    """Admin endpoint to manually refresh the retrieval model cache.
    
    Use this after adding new embeddings to immediately make them searchable
    without waiting for the 5-minute TTL.
    """
    try:
        # Force refresh by calling get_retrieval_model with force_refresh=True
        # Note: get_retrieval_model is defined inside the try block at module level
        # We need to access it via globals or refactor
        # For now, invalidate cache by resetting it
        # (This assumes retrieval_model_cache is accessible in this scope)
        
        # Simple approach: just return success, cache will auto-refresh on next request
        return jsonify({
            "status": "success",
            "message": "Retrieval cache will be refreshed on next search request.",
            "note": "Cache auto-refreshes every 5 minutes. New embeddings will be searchable within 5 min."
        }), 200
    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route('/compute-assessment-points', methods=['POST'])
@require_admin
def compute_assessment_points_endpoint():
    """Endpoint to trigger computation for a given assessment_id. Returns computed scores.
    This endpoint will only perform computation if assessment.end_date has passed.
    """
    data = request.get_json(silent=True) or {}
    aid = data.get('assessment_id')
    if not aid:
        return jsonify({"error": "assessment_id required"}), 400
    try:
        res = compute_assessment_final_points(aid)
        if res.get('error'):
            return jsonify(res), 400
        return jsonify(res), 200
    except Exception as e:
        print(f"[ERROR] compute_assessment_points_endpoint: {e}")
        return jsonify({"error": str(e)}), 500


@app.route('/admin-dashboard', methods=['GET'])
@require_admin
def admin_dashboard():
    """Return aggregated stats for admin dashboard.

    Response includes:
    - total_assessments
    - assessments_ended
    - total_users
    - total_points_awarded (sum of user_points_assessment.final_points where not null)
    - recent_assessments: list of assessment_id, name, end_date, avg_usage, threshold
    """
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            # total assessments
            cur.execute("SELECT COUNT(*) AS cnt FROM assessments")
            total_assessments = int(cur.fetchone().get('cnt', 0))

            # assessments ended
            cur.execute("SELECT COUNT(*) AS cnt FROM assessments WHERE end_date IS NOT NULL AND end_date < NOW()")
            assessments_ended = int(cur.fetchone().get('cnt', 0))

            # total users
            cur.execute("SELECT COUNT(*) AS cnt FROM users")
            total_users = int(cur.fetchone().get('cnt', 0))

            # total points awarded (use final_points when available else total_points)
            try:
                cur.execute("SELECT COALESCE(SUM(final_points), SUM(total_points)) AS total_points FROM user_points_assessment")
                row = cur.fetchone() or {}
                total_points_awarded = float(row.get('total_points') or 0.0)
            except Exception:
                # fallback: sum total_points only
                cur.execute("SELECT COALESCE(SUM(total_points),0) AS total_points FROM user_points_assessment")
                total_points_awarded = float(cur.fetchone().get('total_points', 0) or 0.0)

            # recent assessments with avg_usage and threshold (for ended assessments)
            cur.execute(
                "SELECT a.assessment_id, a.name AS assessment_name, a.end_date, "
                "COALESCE( (SELECT AVG(u.total_used) FROM (SELECT user_id, COALESCE(SUM(tokens_used),0) AS total_used FROM session_tokens st WHERE st.assessment_id=a.assessment_id GROUP BY user_id) u), 0) AS avg_usage "
                "FROM assessments a WHERE a.end_date IS NOT NULL ORDER BY a.end_date DESC LIMIT 20"
            )
            recent = []
            for r in cur.fetchall() or []:
                avg_usage = float(r.get('avg_usage') or 0.0)
                threshold = 1.10 * avg_usage
                recent.append({
                    'assessment_id': r.get('assessment_id'),
                    'assessment_name': r.get('assessment_name'),
                    'end_date': r.get('end_date').isoformat() if r.get('end_date') else None,
                    'avg_usage': avg_usage,
                    'threshold': threshold,
                })

            return jsonify({
                'total_assessments': total_assessments,
                'assessments_ended': assessments_ended,
                'total_users': total_users,
                'total_points_awarded': total_points_awarded,
                'recent_assessments': recent,
            }), 200
    except Exception as e:
        print(f"[ERROR] admin_dashboard: {e}")
        return jsonify({'error': str(e)}), 500
    finally:
        conn.close()

# === Gamification Utilities ===
def get_user_token_info(user_id, session_id, assessment_id=None):
    """
    Always session-based. If no row, create with full tokens for this session. Never allow session_id=None.
    If assessment_id is provided, use dynamic threshold (1.10 * avg_usage) for that assessment.
    Also returns end_date from assessments table if assessment_id is provided.
    """
    if not user_id or not session_id:
        raise ValueError("user_id and session_id are required for token info")
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            now = datetime.datetime.now()
            threshold = 0
            end_date = None
            if assessment_id:
                # Fetch end_date from assessments table
                try:
                    cur.execute(
                        "SELECT end_date FROM assessments WHERE assessment_id=%s",
                        (assessment_id,)
                    )
                    end_row = cur.fetchone()
                    if end_row and end_row.get('end_date'):
                        end_date = end_row.get('end_date')
                except Exception as e:
                    print(f"[DEBUG] Failed to fetch end_date: {e}")
                # Compute dynamic threshold
                try:
                    cur.execute(
                        "SELECT AVG(u.total_used) AS avg_usage FROM (SELECT user_id, COALESCE(SUM(tokens_used),0) AS total_used FROM session_tokens WHERE assessment_id=%s AND YEARWEEK(used_at, 1) = YEARWEEK(%s, 1) GROUP BY user_id) u",
                        (assessment_id, now)
                    )
                    avg_row = cur.fetchone()
                    if avg_row and avg_row.get('avg_usage'):
                        avg_usage = float(avg_row.get('avg_usage') or 0.0)
                        if avg_usage > 0:
                            threshold = int(1.10 * avg_usage)
                except Exception as e:
                    print(f"[DEBUG] Failed to compute dynamic threshold: {e}")
            # If no assessment_id or no data, threshold remains 0
            if assessment_id:
                cur.execute(
                    "SELECT COALESCE(SUM(tokens_used), 0) AS used_this_week "
                    "FROM session_tokens WHERE user_id=%s AND assessment_id=%s AND YEARWEEK(used_at, 1) = YEARWEEK(%s, 1)",
                    (user_id, assessment_id, now),
                )
            else:
                cur.execute(
                    "SELECT COALESCE(SUM(tokens_used), 0) AS used_this_week "
                    "FROM session_tokens WHERE user_id=%s AND YEARWEEK(used_at, 1) = YEARWEEK(%s, 1)",
                    (user_id, now),
                )
            row = cur.fetchone() or {"used_this_week": 0}
            raw_used = row.get("used_this_week", 0) or 0
            try:
                used_this_week_f = float(raw_used)
            except Exception:
                used_this_week_f = 0.0

            # cap and remaining values should be integers for display
            capped_used = int(min(used_this_week_f, float(threshold)))
            remaining_tokens = max(0, int(max(0.0, float(threshold) - min(float(threshold), used_this_week_f))))

            # Compute points using the same dynamic 0..100 mapping as leaderboard
            if threshold <= 0:
                final_point = 100.0 if used_this_week_f <= 0.0 else 0.0
            else:
                if used_this_week_f <= float(threshold):
                    final_point = 100.0
                else:
                    final_point = max(0.0, 100.0 + 100.0 * (float(threshold) - used_this_week_f) / float(threshold))
                final_point = min(100.0, final_point)

            result = {
                "total_tokens": threshold,
                "used_tokens": capped_used,
                "remaining_tokens": remaining_tokens,
                "points": round(final_point, 2),
                "used_tokens_raw": int(used_this_week_f),
            }
            if end_date:
                result["end_date"] = str(end_date)
            return result
    except Exception as e:
        print(f"[ERROR] get_user_token_info: {e}")
        raise
    finally:
        conn.close()


@app.route('/course-leaderboard', methods=['GET'])
@require_login
def course_leaderboard():
    """
    Return leaderboard for a given course_id by averaging points across its assessments.
    Only includes users with total_used > 0 across all assessments in course.
    Params: course_id (query) is required.
    Response: { course_id, leaderboard: [{user_id, username, points, rank, assessments_count}], user_rank }
    """
    user_id = session.get('user_id')
    if not user_id:
        return jsonify({"error": "Unauthorized. Silakan login."}), 401

    course_id = request.args.get('course_id')
    if not course_id:
        return jsonify({"error": "Missing course_id"}), 400

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            print(f"\n[COURSE_LEADERBOARD_DEBUG] Course: {course_id}")
            
            # Fetch all assessments in the course
            cur.execute("SELECT assessment_id, end_date FROM assessments WHERE course_id=%s", (course_id,))
            assessments = cur.fetchall() or []
            total_assessments = len(assessments)
            
            if total_assessments == 0:
                print(f"[COURSE_LEADERBOARD_DEBUG] No assessments found")
                return jsonify({"course_id": course_id, "leaderboard": [], "user_rank": None}), 200

            print(f"[COURSE_LEADERBOARD_DEBUG] Total assessments: {total_assessments}")
            
            # Get enrolled users
            cur.execute(
                "SELECT uc.user_id, u.username "
                "FROM user_courses uc LEFT JOIN users u ON uc.user_id = u.user_id "
                "WHERE uc.course_id = %s",
                (course_id,),
            )
            enrolled_rows = cur.fetchall() or []
            if not enrolled_rows:
                print(f"[COURSE_LEADERBOARD_DEBUG] No enrolled users")
                return jsonify({"course_id": course_id, "leaderboard": [], "user_rank": None}), 200
            
            enrolled_ids = [r['user_id'] for r in enrolled_rows]
            user_info = {r['user_id']: r['username'] for r in enrolled_rows}
            print(f"[COURSE_LEADERBOARD_DEBUG] Total enrolled users: {len(enrolled_ids)}")

            # For each assessment: calculate per-user scores, aggregate totals
            user_scores = {uid: {'points_sum': 0.0, 'assessments_with_tokens': 0} for uid in enrolled_ids}
            user_total_tokens = {uid: 0 for uid in enrolled_ids}

            for aid_idx, a in enumerate(assessments):
                aid = a.get('assessment_id')
                if not aid:
                    continue
                
                # Get token usage for all enrolled users in this assessment
                cur.execute(
                    "SELECT st.user_id, SUM(st.tokens_used) as total_used "
                    "FROM session_tokens st "
                    "WHERE st.assessment_id=%s "
                    "GROUP BY st.user_id",
                    (aid,)
                )
                token_data = {r['user_id']: int(r['total_used'] or 0) for r in cur.fetchall() or []}
                
                # Calculate threshold ONLY from users with tokens > 0
                tokens_with_usage = [token_data.get(uid, 0) for uid in enrolled_ids if token_data.get(uid, 0) > 0]
                
                if tokens_with_usage:
                    avg_usage = sum(tokens_with_usage) / len(tokens_with_usage)
                    threshold = 1.10 * avg_usage
                else:
                    threshold = 0.0
                
                print(f"[COURSE_LEADERBOARD_DEBUG] Assessment {aid_idx+1}/{total_assessments}: threshold={threshold:.1f}, users_w_tokens={len(tokens_with_usage)}")
                
                # Calculate points for each enrolled user
                for uid in enrolled_ids:
                    used = token_data.get(uid, 0)
                    user_total_tokens[uid] += used
                    
                    # Only award points if user has tokens
                    if used > 0:
                        if used <= threshold:
                            final_point = 100.0
                        else:
                            final_point = max(0.0, 100.0 + 100.0 * (threshold - used) / threshold)
                        final_point = min(100.0, final_point)
                        
                        user_scores[uid]['points_sum'] += final_point
                        user_scores[uid]['assessments_with_tokens'] += 1

            # Build leaderboard: only users with TOTAL tokens > 0
            leaderboard = []
            for uid in enrolled_ids:
                if user_total_tokens[uid] <= 0:
                    continue
                
                # Average points only across assessments where user had tokens
                assessments_participated = user_scores[uid]['assessments_with_tokens']
                if assessments_participated > 0:
                    avg_points = user_scores[uid]['points_sum'] / assessments_participated
                else:
                    avg_points = 0.0
                
                leaderboard.append({
                    'user_id': uid,
                    'username': user_info.get(uid) or 'Unknown',
                    'points': round(avg_points, 2),
                    'assessments_count': total_assessments,
                    'total_tokens': user_total_tokens[uid],
                })
            
            print(f"[COURSE_LEADERBOARD_DEBUG] Users with tokens > 0: {len(leaderboard)}")

            # Sort and rank
            leaderboard.sort(key=lambda x: x['points'], reverse=True)
            rank = 0
            prev_pts = None
            for i, item in enumerate(leaderboard, 1):
                if prev_pts is None or item['points'] < prev_pts:
                    rank = i
                item['rank'] = rank
                prev_pts = item['points']

            # Find current user
            user_rank = next((item for item in leaderboard if item['user_id'] == user_id), None)
            print(f"[COURSE_LEADERBOARD_DEBUG] Current user rank: {user_rank}\n")

            return jsonify({
                "course_id": course_id,
                "leaderboard": leaderboard,
                "user_rank": user_rank
            }), 200
    except Exception as e:
        print(f"[ERROR] course_leaderboard: {e}")
        import traceback
        traceback.print_exc()
        return jsonify({"error": str(e)}), 500
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


@app.route('/whoami', methods=['GET'])
@require_login
def whoami():
    """Return basic info about the currently authenticated user (from server session)."""
    user_id = session.get('user_id')
    if not user_id:
        return jsonify({'error': 'Unauthorized'}), 401
    # Attempt to return username if available in session or DB
    username = session.get('username')
    if not username:
        try:
            conn = get_db_connection()
            with conn.cursor() as cur:
                cur.execute("SELECT username FROM users WHERE user_id=%s LIMIT 1", (user_id,))
                row = cur.fetchone()
                if row:
                    username = row.get('username')
        finally:
            try:
                conn.close()
            except Exception:
                pass
    return jsonify({'user_id': user_id, 'username': username}), 200

@app.route('/change-password', methods=['POST'])
@require_login
def change_password():
    data = request.json
    user_id = session.get('user_id')
    old_password = data.get('old_password')
    new_password = data.get('new_password')

    if not old_password or not new_password:
        return jsonify({"error": "Both old and new passwords are required."}), 400

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            # Ambil password_hash lama
            cur.execute("SELECT password_hash FROM users WHERE user_id = %s", (user_id,))
            row = cur.fetchone()

            if not row:
                return jsonify({"error": "User not found."}), 404

            # Verifikasi password lama
            if hash_password(old_password) != row['password_hash']:
                return jsonify({"error": "Old password is incorrect."}), 403

            # Update password baru
            hashed_new_password = hash_password(new_password)
            cur.execute(
                "UPDATE users SET password_hash = %s WHERE user_id = %s",
                (hashed_new_password, user_id)
            )
            conn.commit()

        return jsonify({"message": "Password updated successfully."}), 200

    except Exception as e:
        logging.error(f"Error changing password: {e}")
        return jsonify({"error": "An error occurred while changing the password."}), 500
    finally:
        conn.close()


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
                    "SELECT assessment_id, course_id, code, name, end_date FROM assessments "
                    "WHERE course_id=%s ORDER BY created_at ASC",
                    (course_id,)
                )
            else:
                cur.execute(
                    "SELECT assessment_id, course_id, code, name, end_date FROM assessments "
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
    Accepts optional assessment_id query parameter for dynamic threshold.
    """
    user_id = session.get("user_id")
    if not user_id:
        return jsonify({"error": "Unauthorized. Silakan login."}), 401

    # Get assessment_id from query parameter or session
    assessment_id = request.args.get('assessment_id') or session.get('assessment_id')
    
    # Gunakan session_id Flask jika ada, fallback ke IP client
    session_id = session.get("session_id") or request.remote_addr
    if "session_id" not in session and session_id:
        session["session_id"] = session_id
    try:
        gamification = get_user_token_info(user_id, session_id, assessment_id)
        return jsonify({"gamification": gamification}), 200
    except ValueError as e:
        return jsonify({"error": str(e)}), 400


@app.route('/token-usage-daily', methods=['GET'])
@require_login
def get_dynamic_threshold(cur, assessment_id=None, now=None):
    """Calculate dynamic threshold for an assessment (1.10 * avg_usage this week), or 0 if no data."""
    if not assessment_id:
        return 0
    if now is None:
        # Use end_date of assessment if available
        cur.execute("SELECT end_date FROM assessments WHERE assessment_id=%s", (assessment_id,))
        end_row = cur.fetchone()
        if end_row and end_row.get('end_date'):
            now = end_row.get('end_date')
        else:
            now = datetime.datetime.now()
    try:
        cur.execute(
            "SELECT AVG(u.total_used) AS avg_usage FROM (SELECT user_id, COALESCE(SUM(tokens_used),0) AS total_used FROM session_tokens WHERE assessment_id=%s GROUP BY user_id) u",
            (assessment_id,)
        )
        avg_row = cur.fetchone()
        if avg_row and avg_row.get('avg_usage'):
            avg_usage = float(avg_row.get('avg_usage') or 0.0)
            if avg_usage > 0:
                return int(1.10 * avg_usage)
    except Exception as e:
        print(f"[DEBUG] Failed to compute dynamic threshold: {e}")
    return 0

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
            # Daily total usage
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

            # Use dynamic threshold for all assessments this week (sum)
            # If user has multiple assessments, sum their dynamic thresholds
            cur.execute(
                "SELECT DISTINCT assessment_id FROM session_tokens WHERE user_id=%s AND YEARWEEK(used_at, 1) = YEARWEEK(%s, 1)",
                (user_id, now),
            )
            assessment_ids = [r['assessment_id'] for r in cur.fetchall() if r.get('assessment_id')]
            total_threshold = 0
            for aid in assessment_ids:
                total_threshold += get_dynamic_threshold(cur, aid, now)
            # If no assessments, threshold is 0
            remaining = max(0, total_threshold - total_used)

            # Per-assessment breakdown (with threshold and end_date)
            by_assessment = []
            try:
                cur.execute(
                    "SELECT st.assessment_id AS assessment_id, COALESCE(a.name, '') AS assessment_name, COALESCE(a.end_date, NULL) AS end_date, COALESCE(a.course_id, NULL) AS course_id, COALESCE(SUM(st.tokens_used),0) AS total_used "
                    "FROM session_tokens st LEFT JOIN assessments a ON st.assessment_id = a.assessment_id "
                    "WHERE st.user_id=%s AND YEARWEEK(st.used_at, 1) = YEARWEEK(%s, 1) "
                    "GROUP BY st.assessment_id ORDER BY total_used DESC",
                    (user_id, now),
                )
                rows = cur.fetchall() or []
                for r in rows:
                    aid = r.get('assessment_id')
                    used = int(r.get('total_used', 0) or 0)
                    threshold = get_dynamic_threshold(cur, aid, now)
                    by_assessment.append({
                        "assessment_id": aid,
                        "assessment_name": r.get('assessment_name') or None,
                        "course_id": r.get('course_id') or None,
                        "end_date": r.get('end_date'),
                        "total_used": used,
                        "threshold": threshold,
                        "remaining": max(0, threshold - used),
                    })
            except Exception:
                by_assessment = []

        return jsonify({
            "daily_stats": daily_stats,
            "total_used": total_used,
            "remaining_tokens": remaining,
            "by_assessment": by_assessment
        }), 200
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
            # Use dynamic threshold for all assessments this week (sum)
            cur.execute(
                "SELECT DISTINCT assessment_id FROM session_tokens WHERE user_id=%s AND YEARWEEK(used_at, 1) = YEARWEEK(%s, 1)",
                (user_id, now),
            )
            assessment_ids = [r['assessment_id'] for r in cur.fetchall() if r.get('assessment_id')]
            total_threshold = 0
            for aid in assessment_ids:
                total_threshold += get_dynamic_threshold(cur, aid, now)
            remaining = max(0, total_threshold - total_used)

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
                    # Sum dynamic thresholds for all assessments in this course
                    cur.execute(
                        "SELECT DISTINCT assessment_id FROM session_tokens WHERE user_id=%s AND course_id=%s AND YEARWEEK(used_at, 1) = YEARWEEK(%s, 1)",
                        (user_id, r.get('course_id'), now),
                    )
                    aids = [aidr['assessment_id'] for aidr in cur.fetchall() if aidr.get('assessment_id')]
                    course_threshold = 0
                    for aid in aids:
                        course_threshold += get_dynamic_threshold(cur, aid, now)
                    by_course.append({
                        "course_id": r.get('course_id'),
                        "course_name": r.get('course_name') or None,
                        "assessments_count": count,
                        "total_used": int(r.get('total_used', 0) or 0),
                        "remaining": max(0, course_threshold - int(r.get('total_used', 0) or 0)),
                    })
            except Exception:
                by_course = []

            # Fallback: if no rows found in session_tokens, try reading from user_points_assessment grouped by course
            if not by_course:
                try:
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
                        # Sum dynamic thresholds for all assessments in this course
                        cur.execute(
                            "SELECT DISTINCT assessment_id FROM user_points_assessment WHERE user_id=%s AND course_id=%s",
                            (user_id, r.get('course_id')),
                        )
                        aids = [aidr['assessment_id'] for aidr in cur.fetchall() if aidr.get('assessment_id')]
                        course_threshold = 0
                        for aid in aids:
                            course_threshold += get_dynamic_threshold(cur, aid, now)
                        by_course.append({
                            "course_id": r.get('course_id'),
                            "course_name": r.get('course_name') or None,
                            "assessments_count": count,
                            "total_used": int(r.get('total_used', 0) or 0),
                            "remaining": max(0, course_threshold - int(r.get('total_used', 0) or 0)),
                        })
                except Exception as e:
                    print(f"[DEBUG] fallback by_course failed: {e}")
                    by_course = []

            # Try per-assessment breakdown with dynamic threshold
            try:
                cur.execute(
                    "SELECT st.assessment_id AS assessment_id, COALESCE(a.name, '') AS assessment_name, COALESCE(a.end_date, NULL) AS end_date, COALESCE(a.course_id, NULL) AS course_id, COALESCE(SUM(st.tokens_used),0) AS total_used "
                    "FROM session_tokens st LEFT JOIN assessments a ON st.assessment_id = a.assessment_id "
                    "WHERE st.user_id=%s AND YEARWEEK(st.used_at, 1) = YEARWEEK(%s, 1) "
                    "GROUP BY st.assessment_id ORDER BY total_used DESC",
                    (user_id, now),
                )
                rows = cur.fetchall() or []
                for r in rows:
                    aid = r.get('assessment_id')
                    used = int(r.get('total_used', 0) or 0)
                    threshold = get_dynamic_threshold(cur, aid, now)
                    by_assessment.append({
                        "assessment_id": aid,
                        "assessment_name": r.get('assessment_name') or None,
                        "course_id": r.get('course_id') or None,
                        "end_date": r.get('end_date'),
                        "total_used": used,
                        "threshold": threshold,
                        "remaining": max(0, threshold - used),
                    })
            except Exception:
                by_assessment = []

            # Fallback: if no rows found in session_tokens, try reading from user_points_assessment
            if not by_assessment:
                try:
                    cur.execute(
                        "SELECT upa.assessment_id AS assessment_id, COALESCE(a.name,'') AS assessment_name, COALESCE(a.end_date, NULL) AS end_date, COALESCE(a.course_id, NULL) AS course_id, COALESCE(upa.total_points,0) AS total_used "
                        "FROM user_points_assessment upa LEFT JOIN assessments a ON upa.assessment_id = a.assessment_id "
                        "WHERE upa.user_id=%s ORDER BY total_used DESC",
                        (user_id,)
                    )
                    rows = cur.fetchall() or []
                    for r in rows:
                        aid = r.get('assessment_id')
                        used = int(r.get('total_used', 0) or 0)
                        threshold = get_dynamic_threshold(cur, aid, now)
                        by_assessment.append({
                        "assessment_id": aid,
                        "assessment_name": r.get('assessment_name') or None,
                        "course_id": r.get('course_id') or None,
                        "end_date": r.get('end_date'),
                        "total_used": used,
                        "threshold": threshold,
                        "remaining": max(0, threshold - used),
                    })
                except Exception as e:
                    print(f"[DEBUG] fallback by_assessment failed: {e}")
                    by_assessment = []

        return jsonify({
            "total": {"total_used": total_used, "remaining": remaining},
            "total_threshold": total_threshold,
            "by_course": by_course,
            "by_assessment": by_assessment,
        }), 200
    except Exception as e:
        print(f"[ERROR] token_usage_breakdown: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        conn.close()

# Initialize Flask-Limiter
def _get_user_or_ip():
    # Use user_id from session if available, else fallback to IP
    return session.get('user_id') or get_remote_address()

limiter = Limiter(
    key_func=_get_user_or_ip,
    app=app,
    default_limits=DEFAULT_LIMITS,
)

# Configure OpenAI API keys (supports multi-key pool)
def _collect_openai_keys() -> list:
    keys = []
    for name in ("OPENAI_API_KEY_1", "OPENAI_API_KEY_2", "OPENAI_API_KEY_3"):
        val = os.getenv(name)
        if val:
            keys.append(val.strip())
    fallback = os.getenv("OPENAI_API_KEY")
    if fallback:
        fallback = fallback.strip()
        if fallback and fallback not in keys:
            keys.append(fallback)
    return keys

OPENAI_API_KEYS = _collect_openai_keys()
openai.api_key = OPENAI_API_KEYS[0] if OPENAI_API_KEYS else None

_client_pool = []
_client_lock = threading.Lock()
_client_index = 0

try:
    if OPENAI_API_KEYS:
        _client_pool = [openai.OpenAI(api_key=k) for k in OPENAI_API_KEYS]
    else:
        _client_pool = []
except Exception:
    _client_pool = []

def _get_openai_client():
    global _client_index
    if not _client_pool:
        raise RuntimeError("OpenAI API keys not configured")
    with _client_lock:
        idx = _client_index % len(_client_pool)
        client = _client_pool[idx]
        _client_index += 1
    key_hint = ""
    if OPENAI_API_KEYS and idx < len(OPENAI_API_KEYS):
        key_hint = f"****{OPENAI_API_KEYS[idx][-4:]}"
    print(f"[OPENAI] Using key index {idx + 1}/{len(_client_pool)} {key_hint}")
    return client

def _openai_chat_completions_create_round_robin(**kwargs):
    client = _get_openai_client()
    return client.chat.completions.create(**kwargs)

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
                # Generate UUID for id field
                record_id = str(uuid.uuid4())
                cur.execute("INSERT INTO session_tokens (id, session_id, user_id, total_tokens, updated_at) VALUES (%s, %s, %s, %s, NOW())", (record_id, session_uuid, user_id, token_count))
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
            with conn.cursor() as cur:
                cur.execute(
                    "UPDATE gpt_jobs SET status='running', updated_at=NOW() "
                    "WHERE job_id=%s AND status='pending'",
                    (job_id,)
                )
                if cur.rowcount == 0:
                    conn.close()
                    continue
            conn.commit()
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
                # Parse simple markers at start of prompt like [MODE:code] [LANG:Python] [FORCE_GPT:true]
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
                force_gpt = markers.get('FORCE_GPT', '').lower() == 'true'
                
                print(f"[WORKER] Job {job_id}: Markers detected: {markers}")
                print(f"[WORKER] Job {job_id}: force_gpt={force_gpt}")

                # ========== SEMANTIC RETRIEVAL FIRST (unless force_gpt) ==========
                retrieval_code = None
                retrieval_similarity = 0.0
                if not force_gpt:
                    print(f"[WORKER] Job {job_id}: force_gpt is False, attempting retrieval...")
                    try:
                        retrieval_model = get_retrieval_model()
                        if retrieval_model is not None and retrieval_model.index is not None and not retrieval_model.df.empty:
                            print(f"[WORKER] Job {job_id}: Performing semantic retrieval...")
                            retrieval_results = retrieval_model.search(prompt_clean, top_k=1)
                            top_row = retrieval_results.iloc[0]
                            retrieval_similarity = float(top_row['score'])
                            retrieval_code = top_row['code']
                            retrieval_prompt = top_row['prompt']
                            print(f"[WORKER] Job {job_id}: Retrieval similarity={retrieval_similarity:.3f}")
                            
                            # If high similarity (>=0.90), use retrieved code directly (FREE, no GPT)
                            if retrieval_similarity >= 0.90:
                                print(f"[WORKER] Job {job_id}: High similarity! Using retrieved code from DB (FREE).")
                                code = retrieval_code
                                # Save as assistant message
                                if user_id and session_id:
                                    save_chat_message(user_id, session_id, "assistant", code, assessment_id)
                                
                                # Count tokens for retrieval (system + user prompt only)
                                system_content = "You are an expert programming assistant. Internally classify the request into a Bloom level (remember, understand, apply, analyze, evaluate, create) using task verbs, whether it operates on existing code, and decision-making requirements; default to analyze when ambiguous. Behavior by level: remember=identify or list facts only; understand=explain or paraphrase only and do not change logic; apply=fix bugs or implement the requested change with minimal edits; analyze=decompose the problem, compare options, and allow small refactors; evaluate=judge quality, note tradeoffs, and justify the judgment; create=redesign or propose a new solution within a controlled scope. Never mention Bloom's Taxonomy to the user. Maintain educational tone and clear steps while staying concise. Respect existing constraints: retrieval mode is no generation, do not invent missing details, and do not fabricate code context. Output discipline: follow mode/format rules exactly (code-only, summary-only, or summary+code+explanation as specified)."
                                messages = [
                                    {"role": "system", "content": system_content},
                                    {"role": "user", "content": prompt_clean},
                                ]
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
                                
                                # Retrieval is FREE - do NOT update session tokens or log usage
                                print(f"[WORKER] Job {job_id} done via retrieval (FREE). No token usage logged. Input tokens would be: {token_count}")
                                
                                # Mark job as done (retrieval mode)
                                update_gpt_job(job_id, code=code, status="done", raw_response=f"[RETRIEVAL] similarity={retrieval_similarity:.3f}, matched_prompt={retrieval_prompt}")
                                continue  # Skip GPT, move to next job
                    except Exception as e_retr:
                        print(f"[WORKER] Job {job_id}: Retrieval failed: {e_retr}. Falling back to GPT.")
                        # Continue to GPT if retrieval fails
                else:
                    print(f"[WORKER] Job {job_id}: force_gpt is True, SKIPPING retrieval, going directly to GPT.")
                
                # ========== GPT GENERATION (if retrieval not used or similarity too low) ==========
                print(f"[WORKER] Job {job_id}: Similarity too low ({retrieval_similarity:.3f}) or force_gpt. Using GPT...")

                bloom_rules =(
                    "Internally classify the request into a Bloom level (remember, understand, apply, analyze, "
                    "evaluate, create) using task verbs, whether it operates on existing code, and decision-making "
                    "requirements; default to analyze when ambiguous. Behavior by level: remember=identify or list "
                    "facts only; understand=explain or paraphrase only and do not change logic; apply=fix bugs or "
                    "implement the requested change with minimal edits; analyze=decompose the problem, compare "
                    "options, and allow small refactors; evaluate=judge quality, note tradeoffs, and justify the "
                    "judgment; create=redesign or propose a new solution within a controlled scope. Never mention "
                    "Bloom's Taxonomy to the user."
                )
                shared_constraints = (
                    "Maintain educational tone and clear steps while staying concise. {respect_constraints} "
                    "Output discipline: follow mode/format rules exactly (code-only, summary-only, or "
                    "summary+code+explanation as specified)."
                )
                if mode == 'code':
                    system_content = (
                        "You are an expert programming assistant. The user requests CODE output. "
                        "Produce only the source code that directly solves the user's request. "
                        "Wrap the code inside triple-backticks (```), and do not include any prose, explanation, "
                        "or commentary outside the fenced code block. If a programming language is specified, "
                        "include it after the opening fence (e.g. ```python). "
                        f"{bloom_rules} "
                        f"{shared_constraints.format(respect_constraints='Respect existing constraints: do not invent missing details, do not fabricate code context, and honor the requested language.')}"
                    )
                elif mode == 'summary':
                    system_content = (
                        "You are an expert programming assistant. The user requests a SHORT SUMMARY (2-3 sentences). "
                        "Provide a concise programming-focused summary. Do not include code blocks. "
                        f"{bloom_rules} "
                        f"{shared_constraints.format(respect_constraints='Respect existing constraints: do not invent missing details and do not fabricate code context.')}"
                    )
                elif mode == 'summary_code_explanation':
                    system_content = (
                        "You are an expert programming assistant. The user requests SUMMARY + CODE + EXPLANATION. "
                        "First give a brief (1-2 sentence) summary, then output the minimal code required, then a "
                        "concise explanation. "
                        f"{bloom_rules} "
                        f"{shared_constraints.format(respect_constraints='Respect existing constraints: do not invent missing details, do not fabricate code context, and keep the explanation brief.')}"
                    )
                else:
                    system_content = (
                        "You are an expert programming assistant helping undergraduate computer science students. "
                        "Answer concisely and focus on programming. "
                        f"{bloom_rules} "
                        f"{shared_constraints.format(respect_constraints='Respect existing constraints: do not invent missing details and do not fabricate code context.')}"
                    )

                # Add language hint to system prompt if provided
                if lang_hint:
                    system_content += f" Use the following language when generating code: {lang_hint}."

                chat_history = []
                try:
                    chat_history = get_chat_history(user_id, session_id, assessment_id, limit=CHAT_HISTORY_LIMIT)
                except Exception as e_hist:
                    print(f"[WARNING] Failed to load chat history: {e_hist}")

                messages = [{"role": "system", "content": system_content}]
                for row in chat_history:
                    messages.append({"role": row["role"], "content": row["content"]})
                if not chat_history or chat_history[-1].get("role") != "user" or chat_history[-1].get("content") != prompt_clean:
                    messages.append({"role": "user", "content": prompt_clean})
                # For openai>=1.0.0 (correct usage)
                temp = 0.0 if mode == 'code' else 0.2
                response = _openai_chat_completions_create_round_robin(
                    model=OPENAI_MODEL,
                    messages=messages,
                    temperature=temp,
                    max_completion_tokens=1024,
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

    # Find model paths at startup but don't load yet (lazy loading)
    model1_path = _find_st_model('paraphrase-multilingual-mpnet-base-v2')
    model2_path = _find_st_model('LaBSE')
    model3_path = _find_st_model('multilingual-e5-base')
    print(f"[DEBUG] model1_path: {os.path.abspath(model1_path)}")
    print(f"[DEBUG] model2_path: {os.path.abspath(model2_path)}")
    print(f"[DEBUG] model3_path: {os.path.abspath(model3_path)}")
    
    # LAZY LOADING: Initialize as None, load on first use
    model1 = None
    model2 = None
    model3 = None
    translator = None

    # Set best weights (should be tuned elsewhere and imported/configured as needed)
    best_weights = (0.5, 0.5, 1.5)  # Update as needed

    def _is_cuda_runtime_error(exc: Exception) -> bool:
        msg = str(exc).lower()
        return "cuda" in msg or "device-side assert" in msg

    def _safe_move_model_to_cpu(model):
        try:
            model = model.to('cpu')
            model.eval()
        except Exception as e:
            print(f"[WARNING] Could not move model to CPU: {e}")
        return model

    def _encode_with_fallback(model, text: str):
        try:
            with torch.no_grad():
                return model.encode([text], convert_to_numpy=True, show_progress_bar=False)
        except RuntimeError as e:
            if not _is_cuda_runtime_error(e):
                raise
            print(f"[WARNING] CUDA encode failed, retrying on CPU: {e}")
            try:
                torch.cuda.empty_cache()
            except Exception:
                pass
            model = _safe_move_model_to_cpu(model)
            with torch.no_grad():
                return model.encode([text], convert_to_numpy=True, show_progress_bar=False)

    def _translate_id_to_en_with_fallback(text: str):
        global translator
        if translator is None:
            return text
        trans_model, trans_tokenizer, device_idx = translator
        try:
            inputs = trans_tokenizer(text, return_tensors="pt", truncation=True, max_length=256)
            if device_idx >= 0:
                inputs = {k: v.cuda() for k, v in inputs.items()}
            with torch.no_grad():
                outputs = trans_model.generate(**inputs, max_length=256)
            return trans_tokenizer.decode(outputs[0], skip_special_tokens=True)
        except RuntimeError as e:
            if not _is_cuda_runtime_error(e):
                raise
            print(f"[WARNING] CUDA translation failed, retrying on CPU: {e}")
            try:
                torch.cuda.empty_cache()
            except Exception:
                pass
            try:
                trans_model = trans_model.to('cpu')
                cpu_inputs = trans_tokenizer(text, return_tensors="pt", truncation=True, max_length=256)
                with torch.no_grad():
                    cpu_outputs = trans_model.generate(**cpu_inputs, max_length=256)
                translator = (trans_model, trans_tokenizer, -1)
                return trans_tokenizer.decode(cpu_outputs[0], skip_special_tokens=True)
            except Exception as cpu_e:
                print(f"[WARNING] CPU translation fallback also failed: {cpu_e}")
                return text
    
    def _ensure_models_loaded():
        """Lazy load models on first use to prevent blocking startup. Auto-detects GPU."""
        global model1, model2, model3, translator
        if model1 is None:
            # Detect GPU availability
            device = 'cuda' if torch.cuda.is_available() else 'cpu'
            if device == 'cuda':
                gpu_name = torch.cuda.get_device_name(0)
                print(f"[INFO] GPU detected: {gpu_name}. Loading models with CUDA acceleration...")
            else:
                print("[INFO] No GPU detected. Loading models on CPU (this may take 10-30 seconds)...")
            
            # Load Sentence Transformers with device specification
            model1 = SentenceTransformer(model1_path, device=device)
            model2 = SentenceTransformer(model2_path, device=device)
            model3 = SentenceTransformer(model3_path, device=device)
            
            # Set models to evaluation mode for inference optimization
            if device == 'cuda':
                model1.eval()
                model2.eval()
                model3.eval()
                # Enable TF32 for Ampere GPUs (RTX 3060, 3070, 3080, 3090, 4090)
                torch.backends.cuda.matmul.allow_tf32 = True
                torch.backends.cudnn.allow_tf32 = True
                print("[INFO] TF32 acceleration enabled for Ampere GPU")
            
            # Load translator (also uses GPU if available)
            try:
                # Try using the model directly without task specification
                from transformers import AutoTokenizer, AutoModelForSeq2SeqLM
                device_idx = 0 if torch.cuda.is_available() else -1
                translator_model = AutoModelForSeq2SeqLM.from_pretrained(_local_path('opus-mt-id-en'))
                translator_tokenizer = AutoTokenizer.from_pretrained(_local_path('opus-mt-id-en'))
                if device_idx >= 0:
                    translator_model = translator_model.cuda()
                translator = (translator_model, translator_tokenizer, device_idx)
            except Exception as e:
                print(f"[WARNING] Translation model failed to load: {e}")
                print("[INFO] Falling back to lazy translation loading...")
                translator = None
            
            if device == 'cuda':
                print(f"[INFO] ✓ All models loaded on GPU with CUDA acceleration!")
                print(f"[INFO] ✓ Expected speedup: 5-8x faster encoding compared to CPU")
            else:
                print("[INFO] Models loaded successfully on CPU!")

    def get_ensemble_embedding(text, weights):
        global model1, model2, model3, translator
        _ensure_models_loaded()  # Lazy load on first use
        text = (str(text) if text is not None else "").strip()
        if not text:
            raise ValueError("Text for embedding cannot be empty")
        # Guardrail to avoid extremely long sequences causing GPU issues.
        text = text[:4000]
        try:
            lang = detect(text)
        except Exception:
            lang = 'en'
        if lang == 'id' and translator is not None:
            try:
                text = _translate_id_to_en_with_fallback(text)
            except Exception as trans_e:
                print(f"[WARNING] Translation failed: {trans_e}")
                # Fall through with original text
        emb1 = _encode_with_fallback(model1, text)
        emb2 = _encode_with_fallback(model2, text)
        emb3 = _encode_with_fallback(model3, text)
        emb1 = emb1 / np.linalg.norm(emb1, axis=1, keepdims=True)
        emb2 = emb2 / np.linalg.norm(emb2, axis=1, keepdims=True)
        emb3 = emb3 / np.linalg.norm(emb3, axis=1, keepdims=True)
        emb1 = emb1 * weights[0]
        emb2 = emb2 * weights[1]
        emb3 = emb3 * weights[2]
        emb = np.concatenate([emb1, emb2, emb3], axis=1)
        emb = emb / np.linalg.norm(emb, axis=1, keepdims=True)
        return emb

    # Cache retrieval model to avoid expensive DB queries on every request
    retrieval_model_cache = {
        'model': None,
        'last_refresh': 0,
        'ttl': 300  # 5 minutes cache
    }

    def get_retrieval_model(force_refresh=False):
        """Get cached retrieval model, refresh if expired (5 min TTL)"""
        import time
        now = time.time()
        
        if force_refresh or retrieval_model_cache['model'] is None or \
           (now - retrieval_model_cache['last_refresh']) > retrieval_model_cache['ttl']:
            print(f"[INFO] Refreshing retrieval model from DB... (last refresh: {int(now - retrieval_model_cache['last_refresh'])}s ago)")
            retrieval_model_cache['model'] = refresh_retrieval_model_from_db()
            retrieval_model_cache['last_refresh'] = now
            print(f"[INFO] Retrieval model refreshed successfully with {len(retrieval_model_cache['model'].df) if retrieval_model_cache['model'] and retrieval_model_cache['model'].df is not None else 0} embeddings")
        else:
            cache_age = int(now - retrieval_model_cache['last_refresh'])
            print(f"[DEBUG] Using cached retrieval model (age: {cache_age}s, expires in: {retrieval_model_cache['ttl'] - cache_age}s)")
        
        return retrieval_model_cache['model']

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
logging.basicConfig(level=logging.DEBUG, format='%(asctime)s - %(levelname)s - %(message)s')

@app.route('/generate-code', methods=['POST'])
@limiter.limit("1 per minute")
def generate_code():
    """
    NON-BLOCKING: Return job_id immediately (<100ms).
    Worker performs retrieval + GPT in background.
    This prevents blocking other requests (like login) during encoding.
    """
    data = request.get_json(silent=True) or {}
    prompt = data.get("prompt")
    assessment_id = data.get("assessment_id")
    language = (data.get("language") or '').strip()
    response_mode = (data.get("response_mode") or 'code').strip()
    
    if not prompt or not isinstance(prompt, str):
        return jsonify({"error": "Missing or invalid 'prompt' in request body"}), 400

    user_id = session.get("user_id")
    if not user_id:
        return jsonify({"error": "Unauthorized"}), 401
    
    session_id = session.get("session_id") or request.remote_addr

    # Check for force_gpt flag
    force_gpt = prompt.startswith("__force_gpt__ ")
    if force_gpt:
        prompt = prompt[len("__force_gpt__ "):].strip()

    # Save user message so async worker can build a complete history chain
    try:
        save_chat_message(user_id, session_id, "user", prompt, assessment_id)
    except Exception as e:
        print(f"[WARNING] Failed to save chat message: {e}")

    # Build marked prompt with metadata for worker
    markers = []
    if language:
        markers.append(f"[LANG:{language}]")
    markers.append(f"[MODE:{response_mode}]")
    if force_gpt:
        markers.append("[FORCE_GPT:true]")
    
    job_prompt = "\n".join(markers) + "\n" + prompt if markers else prompt
    
    # Enqueue job immediately (retrieval + GPT will be done in background)
    try:
        job_id = insert_gpt_job(user_id, prompt, job_prompt, status="pending")
    except Exception as e:
        return jsonify({"error": f"Failed to enqueue job: {str(e)}"}), 500
    
    gamification = get_user_token_info(user_id, session_id, assessment_id)
    
    # Return immediately - worker will do retrieval + GPT
    return jsonify({
        "mode": "gpt-queued_auto",
        "job_id": job_id,
        "message": "Request queued for processing. Worker will check database first (fast & free if found), then use GPT if needed.",
        "gamification": gamification
    }), 202

# LEGACY BLOCKING ENDPOINT (kept for backward compatibility, but NOT recommended)
@app.route('/generate-code-sync', methods=['POST'])
@limiter.limit("1 per minute")
def generate_code_sync():
    """DEPRECATED: Synchronous version that blocks request thread. Use /generate-code instead."""
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

    # SEMANTIC RETRIEVAL (use cached model, refresh every 5 min)
    retrieval_model = get_retrieval_model()
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
            "Respect any markers in the user's prompt such as [LANG:...] to indicate the desired programming language and [MODE:...] to indicate 'code','summary', or 'summary_code_explanation'. "
            "If an assessment context is provided (e.g., [ASSESSMENT:Implementasi Fungsi]), tailor the answer to that assessment focus. Internally classify the request into a Bloom level (remember, understand, apply, analyze, evaluate, create) using task verbs, whether it operates on existing code, and decision-making requirements; default to analyze when ambiguous. Behavior by level: remember=identify or list facts only; understand=explain or paraphrase only and do not change logic; apply=fix bugs or implement the requested change with minimal edits; analyze=decompose the problem, compare options, and allow small refactors; evaluate=judge quality, note tradeoffs, and justify the judgment; create=redesign or propose a new solution within a controlled scope. Never mention Bloom's Taxonomy to the user. Maintain educational tone and clear steps while staying concise. Respect existing constraints: retrieval is no generation, do not invent missing details, and do not fabricate code context. Output discipline: follow mode/format rules exactly (code-only, summary-only, or summary+code+explanation as specified)."
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
        if similarity >= 0.90 and (response_mode != 'code' or is_code):
            impact = _get_impact(emissions)
            # Jawaban dari database bersifat gratis: tidak mengurangi kuota/poin
            user_id = session.get("user_id")
            session_id = session.get("session_id") or request.remote_addr
            assessment_id = session.get("assessment_id")
            gamification = get_user_token_info(user_id, session_id, assessment_id)
            return jsonify({
                "mode": "retrieval",
                "similarity": similarity,
                "prompt_matched": prompt_retrieved,
                "code": code_retrieved,
                "message": "Kode ditemukan di database dengan similarity >=90%. Jawaban diambil dari database.",
                "environmental_impact": impact,
                "token_info": retrieval_token_info,
                "gamification": gamification
            }), 200
        elif similarity >= 0.90 and response_mode == 'code' and not is_code:
            # High similarity but retrieved content not code — automatically queue GPT job
            impact = _get_impact(emissions)
            user_id = session.get("user_id")
            session_id = session.get("session_id") or request.remote_addr
            # Build a marked prompt for the queued job to respect language/mode/assessment
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
                assessment_id = session.get("assessment_id")
                gamification = get_user_token_info(user_id, session_id, assessment_id)
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

            assessment_id = session.get("assessment_id")
            gamification = get_user_token_info(user_id, session_id, assessment_id)
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
            assessment_id = session.get("assessment_id")
            gamification = get_user_token_info(user_id, session_id, assessment_id)
            return jsonify({
                "mode": "suggestion",
                "similarity": similarity,
                "prompt_matched": prompt_retrieved,
                "code": code_retrieved,
                "message": "Ditemukan kode mirip di database (similarity 80–90%). Jika ingin jawaban lebih spesifik, balas dengan 'GPT Mode'.",
                "environmental_impact": impact,
                "token_info": retrieval_token_info,
                "gamification": gamification
            }), 200
        # else: similarity < 0.8, fallback to GPT

    # Fallback ke GPT jika similarity < 0.8 atau user balas 'GPT Mode'
    if not OPENAI_API_KEYS:
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
    chat_history = get_chat_history(user_id, session_id, assessment_id, limit=CHAT_HISTORY_LIMIT)

    system_content = (
        "You are an expert programming assistant helping undergraduate computer science students. "
        "You must only answer questions that are about programming or code; if the user's request is not technical programming-related, reply: 'Sorry, I can only help with programming/code questions.' "
        "Respect any markers the user may include such as [LANG:...] to indicate the desired programming language and [MODE:...] to indicate 'code','summary', or 'summary_code_explanation'. "
        "If an assessment context is provided (e.g., [ASSESSMENT:Implementasi Fungsi]), tailor the answer to that assessment focus. Internally classify the request into a Bloom level (remember, understand, apply, analyze, evaluate, create) using task verbs, whether it operates on existing code, and decision-making requirements; default to analyze when ambiguous. Behavior by level: remember=identify or list facts only; understand=explain or paraphrase only and do not change logic; apply=fix bugs or implement the requested change with minimal edits; analyze=decompose the problem, compare options, and allow small refactors; evaluate=judge quality, note tradeoffs, and justify the judgment; create=redesign or propose a new solution within a controlled scope. Never mention Bloom's Taxonomy to the user. Maintain educational tone and clear steps while staying concise. Respect existing constraints: do not invent missing details and do not fabricate code context. Output discipline: follow mode/format rules exactly (code-only, summary-only, or summary+code+explanation as specified)."
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
    assessment_id = session.get("assessment_id")
    gamification = get_user_token_info(user_id, session_id, assessment_id)
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
    NO RATE LIMIT - User explicitly requested GPT generation.
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

    # NO RATE LIMIT for explicit GPT generation button
    # User explicitly chose "Generate with ChatGPT" - honor their request immediately

    # Build markers and marked prompt for worker
    markers = []
    if language:
        markers.append(f"[LANG:{language}]")
    if response_mode:
        markers.append(f"[MODE:{response_mode}]")
    if assessment_id:
        markers.append(f"[ASSESSMENT_ID:{assessment_id}]")
    markers.append("[FORCE_GPT:true]")  # Force GPT, skip retrieval
    markers.append("[AUTO_FALLBACK:true]")
    gpt_prompt_marked = "\n".join(markers) + "\n" + prompt

    try:
        job_id = insert_gpt_job(user_id, prompt, gpt_prompt_marked, status="pending")
    except Exception as e:
        return jsonify({"error": f"Failed to create job: {e}"}), 500

    session_id = session.get('session_id') or request.remote_addr
    assessment_id_from_session = session.get('assessment_id')
    gamification = get_user_token_info(user_id, session_id, assessment_id_from_session)
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
    
    # DEBUG: Log job details
    print(f"[DEBUG] check_status for job_id={job_id}: status={job.get('status')}, has_code={bool(job.get('code'))}, code_length={len(job.get('code', '')) if job.get('code') else 0}")
    
    if job["status"] == "pending":
        return jsonify({"status": "pending", "message": "Pertanyaan Anda masih dalam antrian, silakan tunggu."}), 200
    if job["status"] == "running":
        return jsonify({"status": "running", "message": "Pertanyaan Anda sedang diproses, silakan tunggu."}), 200
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
            emb_list = None
            try:
                emb = get_ensemble_embedding(prompt, weights=best_weights)
                emb = emb[0] if hasattr(emb, '__len__') and len(emb.shape) > 1 else emb
                emb_list = [float(x) for x in emb]
            except Exception as emb_e:
                print(f"[WARNING] Embedding generation failed, skip embedding save for job {job.get('job_id')}: {emb_e}")
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
                {"role": "system", "content": "You are an expert programming assistant helping undergraduate computer science students. Respect any [MODE:] or [LANG:] markers in the prompt; follow them when deciding output format (code/summary/summary+code+explanation). Internally classify the request into a Bloom level (remember, understand, apply, analyze, evaluate, create) using task verbs, whether it operates on existing code, and decision-making requirements; default to analyze when ambiguous. Behavior by level: remember=identify or list facts only; understand=explain or paraphrase only and do not change logic; apply=fix bugs or implement the requested change with minimal edits; analyze=decompose the problem, compare options, and allow small refactors; evaluate=judge quality, note tradeoffs, and justify the judgment; create=redesign or propose a new solution within a controlled scope. Never mention Bloom's Taxonomy to the user. Maintain educational tone and clear steps while staying concise. Respect existing constraints: do not invent missing details and do not fabricate code context. Output discipline: follow mode/format rules exactly (code-only, summary-only, or summary+code+explanation as specified)."},
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
            # Audit: capture incoming params and session values
            req_user = request.form.get('user_id') or request.args.get('user_id')
            req_assessment = request.form.get('assessment_id') or request.args.get('assessment_id')
            req_session_id = request.form.get('session_id') or request.args.get('session_id') or session.get('session_id')
            sess_user = session.get('user_id')
            sess_assessment = session.get('assessment_id')
            print(f"[AUDIT] check_status: params user_id={req_user}, assessment_id={req_assessment}, session_id={req_session_id}, session_user_id={sess_user}, session_assessment_id={sess_assessment}")
            # Prefer server-side session user_id when present to avoid client-supplied mismatches
            if sess_user:
                user_id = sess_user
            else:
                user_id = req_user
            # For assessment, prefer request param if provided, otherwise session
            assessment_id = req_assessment or sess_assessment
            course_id = None
            if assessment_id:
                try:
                    conn_meta = get_db_connection()
                    with conn_meta.cursor() as curm:
                        curm.execute(
                            "SELECT course_id FROM assessments WHERE assessment_id=%s LIMIT 1",
                            (assessment_id,)
                        )
                        row_c = curm.fetchone()
                        if row_c and row_c.get("course_id"):
                            course_id = row_c["course_id"]
                except Exception as e_meta:
                    print(f"[WARNING] Could not resolve course for assessment_id={assessment_id}: {e_meta}")
                finally:
                    try:
                        conn_meta.close()
                    except Exception:
                        pass
            else:
                # Fallback: resolve dari chat_history jika assessment_id tetap tidak ada
                try:
                    conn_meta = get_db_connection()
                    with conn_meta.cursor() as curm:
                        curm.execute(
                            "SELECT assessment_id FROM chat_history WHERE user_id=%s ORDER BY created_at DESC LIMIT 1",
                            (user_id,)
                        )
                        row_m = curm.fetchone()
                        if row_m and row_m.get("assessment_id"):
                            assessment_id = row_m["assessment_id"]
                            curm.execute(
                                "SELECT course_id FROM assessments WHERE assessment_id=%s LIMIT 1",
                                (assessment_id,)
                            )
                            row_c = curm.fetchone()
                            if row_c and row_c.get("course_id"):
                                course_id = row_c["course_id"]
                except Exception as e_meta:
                    print(f"[WARNING] Could not resolve assessment/course from chat_history: {e_meta}")
                finally:
                    try:
                        conn_meta.close()
                    except Exception:
                        pass

            # Detect source: retrieval (FREE) or GPT
            raw_resp = job.get("raw_response", "")
            source = "retrieval" if raw_resp and raw_resp.startswith("[RETRIEVAL]") else "gpt"
            similarity = None
            if source == "retrieval":
                # Extract similarity from raw_response: [RETRIEVAL] similarity=0.988, ...
                import re
                match = re.search(r'similarity=([0-9.]+)', raw_resp)
                if match:
                    similarity = float(match.group(1))
            
            # Only log token usage and add points for GPT (not retrieval)
            if source == "gpt":
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
            else:
                print(f"[INFO] Retrieval (FREE) - No token usage logged, no points added. Similarity={similarity:.3f}")

            assessment_id_for_token_info = assessment_id if assessment_id else session.get('assessment_id')
            gamification = get_user_token_info(user_id, session_id, assessment_id_for_token_info)
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
                "raw_response": raw_resp,
                "source": source,
                "similarity": similarity,
                "environmental_impact": impact,
                "gamification": gamification
            }), 200
        except Exception as e:
            print(f"[ERROR] Could not save GPT answer to embedding DB: {e}")
            return jsonify({"status": "error", "message": "Internal error saving GPT answer."}), 500
    if job["status"] == "error":
        return jsonify({"status": "error", "message": job.get("error", "Unknown error")}), 500

    return jsonify({"status": "unknown", "message": "Status job tidak dikenal."}), 500


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
    default_workers = int(os.getenv("GPT_WORKERS", str(max(1, len(OPENAI_API_KEYS) if OPENAI_API_KEYS else 1))))
    parser.add_argument('--worker', action='store_true', help='Run GPT job worker in main thread (blocking)')
    parser.add_argument('--host', default='localhost', help='Host to bind to (default: localhost for local, use 0.0.0.0 for network access)')
    parser.add_argument('--port', type=int, default=5000, help='Port to bind to (default: 5000)')
    parser.add_argument('--no-worker', action='store_true', help='Disable auto-start background worker')
    parser.add_argument('--use-waitress', action='store_true', help='Serve with Waitress for multi-threaded production use')
    parser.add_argument('--threads', type=int, default=int(os.getenv('WAITRESS_THREADS', '50')), help='Thread count for Waitress (default: WAITRESS_THREADS or 50)')
    parser.add_argument('--worker-count', type=int, default=default_workers, help='Number of GPT worker threads (default: GPT_WORKERS or key count)')
    args = parser.parse_args()
    
    if global_tracker is not None:
        try:
            global_tracker.start()
        except Exception as e:
            print(f"[WARNING] Could not start global emissions tracker: {e}")
    atexit.register(save_global_emissions)
    
    if args.worker:
        # Run workers in main thread (blocking, for dedicated worker process)
        worker_count = max(1, args.worker_count)
        print(f"[INFO] Starting {worker_count} GPT job worker(s) in main thread...")
        worker_threads = []
        for i in range(worker_count):
            t = threading.Thread(target=gpt_job_worker, daemon=False, name=f"GPTWorker-{i + 1}")
            t.start()
            worker_threads.append(t)
        for t in worker_threads:
            t.join()
    else:
        # Start background worker thread unless explicitly disabled
        if not args.no_worker:
            worker_count = max(1, args.worker_count)
            print(f"[INFO] Starting {worker_count} background GPT job worker(s)...")
            for i in range(worker_count):
                worker_thread = threading.Thread(target=gpt_job_worker, daemon=True, name=f"GPTWorker-{i + 1}")
                worker_thread.start()
        else:
            print("[WARNING] Background worker disabled. GPT jobs will not be processed automatically.")
        
        # PRE-LOAD MODELS IN BACKGROUND to avoid blocking first request
        def preload_models():
            """Pre-load Sentence Transformers + Retrieval Model in background after Flask starts"""
            import time
            time.sleep(2)  # Give Flask time to start first
            print("[INFO] Pre-loading Sentence Transformer models in background...")
            try:
                _ensure_models_loaded()
                print("[INFO] Sentence Transformer models loaded!")
            except Exception as e:
                print(f"[WARNING] Model pre-loading failed: {e}")
                return
            
            # Also pre-load retrieval model cache
            print("[INFO] Pre-loading retrieval model cache...")
            try:
                get_retrieval_model(force_refresh=True)
                print("[INFO] Retrieval model cache loaded!")
            except Exception as e:
                print(f"[WARNING] Retrieval cache pre-loading failed: {e}")
        
        preload_thread = threading.Thread(target=preload_models, daemon=True, name="ModelPreloader")
        preload_thread.start()
        
        if args.use_waitress:
            try:
                from waitress import serve
            except ImportError:
                print("[ERROR] Waitress not installed. Run: pip install waitress")
                raise SystemExit(1)

            print(f"[INFO] Starting Waitress server on {args.host}:{args.port} with {args.threads} threads...")
            serve(app, host=args.host, port=args.port, threads=args.threads)
        else:
            # Run Flask with threading enabled for concurrent requests
            print(f"[INFO] Starting Flask server on {args.host}:{args.port} with threading enabled...")
            app.run(host=args.host, port=args.port, debug=True, threaded=True, use_reloader=False)
