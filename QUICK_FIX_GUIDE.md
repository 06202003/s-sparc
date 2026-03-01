# QUICK FIX GUIDE - S-SPARC AI

## ⚡ Problem 1: User 2 tidak bisa login saat User 1 pakai LLM

### ❌ Sebelumnya:

```
User 1 generate code → Flask BLOCKED → User 2 tunggu... 😭
```

### ✅ Setelah Fix:

```
User 1 generate code → Queue → Background Worker
User 2 login → INSTANT ✅
User 3 dashboard → INSTANT ✅
```

### Cara Pakai:

#### Quick Start (Recommended):

```bash
# Local development (default)
python app.py --port 5000

# Atau untuk akses dari komputer lain di jaringan
python app.py --host 0.0.0.0 --port 5000
```

**Output harus ada:**

```
[INFO] Starting background GPT job worker thread...
[INFO] Starting Flask server on localhost:5000 with threading enabled...
[WORKER] GPT job worker started.
```

#### Test Concurrent Access:

1. Browser 1: Login sebagai User 1, generate code
2. Browser 2 (incognito): Login sebagai User 2
3. **Expected:** User 2 bisa login tanpa tunggu ✅

---

## 🚀 Upgrade ke Production (Optional)

### Untuk Lab dengan >20 Users:

```bash
# Install Waitress
pip install waitress

# Run production server
python run_production.py
```

**Benefit:**

- 8 concurrent threads (vs 1 thread sebelumnya)
- Better stability
- Handle 50-100+ concurrent users

---

## 🔧 Troubleshooting

### Problem: Worker tidak jalan (jobs stuck di "pending")

**Check:** Saat startup, harus ada log ini:

```
[INFO] Starting background GPT job worker thread...
[WORKER] GPT job worker started.
```

**Fix jika tidak ada:**

```bash
# Terminal 1: Flask tanpa auto-worker
python app.py --host 0.0.0.0 --port 5000 --no-worker

# Terminal 2: Manual worker
python app.py --worker
```

---

### Problem: Port 5000 sudah dipakai

**Error:**

```
OSError: [WinError 10048] Only one usage of each socket address
```

**Fix:**

```bash
# Gunakan port lain
python app.py --host 0.0.0.0 --port 5001

# Atau kill process yang pakai port 5000
netstat -ano | findstr :5000
taskkill /PID <PID> /F
```

---

### Problem: "Too many connections" di MySQL

**Fix:** Increase MySQL max_connections

```sql
SHOW VARIABLES LIKE 'max_connections';
SET GLOBAL max_connections = 500;
```

---

## 📊 Performance Comparison

| Metric                 | Before    | After      |
| ---------------------- | --------- | ---------- |
| Concurrent users       | 1         | 8-16       |
| Login saat LLM running | ❌ Block  | ✅ Instant |
| Multiple code gen      | ❌ Serial | ✅ Queue   |
| Production ready       | ❌ No     | ✅ Yes     |

---

## 🎯 Command Cheat Sheet

```bash
# Development (Recommended)
python app.py --host 0.0.0.0 --port 5000

# Production (50+ users)
python run_production.py

# Manual worker (advanced)
python app.py --worker

# Custom port
python app.py --host 0.0.0.0 --port 5001
```

---

## ✅ Checklist

- [ ] Install dependencies: `pip install -r requirements.txt`
- [ ] Run backend: `python app.py --host 0.0.0.0 --port 5000`
- [ ] Check logs: Harus ada `[INFO] Starting background GPT job worker thread...`
- [ ] Test concurrent access: 2 users login bersamaan
- [ ] Test GPT queue: Multiple code generation requests
- [ ] Firewall: Allow port 5000 TCP inbound

---

**Selesai!** Sekarang sistem bisa handle multiple concurrent users. 🎉
