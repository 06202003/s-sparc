import datetime
from typing import Optional
from fastapi import APIRouter, Depends, HTTPException, Query, Request
from backend.core.db import get_db_connection
from backend.api.auth import get_current_user_id
from backend.services.gamification import get_user_token_info

router = APIRouter()

@router.get(
    "/courses",
    summary="List Enrolled Courses",
    description="Retrieves academic courses directly from E-STRANGE parent database for the authenticated user.",
    response_description="Array of enrolled courses with course_id, name, and description"
)
async def list_courses(user_id: str = Depends(get_current_user_id)):
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            # 1. Query strictly enrolled courses from E-STRANGE enrollment table
            cur.execute(
                """
                SELECT DISTINCT c.course_id, c.name, COALESCE(c.description, '') AS description
                FROM course c
                INNER JOIN enrollment e ON e.course_id = c.course_id
                WHERE (e.student_id = %s OR e.student_id IN (SELECT user_id FROM user WHERE username = %s OR user_id = %s))
                  AND c.is_active = 1
                ORDER BY c.name ASC
                """,
                (user_id, user_id, user_id)
            )
            rows = cur.fetchall() or []
        return {"courses": rows}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()

@router.get(
    "/assessments",
    summary="List Course Assessments",
    description="Retrieves active coding assessments from E-STRANGE parent database that are currently within the submission due window.",
    response_description="Array of assessments with assessment_id, course_id, name, submission_file_extension, and end_date"
)
async def list_assessments(course_id: Optional[str] = Query(None, description="Optional Course ID filter"), user_id: str = Depends(get_current_user_id)):
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            if course_id:
                cur.execute(
                    """
                    SELECT assessment_id, course_id, name, description, submission_file_extension, submission_close_time AS end_date
                    FROM assessment
                    WHERE course_id=%s
                      AND (submission_close_time > NOW() OR allow_late_submission = 1)
                      AND submission_open_time < NOW()
                    ORDER BY submission_close_time ASC, assessment_id ASC
                    """,
                    (course_id,)
                )
            else:
                cur.execute(
                    """
                    SELECT assessment_id, course_id, name, description, submission_file_extension, submission_close_time AS end_date
                    FROM assessment
                    WHERE (submission_close_time > NOW() OR allow_late_submission = 1)
                      AND submission_open_time < NOW()
                    ORDER BY course_id ASC, submission_close_time ASC, assessment_id ASC
                    """
                )
            rows = cur.fetchall() or []
        return {"assessments": rows}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()

@router.get(
    "/gamification",
    summary="Get Real-Time Gamification Metrics",
    description="Calculates student's current token consumption, dynamic threshold (1.10x peer average), active points, and sustainability tier.",
    response_description="Gamification status object including tokens used, threshold, remaining quota, points, and sustainability indicators"
)
async def get_gamification(request: Request, assessment_id: Optional[str] = Query(None, description="Assessment UUID"), user_id: str = Depends(get_current_user_id)):
    assessment_id = assessment_id or request.session.get('assessment_id')
    session_id = request.session.get("session_id") or request.client.host
    if "session_id" not in request.session and session_id:
        request.session["session_id"] = session_id
        
    try:
        gamification = get_user_token_info(user_id, session_id, assessment_id)
        return {"gamification": gamification}
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.get(
    "/token-usage-daily",
    summary="Daily Token Consumption for Current Week",
    description="Aggregates tokens used per day across the current ISO week for charting and trend analysis.",
    response_description="Array of daily token logs with date string and tokens_used count"
)
async def token_usage_daily(user_id: str = Depends(get_current_user_id)):
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
            
            # format day to string
            for row in rows:
                if 'day' in row and row['day']:
                    row['day'] = str(row['day'])
                    
        return {"daily_usage": rows}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()

@router.get(
    "/token-usage-breakdown",
    summary="Token Usage & Threshold Breakdown",
    description="Retrieves granular token metrics: total tokens consumed, overall dynamic threshold, remaining quota, breakdown by enrolled course, and breakdown by specific assessment.",
    response_description="Comprehensive token breakdown object including total, by_course, and by_assessment"
)
async def token_usage_breakdown(
    period: str = Query("week", description="Aggregation timeframe, e.g. 'week', 'month', 'all'"), 
    user_id: str = Depends(get_current_user_id)
):
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            now = datetime.datetime.now()
            # 1. Total used
            cur.execute(
                "SELECT COALESCE(SUM(tokens_used), 0) AS total_used FROM session_tokens WHERE user_id=%s",
                (user_id,)
            )
            row = cur.fetchone() or {"total_used": 0}
            total_used = int(row.get("total_used", 0) or 0)
            
            # 2. Total threshold & by_assessment
            cur.execute(
                """
                SELECT 
                    st.assessment_id,
                    COALESCE(a.name, a.code, st.assessment_id) AS assessment_name,
                    st.course_id,
                    COALESCE(c.name, c.code, st.course_id) AS course_name,
                    COALESCE(SUM(st.tokens_used), 0) AS total_used
                FROM session_tokens st
                LEFT JOIN assessments a ON st.assessment_id = a.assessment_id
                LEFT JOIN courses c ON st.course_id = c.course_id
                WHERE st.user_id = %s
                GROUP BY st.assessment_id, a.name, a.code, st.course_id, c.name, c.code
                ORDER BY total_used DESC
                """,
                (user_id,)
            )
            assessment_rows = cur.fetchall() or []
            
            by_assessment = []
            total_threshold = 0
            for r in assessment_rows:
                aid = r.get("assessment_id")
                avg_usage = 0.0
                if aid:
                    cur.execute(
                        "SELECT AVG(u.total_used) AS avg_usage FROM (SELECT user_id, COALESCE(SUM(tokens_used),0) AS total_used FROM session_tokens WHERE assessment_id=%s GROUP BY user_id) u",
                        (aid,)
                    )
                    avg_row = cur.fetchone()
                    if avg_row and avg_row.get("avg_usage"):
                        avg_usage = float(avg_row.get("avg_usage") or 0.0)
                
                threshold = max(0, int(1.10 * avg_usage)) if avg_usage > 0 else 0
                total_threshold += threshold
                used = int(r.get("total_used", 0) or 0)
                remaining = max(0, threshold - used)
                
                by_assessment.append({
                    "assessment_id": aid,
                    "assessment_name": r.get("assessment_name"),
                    "course_id": r.get("course_id"),
                    "course_name": r.get("course_name"),
                    "total_used": used,
                    "threshold": threshold,
                    "remaining": remaining
                })
            
            if total_threshold <= 0:
                total_threshold = 0
            
            remaining = max(0, total_threshold - total_used)
            
            # 3. by_course
            cur.execute(
                """
                SELECT 
                    st.course_id,
                    COALESCE(c.name, c.code, st.course_id) AS course_name,
                    COALESCE(SUM(st.tokens_used), 0) AS total_used,
                    COUNT(DISTINCT st.assessment_id) AS assessments_count
                FROM session_tokens st
                LEFT JOIN courses c ON st.course_id = c.course_id
                WHERE st.user_id = %s
                GROUP BY st.course_id, c.name, c.code
                ORDER BY total_used DESC
                """,
                (user_id,)
            )
            course_rows = cur.fetchall() or []
            by_course = []
            for cr in course_rows:
                cid = cr.get("course_id")
                c_used = int(cr.get("total_used", 0) or 0)
                c_threshold = sum(a["threshold"] for a in by_assessment if a.get("course_id") == cid)
                if c_threshold <= 0:
                    c_threshold = 0

                by_course.append({
                    "course_id": cid,
                    "course_name": cr.get("course_name"),
                    "assessments_count": int(cr.get("assessments_count", 0) or 0),
                    "total_used": c_used,
                    "threshold": c_threshold,
                    "remaining": max(0, c_threshold - c_used)
                })

            return {
                "period": period,
                "total": {
                    "total_used": total_used,
                    "threshold": total_threshold,
                    "remaining": remaining
                },
                "total_threshold": total_threshold,
                "by_course": by_course,
                "by_assessment": by_assessment
            }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()

@router.get(
    "/impact-summary",
    summary="Scientific Environmental Footprint Summary",
    description="Calculates academic AI consumption metrics: Electrical energy in Wh/kWh, carbon footprint in kg CO2e, and freshwater server cooling in mL, with daily timeseries.",
    response_description="Aggregate environmental metrics and daily breakdown"
)
async def impact_summary(
    days: int = Query(30, ge=1, le=365, description="Lookback window in days (default 30)"),
    scope: str = Query("all", description="Filtering scope: 'all', 'course', or 'assessment'"),
    course_id: Optional[str] = Query(None, description="Course UUID filter when scope='course'"),
    assessment_id: Optional[str] = Query(None, description="Assessment UUID filter when scope='assessment'"),
    user_id: str = Depends(get_current_user_id)
):
    where_clauses = ["user_id = %s", "created_at >= DATE_SUB(NOW(), INTERVAL %s DAY)"]
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
            cur.execute(
                f"""
                SELECT
                    COALESCE(SUM(energy_wh), 0) AS energy_wh,
                    COALESCE(SUM(energy_kwh), 0) AS energy_kwh,
                    COALESCE(SUM(carbon_kg), 0) AS carbon_kg,
                    COALESCE(SUM(water_ml), 0) AS water_ml
                FROM environmental_impact_logs
                WHERE {where_sql}
                """,
                tuple(params_totals),
            )
            totals = cur.fetchone() or {
                "energy_wh": 0,
                "energy_kwh": 0,
                "carbon_kg": 0,
                "water_ml": 0,
            }

            params_daily = list(params_totals)
            cur.execute(
                f"""
                SELECT
                    DATE(created_at) AS day,
                    COALESCE(SUM(energy_wh), 0) AS energy_wh,
                    COALESCE(SUM(energy_kwh), 0) AS energy_kwh,
                    COALESCE(SUM(carbon_kg), 0) AS carbon_kg,
                    COALESCE(SUM(water_ml), 0) AS water_ml
                FROM environmental_impact_logs
                WHERE {where_sql}
                GROUP BY DATE(created_at)
                ORDER BY DATE(created_at) ASC
                """,
                tuple(params_daily),
            )
            rows = cur.fetchall() or []
            for r in rows:
                if 'day' in r and r['day']:
                    r['day'] = str(r['day'])

        return {
            "range_days": days,
            "scope": scope,
            "course_id": course_id,
            "assessment_id": assessment_id,
            "totals": totals,
            "daily": rows,
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()

@router.get(
    "/assessment-leaderboard",
    summary="Assessment Student Leaderboard",
    description="Calculates gamified point rankings for all students enrolled in an assessment based on sustainability benchmarks.",
    response_description="Leaderboard ranking array and current student rank"
)
async def assessment_leaderboard(
    request: Request, 
    assessment_id: Optional[str] = Query(None, description="Assessment UUID (defaults to session)"), 
    user_id: str = Depends(get_current_user_id)
):
    assessment_id = assessment_id or request.session.get('assessment_id')
    if not assessment_id:
        raise HTTPException(status_code=400, detail="Missing assessment_id")

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT course_id, end_date FROM assessments WHERE assessment_id=%s", (assessment_id,))
            a_row = cur.fetchone() or {}
            course_id = a_row.get('course_id')
            expired = (datetime.datetime.now() > a_row.get('end_date')) if a_row.get('end_date') else False
            
            if not course_id:
                return {'assessment_id': assessment_id, 'leaderboard': [], 'user_rank': None}
            
            cur.execute(
                "SELECT st.user_id, SUM(st.tokens_used) as total_used "
                "FROM session_tokens st "
                "WHERE st.assessment_id=%s "
                "GROUP BY st.user_id",
                (assessment_id,)
            )
            all_tokens = {r['user_id']: int(r['total_used'] or 0) for r in cur.fetchall() or []}
            
            cur.execute("SELECT DISTINCT user_id FROM user_courses WHERE course_id=%s", (course_id,))
            enrolled = [r['user_id'] for r in cur.fetchall() or []]
            
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
            
            if not leaderboard_raw:
                return {'assessment_id': assessment_id, 'leaderboard': [], 'user_rank': None}
            
            avg_usage = sum(d['total_used'] for d in leaderboard_raw) / len(leaderboard_raw)
            threshold = 1.10 * avg_usage
            
            leaderboard = []
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
            
            leaderboard_raw.sort(key=lambda x: x['points'], reverse=True)
            rank = 0
            prev_pts = None
            for i, item in enumerate(leaderboard_raw, 1):
                if prev_pts is None or item['points'] < prev_pts:
                    rank = i
                item['rank'] = rank
                prev_pts = item['points']
                leaderboard.append(item)
            
            user_rank = next((item for item in leaderboard if str(item['user_id']) == str(user_id)), None)
            
            return {
                'assessment_id': assessment_id,
                'leaderboard': leaderboard,
                'user_rank': user_rank,
            }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()

@router.get(
    "/course-leaderboard",
    summary="Course Student Leaderboard",
    description="Aggregates cumulative sustainability points across all assessments for a specific course.",
    response_description="Course-wide leaderboard ranking and points"
)
async def course_leaderboard(
    request: Request, 
    course_id: Optional[str] = Query(None, description="Course UUID (defaults to session)"), 
    user_id: str = Depends(get_current_user_id)
):
    course_id = course_id or request.session.get('course_id')
    if not course_id:
        raise HTTPException(status_code=400, detail="Missing course_id")

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT DISTINCT user_id FROM user_courses WHERE course_id=%s", (course_id,))
            enrolled = [r['user_id'] for r in cur.fetchall() or []]
            
            leaderboard_raw = []
            for uid in enrolled:
                cur.execute(
                    "SELECT COALESCE(SUM(total_points), 0) AS total "
                    "FROM user_points_assessment "
                    "WHERE user_id=%s AND course_id=%s",
                    (uid, course_id)
                )
                pt_row = cur.fetchone()
                total_pts = int(pt_row.get('total') or 0)
                if total_pts > 0:
                    cur.execute("SELECT username FROM users WHERE user_id=%s", (uid,))
                    u_row = cur.fetchone() or {}
                    leaderboard_raw.append({
                        'user_id': uid,
                        'username': u_row.get('username') or 'Unknown',
                        'points': total_pts
                    })
            
            leaderboard_raw.sort(key=lambda x: x['points'], reverse=True)
            leaderboard = []
            rank = 0
            prev_pts = None
            for i, item in enumerate(leaderboard_raw, 1):
                if prev_pts is None or item['points'] < prev_pts:
                    rank = i
                item['rank'] = rank
                prev_pts = item['points']
                leaderboard.append(item)
            
            user_rank = next((item for item in leaderboard if str(item['user_id']) == str(user_id)), None)
            
            return {
                'course_id': course_id,
                'leaderboard': leaderboard,
                'user_rank': user_rank,
            }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()
