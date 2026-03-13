# Executive Summary

## S-SPARC: Quality Control Berbasis Snippet untuk Repositori Kode Retrieval-Augmented

Penelitian ini memperkenalkan S-SPARC, sebuah kerangka evaluasi dan tata kelola kualitas untuk menjaga mutu repositori potongan kode (code snippets) yang digunakan pada sistem AI-assisted software engineering. Evaluator konvensional umumnya didesain untuk program produksi yang utuh, sehingga sering tidak cocok ketika diterapkan pada snippet pendek yang memang bersifat parsial (misalnya gaya MBPP). Ketidaksesuaian ini dapat memicu over-deletion pada data yang sebenarnya masih relevan dan berguna untuk retrieval.

S-SPARC menutup gap tersebut melalui pipeline evaluasi snippet-aware yang menggabungkan relevansi semantik, analisis statis, penilaian LLM-as-a-Judge, deteksi duplikasi, dan sinyal anomali ke dalam satu proses keputusan terkalibrasi. Framework ini menerapkan kebijakan tiga tingkat: VALID, REVIEW_REQUIRED, dan DELETE_CANDIDATE agar proses pembersihan data lebih aman dan mengurangi false-positive deletion.

## Metode Penelitian

Pendekatan penelitian menggunakan desain eksperimental terapan (applied experimental design) dengan langkah berikut.

1. Kalibrasi rubric evaluasi agar sesuai karakter snippet retrieval.
2. Implementasi layanan evaluator terpisah (service-based) agar observabilitas dan kontrol eksperimen lebih baik.
3. Eksekusi evaluasi batch pada tabel code_embeddings dengan parameter yang dapat direproduksi.
4. Analisis hasil menggunakan metrik agregat (valid/review/delete, similarity, score, suspicious) serta alasan penghapusan.
5. Validasi keamanan eksekusi melalui dry-run, backup otomatis, dan pelaporan JSON.

## Modul dan Model yang Digunakan

### Modul inti sistem

1. evaluator_pipeline.py

- Fungsi: orkestrasi alur evaluasi end-to-end per batch.
- Metode utama: iterate batch, evaluasi per baris, agregasi skor, klasifikasi keputusan, pembuatan report.

2. static_analysis.py

- Fungsi: analisis kode berbasis aturan/statik.
- Metode utama: deteksi bahasa, validasi sintaks, line/function/loop counting, cyclomatic complexity, maintainability index, static scoring snippet-aware.

3. llm_judge.py

- Fungsi: penilaian kualitas semantik dan logika berbasis LLM.
- Metode utama: LLM-as-a-Judge dengan output terstruktur JSON (alignment, logic, quality, readability, completeness) dan fallback heuristic deterministik.

4. embedding_model.py

- Fungsi: pengukuran kesesuaian prompt dan kode melalui embedding.
- Metode utama: encoding query/passage, normalisasi embedding, cosine similarity untuk semantic relevance.

5. anomaly_detection.py

- Fungsi: deteksi entri yang menyimpang dari distribusi normal.
- Metode utama: anomaly scoring berbasis Isolation Forest terhadap fitur evaluasi.

6. database.py

- Fungsi: akses data code_embeddings dan operasi penghapusan terkontrol.
- Metode utama: iterasi batch, hitung total, dan delete by id.

7. scheduler.py

- Fungsi: otomasi evaluasi berkala.
- Metode utama: scheduling mingguan (APScheduler) dan pemanggilan pipeline terjadwal.

### Model dan teknologi yang digunakan

1. Embedding model

- intfloat/multilingual-e5-base (Sentence-Transformers) untuk semantic similarity.

2. LLM model

- gpt-4o sebagai penilai kualitas kode berbasis konteks prompt.

3. Analisis statis dan machine learning

- Radon untuk complexity dan maintainability.
- Scikit-learn (IsolationForest) untuk anomaly detection.

4. Framework layanan

- FastAPI + Uvicorn untuk API service.
- APScheduler untuk penjadwalan otomatis.

## Metode Penilaian (Scoring Method)

Skor akhir dihitung dengan pendekatan weighted aggregation yang menekankan kesesuaian snippet terhadap kebutuhan retrieval.

1. Komponen skor

- LLM alignment
- LLM logic
- Semantic similarity
- LLM quality
- Static score

2. Kebijakan keputusan

- VALID: skor memenuhi ambang akhir dan lolos filter semantik.
- REVIEW_REQUIRED: skor berada di rentang review band agar ditinjau manual.
- DELETE_CANDIDATE: duplikat, tidak relevan secara semantik, atau kualitas rendah.

3. Mekanisme keamanan

- Backup JSON sebelum delete.
- Dry-run mode untuk validasi tanpa perubahan data.
- Laporan detail (threshold, breakdown, sample ids) untuk audit.

## Ringkasan Hasil Evaluasi Terbaru (Full Dataset)

- Total entri dievaluasi: 678
- Valid: 647 (95,4%)
- Review required: 1
- Kandidat delete: 30 (4,4%)
- Rata-rata semantic similarity: 0,8774
- Rata-rata final score: 8,6149/10
- Alasan delete: 23 duplicate, 7 low-quality
- Durasi: 1087,13 detik

Temuan ini menunjukkan bahwa evaluator terkalibrasi mampu mempertahankan mayoritas snippet bernilai tinggi, sekaligus membersihkan data redundan/noisy secara terukur. Dominasi kategori duplicate pada kandidat delete mengindikasikan bahwa peluang peningkatan utama ada pada hygiene repositori, bukan pada rendahnya relevansi konten inti.

## Dampak Ilmiah dan Praktis

S-SPARC menegaskan bahwa quality control untuk code retrieval system harus context-aware: snippet pendek tidak dapat dinilai dengan kriteria yang sama seperti aplikasi penuh. Metode yang diusulkan meningkatkan keandalan retrieval layer, menurunkan risiko filtering destruktif, dan menyediakan artefak audit yang kuat (backup, report, trace threshold) untuk kebutuhan riset maupun produksi.

Secara keseluruhan, penelitian ini berkontribusi pada pendekatan continuous quality assurance yang dapat dioperasionalkan, berbasis bukti empiris, dan langsung aplikatif untuk dataset edukasi pemrograman, coding assistant, serta pipeline retrieval-augmented generation.
