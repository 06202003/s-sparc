# 🚀 Panduan Lengkap Cara Running Sistem S-SPARC AI + E-STRANGE Platform

Dokumen ini berisi panduan *step-by-step* untuk menjalankan seluruh komponen dari ekosistem **S-SPARC AI (Frontend, Backend, Code Evaluator Microservice)** dan **E-STRANGE Parent Platform**.

---

## 🏗️ Ringkasan Arsitektur & Port Komponen

| Komponen | Bahasa / Framework | Fungsi Utama | Default Port | Direktori |
| :--- | :--- | :--- | :--- | :--- |
| **MySQL Database** | MySQL 8.0 / MariaDB | Database gabungan E-STRANGE + S-SPARC | `3306` | Root (`db_semantic_vfinal.sql`) |
| **E-STRANGE Platform** | PHP (Native / PDO) | LMS Induk (Pendaftaran, Tugas, Submisi, Peer Review) | `8080` | `estrange/v2/v2/` |
| **S-SPARC Frontend** | PHP / JS Interactive | Client Workspace AI (Gauge Token, Footprint, Chat) | `8000` | `frontend/` |
| **S-SPARC Backend** | Python / FastAPI / Uvicorn | API Gateway, Semantic Search (0 Token), Footprint Telemetry | `5000` | `backend/` |
| **Code Evaluator** | Python / FastAPI / Radon | Quality Control Snippet (LLM-as-a-Judge, Anomaly Detection) | `8001` | `code_evaluator_service/` |

---

## 📋 Langkah 1: Persiapan & Import Database

1. Pastikan **MySQL / MariaDB Server** (XAMPP / Standalone) sudah aktif pada port `3306`.
2. Import database `db_semantic_vfinal.sql` yang berada di root proyek:
   ```bash
   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS estrange_ssparc;"
   mysql -u root -p estrange_ssparc < db_semantic_vfinal.sql
   ```

---

## ⚙️ Langkah 2: Konfigurasi Environment Variables (`.env`)

Buat file `.env` di root proyek (`c:\S-SPARC_FINAL EDIT\.env`):

```env
# MySQL Database Configuration
MYSQL_HOST=127.0.0.1
MYSQL_PORT=3306
MYSQL_USER=root
MYSQL_PASSWORD=root
MYSQL_DB=estrange_ssparc

# OpenAI API Key (Untuk Fallback Inference jika Semantic Search < 90%)
OPENAI_API_KEY=sk-proj-YOUR_API_KEY_HERE

# Session Secret Key
FLASK_SECRET_KEY=supersecretkey_ssparc_2026

# FastAPI & Worker Settings
FASTAPI_HOST=0.0.0.0
FASTAPI_PORT=5000
UVICORN_WORKERS=4
```

---

## 🏃 Langkah 3: Cara Running Masing-Masing Komponen

Buka **4 jendela Terminal / Command Prompt terpisah** untuk menjalankan keempat layanan:

### Terminal 1: Running E-STRANGE Parent Platform (LMS Induk)
```bash
cd "c:\S-SPARC_FINAL EDIT\estrange\v2\v2"
php -S 0.0.0.0:8080
```
> **Akses Browser**: `http://localhost:8080/index.php`

---

### Terminal 2: Running S-SPARC Frontend (Client App Mahasiswa)
```bash
cd "c:\S-SPARC_FINAL EDIT\frontend"
php -S 0.0.0.0:8000
```
> **Catatan**: Pastikan file `frontend/config.php` mengarah ke Backend API:
> `$backend_url = "http://127.0.0.1:5000";`
> **Akses Browser**: `http://localhost:8000/index.php`

---

### Terminal 3: Running S-SPARC FastAPI Backend (AI Core & Semantic Search)

**Pilihan A (Development Mode - Fast Reload)**:
```bash
cd "c:\S-SPARC_FINAL EDIT"
python run_fastapi.py
```

**Pilihan B (Production Mode - GPU Acceleration RTX 3060)**:
```bash
cd "c:\S-SPARC_FINAL EDIT"
python run_production_server.py
```
> **Akses API**: `http://localhost:5000/health`
> **Akses Dokumentasi Redocly**: `http://localhost:5000/redocly`

---

### Terminal 4: Running Code Evaluator Microservice & Batch Scheduler

**1. Running Evaluator API Service**:
```bash
cd "c:\S-SPARC_FINAL EDIT"
python -m code_evaluator_service.evaluator_app
```

**2. Running Automated Weekly Batch Scheduler (Opsional)**:
```bash
cd "c:\S-SPARC_FINAL EDIT"
python -m code_evaluator_service.scheduler
```

---

## 🔍 Troubleshooting & Cek Koneksi Inter-Service

1. **Cek Uptime Backend**:
   Akses `http://localhost:5000/health` $\rightarrow$ Respon harus `{"status": "ok", ...}`.
2. **Cek Semantic Search (0 Token / FREE Tier)**:
   Saat mahasiswa mengajukan prompt yang memiliki kemiripan semantik $\ge 90\%$ dengan database `code_embeddings`, backend akan merespon instan dalam `< 50ms` tanpa memotong kuota token API OpenAI.
3. **Cek Redocly API Docs**:
   Buka `http://localhost:5000/redocly` di browser atau buka file [docs/index.html](file:///c:/S-SPARC_FINAL%20EDIT/docs/index.html) langsung secara offline.
