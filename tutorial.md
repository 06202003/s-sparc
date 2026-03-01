# Tutorial Setup Mini Server (Flask + PHP) di PC Windows Lab

---

## 1. Persiapan Awal

- Pastikan **Python 3.13 up** dan **pip** sudah terinstall.
- Pastikan **PHP** sudah terinstall (untuk frontend PHP, bisa pakai PHP built-in server).
- Download source code ke folder, misal: `D:\TESIS\openai`

---

## 2. Setup Backend (Flask)

### a. Install Dependensi Python

```sh
cd D:\TESIS\openai
pip install -r requirements.txt
```

### b. Jalankan Flask agar Bisa Diakses Jaringan

**RECOMMENDED - Mode Development dengan Threading (Concurrent Users):**

```sh
# Local development (hanya PC ini)
python app.py --port 5000

# Atau untuk akses dari komputer lain di jaringan
python app.py --host 0.0.0.0 --port 5000
```

**Fitur:**

- Threading enabled (multiple users bisa akses bersamaan)
- Background worker untuk GPT job processing (auto-start)
- User 1 generate code TIDAK akan block User 2 login ✅

**Alternative - Mode Production dengan Waitress:**

```sh
# Local production
python run_production.py

# Atau untuk akses dari komputer lain di jaringan
FLASK_HOST=0.0.0.0 python run_production.py
```

**Fitur:**

- Production-grade WSGI server
- 8 concurrent threads (bisa handle 50+ users)
- Better performance & stability

**Catatan:**

- `--host 0.0.0.0` = agar bisa diakses dari semua IP di jaringan
- `--port 5000` bisa diganti sesuai kebutuhan (contoh: `--port 5001`)
- Jika ingin manual worker, tambahkan flag `--no-worker` dan jalankan worker di terminal terpisah: `python app.py --worker`

### c. Setting Windows Firewall (Backend)

1. Buka **Windows Defender Firewall with Advanced Security**.
2. Pilih **Inbound Rules** → klik **New Rule...**.
3. Pilih **Port** → Next.
4. Pilih **TCP**, masukkan port (misal 5000) → Next.
5. Pilih **Allow the connection** → Next.
6. Pilih **Private** (atau sesuai kebutuhan) → Next.
7. Beri nama, misal: `Flask 5000` → Finish.

8. (Rekomendasi untuk banyak subnet):
   - Jika subnet lab tidak berurutan (misal 192.168.101.x, 192.168.120.x, dst),
     di tab **Scope** pada rule firewall, tambahkan semua IP range subnet yang dipakai, atau cukup masukkan:
     - `192.168.0.0/255.255.0.0` (mengizinkan semua 192.168.x.y)
   - Ini akan membuat server bisa diakses dari semua lab tanpa perlu tahu x dan y satu per satu.
   - Pastikan admin jaringan mengaktifkan routing antar subnet agar semua lab bisa akses server.

### d. Cek Akses Backend dari Client

Dari PC lain di lab/campus, buka browser:

```
http://<IP-mini-server>:5000
```

Contoh: `http://192.168.101.10:5000`

---

## 3. Setup Frontend (PHP) dengan Built-in Server (Alternatif tanpa XAMPP)

### a. Pastikan PHP sudah terinstall

- Download PHP Windows: https://windows.php.net/download/
- Ekstrak dan tambahkan folder PHP ke PATH environment variable.
- Cek versi di Command Prompt:
  ```sh
  php -v
  ```

### b. Jalankan Built-in Server PHP

1. Buka Command Prompt, masuk ke folder frontend:
   ```sh
   cd D:\TESIS\openai\frontend
   php -S 0.0.0.0:8000
   ```
2. Server akan berjalan di port 8000 dan bisa diakses dari jaringan.

### c. Konfigurasi Koneksi Backend di Frontend

1. Edit file konfigurasi (misal `config.php` di `frontend`).
2. Pastikan URL/backend API mengarah ke IP mini server dan port Flask, contoh:
   ```php
   // frontend/config.php
   $backend_url = "http://192.168.101.10:5000";
   ```
   Ganti `192.168.101.10` dengan IP mini server kamu.

### d. Buka Firewall untuk Port 8000 (Frontend)

1. Buka Windows Firewall, buat rule baru untuk port 8000 (TCP) seperti langkah backend.

### e. Cek Akses Frontend dari Client

Buka browser di PC lain:

```
http://<IP-mini-server>:8000
```

Contoh: `http://192.168.101.10:8000`

---

## 4. Tips Keamanan & Best Practice

- **Jangan aktifkan debug mode** di production (`debug=False`).
- **Gunakan autentikasi/login** untuk endpoint penting.
- **Validasi semua input user** (hindari SQL injection/XSS).
- **Update Python, library, dan XAMPP** secara berkala.
- **Pantau log aplikasi** dan Windows Event Viewer secara rutin.
- **Batasi firewall** hanya untuk subnet kampus jika memungkinkan.

---

Sekarang backend (Flask) dan frontend (PHP) sudah bisa diakses dari semua lab dengan setup yang lebih aman dan rapi!
