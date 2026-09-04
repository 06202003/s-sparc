# Draft Paten — S-SPARC

Tanggal: 25 Mei 2026

## 1. Judul

S-SPARC: Sistem Asisten Pembelajaran Pemrograman Berbasis Retrieval‑First dengan Pengelolaan Basis‑Pengetahuan Otonom dan Lapisan Gamifikasi Hemat Energi

## 2. Abstrak

S-SPARC adalah sebuah sistem komputer‑implemented untuk memberikan jawaban pembelajaran pemrograman yang memprioritaskan retrieval berbasis embedding sebelum memanggil model bahasa besar (LLM). Sistem ini menggabungkan modul routing adaptif, pipeline evaluasi otonom khusus snippet (AST checks, LLM‑as‑a‑Judge, semantic similarity, SHA‑256 duplicate detection, Isolation Forest anomaly detection), mekanisme ensemble embedding dan refresh‑from‑DB untuk menjaga indeks retrieval, serta modul penghitung token dan estimator dampak lingkungan yang memetakan penghematan sumber daya ke metrik analogi sehari‑hari. Sebuah lapisan gamifikasi memberikan poin/token kepada pengguna berdasarkan penghematan token/energi yang dihasilkan dari penggunaan retrieval, mendorong perilaku hemat sumber daya. Sistem secara otomatis menambahkan jawaban LLM berkualitas ke basis‑pengetahuan untuk meningkatkan efektivitas retrieval di masa mendatang.

## 3. Deskripsi Invensi (Uraian Teknis)

Ringkasan arsitektur:

- Basis‑pengetahuan (KB): koleksi pasangan prompt–snippet (kode) dengan embedding dan metadata (timestamp, sumber, kualitas). Indeksasi embedding dapat berupa struktur vektor (faiss/ann) atau DataFrame terindeks.
- Modul Retrieval Semantik: menghitung embedding kueri dan mencari entri KB berperingkat teratas; jika similarity ≥ ambang (mis. 0.90), hasil dikembalikan sebagai "direct retrieval" tanpa pemanggilan LLM.
- Routing Adaptif: aturan logika (thresholds + metrik kualitas) menentukan kapan menggunakan hasil retrieval atau memanggil LLM.
- Pipeline Evaluasi Otonom (Knowledge Governance): job terjadwal/autonom yang menilai entri KB menggunakan kombinasi: (a) AST‑based partial snippet validation untuk mendeteksi error sintaks/struktur relevan pada snippet; (b) LLM‑as‑a‑Judge untuk scoring kualitas jawaban; (c) semantic similarity clustering untuk menemukan kemiripan semantik antar entri; (d) SHA‑256 exact duplicate detection untuk deteksi duplikat; (e) Isolation Forest atau metode anomaly detection lain untuk menemukan outlier. Pipeline menerapkan aturan ambang terkalibrasi untuk menandai, menurunkan prioritas, atau menghapus entri.
- Ensemble Embedding & Refresh: sistem dapat menggabungkan beberapa encoder (ensemble) dengan bobot yang dapat disetel, dan mendukung mekanisme refresh_from_DB setiap kali ada penambahan atau setelah interval tertentu agar indeks retrieval terkini.
- Otomatisasi Penambahan Konten: jawaban LLM yang lulus pemeriksaan kualitas otomatis (LLM‑as‑a‑Judge + AST + similarity threshold) secara otomatis ditambahkan ke KB bersama embeddingnya.
- Penghitung Token & Estimator Dampak Lingkungan: modul menghitung token yang dihemat pada retrieval dibanding pemanggilan LLM, dan mengubahnya menjadi estimasi energi/CO2/air menggunakan model konversi internal; hasil ditampilkan dalam analogi sehari‑hari.
- Gamifikasi Ekonomi Token: sistem memberikan poin/credit berdasarkan penghematan (token/energi) dan menampilkan leaderboard, saldo mingguan, dan agregat, guna mendorong penggunaan retrieval.

Implementasi praktis yang ada di repo (referensi): `AIREA_2026_SUBMISSION.md`, modul `semantic_similarity` dan contoh implementasi routing/gamification di `app copy 2.py` / `app copy 3.py`.

## 4. Klaim Kebaruan (Claims) — Draft

1. Suatu sistem komputer‑implemented untuk menyediakan jawaban pembelajaran pemrograman yang mengurangi panggilan ke model bahasa besar, meliputi: sebuah basis‑pengetahuan berisi pasangan prompt–snippet yang diindeks dengan embedding; sebuah modul retrieval semantik yang mengembalikan entri berdasarkan kemiripan embedding; sebuah modul routing adaptif yang memilih antara hasil retrieval dan pemanggilan LLM berdasarkan skor kemiripan dan/atau metrik kualitas; sebuah pipeline evaluasi otonom yang menilai dan memurnikan entri KB menggunakan kombinasi AST‑based snippet validation, LLM‑as‑a‑Judge, semantic similarity, SHA‑256 duplicate detection, dan anomaly detection; sebuah modul penghitung token dan estimator dampak lingkungan yang memperkirakan konsumsi sumber daya untuk tiap respons; dan sebuah modul gamifikasi yang memberikan insentif berdasarkan penghematan sumber daya, dimana subsistem tersebut terintegrasi untuk otomatis memperbarui KB dan mendorong perilaku hemat sumber daya.

2. Sistem menurut klaim 1, dimana pipeline evaluasi otonom melakukan tindakan pembersihan otomatis berdasarkan kombinasi skor AST, skor LLM‑as‑a‑Judge, pengukuran kesamaan semantik, deteksi duplikat SHA‑256, dan skor anomali Isolation Forest.

3. Sistem menurut klaim 1, dimana modul retrieval menggunakan ambang kemiripan semantik untuk menyalurkan jawaban sebagai direct retrieval tanpa pemanggilan LLM.

4. Sistem menurut klaim 1, yang menggunakan ensemble encoder embedding dan mekanisme refresh‑from‑DB untuk memperbarui indeks retrieval secara berkala dan segera setelah penambahan entri baru.

5. Sistem menurut klaim 1, dimana modul gamifikasi menetapkan poin kepada pengguna berdasarkan jumlah token/energi/CO2 yang dihemat oleh jawaban retrieval versus jawaban LLM.

6. Sistem menurut klaim 1, dimana jawaban LLM yang melewati pemeriksaan kualitas otomatis otomatis ditambahkan ke KB bersama embedding‑nya untuk penggunaan retrieval selanjutnya.

7. Metode operasi sesuai klaim 1, yang meliputi langkah‑langkah: menerima query; melakukan pencarian embedding pada KB; apabila score ≥ ambang maka mengembalikan entri retrieval dan menghitung penghematan serta memberi poin gamifikasi; jika tidak, memanggil LLM, menilai jawaban menggunakan pipeline evaluasi, menambahkan jawaban berkualitas ke KB, dan memperbarui metrik sustainability dan gamification.

> Catatan: klaim di atas dirancang untuk menonjolkan integrasi teknis modul‑modul (arsitektur dan efek teknis terukur). Untuk pengajuan resmi, klaim harus dipadatkan/dipilah ke dalam klaim independen dan beberapa klaim dependen yang lebih spesifik (parameter numerik, urutan operasi, struktur data).

## 5. Analisis Patentabilitas di Indonesia (Singkat)

- Kriteria utama: kebaruan (novelty), langkah inventif (non‑obviousness), penerapan industri (industrial applicability).
- Risiko utama: klaim yang menargetkan "program komputer sebagai such" atau aturan permainan/metode bisnis dapat ditolak berdasarkan praktik DJKI. Untuk meningkatkan peluang, fokuskan klaim pada solusi teknis terapan yang menunjukkan efek teknis (mis. pengurangan panggilan LLM terukur, pengurangan konsumsi energi, peningkatan akurasi retrieval pada dataset snippet).
- Bukti yang perlu dipersiapkan: diagram arsitektur, flow chart, pseudo‑code/algoritma inti, parameter ambang yang digunakan, hasil eksperimen (mis. pengurangan rata‑rata panggilan LLM, metrik kualitas), contoh kasus (dataset MBPP‑style), dan bukti implementasi di repo.

## 6. Prior Art Checklist (Tindakan awal yang disarankan)

- Cari paten publik terkait RAG, knowledge‑base governance, gamification untuk pembelajaran, LLM‑as‑a‑Judge, AST‑based snippet evaluation.
- Telusuri literatur akademik (ACL/NeurIPS/ICML/EMNLP), arXiv, dan whitepapers perusahaan (OpenAI, Anthropic, Google, etc.).
- Pencarian repositori GitHub untuk project RAG + gamification + sustainability tracking.
- Pencarian basis paten global (Espacenet, Google Patents, WIPO) untuk istilah kunci: "retrieval augmented generation", "knowledge base governance", "LLM judge", "snippet quality", "energy estimation for LLM".

## 7. Rekomendasi Langkah Selanjutnya

1. Lakukan prior‑art search mendalam; rapikan hasil temuan ke dalam tabel per unsur kebaruan.
2. Susun versi klaim formal dalam bahasa Indonesia sesuai format DJKI — minta bantuan patent agent/pengacara paten untuk redaksi final.
3. Lengkapi dokumen pendukung: diagram arsitektur, alur data, pseudo‑code, contoh input/output, hasil eksperimen kuantitatif.
4. Pertimbangkan perlindungan komplementer: rahasia dagang (algoritma internal, dataset), hak cipta untuk kode, dan/atau serangkaian klaim internasional bila perlu.
5. Jika ingin, persiapkan versi singkat untuk pengajuan provisional (jika negara tujuan mendukung) atau langsung file Paten Indonesia.

## 8. Lampiran — Referensi Kode (repo lokal)

- `AIREA_2026_SUBMISSION.md` (penjelasan arsitektur dan eksperimental)
- `semantic_similarity/` (modul retrieval/encoder)
- `app copy 2.py`, `app copy 3.py` (implementasi routing, token counting, gamification hooks)

---

Jika setuju, saya akan:

- mengupdate klaim menjadi format legal‑style (Bahasa Indonesia) sesuai standar DJKI, atau
- menjalankan prior‑art search awal pada repo + Google Patents + arXiv dan melaporkan temuan.

Pilih opsi selanjutnya: "Klaim formal", "Prior‑art search", atau "Keduanya".
