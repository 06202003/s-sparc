# SURVEY DATA ENTRY TEMPLATE & ANALYSIS SPREADSHEET

Panduan ini menunjukkan cara setup spreadsheet untuk data entry dan analisis.

---

## OPTION 1: GOOGLE SHEETS TEMPLATE

Buat new Google Sheet dengan struktur berikut:

### **Sheet 1: DATA ENTRY**

```
Column Headers (Row 1):

A: Responden_ID (format: R001, R002, dst)
B: Nama
C: NIM
D: Program_Studi
E: Tahun_Akademik

-- PRE-SURVEY BASIC INFO --
F: A5_AI_Frequency (1-5: 1=tidak pernah, 5=setiap hari)
G: A6_Code_Generation (Y/N)
H: A6_Learning (Y/N)
I: A6_Brainstorming (Y/N)
J: A6_Other (text)

-- PRE-SURVEY: ENVIRONMENTAL CONSCIOUSNESS (B1-B6) --
K: B1_Peduli_Lingkungan (1-5)
L: B2_Carbon_Intensive (1-5)
M: B3_Energy_Efficiency (1-5)
N: B4_Developer_Responsibility (1-5)
O: B5_Know_Energy (1-5)
P: B6_Kurangi_Footprint (1-5)

Pre_Consciousness_Score = AVERAGE(K:P)

-- PRE-SURVEY: KNOWLEDGE (C1-C5) --
Q: C1_Carbon_Definition (0/1: 0=salah, 1=benar [B])
R: C2_Energy_Efficient (0/1: benar jika C atau sesuai)
S: C3_PUE_Definition (0/1: benar jika A)
T: C4_Carbon_Query (0/1: benar jika B)
U: C5_Environmental_Factor (0/1: benar jika C)

Pre_Knowledge_Score = SUM(Q:U)

-- PRE-SURVEY: ATTITUDE (D1-D6) --
V: D1_Mau_Belajar (1-5)
W: D2_Careful_Info (1-5)
X: D3_Curriculum (1-5)
Y: D4_Sacrifice_Efficiency (1-5)
Z: D5_Transparency (1-5)
AA: D6_Trend_Gimmick (1-5, REVERSE CODED)

Pre_Attitude_Score = (V+W+X+Y+Z+(6-AA))/6

-- PRE-SURVEY: BASELINE BEHAVIOR --
AB: E1_AI_Weekly (1-5 scale)
AC: E2_Review_Or_Copy (text)
AD: E3_Search_Before_AI (1-5)
AE: E4_Preference (text)

-- POST-SURVEY EXPOSURE --
AF: B1_Usage_Frequency (1-4)
AG: B2_Features_Used (text: multiple selections)
AH: B3_View_Metrics_Frequency (1-5)
AI: B4_Read_Understand_Info (1-5)

-- POST-SURVEY: KNOWLEDGE (C1-C5) --
AJ: PostC1_Carbon_Definition (0/1)
AK: PostC2_Energy_Efficient (0/1)
AL: PostC3_PUE_Definition (0/1)
AM: PostC4_Carbon_Query (0/1)
AN: PostC5_Environmental_Factor (0/1)

Post_Knowledge_Score = SUM(AJ:AN)
Knowledge_Gain = Post_Knowledge_Score - Pre_Knowledge_Score

-- POST-SURVEY: CONSCIOUSNESS (D1-D6) --
AO: D1_Peduli (1-5)
AP: D2_Carbon_Intensive (1-5)
AQ: D3_Energy_Efficiency (1-5)
AR: D4_Developer_Responsibility (1-5)
AS: D5_Know_Energy (1-5)
AT: D6_Reduce_Footprint (1-5)
AU: D7_Awareness_Change (1-5)

Post_Consciousness_Score = AVERAGE(AO:AU)
Consciousness_Change = Post_Consciousness_Score - Pre_Consciousness_Score

-- POST-SURVEY: ATTITUDE (E1-E7) --
AV: E1_Mau_Belajar (1-5)
AW: E2_Careful_Info (1-5)
AX: E3_Curriculum (1-5)
AY: E4_Sacrifice_Efficiency (1-5)
AZ: E5_Transparency (1-5)
BA: E6_Trend_Gimmick (1-5, REVERSE)
BB: E7_Choose_Sustainable (1-5)

Post_Attitude_Score = (AV+AW+AX+AY+AZ+(6-BA)+BB)/7
Attitude_Change = Post_Attitude_Score - Pre_Attitude_Score

-- POST-SURVEY: BEHAVIOR & GAMIFICATION --
BC: F1_Efficiency_Attempts (count: 1-4)
BD: F2_Code_Reuse_Frequency (1-5)
BE: F3_Semantic_Search_Frequency (1-5)
BF: F4_Semantic_Satisfaction (1-5)
BG: F5_Stop_And_Think (1-5)
BH: F6_Change_Other_Tools (1-5)

Behavior_Change_Score = (BE + BC + BG) / 3 * 100

BI: G1_Leaderboard_Frequency (1-5)
BJ: G2_Leaderboard_Effect (1-5)
BK: G3_Motivated (1-5)
BL: G4_Gamification_Quality (1-5)

-- POST-SURVEY: TRANSPARENCY & IMPACT --
BM: H1_Transparency (1-5)
BN: H2_Trust_Numbers (1-5)
BO: I1_System_Helpful (1-5)
BP: I2_Most_Impactful_Feature (text)
BQ: I3_Recommend (1-5)
BR: I4_Continue_Using (1-5)
BS: L1_Mindset_Change_Percent (%)
BT: L2_Satisfaction (1-5)
BU: L3_Rating (1-10)
BV: L4_Educational_Value (1-5)

-- COMPUTED COMPOSITE SCORES --
BW: Overall_Consciousness_Change = AU - Pre_Consciousness_Score
BX: Overall_Knowledge_Gain = Post_Knowledge_Score - Pre_Knowledge_Score
BY: Overall_Behavior_Change = (BE + BC + BG) / 3
BZ: Overall_Attitude_Change = Post_Attitude_Score - Pre_Attitude_Score

CA: Success_Criteria_Met (IF(BY > 3, "YES", "NO"))

-- PRE-SURVEY: REFERENSI DOSEN (Q01-Q12, Likert 1-5) --
CB: R01_Computational_Power (1-5)
CC: R02_Environmental_Harm (1-5)
CD: R03_Clear_Focused_Prompts (1-5)
CE: R04_Prompt_Examples (1-5)
CF: R05_Productive_Use (1-5)
CG: R06_No_Trivial_Use (1-5)
CH: R07_Greetings_Wasteful (1-5)
CI: R08_Repeated_Computation (1-5)
CJ: R09_Store_Results (1-5)
CK: R10_Document_Upload_Cost (1-5)
CL: R11_Media_Upload_Cost (1-5)
CM: R12_Multimedia_Response_Heavy (1-5)

Pre_Responsible_Score = AVERAGE(CB:CM)

-- POST-SURVEY: REFERENSI DOSEN (Q01-Q12, Likert 1-5) --
CN: PostR01_Computational_Power (1-5)
CO: PostR02_Environmental_Harm (1-5)
CP: PostR03_Clear_Focused_Prompts (1-5)
CQ: PostR04_Prompt_Examples (1-5)
CR: PostR05_Productive_Use (1-5)
CS: PostR06_No_Trivial_Use (1-5)
CT: PostR07_Greetings_Wasteful (1-5)
CU: PostR08_Repeated_Computation (1-5)
CV: PostR09_Store_Results (1-5)
CW: PostR10_Document_Upload_Cost (1-5)
CX: PostR11_Media_Upload_Cost (1-5)
CY: PostR12_Multimedia_Response_Heavy (1-5)

Post_Responsible_Score = AVERAGE(CN:CY)
Responsible_Change = Post_Responsible_Score - Pre_Responsible_Score
```

---

## OPTION 2: EXCEL TEMPLATE STRUCTURE

Jika prefer Excel, buat:

**Sheet "Data Entry"**

- Rows: 1 header, 2-51 untuk data (max 50 responden)
- Columns: A-CA (seperti template di atas)

**Sheet "Validation"**

```
Column A: Field Name
Column B: Data Type (number 1-5, 0/1, text, %)
Column C: Valid Range (e.g., "1-5", "0/1")
Column D: Notes

Contoh:
| B1_Peduli | number 1-5 | 1-5 | Likert scale consciousness |
| Pre_Know  | number     | 0-5 | Sum of 5 binary items |
```

**Sheet "Formulas"**

- Koleksi formula untuk perhitungan
- Copy-paste ke main sheet setelah data entry

**Sheet "Summary Statistics"**

- Tabel dengan mean, SD, min, max
- Paired t-test results
- Effect sizes

---

## BAGIAN 3: SAMPLE DATA ENTRY (3 responden)

```
Responden_ID | Nama | NIM | ... | B1_Peduli_Lingkungan | B2_Carbon | ... | Post_Consciousness
R001         | Andi | 001 | ... | 3                     | 3         | ... | 4
R002         | Budi | 002 | ... | 2                     | 2         | ... | 4
R003         | Citra| 003 | ... | 4                     | 4         | ... | 5
```

---

## BAGIAN 4: ANALYSIS FORMULAS (Google Sheets / Excel)

### **Descriptive Statistics**

```
=AVERAGE(K2:K51)          // Mean Pre_Consciousness
=STDEV.S(K2:K51)          // SD Pre_Consciousness
=MIN(K2:K51)              // Min
=MAX(K2:K51)              // Max
=MEDIAN(K2:K51)           // Median
```

### **Paired T-Test**

```
Google Sheets:
=T.TEST(K2:K51, AO2:AO51, 2, 1)
// Returns p-value
// 2 = two-tailed
// 1 = paired

Excel:
=T.TEST(K2:K51, AO2:AO51, 2, 1)
```

### **Cronbach's Alpha (Internal Consistency)**

```
Untuk 6-item Consciousness scale (Pre):
=((6/(6-1)) * (VAR(K2:K51)+VAR(L2:L51)+VAR(M2:M51)+VAR(N2:N51)+VAR(O2:O51)+VAR(P2:P51)) - VAR(K2:P51)) / VAR(K2:P51)

Or use CORREL for simpler version:
=AVERAGE(CORREL(K2:K51, L2:L51), CORREL(K2:K51, M2:M51), ... )
// Butuh adjustment faktor untuk true alpha
```

Lebih mudah gunakan Python/R untuk ini.

### **Effect Size (Cohen's d)**

```
Pre_Mean = AVERAGE(K2:K51)
Post_Mean = AVERAGE(AO2:AO51)
SD_Pre = STDEV.S(K2:K51)
SD_Post = STDEV.S(AO2:AO51)

SD_Pooled = SQRT(((49*SD_Pre^2 + 49*SD_Post^2) / 98))
Cohen_d = (Post_Mean - Pre_Mean) / SD_Pooled
```

---

## BAGIAN 5: PYTHON SCRIPT UNTUK ANALISIS (RECOMMENDED)

Buat file `analyze_survey.py`:

```python
import pandas as pd
import numpy as np
from scipy import stats
import matplotlib.pyplot as plt

# Load data
df = pd.read_csv('survey_data.csv')

# Descriptive Statistics
print("=== PRE-SURVEY CONSCIOUSNESS ===")
pre_consciousness = df[['B1','B2','B3','B4','B5','B6']].mean(axis=1)
print(f"Mean: {pre_consciousness.mean():.2f}")
print(f"SD: {pre_consciousness.std():.2f}")
print(f"Min: {pre_consciousness.min()}, Max: {pre_consciousness.max()}")

print("\n=== POST-SURVEY CONSCIOUSNESS ===")
post_consciousness = df[['D1','D2','D3','D4','D5','D6','D7']].mean(axis=1)
print(f"Mean: {post_consciousness.mean():.2f}")
print(f"SD: {post_consciousness.std():.2f}")

# Paired T-Test
t_stat, p_value = stats.ttest_rel(post_consciousness, pre_consciousness)
print(f"\nPaired T-Test:")
print(f"t = {t_stat:.3f}, p = {p_value:.4f}")

# Cohen's d
sd_pooled = np.sqrt(((len(df)-1)*pre_consciousness.std()**2 +
                      (len(df)-1)*post_consciousness.std()**2) / (2*len(df)-2))
cohens_d = (post_consciousness.mean() - pre_consciousness.mean()) / sd_pooled
print(f"Cohen's d = {cohens_d:.3f}")

# Knowledge Analysis
pre_knowledge = df[['C1','C2','C3','C4','C5']].sum(axis=1)
post_knowledge = df[['PostC1','PostC2','PostC3','PostC4','PostC5']].sum(axis=1)
print(f"\n=== KNOWLEDGE ===")
print(f"Pre: {pre_knowledge.mean():.2f}/5")
print(f"Post: {post_knowledge.mean():.2f}/5")
print(f"Gain: {(post_knowledge - pre_knowledge).mean():.2f} points")

# Behavior Change
print(f"\n=== BEHAVIOR CHANGE ===")
behavior_score = (df['F3'] + df['F1'] + df['F5']) / 3
print(f"Mean Behavior Score: {behavior_score.mean():.2f}/5")

# Responsible GenAI (lecturer references, Q01-Q12) if columns present
resp_pre_cols = [c for c in df.columns if c.startswith('R0') and not c.startswith('PostR0')]
resp_post_cols = [c for c in df.columns if c.startswith('PostR0')]
if resp_pre_cols and resp_post_cols:
    pre_resp = df[resp_pre_cols].mean(axis=1)
    post_resp = df[resp_post_cols].mean(axis=1)
    t_resp, p_resp = stats.ttest_rel(post_resp, pre_resp)
    sd_pooled_resp = np.sqrt(((len(df)-1)*pre_resp.std()**2 + (len(df)-1)*post_resp.std()**2) / (2*len(df)-2))
    d_resp = (post_resp.mean() - pre_resp.mean()) / sd_pooled_resp
    print(f"\n=== RESPONSIBLE GENAI (Q01-Q12) ===")
    print(f"Pre:  {pre_resp.mean():.2f} | Post: {post_resp.mean():.2f} | Change: {(post_resp - pre_resp).mean():.2f}")
    print(f"t = {t_resp:.3f}, p = {p_resp:.4f}, d = {d_resp:.3f}")
else:
    print("\n[Info] Responsible GenAI items (Q01-Q12) not found in dataset; skipping.")

# Visualization
fig, axes = plt.subplots(2, 2, figsize=(12, 10))

# Consciousness change
axes[0,0].bar(['Pre', 'Post'],
              [pre_consciousness.mean(), post_consciousness.mean()],
              color=['blue', 'green'])
axes[0,0].set_title('Environmental Consciousness')
axes[0,0].set_ylabel('Mean Score (1-5)')

# Knowledge gain
axes[0,1].bar(['Pre', 'Post'],
              [pre_knowledge.mean(), post_knowledge.mean()],
              color=['blue', 'green'])
axes[0,1].set_title('Knowledge (0-5)')
axes[0,1].set_ylabel('Score')

# Attitude change
pre_attitude = df[['D1','D2','D3','D4','D5','D6']].apply(
    lambda row: (row['D1']+row['D2']+row['D3']+row['D4']+row['D5']+(6-row['D6']))/6, axis=1
)
post_attitude = df[['E1','E2','E3','E4','E5','E6','E7']].apply(
    lambda row: (row['E1']+row['E2']+row['E3']+row['E4']+row['E5']+(6-row['E6'])+row['E7'])/7, axis=1
)
axes[1,0].bar(['Pre', 'Post'],
              [pre_attitude.mean(), post_attitude.mean()],
              color=['blue', 'green'])
axes[1,0].set_title('Attitude towards Sustainability')
axes[1,0].set_ylabel('Mean Score (1-5)')

# Behavior intention
axes[1,1].hist(behavior_score, bins=10, color='teal', edgecolor='black')
axes[1,1].set_title('Behavioral Intention Distribution')
axes[1,1].set_xlabel('Score (1-5)')
axes[1,1].set_ylabel('Frequency')

plt.tight_layout()
plt.savefig('survey_analysis.png', dpi=300)
plt.show()

print("\n✓ Analysis complete. Chart saved to survey_analysis.png")
```

---

## BAGIAN 6: CHECKLIST SEBELUM ANALISIS

- [ ] Semua survey responses sudah di-enter ke spreadsheet
- [ ] Check data types (numbers vs text)
- [ ] Identify missing values (NA)
- [ ] Validate range (likert harus 1-5, knowledge harus 0/1)
- [ ] Reverse code D6, E6 jika belum
- [ ] Hitung composite scores
- [ ] Run descriptive statistics
- [ ] Run paired t-tests
- [ ] Calculate effect sizes
- [ ] Create visualizations
- [ ] Thematic analysis untuk open-ended
- [ ] Write up findings

---

## BAGIAN 7: REPORTING TEMPLATE

**Table untuk Results Section:**

```
Table 1: Descriptive Statistics for Pre and Post Measures

                        Pre (n=45)      Post (n=45)     Change    p-value    Cohen's d
                        M±SD            M±SD            M±SD
Environmental           2.8±1.2         3.9±1.0         +1.1±0.8  0.018*    0.92
 Consciousness

Knowledge               1.8±1.3         3.5±1.2         +1.7±1.4  <0.001**  1.26
 (0-5 scale)

Attitude                3.2±0.9         4.1±0.8         +0.9±0.7  0.032*    1.04
 towards Sustainability

Behavioral              --              3.6±1.1         --        --        --
 Intention
 (Post only)

* p < 0.05, ** p < 0.01
```

---

**Semua file template dan guide sudah siap untuk research Anda!** 📊
