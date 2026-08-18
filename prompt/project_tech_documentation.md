# Dokumentasi Teknis Proyek S-SPARC AI

Dokumen ini menjelaskan struktur teknis proyek secara menyeluruh berdasarkan kode, migrasi database, layanan evaluator, dan frontend PHP yang ada di workspace ini. Fokusnya adalah pada arsitektur, alur eksekusi, API, skema data, konfigurasi runtime, dan cara menjalankan sistem secara lokal maupun produksi.

## 1. Ringkasan Proyek

S-SPARC AI adalah platform pembelajaran berbasis AI untuk pemrograman dan sustainability. Proyek ini menggabungkan:

- Backend utama berbasis Flask untuk autentikasi, chat, retrieval, gamification, dan pelaporan dampak lingkungan.
- Frontend PHP untuk antarmuka pengguna, dashboard, login, chat, dan halaman analitik.
- Service evaluator terpisah berbasis FastAPI untuk memeriksa kualitas isi knowledge base `code_embeddings`.
- Skema database MySQL/MariaDB yang memuat pengguna, kursus, asesmen, histori chat, token usage, job GPT, dan log dampak lingkungan.

Tujuan arsitekturalnya bukan hanya menghasilkan jawaban kode, tetapi juga menjaga kualitas knowledge base, mengelola penggunaan token, dan melacak dampak energi, karbon, serta air.

## 2. Struktur Tingkat Tinggi

Komponen utama repo ini adalah:

- `app.py`: aplikasi Flask utama.
- `run_production.py`: runner produksi berbasis Waitress untuk penggunaan umum.
- `run_production_server.py`: runner produksi yang dioptimalkan untuk server dengan GPU.
- `frontend/`: antarmuka PHP.
- `code_evaluator_service/`: service evaluasi kualitas code snippet.
- `db_migrations/`: migrasi dan bootstrap skema database.
- `docker-compose.yml`: layanan pendukung lokal MySQL dan phpMyAdmin.

Sistem ini bersifat hybrid: backend Python menangani logika AI dan API, sedangkan frontend PHP bertugas sebagai lapisan presentasi dan integrasi BotMan.

## 3. Alur Eksekusi Sistem

Alur utama aplikasi dapat diringkas sebagai berikut:

1. Pengguna login melalui frontend PHP.
2. Frontend menyimpan sesi Flask yang dipakai pada request berikutnya.
3. Pengguna mengirim prompt coding melalui chat.
4. Backend melakukan pencarian retrieval ke knowledge base dan cache semantic similarity.
5. Jika cocok, backend mengembalikan jawaban berbasis retrieval.
6. Jika tidak cocok, prompt dapat diteruskan ke job GPT background atau endpoint synchronous sesuai mode yang dipakai.
7. Setiap proses dapat mencatat token usage, histori chat, dan dampak lingkungan.
8. Secara terpisah, evaluator service mengevaluasi isi `code_embeddings` untuk mendeteksi duplikasi, kualitas rendah, dan anomali.

## 4. Backend Flask

### 4.1 Peran Utama

Backend Flask adalah pusat logika bisnis. Dari `app.py`, backend menangani:

- autentikasi dan otorisasi,
- daftar kursus dan asesmen,
- gamification dan leaderboard,
- endpoint lingkungan dan token usage,
- job GPT asynchronous,
- generation code,
- status pengecekan job,
- administrasi dashboard dan ekspor CSV.

### 4.2 Pola Keamanan

Terdapat dua decorator otorisasi utama:

- `require_login`: hanya untuk user yang sudah punya `user_id` di session.
- `require_admin`: memeriksa flag `is_admin` pada tabel `users` dan menolak akses jika tidak admin.

Pendekatan ini dipakai pada endpoint administratif seperti statistik lingkungan, CSV export, dan dashboard admin.

### 4.3 Endpoint Utama

Endpoint yang terdeteksi pada backend saat ini adalah:

#### Administratif

- `GET /admin-environmental-stats`
- `GET /admin-environmental-csv`
- `GET /admin-assessment-csv`
- `GET /admin-assessment-histogram`
- `GET /admin-dashboard`
- `POST /refresh-retrieval-cache`
- `POST /compute-assessment-points`

#### User / Auth

- `POST /register`
- `POST /login`
- `POST /logout`
- `GET /whoami`
- `POST /change-password`

#### Domain Learning

- `GET /courses`
- `GET /assessments`
- `GET /gamification`
- `GET /assessment-leaderboard`
- `GET /course-leaderboard`

#### Token Usage dan Sustainability

- `GET /token-usage-daily`
- `GET /token-usage-breakdown`
- `GET /impact-summary`

#### AI / GPT Flow

- `POST /generate-code`
- `POST /generate-code-sync`
- `POST /enqueue-gpt`
- `GET /check-status/<job_id>`

### 4.4 Fungsi Teknis Penting

Beberapa pola teknis yang terlihat di backend:

- penggunaan Flask session untuk identitas pengguna,
- koneksi database MySQL melalui `pymysql`,
- cache retrieval dan model embedding,
- integrasi OpenAI untuk fallback atau job GPT,
- pengukuran environmental impact memakai CodeCarbon jika tersedia,
- pembatasan request dengan `flask-limiter`,
- CORS diaktifkan untuk mendukung integrasi frontend.

### 4.5 Pemanggilan OpenAI API

Pemanggilan OpenAI sebenarnya sudah ada di kode, tetapi sebelumnya belum dijelaskan secara eksplisit di dokumen. Pola yang dipakai saat ini adalah:

- Backend utama mengumpulkan beberapa key dari `OPENAI_API_KEY_1`, `OPENAI_API_KEY_2`, `OPENAI_API_KEY_3`, lalu fallback ke `OPENAI_API_KEY`.
- Backend membuat pool client dengan `openai.OpenAI(api_key=...)` dan melakukan rotasi key secara round-robin lewat helper `_get_openai_client()`.
- Request ke OpenAI dilakukan lewat `client.chat.completions.create(...)`.
- Di jalur generasi kode, backend mengirim `model=OPENAI_MODEL`, `messages`, `temperature`, dan `max_completion_tokens`.
- Di service evaluator, kelas `LLMJudge` membuat `OpenAI(api_key=settings.llm_api_key, timeout=30.0, max_retries=2)` dan memanggil `self.client.chat.completions.create(...)` dengan `response_format={"type": "json_object"}` agar output bisa diparsing sebagai JSON.
- Jika API key tidak tersedia atau request gagal, evaluator turun ke heuristic fallback, sehingga sistem tetap berjalan tanpa OpenAI.

Secara ringkas, proyek ini tidak memakai satu pemanggilan tunggal, tetapi dua jalur utama: satu untuk generasi respons di backend Flask dan satu untuk penilaian kualitas di evaluator service.

### 4.6 Mode Produksi

Ada dua runner produksi:

- `run_production.py`: runner umum berbasis Waitress, cocok untuk deployment Windows/Linux tanpa optimasi GPU khusus.
- `run_production_server.py`: runner yang mengaktifkan optimasi GPU, preload model, dan beberapa worker GPT background.

Runner produksi melakukan beberapa hal penting:

- menjalankan worker background untuk job GPT,
- memuat model embedding lebih awal,
- menjaga throughput request I/O tinggi,
- menyiapkan cleanup untuk pencatatan emisi global.

## 5. Frontend PHP

### 5.1 Peran Frontend

Frontend ada di folder `frontend/` dan berfungsi sebagai lapisan UI. Ia tidak menggantikan backend, tetapi memanggil API Flask dan menyimpan session cookie yang dibutuhkan backend.

### 5.2 Halaman yang Ada

Halaman frontend yang tersedia saat ini meliputi:

- `index.php`
- `login.php`
- `register.php`
- `logout.php`
- `dashboard.php`
- `chat.php`
- `botman.php`
- `features.php`
- `about.php`
- `courses.php`
- `assessments.php`
- `gamification.php`
- `gamification_dashboard.php`
- `admin_dashboard.php`
- `assessment_leaderboard.php`
- `course_leaderboard.php`
- `change_password.php`
- `token_usage_daily.php`
- `token_usage_breakdown.php`
- `sustainability.php`

### 5.3 Konfigurasi Frontend

File `frontend/config.php` melakukan:

- inisialisasi sesi PHP yang lebih aman,
- penyimpanan session di folder proyek bila writable,
- pengaturan cookie `httponly` dan `samesite`,
- helper `backend_base()` untuk menentukan URL backend dari `FLASK_BASE_URL`.

### 5.4 Integrasi BotMan

Frontend chat memakai BotMan untuk mengirim prompt ke backend. Alurnya adalah:

1. pengguna mengetik prompt di `chat.php`,
2. request dikirim ke `botman.php`,
3. backend dipanggil melalui `/generate-code`,
4. jika backend mengembalikan `job_id`, frontend melakukan polling ke `/check-status/<job_id>`,
5. hasil atau error ditampilkan ke pengguna.

## 6. Code Evaluator Service

### 6.1 Tujuan

Service ini memeriksa kualitas isi tabel `code_embeddings`. Fungsinya adalah menjaga knowledge base tetap bersih, relevan, dan tidak berisi duplikasi atau konten lemah.

### 6.2 Teknologi

Service ini menggunakan:

- FastAPI,
- Uvicorn,
- APScheduler,
- sentence-transformers,
- scikit-learn,
- radon,
- OpenAI API bila tersedia,
- PyMySQL dan dotenv untuk koneksi database.

### 6.3 Endpoint Service

Endpoint evaluator saat ini adalah:

- `GET /health`
- `GET /run-evaluation`
- `GET /run-evaluation?background=true`
- `GET /stats`

### 6.4 Fungsi Evaluasi

Evaluator melakukan beberapa langkah:

- mengambil data dari `code_embeddings` secara batch,
- mendeteksi bahasa pemrograman secara heuristik,
- menjalankan analisis statik,
- menghitung semantic similarity antara prompt dan code,
- menggunakan LLM-as-a-Judge bila API key tersedia,
- mendeteksi duplikasi dengan hashing,
- mendeteksi anomali dengan model statistik,
- membuat backup JSON sebelum penghapusan,
- menghasilkan report evaluasi.

### 6.5 Jadwal Otomatis

Service dijalankan otomatis setiap Minggu pukul 03:00 AM melalui scheduler.

### 6.6 Catatan Data

Repo ini menunjukkan bahwa evaluator sudah mengakomodasi skema lama maupun baru. Pada implementasi saat ini, kolom `code` pada `code_embeddings` dapat di-alias sebagai `generated_code` secara internal jika diperlukan.

## 7. Database dan Skema Data

### 7.1 Database Utama

Database utama berbasis MySQL/MariaDB. Skema awal dan migrasi incremental disimpan di:

- `db_semantic_vfinal.sql`
- `db_migrations/`

### 7.2 Tabel Inti

Tabel utama yang teridentifikasi adalah:

- `users`
- `user_points`
- `courses`
- `assessments`
- `user_courses`
- `chat_history`
- `session_tokens`
- `gpt_jobs`
- `code_embeddings`
- `environmental_impact_logs`
- `local_carbon_logs`
- `user_points_assessment`

### 7.3 Fungsi Tabel

- `users`: identitas pengguna dan hash password.
- `user_points`: total poin agregat pengguna.
- `courses`: data kelas atau mata pelajaran.
- `assessments`: data asesmen yang terkait ke course.
- `user_courses`: relasi user-ke-course beserta role.
- `chat_history`: histori percakapan per user dan session.
- `session_tokens`: log penggunaan token per sesi dan per asesmen.
- `gpt_jobs`: antrian job generasi kode.
- `code_embeddings`: knowledge base prompt-code dan embedding.
- `environmental_impact_logs`: log energi, karbon, dan air untuk job AI.
- `local_carbon_logs`: log karbon lokal atau server.
- `user_points_assessment`: akumulasi poin per asesmen.

### 7.4 Migrasi yang Penting

Beberapa migrasi yang terlihat menambahkan:

- relasi user-course,
- field asesmen tambahan seperti `end_date` dan `final_points`,
- kolom `is_admin` pada `users`,
- seed admin default.

## 8. Konfigurasi Environment

### 8.1 Konfigurasi Backend Utama

Environment variables utama yang dipakai backend adalah:

- `MYSQL_HOST`
- `MYSQL_PORT`
- `MYSQL_USER`
- `MYSQL_PASSWORD`
- `MYSQL_DB`
- `FLASK_SECRET_KEY`
- `FLASK_HOST`
- `FLASK_PORT`
- `WAITRESS_THREADS`
- `CHAT_HISTORY_LIMIT`

### 8.2 Konfigurasi Frontend

- `FLASK_BASE_URL`

### 8.3 Konfigurasi Evaluator

- `EVALUATOR_PORT`
- `EVALUATOR_BATCH_SIZE`
- `EVALUATOR_SEMANTIC_THRESHOLD`
- `EVALUATOR_REVIEW_SCORE_THRESHOLD`
- `EVALUATOR_FINAL_SCORE_THRESHOLD`
- `EVALUATOR_EMBEDDING_MODEL`
- `EVALUATOR_LLM_MODEL`
- `EVALUATOR_OPENAI_API_KEY`
- `EVALUATOR_TIMEZONE`
- `EVALUATOR_DRY_RUN`

### 8.4 Konfigurasi Produksi GPU

`run_production_server.py` juga memanfaatkan variabel seperti:

- `CUDA_VISIBLE_DEVICES`
- `PYTORCH_CUDA_ALLOC_CONF`
- `OMP_NUM_THREADS`
- `MKL_NUM_THREADS`
- `DEBUG_CUDA`
- `GPT_WORKERS`

## 9. Dependensi Runtime

### 9.1 Backend Flask

Dependensi utama backend mencakup:

- Flask
- flask_cors
- flask-limiter
- python-dotenv
- sentence-transformers
- transformers
- torch
- langdetect
- faiss-cpu
- openai
- sqlalchemy
- pymysql
- joblib
- numpy
- pandas
- tiktoken
- codecarbon
- waitress

### 9.2 Evaluator Service

Dependensi evaluator service mencakup:

- fastapi
- uvicorn[standard]
- apscheduler
- pymysql
- sentence-transformers
- transformers
- torch
- numpy
- radon
- scikit-learn
- openai
- cryptography

## 10. Deployment dan Operasi

### 10.1 Lokal dengan Docker

File `docker-compose.yml` saat ini menyediakan layanan pendukung berikut:

- MySQL 8,
- phpMyAdmin.

Artinya, aplikasi utama masih dijalankan dari runtime Python dan PHP lokal, sementara Docker Compose dipakai untuk database dan tooling administrasi.

### 10.2 Menjalankan Backend

Mode umum:

```powershell
python run_production.py
```

Mode server GPU:

```powershell
python run_production_server.py
```

### 10.3 Menjalankan Frontend

```powershell
cd frontend
composer install
php -S localhost:8000
```

### 10.4 Menjalankan Evaluator

```powershell
pip install -r code_evaluator_service/requirements.txt
python -m code_evaluator_service
```

atau:

```powershell
python -m uvicorn code_evaluator_service.evaluator_app:app --host 0.0.0.0 --port 5055
```

## 11. Catatan Operasional

- Proyek ini menggabungkan artefak produksi dan artefak eksperimen dalam satu workspace, jadi pembacaan konteks harus spesifik per folder.
- Model lokal dan output evaluator dapat berukuran besar, sehingga tidak semua aset disimpan di Git.
- Sistem memakai cache retrieval dan worker background untuk menjaga request user tetap responsif.
- Endpoint admin bergantung pada kolom `is_admin` pada tabel `users`.
- Dokumentasi dan migrasi database perlu dijalankan berurutan agar skema cocok dengan kode terbaru.

## 12. Area yang Perlu Di-Enhance

Berdasarkan kode yang ada, ada beberapa area yang memang masih perlu ditingkatkan agar pengalaman AI-nya menjadi chatbot yang lebih matang, bukan sekadar assistant generatif satu arah:

- Backend perlu dibuat benar-benar dua arah, dengan konteks percakapan yang dipertahankan antar-turn, bukan hanya menerima prompt lalu mengembalikan jawaban satu kali.
- Perlu context window yang dikelola eksplisit, misalnya ringkasan percakapan, sliding window turns, atau memori jangka pendek/jangka panjang agar respons bisa merujuk ke chat sebelumnya.
- Harness prompting perlu disempurnakan supaya format input-output lebih konsisten, lebih tahan terhadap prompt injection, dan lebih cocok untuk percakapan multi-turn.
- Search/retrieval bisa ditingkatkan dengan memadukan dense embedding dan sparse embedding sebagai opsi hybrid search. Ini biasanya lebih kuat untuk kombinasi query natural language, keyword teknis, dan kode.
- Perlu AI gateway untuk memusatkan seluruh akses model AI, sehingga routing model, logging, rate limit, observabilitas, fallback, dan governance tidak tersebar di banyak tempat.
- Prompt registry perlu diperkuat supaya template prompt, versi prompt, metadata model, dan tujuan penggunaan tersimpan terpusat serta mudah diaudit.
- API saat ini masih terasa seperti API assistant berbasis task generation. Untuk chatbot yang benar-benar mengingat percakapan, endpoint perlu membawa identitas sesi, history ringkas, dan state percakapan yang konsisten.

Implikasinya, `generate-code` dan jalur terkait sebaiknya diposisikan ulang sebagai bagian dari orkestrasi chatbot, bukan sebagai satu-satunya titik interaksi AI.

## 13. Referensi File Penting

- [README.md](../README.md)
- [DEPLOYMENT_GUIDE.md](../DEPLOYMENT_GUIDE.md)
- [QUICK_START_PRODUCTION.md](../QUICK_START_PRODUCTION.md)
- [PERFORMANCE_OPTIMIZATION.md](../PERFORMANCE_OPTIMIZATION.md)
- [code_evaluator_service/README.md](../code_evaluator_service/README.md)
- [frontend/README.md](../frontend/README.md)
- [db_migrations/README.md](../db_migrations/README.md)

## 14. Kesimpulan

Secara teknis, proyek ini adalah platform AI edukasi yang cukup lengkap: backend Flask untuk domain learning dan generasi kode, frontend PHP untuk antarmuka, evaluator service untuk quality control knowledge base, dan database MySQL sebagai pusat penyimpanan. Komponen sustainability dan gamification membuat sistem ini lebih dari sekadar chatbot, karena ia juga melacak dampak penggunaan AI dan perilaku belajar pengguna.

Jika dokumentasi ini akan dijadikan artefak resmi proyek, langkah berikutnya yang paling berguna adalah memecahnya menjadi beberapa dokumen operasional terpisah seperti API reference, deployment runbook, dan data dictionary.
