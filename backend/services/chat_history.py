import uuid
from backend.core.db import get_db_connection, resolve_user_uuid, resolve_assessment_id

def insert_chat_history(user_id: str, session_id: str, role: str, content: str, assessment_id: str = None):
    resolved_uid = resolve_user_uuid(user_id)
    resolved_aid = resolve_assessment_id(assessment_id)
    conn = get_db_connection()
    if conn is None:
        return
    try:
        with conn.cursor() as cur:
            cur.execute(
                "INSERT INTO chat_history (id, user_id, session_id, assessment_id, role, content) "
                "VALUES (%s, %s, %s, %s, %s, %s)",
                (str(uuid.uuid4()), resolved_uid, session_id, resolved_aid, role, content)
            )
        conn.commit()
    except Exception as e:
        # Fallback without assessment_id if FK failed
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "INSERT INTO chat_history (id, user_id, session_id, assessment_id, role, content) "
                    "VALUES (%s, %s, %s, NULL, %s, %s)",
                    (str(uuid.uuid4()), resolved_uid, session_id, role, content)
                )
            conn.commit()
        except Exception as e2:
            print(f"[ERROR] insert_chat_history: {e2}")
    finally:
        try:
            conn.close()
        except Exception:
            pass

def get_chat_history(user_id: str, session_id: str, assessment_id: str = None, limit: int = 10):
    resolved_uid = resolve_user_uuid(user_id)
    resolved_aid = resolve_assessment_id(assessment_id)
    conn = get_db_connection()
    if conn is None:
        return []
    try:
        with conn.cursor() as cur:
            if resolved_aid:
                cur.execute(
                    "SELECT role, content FROM chat_history "
                    "WHERE (user_id=%s OR user_id=%s) AND session_id=%s AND assessment_id=%s "
                    "ORDER BY created_at DESC LIMIT %s",
                    (resolved_uid, user_id, session_id, resolved_aid, limit)
                )
            else:
                cur.execute(
                    "SELECT role, content FROM chat_history "
                    "WHERE (user_id=%s OR user_id=%s) AND session_id=%s "
                    "ORDER BY created_at DESC LIMIT %s",
                    (resolved_uid, user_id, session_id, limit)
                )
            rows = cur.fetchall() or []
            # We ordered by DESC to get the latest, now we reverse to chronological
            rows.reverse()
            return rows
    except Exception as e:
        print(f"[ERROR] get_chat_history: {e}")
        return []
    finally:
        try:
            conn.close()
        except Exception:
            pass
