# 🚀 S-SPARC AI & E-STRANGE Platform - Quick Deployment Summary

Panduan ringkas untuk menjalankan sistem **S-SPARC AI Assistant (FastAPI)** dan **E-STRANGE LMS (PHP)**.

> 📖 **Untuk panduan deployment produksi lengkap, detail arsitektur, setup Linux VPS, Nginx SSL, dan troubleshooting, silakan baca [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md).**

---

## ⚡ 1-Click Launch (Windows Quick Start)

1. Pastikan **MySQL Server** aktif di port `3306` (atau jalankan `docker compose up -d`).
2. Pastikan **Ollama** aktif di port `11434` (model `qwen2.5-coder:14b`).
3. Pastikan file `.env` sudah dikonfigurasi (salin dari `.env.example`).
4. Dobel klik file:
   ```cmd
   start_full_system.bat
   ```
   *Terminal 1 akan membuka FastAPI Backend di `http://localhost:8000`*
   *Terminal 2 akan membuka E-STRANGE Web Portal di `http://localhost:8080`*

---

## 🛠️ Langkah Manual (Step-by-Step)

### 1. Database MySQL
```bash
mysql -u root -p db_semantic_final < database/db_semantic_vfinal.sql
```

### 2. Python Virtual Environment & Backend
```bash
python -m venv .venv
.venv\Scripts\activate          # Windows
# source .venv/bin/activate     # Linux/macOS

pip install -r requirements.txt
python -m uvicorn backend.main:app --host 0.0.0.0 --port 8000 --reload
```

### 3. Frontend Web (E-STRANGE)
```bash
cd estrange
php -S 0.0.0.0:8080
```

---

## 🌐 Layanan & Akses URL:
- **E-STRANGE Web Portal:** [http://localhost:8080](http://localhost:8080)
- **FastAPI Swagger Docs:** [http://localhost:8000/docs](http://localhost:8000/docs)
- **Redocly API Reference:** [http://localhost:8000/redocly](http://localhost:8000/redocly)
- **Backend Health Check:** [http://localhost:8000/api/health](http://localhost:8000/api/health)
