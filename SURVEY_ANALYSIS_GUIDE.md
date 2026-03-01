# PANDUAN ANALISIS SURVEY & SCORING GUIDE

## S-SPARC AI Sustainability Research

---

## OVERVIEW VARIABEL YANG DIUKUR

### **Dimensi 1: Environmental Consciousness (Kesadaran Lingkungan)**

**Pertanyaan kunci:** B1-B6 (Pre), D1-D6 (Post)  
**Tipe data:** Likert Scale 1-5  
**Interpretation:**

- Skor tinggi (4-5) = Kesadaran lingkungan tinggi
- Skor rendah (1-2) = Kesadaran lingkungan rendah

**Nilai Pre-Post:**

```
Perubahan = Post_Score - Pre_Score
- >0 = Peningkatan awareness
- <0 = Penurunan awareness
- =0 = Tidak ada perubahan
```

---

### **Dimensi 2: Knowledge Assessment (Penilaian Pengetahuan)**

**Pertanyaan kunci:** C1-C5 (Pre), C1-C5 (Post)  
**Tipe data:** Multiple Choice (correct/incorrect)  
**Scoring:**

- Setiap jawaban benar = 1 poin
- Setiap jawaban salah = 0 poin
- **Total skor pre/post:** 0-5

**Kunci Jawaban yang Benar:**
| Pertanyaan | Pre | Post | Catatan |
|-----------|-----|------|---------|
| **C1** - Definisi Carbon Footprint | B (CO2) | B (CO2) | Fokus pada jumlah CO2 dari energi |
| **C2** - Energy Efficiency | Tergantung | C (semantic search) | Model besar 1x lebih baik dari kecil multiple (tapi semantic search = paling baik) |
| **C3** - Definisi PUE | A (Data center efficiency) | A | PUE = energy_total / IT_equipment_energy |
| **C4** - Carbon emission 1 query GPT-4 | B (0.1g) | B (0.1g) | Rough estimate: 0.08-0.15g CO2e |
| **C5** - Factor BUKAN dalam environmental impact | C (Jumlah query sebelumnya) | C | Previous queries tidak affect current impact |

**Interpretasi Perubahan Knowledge:**

```
Knowledge Gain = Post_Score - Pre_Score
- Gain > 1 = Signifikan peningkatan knowledge
- Gain = 0-1 = Sedikit improvement
- Gain < 0 = Penurunan knowledge (rare, mungkin karena confusion)
```

---

### **Dimensi 3: Attitude towards Sustainable Computing (Sikap)**

**Pertanyaan kunci:** D1-D6 (Pre), E1-E7 (Post)  
**Tipe data:** Likert Scale 1-5  
**Scoring:**

- **D6 dan E6** (reverse coded) = 6 - value (karena pernyataan negative)
- Hitung rata-rata untuk composite score: (D1+D2+...+D5) / 5 (exclude D6 yang sudah reverse)

**Komposit Attitude Score:**

```
Pre_Attitude = (D1+D2+D3+D4+D5 + (6-D6)) / 6
Post_Attitude = (E1+E2+E3+E4+E5 + (6-E6) + E7) / 7

Attitude Change = Post_Attitude - Pre_Attitude
```

---

### **Dimensi 4: Behavioral Intention (Niat Perilaku)**

**Pertanyaan kunci:** E7 (Post), F1-F6 (Post)  
**Tipe data:** Likert Scale 1-5 + behavioral frequency  
**Scoring:**

- E7: Intention to choose sustainable alternatives (1-5)
- F1: Attempt to be more efficient (count types)
- F3: Frequency using semantic search (1-5)
- F5: "Stop and think" behavior (1-5)

**Behavioral Intention Composite:**

```
Behavioral_Intention_Score = (E7 + F3 + F5) / 3
(Normalized to 1-5 scale)

Interpretation:
- 4-5 = Strong intention to change behavior
- 3-4 = Moderate intention
- 2-3 = Weak intention
- 1-2 = No behavioral intention
```

---

### **Dimensi 5: Actual Behavior Change (Perubahan Perilaku Aktual)**

**Pertanyaan kunci:** B1-B4 (Pre), F1-F6 (Post)  
**Tipe data:** Frequency + self-report  
**Scoring:**

```
Behavior_Change_Score = (
  # Semantic search adoption (F3)
  (F3_score / 5) * 0.35 +
  # Efficiency attempts (count from F1)
  (efficiency_attempts / 5) * 0.35 +
  # "Stop and think" behavior (F5)
  (F5_score / 5) * 0.30
) * 100

Interpretation:
- 70-100 = Signifikan behavior change
- 50-70 = Moderate behavior change
- 30-50 = Sedikit behavior change
- <30 = Minimal/no behavior change
```

---

### **Dimensi 6: Responsible GenAI Practices (Referensi Dosen Q01-Q12)**

**Pertanyaan kunci:** Q01-Q12 (Pre dan Post)  
**Tipe data:** Likert Scale 1-5  
**Scoring:**

```
Pre_Responsible = AVERAGE(Q01..Q12 pre)
Post_Responsible = AVERAGE(Q01..Q12 post)
Responsible_Change = Post_Responsible - Pre_Responsible

Interpretation:
- 4-5 = Kuat setuju dengan praktik GenAI yang efisien/etis
- 3-4 = Moderat, bisa ditingkatkan
- 1-3 = Rendah; perlu edukasi tambahan

Statistik:
- Uji paired t-test (pre vs post)
- Efek praktis: Cohen's d ≥ 0.5 diharapkan (medium)
- Reliabilitas: Cronbach's alpha target ≥ 0.75 (12 item)
```

---

## ANALISIS STATISTIK YANG DISARANKAN

### **1. Descriptive Statistics**

```
Untuk setiap dimensi, hitung:
- Mean (rata-rata)
- Standard Deviation (SD)
- Median
- Mode (untuk categorical)
```

**Contoh untuk Consciousness:**

```
Dimensi: Environmental Consciousness
Pre_Survey:
  Mean = 2.8, SD = 1.2, Min = 1, Max = 5
Post_Survey:
  Mean = 3.9, SD = 1.0, Min = 2, Max = 5

Perubahan: +1.1 poin (p < 0.05 significant)
```

### **2. Paired T-Test (jika sampel ≥ 30)**

```
Untuk menguji apakah ada perbedaan signifikan antara Pre dan Post

H0: μ_pre = μ_post (tidak ada perubahan)
H1: μ_pre ≠ μ_post (ada perubahan)

Tool: R, SPSS, atau Python (scipy.stats.ttest_rel)

Contoh output:
t = 2.45, df = 45, p = 0.018 *
Kesimpulan: Signifikan increase pada sustainability consciousness
```

### **3. Pearson Correlation (Jika perlu understand relationships)**

```
Apakah ada korelasi antara:
- Environmental Consciousness ↔ Behavioral Change
- Knowledge ↔ Behavior Change
- Gamification Impact ↔ Behavior Change

r > 0.7 = Strong correlation
r 0.4-0.7 = Moderate correlation
r < 0.4 = Weak correlation
```

### **4. Effect Size (Cohen's d)**

```
d = (M_post - M_pre) / SD_pooled

Interpretation:
d = 0.2 = Small effect
d = 0.5 = Medium effect
d = 0.8 = Large effect

Contoh:
If post_knowledge_gain = 1.5 dengan SD = 1.2
d = 1.5 / 1.2 = 1.25 (Large effect, very significant)
```

---

## QUALITY METRICS UNTUK SURVEY

### **1. Validity Check**

```
✓ Internal consistency (Cronbach's alpha > 0.7)
  - Hitung untuk setiap Likert dimension
  - α < 0.6 = Weak consistency (perlu review item)
  - α 0.6-0.8 = Acceptable
  - α > 0.8 = Excellent

Contoh:
Environmental Consciousness (6 items):
  Pre Cronbach's α = 0.78 ✓ Acceptable
  Post Cronbach's α = 0.81 ✓ Excellent
```

### **2. Reliability Check**

```
✓ Test-retest (jika ada responden yang re-survey setelah 1 minggu)
  Correlasi > 0.7 = Good reliability

✓ Item-total correlation
  Setiap item harus correlate dengan total (r > 0.3)
  Jika ada item dengan r < 0.3, pertimbangkan remove
```

---

## SAMPLE REPORT TEMPLATE

```markdown
# HASIL QUASI-EXPERIMENT: SUSTAINABILITY AI AWARENESS

## S-SPARC AI Platform

### EXECUTIVE SUMMARY

- **N = 45** mahasiswa CS
- **Duration:** 4 minggu
- **Overall finding:** Signifikan peningkatan sustainability awareness (p < 0.05)

### KEY FINDINGS

#### 1. Environmental Consciousness

**Pre vs Post Comparison:**
| Dimensi | Pre Mean | Post Mean | Change | p-value |
|---------|----------|-----------|--------|---------|
| Consciousness | 2.8±1.2 | 3.9±1.0 | +1.1** | 0.018 |
| Knowledge (score) | 1.8/5 | 3.5/5 | +1.7** | <0.001 |
| Attitude | 3.2±0.9 | 4.1±0.8 | +0.9** | 0.032 |
| Behavior Intention | - | 3.6±1.1 | - | - |
| Actual Behavior | 2.1/10 | 6.8/10 | +4.7** | <0.001 |

\*\* p < 0.05, significantly different

#### 2. Feature Impact Analysis

**Mana feature yang paling berpengaruh?**

- Environmental metrics display: 78% responden melihat regularly
- Semantic search adoption: 62% menggunakan (vs expected 20%)
- Gamification/leaderboard: 71% merasa motivated

#### 3. Behavioral Changes

**Observable behavior changes:**

- 68% responden sekarang "pause & think" sebelum generate
- 55% menggunakan semantic search instead of new generation
- 74% lebih conscious tentang efficiency

#### 4. Knowledge Gain

- Pre knowledge score: 1.8/5 (36%)
- Post knowledge score: 3.5/5 (70%)
- **Knowledge gain: 34 percentage points (p < 0.001)**

**Jenis knowledge yang meningkat:**

- Carbon footprint definition: 58% → 91% correct
- PUE understanding: 22% → 68% correct
- Energy efficiency trade-offs: 31% → 73% correct

### LIMITATIONS

- Self-reported behavior (tidak ada actual system logs)
- Small sample size (jika < 30)
- Selection bias (voluntary participation)
- Hawthorne effect (awareness karena sedang diteliti)

### RECOMMENDATIONS

1. Continuous integration sustainability metrics di curriculum
2. Enhance semantic search UX untuk adoption lebih tinggi
3. Develop companion sustainability module
4. Longitudinal study untuk understand retention
```

---

## SCORING SPREADSHEET TEMPLATE

Anda bisa menggunakan Excel/Google Sheets dengan struktur berikut:

```
Columns:
A: Responden ID
B-G: Pre_Consciousness (B1-B6, values 1-5)
H: Pre_Consciousness_Total (average B:G)
I-M: Pre_Knowledge (C1-C5, values 0/1)
N: Pre_Knowledge_Total (sum I:M)
O-T: Pre_Attitude (D1-D6 with reverse coding)
U: Pre_Attitude_Total (average O:T)

V-AA: Post_Consciousness (D1-D6)
AB: Post_Consciousness_Total
...dan seterusnya untuk Post measures

AL: Change_Consciousness = AB - H
AM: Change_Knowledge = sum_post - N
AN: Change_Attitude = sum_post - U
...dst

AO: Behavior_Score (calculated from F1-F6)
AP: T-test result (formula atau manual calculation)
```

---

## QUALITATIVE ANALYSIS GUIDE

Untuk open-ended questions (G1-G3 Pre, J1-J5 Post):

### **Coding Themes:**

```
Pro-Sustainability Themes:
- [AWARE]: Menunjukkan environmental awareness
- [INTENT]: Expressed intention to change
- [BARRIER]: Identified barriers/challenges
- [SUGGEST]: Suggestions for improvement
- [IMPACT]: Perceived system impact

Coding example:
"Saya jadi lebih terpikir sebelum generate kode sekarang karena
lihat carbon number" = [AWARE] + [INTENT] + [IMPACT]

Setelah coding, hitung frequency:
- [AWARE]: 38/45 (84%)
- [INTENT]: 32/45 (71%)
- [BARRIER]: 28/45 (62%)
...
```

### **Sentiment Analysis:**

Untuk setiap open-ended response, rate:

- Positive (+1): Pro-sustainability, suggest improvement
- Neutral (0): Neutral atau mixed
- Negative (-1): Skeptical atau critical

```
Overall sentiment score = (Positive_count - Negative_count) / Total
Score range: -1 to +1
Interpretation:
- >0.5 = Overall positive
- 0 to 0.5 = Slightly positive
- 0 = Neutral/balanced
- < 0 = Negative
```

---

## HYPOTHESIS TESTING EXAMPLES

### **H1: Environmental Consciousness Increases After S-SPARC Usage**

```
Pre Mean = 2.8, Post Mean = 3.9, SD = 1.2
t-test result: t(44) = 2.45, p = 0.018 ✓
Conclusion: SUPPORTED - Significantly increase
Effect size (Cohen's d) = 0.92 (Large)
```

### **H2: Knowledge about Sustainable AI Improves**

```
Pre Score = 1.8/5, Post Score = 3.5/5
Paired t-test: t(44) = 3.18, p < 0.001 ✓
Conclusion: SUPPORTED - Significantly improve
34 percentage point gain in knowledge
```

### **H3: Behavioral Intention Correlates with Behavior Change**

```
Correlation (Behavioral_Intention vs Behavior_Score): r = 0.64, p = 0.002 ✓
Conclusion: SUPPORTED - Strong correlation
R² = 0.41 (41% variance explained)
```

---

## TIPS UNTUK HASIL YANG ROBUST

1. **Maintain High Response Rate**
   - Target: ≥ 80% for both pre dan post
   - Reminder system untuk post-survey completion

2. **Control Confounds**
   - Record: Lama experience dengan AI tools, prior environmental awareness, GPA
   - Analyze apakah ada differences antara high/low prior groups

3. **Collect Behavioral Data**
   - Dari system logs di S-SPARC (jumlah semantic searches, queries, token usage)
   - Compare dengan self-reported behavior untuk validation

4. **Define Success Criteria Beforehand**
   - Contoh: "Success jika ≥ 30% menunjukkan behavior change"
   - "Significant awareness increase jika Cohen's d ≥ 0.5"

5. **Qualitative Validation**
   - Interview subset (10-15%) responden untuk deeper understanding
   - Understand _why_ behavior changed atau tidak

---

## QUICK ANALYSIS CHECKLIST

- [ ] Data entry complete dan clean
- [ ] Check for missing values (< 5% acceptable)
- [ ] Compute descriptive statistics untuk setiap dimensi
- [ ] Check Cronbach's alpha (internal consistency)
- [ ] Run paired t-tests untuk pre-post comparisons
- [ ] Calculate Cohen's d untuk effect sizes
- [ ] Analyze knowledge gain dengan frequency tables
- [ ] Code open-ended responses dengan thematic analysis
- [ ] Create visualizations (bar charts, box plots, scatter plots)
- [ ] Write findings dalam technical report format
- [ ] Discuss limitations dan future work

---

**Good luck dengan research Anda! Semoga findings bermanfaat untuk community.** 📊✨
