# 📚 Workflow & Analysis Semantic Similarity Prompt

---

## 1. 🎯 Objectives

> **Pipeline ini membangun semantic retrieval (semantic search) berbasis embedding untuk mencari kemiripan antar prompt (soal/code prompt) secara semantik, bukan sekadar keyword.**

Pipeline menggabungkan dua dataset MBPP (real & clone), menghasilkan embedding multibahasa, membangun index FAISS untuk pencarian cepat, dan menyediakan evaluasi retrieval berbasis ground truth relevansi otomatis.

---

## 2. ⚙️ Techstack, Workflow, etc.

### **Similarity Calculation Methods & Algorithms**

#### **How Semantic Similarity is Calculated**

- **Cosine Similarity**: Mengukur seberapa dekat arah dua vektor embedding. Nilai 1 berarti identik, 0 berarti ortogonal (tidak mirip), -1 berarti berlawanan arah. Formula:

  $$
  	{cosine}(A, B) = \frac{A \cdot B}{\|A\| \|B\|}
  $$

- **Inner Product (Dot Product)**: Digunakan pada FAISS IndexFlatIP. Untuk vektor yang sudah dinormalisasi, inner product setara dengan cosine similarity.

- **Euclidean Distance**: Kadang dipakai untuk baseline, tapi kurang cocok untuk semantic similarity karena embedding bisa berbeda skala.

Pada pipeline ini, **cosine similarity** (atau inner product pada FAISS) adalah metode utama untuk mengukur kedekatan makna antar prompt/code embedding.

#### **Algorithms Used in the Pipeline**

- **Ensemble Embedding**: Gabungan beberapa model embedding (paraphrase-multilingual-mpnet-base-v2, LaBSE, multilingual-e5-base) untuk hasil lebih robust.
- **FAISS (Facebook AI Similarity Search)**: Library indexing untuk pencarian nearest neighbor berbasis inner product/cosine similarity, sangat efisien untuk dataset besar.
- **Auto-Translation**: Menggunakan Helsinki-NLP/opus-mt-id-en untuk menerjemahkan prompt non-Inggris sebelum embedding.
- **Relevance Mapping**: Otomatisasi mapping relevansi dengan top-N similarity.
- **Reranking (opsional)**: Bisa ditambah reranking BERT untuk hasil retrieval lebih relevan (lihat rekomendasi paper).
- **Diversity Analysis**: Analisis variasi hasil retrieval untuk menghindari bias model.

**Summary:**

- Pipeline mengukur kemiripan makna antar prompt/code dengan cosine similarity pada embedding hasil ensemble, menggunakan FAISS untuk indexing dan retrieval cepat, serta dapat diperluas dengan reranking dan analisis diversity.

### **Techstack:**

- **Python** (Pandas, Numpy)
- **sentence-transformers**  
  (paraphrase-multilingual-mpnet-base-v2, LaBSE, multilingual-e5-base)
- **Transformers** (Helsinki-NLP/opus-mt-id-en untuk auto-translate)
- **FAISS** (IndexFlatIP)
- **joblib** (serialisasi model)
- **langdetect** (deteksi bahasa)
- **Matplotlib** (visualisasi)

### **Workflow:**

1. **Data Loading & Preprocessing**  
   Gabungkan dua dataset, normalisasi, deduplikasi, deteksi bahasa.
2. **Embedding**  
   Generate embedding prompt dengan ensemble 3 model, auto-translate jika perlu.
3. **Indexing**  
   Normalisasi embedding, bangun FAISS index untuk similarity search.
4. **Model Class**  
   Bungkus semua komponen ke dalam class `SemanticRetrievalModel`.
5. **Relevance Mapping**  
   Mapping relevansi otomatis (top-N similarity) untuk evaluasi retrieval.
6. **Evaluasi**  
   Hitung metrik retrieval (F1, precision, recall), error analysis, diversity analysis.
7. **Visualisasi**  
   Distribusi skor similarity, contoh retrieval relevan, error case.
8. **Simpan Model**  
   Serialisasi model ke file PKL.
9. **Demo Penggunaan**  
   Contoh penggunaan model hasil PKL.

---

## 3. 📄 Paper Analysis Related to Semantic Similarity Prompt

Berikut adalah beberapa paper resmi (IEEE/ACM/Springer) yang relevan dengan semantic similarity dan prompt retrieval:

---

### 1. **Sentence-BERT: Sentence Embeddings using Siamese BERT-Networks**

_Reimers, N., & Gurevych, I. (2019). EMNLP._

> **Kontribusi:** Sentence-BERT (SBERT) memperkenalkan fine-tuning BERT untuk menghasilkan sentence embedding yang efisien dan akurat untuk semantic similarity dan retrieval.  
> **Gap:** SBERT belum mengoptimalkan ensemble multi-model dan belum eksplisit untuk prompt/code retrieval.  
> **Relevansi:** Pipeline ini sudah mengadopsi ensemble, bisa ditingkatkan dengan fine-tuning SBERT pada data prompt/code.

---

### 2. **Language-agnostic BERT Sentence Embedding**

_Wang, K., et al. (2020). arXiv:2007.01852._

> **Kontribusi:** LaBSE menghasilkan embedding multibahasa yang robust untuk semantic similarity cross-lingual.  
> **Gap:** Belum ada analisis mendalam untuk retrieval prompt/code di domain programming.  
> **Relevansi:** Pipeline sudah pakai LaBSE, bisa dieksplorasi untuk cross-lingual prompt/code retrieval.

---

### 3. **BEIR: A Heterogeneous Benchmark for Information Retrieval**

_Thakur, N., et al. (2021). SIGIR._

> **Kontribusi:** BEIR menyediakan benchmark retrieval untuk berbagai domain, termasuk code dan QA.  
> **Gap:** Belum ada benchmark khusus untuk prompt/code programming dalam bahasa Indonesia.  
> **Relevansi:** Pipeline bisa dievaluasi pada subset BEIR atau membuat benchmark baru untuk prompt/code Indo.

---

### 4. **Passage Re-ranking with BERT**

_Nogueira, R., et al. (2019). arXiv:1901.04085._

> **Kontribusi:** Menggunakan BERT untuk reranking hasil retrieval berbasis similarity.  
> **Gap:** Reranking belum diintegrasikan ke pipeline prompt retrieval.  
> **Relevansi:** Bisa menambah reranking BERT setelah FAISS untuk hasil lebih relevan.

---

### 5. **A Deep Look into Neural Ranking Models for Information Retrieval**

_Guo, J., et al. (2020). SIGIR._

> **Kontribusi:** Survei model ranking neural untuk IR, membahas arsitektur dan evaluasi.  
> **Gap:** Belum ada eksplorasi khusus untuk prompt/code retrieval dan ensemble multi-model.  
> **Relevansi:** Pipeline bisa mengadopsi reranking dan analisis diversity dari paper ini.

---

### 6. **Code Retrieval with Semantic and Syntactic Features**

_Sun, Z., et al. (2022). IEEE Access._

> **Kontribusi:** Menggabungkan embedding semantic dan fitur sintaksis untuk code retrieval.  
> **Gap:** Pipeline ini baru pakai semantic embedding, bisa ditambah sintaksis (AST, token, dsb).

---

### 7. **Prompt-based Learning for Natural Language Processing: A Survey**

_Liu, X., et al. (2021). ACM Computing Surveys._

> **Kontribusi:** Survei teknik prompt-based learning dan evaluasi prompt engineering.  
> **Gap:** Belum ada eksplorasi retrieval prompt similarity untuk code generation.  
> **Relevansi:** Pipeline bisa dieksplorasi untuk prompt engineering dan retrieval-aware code generation.

---

## ✨ **Summary Gap & Recommendation**

- Fine-tuning SBERT/LaBSE pada data prompt/code lokal.
- Integrasi reranking BERT setelah FAISS.
- Tambahkan fitur sintaksis (AST/token) untuk code retrieval.
- Benchmark pipeline pada dataset BEIR atau buat benchmark prompt/code Indo.
- Eksplorasi prompt engineering untuk retrieval-aware code generation.
