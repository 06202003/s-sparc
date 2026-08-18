#!/usr/bin/env python3
"""Utility script: compute final points for all ended assessments.

Run from repository root:
  python tools/compute_all_assessments.py [--force]

This script connects to the same database as the application (reads env vars),
finds assessments with end_date < NOW(), and computes final points using the
same formula as the application. It will skip assessments that already have
final_points computed unless --force is provided.
"""
import os
import sys
import argparse
import datetime
import uuid
import pymysql


def get_db_connection():
    return pymysql.connect(
        host=os.getenv('MYSQL_HOST', 'localhost'),
        user=os.getenv('MYSQL_USER', 'root'),
        password=os.getenv('MYSQL_PASSWORD', ''),
        database=os.getenv('MYSQL_DB', 'db_semantic'),
        charset='utf8mb4',
        cursorclass=pymysql.cursors.DictCursor,
    )


def compute_for_assessment(conn, assessment_id, force=False):
    with conn.cursor() as cur:
        # check assessment
        cur.execute("SELECT assessment_id, course_id, end_date FROM assessments WHERE assessment_id=%s LIMIT 1", (assessment_id,))
        a = cur.fetchone()
        if not a:
            print(f"Assessment {assessment_id} not found")
            return
        end_date = a.get('end_date')
        if not end_date or datetime.datetime.now() <= end_date:
            print(f"Skipping {assessment_id}: end_date not set or assessment still active")
            return

        # find users: user_courses
        cur.execute("SELECT user_id FROM user_courses WHERE course_id=%s", (a.get('course_id'),))
        rows = cur.fetchall() or []
        users = [r['user_id'] for r in rows]
        if not users:
            cur.execute("SELECT DISTINCT user_id FROM session_tokens WHERE assessment_id=%s", (assessment_id,))
            rows = cur.fetchall() or []
            users = [r['user_id'] for r in rows]
        if not users:
            print(f"No users for assessment {assessment_id}")
            return

        # compute usage per user
        usage_map = {}
        for uid in users:
            cur.execute("SELECT COALESCE(SUM(tokens_used),0) AS total_used FROM session_tokens WHERE assessment_id=%s AND user_id=%s", (assessment_id, uid))
            row = cur.fetchone() or {'total_used': 0}
            usage_map[uid] = int(row.get('total_used', 0) or 0)

        avg_usage = float(sum(usage_map.values())) / float(len(usage_map)) if usage_map else 0.0
        threshold = 1.10 * avg_usage

        # ensure final_points column exists (best-effort)
        try:
            cur.execute("ALTER TABLE user_points_assessment ADD COLUMN final_points DECIMAL(7,2) NULL")
            conn.commit()
        except Exception:
            pass

        # For each user compute and upsert
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
                    "VALUES (%s,%s,%s,%s,%s,%s,NOW()) "
                    "ON DUPLICATE KEY UPDATE total_points=VALUES(total_points), final_points=VALUES(final_points), updated_at=NOW()",
                    (uid_uuid, uid, assessment_id, a.get('course_id'), total_points_int, final_point_rounded),
                )
            except Exception:
                try:
                    cur.execute(
                        "INSERT INTO user_points_assessment (id, user_id, assessment_id, course_id, total_points, updated_at) "
                        "VALUES (%s,%s,%s,%s,%s,NOW()) "
                        "ON DUPLICATE KEY UPDATE total_points=VALUES(total_points), updated_at=NOW()",
                        (uid_uuid, uid, assessment_id, a.get('course_id'), total_points_int),
                    )
                except Exception as e:
                    print(f"Failed to upsert for user {uid}: {e}")
        conn.commit()
        print(f"Computed assessment {assessment_id}: avg_usage={avg_usage}, threshold={threshold}")


def main(argv):
    parser = argparse.ArgumentParser()
    parser.add_argument('--force', action='store_true', help='Force recompute even if final_points exists')
    args = parser.parse_args(argv)

    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT assessment_id FROM assessments WHERE end_date IS NOT NULL AND end_date < NOW()")
            rows = cur.fetchall() or []
            assessments = [r['assessment_id'] for r in rows]
        for aid in assessments:
            compute_for_assessment(conn, aid, force=args.force)
    finally:
        conn.close()


if __name__ == '__main__':
    main(sys.argv[1:])
