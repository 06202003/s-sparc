import uuid
import logging
import datetime
from backend.core.db import get_db_connection

def compute_assessment_final_points(assessment_id: str):
    """Compute and persist final gamification points for all users in an assessment."""
    if not assessment_id:
        return {"error": "assessment_id required"}
        
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
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

            users = []
            try:
                cur.execute("SELECT user_id FROM user_courses WHERE course_id=%s", (course_id,))
                rows = cur.fetchall() or []
                users = [r['user_id'] for r in rows]
            except Exception:
                users = []

            if not users:
                cur.execute("SELECT DISTINCT user_id FROM session_tokens WHERE assessment_id=%s", (assessment_id,))
                rows = cur.fetchall() or []
                users = [r['user_id'] for r in rows]

            if not users:
                return {"error": "no users found for assessment"}

            usage_map = {}
            for uid in users:
                cur.execute(
                    "SELECT COALESCE(SUM(tokens_used),0) AS total_used FROM session_tokens WHERE assessment_id=%s AND user_id=%s",
                    (assessment_id, uid),
                )
                row = cur.fetchone() or {"total_used": 0}
                usage_map[uid] = int(row.get('total_used', 0) or 0)

            avg_usage = float(sum(usage_map.values())) / float(len(usage_map)) if usage_map else 0.0
            threshold = 1.10 * avg_usage

            try:
                cur.execute("ALTER TABLE user_points_assessment ADD COLUMN final_points DECIMAL(7,2) NULL")
                conn.commit()
            except Exception:
                pass

            results = []
            for uid, usage in usage_map.items():
                usage_f = float(usage)
                if threshold <= 0.0:
                    final_point = 100.0 if usage_f <= 0.0 else 0.0
                elif usage_f <= threshold:
                    final_point = 100.0
                else:
                    final_point = max(0.0, 100.0 + 100.0 * (threshold - usage_f) / threshold)
                    
                final_point_rounded = round(final_point, 2)
                total_points_int = int(round(final_point_rounded))

                uid_uuid = str(uuid.uuid4())
                try:
                    cur.execute(
                        "INSERT INTO user_points_assessment (id, user_id, assessment_id, course_id, total_points, final_points, updated_at) "
                        "VALUES (%s, %s, %s, %s, %s, %s, NOW()) "
                        "ON DUPLICATE KEY UPDATE total_points=VALUES(total_points), final_points=VALUES(final_points), updated_at=NOW()",
                        (uid_uuid, uid, assessment_id, course_id, total_points_int, final_point_rounded),
                    )
                except Exception as e:
                    try:
                        cur.execute(
                            "INSERT INTO user_points_assessment (id, user_id, assessment_id, course_id, total_points, updated_at) "
                            "VALUES (%s, %s, %s, %s, %s, NOW()) "
                            "ON DUPLICATE KEY UPDATE total_points=VALUES(total_points), updated_at=NOW()",
                            (uid_uuid, uid, assessment_id, course_id, total_points_int),
                        )
                    except Exception as e2:
                        results.append({"user_id": uid, "error": str(e2)})
                        continue

                results.append({"user_id": uid, "usage": usage, "final_point": final_point_rounded})

            conn.commit()
            return {"status": "ok", "threshold": threshold, "avg_usage": avg_usage, "results": results}
    finally:
        conn.close()


def get_user_token_info(user_id, session_id, assessment_id=None):
    if not user_id or not session_id:
        raise ValueError("user_id and session_id are required for token info")
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            now = datetime.datetime.now()
            threshold = 0
            end_date = None
            avg_usage = 0.0
            
            if assessment_id:
                try:
                    cur.execute(
                        "SELECT end_date FROM assessments WHERE assessment_id=%s",
                        (assessment_id,)
                    )
                    end_row = cur.fetchone()
                    if end_row and end_row.get('end_date'):
                        end_date = end_row.get('end_date')
                except Exception as e:
                    logging.error(f"Failed to fetch end_date: {e}")
                
                try:
                    cur.execute(
                        "SELECT AVG(u.total_used) AS avg_usage FROM (SELECT user_id, COALESCE(SUM(tokens_used),0) AS total_used FROM session_tokens WHERE assessment_id=%s GROUP BY user_id) u",
                        (assessment_id,)
                    )
                    avg_row = cur.fetchone()
                    if avg_row and avg_row.get('avg_usage'):
                        avg_usage = float(avg_row.get('avg_usage') or 0.0)
                        if avg_usage > 0:
                            threshold = max(0, int(1.10 * avg_usage))
                except Exception as e:
                    logging.error(f"Failed to compute dynamic threshold: {e}")

            
            from backend.core.db import resolve_user_uuid
            resolved_uid = resolve_user_uuid(user_id)

            if assessment_id:
                cur.execute(
                    "SELECT COALESCE(SUM(tokens_used), 0) AS used_total "
                    "FROM session_tokens WHERE (user_id=%s OR user_id=%s) AND (assessment_id=%s OR assessment_id IS NULL)",
                    (user_id, resolved_uid, assessment_id),
                )
            else:
                cur.execute(
                    "SELECT COALESCE(SUM(tokens_used), 0) AS used_total "
                    "FROM session_tokens WHERE (user_id=%s OR user_id=%s)",
                    (user_id, resolved_uid),
                )
            row = cur.fetchone() or {"used_total": 0}
            raw_used = row.get("used_total", 0) or 0
            used_total_f = float(raw_used)

            capped_used = int(used_total_f)
            remaining_tokens = max(0, int(max(0.0, float(threshold) - used_total_f)))

            if threshold <= 0:
                final_point = 100.0 if used_total_f <= 0.0 else 0.0
            else:
                if used_total_f <= float(threshold):
                    final_point = 100.0
                else:
                    final_point = max(0.0, 100.0 + 100.0 * (float(threshold) - used_total_f) / float(threshold))
                final_point = min(100.0, final_point)

            from backend.services.points_aggregator import PointsAggregator
            user_points = PointsAggregator.get_user_points(user_id)
            remaining_queries = max(0, int(user_points // 10))

            result = {
                "total_tokens": threshold,
                "token_threshold": threshold,
                "gpt_tokens_used": capped_used,
                "used_tokens": capped_used,
                "remaining_tokens": remaining_tokens,
                "current_points": round(final_point, 2),
                "points": round(final_point, 2),
                "gamification_points": round(user_points, 1),
                "remaining_queries": remaining_queries,
                "used_tokens_raw": int(used_total_f),
            }
            if end_date:
                result["assessment_end_date"] = str(end_date)
                result["end_date"] = str(end_date)
            return result
    finally:
        conn.close()

def log_token_usage(user_id, session_id, tokens_used, assessment_id=None, course_id=None):
    if not user_id or not session_id:
        raise ValueError("user_id and session_id are required for token usage log")
    from backend.core.db import resolve_user_uuid, resolve_assessment_id
    resolved_uid = resolve_user_uuid(user_id)
    resolved_aid = resolve_assessment_id(assessment_id)
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            now = datetime.datetime.now()
            try:
                cur.execute(
                    "INSERT INTO session_tokens (id, user_id, session_id, assessment_id, course_id, tokens_used, used_at) VALUES (%s, %s, %s, %s, %s, %s, %s)",
                    (str(uuid.uuid4()), resolved_uid, session_id, resolved_aid, course_id, tokens_used, now)
                )
            except Exception as e1:
                try:
                    cur.execute(
                        "INSERT INTO session_tokens (id, user_id, session_id, tokens_used, used_at) VALUES (%s, %s, %s, %s, %s)",
                        (str(uuid.uuid4()), resolved_uid, session_id, tokens_used, now)
                    )
                except Exception as e2:
                    logging.warning(f"Session tokens insert fallback: {e2}")
        conn.commit()
    finally:
        conn.close()
