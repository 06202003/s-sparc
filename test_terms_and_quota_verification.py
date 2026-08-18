"""
Verification Test Suite for Terms & Conditions and Dynamic Query Quota Tracker
Tests:
1. User API Key saving with Terms & Conditions acceptance
2. GET /api/user/query-quota endpoint schema and values (1,500 RPD / 15 RPM)
3. Dynamic query quota returned in POST /api/generate-code
4. Query tracking incrementation on subsequent requests
5. Rate limit and prompt boundaries maintained
"""
import sys
import os
import time
from unittest.mock import patch

# Ensure workspace root is in sys.path
sys.path.insert(0, os.path.abspath(os.path.dirname(__file__)))

from fastapi.testclient import TestClient
from backend.main import app
from backend.core.db import (
    get_db_connection,
    set_user_api_key, 
    get_user_api_key, 
    delete_user_api_key,
    get_user_query_quota,
    increment_user_query_count,
    _in_memory_user_keys,
    _in_memory_daily_queries
)

client = TestClient(app)

def run_tests():
    print("=" * 70, flush=True)
    print("STARTING TEST SUITE: Terms & Conditions & Dynamic Query Quota", flush=True)
    print("=" * 70, flush=True)
    
    test_user = f"test_terms_user_{int(time.time())}"
    # 1. Clean test state & ensure user exists in users table
    conn = get_db_connection()
    if conn is not None:
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "INSERT INTO users (user_id, username, email, password_hash) VALUES (%s, %s, %s, %s) ON DUPLICATE KEY UPDATE username=VALUES(username)",
                    (test_user, test_user, f"{test_user}@test.com", "hash")
                )
            conn.commit()
        except Exception:
            pass
        finally:
            conn.close()
            
    delete_user_api_key(test_user, provider="gemini")
    keys_to_del = [k for k in _in_memory_daily_queries.keys() if k[0] == test_user]
    test_key = "AIzaSyTestTermsAndQuotaKey99999"
    headers = {"X-User-ID": test_user}
    
    # 2. Check query quota before key registration
    print("\n[TEST 1] Checking Query Quota for user with NO key registered...", flush=True)
    resp = client.get("/api/user/query-quota", headers=headers)
    assert resp.status_code == 200, f"Expected 200, got {resp.status_code}: {resp.text}"
    quota_no_key = resp.json()
    print(f"  Response: {quota_no_key}", flush=True)
    assert quota_no_key["has_key"] is False, "Expected has_key to be False"
    assert quota_no_key["daily_limit"] == 1500, "Expected daily_limit 1500"
    assert quota_no_key["daily_remaining"] == 1500, "Expected daily_remaining 1500"
    print("  --> PASS: Initial quota correctly reports no key and 1500 limit.", flush=True)

    # 3. Save API Key with explicit Terms & Conditions acceptance
    print("\n[TEST 2] Saving personal API key with terms_accepted=True...", flush=True)
    save_payload = {
        "api_key": test_key,
        "provider": "gemini",
        "terms_accepted": True
    }
    resp = client.post("/api/user/api-key", json=save_payload, headers=headers)
    assert resp.status_code == 200, f"Expected 200, got {resp.status_code}: {resp.text}"
    save_data = resp.json()
    print(f"  Response: {save_data}", flush=True)
    assert save_data["status"] == "success"
    assert save_data["terms_accepted"] is True
    assert save_data["masked_key"].startswith("AIzaSy")
    print("  --> PASS: API key saved with Terms & Conditions acknowledged.", flush=True)

    # 4. Check query quota after key registration
    print("\n[TEST 3] Checking Query Quota after key registration...", flush=True)
    resp = client.get("/api/user/query-quota", headers=headers)
    assert resp.status_code == 200
    quota_with_key = resp.json()
    print(f"  Response: {quota_with_key}", flush=True)
    assert quota_with_key["has_key"] is True
    assert quota_with_key["masked_key"].startswith("AIzaSy")
    assert quota_with_key["daily_limit"] == 1500
    assert quota_with_key["daily_used"] == 0
    assert quota_with_key["daily_remaining"] == 1500
    assert quota_with_key["rate_limit_rpm"] == 15
    assert quota_with_key["cooldown_seconds"] == 60
    assert quota_with_key["terms_accepted"] is True
    print("  --> PASS: Query quota endpoint accurately returns 1500 RPD, 15 RPM, and terms status.", flush=True)

    # 5. Execute chat generation with mocked router and verify dynamic query_quota in response
    print("\n[TEST 4] Generating code and validating dynamic query_quota payload...", flush=True)
    chat_payload = {
        "prompt": "Buatkan fungsi Python binary_search untuk mencari elemen dalam sorted array.",
        "language": "python",
        "response_mode": "code"
    }
    mock_router_result = {
        "text": "def binary_search(arr, target): return 0",
        "provider": "gemini_user_key",
        "model": "gemini-2.5-flash",
        "tokens": 40,
        "points_deducted": 0.0,
        "is_retrieval": False
    }

    with patch("backend.services.adaptive_router.AdaptiveRouter.route_and_generate", return_value=mock_router_result):
        resp = client.post("/api/generate-code", json=chat_payload, headers=headers)
    
    assert resp.status_code == 200, f"Expected 200, got {resp.status_code}: {resp.text}"
    chat_res = resp.json()
    print(f"  Response keys: {list(chat_res.keys())}", flush=True)
    assert "query_quota" in chat_res, "Expected 'query_quota' in response"
    quota_after_chat = chat_res["query_quota"]
    print(f"  Returned query_quota: {quota_after_chat}", flush=True)
    assert quota_after_chat["daily_used"] >= 1, f"Expected daily_used >= 1, got {quota_after_chat['daily_used']}"
    assert quota_after_chat["daily_remaining"] == quota_after_chat["daily_limit"] - quota_after_chat["daily_used"]
    assert chat_res["cooldown_seconds"] == 60
    print("  --> PASS: Dynamic query quota returned and decremented correctly.", flush=True)

    # 6. Verify subsequent query quota endpoint call reflects the incremented usage
    print("\n[TEST 5] Verifying GET /api/user/query-quota reflects updated usage...", flush=True)
    resp = client.get("/api/user/query-quota", headers=headers)
    assert resp.status_code == 200
    updated_quota = resp.json()
    print(f"  Live query quota: {updated_quota}", flush=True)
    assert updated_quota["daily_used"] == quota_after_chat["daily_used"]
    assert updated_quota["daily_remaining"] == 1500 - updated_quota["daily_used"]
    print("  --> PASS: Live query quota matches server-side tracked usage.", flush=True)

    # 7. Cleanup
    delete_user_api_key(test_user, provider="gemini")
    _in_memory_daily_queries.pop((test_user, "gemini"), None)
    
    print("\n" + "=" * 70, flush=True)
    print("ALL 5 TESTS PASSED SUCCESSFULLY! (100% PASS)", flush=True)
    print("=" * 70, flush=True)

if __name__ == "__main__":
    run_tests()
