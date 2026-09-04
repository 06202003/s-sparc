import os
import pymysql
from pymysql.cursors import DictCursor
from dotenv import load_dotenv

# Load env variables
load_dotenv()

# In-memory fallback cache for user API keys when DB is offline
_in_memory_user_keys = {}
_last_db_connect_failure_time = 0
_DB_FAILURE_COOLDOWN = 5.0

def get_db_connection():
    """
    Establish a connection to the MySQL database using environment variables.
    Returns a PyMySQL connection object configured to return dicts or None on failure.
    Includes a 5-second circuit breaker to prevent blocking when DB is offline.
    """
    global _last_db_connect_failure_time
    import time
    now = time.time()
    if now - _last_db_connect_failure_time < _DB_FAILURE_COOLDOWN:
        return None

    host = os.getenv("MYSQL_HOST", "localhost")
    port = int(os.getenv("MYSQL_PORT", 3306))
    user = os.getenv("MYSQL_USER", "root")
    password = os.getenv("MYSQL_PASSWORD", "")
    db = os.getenv("MYSQL_DB", "s_sparc_db")
    
    try:
        connection = pymysql.connect(
            host=host,
            port=port,
            user=user,
            password=password,
            db=db,
            charset='utf8mb4',
            cursorclass=DictCursor,
            autocommit=True,
            connect_timeout=1
        )
        return connection
    except Exception as e:
        _last_db_connect_failure_time = time.time()
        import logging
        logging.warning(f"get_db_connection failed: {e}")
        return None

def resolve_user_uuid(user_identifier: str) -> str:
    """Resolve E-STRANGE user_id or username to S-SPARC users.user_id UUID."""
    if not user_identifier:
        return "55081e81-0a64-11f1-b762-3e0936ad7efb"
    conn = get_db_connection()
    if conn is None:
        return str(user_identifier)
    try:
        with conn.cursor() as cur:
            # 1. Direct match in users.user_id
            cur.execute("SELECT user_id FROM users WHERE user_id=%s LIMIT 1", (str(user_identifier),))
            row = cur.fetchone()
            if row:
                return str(row['user_id'])
            # 2. Match by username in users
            cur.execute("SELECT user_id FROM users WHERE username=%s LIMIT 1", (str(user_identifier),))
            row = cur.fetchone()
            if row:
                return str(row['user_id'])
            # 3. Match by E-STRANGE user.user_id or user.username
            cur.execute("SELECT user_id, username, email FROM user WHERE user_id=%s OR username=%s LIMIT 1", (str(user_identifier), str(user_identifier)))
            row_e = cur.fetchone()
            if row_e:
                cur.execute("SELECT user_id FROM users WHERE username=%s LIMIT 1", (row_e['username'],))
                row_u = cur.fetchone()
                if row_u:
                    return str(row_u['user_id'])
                # Auto-sync user into users table if missing to prevent FK error
                import uuid
                new_uid = str(uuid.uuid4())
                cur.execute(
                    "INSERT INTO users (user_id, username, email, password_hash) VALUES (%s, %s, %s, %s)",
                    (new_uid, row_e['username'], row_e.get('email') or f"{row_e['username']}@maranatha.ac.id", "synced_sso_account")
                )
                conn.commit()
                return new_uid
            
            # 4. Return the provided user identifier if not matched
            return str(user_identifier)
    except Exception as e:
        import logging
        logging.warning(f"resolve_user_uuid error: {e}")
    finally:
        try:
            conn.close()
        except Exception:
            pass
    return str(user_identifier)

def resolve_assessment_id(assessment_identifier: str) -> str:
    """Ensure assessment_id exists in assessments table or returns None if invalid."""
    if not assessment_identifier:
        return None
    conn = get_db_connection()
    if conn is None:
        return str(assessment_identifier)
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT assessment_id FROM assessments WHERE assessment_id=%s LIMIT 1", (str(assessment_identifier),))
            row = cur.fetchone()
            if row:
                return str(row['assessment_id'])
            # If numeric or non-UUID, check if exists in E-STRANGE assessment table
            cur.execute("SELECT assessment_id, title, course_id FROM assessment WHERE assessment_id=%s LIMIT 1", (str(assessment_identifier),))
            row_e = cur.fetchone()
            if row_e:
                # Synchronize to S-SPARC assessments table
                new_asmt_uuid = str(assessment_identifier)
                cur.execute(
                    "INSERT INTO assessments (assessment_id, course_id, title, token_threshold) VALUES (%s, %s, %s, %s) ON DUPLICATE KEY UPDATE title=VALUES(title)",
                    (new_asmt_uuid, str(row_e['course_id']), row_e.get('title') or "Assessment", 2500)
                )
                conn.commit()
                return new_asmt_uuid
    except Exception as e:
        import logging
        logging.warning(f"resolve_assessment_id error: {e}")
    finally:
        try:
            conn.close()
        except Exception:
            pass
    return str(assessment_identifier)

# In-memory daily query count fallback: (user_id, provider) -> int
_in_memory_daily_queries = {}

def ensure_user_api_keys_table():
    """Ensure that the user_api_keys table exists in the database."""
    conn = get_db_connection()
    if conn is None:
        return
    try:
        with conn.cursor() as cur:
            cur.execute("""
                CREATE TABLE IF NOT EXISTS user_api_keys (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id VARCHAR(64) NOT NULL,
                    api_key VARCHAR(255) NOT NULL,
                    provider VARCHAR(50) DEFAULT 'gemini',
                    is_active TINYINT(1) DEFAULT 1,
                    terms_accepted TINYINT(1) DEFAULT 1,
                    terms_accepted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_user_provider (user_id, provider),
                    INDEX idx_user_id (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            """)
            conn.commit()
    except Exception as e:
        import logging
        logging.warning(f"ensure_user_api_keys_table error: {e}")
    finally:
        try:
            conn.close()
        except Exception:
            pass

def get_user_api_key(user_identifier: str, provider: str = "gemini") -> str:
    """Retrieve active user API key by user_id or resolved user UUID."""
    if not user_identifier:
        return None
    
    key_tuple = (str(user_identifier), provider)
    user_id = resolve_user_uuid(user_identifier)
    
    conn = get_db_connection()
    if conn is not None:
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT api_key FROM user_api_keys WHERE user_id=%s AND provider=%s AND is_active=1 LIMIT 1",
                    (user_id, provider)
                )
                row = cur.fetchone()
                if row and row.get('api_key'):
                    k = str(row['api_key']).strip()
                    _in_memory_user_keys[key_tuple] = k
                    _in_memory_user_keys[(user_id, provider)] = k
                    return k
                
                if str(user_identifier) != user_id:
                    cur.execute(
                        "SELECT api_key FROM user_api_keys WHERE user_id=%s AND provider=%s AND is_active=1 LIMIT 1",
                        (str(user_identifier), provider)
                    )
                    row2 = cur.fetchone()
                    if row2 and row2.get('api_key'):
                        k2 = str(row2['api_key']).strip()
                        _in_memory_user_keys[key_tuple] = k2
                        _in_memory_user_keys[(user_id, provider)] = k2
                        return k2
                
                # If DB has no active key, clear in-memory cache for this user
                _in_memory_user_keys.pop(key_tuple, None)
                _in_memory_user_keys.pop((user_id, provider), None)
                return None
        except Exception as e:
            import logging
            logging.error(f"get_user_api_key DB query error: {e}")
    
    # Fallback to in-memory dict if DB is unreachable
    return _in_memory_user_keys.get(key_tuple) or _in_memory_user_keys.get((user_id, provider))

def set_user_api_key(user_identifier: str, api_key: str, provider: str = "gemini", terms_accepted: bool = True) -> bool:
    """Save or update user API key with terms acceptance."""
    if not user_identifier or not api_key:
        return False
    
    clean_key = str(api_key).strip()
    user_id = resolve_user_uuid(user_identifier)
    
    # Store in memory fallback as well
    _in_memory_user_keys[(str(user_identifier), provider)] = clean_key
    _in_memory_user_keys[(user_id, provider)] = clean_key
    
    ensure_user_api_keys_table()
    conn = get_db_connection()
    if conn is None:
        return True
        
    try:
        with conn.cursor() as cur:
            cur.execute("""
                INSERT INTO user_api_keys (user_id, api_key, provider, is_active, terms_accepted, terms_accepted_at, updated_at)
                VALUES (%s, %s, %s, 1, %s, NOW(), NOW())
                ON DUPLICATE KEY UPDATE api_key=VALUES(api_key), is_active=1, terms_accepted=VALUES(terms_accepted), terms_accepted_at=NOW(), updated_at=NOW()
            """, (user_id, clean_key, provider, 1 if terms_accepted else 0))
            conn.commit()
            return True
    except Exception as e:
        import logging
        logging.error(f"set_user_api_key error: {e}")
        return True # Fallback in memory was saved successfully
    finally:
        try:
            conn.close()
        except Exception:
            pass

def delete_user_api_key(user_identifier: str, provider: str = "gemini") -> bool:
    """Deactivate or remove user API key."""
    if not user_identifier:
        return False
    user_id = resolve_user_uuid(user_identifier)
    
    _in_memory_user_keys.pop((str(user_identifier), provider), None)
    _in_memory_user_keys.pop((user_id, provider), None)
    
    conn = get_db_connection()
    if conn is None:
        return True
        
    try:
        with conn.cursor() as cur:
            cur.execute(
                "DELETE FROM user_api_keys WHERE (user_id=%s OR user_id=%s) AND provider=%s",
                (user_id, str(user_identifier), provider)
            )
            conn.commit()
            return True
    except Exception as e:
        import logging
        logging.error(f"delete_user_api_key error: {e}")
        return True
    finally:
        try:
            conn.close()
        except Exception:
            pass

def increment_user_query_count(user_identifier: str, provider: str = "gemini"):
    """Increment user daily query count for memory tracking."""
    if not user_identifier:
        return
    key1 = (str(user_identifier), provider)
    _in_memory_daily_queries[key1] = _in_memory_daily_queries.get(key1, 0) + 1
    user_id = resolve_user_uuid(user_identifier)
    key2 = (user_id, provider)
    _in_memory_daily_queries[key2] = _in_memory_daily_queries.get(key2, 0) + 1

def get_user_query_quota(user_identifier: str, provider: str = "gemini") -> dict:
    """
    Calculate real-time query quota usage for the user's registered Gemini API key.
    Based on Google Gemini Free Tier daily allocation of 1,500 Requests/Day (RPD) & 15 RPM.
    """
    user_id = resolve_user_uuid(user_identifier) if user_identifier else None
    key = get_user_api_key(user_identifier, provider=provider)
    has_key = bool(key)
    masked = (key[:6] + "..." + key[-4:]) if (key and len(key) > 10) else None
    daily_limit = int(os.getenv("GEMINI_DAILY_QUERY_LIMIT", "1500"))
    
    daily_used = 0
    conn = get_db_connection()
    if conn is not None:
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT COUNT(*) AS cnt FROM gpt_jobs WHERE (user_id=%s OR user_id=%s) AND DATE(created_at) = CURDATE()",
                    (user_id, str(user_identifier))
                )
                row = cur.fetchone()
                if row:
                    daily_used = int(row.get('cnt') or 0)
        except Exception:
            pass
        finally:
            try:
                conn.close()
            except Exception:
                pass
    else:
        daily_used = _in_memory_daily_queries.get((str(user_identifier), provider), 0) or _in_memory_daily_queries.get((user_id, provider), 0)
        
    daily_remaining = max(0, daily_limit - daily_used)
    
    return {
        "has_key": has_key,
        "provider": provider,
        "masked_key": masked,
        "daily_limit": daily_limit,
        "daily_used": daily_used,
        "daily_remaining": daily_remaining,
        "rate_limit_rpm": 15,
        "cooldown_seconds": 60,
        "tier_label": "Google Gemini Free Tier (1,500 RPD / 15 RPM)",
        "terms_accepted": True
    }
