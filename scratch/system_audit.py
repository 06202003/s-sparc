import urllib.request
import json
import time

print("=" * 60)
print(" S-SPARC & E-STRANGE FULL-STACK SYSTEM AUDIT")
print("=" * 60)

# 1. Check PHP Server (Port 8088)
php_urls = [
    ("Login Page", "http://127.0.0.1:8088/index.php"),
    ("Student Registration", "http://127.0.0.1:8088/student_registration.php"),
    ("Forgot Password", "http://127.0.0.1:8088/forgot_password.php"),
]

print("\n[1] PHP Web Platform (Port 8088):")
for name, url in php_urls:
    try:
        with urllib.request.urlopen(url, timeout=5) as resp:
            print(f"  [OK] {name:<22}: HTTP {resp.status} (Length: {len(resp.read())} bytes)")
    except Exception as e:
        print(f"  [FAIL] {name:<22}: FAILED ({e})")

# 2. Check FastAPI Backend (Port 5000)
print("\n[2] FastAPI Backend Server (Port 5000):")
fastapi_urls = [
    ("Docs / Swagger", "http://127.0.0.1:5000/docs"),
    ("OpenAPI Schema", "http://127.0.0.1:5000/openapi.json"),
]

for name, url in fastapi_urls:
    try:
        with urllib.request.urlopen(url, timeout=5) as resp:
            print(f"  [OK] {name:<22}: HTTP {resp.status}")
    except Exception as e:
        print(f"  [FAIL] {name:<22}: FAILED ({e})")

# 3. Test AI Inference Endpoint POST /api/generate-code
print("\n[3] AI Generation & Semantic Cache (/api/generate-code):")
test_payloads = [
    ("Cache Hit Query (0 Tok)", {"prompt": "Buatkan fungsi Binary Search sederhana dalam C++", "language": "cpp", "response_mode": "code"}),
    ("Fresh Query Test", {"prompt": "Jelaskan perbedaan antara Quick Sort dan Merge Sort secara singkat.", "language": "text", "response_mode": "summary"})
]

for label, payload in test_payloads:
    try:
        req = urllib.request.Request(
            "http://127.0.0.1:5000/api/generate-code",
            data=json.dumps(payload).encode("utf-8"),
            headers={"Content-Type": "application/json", "X-User-ID": "218"},
            method="POST"
        )
        t0 = time.time()
        with urllib.request.urlopen(req, timeout=30) as resp:
            elapsed = time.time() - t0
            data = json.loads(resp.read().decode("utf-8"))
            is_retrieval = data.get("is_retrieval") or (data.get("tokens_used") is None or data.get("tokens_used") == 0)
            status_desc = "0-Token Cache Hit" if is_retrieval else f"Inference ({data.get('tokens_used', 'N/A')} tokens)"
            print(f"  [OK] {label:<22}: HTTP {resp.status} in {elapsed:.2f}s -> {status_desc}")
    except Exception as e:
        print(f"  [FAIL] {label:<22}: FAILED ({e})")

# 4. Check Database Tables & Records
print("\n[4] Database Health (db_semantic_final):")
try:
    import pymysql
    conn = pymysql.connect(
        host="127.0.0.1", user="david", password="david20juni2003#", database="db_semantic_final",
        cursorclass=pymysql.cursors.DictCursor
    )
    with conn.cursor() as cur:
        tables = ["user", "users", "gpt_jobs", "submission", "suspicion", "code_clarity_suggestion", "game_course", "session_tokens", "code_embeddings"]
        for t in tables:
            cur.execute(f"SELECT COUNT(*) as cnt FROM `{t}`")
            row = cur.fetchone()
            print(f"  [OK] Table `{t:<22}`: {row['cnt']} records")
    conn.close()
except Exception as e:
    print(f"  [FAIL] Database check failed: {e}")

print("\n" + "=" * 60)
print(" AUDIT COMPLETED")
print("=" * 60)
