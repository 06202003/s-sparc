import os
import logging
from backend.core.db import get_db_connection

logging.basicConfig(level=logging.INFO)

class PointsAggregator:
    """
    PointsAggregator queries E-STRANGE database tables to compute student gamification points
    based on submission assessments (Originality, Efficiency, and Quality points).
    
    Formula:
    Total Point = SUM(suspicion.originality_point)
                + SUM(suspicion.efficiency_point)
                + SUM(code_clarity_suggestion.quality_point)
    WHERE submission.username = :username
    """
    
    _test_points_mock = {}
    _test_tokens_mock = {}

    @classmethod
    def set_mock_points(cls, username: str, points: float):
        """Set mock points for testing purposes."""
        cls._test_points_mock[username] = float(points)

    @classmethod
    def set_mock_token_usage(cls, username: str, tokens: int):
        """Set mock token usage for testing purposes."""
        cls._test_tokens_mock[username] = int(tokens)

    @classmethod
    def clear_mock_points(cls):
        """Clear all mock points and mock token usage."""
        cls._test_points_mock.clear()
        cls._test_tokens_mock.clear()

    @classmethod
    def get_user_token_usage(cls, username: str, assessment_id: str = None, course_id: str = None) -> int:
        """
        Calculates total token usage for a student in session_tokens.
        Used for enforcing token limit quota on courses where Game feature is OFF.
        """
        if not username:
            return 0

        if username in cls._test_tokens_mock:
            return cls._test_tokens_mock[username]

        conn = None
        try:
            conn = get_db_connection()
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT COALESCE(SUM(tokens_used), 0) AS total_used FROM session_tokens "
                    "WHERE user_id = %s AND (assessment_id = %s OR course_id = %s OR (%s IS NULL AND %s IS NULL))",
                    (username, assessment_id, course_id, assessment_id, course_id)
                )
                row = cur.fetchone()
                return int(row.get("total_used", 0) or 0) if row else 0
        except Exception as e:
            logging.error(f"Error querying token usage for student '{username}': {e}")
            return 0
        finally:
            if conn:
                try:
                    conn.close()
                except Exception:
                    pass


    @classmethod
    def is_game_active_for_assessment(cls, assessment_id: str = None, course_id: str = None) -> bool:

        """
        Checks if the game feature is active (game_course.is_active = 1) for the given assessment/course in E-STRANGE.
        If no assessment_id or course_id is specified, defaults to True (game policy active).
        """
        if not assessment_id and not course_id:
            return True

        conn = None
        try:
            conn = get_db_connection()
            with conn.cursor() as cur:
                if assessment_id:
                    cur.execute(
                        "SELECT gc.is_active FROM assessment a "
                        "JOIN game_course gc ON gc.course_id = a.course_id "
                        "WHERE a.assessment_id = %s LIMIT 1",
                        (assessment_id,)
                    )
                elif course_id:
                    cur.execute("SELECT is_active FROM game_course WHERE course_id = %s LIMIT 1", (course_id,))
                
                row = cur.fetchone()
                if row:
                    val = row.get("is_active")
                    return bool(val == 1 or str(val) == "1")
                # If no game_course entry found for this course, game is OFF
                return False
        except Exception as e:
            logging.error(f"Error checking game_course is_active status: {e}")
            return True
        finally:
            if conn:
                try:
                    conn.close()
                except Exception:
                    pass


    @classmethod
    def get_user_points(cls, username: str) -> float:
        """
        Calculates and returns total gamification points for a given student username.
        Checks mock dictionary first, then queries the E-STRANGE database tables.
        """
        if not username:
            return 0.0

        if username in cls._test_points_mock:
            return cls._test_points_mock[username]

        conn = None
        try:
            conn = get_db_connection()
            with conn.cursor() as cur:
                # Query E-STRANGE tables joining submission, suspicion, and code_clarity_suggestion
                # Handles direct sub.username or JOIN with user table sub.submitter_id
                query = """
                SELECT 
                    (COALESCE(SUM(s.originality_point), 0) + 
                     COALESCE(SUM(s.efficiency_point), 0) + 
                     COALESCE(SUM(c.quality_point), 0)) AS total_points
                FROM submission sub
                LEFT JOIN suspicion s ON sub.submission_id = s.submission_id
                LEFT JOIN code_clarity_suggestion c ON sub.submission_id = c.submission_id
                LEFT JOIN user u ON u.user_id = sub.submitter_id
                WHERE sub.submitter_id = %s OR u.username = %s OR u.user_id = %s
                """
                cur.execute(query, (username, username, username))
                row = cur.fetchone()
                
                if row and row.get('total_points') is not None and float(row['total_points']) > 0:
                    return float(row['total_points'])
                
                # Fallback: check user_points_assessment table if present in S-SPARC
                try:
                    cur.execute(
                        "SELECT COALESCE(SUM(total_points), 0) as total_pts FROM user_points_assessment WHERE user_id = %s",
                        (username,)
                    )
                    row_pts = cur.fetchone()
                    if row_pts and row_pts.get('total_pts') is not None and float(row_pts['total_pts']) > 0:
                        return float(row_pts['total_pts'])
                except Exception as e_pts:
                    logging.debug(f"user_points_assessment fallback skipped: {e_pts}")

                return 100.0
        except Exception as e:
            logging.error(f"Error querying E-STRANGE points for username '{username}': {e}")
            return 100.0
        finally:
            if conn:
                try:
                    conn.close()
                except Exception:
                    pass

    @classmethod
    def deduct_user_points(cls, username: str, amount: float) -> bool:
        """
        Deducts points from student after a successful Cloud inference call.
        """
        if not username or amount <= 0:
            return True

        if username in cls._test_points_mock:
            cls._test_points_mock[username] = max(0.0, cls._test_points_mock[username] - amount)
            return True

        conn = None
        try:
            conn = get_db_connection()
            with conn.cursor() as cur:
                # Log point deduction in session_tokens or cloud_point_deductions table
                try:
                    cur.execute(
                        "CREATE TABLE IF NOT EXISTS cloud_point_deductions ("
                        "id VARCHAR(64) PRIMARY KEY, "
                        "username VARCHAR(128) NOT NULL, "
                        "points_deducted DECIMAL(7,2) NOT NULL, "
                        "created_at DATETIME NOT NULL"
                        ")"
                    )
                    import uuid
                    cur.execute(
                        "INSERT INTO cloud_point_deductions (id, username, points_deducted, created_at) "
                        "VALUES (%s, %s, %s, NOW())",
                        (str(uuid.uuid4()), username, amount)
                    )
                    conn.commit()
                    logging.info(f"Deducted {amount} points for student '{username}' on Cloud inference success.")
                    return True
                except Exception as e_deduct:
                    logging.warning(f"Failed to log point deduction: {e_deduct}")
                    return False
        except Exception as e:
            logging.error(f"Error executing point deduction for user '{username}': {e}")
            return False
        finally:
            if conn:
                try:
                    conn.close()
                except Exception:
                    pass
