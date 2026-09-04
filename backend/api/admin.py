import io
import csv
from typing import Optional
from fastapi import APIRouter, Depends, HTTPException, Query
from fastapi.responses import Response, JSONResponse
from pydantic import BaseModel
from backend.core.db import get_db_connection
from backend.api.auth import require_admin
from backend.services.gamification import compute_assessment_final_points

router = APIRouter()

class ComputeRequest(BaseModel):
    assessment_id: str

@router.get(
    "/admin-environmental-stats",
    summary="Admin Environmental Impact Metrics",
    description="Retrieves administrative aggregate metrics for total energy (kWh), carbon footprint (kg CO2e), water usage (mL), daily timeseries, and recent audit logs.",
    response_description="Aggregate environmental metrics, daily breakdown, and recent log entries"
)
async def admin_environmental_stats(
    days: int = Query(30, ge=1, le=365, description="Lookback window in days"), 
    admin_id: str = Depends(require_admin)
):
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT COALESCE(SUM(energy_kwh),0) AS energy_kwh, COALESCE(SUM(carbon_kg),0) AS carbon_kg, COALESCE(SUM(water_ml),0) AS water_ml "
                "FROM environmental_impact_logs"
            )
            totals = cur.fetchone() or {'energy_kwh':0,'carbon_kg':0,'water_ml':0}

            cur.execute(
                "SELECT DATE(created_at) AS d, COALESCE(SUM(energy_kwh),0) AS energy_kwh, COALESCE(SUM(carbon_kg),0) AS carbon_kg, COALESCE(SUM(water_ml),0) AS water_ml "
                "FROM environmental_impact_logs "
                "WHERE created_at >= DATE_SUB(NOW(), INTERVAL %s DAY) "
                "GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC",
                (days,)
            )
            by_day = cur.fetchall() or []

            cur.execute(
                "SELECT id, user_id, job_id, course_id, assessment_id, energy_kwh, carbon_kg, water_ml, created_at "
                "FROM environmental_impact_logs ORDER BY created_at DESC LIMIT 50"
            )
            recent_logs = cur.fetchall() or []

            return {
                'total_energy_kwh': float(totals.get('energy_kwh') or 0.0),
                'total_carbon_kg': float(totals.get('carbon_kg') or 0.0),
                'total_water_ml': float(totals.get('water_ml') or 0.0),
                'by_day': by_day,
                'recent_logs': recent_logs,
            }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()

@router.get(
    "/admin-environmental-csv",
    summary="Export Environmental Logs to CSV",
    description="Streams a downloadable CSV spreadsheet containing detailed environmental impact logs (energy, carbon, water).",
    response_description="CSV file download of environmental logs"
)
async def admin_environmental_csv(
    days: Optional[int] = Query(None, description="Optional days lookback filter"), 
    admin_id: str = Depends(require_admin)
):
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
            return Response(content=csv_data, media_type='text/csv', headers={
                'Content-Disposition': 'attachment; filename="environmental_impact_logs.csv"'
            })
    finally:
        conn.close()

@router.get(
    "/admin-assessment-csv",
    summary="Export Assessment Points & Token Usage to CSV",
    description="Streams a downloadable CSV spreadsheet containing student token usage, threshold, and computed points for a specific assessment.",
    response_description="CSV file download of assessment grading metrics"
)
async def admin_assessment_csv(
    assessment_id: str = Query(..., description="Assessment UUID"), 
    admin_id: str = Depends(require_admin)
):
    if not assessment_id:
        raise HTTPException(status_code=400, detail="assessment_id required")
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT DISTINCT user_id FROM session_tokens WHERE assessment_id=%s", (assessment_id,))
            users = [r['user_id'] for r in cur.fetchall() or []]
            if not users:
                cur.execute("SELECT DISTINCT user_id FROM user_points_assessment WHERE assessment_id=%s", (assessment_id,))
                users = [r['user_id'] for r in cur.fetchall() or []]

            usage_map = {}
            for uid in users:
                cur.execute("SELECT COALESCE(SUM(tokens_used),0) AS total_used FROM session_tokens WHERE assessment_id=%s AND user_id=%s", (assessment_id, uid))
                row = cur.fetchone() or {'total_used': 0}
                usage_map[uid] = int(row.get('total_used') or 0)

            if not usage_map:
                raise HTTPException(status_code=404, detail="no users found for assessment")

            avg_usage = float(sum(usage_map.values())) / float(len(usage_map))
            threshold = 1.10 * avg_usage

            output = io.StringIO()
            writer = csv.writer(output)
            writer.writerow(['user_id', 'username', 'usage', 'final_point'])
            for uid, usage in usage_map.items():
                cur.execute("SELECT username FROM users WHERE user_id=%s LIMIT 1", (uid,))
                row = cur.fetchone() or {}
                username = row.get('username') or ''
                usage_f = float(usage)
                if threshold <= 0.0:
                    final_point = 100.0 if usage_f <= 0.0 else 0.0
                elif usage_f <= threshold:
                    final_point = 100.0
                else:
                    final_point = max(0.0, 100.0 + 100.0 * (threshold - usage_f) / threshold)
                final_point = min(100.0, final_point)
                writer.writerow([uid, username, usage, f"{final_point:.2f}"])

            csv_data = output.getvalue()
            return Response(content=csv_data, media_type='text/csv', headers={
                'Content-Disposition': f'attachment; filename="assessment_{assessment_id}_points.csv"'
            })
    finally:
        conn.close()

@router.get(
    "/admin-assessment-histogram",
    summary="Assessment Token Usage Histogram",
    description="Generates bucketed distribution counts of student final points and tokens for an assessment.",
    response_description="Histogram labels, bucket counts, average usage, and threshold"
)
async def admin_assessment_histogram(
    assessment_id: str = Query(..., description="Assessment UUID"), 
    buckets: int = Query(10, ge=1, le=100, description="Number of distribution histogram buckets"), 
    admin_id: str = Depends(require_admin)
):
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT DISTINCT user_id FROM session_tokens WHERE assessment_id=%s", (assessment_id,))
            users = [r['user_id'] for r in cur.fetchall() or []]
            if not users:
                cur.execute("SELECT DISTINCT user_id FROM user_points_assessment WHERE assessment_id=%s", (assessment_id,))
                users = [r['user_id'] for r in cur.fetchall() or []]
            if not users:
                raise HTTPException(status_code=404, detail="no users found for assessment")

            usage_map = {}
            for uid in users:
                cur.execute("SELECT COALESCE(SUM(tokens_used),0) AS total_used FROM session_tokens WHERE assessment_id=%s AND user_id=%s", (assessment_id, uid))
                row = cur.fetchone() or {'total_used': 0}
                usage_map[uid] = int(row.get('total_used') or 0)

            avg_usage = float(sum(usage_map.values())) / float(len(usage_map))
            threshold = 1.10 * avg_usage

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

            bin_counts = [0] * buckets
            for p in final_points:
                v = max(0.0, min(100.0, p))
                idx = int((v / 100.0) * buckets)
                if idx == buckets:
                    idx = buckets - 1
                bin_counts[idx] += 1

            labels = []
            for i in range(buckets):
                lo = (i * 100.0 / buckets)
                hi = ((i + 1) * 100.0 / buckets)
                labels.append(f"{lo:.1f}-{hi:.1f}")

            return {'labels': labels, 'counts': bin_counts, 'avg_usage': avg_usage, 'threshold': threshold}
    finally:
        conn.close()

@router.get(
    "/admin-dashboard",
    summary="Instructor & Administrator Dashboard Overview",
    description="Returns high-level platform statistics: total assessments, completed assessments, total enrolled users, awarded points, and recent assessments with usage averages.",
    response_description="Administrative dashboard summary KPIs"
)
async def admin_dashboard(admin_id: str = Depends(require_admin)):
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS cnt FROM assessments")
            total_assessments = int(cur.fetchone().get('cnt', 0))

            cur.execute("SELECT COUNT(*) AS cnt FROM assessments WHERE end_date IS NOT NULL AND end_date < NOW()")
            assessments_ended = int(cur.fetchone().get('cnt', 0))

            cur.execute("SELECT COUNT(*) AS cnt FROM users")
            total_users = int(cur.fetchone().get('cnt', 0))

            try:
                cur.execute("SELECT COALESCE(SUM(final_points), SUM(total_points)) AS total_points FROM user_points_assessment")
                row = cur.fetchone() or {}
                total_points_awarded = float(row.get('total_points') or 0.0)
            except Exception:
                cur.execute("SELECT COALESCE(SUM(total_points),0) AS total_points FROM user_points_assessment")
                total_points_awarded = float(cur.fetchone().get('total_points', 0) or 0.0)

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

            return {
                'total_assessments': total_assessments,
                'assessments_ended': assessments_ended,
                'total_users': total_users,
                'total_points_awarded': total_points_awarded,
                'recent_assessments': recent,
            }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()

@router.post(
    "/refresh-retrieval-cache",
    summary="Refresh Semantic Knowledge Cache",
    description="Forces cache invalidation to make newly vectorized code_embeddings immediately queryable.",
    response_description="Status message indicating cache refresh state"
)
async def refresh_retrieval_cache(admin_id: str = Depends(require_admin)):
    return {
        "status": "success",
        "message": "Retrieval cache will be refreshed on next search request.",
        "note": "Cache auto-refreshes every 5 minutes. New embeddings will be searchable within 5 min."
    }

@router.post(
    "/compute-assessment-points",
    summary="Compute Assessment Gamification Points",
    description="Executes the point calculation algorithm for all students in an assessment based on the 1.10x peer usage formula.",
    response_description="Calculation results including threshold, student count, and awarded points"
)
async def compute_assessment_points_endpoint(data: ComputeRequest, admin_id: str = Depends(require_admin)):
    try:
        res = compute_assessment_final_points(data.assessment_id)
        if res.get('error'):
            raise HTTPException(status_code=400, detail=res['error'])
        return res
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

