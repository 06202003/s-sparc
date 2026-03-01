# Hasil Evaluasi Semantic Similarity (Siap Tempel Bab Tesis)

## Ringkasan Setup
- Sumber hasil: **manual labels**
- Jumlah query dievaluasi: **200**

## Tabel 1. Kinerja Retrieval
| k | Hit@k | Precision@k | Recall@k | MRR@k | MAP@k | nDCG@k |
|---|---|---|---|---|---|---|
| 1 | 100.00% | 100.00% | 6.32% | 1.0000 | 0.0632 | 1.0000 |
| 3 | 100.00% | 99.50% | 17.72% | 1.0000 | 0.1772 | 1.0000 |
| 5 | 100.00% | 98.70% | 27.87% | 1.0000 | 0.2787 | 1.0000 |
| 10 | 100.00% | 97.60% | 52.25% | 1.0000 | 0.5225 | 1.0000 |

### Interpretasi Otomatis (Retrieval)
- Pada skenario **manual**, evaluasi dilakukan pada **200 query**.
- Kualitas ranking terbaik terhadap nDCG pada tabel ini terjadi pada **k=1** dengan nDCG=1.0000.
- Nilai MAP tertinggi terjadi pada **k=10** dengan MAP=0.5225.
- Secara umum, ketika nilai k membesar, recall meningkat karena lebih banyak kandidat dikembalikan, sedangkan precision cenderung menurun.

## Tabel 2. Evaluasi Threshold Keputusan
| Threshold | TP | FP | TN | FN | Precision | Recall | F1 | Accuracy | Retrieval Rate |
|---|---|---|---|---|---|---|---|---|---|
| 0.80 | 200 | 0 | 0 | 0 | 100.00% | 100.00% | 1.0000 | 100.00% | 100.00% |
| 0.90 | 192 | 0 | 0 | 8 | 100.00% | 96.00% | 0.9796 | 96.00% | 96.00% |

### Interpretasi Otomatis (Threshold)
- Threshold terbaik berdasarkan F1 global berada pada **0.50** dengan F1=1.0000.
- Untuk implementasi aplikasi, threshold yang lebih tinggi (misalnya 0.90) biasanya meningkatkan kehati-hatian retrieval, namun menambah fallback ke GPT.
- Pemilihan akhir threshold disarankan mempertimbangkan trade-off kualitas jawaban retrieval, biaya token GPT, dan latensi sistem.

## Kesimpulan Singkat
Model retrieval menunjukkan performa tinggi pada skenario evaluasi ini. Untuk pelaporan ilmiah utama, disarankan menjadikan hasil **manual labels (gold qrels)** sebagai hasil utama, sedangkan weak labels digunakan sebagai baseline internal.
