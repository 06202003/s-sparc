# Outline Presentasi PPT: Evaluasi Sistem Semantic Similarity

---

## SLIDE 1: TITLE SLIDE

**Background:** Gradient biru (theme profesional)

### Judul Utama (size: 54pt, bold, center)

**Near-Perfect Ranking: Sistem Semantic Similarity dengan Precision 100%**

### Subtitle (size: 32pt, center)

Evaluasi & Validasi Sistem Retrieval Document Similarity

### Footer (size: 20pt, center)

Nama | NRP | Universitas  
Februari 2026

---

## SLIDE 2: AGENDA

**Layout:** Bullet list dengan 4 poin utama

### Agenda Presentasi

1. **Konteks Masalah** — Mengapa semantic similarity perlu dievaluasi?
2. **Metodologi Evaluasi** — Dataset, metrik, dan proses labeling manual
3. **Hasil Utama** — Performance metrics & threshold decision
4. **Justifikasi Threshold 90%** — Why not 80%? Trade-off analysis

---

## SLIDE 3: KONTEKS & MOTIVASI

**Layout:** 2 kolom (kiri: problem, kanan: solusi)

### Left Column - MASALAH

- ⚠️ Query ambiguous → bisa retrieve dokumen yang salah
- ⚠️ Tanpa evaluasi rigorous → tidak tahu seberapa akurat sistem
- ⚠️ Production risk → user dapat jawaban menyesatkan

### Right Column - SOLUSI

- ✅ Evaluasi dengan manual labels (gold standard)
- ✅ 200 query × 20 candidates = 4000 judgments
- ✅ Validasi threshold decision (80% vs 90%)

---

## SLIDE 4: METODOLOGI - DATASET & LABELING

**Layout:** 3 poin dengan visual representation

### 📊 Dataset Composition

```
Total Queries: 632
├─ Manual-Labeled: 200 (untuk validasi)
└─ Weak-Labeled: 432 (baseline comparison)

Candidates per Query: 20
Total Judgments: 4000 (200 × 20)
Relevance Scale: 0-3 points (not relevant → highly relevant)
```

### 🏷️ Relevance Labeling

| Rel Score | Definisi                                             |
| --------- | ---------------------------------------------------- |
| **0**     | Not Relevant — tidak ada kesamaan semantic           |
| **1**     | Partially Relevant — ada kesamaan tapi surface-level |
| **2**     | Relevant — semantically similar                      |
| **3**     | Highly Relevant — exact semantic match               |

### ✍️ Manual Labeling Process

1. Auto-fill pred_score berdasarkan embedding similarity
2. Review manual untuk setiap query (threshold-based pre-fill)
3. Validation: check untuk false positives/negatives
4. Output: qrels_manual.csv (gold standard)

---

## SLIDE 5: METODOLOGI - METRIK EVALUASI

**Layout:** 2 kolom (metrik ranking vs metrik threshold)

### Column 1: RANKING QUALITY METRICS

- **Hit@k** — Apakah dokumen relevan ada di top-k?
- **Precision@k** — Dari k dokumen, berapa persen yang relevan?
- **Recall@k** — Dari semua dokumen relevan, berapa persen terakit di top-k?
- **MRR** (Mean Reciprocal Rank) — Di posisi berapa dokumen terbaik?
- **MAP** (Mean Average Precision) — Rata-rata precision di setiap ranking position?
- **nDCG** (normalized Discounted Cumulative Gain) — Kualitas ranking (best docs di top)?

### Column 2: THRESHOLD DECISION METRICS

- **Precision** — Dari retrieve, berapa yang benar?
- **Recall** — Dari yang seharusnya retrieve, berapa yang kedapat?
- **F1 Score** — Harmonic mean precision & recall
- **Accuracy** — Prediction keseluruhan benar?
- **Confusion Matrix** — TP, FP, TN, FN breakdown

---

## SLIDE 6: HASIL UTAMA - RANKING QUALITY (TABLE)

**Layout:** Tabel dengan highlight warna

### 📊 Kinerja Retrieval Model (200 Query - Manual Labels)

| **k**  | **Hit@k** | **Precision@k** | **Recall@k** | **MRR** | **MAP** | **nDCG** |
| ------ | --------- | --------------- | ------------ | ------- | ------- | -------- |
| **1**  | ✅ 100%   | ✅ 100%         | 6.32%        | 1.000   | 0.063   | 1.000    |
| **3**  | ✅ 100%   | ✅ 99.5%        | 17.72%       | 1.000   | 0.177   | 1.000    |
| **5**  | ✅ 100%   | ✅ 98.7%        | 27.87%       | 1.000   | 0.279   | 1.000    |
| **10** | ✅ 100%   | ✅ 97.6%        | 52.25%       | 1.000   | 0.523   | 1.000    |

### KEY INSIGHTS

- ✅ **Hit Rate 100%** → Sistem selalu menemukan minimal 1 dokumen relevan
- ✅ **MRR = 1.000** → Dokumen paling relevan ALWAYS ranked #1
- ✅ **nDCG = 1.000** → Ranking quality NEAR-PERFECT
- ✅ **Precision tetap >97%** bahkan di k=10

---

## SLIDE 7: HASIL UTAMA - THRESHOLD EVALUATION (TABLE)

**Layout:** Tabel threshold dengan fokus pada 0.90

### 📊 Decision Quality @ Different Thresholds

| **Threshold** | **Precision** | **Recall** | **F1**    | **Accuracy** | **Retrieval Rate** | **Keterangan**     |
| ------------- | ------------- | ---------- | --------- | ------------ | ------------------ | ------------------ |
| 0.80          | 100%          | 100%       | 1.000     | 100%         | 100%               | TP: 200, FP: 0     |
| **0.90**      | **✅ 100%**   | **✅ 96%** | **0.980** | **96%**      | **96%**            | **TP: 192, FP: 0** |

### Confusion Matrix @ Threshold 0.90

```
           Predicted Positive | Predicted Negative
Actual Positive  |  192 (TP)  |    8 (FN)  ← Fallback ke GPT
Actual Negative  |   0 (FP)   |    0 (TN)  ← Perfect!
```

### INTERPRETATION

- ✅ **Zero False Positive** → Tidak ada dokumen salah yang di-retrieve
- ✅ **96% Retrieved** → 192 dari 200 query bisa dijawab dari knowledge base
- ⚠️ **4% Fallback ke GPT** → Hanya 8 query yang perlu GPT (minimal cost)

---

## SLIDE 8: MENGAPA THRESHOLD 0.90? (DEFENSE)

**Layout:** 6 alasan dengan visual emphasis

### ❓ PERTANYAAN

**Mengapa pilih 0.90 padahal 0.80 dapat 100%?**

---

### ✅ ALASAN 1: PRODUCTION RISK MITIGATION

**Problem dengan Threshold 0.80:**

- 200 query manual = environment terkontrol (tidak representative semua case)
- Di production ada query ambiguous yang bisa di-retrieve salah
- "Mirip" pada evaluation ≠ "mirip" pada data baru → overfitting

**Solusi dengan 0.90:**

- Conservative approach → safe untuk unseen queries
- Lebih baik tolak query ambiguous daripada beri jawaban salah

---

### ✅ ALASAN 2: COST OF ERRORS (FALSE POSITIVE >> FALSE NEGATIVE)

**False Positive (retrieve dokumen salah):**

- ❌ User dapat jawaban menyesatkan
- ❌ **Damage kredibilitas sistem** (user tidak tahu itu salah!)
- ❌ Very hard to detect & fix

**False Negative (fallback ke GPT):**

- ✅ User tetap dapat jawaban (dari GPT)
- ✅ Hanya +4% token cost
- ✅ Easy to trace & acceptable

**Conclusion:** Risk FP >> biaya FN → choose 0.90

---

### ✅ ALASAN 3: SEMANTIC CONFIDENCE QUALITY

| Threshold Range | Interpretasi       | Confidence                      |
| --------------- | ------------------ | ------------------------------- |
| 0.50-0.80       | "Mirip"            | Bisa syntactic/surface-level    |
| 0.80-0.89       | "Cukup mirip"      | Tinggi tapi bisa false positive |
| **0.90-1.00**   | **"Sangat mirip"** | **✅ TRUE SEMANTIC MATCH**      |

**Why 0.90?** = Memastikan **true semantic equivalence**, bukan hanya syntactic similarity

---

### ✅ ALASAN 4: EVIDENCE FROM WEAK LABELS (632 Query)

**Dataset lebih besar (weak labels) memberikan evidence:**

```
Threshold 0.80:  611 TP, 20 FP → 96.83% Precision
Threshold 0.90:  580 TP, 19 FP → 96.83% Precision
                                ↑ Lebih konservatif (1 FP less)
```

**Insight:**

- Sama precision (96.83%)
- Tapi 0.90 lebih conservative (fewer FP risk)
- Confirms decision untuk production

---

### ✅ ALASAN 5: USER EXPERIENCE PRINCIPLE

**For high-stakes information retrieval:**

- **Precision > Recall** (better wrong than misleading)
- User tolerant dengan "sistem tidak tahu" (fallback)
- User NOT tolerant dengan "sistem salah tapi confident"

**Impact:**

- Hanya 4% query affected (8/200) → minimal disruption
- Better long-term trust

---

### ✅ ALASAN 6: SCALABILITY & MAINTENANCE

**Sebagai sistem berkembang:**

- Knowledge base akan ditambah
- Threshold 0.90 lebih robust → reduce noise dari dokumen baru
- Minimize manual review false positives

---

### 🎯 FINAL DECISION

> **Threshold 0.90 dipilih bukan karena "terbaik" pada evaluation, tetapi karena:**
>
> 1. **Safety margin** untuk production deployment
> 2. **Zero tolerance** untuk false positive
> 3. **Acceptable cost** (4% fallback) untuk long-term reliability

**Analogi:** Seperti pilih 99% confidence interval (bukan 95%) dalam stats testing — sacrifice sensitivity untuk gain specificity & reduce Type I error.

---

## SLIDE 9: BASELINE COMPARISON (WEAK VS MANUAL)

**Layout:** Perbandingan side-by-side dengan delta

### 📈 Weak Labels vs Manual Labels

| **Metrik**   | **Weak (632q)** | **Manual (200q)** | **Delta** | **Insight**          |
| ------------ | --------------- | ----------------- | --------- | -------------------- |
| Hit@10       | 100%            | 100%              | 0%        | Same                 |
| Precision@10 | 29.68%          | 97.6%             | **+68%**  | 🔥 Huge improvement! |
| nDCG@10      | 0.982           | 1.000             | +1.8%     | Both excellent       |
| MRR          | 0.979           | 1.000             | +2.1%     | Both excellent       |

### KEY FINDING

**Manual labeling significantly improves evaluation precision (+68 points)**

- Weak labels = rough estimate
- Manual labels = gold standard validation
- For thesis: use manual as primary, weak as baseline

---

## SLIDE 10: SUMMARY & DEPLOYMENT RECOMMENDATION

**Layout:** 2 kolom (summary left, recommendation right)

### Left - KESIMPULAN EVALUASI

**Near-Perfect Performance Achieved:**

- ✅ Hit Rate 100% — selalu menemukan dokumen
- ✅ MRR 1.000 — dokumen terbaik di rank #1
- ✅ nDCG 1.000 — ranking quality optimal
- ✅ Precision 100% @ threshold 0.90
- ✅ Zero False Positive

**Trade-off Accepted:**

- ⚠️ 4% fallback ke GPT (8/200 query)
- ✅ Minimal cost untuk zero false positive
- ✅ Acceptable untuk production

### Right - REKOMENDASI DEPLOYMENT

**Production Configuration:**

```
threshold = 0.90
├─ ≥0.90  → Retrieve (confidence tinggi)
├─ 0.80-0.90 → Fallback ke GPT (confidence medium)
├─ <0.80  → Always fallback (confidence low)
└─ Monitor: fallback rate (target <5%)
```

**Monitoring Plan:**

1. Track success rate vs user feedback
2. Monthly threshold review
3. Re-evaluate ketika knowledge base bertambah

---

## SLIDE 11: Q&A / CLOSING

**Layout:** Blank dengan big text

### TERIMA KASIH

**Pertanyaan Umum yang Siap:**

1. **"Kenapa tidak threshold lebih rendah?"** → Trade-off, zero FP priority
2. **"Bagaimana sistem perform di live data?"** → Need production monitoring
3. **"Apakah 100% query bisa di-retrieve?"** → 96% dengan threshold 0.90, remainder fallback
4. **"Apakah embeddings bisa di-improve?"** → Yes, future work mencakup fine-tuning model
5. **"Berapa biaya fallback?"** → ~4% token cost, acceptable vs zero false positive

**Contact / Next Steps:**

- Code: GitHub repository s-sparc
- Evaluation framework: pengujian semantic similarity/
- Data: semantic_similarity/

---

---

# NOTES UNTUK PRESENTER

## Timing

- Slide 1-2: 1 menit (intro)
- Slide 3-5: 3 menit (metodologi)
- Slide 6-7: 2 menit (hasil)
- Slide 8: 4 menit (MAIN - threshold justification)
- Slide 9-10: 2 menit (comparison & recommendation)
- Slide 11: 1 menit (Q&A)
- **Total: 13 menit + Q&A**

## Key Talking Points

1. **Opening**: "Sistem kami mencapai near-perfect ranking dengan zero false positive"
2. **Metodologi**: "Kami evaluate dengan 4000 manual judgments, bukan hanya automated"
3. **Results**: Highlight MRR 1.0, nDCG 1.0 (rare achievement)
4. **Threshold**: "Ini bukan soal angka highest, tapi safest untuk production"
5. **Closing**: "Trade-off 4% fallback cost adalah investasi untuk long-term reliability"

## Visual Tips for PowerPoint

1. **Slide 6-7**: Gunakan highlight cell untuk angka penting (100%, 1.000, 0, 96%)
2. **Slide 8**: Setiap alasan bisa pake emoji/icon untuk clarity
3. **Colors**:
   - ✅ Green untuk positive insights
   - ⚠️ Orange untuk warnings/trade-offs
   - ❌ Red untuk risks
4. **Fonts**: Title 36pt+, body 20pt+, table 16pt+ (readable dari belakang)
5. **Visuals**: Bisa tambah bar chart untuk threshold comparison (0.80 vs 0.90)

## Demo Ideas (if applicable)

- Live demo: run evaluation pipeline (menunjukkan automation)
- Show confusion matrix visualization
- Query example: show retrieved documents vs fallback cases
