# Frontend PHP + BotMan

Halaman PHP untuk register, login, dan chatbot yang terhubung ke backend Flask (`app.py`) melalui BotMan.

## Struktur

- `register.php` — form registrasi, kirim POST JSON ke `/register`.
- `login.php` — form login, simpan cookie sesi Flask (`Set-Cookie`) ke `$_SESSION['flask_cookie']` untuk dipakai di permintaan berikutnya.
- `chat.php` — UI chat Tailwind + JS, kirim pesan ke `botman.php`.
- `botman.php` — endpoint BotMan Web Driver; meneruskan prompt ke `/generate-code`, lakukan polling `/check-status/{job_id}` bila antrian.
- `composer.json` — dependensi `botman/botman`, `botman/driver-web`, `guzzlehttp/guzzle`.

## Menjalankan

1. Instal dependensi PHP:
   ```bash
   cd frontend
   composer install
   ```
2. Pastikan backend Flask aktif, default `http://localhost:5000`. Atur env jika berbeda:
   ```bash
   set FLASK_BASE_URL=http://localhost:5000
   ```
3. Jalankan server PHP (contoh):
   ```bash
   php -S localhost:8000
   ```
4. Akses:
   - `http://localhost:8000/register.php`
   - `http://localhost:8000/login.php`
   - `http://localhost:8000/chat.php`

## Alur

- Login menyimpan cookie sesi Flask ke PHP session. Chatbot membutuhkan cookie ini agar backend mengenali `user_id` pada `/generate-code`.
- BotMan mengirim prompt ke `/generate-code`. Jika backend mengembalikan `job_id` (mode antrian), BotMan akan mem-poll `/check-status/{job_id}` hingga selesai (batas ~24 detik) lalu menampilkan kode atau error.
- Respons backend (error/rate limit) diteruskan apa adanya ke pengguna.

## Catatan

- Tailwind di-load melalui CDN untuk kesederhanaan. Tidak ada build step.
- Jika backend memakai domain/port lain, set `FLASK_BASE_URL` di environment web server PHP.
