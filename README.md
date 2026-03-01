## Quasi-Experiment Tasks (Programming Class, One-Shot, Easy)

Jawab tiga contoh berikut dalam satu kali prompt panjang per soal. Fokus pada pemahaman dasar algoritma/struktur data dan desain sederhana; jika menyertakan cuplikan kode runnable, gunakan bahasa populer apa saja.

- Waktu pengerjaan: maks. 25 menit per soal (total ±75 menit) sebagaimana ujian praktikum pemrograman.

- Soal 1: Linked List Sederhana (Singly)
  - Deskripsi: Antrean tugas staf laboratorium komputer (pasang software praktikum, cek kabel jaringan, bersihkan PC, reset akun lab). Gunakan `Singly Linked List` dengan data awal prioritas `[3, 1, 4, 1, 5]` (angka makin kecil = lebih mendesak). Implementasikan `insertHead`, `insertTail`, `deleteValue` (hapus kemunculan pertama), `find`, `toArray`. Jalankan urutan: `insertHead(2)`, `deleteValue(1)`, `insertTail(9)`, `find(4)`. Jelaskan perubahan pointer `next`, hasil `find(4)`, dan bentuk akhir list.
  - Ekspektasi Input: Data awal `[3,1,4,1,5]` dan urutan operasi di atas.
  - Ekspektasi Output: Ringkasan struktur `Node`/list, cuplikan kode runnable, jejak operasi + perubahan pointer, hasil `find(4)` (ketemu/tidak), bentuk akhir (array), kompleksitas waktu per operasi (`O(1)` insert head, `O(n)` delete/find, dst.).
  - Evaluasi: Ketepatan operasi, kejelasan pointer, konsistensi hasil akhir, penyebutan kompleksitas.

- Soal 2: Stack Sederhana (LIFO)
  - Deskripsi: Riwayat undo saat mengisi form pengajuan cuti kuliah di aplikasi kampus. Implementasi `Stack` berbasis array/list. Jalankan `push(A)` (isi awal), `push(B)` (edit pertama), `push(C)` (edit kedua), `pop()` (undo 1), `pop()` (undo 2), `push(D)` (edit baru). Definisikan `push`, `pop`, `top/peek`, dan jelaskan kapan LIFO dipakai (undo/redo).
  - Ekspektasi Input: Urutan operasi di atas dan arti simbol A/B/C/D (versi isi form).
  - Ekspektasi Output: Daftar nilai hasil `pop` berurutan, `top` akhir, isi stack (sebutkan urutan bawah→atas atau sebaliknya), cuplikan kode runnable, ringkas kompleksitas (`push/pop O(1)`), kalimat singkat fungsi stack pada skenario sehari-hari.
  - Evaluasi: Ketepatan LIFO, konsistensi urutan, kejelasan representasi, kecukupan penjelasan fungsi stack.

- Soal 3: POS Sederhana (Kasir Mini-Market)
  - Deskripsi: Simulasi Point-of-Sale minimarket kampus. Input: daftar item (nama, qty, harga satuan), diskon per item opsional, pajak 10% atas subtotal. Tulis kode runnable yang menghitung subtotal, total diskon, pajak, grand total, menerima bayar tunai, dan menghitung kembalian. Sertakan ringkasan struk.
  - Ekspektasi Input: Contoh daftar item `[{'Teh Botol', qty:2, harga:5000, diskon:0}, {'Roti', qty:1, harga:12000, diskon:2000}, {'Pulpen', qty:3, harga:3000, diskon:0}]`, pajak 10%, bayar tunai mis. 50000.
  - Ekspektasi Output: (1) Summary struk: subtotal, total diskon, pajak, grand total, bayar, kembalian. (2) Cuplikan kode runnable. (3) Penjelasan singkat langkah hitung (traverse item → subtotal → diskon → pajak → total → kembalian). Boleh tabel/bullet.
  - Evaluasi: Ketepatan perhitungan, kejelasan struk ringkas, kode dapat dijalankan, penjelasan langkah.

Catatan Pengumpulan

- Jawaban ringkas dan terstruktur (bullet/tabel). Jika ada kode, buat singkat dan fokus pada operasi inti.

Rubrik

- Ketepatan konsep & hasil (60%), Kejelasan penyajian (25%), Penalaran singkat/kompleksitas (15%).

# S-SPARC AI

AI-powered learning assistant for sustainability and environmental awareness, combining a Python backend with a PHP-based web frontend and multilingual NLP models.

## Overview

This project provides:

- A Python backend API (Flask) for handling chat, semantic similarity, and recommendation logic.
- A PHP frontend (BotMan / Laravel-style structure) for the user-facing dashboard, chat interface, and gamification features.
- Pre-trained multilingual sentence-transformer and translation models (ignored in Git) for semantic search and evaluation.
- SQL migration files for managing the application database schema.

## Project Structure

- `app.py` and related scripts: main Python backend and helper scripts.
- `frontend/`: PHP web frontend (chat UI, dashboards, authentication, etc.).
- `db_migrations/`: SQL migration scripts for setting up and updating the database.
- `semantic_similarity/`: utilities and notebooks for embedding generation and relevance evaluation.
- `pretrained_model/`: local copies of large NLP models (excluded from Git via `.gitignore`).
- `docker-compose.yml`: optional Docker-based setup for services (e.g., database / backend).

## Requirements

- Python 3.13+ (recommended)
- `pip` for Python package management
- PHP 8+ and Composer (for the frontend)
- A SQL database (e.g., MySQL/MariaDB or PostgreSQL) configured in the frontend config

## Backend Setup (Python)

From the project root:

```bash
# 1. Create and activate virtual environment (example for Windows PowerShell)
python -m venv .venv
.venv\\Scripts\\Activate

# 2. Install dependencies
pip install -r requirements.txt

# 3. Run the backend (RECOMMENDED - with threading & auto-worker)
python app.py --port 5000
```

**To access from other computers on your network, use:**

```bash
python app.py --host 0.0.0.0 --port 5000
```

**Important:** The command above enables:

- ✅ **Threading** for concurrent requests (multiple users can access simultaneously)
- ✅ **Background worker** for GPT job processing (auto-start)
- ✅ User 1 generating code will NOT block User 2 from logging in

**Alternative - Production Mode (for 50+ concurrent users):**

```bash
python run_production.py
```

By default, the backend will expose a local HTTP API on port 5000. You should see:

```
[INFO] Starting background GPT job worker thread...
[INFO] Starting Flask server on 0.0.0.0:5000 with threading enabled...
[WORKER] GPT job worker started.
```

**For more deployment options, see:** [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) or [QUICK_FIX_GUIDE.md](QUICK_FIX_GUIDE.md)

## Frontend Setup (PHP)

From the project root:

```bash
cd frontend

# 1. Install PHP dependencies
composer install

# 2. Configure environment
#   - Edit config.php (database connection, API base URL to Python backend, etc.)

# 3. Run via local PHP server (example)
php -S localhost:8000
```

Then open `http://localhost:8000` in your browser.

## Database Migrations

The `db_migrations/` folder contains SQL scripts such as:

- `001_add_user_courses.sql`
- `002_add_env_impact_filters.sql`

Apply them in order to your database using your preferred SQL client or DB migration tooling.

## Docker (optional)

If you prefer containers, review and adjust `docker-compose.yml`, then run:

```bash
docker compose up --build
```

This can be extended to orchestrate the backend, database, and any additional services.

## Pre-trained Models

Large NLP models are stored locally under `pretrained_model/` and are **not** tracked by Git. To run the full pipeline, download or place the required models into that directory according to your environment and update any paths in the Python code if needed.

## Contributing

- Use feature branches for changes.
- Keep large model files and datasets out of Git (they are already ignored via `.gitignore`).
- Prefer small, focused pull requests with clear descriptions.

## Quasi-Experiment Tasks (Programming Class, One-Shot, Easy)

Jawab tiga contoh berikut dalam satu kali prompt panjang per soal. Fokus pada pemahaman dasar algoritma/struktur data dan desain sederhana; jika menyertakan cuplikan kode runnable, gunakan bahasa populer apa saja.

- Waktu pengerjaan: maks. 25 menit per soal (total ±75 menit) sebagaimana ujian praktikum pemrograman.

- Soal 1: Linked List Sederhana (Singly)
  - Deskripsi: Antrean tugas staf laboratorium komputer (pasang software praktikum, cek kabel jaringan, bersihkan PC, reset akun lab). Gunakan `Singly Linked List` dengan data awal prioritas `[3, 1, 4, 1, 5]` (angka makin kecil = lebih mendesak). Implementasikan `insertHead`, `insertTail`, `deleteValue` (hapus kemunculan pertama), `find`, `toArray`. Jalankan urutan: `insertHead(2)`, `deleteValue(1)`, `insertTail(9)`, `find(4)`. Jelaskan perubahan pointer `next`, hasil `find(4)`, dan bentuk akhir list.
  - Ekspektasi Input: Data awal `[3,1,4,1,5]` dan urutan operasi di atas.
  - Ekspektasi Output: Ringkasan struktur `Node`/list, cuplikan kode runnable, jejak operasi + perubahan pointer, hasil `find(4)` (ketemu/tidak), bentuk akhir (array), kompleksitas waktu per operasi (`O(1)` insert head, `O(n)` delete/find, dst.).
  - Evaluasi: Ketepatan operasi, kejelasan pointer, konsistensi hasil akhir, penyebutan kompleksitas.

- Soal 2: Stack Sederhana (LIFO)
  - Deskripsi: Riwayat undo saat mengisi form pengajuan cuti kuliah di aplikasi kampus. Implementasi `Stack` berbasis array/list. Jalankan `push(A)` (isi awal), `push(B)` (edit pertama), `push(C)` (edit kedua), `pop()` (undo 1), `pop()` (undo 2), `push(D)` (edit baru). Definisikan `push`, `pop`, `top/peek`, dan jelaskan kapan LIFO dipakai (undo/redo).
  - Ekspektasi Input: Urutan operasi di atas dan arti simbol A/B/C/D (versi isi form).
  - Ekspektasi Output: Daftar nilai hasil `pop` berurutan, `top` akhir, isi stack (sebutkan urutan bawah→atas atau sebaliknya), cuplikan kode runnable, ringkas kompleksitas (`push/pop O(1)`), kalimat singkat fungsi stack pada skenario sehari-hari.
  - Evaluasi: Ketepatan LIFO, konsistensi urutan, kejelasan representasi, kecukupan penjelasan fungsi stack.

- Soal 3: POS Sederhana (Kasir Mini-Market)
  - Deskripsi: Simulasi Point-of-Sale minimarket kampus. Input: daftar item (nama, qty, harga satuan), diskon per item opsional, pajak 10% atas subtotal. Tulis kode runnable yang menghitung subtotal, total diskon, pajak, grand total, menerima bayar tunai, dan menghitung kembalian. Sertakan ringkasan struk.
  - Ekspektasi Input: Contoh daftar item `[{'Teh Botol', qty:2, harga:5000, diskon:0}, {'Roti', qty:1, harga:12000, diskon:2000}, {'Pulpen', qty:3, harga:3000, diskon:0}]`, pajak 10%, bayar tunai mis. 50000.
  - Ekspektasi Output: (1) Summary struk: subtotal, total diskon, pajak, grand total, bayar, kembalian. (2) Cuplikan kode runnable. (3) Penjelasan singkat langkah hitung (traverse item → subtotal → diskon → pajak → total → kembalian). Boleh tabel/bullet.
  - Evaluasi: Ketepatan perhitungan, kejelasan struk ringkas, kode dapat dijalankan, penjelasan langkah.

Catatan Pengumpulan

- Jawaban ringkas dan terstruktur (bullet/tabel). Jika ada kode, buat singkat dan fokus pada operasi inti.

Rubrik

- Ketepatan konsep & hasil (60%), Kejelasan penyajian (25%), Penalaran singkat/kompleksitas (15%).

## License

Add your preferred license here (e.g., MIT, Apache-2.0) or link to a `LICENSE` file if you create one.
