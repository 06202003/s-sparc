# SAMPLE RESEARCH REPORT TEMPLATE

## S-SPARC AI: Impact on Sustainability Awareness in CS Education

**Author:** [Your Name]  
**Institution:** [University Name]  
**Date:** [Date]  
**Research Type:** Quasi-Experimental Study

---

## EXECUTIVE SUMMARY

This quasi-experimental study investigates the impact of transparency regarding environmental metrics in an AI-assisted learning platform (S-SPARC) on computer science students' sustainability awareness and behavior. Forty-five undergraduate CS students were surveyed before and after a 4-week intervention using S-SPARC AI, which displays real-time carbon footprint, energy consumption, and water usage metrics for code generation tasks.

**Key Findings:**

- Significant increase in environmental consciousness (M_pre=2.8 vs M_post=3.9, p<0.05)
- Knowledge about sustainable AI improved by 34 percentage points (70% correct vs 36% baseline)
- 68% of participants reported deliberate efficiency-seeking behavior
- Semantic code reuse adoption rate: 62% (vs. expected 20% without intervention)
- Large effect size (Cohen's d=0.92-1.26) across cognitive and behavioral dimensions

---

## 1. INTRODUCTION

### 1.1 Background

The widespread adoption of Large Language Models (LLMs) like GPT-4 for code generation has raised concerns about environmental sustainability. A single query to GPT-4 consumes approximately 0.0021 Wh of energy (Jegham et al., 2023), which translates to ~0.0008g of CO2 equivalent emissions (using Indonesia's grid carbon intensity). For students generating code dozens of times weekly, the cumulative environmental impact is non-trivial.

Despite this, most students lack awareness of:

- The carbon cost of their AI tool usage
- Alternative approaches (semantic search, code reuse)
- Their role in sustainable computing practices

### 1.2 Motivation: Why S-SPARC?

S-SPARC AI is a learning platform that uniquely combines:

1. **AI-assisted code generation** (semantic retrieval + GPT-4 fallback)
2. **Real-time environmental metrics** (energy Wh, carbon kg CO2e, water mL)
3. **Gamification elements** (efficiency scores, leaderboards)
4. **Code reuse incentives** (semantic search instead of generation)

This study tests whether transparency about environmental impact can shape student behavior towards more sustainable AI use.

### 1.3 Research Questions

**RQ1:** Does exposure to environmental metrics in S-SPARC increase students' awareness of AI's environmental impact?

**RQ2:** Does the platform improve students' knowledge about sustainable AI practices?

**RQ3:** Do students demonstrate behavioral changes (increased code reuse, reduced generation)?

**RQ4:** What role does gamification play in motivating sustainable behavior?

### 1.4 Hypotheses

**H1:** Students using S-SPARC will show significant increase in environmental consciousness scores from pre to post-survey.

**H2:** Knowledge assessment scores (especially carbon footprint, energy efficiency) will improve significantly.

**H3:** Behavioral intention for sustainability will increase, correlating with actual usage patterns (semantic search adoption).

**H4:** Gamification features (leaderboards) will moderate the relationship between awareness and behavior change.

---

## 2. LITERATURE REVIEW

### 2.1 Environmental Impact of AI

**Energy Consumption:**

- Training GPT-3: ~1,287 MWh (Strubell et al., 2019)
- Inference (per query): ~0.002 Wh (Jegham et al., 2023)
- Carbon equivalent (global average): ~0.0009 kg CO2e per query

**Water Usage:**

- Data centers: 1.9-4.35 L per kWh (Uptime Institute, 2022)
- A single model query: ~5-10 mL (estimated from energy consumption)

**Contextual Concern:**

- 1 million queries to GPT-4 ≈ 2.3 kg CO2e ≈ 50L water
- With thousands of CS students, weekly usage can exceed 1000 queries
- **Cumulative impact: Real and measurable**

### 2.2 Environmental Behavior Change Models

**Theory of Planned Behavior (TPB)** (Ajzen, 1985):

- Attitude → Behavioral Intention → Behavior
- Applied here: Environmental consciousness → Intention to be efficient → Semantic search adoption

**Operant Conditioning & Gamification:**

- Immediate feedback (environmental metrics) reinforces awareness
- Leaderboard/competition taps into intrinsic motivation
- "Efficiency points" as positive reinforcement

### 2.3 Prior Research on Sustainability in CS

**Gap in Literature:**

- Limited research on environmental awareness in CS education
- Few studies on "nudge" interventions (like S-SPARC) for sustainable behavior
- No prior studies combining: environmental metrics + code reuse + gamification

**This Study Fills Gap:** First quasi-experimental test of a comprehensive sustainability-aware learning platform

---

## 3. METHODOLOGY

### 3.1 Research Design

**Type:** Quasi-experimental, one-group pre-post design

**Rationale:**

- Practical constraints (can't randomly assign students)
- All participants receive intervention (no control group)
- Repeated measures design allows paired statistical testing

**Limitations Acknowledged:**

- No control group (History, Maturation threats)
- Selection bias (volunteer participants)
- Hawthorne effect (awareness of study may influence behavior)

### 3.2 Participants

**Inclusion Criteria:**

- Undergraduate CS students (2nd-4th year)
- Regular AI tool users (at least 1x per week)
- Fluent in English/Indonesian

**Sample:**

- N = 45 students
- Age: 19-22 years (M=20.3, SD=0.8)
- Gender: 62% male, 38% female
- Prior AI experience: 78% used ChatGPT, 44% GitHub Copilot
- Prior sustainability awareness: Low baseline (M=2.8/5)

**Recruitment:**

- Posted in CS department announcements
- Email invitations to active students
- Incentive: Free tool access + certificate

### 3.3 Intervention

**Duration:** 4 weeks

**Platform Features:**

1. **Chat interface** for code generation prompts
2. **Semantic search** (retrieves similar code from database)
3. **Environmental dashboard** showing:
   - Real-time carbon for current session
   - Weekly energy breakdown
   - Water usage metrics
4. **Gamification:**
   - Efficiency score (based on code reuse rate)
   - Leaderboard (weekly rankings)
   - Token quota (limited free queries, rewards for efficiency)

**Suggested Usage:**

- 2-4 times per week
- Minimum: 1 code generation task
- Monitoring: System logs track all interactions

### 3.4 Measures

#### **3.4.1 Environmental Consciousness (Likert 1-5 scale)**

| Item | Question                                         |
| ---- | ------------------------------------------------ |
| B1   | I care about environmental impact of technology  |
| B2   | AI/LLM usage has significant carbon footprint    |
| B3   | I consider energy efficiency when choosing tools |
| B4   | Sustainability is developer responsibility       |
| B5   | I know how much energy my AI usage requires      |
| B6   | It's important to reduce my carbon footprint     |

**Alpha (pre):** 0.78, Alpha (post): 0.81 → Good internal consistency

#### **3.4.2 Knowledge Assessment (Multiple Choice, 0-5 points)**

| Item | Dimension                                     |
| ---- | --------------------------------------------- |
| C1   | Definition of Carbon Footprint                |
| C2   | Energy efficiency trade-offs                  |
| C3   | Understanding of PUE                          |
| C4   | Quantitative carbon impact (1 query estimate) |
| C5   | Environmental factors in AI impact            |

**Scoring:** 1 point per correct answer (max 5)

#### **3.4.3 Attitude towards Sustainability (Likert 1-5)**

6 items (D1-D6 pre, E1-E7 post) measuring willingness to sacrifice efficiency, support curriculum change, developer transparency expectations.

**Composite:** Mean of 6-7 items (range 1-5)

#### **3.4.4 Behavioral Measures (Post-survey)**

- **F3:** Semantic search frequency (1-5 scale)
- **F5:** "Stop and think before query" frequency
- **F1:** Types of efficiency-seeking attempts (count)
- **System logs:** Actual semantic search vs new generation ratio

#### **3.4.5 Feature Satisfaction (Post-survey)**

- **I1-I4:** Perceived helpfulness of environmental metrics
- **G1-G4:** Leaderboard/gamification effectiveness
- **H1-H2:** Transparency and trust in metrics

---

## 4. RESULTS

### 4.1 Descriptive Statistics

**Table 1: Pre-Post Comparisons (N=43, two excluded due to incomplete surveys)**

| Measure                         | Pre (M±SD) | Post (M±SD) | Change | t    | p          | d    |
| ------------------------------- | ---------- | ----------- | ------ | ---- | ---------- | ---- |
| **Environmental Consciousness** | 2.8±1.2    | 3.9±1.0     | +1.1   | 2.45 | 0.018\*    | 0.92 |
| **Knowledge (0-5)**             | 1.8±1.3    | 3.5±1.2     | +1.7   | 3.18 | <0.001\*\* | 1.26 |
| **Attitude (1-5)**              | 3.2±0.9    | 4.1±0.8     | +0.9   | 2.01 | 0.032\*    | 1.04 |
| **Behavioral Intention**        | -          | 3.6±1.1     | -      | -    | -          | -    |

- p < 0.05, ** p < 0.01, \*** p < 0.001

### 4.2 Hypothesis Testing

**H1: Environmental Consciousness Increases**
✓ **SUPPORTED**

- Pre: M=2.8 (SD=1.2)
- Post: M=3.9 (SD=1.0)
- t(42)=2.45, p=0.018
- Cohen's d=0.92 (large effect)

Interpretation: Significant and meaningful increase. Students became more aware of environmental impact.

**H2: Knowledge Improves**
✓ **SUPPORTED**

- Pre: 1.8/5 (36% correct)
- Post: 3.5/5 (70% correct)
- Gain: +34 percentage points
- t(42)=3.18, p<0.001
- Cohen's d=1.26 (large effect)

Interpretation: Substantial knowledge gain. Largest effect size suggests learning was significant.

**H3: Behavioral Intention & Actual Behavior**
✓ **PARTIALLY SUPPORTED**

- Behavioral intention (post): M=3.6±1.1 (out of 5) → Moderate-to-strong
- Semantic search adoption: 62% used regularly (vs. <20% expected)
- Mean behavior change score: 6.8/10 (68% adoption)
- Correlation (intention vs actual): r=0.64, p=0.002 → Strong

Interpretation: Students who reported intention to be efficient actually used semantic search. Self-reported intention correlates well with logged behavior.

**H4: Gamification Effect (Moderator)**
✓ **SUPPORTED**

- 71% of participants found leaderboard motivating
- High leaderboard viewers (>50% of time): M_behavior=7.4
- Low leaderboard viewers (<25% of time): M_behavior=5.9
- Difference: t=1.87, p=0.068 (trending significant)
- Qualitative: "Seeing my name on leaderboard made me want to be more efficient"

Interpretation: Gamification plays supportive role, though effect smaller than other components.

### 4.3 Individual Item Improvements (Knowledge)

| Knowledge Item              | Pre | Post | Gain  |
| --------------------------- | --- | ---- | ----- |
| Carbon footprint definition | 58% | 91%  | +33pp |
| Energy efficiency knowledge | 31% | 73%  | +42pp |
| PUE understanding           | 22% | 68%  | +46pp |
| Carbon query estimate       | 20% | 64%  | +44pp |
| Environmental factors       | 29% | 79%  | +50pp |

**All items improved significantly (p < 0.01)**

### 4.4 Behavioral Patterns

**Code Reuse Behavior:**

- Pre (estimated): 20% of students search for code before AI (from A6_E3)
- Post: 62% report regular semantic search use
- System logs: 58% of generation attempts preceded by search attempt

**Efficiency-Seeking Behaviors (Reported):**

- 68% "stop and think" before each query (f5: M=3.8/5)
- 55% attempted to optimize prompts (f1_count=1-4 types)
- 42% explicitly asked for efficient code
- 28% reviewed multiple alternative solutions

**Gamification Engagement:**

- 78% viewed environmental metrics regularly
- 71% checked leaderboard weekly
- 64% mentioned wanting to improve efficiency score
- Correlation (leaderboard_viewing vs behavior): r=0.52, p=0.008

### 4.5 Feature Satisfaction

**Environmental Metrics (H1-H4):**

- Transparency rating: M=4.1/5 (81% "transparent" or "very transparent")
- Trust in numbers: M=3.9/5 (79% trust)
- Most impactful feature: Real-time carbon display (67% mention)
- Desired improvement: Breakdown by assessment/course (56% request)

**System Usefulness (I1-I4):**

- Overall helpfulness: M=4.0/5
- 78% would recommend to peers
- 82% plan to continue using S-SPARC after study
- Satisfaction: M=4.1/5

### 4.6 Qualitative Findings (Open-Ended Responses)

**Theme 1: Awareness [41/43 respondents, 95%]**

> "Never realized one query costs carbon. Makes you think twice." - R12
> "Seeing the number on screen is powerful. Abstract before, real now." - R27

**Theme 2: Behavior Change [29/43, 67%]**

> "Now I search for similar code first. Saves time AND planet." - R38
> "Used to just regenerate until happy. Now try semantic search first." - R14

**Theme 3: Motivation (Gamification) [31/43, 72%]**

> "Leaderboard made it fun. Wanted to beat others' efficiency." - R21
> "Points system is silly but honestly works. I check every day." - R33

**Theme 4: Suggestions for Improvement [35/43, 81%]**

> "Would like detailed breakdown by assignment." - R05
> "More tips on how to reduce carbon." - R19
> "Show carbon comparison: me vs. average student." - R42

---

## 5. DISCUSSION

### 5.1 Main Findings

This study provides evidence that **transparency about environmental impact can shift CS student behavior towards sustainability**, supporting theories of environmental behavior change (TPB).

**Three key contributions:**

1. **Knowledge Gains are Substantial** (+34 percentage points)
   - Students significantly improved understanding of carbon footprint, energy efficiency, and related concepts
   - Suggests that properly designed educational interfaces can convey complex environmental information effectively
   - Implication: Environmental education in CS should be made more explicit and quantified

2. **Behavioral Intention Translates to Action** (r=0.64)
   - Students who reported intention to be efficient actually used semantic search more
   - System logs validate self-reported behavior (correlation confirms not mere response bias)
   - Implication: Behavioral commitment devices (like surveys + tracking) can reinforce intentions

3. **Gamification Amplifies (but doesn't Drive) Change**
   - Leaderboard viewers show higher behavior scores (7.4 vs 5.9)
   - But effect is moderate, not dominant
   - Suggests gamification is useful complement, not replacement for core motivation (environmental consciousness)

### 5.2 Mechanism of Change

**Proposed pathway (supported by data):**

```
Educational Metrics Display
         ↓
    Increased Knowledge (d=1.26)
         ↓
    Raised Consciousness (d=0.92)
         ↓
    Behavioral Intention (M=3.6)
         ↓
    Actual Behavior: Semantic Search Adoption (62%)
         ↓
    Environmental Benefit: Reduced energy/carbon/water

(Gamification: Enhances motivation at each stage, but not primary driver)
```

### 5.3 Comparison to Literature

**Prior expectations vs. findings:**

| Expectation                  | Finding          | Alignment               |
| ---------------------------- | ---------------- | ----------------------- |
| 20% semantic search adoption | 62% actual       | ✓✓ Much higher          |
| Awareness ~3/5 baseline      | 3.9/5 post       | ✓ Improved as expected  |
| Knowledge gain ~0.5 items    | 1.7 items (34pp) | ✓✓ Larger than expected |
| Behavior change in ~30%      | 68% adoption     | ✓✓ Much stronger        |

**Implications:**

- Environmental transparency is **more effective** than expected for motivating behavior
- Students are **more responsive** to sustainability education than literature suggests
- **Design matters:** S-SPARC's combination of metrics + alternatives + gamification is effective

### 5.4 Limitations

**1. No Control Group**

- Cannot rule out: History effects, maturation, testing effects
- **Mitigation:** Large effect sizes (d>0.9) suggest genuine intervention effect
- **Recommendation:** Future work should include control group

**2. Self-Selection Bias**

- Volunteers more motivated (already interested in learning)
- **Mitigation:** Still found high variance; effects hold across interest levels
- **Generalization:** Results may overestimate impact on all students

**3. Hawthorne Effect**

- Being studied may artificially increase effort/awareness
- **Mitigation:** Asked participants 2 weeks into intervention (by then, awareness of study fades)
- **Evidence:** Knowledge still improved significantly by post-survey

**4. Short Intervention (4 weeks)**

- Unknown if behavior changes persist long-term
- **Recommendation:** Longitudinal follow-up at 3, 6, 12 months

**5. Self-Reported Behavior**

- Semantic search frequency self-reported (not all system-logged)
- **Validation:** For users with logs, r=0.78 with self-report (good agreement)
- **Mitigation:** Actual system data available for 30/43 participants

### 5.5 Theoretical Implications

**1. Environmental Consciousness is Malleable**

- Can be increased through targeted intervention (d=0.92)
- Implication: Environmental education should start early in CS curriculum

**2. Transparency Works**

- Behavioral economics literature suggests "nudges" with information are powerful
- S-SPARC validates this in CS education context
- Implication: Industry tools should implement similar transparency

**3. Gamification as Amplifier, Not Driver**

- Consistent with recent meta-analyses (Hamari et al., 2014)
- Works best when aligned with intrinsic motivation
- Implication: Don't rely on points alone; pair with meaningful education

---

## 6. IMPLICATIONS & RECOMMENDATIONS

### 6.1 For CS Education

**Recommendation 1: Integrate Sustainability into CS Curriculum**

- Add "Environmental Aspects of AI" to requirements
- Teach carbon-aware coding practices
- Require lifecycle assessment for projects

**Recommendation 2: Make Metrics Visible**

- When students use AI tools → Show environmental impact
- Real-time feedback drives behavior change (this study confirms)
- Should become industry standard

**Recommendation 3: Incentivize Code Reuse**

- Semantic search engines should be default, not afterthought
- Academic culture should reward "efficient reuse" not just "novel generation"

### 6.2 For Tool Designers (S-SPARC & Others)

**Recommendation 1: Default to Sustainable Path**

- Make semantic search the first option, generation second
- Reverse current paradigm (generation-first → search-first)

**Recommendation 2: Transparency at Scale**

- Show cumulative carbon ("You've saved X kg CO2 through reuse")
- Comparative metrics ("You're 15% more efficient than peers")
- Historical trend ("Your efficiency +23% since week 1")

**Recommendation 3: Contextualize Impact**

- "Your 50 queries = carbon footprint of 0.5 kg CO2, equivalent to driving 2km in a car"
- "You've saved 2 liters of water through semantic search today"
- Make numbers relatable

### 6.3 For Future Research

**Study 1: Longitudinal (Retention)**

- Follow cohort 6+ months post-intervention
- Do behavior changes persist?
- Which students retain sustainable practices?

**Study 2: Cross-Institution**

- Replicate at 5+ universities
- Test cultural/institutional differences
- Generalize findings

**Study 3: Mechanism Testing**

- Isolate components: metrics-only vs. leaderboard-only vs. combined
- What drives behavior change most?

**Study 4: Cost-Benefit Analysis**

- Calculate actual carbon savings from behavior change
- Scale to 10,000 students: What's total impact?

---

## 7. CONCLUSION

This quasi-experimental study provides evidence that **transparency about AI's environmental impact, combined with code reuse alternatives and gamification, can significantly increase CS students' sustainability consciousness and behavior**.

**Key findings:**

- Environmental consciousness +39% (p=0.018, d=0.92)
- Knowledge +94% (p<0.001, d=1.26)
- Semantic search adoption: 62% (vs. <20% expected)
- Strong intention-behavior correlation (r=0.64)

**These results suggest:**

1. CS students are responsive to environmental education
2. Well-designed tools can drive sustainable behavior
3. Transparency + alternatives + incentives work together
4. Small interventions at scale could have global environmental impact

**With 1 million CS students worldwide, if each reduces AI-based code generation by 50% through semantic search:**

- Annual carbon savings: ~115 metric tons CO2e
- Water savings: ~240,000 liters
- Energy savings: ~300 MWh

**This study provides a blueprint for making AI education sustainable.**

---

## REFERENCES

Ajzen, I. (1985). From intentions to actions: A theory of planned behavior. _Action Control_, 11(39), 11-39.

Hamari, J., Koivisto, J., & Sarsa, H. (2014). Does gamification work?--a literature review of empirical studies on gamification. _2014 47th Hawaii International Conference on System Sciences_ (pp. 3025-3034). IEEE.

Jegham, I., et al. (2023). HowHungry is AI? Benchmarking Energy, Water, and Carbon Footprint of LLM Inference. _arXiv preprint arXiv:2505.09598_.

Strubell, E., Ganesh, A., & McCallum, A. (2019). Energy and policy considerations for deep learning in NLP. _arXiv preprint arXiv:1906.02243_.

Uptime Institute. (2022). Data Center Power Usage Effectiveness Trends.

---

## APPENDICES

### **Appendix A: Survey Instruments (Full Text)**

[Include full pre-post surveys here]

### **Appendix B: Detailed Results Tables**

[Additional statistical tables]

### **Appendix C: Qualitative Coding Scheme**

[Thematic coding categories and examples]

### **Appendix D: System Logs & Metrics**

[Sample of usage data from S-SPARC]

---

**END OF REPORT**
