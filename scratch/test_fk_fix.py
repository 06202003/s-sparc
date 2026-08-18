import sys
import os

# Add workspace to path
sys.path.insert(0, r"c:\S-SPARC_FINAL EDIT")

from backend.api.ai_chat import insert_gpt_job, get_gpt_job
from backend.core.db import resolve_user_uuid, get_db_connection

print("=== Testing resolve_user_uuid ===")
uid_218 = resolve_user_uuid("218")
print(f"User 218 resolved to: {uid_218}")

uid_2172003 = resolve_user_uuid("2172003")
print(f"User 2172003 resolved to: {uid_2172003}")

print("\n=== Testing insert_gpt_job with user_id='218' ===")
try:
    job_id = insert_gpt_job("218", "test prompt for binary search", "test prompt for binary search")
    print(f"[SUCCESS] Created job_id: {job_id}")
    job = get_gpt_job(job_id)
    print(f"Job in DB: user_id={job['user_id']}, status={job['status']}")
except Exception as e:
    print(f"[FAILED]: {e}")
    sys.exit(1)

print("\nAll DB foreign key tests passed!")
