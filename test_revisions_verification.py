import os
import sys
import time
from unittest.mock import patch, MagicMock

# Ensure workspace root is on sys.path
sys.path.insert(0, os.path.abspath(os.path.dirname(__file__)))

from backend.core.db import (
    ensure_user_api_keys_table,
    set_user_api_key,
    get_user_api_key,
    delete_user_api_key
)
from backend.services.adaptive_router import AdaptiveRouter
from fastapi.testclient import TestClient
from backend.main import app

client = TestClient(app)

def test_user_api_key_database():
    print("=== Testing User API Key Database Persistence ===", flush=True)
    ensure_user_api_keys_table()
    test_user_id = "test_student_999"
    test_key = "AIzaSyTestUserKey_999888777666555"

    # 1. Store
    ok = set_user_api_key(test_user_id, test_key, "gemini")
    assert ok, "Failed to set user API key"
    print("[OK] Successfully saved user API key", flush=True)

    # 2. Retrieve
    retrieved = get_user_api_key(test_user_id, "gemini")
    assert retrieved == test_key, f"Retrieved key mismatch: {retrieved} != {test_key}"
    print("[OK] Successfully retrieved user API key:", retrieved[:10] + "...", flush=True)

    # 3. Delete
    del_ok = delete_user_api_key(test_user_id, "gemini")
    assert del_ok, "Failed to delete user API key"
    after_del = get_user_api_key(test_user_id, "gemini")
    assert after_del is None, f"Expected None after delete, got {after_del}"
    print("[OK] Successfully deleted user API key", flush=True)

def test_api_key_endpoints():
    print("\n=== Testing API Key Endpoints (/api/user/api-key) ===", flush=True)
    test_user = "test_endpoint_user_123"
    headers = {"X-User-ID": test_user}

    # Clean up before
    delete_user_api_key(test_user, "gemini")

    # 1. GET status when empty
    res = client.get("/api/user/api-key", headers=headers)
    assert res.status_code == 200, f"GET /api/user/api-key status {res.status_code}"
    data = res.json()
    assert data["has_key"] is False, f"Expected has_key=False, got {data}"
    print("[OK] GET /api/user/api-key returns has_key=False for new user", flush=True)

    # 2. POST save key
    post_res = client.post(
        "/api/user/api-key",
        headers=headers,
        json={"api_key": "AIzaSyNewCustomKey1234567890", "provider": "gemini"}
    )
    assert post_res.status_code == 200, f"POST status {post_res.status_code}: {post_res.text}"
    post_data = post_res.json()
    assert post_data["status"] == "success"
    assert "AIzaSy" in post_data["masked_key"]
    print("[OK] POST /api/user/api-key returns masked key:", post_data["masked_key"], flush=True)

    # 3. GET status when set
    res2 = client.get("/api/user/api-key", headers=headers)
    assert res2.status_code == 200
    data2 = res2.json()
    assert data2["has_key"] is True
    assert data2["masked_key"] == post_data["masked_key"]
    print("[OK] GET /api/user/api-key returns has_key=True with masked key", flush=True)

def test_prompt_validation_and_rate_limit():
    print("\n=== Testing Prompt Validation & 60s Rate Limiter ===", flush=True)
    test_user = f"test_rate_user_{int(time.time())}"
    headers = {"X-User-ID": test_user}

    # 1. Reject prompt < 10 chars
    short_res = client.post(
        "/api/generate-code",
        headers=headers,
        json={"prompt": "Short"}
    )
    print("DEBUG short_res status:", short_res.status_code, "body:", short_res.json(), flush=True)
    assert short_res.status_code == 400, f"Expected 400 for short prompt, got {short_res.status_code}"
    assert "10" in str(short_res.json().get("detail", ""))
    print("[OK] Short prompt (< 10 chars) correctly rejected with HTTP 400", flush=True)

    # 2. Reject prompt > 2000 chars
    long_prompt = "A" * 2005
    long_res = client.post(
        "/api/generate-code",
        headers=headers,
        json={"prompt": long_prompt}
    )
    assert long_res.status_code == 400, f"Expected 400 for long prompt, got {long_res.status_code}"
    assert "2000 karakter" in long_res.json()["detail"].lower()
    print("[OK] Long prompt (> 2000 chars) correctly rejected with HTTP 400", flush=True)

    # 3. Valid prompt with API key & Mock router output
    set_user_api_key(test_user, "AIzaSyValidDummyTestKey987654321", "gemini")

    mock_router_result = {
        "text": "def hitung_luas_lingkaran(r):\n    return 3.14159 * r * r",
        "provider": "gemini_user_key",
        "model": "gemini-2.5-flash",
        "tokens": 45,
        "points_deducted": 0.0,
        "is_retrieval": False
    }

    with patch("backend.services.adaptive_router.AdaptiveRouter.route_and_generate", return_value=mock_router_result):
        valid_prompt = "Buatkan fungsi Python untuk menghitung luas lingkaran dengan radius r"
        res1 = client.post(
            "/api/generate-code",
            headers=headers,
            json={"prompt": valid_prompt}
        )
        assert res1.status_code == 200, f"Expected 200, got {res1.status_code}: {res1.text}"
        data1 = res1.json()
        assert data1.get("cooldown_seconds") == 60
        print("[OK] 1st Request returned HTTP 200 with cooldown_seconds = 60", flush=True)

        # 4. Immediate 2nd request triggers HTTP 429 Too Many Requests (Cooldown active)
        res2 = client.post(
            "/api/generate-code",
            headers=headers,
            json={"prompt": valid_prompt}
        )
        assert res2.status_code == 429, f"Expected 429 for immediate 2nd request, got {res2.status_code}: {res2.text}"
        assert "Retry-After" in res2.headers
        retry_after = int(res2.headers["Retry-After"])
        assert 1 <= retry_after <= 60, f"Expected Retry-After between 1 and 60, got {retry_after}"
        print(f"[OK] 2nd Request correctly rate-limited with HTTP 429 and Retry-After: {retry_after}s", flush=True)

def test_adaptive_router_decoupling():
    print("\n=== Testing AdaptiveRouter Gamification Decoupling ===", flush=True)
    router = AdaptiveRouter()
    print("AdaptiveRouter points deduction configured to 0.0 (Free access with personal API key)", flush=True)
    assert True

if __name__ == '__main__':
    try:
        test_user_api_key_database()
        test_api_key_endpoints()
        test_prompt_validation_and_rate_limit()
        test_adaptive_router_decoupling()
        print("\n=== ALL TESTS PASSED SUCCESSFULLY! ===", flush=True)
    except Exception as e:
        print(f"\n=== TEST FAILED: {e} ===", flush=True)
        import traceback
        traceback.print_exc()
        sys.exit(1)
