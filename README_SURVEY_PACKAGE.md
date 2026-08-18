# COMPLETE SURVEY & RESEARCH PACKAGE - SUMMARY CHECKLIST

## S-SPARC AI Quasi-Experiment on Sustainability Awareness

---

## 📦 WHAT YOU NOW HAVE

Saya telah membuat **5 dokumen comprehensive** untuk quasi-experiment Anda:

### **1. SUSTAINABILITY_AI_SURVEY_PRE.md** ✅

**Purpose:** Pre-intervention baseline assessment  
**Contents:**

- 7 bagian (A-H)
- 40+ pertanyaan mencakup:
  - Demographic info
  - Environmental consciousness (Likert 1-5)
  - Knowledge assessment (multiple choice)
  - Attitude terhadap sustainability
  - Behavior & usage patterns baseline
  - Perception tentang tools
  - Open-ended feedback
- **Durasi:** 10-15 menit
- **Format:** Markdown + bisa langsung di Google Forms/Qualtrics

### **2. SUSTAINABILITY_AI_SURVEY_POST.md** ✅

**Purpose:** Post-intervention measurement + change assessment  
**Contents:**

- 12 bagian (A-L)
- 45+ pertanyaan mencakup:
  - Exposure & usage metrics
  - Knowledge assessment (same items as pre untuk comparison)
  - Environmental consciousness (perubahan)
  - Attitude changes
  - Behavior & gamification impact
  - Transparency & trust assessment
  - System impact evaluation
  - Demographic tracking for follow-up
  - Comparative perception changes
- **Durasi:** 15-20 menit
- **Special features:** Matched items dengan pre-survey untuk paired analysis

### **3. SURVEY_ANALYSIS_GUIDE.md** ✅

**Purpose:** Statistical & qualitative analysis methodology  
**Contents:**

- Overview semua 5 dimensi yang diukur:
  1. Environmental Consciousness (Likert)
  2. Knowledge Assessment (Multiple choice scoring)
  3. Attitude (Composite scores)
  4. Behavioral Intention (Post-only)
  5. Actual Behavior Change (Frequency + logs)
- **Analisis statistik:**
  - Descriptive stats template
  - Paired t-test methodology
  - Correlation analysis
  - Effect size (Cohen's d) calculations
- **Quality metrics:**
  - Cronbach's alpha (internal consistency)
  - Reliability checks
  - Validity considerations
- **Report template** siap pakai
- **Hypothesis testing framework** dengan contoh
- **Scoring checklist** untuk quality assurance

### **4. SURVEY_DATA_ENTRY_TEMPLATE.md** ✅

**Purpose:** Spreadsheet setup & data management  
**Contents:**

- **Google Sheets structure** (A-CA columns) dengan:
  - Pre-survey items (B-U)
  - Post-survey items (V-AT)
  - Composite score calculations
  - Analysis formulas siap pakai
- **Excel template** dengan validation sheets
- **Sample data entry** (3 responden example)
- **Analysis formulas** (descriptive stats, t-tests, Cohen's d)
- **Python script** (complete `analyze_survey.py`) untuk automated analysis:
  - Descriptive statistics
  - Paired t-tests
  - Effect sizes
  - Visualization (matplotlib)
  - Cronbach's alpha
- **Reporting table template** siap copy-paste ke paper

### **5. SURVEY_ADMINISTRATION_QUICK_GUIDE.md** ✅

**Purpose:** Operational checklist & best practices  
**Contents:**

- **Timeline:** Week-by-week planning (8 weeks total)
- **Checklists:**
  - Pre-survey execution (setup, hari H, follow-up)
  - Intervention phase monitoring (Weeks 3-5)
  - Post-survey execution
- **Templates:** Email, consent form, recruitment, reminders
- **Troubleshooting:** Common issues & solutions
  - Responder dropout
  - Technical issues
  - Low response rates
  - Data quality issues
- **Best practices** untuk data quality
- **Success metrics** (targets):
  - ≥85% pre-survey response
  - ≥90% intervention completion
  - ≥80% post-survey response
  - p<0.05 significance threshold
  - Cohen's d≥0.5 effect size

### **6. SAMPLE_RESEARCH_REPORT.md** ✅

**Purpose:** Template laporan hasil penelitian  
**Contents:**

- **Full research report** dengan 7 bagian:
  1. Executive Summary
  2. Introduction (background, RQ, hypotheses)
  3. Literature Review
  4. Methodology (design, participants, measures)
  5. Results (descriptive stats, hypothesis testing, qualitative)
  6. Discussion (findings, mechanisms, limitations, implications)
  7. Conclusion + References
- **Example hasil lengkap** dengan:
  - Table of results (real-looking numbers)
  - Effect sizes & p-values
  - Knowledge gain analysis
  - Behavioral pattern breakdown
  - Feature satisfaction metrics
  - Qualitative themes (95% respondents)
  - Discussion of mechanisms
  - Recommendations for CS education
- **Appendix templates** untuk survey instruments, tables, coding schemes

---

## 🎯 HOW TO USE THESE DOCUMENTS

### **PHASE 1: PREPARATION (Week -1)**

```
1. Review SUSTAINABILITY_AI_SURVEY_PRE.md
2. Customize questions jika perlu (add institution-specific items)
3. Setup online form (Google Forms recommended):
   - Copy survey items
   - Set as required fields
   - Add branching logic (optional)
4. Test di 3 devices (mobile, tablet, desktop)
5. Print backup copies
6. Read SURVEY_ADMINISTRATION_QUICK_GUIDE.md fully
7. Prepare consent form & recruitment email (templates provided)
```

### **PHASE 2: PRE-SURVEY (Week 1)**

```
1. Follow Pre-Survey Checklist dari SURVEY_ADMINISTRATION_QUICK_GUIDE.md
2. Distribute survey (online + QR code + printed backup)
3. Collect responses (target: 45+ students)
4. Export data immediately
5. Open SURVEY_DATA_ENTRY_TEMPLATE.md
6. Entry data ke Google Sheets / Excel
7. Run data quality checks (completeness, ranges, missing values)
```

### **PHASE 3: INTERVENTION (Weeks 2-6)**

```
1. Students use S-SPARC AI normally
2. Refer to SURVEY_ADMINISTRATION_QUICK_GUIDE.md untuk:
   - Weekly monitoring
   - Engagement maintenance
   - Technical support
3. Export system logs (queries, semantic search usage, etc.)
4. Monitor compliance (check ~2x per week)
```

### **PHASE 4: POST-SURVEY (Week 7)**

```
1. Follow Post-Survey Checklist dari SURVEY_ADMINISTRATION_QUICK_GUIDE.md
2. Distribute SUSTAINABILITY_AI_SURVEY_POST.md
3. CRITICAL: Match responder IDs dengan pre-survey
4. Collect responses (target: 40+ matched pairs)
5. Entry data ke spreadsheet
6. Data quality checks
```

### **PHASE 5: ANALYSIS (Week 8)**

```
1. Open SURVEY_DATA_ENTRY_TEMPLATE.md
2. Use Google Sheets formulas OR run Python script:
   python analyze_survey.py
3. Follow SURVEY_ANALYSIS_GUIDE.md untuk:
   - Descriptive statistics
   - Paired t-tests
   - Knowledge gain analysis
   - Behavioral scoring
   - Qualitative coding
4. Create visualizations (bar charts, box plots, correlation plots)
5. Interpret results vis-a-vis hypotheses
```

### **PHASE 6: REPORTING (Week 9)**

```
1. Open SAMPLE_RESEARCH_REPORT.md
2. Fill in dengan hasil Anda:
   - Replace all M=2.8 with actual_mean_pre
   - Update p-values & t-statistics
   - Include actual quotes dari open-ended responses
3. Write up Discussion section (explain findings, limitations, implications)
4. Add your References
5. Submit untuk publication / presentation
```

---

## 📋 DETAILED CONTENT BREAKDOWN

### **Survey Dimensions Measured:**

| Dimensi                         | Pre Items | Post Items | Type            | Analysis                                 |
| ------------------------------- | --------- | ---------- | --------------- | ---------------------------------------- |
| **Environmental Consciousness** | B1-B6     | D1-D7      | Likert 1-5      | Paired t-test, effect size               |
| **Knowledge**                   | C1-C5     | C1-C5      | Multiple choice | Knowledge gain, percent correct          |
| **Attitude**                    | D1-D6     | E1-E7      | Likert 1-5      | Composite score, paired t-test           |
| **Behavioral Intention**        | -         | E7         | Likert 1-5      | Descriptive, correlation with behavior   |
| **Actual Behavior**             | E1-E4     | F1-F6      | Frequency/count | Behavior score, system logs validation   |
| **Gamification**                | -         | G1-G4      | Likert 1-5      | Moderator analysis, engagement metrics   |
| **System Evaluation**           | -         | H1-I4      | Likert/text     | Satisfaction metrics, qualitative themes |

### **Statistical Tests Ready to Run:**

1. **Paired t-tests** (Consciousness, Knowledge, Attitude)

   ```
   t(N-1) = (M_post - M_pre) / (SD_diff / √N)
   Compare: t_value dengan critical value (α=0.05, two-tailed)
   ```

2. **Cohen's d** (Effect size)

   ```
   d = (M_post - M_pre) / SD_pooled
   Interpret: 0.2=small, 0.5=medium, 0.8=large
   ```

3. **Cronbach's α** (Reliability)

   ```
   Untuk setiap Likert scale (consciousness, attitude)
   Target: α > 0.7
   ```

4. **Pearson r** (Correlation)

   ```
   Intention ↔ Behavior
   Leaderboard viewing ↔ Efficiency score
   Knowledge ↔ Behavior change
   ```

5. **Qualitative** (Thematic Analysis)
   ```
   Code open-ended responses with themes:
   [AWARE], [INTENT], [BARRIER], [IMPACT], etc.
   Report frequency & representative quotes
   ```

---

## ✅ IMPLEMENTATION CHECKLIST

### **Before Starting:**

- [ ] Read all 6 documents completely
- [ ] Understand the theoretical framework (TPB model)
- [ ] Review your research questions & hypotheses
- [ ] Confirm ethical approval from institution
- [ ] Identify target sample (aim for N≥45)
- [ ] Setup technology (Google Forms, Google Sheets, Python if using)

### **Pre-Survey Phase:**

- [ ] Customize survey (optional)
- [ ] Setup online form
- [ ] Test with 3 devices
- [ ] Print backups
- [ ] Prepare recruitment materials
- [ ] Get informed consent
- [ ] Distribute survey
- [ ] Target response rate: ≥85% (≥38 students)
- [ ] Export & backup data
- [ ] Enter into spreadsheet

### **Intervention Phase:**

- [ ] Setup S-SPARC access for all participants
- [ ] Provide orientation (15-30 min training)
- [ ] Monitor weekly (check logs, engagement)
- [ ] Send reminder emails (Week 2 & 4)
- [ ] Support technical issues
- [ ] Track usage (system logs)
- [ ] Document any issues/adjustments
- [ ] Confirm target usage (2-4x/week average)

### **Post-Survey Phase:**

- [ ] Prepare post-survey form
- [ ] Recruit for post-survey (remind 1 week before)
- [ ] Target response rate: ≥80% (≥32 matched pairs)
- [ ] CRITICAL: Match with pre-survey responders
- [ ] Export & backup data
- [ ] Enter into spreadsheet
- [ ] Validate data quality

### **Analysis Phase:**

- [ ] Run descriptive statistics
- [ ] Calculate composite scores
- [ ] Perform paired t-tests
- [ ] Calculate effect sizes (Cohen's d)
- [ ] Analyze knowledge gain
- [ ] Validate system logs vs self-report
- [ ] Code qualitative responses
- [ ] Create visualizations

### **Reporting Phase:**

- [ ] Write Methods section
- [ ] Write Results section (fill in numbers)
- [ ] Write Discussion (explain findings)
- [ ] Write Implications & Recommendations
- [ ] Add References
- [ ] Create figures/tables
- [ ] Get peer review
- [ ] Submit for publication

---

## 🎓 RESEARCH DESIGN SUMMARY

**Type:** Quasi-experimental, one-group pre-post design

**Participants:** 45 CS undergraduates

**Duration:** 4-week intervention with S-SPARC AI

**Key Variables:**

- **IV (Independent):** Use of S-SPARC with environmental metrics
- **DVs (Dependent):**
  - Consciousness (awareness)
  - Knowledge (learning)
  - Attitude (willingness)
  - Behavioral intention (intention to change)
  - Actual behavior (semantic search adoption)

**Hypotheses:**

- H1: Consciousness increases significantly (p<0.05, d>0.5)
- H2: Knowledge improves significantly (p<0.05, d>0.5)
- H3: Behavior intention & actual behavior correlate (r>0.5)
- H4: Gamification amplifies effect (moderator analysis)

**Expected Outcomes** (based on sample report):

- Environmental consciousness: +39% (p=0.018, d=0.92)
- Knowledge: +94% (p<0.001, d=1.26)
- Semantic search adoption: 62% (vs. 20% baseline)
- Behavior change score: 68%

---

## 🔧 TECH STACK RECOMMENDATIONS

### **For Data Collection:**

- **Google Forms** (free, easy) atau **Qualtrics** (professional)
- Both can export to CSV easily

### **For Data Entry & Analysis:**

- **Google Sheets** (free, collaborative, built-in formulas)
- **Excel** (if already using)
- **Python** (for automated analysis - script provided)

### **For Statistical Analysis:**

- **Built-in formulas** (Google Sheets/Excel)
- **Python with scipy** (T.TEST, t-statistic)
- **R** (if familiar with statistical software)
- **SPSS/Jamovi** (if institution provides)

### **For Visualization:**

- **Google Sheets charts** (quick & easy)
- **Matplotlib/Seaborn** (Python - professional)
- **Tableau** (if institution provides)

---

## 📞 QUICK REFERENCE ANSWERS

**Q: Berapa sample size yang saya butuh?**
A: Minimum N=30 untuk statistical power. Target N=45-50 untuk robustness.

**Q: Bagaimana kalau ada yang dropout?**
A: Acceptable up to 10-15% (jadi dari 45, minimal 38-40 paired complete).

**Q: Berapa lama analyze?**
A: 1-2 hari untuk data entry, 1 hari untuk statistical analysis, 2-3 hari untuk write-up.

**Q: Apakah tanpa control group tetap valid?**
A: Ya, dengan catatan: document limitations, use large effect sizes (d>0.8) sebagai evidence, jangan claim causation terlalu kuat.

**Q: Bagaimana kalau tidak signifikan?**
A: Masih valuable untuk report: "No significant change suggests X, Y, Z... implications..."

**Q: Format mana yang better - online atau paper survey?**
A: Online lebih baik (easier data entry, higher completion rate), tapi have printed backup.

**Q: Apakah perlu qualitative interviews juga?**
A: Optional tapi recommended: interview 10-15 responden untuk deeper insights (30-45 min each).

---

## 🎁 BONUS: CUSTOM MODIFICATIONS

### **Jika ingin menambah/modifikasi:**

**Add more knowledge items?**

- Copy format dari C1-C5 (multiple choice, 0/1 scoring)
- Keep balance (3-5 items per construct)

**Add environmental behavior items?**

- Create new section E (Pre) measuring specific behaviors
- Example: "How often do you search for existing code before asking AI?"

**Add implementation fidelity checks?**

- Create brief "usage log" section (post-survey)
- Ask: "How many times did you use semantic search?" (self-report)
- Compare dengan actual system logs

**Add moderator variables?**

- Example: "Prior sustainability knowledge", "AI tool experience"
- See if high baseline = smaller gains vs low baseline = larger gains

**Modify for different institution/culture?**

- Adjust language (Indonesian/English balance)
- Add context-specific questions
- But keep core 20 items for comparability

---

## 📊 EXPECTED RESULTS RANGE

If your results look like this, you're on track:

```
✓ Environmental Consciousness: Pre M=2.8-3.2, Post M=3.8-4.2
✓ Knowledge: Pre score 1.5-2.0 out of 5, Post score 3.2-3.8
✓ Attitude: Pre M=3.0-3.4, Post M=3.9-4.3
✓ Behavior adoption: 50-75% semantic search usage
✓ Effect sizes: Cohen's d = 0.7-1.3 (medium-large)
✓ Significance: p-values < 0.05 for main outcomes
✓ Qualitative: 60-80% report positive behavior change awareness
```

If results are smaller/non-significant:

- Still publishable! Document as "no significant change"
- Useful for understanding what doesn't work
- Identify moderators (why some students not affected?)

---

## 🚀 NEXT STEPS

1. **Today:** Read through all 6 documents
2. **Tomorrow:** Customize surveys for your context
3. **This week:** Setup Google Forms, recruit participants
4. **Next week:** Run pre-survey
5. **Weeks 2-6:** Intervention phase
6. **Week 7:** Run post-survey
7. **Week 8:** Analyze data
8. **Week 9:** Write report

---

## 💬 FINAL NOTES

Anda sekarang memiliki **complete research toolkit** untuk quasi-experiment ini. Semua dokumen dirancang untuk:

✅ **Comprehensive:** Dari recruitment hingga publication
✅ **Practical:** Siap pakai templates & checklists
✅ **Rigorous:** Following educational research standards
✅ **Flexible:** Dapat dikustomisasi sesuai kebutuhan Anda
✅ **Transparent:** Clear about limitations & best practices

**Key untuk sukses:**

- Maintain high response rates (≥80% both pre & post)
- Keep data quality high (validate entries)
- Follow timeline (consistent is better than rushed)
- Document everything (changes, issues, decisions)
- Plan analysis BEFORE collecting data

---

**Anda siap untuk melakukan quasi-experiment sustainability AI yang berkualitas tinggi!** 🎓✨

**Semoga penelitian Anda membawa impact positif untuk sustainability awareness di CS education!**
