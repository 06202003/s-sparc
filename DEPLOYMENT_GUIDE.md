# S-SPARC AI Deployment Guide

## Problem yang Diperbaiki

**PROBLEM 1: Blocking Operations**

- **Sebelumnya:** User 2 tidak bisa login saat User 1 sedang menggunakan LLM (blocking)
- **Solusi:** Threading enabled + background worker untuk concurrent request handling

---

## Quick Start (Development)

### Mode 1: Flask dengan Background Worker (RECOMMENDED untuk Development)

```bash
# Local development (hanya PC ini)
python app.py --port 5000

# Atau untuk akses dari jaringan lab
python app.py --host 0.0.0.0 --port 5000
```

**Fitur:**

- ✅ Flask server dengan threading enabled (concurrent requests)
- ✅ Background worker thread untuk process GPT jobs
- ✅ User 1 generate code TIDAK akan block User 2 login
- ✅ Multiple users bisa akses bersamaan

**Output yang diharapkan:**

```
[INFO] Starting background GPT job worker thread...
[INFO] Starting Flask server on 0.0.0.0:5000 with threading enabled...
[WORKER] GPT job worker started.
 * Serving Flask app 'app'
 * Debug mode: on
```

---

### Mode 2: Worker Terpisah (Advanced)

Untuk production atau load tinggi, jalankan worker di process terpisah:

#### Terminal 1 (Flask Server)

```bash
python app.py --host 0.0.0.0 --port 5000 --no-worker
```

#### Terminal 2 (Dedicated Worker)

```bash
python app.py --worker
```

**Keuntungan:**

- Worker tidak terpengaruh jika Flask restart
- Bisa scale worker secara independen (multiple worker processes)
- Lebih stable untuk production

---

## Production Deployment (RECOMMENDED untuk Lab/Server)

### Option 1: Waitress (Windows-friendly)

1. **Install Waitress:**

   ```bash
   pip install waitress
   ```

2. **Create production runner** (`run_production.py`):

   ```python
   from waitress import serve
   from app import app
   import threading
   from app import gpt_job_worker

   # Start background worker
   print("[INFO] Starting background GPT job worker thread...")
   worker_thread = threading.Thread(target=gpt_job_worker, daemon=True)
   worker_thread.start()

   # Serve with Waitress (production-ready WSGI server)
   print("[INFO] Starting Waitress WSGI server on 0.0.0.0:5000...")
   serve(app, host='0.0.0.0', port=5000, threads=8)
   ```

3. **Run production server:**
   ```bash
   python run_production.py
   ```

**Fitur:**

- ✅ Production-grade WSGI server
- ✅ 8 concurrent threads (configurable)
- ✅ Better stability & performance
- ✅ No reloader (safe for production)

---

### Option 2: Gunicorn (Linux/Mac)

1. **Install Gunicorn:**

   ```bash
   pip install gunicorn
   ```

2. **Terminal 1 - Run Gunicorn:**

   ```bash
   gunicorn -w 4 -b 0.0.0.0:5000 --timeout 120 app:app
   ```

   - `-w 4` = 4 worker processes
   - `--timeout 120` = 120 detik timeout untuk GPT requests

3. **Terminal 2 - Run Worker:**
   ```bash
   python app.py --worker
   ```

---

## Testing Concurrent Access

### Test 1: Login saat LLM running

1. **User 1:** Buka browser, login, generate code (tunggu response)
2. **User 2:** Buka browser lain/incognito, akses login page
3. **Expected:** User 2 bisa login tanpa menunggu User 1 selesai ✅

### Test 2: Multiple code generation

1. **User 1:** Generate code request 1
2. **User 2:** Generate code request 2 (tidak menunggu User 1)
3. **Expected:** Keduanya masuk queue, diproses oleh worker secara berurutan ✅

---

## Architecture Diagram

### Before (Blocking):

```
User 1 → [Flask] → GPT (blocking) → Response
                    ↓
User 2 → WAIT... 😭 (cannot access)
```

### After (Threading + Worker):

```
User 1 → [Flask Thread 1] → Login ✅
User 2 → [Flask Thread 2] → Generate → [Queue] → [Worker Thread] → GPT
User 3 → [Flask Thread 3] → Dashboard ✅
```

---

## Performance Recommendations

### Development (Lab Testing):

```bash
python app.py --host 0.0.0.0 --port 5000
```

- **Threads:** Default Flask threading (good untuk 5-20 concurrent users)
- **Worker:** 1 background thread
- **Good for:** Development, testing, small lab environment

### Production (Real Deployment):

```bash
# Option A: Waitress (Windows)
python run_production.py  # 8 threads

# Option B: Gunicorn (Linux)
gunicorn -w 4 -b 0.0.0.0:5000 --timeout 120 app:app
python app.py --worker  # Separate terminal
```

- **Threads:** 4-8 worker processes (Gunicorn) atau 8-16 threads (Waitress)
- **Worker:** 1-2 dedicated worker processes
- **Good for:** 50-200 concurrent users

---

## Troubleshooting

### Problem: Worker tidak jalan

**Symptom:** GPT jobs stuck di status "pending"

**Solution 1:** Pastikan worker thread jalan

```bash
# Check logs saat startup, harus ada:
[INFO] Starting background GPT job worker thread...
[WORKER] GPT job worker started.
```

**Solution 2:** Jika pakai `--no-worker`, jalankan worker manual:

```bash
python app.py --worker
```

---

### Problem: "Address already in use"

**Symptom:** `OSError: [WinError 10048] Only one usage of each socket address`

**Solution:** Port 5000 sudah dipakai, gunakan port lain:

```bash
python app.py --host 0.0.0.0 --port 5001
```

Atau kill process yang pakai port 5000:

```bash
# Windows
netstat -ano | findstr :5000
taskkill /PID <PID> /F

# Linux/Mac
lsof -ti:5000 | xargs kill -9
```

---

### Problem: Database connection pool exhausted

**Symptom:** `pymysql.err.OperationalError: (1040, 'Too many connections')`

**Solution:** Increase MySQL max_connections:

```sql
-- Check current value
SHOW VARIABLES LIKE 'max_connections';

-- Increase limit (requires restart)
SET GLOBAL max_connections = 500;
```

Or optimize connection management in code (use connection pooling with SQLAlchemy).

---

## Command Reference

```bash
# Development dengan auto-worker (RECOMMENDED)
python app.py --host 0.0.0.0 --port 5000

# Development tanpa worker (manual worker di terminal lain)
python app.py --host 0.0.0.0 --port 5000 --no-worker

# Worker only (dedicated process)
python app.py --worker

# Production dengan Waitress
python run_production.py

# Production dengan Gunicorn (Linux/Mac)
gunicorn -w 4 -b 0.0.0.0:5000 --timeout 120 app:app
```

---

## Next Steps

1. ✅ **Test concurrent access** (2-3 users login bersamaan)
2. ✅ **Test GPT queue** (multiple code generation requests)
3. ✅ **Monitor worker logs** (pastikan jobs diproses)
4. ✅ **Load testing** (jika perlu, gunakan Apache Bench atau Locust)

---

## Notes

- **`threaded=True`** di Flask memungkinkan concurrent requests (multiple users)
- **Background worker thread** memproses GPT jobs tanpa block Flask
- **`daemon=True`** pada worker thread → worker mati otomatis saat Flask shutdown
- **`use_reloader=False`** mencegah worker thread duplikasi saat auto-reload

**Rekomendasi:** Untuk lab deployment dengan 10-50 users, mode default sudah cukup. Untuk production dengan >50 users, gunakan Waitress atau Gunicorn.
