# Panduan Penyiapan Proyek S-SPARC (Versi Clean / Share)

Folder ini adalah versi ringkas dari proyek S-SPARC (ukuran dipangkas dari ~12.3 GB menjadi ~660 MB) yang telah dibersihkan dari file cache, virtualenv lokal, zip backup, dan weights model AI raksasa.

---

## 🚀 Langkah Penyiapan Awal untuk Developer Baru

### 1. Penyiapan Virtual Environment Python
Buat dan aktifkan virtual environment Python baru:
```bash
python -m venv .venv
# Di Windows PowerShell:
.\.venv\Scripts\Activate.ps1
```

Install seluruh dependencies python:
```bash
pip install -r requirements.txt
```

---

## 🗄️ Restore Database
1. Skema & migrasi lengkap tersedia di folder `db_migrations/`.
2. File dump terkompresi tersedia di root folder (`db_semantic_vfinal.sql.gz.part01..03`).
3. Untuk aplikasi Estrange, file SQL tersedia di `estrange/estrange_v7.sql`.

---

## 🤖 Pretrained Model AI (Opsional)
Folder `pretrained_model/` tidak diikutsertakan. Model HuggingFace akan di-download otomatis saat pertama kali script dijalankan jika dibutuhkan.
