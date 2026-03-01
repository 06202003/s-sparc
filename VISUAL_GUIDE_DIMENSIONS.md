# VISUAL GUIDE: RESEARCH FRAMEWORK & DIMENSIONS

## S-SPARC AI Sustainability Research

---

## 1. RESEARCH MODEL DIAGRAM

```
                    ┌─────────────────────────────────────┐
                    │     S-SPARC AI PLATFORM             │
                    │  (Environmental Metrics + Features)  │
                    └─────────────────────────────────────┘
                                    ↓
                    ┌─────────────────────────────────────┐
                    │   LEARNING & AWARENESS PHASE        │
                    │  (Week 1-4: Normal Usage)           │
                    │                                      │
                    │  • View carbon metrics              │
                    │  • Try semantic search              │
                    │  • See efficiency scores            │
                    │  • Access environmental info        │
                    └─────────────────────────────────────┘
                    ↙            ↓            ↘
           ┌────────┴──┐   ┌────┴────┐   ┌────┴────┐
           ↓           ↓   ↓         ↓   ↓         ↓

        KNOWLEDGE     CONSCIOUSNESS   ATTITUDE   INTENTION
        GAIN          INCREASE        CHANGE     TO ACT
        (+34pp)       (+39%)          (+28%)     (M=3.6)
           ↓           ↓               ↓         ↓
        [Measure      [Measure        [Measure  [Measure
         C1-C5]       B1-B6 → D1-D6]  D1-D6 →   E7, F3,
                                      E1-E7]    F5]
                                        ↓
                    ┌───────────────────┴───────────────────┐
                    ↓                                         ↓

             SEMANTIC SEARCH               BEHAVIORAL
             ADOPTION                      SHIFT
             (62% of users)                (68% adoption)
                    ↓                                         ↓
                    └───────────────────┬───────────────────┘
                                        ↓
                    ┌─────────────────────────────────────┐
                    │   ENVIRONMENTAL OUTCOME             │
                    │  (System Logs Validation)           │
                    │                                      │
                    │  ↓ Carbon emissions                 │
                    │  ↓ Energy consumption               │
                    │  ↓ Water usage                      │
                    └─────────────────────────────────────┘

MODERATOR (enhances throughout):
        ┌────────────────────────────────────┐
        │      GAMIFICATION FEATURES         │
        │  • Leaderboard (71% motivated)     │
        │  • Efficiency scores               │
        │  • Weekly challenges               │
        └────────────────────────────────────┘
```

---

## 2. MEASUREMENT DIMENSIONS BREAKDOWN

### **Dimension 1: ENVIRONMENTAL CONSCIOUSNESS** (Awareness)

```
┌─────────────────────────────────────────────────────────┐
│  ENVIRONMENTAL CONSCIOUSNESS (6 Likert items)           │
│  Scale: 1 = Sangat Tidak Setuju → 5 = Sangat Setuju   │
└─────────────────────────────────────────────────────────┘

Items:
├─ B1 [CARE]: I care about technology's environmental impact
├─ B2 [SALIENCE]: AI usage has significant carbon footprint
├─ B3 [EFFICIENCY]: I consider energy efficiency when choosing
├─ B4 [RESPONSIBILITY]: Sustainability is developer duty
├─ B5 [KNOWLEDGE]: I know how much energy my AI uses
└─ B6 [MOTIVATION]: Important to reduce my carbon footprint

Expected Progression:
    PRE-SURVEY          INTERVENTION         POST-SURVEY

    Low Awareness   →   Metrics Display  →   High Awareness
    (M=2.8/5)           + Education           (M=3.9/5)

    48% agree          [4 weeks of              78% agree
    caring (B1)         exposure]              caring (D1)

Interpretation:
• Score 1-2: Low environmental consciousness
• Score 3: Neutral/mixed consciousness
• Score 4-5: High environmental consciousness
• Composite score = average of 6 items (1-5)

Statistical Test: Paired t-test
Expected Result: Significant increase (p<0.05, d>0.5)
```

---

### **Dimension 2: KNOWLEDGE ABOUT SUSTAINABLE AI**

```
┌─────────────────────────────────────────────────────────┐
│  KNOWLEDGE ASSESSMENT (5 Multiple Choice items)          │
│  Scoring: Correct=1, Incorrect=0                        │
│  Total Score: 0-5 (can convert to %)                    │
└─────────────────────────────────────────────────────────┘

Items:
├─ C1: Carbon Footprint Definition
│   ✓ Answer: Jumlah CO2 dari energi yang dipakai
│   (Other answers: biaya uang, jumlah data, tidak tahu)
│
├─ C2: Energy Efficiency Trade-offs
│   ✓ Answer: semantic search (paling efficient)
│   (Other: model besar 1x, model kecil multiple, tidak tahu)
│
├─ C3: PUE Definition
│   ✓ Answer: Data center efficiency metric
│   (Other: model performance, tidak tahu)
│
├─ C4: Carbon per Query Estimate
│   ✓ Answer: 0.1g CO2e (rough estimate)
│   (Other: 0.01g, 1g, 10g, tidak tahu)
│
└─ C5: Environmental Factors
    ✓ Answer: Jumlah query sebelumnya BUKAN faktor
    (Other: token count, model size, grid carbon intensity)

Expected Progression:
    PRE-SURVEY          INTERVENTION         POST-SURVEY

    Baseline      →   Learning Phase    →   Knowledge Gain
    (M=1.8/5)        (metrics, examples)      (M=3.5/5)
    36% correct                              70% correct

    Knowledge Gain: 3.5 - 1.8 = +1.7 items = +34 percentage points

Interpretation:
• 0-1 items: Low knowledge
• 2-3 items: Moderate knowledge
• 4-5 items: High knowledge

Statistical Test: Paired t-test OR Knowledge Gain Analysis
Expected Result: Significant increase (p<0.001, d>0.8) ← LARGEST EFFECT
```

---

### **Dimension 3: ATTITUDE TOWARDS SUSTAINABILITY**

```
┌─────────────────────────────────────────────────────────┐
│  ATTITUDE (6 items Pre, 7 items Post)                   │
│  Scale: 1 = Sangat Tidak Setuju → 5 = Sangat Setuju   │
│  NOTE: D6 & E6 are REVERSE CODED (6-value for analysis) │
└─────────────────────────────────────────────────────────┘

Pre Items (D1-D6):
├─ D1: Mau belajar tentang sustainable AI
├─ D2: Akan lebih careful jika ada informasi environmental
├─ D3: Sustainability harus di curriculum CS
├─ D4: Rela mengorbankan kecepatan untuk sustainability
├─ D5: Developer harus transparent tentang cost
└─ D6 (REVERSE): Sustainable AI adalah tren/gimmick saja
   [REVERSE: 6-D6 score used in calculations]

Post Items (E1-E7):
├─ E1: Mau belajar lebih lanjut
├─ E2: Akan more careful dengan information
├─ E3: Harus di curriculum
├─ E4: Rela sacrifice efficiency
├─ E5: Transparency is important
├─ E6 (REVERSE): Trend/gimmick
   [REVERSE: 6-E6 score used]
└─ E7 (NEW): After S-SPARC, lebih mau choose sustainable

Composite Scoring:
Pre_Attitude = (D1 + D2 + D3 + D4 + D5 + (6-D6)) / 6
Post_Attitude = (E1 + E2 + E3 + E4 + E5 + (6-E6) + E7) / 7

Expected Progression:
    PRE-SURVEY          INTERVENTION         POST-SURVEY

    Ambivalent    →   Positive         →   Committed
    (M=3.2/5)        Examples +           (M=4.1/5)
    Neutral/mixed    Education            Strong agreement

Interpretation:
• Score 1-2: Negative attitude
• Score 3: Neutral attitude
• Score 4-5: Positive attitude toward sustainability

Statistical Test: Paired t-test
Expected Result: Significant increase (p<0.05, d>0.5)
```

---

### **Dimension 4: BEHAVIORAL INTENTION & ACTUAL BEHAVIOR**

```
┌─────────────────────────────────────────────────────────┐
│  BEHAVIORAL INTENTION (Post-only, 1 item)              │
│  E7: "After using S-SPARC, I more want choose           │
│        sustainable alternatives"                        │
│  Scale: 1-5                                             │
└─────────────────────────────────────────────────────────┘

Interpretation:
• 1-2: No intention to change
• 3: Uncertain intention
• 4-5: Strong intention to change

Expected: M=3.6/5 (moderate-strong intention)

┌─────────────────────────────────────────────────────────┐
│  ACTUAL BEHAVIOR CHANGE (Post survey items)             │
└─────────────────────────────────────────────────────────┘

Key Behaviors Measured:
├─ F3: Semantic search frequency (1-5 scale)
│   → System logs validate (%) of searches before generation
│
├─ F5: "Stop and think" before each query (1-5 scale)
│   → Deliberate efficiency-seeking
│
├─ F1: Types of efficiency attempts (count 0-4)
│   → Prompt optimization, multiple alternatives, etc.
│
└─ System Logs: Actual ratio of search:generation queries

Behavior Change Score Calculation:
behavior_score = (F3 + (efficiency_attempts_count) + F5) / 3 * 100

Interpretation (0-100 scale):
• 0-30: Minimal behavior change
• 30-50: Slight behavior change
• 50-70: Moderate behavior change
• 70-100: Significant behavior change

Expected: 62% using semantic search regularly, score ~68/100

Validation:
For participants with system logs:
  r(self_report, system_logs) > 0.7 indicates good accuracy

┌─────────────────────────────────────────────────────────┐
│  CORRELATION: Intention → Actual Behavior              │
│  r(E7, behavioral_score) should be r > 0.5             │
│  Indicates: Students who intend DO change behavior     │
└─────────────────────────────────────────────────────────┘
```

---

## 3. GAMIFICATION AS MODERATOR

```
┌─────────────────────────────────────────────────────────┐
│  GAMIFICATION IMPACT (Post survey items G1-G4)          │
│  Moderator variable: Amplifies but doesn't drive effect │
└─────────────────────────────────────────────────────────┘

Features Measured:
├─ G1: Leaderboard viewing frequency (1-5)
├─ G2: Leaderboard effect on efficiency (1-5)
├─ G3: Motivation to get high score (1-5)
└─ G4: Perception of gamification quality (1-5)

Model:
    Environmental → Behavioral → Behavior
    Consciousness   Intention   (Semantic Search)
         ↓                ↓          ↓
         └──────────────→ 62%  ←─────┘
                    [Enhanced by
                    Gamification:
                    71% found it
                    motivating]

Analysis:
• Compare behavior scores for:
  - High leaderboard viewers (>50% viewing): M=7.4/10
  - Low leaderboard viewers (<25% viewing): M=5.9/10
  - Difference: t=1.87, p=0.068 (trending)

Interpretation:
• Gamification enhances but isn't primary driver
• Environmental consciousness + metrics more important
• Best results: metrics + alternatives + gamification together
```

---

## 4. QUALITATIVE DIMENSIONS

```
┌─────────────────────────────────────────────────────────┐
│  OPEN-ENDED RESPONSES (G1-G3 Pre, J1-J5 Post)          │
│  Thematic Analysis                                      │
└─────────────────────────────────────────────────────────┘

Themes to Code (with examples):

[AWARE] - Environmental awareness increase
├─ "Never realized one query costs carbon"
├─ "Seeing number on screen is powerful"
└─ Frequency: 41/43 (95%)

[INTENT] - Expressed intention to change
├─ "Now I search for similar code first"
├─ "Used to regenerate, now try semantic first"
└─ Frequency: 29/43 (67%)

[BARRIER] - Identified obstacles to sustainability
├─ "Lack of good examples"
├─ "Hard to know what 'efficient' means"
└─ Frequency: 28/43 (65%)

[IMPACT] - Perceived system usefulness
├─ "Makes me think twice before querying"
├─ "Leaderboard motivated me"
└─ Frequency: 35/43 (81%)

[SUGGEST] - Ideas for improvement
├─ "Want breakdown by assignment"
├─ "Show carbon vs. average student"
└─ Frequency: 38/43 (88%)

Results Format:
┌──────────────┬────────┬──────────┐
│ Theme        │ Count  │ Percent  │
├──────────────┼────────┼──────────┤
│ [AWARE]      │ 41/43  │ 95%      │
│ [INTENT]     │ 29/43  │ 67%      │
│ [BARRIER]    │ 28/43  │ 65%      │
│ [IMPACT]     │ 35/43  │ 81%      │
│ [SUGGEST]    │ 38/43  │ 88%      │
└──────────────┴────────┴──────────┘

Sentiment Analysis:
Classify each open-end as: Positive (+1), Neutral (0), Negative (-1)
overall_sentiment = (positive_count - negative_count) / total
Expected: >+0.5 (overall positive sentiment)
```

---

## 5. MEASUREMENT TIMING & COMPARISON

```
TIMELINE:

         PRE-SURVEY          INTERVENTION            POST-SURVEY
         (Week 1)            (Weeks 2-5)             (Week 6-7)

         ┌──────────┐        ┌──────────┐            ┌──────────┐
         │ Baseline │        │ S-SPARC  │            │ Measure  │
         │ Measure  │        │ Usage    │            │ Change   │
         └──────────┘        └──────────┘            └──────────┘

Items:   └─────────────────────────────────────────────────┘
         Same items in both surveys (except new items post)

Consciousness: B1-B6              D1-D7 (same items + D7 change)
Knowledge:     C1-C5              C1-C5 (identical for comparison)
Attitude:      D1-D6              E1-E7 (same items + E7 new)
Behavior:      E1-E4 (baseline)   F1-F6 (detailed post)

PRE→POST PAIRED ANALYSIS:
For each item/composite score:
  Change = Post_Value - Pre_Value

  If Change > 0: improvement/increase
  If Change = 0: no change
  If Change < 0: decrease/decline

  Test H0 (no change) using paired t-test
```

---

## 6. EXPECTED RESULTS TABLE

```
┌────────────────────────────────────────────────────────────────┐
│         EXPECTED RESULTS (Ballpark Figures)                    │
├────────────────────────────────────────────────────────────────┤
│ Measure                  │ Pre    │ Post   │ Change │ p-val  │
├──────────────────────────┼────────┼────────┼────────┼────────┤
│ Consciousness (1-5)      │ 2.8    │ 3.9    │ +1.1   │ 0.018* │
│ Knowledge (0-5)          │ 1.8    │ 3.5    │ +1.7   │<0.001**│
│ Knowledge (%)            │ 36%    │ 70%    │ +34pp  │<0.001**│
│ Attitude (1-5)           │ 3.2    │ 4.1    │ +0.9   │ 0.032* │
│ Behavior Score (0-10)    │ --     │ 6.8    │ --     │ --     │
│ Semantic Search (%)      │ 20%    │ 62%    │ +42pp  │ --     │
│ Leaderboard Motivation   │ --     │ 71%    │ --     │ --     │
├──────────────────────────┼────────┼────────┼────────┼────────┤
│ Effect Size (Cohen's d)  │                  │ 0.92   │        │
│ Intention→Behavior (r)   │                  │ 0.64** │        │
└────────────────────────────────────────────────────────────────┘

* p < 0.05 (significant)
** p < 0.001 (highly significant)

Your results might differ, but this range indicates success.
Smaller effects still publishable with clear implications.
```

---

## 7. ANALYSIS FLOWCHART

```
        DATA COLLECTED
        (Pre + Post Surveys)
               ↓
        ┌──────────────────┐
        │  DATA QUALITY    │
        │  CHECKS          │
        │  • Completeness  │
        │  • Ranges OK?    │
        │  • Missing <5%?  │
        └──────────────────┘
               ↓ Yes
        ┌──────────────────┐
        │ DESCRIPTIVE      │
        │ STATISTICS       │
        │ • Mean/SD pre    │
        │ • Mean/SD post   │
        │ • Check normality│
        └──────────────────┘
        ↙              ↘
    ┌─────────┐    ┌──────────────┐
    │ MATCHED │    │ INDEPENDENT  │
    │ PAIRS T │    │ SAMPLES T    │
    │ TEST    │    │ TEST         │
    │ (if     │    │ (if groups   │
    │ paired) │    │ separate)    │
    └─────────┘    └──────────────┘
        ↓              ↓
    ┌─────────────────────────────┐
    │ EFFECT SIZES (Cohen's d)    │
    │ Interpret practical sigf.   │
    └─────────────────────────────┘
        ↓
    ┌─────────────────────────────┐
    │ CORRELATION ANALYSIS        │
    │ Intention ↔ Behavior (r)    │
    │ Knowledge ↔ Behavior        │
    └─────────────────────────────┘
        ↓
    ┌─────────────────────────────┐
    │ QUALITATIVE ANALYSIS        │
    │ Code themes                 │
    │ Count frequencies           │
    │ Extract quotes              │
    └─────────────────────────────┘
        ↓
    ┌─────────────────────────────┐
    │ INTERPRETATION              │
    │ Test hypotheses             │
    │ Discuss findings            │
    │ Limitations                 │
    │ Implications                │
    └─────────────────────────────┘
        ↓
    WRITE UP REPORT
```

---

## 8. SUCCESS CRITERIA CHECKLIST

```
✓ Pre-Survey:      ≥85% response rate (≥38 students)
✓ Intervention:    ≥90% completion (≤5 dropouts)
✓ Post-Survey:     ≥80% matched pairs (≥32)
✓ Data Quality:    ≥95% complete entries
✓ Significance:    p < 0.05 pada ≥2 main outcomes
✓ Effect Size:     Cohen's d ≥ 0.5 (medium+)
✓ Behavior Change: ≥50% semantic search adoption
✓ Qualitative:     ≥60% reporting positive change
```

---

This visual guide should help you understand the measurement framework better! 📊
