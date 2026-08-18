# Definisi Metrik Evaluasi: MRR, MAP, nDCG

## 1. **MRR (Mean Reciprocal Rank)**

- **Definisi:**
  MRR adalah rata-rata dari kebalikan posisi (rank) dokumen relevan pertama yang ditemukan untuk setiap query.

- **Formula:**
  \[
  \text{MRR} = \frac{1}{|Q|} \sum\_{i=1}^{|Q|} \frac{1}{\text{rank}\_i}
  \]
  - \( |Q| \): Jumlah query
  - \( \text{rank}\_i \): Posisi dokumen relevan pertama untuk query ke-\(i\)

- **Interpretasi:**
  - MRR = 1.0 → Dokumen paling relevan selalu di peringkat pertama.
  - MRR rendah → Dokumen relevan sering berada di peringkat bawah.

---

## 2. **MAP (Mean Average Precision)**

- **Definisi:**
  MAP adalah rata-rata dari precision di setiap posisi dokumen relevan untuk semua query.

- **Formula:**
  \[
  \text{MAP} = \frac{1}{|Q|} \sum*{i=1}^{|Q|} \text{AP}\_i
  \]
  \[
  \text{AP}\_i = \frac{1}{m} \sum*{k=1}^{m} P(k) \cdot \text{rel}(k)
  \]
  - \( |Q| \): Jumlah query
  - \( m \): Jumlah dokumen relevan untuk query
  - \( P(k) \): Precision pada posisi \(k\)
  - \( \text{rel}(k) \): 1 jika dokumen pada posisi \(k\) relevan, 0 jika tidak

- **Interpretasi:**
  - MAP tinggi → Precision konsisten tinggi di semua posisi dokumen relevan.
  - MAP rendah → Precision menurun di posisi dokumen relevan yang lebih rendah.

---

## 3. **nDCG (Normalized Discounted Cumulative Gain)**

- **Definisi:**
  nDCG mengukur kualitas ranking dengan memberikan bobot lebih tinggi pada dokumen relevan yang berada di posisi atas.

- **Formula:**
  \[
  \text{nDCG} = \frac{\text{DCG}}{\text{IDCG}}
  \]
  \[
  \text{DCG} = \sum\_{i=1}^{p} \frac{2^{\text{rel}\_i} - 1}{\log_2(i+1)}
  \]
  \[
  \text{IDCG} = \text{DCG untuk ranking ideal}
  \]
  - \( p \): Jumlah dokumen yang dipertimbangkan
  - \( \text{rel}\_i \): Relevansi dokumen pada posisi \(i\)

- **Interpretasi:**
  - nDCG = 1.0 → Ranking optimal (dokumen relevan di posisi atas).
  - nDCG rendah → Dokumen relevan tersebar di posisi bawah.

---

## Perbandingan Metrik

| **Metrik** | **Fokus**                      | **Kelebihan**                        | **Kekurangan**                        |
| ---------- | ------------------------------ | ------------------------------------ | ------------------------------------- |
| **MRR**    | Posisi dokumen relevan pertama | Mudah diinterpretasi                 | Tidak mempertimbangkan semua dokumen  |
| **MAP**    | Precision di semua posisi      | Mengukur konsistensi ranking         | Tidak mempertimbangkan posisi dokumen |
| **nDCG**   | Kualitas ranking (posisi)      | Memberi bobot lebih pada posisi atas | Lebih kompleks untuk dihitung         |

---

## Mengapa Metrik Ini Penting?

- **MRR:** Fokus pada pengalaman pengguna → dokumen relevan harus muncul di posisi teratas.
- **MAP:** Mengukur performa sistem secara keseluruhan, bukan hanya dokumen pertama.
- **nDCG:** Menilai kualitas ranking dengan mempertimbangkan posisi dokumen relevan.

---

**Generated:** February 2026
