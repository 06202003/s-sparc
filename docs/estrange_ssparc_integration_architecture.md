# 🌿 Arsitektur Integrasi E-STRANGE (Parent) & S-SPARC (AI Module)
## Dokumentasi Resmi: Single Sign-On (SSO), Unified Navigation, Pembersihan File & API FastAPI

---

## 1. Eksekutif Ringkasan & Konsep Integrasi

**E-STRANGE** berperan sebagai **Parent Learning Platform (Sistem Induk)** yang mengelola mata kuliah, asesmen, pengumpulan tugas (*submissions*), etika kode, plagiarisme (*suspicion*), kualitas kode (*code clarity*), efisiensi, dan *peer review*.

**S-SPARC (Sustainable Smart Personal Assistant for Responsible Consumption)** telah sepenuhnya dirombak dan diintegrasikan sebagai **Sub-Module AI Langsung di dalam E-STRANGE** pada direktori `estrange/v2/v2/ssparc/`.

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                             E-STRANGE PARENT SYSTEM (PHP)                               │
│                                                                                         │
│  [Courses]  [Assessments]  [Submissions]  [Peer Review]  [🌿 S-SPARC AI Assistant]  ◄──┐ │
│                                                                        │                │
└────────────────────────────────────────────────────────────────────────┼────────────────┘
                                                                         │
                                                 Seamless SSO Navigation │
                                                 (Active Session)        │
                                                 URL: /ssparc/chat.php   │
                                                                         ▼
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                           S-SPARC SUB-SYSTEM / FE (PHP + JS)                            │
│                                                                                         │
│     [AI Chatbot]     [Gamification Metrics]     [Environmental Footprint Dashboard]     │
└────────────────────────────────────────────────────┬────────────────────────────────────┘
                                                     │
                                                     │ API Calls (X-User-ID: {user_id})
                                                     ▼
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                       FASTAPI BACKEND SERVICE (Python 3.12 / Port 5000)                 │
│                                                                                         │
│  • /generate-code (Semantic Search + GPT-4o)     • /impact-summary ($Wh$, $CO_2$, $H_2O$)│
│  • /gamification (Dynamic Token Threshold)       • /admin-environmental-stats           │
└────────────────────────────────────────────────────┬────────────────────────────────────┘
                                                     │
                                                     ▼
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                        SHARED DATABASE (MySQL: db_semantic_final)                       │
│                                                                                         │
│  • E-STRANGE Tables: user, course, assessment, submission, suspicion, peer_review...   │
│  • S-SPARC Tables: code_embeddings, chat_history, environmental_impact_logs, gpt_jobs...│
└─────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Struktur Direktori Baru

```
c:\S-SPARC_FINAL EDIT\
├── backend\                     # FastAPI Backend (Port 5000)
│   ├── api\                     # Endpoint AI, Auth, Domain, Admin
│   ├── core\                    # Database & Queue Configuration
│   ├── models\                  # Pydantic Schemas
│   └── services\                # AI Inference, Semantic Cache, Gamification
├── docs\                        # Dokumentasi Sistem
│   └── estrange_ssparc_integration_architecture.md
├── estrange\                    # E-STRANGE Core Platform (Port 8080)
│   ├── estrange_v7.sql          # 212 MB Database Dump Resmi
│   ├── paper_estrange.pdf       # Paper Publikasi Elsevier Software Impacts 2026
│   └── v2\v2\                   # Root PHP Web Server E-STRANGE
│       ├── _config.php          # Navbar Terpadu (Menyertakan S-SPARC)
│       ├── index.php            # Gerbang Login Utama E-STRANGE
│       ├── student_dashboard.php# Dashboard Mahasiswa
│       ├── lecturer_dashboard.php# Dashboard Dosen
│       ├── admin_dashboard.php  # Dashboard Admin
│       └── ssparc\              # MODUL S-SPARC TERPADU
│           ├── _sso_bridge.php  # Jembatan Autentikasi Single Sign-On
│           ├── chat.php         # Asisten AI Coding Ramah Lingkungan
│           ├── gamification.php # Dashboard Dynamic Token Threshold
│           ├── environmental_impact.php # Metrik Emisi Karbon, Energi & Air
│           └── leaderboard.php  # Peringkat Efisiensi Token Mahasiswa
└── run_fastapi.py               # Runner Khusus FastAPI Backend
```

---

## 3. Berkas Sampah & Redundan yang Telah Dihapus ([DELETED])

Untuk menjaga kebersihan repositori dan menghilangkan kebingungan, seluruh berkas usang telah dieliminasi:
1. `estrange/v2/v2/error_log` (2.1 MB)
2. `estrange/v2/v2/UTS.zip` (11.5 MB)
3. `estrange/v2/v2/last_attempt_128.zip` (614 KB)
4. `estrange/v2/v2/tempCodeRunnerFile.php`
5. `estrange/v2/v2/dummy.php`
6. `estrange/v2/v2/admin_game_old.php`
7. `estrange/v2/v2/sample_suspicion_alert.html`
8. `estrange/v2/v2/sample_suspicion_simulation.html`
9. `estrange/v2/v2/karina/error_log`
10. `app.py` (167 KB prototipe lama)
11. `app_enhanced.py` & `app_LLM.py`
12. `cek_db_flask.py` & `gemini_api.txt.txt`
13. `run.txt`
14. `estrange/fast_estrange_v7.sql` (212 MB)

---

## 4. Mekanisme Single Sign-On (SSO) 1x Login

1. Mahasiswa/Dosen login 1 kali di `http://127.0.0.1:8080/index.php`.
2. Sesi PHP menyimpan `$_SESSION['user_id']`, `$_SESSION['username']`, dan `$_SESSION['role']`.
3. Saat pengguna mengklik menu **S-SPARC AI** atau **Eco-Metrics** di navbar, halaman `ssparc/_sso_bridge.php` memverifikasi sesi tanpa login ulang.
4. Permintaan AJAX dari antarmuka S-SPARC ke FastAPI (`http://127.0.0.1:5000`) menyertakan header `X-User-ID: {user_id}`.
5. FastAPI memvalidasi identitas user langsung terhadap tabel `users` / `user` pada basis data bersama `db_semantic_final`.

---

## 5. Hasil Pengujian End-to-End (*Test Summary*)

| No | Komponen Pengujian | Endpoint / URL | Status | Hasil |
|---|---|---|---|---|
| 1 | Login E-STRANGE | `http://127.0.0.1:8080/index.php` | 200 OK | Sesi berhasil terbentuk untuk user `2172001` |
| 2 | Dashboard & Navbar | `http://127.0.0.1:8080/student_dashboard.php` | 200 OK | Menu S-SPARC AI & Eco-Metrics terpasang |
| 3 | S-SPARC Chat via SSO | `http://127.0.0.1:8080/ssparc/chat.php` | 200 OK | Terhubung langsung tanpa login ulang |
| 4 | S-SPARC Gamification | `http://127.0.0.1:8080/ssparc/gamification.php` | 200 OK | Radar chart dan Dynamic Threshold aktif |
| 5 | S-SPARC Sustainability | `http://127.0.0.1:8080/ssparc/environmental_impact.php` | 200 OK | Metrik $Wh$, Carbon, dan Air terhubung |
| 6 | S-SPARC Leaderboard | `http://127.0.0.1:8080/ssparc/leaderboard.php` | 200 OK | Peringkat efisiensi token terverifikasi |
| 7 | FastAPI `/generate-code` | `http://127.0.0.1:5000/generate-code` | 200 OK | Header `X-User-ID` valid & job ter-dispatch |
| 8 | OpenAPI / Swagger v3.0.0 | `http://127.0.0.1:5000/openapi.json` | 200 OK | 28 endpoint terdokumentasi penuh |
