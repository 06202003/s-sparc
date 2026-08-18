# Halaman Pembukaan PPT: Pengujian Semantic Similarity

---

## SLIDE 1: TITLE SLIDE

### Judul Utama (size: 54pt, bold, center)

**Pengujian Sistem Semantic Similarity**

### Subtitle (size: 32pt, center)

Evaluasi Kinerja dan Validasi Threshold untuk Sistem Retrieval Dokumen

### Footer (size: 20pt, center)

Nama | NRP | Universitas  
Februari 2026

---

## SLIDE 2: MENGAPA PENGUJIAN DIBUTUHKAN?

### Judul (size: 36pt, bold)

**Mengapa Sistem Perlu Diuji?**

### Isi (Bullet Points, size: 24pt)

- **⚠️ Risiko Tanpa Evaluasi:**
  - Sistem bisa memberikan jawaban yang salah atau menyesatkan
  - Menurunkan kepercayaan pengguna terhadap sistem

- **✅ Pentingnya Evaluasi:**
  - Memastikan sistem memberikan jawaban yang relevan dan akurat
  - Mengidentifikasi kelemahan sistem untuk perbaikan
  - Menentukan threshold optimal untuk deployment

---

## SLIDE 3: MANUAL LABELS VS WEAK LABELS

### Judul (size: 36pt, bold)

**Mengapa Ada Manual dan Weak Labels?**

### Isi (Bullet Points, size: 24pt)

- **Manual Labels (Gold Standard):**
  - Dibuat oleh manusia → lebih akurat dan terpercaya
  - Digunakan untuk validasi akhir sistem
  - Contoh: 200 query × 20 candidates = 4000 judgments

- **Weak Labels (Baseline):**
  - Dibuat otomatis dari sistem (heuristik/embedding)
  - Cepat dibuat, tapi kurang akurat
  - Contoh: 632 query dengan weak relevance

- **Mengapa Keduanya Penting?**
  - Weak labels → baseline untuk eksperimen awal
  - Manual labels → validasi gold standard untuk hasil final

---

**Generated:** February 2026
