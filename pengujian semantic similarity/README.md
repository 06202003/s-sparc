# Pengujian Semantic Similarity

Folder ini berisi paket evaluasi siap-run untuk mengukur kualitas semantic retrieval.

## Yang Diukur

1. **Kualitas Retrieval Ranking**
   - `Hit@k`, `Precision@k`, `Recall@k`, `MRR@k`, `MAP@k`, `nDCG@k`
2. **Kualitas Keputusan Threshold**
   - Untuk logika `retrieval` vs `fallback GPT`
   - `precision`, `recall`, `f1`, `accuracy`, confusion matrix (`tp/fp/tn/fn`)

## Struktur

- `generate_qrels.py` → buat weak qrels + template label manual
- `evaluate_retrieval.py` → evaluasi ranking retrieval
- `evaluate_threshold.py` → evaluasi threshold (default 0.80 dan 0.90)
- `generate_thesis_report.py` → buat laporan markdown siap tempel ke bab evaluasi tesis
- `run_all.py` → jalankan semuanya otomatis
- `data/` → qrels input
- `outputs/` → hasil evaluasi CSV

## 1) Install dependency (sekali saja)

Dari root project:

```powershell
pip install -r "pengujian semantic similarity/requirements.txt"
```

## 2) Jalankan evaluasi cepat (langsung)

```powershell
python "pengujian semantic similarity/run_all.py"
```

Perintah di atas akan:

- membuat `qrels_weak.csv`
- membuat template `qrels_manual_template.csv`
- menghitung metrik retrieval + threshold berbasis weak labels
- membuat laporan siap tempel: `outputs/laporan_bab_evaluasi_weak.md`

## 3) Evaluasi ilmiah (manual labels)

1. Buka file:
   - `pengujian semantic similarity/data/qrels_manual_template.csv`
2. Isi kolom `rel` dengan skala:
   - `0` = tidak relevan
   - `1` = sedikit relevan
   - `2` = relevan
   - `3` = sangat relevan
3. Simpan sebagai:
   - `pengujian semantic similarity/data/qrels_manual.csv`
4. Jalankan lagi:

```powershell
python "pengujian semantic similarity/run_all.py"
```

Maka script akan otomatis evaluasi versi manual juga.

## Output Utama

- `outputs/retrieval_summary_weak.csv`
- `outputs/retrieval_per_query_weak.csv`
- `outputs/threshold_summary_weak.csv`
- `outputs/laporan_bab_evaluasi_weak.md`
- (jika ada manual qrels)
  - `outputs/retrieval_summary_manual.csv`
  - `outputs/retrieval_per_query_manual.csv`
  - `outputs/threshold_summary_manual.csv`
  - `outputs/laporan_bab_evaluasi_manual.md`

## Catatan Penting

- **Weak qrels** berasal dari `relevant_indices` dataset, cocok untuk baseline internal.
- Untuk hasil tesis yang kuat, gunakan **manual qrels** (gold labels).
- Dataset default: `semantic_similarity/mbpp_all_with_embedding_and_relevance_v2.json`
