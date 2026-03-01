# Hasil Evaluasi Semantic Similarity (Siap Tempel Bab Tesis)

## Ringkasan Setup
- Sumber hasil: **weak labels**
- Jumlah query dievaluasi: **632**

## Tabel 1. Kinerja Retrieval
| k | Hit@k | Precision@k | Recall@k | MRR@k | MAP@k | nDCG@k |
|---|---|---|---|---|---|---|
| 1 | 96.84% | 96.84% | 32.28% | 0.9684 | 0.3228 | 0.9684 |
| 3 | 99.21% | 97.15% | 97.15% | 0.9773 | 0.9659 | 0.9710 |
| 5 | 100.00% | 59.30% | 98.84% | 0.9792 | 0.9754 | 0.9810 |
| 10 | 100.00% | 29.68% | 98.95% | 0.9792 | 0.9758 | 0.9815 |

### Interpretasi Otomatis (Retrieval)
- Pada skenario **weak**, evaluasi dilakukan pada **632 query**.
- Kualitas ranking terbaik terhadap nDCG pada tabel ini terjadi pada **k=10** dengan nDCG=0.9815.
- Nilai MAP tertinggi terjadi pada **k=10** dengan MAP=0.9758.
- Secara umum, ketika nilai k membesar, recall meningkat karena lebih banyak kandidat dikembalikan, sedangkan precision cenderung menurun.

## Tabel 2. Evaluasi Threshold Keputusan
| Threshold | TP | FP | TN | FN | Precision | Recall | F1 | Accuracy | Retrieval Rate |
|---|---|---|---|---|---|---|---|---|---|
| 0.80 | 611 | 20 | 0 | 1 | 96.83% | 99.84% | 0.9831 | 96.68% | 99.84% |
| 0.90 | 580 | 19 | 1 | 32 | 96.83% | 94.77% | 0.9579 | 91.93% | 94.78% |

### Interpretasi Otomatis (Threshold)
- Threshold terbaik berdasarkan F1 global berada pada **0.50** dengan F1=0.9839.
- Untuk implementasi aplikasi, threshold yang lebih tinggi (misalnya 0.90) biasanya meningkatkan kehati-hatian retrieval, namun menambah fallback ke GPT.
- Pemilihan akhir threshold disarankan mempertimbangkan trade-off kualitas jawaban retrieval, biaya token GPT, dan latensi sistem.

## Kesimpulan Singkat
Model retrieval menunjukkan performa tinggi pada skenario evaluasi ini. Untuk pelaporan ilmiah utama, disarankan menjadikan hasil **manual labels (gold qrels)** sebagai hasil utama, sedangkan weak labels digunakan sebagai baseline internal.
