# AIREA 2026 — Submission Draft

# S-SPARC: A Sustainable, Self-Governing AI Learning Assistant for Programming Education

**Competition:** 2nd International Competition on Artificial Intelligence in Education (AIREA 2026)
**Host:** The Education University of Hong Kong (EdUHK)
**Stream:** Stream 2 — Development of Tailored AI Agents
**Primary Topic:** (7) Reliable and Relevant Generative AI Application in Education
**Secondary Topics:** (1) Personalised and Adaptive Learning, (4) Engagement and Motivation, (11) Education for Citizenship and Sustainable Development

---

## 1. Project Title and Tagline

**S-SPARC AI**
_A snippet-aware, retrieval-augmented AI coding tutor that learns responsibly, evaluates itself continuously, and measures its own environmental footprint._

---

## 2. Abstract (300 words, ready to submit)

Generative AI holds great promise as a personalised programming tutor, yet most AI learning assistants face two critical and often ignored problems: (1) the quality of their knowledge base degrades over time as duplicate, irrelevant, or low-quality code snippets accumulate without any systematic quality control; and (2) the environmental cost of querying large language models at scale is invisible to both learners and educators.

S-SPARC addresses both problems within a single, cohesive educational platform. The system provides students with a multilingual AI chatbot capable of answering programming questions across multiple languages (Python, Java, JavaScript, PHP, and more), with adaptive response routing: when a high-quality match already exists in the knowledge base, it is retrieved instantly using semantic embedding similarity; only when no reliable match is found is a large language model (GPT-4o) invoked.

What sets S-SPARC apart is its autonomous quality governance layer — a continuously running evaluator service that monitors and cleanses the knowledge base without human intervention. The evaluator combines Abstract Syntax Tree (AST) static analysis, LLM-as-a-Judge scoring, semantic similarity measurement, SHA-256 exact-duplicate detection, and Isolation Forest anomaly screening into a calibrated, snippet-aware quality pipeline. In an empirical run on a live dataset of 678 entries, the system retained 95.4% of valid data while correctly identifying 30 low-quality or duplicate entries for removal, with an average answer quality score of 8.61 out of 10.

Beyond content quality, S-SPARC makes the sustainability dimension of AI use visible and actionable. Every interaction is tracked for energy consumption, carbon emission, and water usage — presented to learners in equivalent everyday analogies (e.g., "this query used the same energy as lighting a 5-watt lamp for X minutes"). A gamification system with token economics and leaderboards further motivates learners to engage deeply while encouraging environmentally efficient usage.

S-SPARC demonstrates that reliable, relevant, and responsible AI in education is achievable today — not as a future aspiration, but as a working, empirically validated system.

---

## 3. Problem Statement

### 3.1 The Knowledge Base Decay Problem

AI tutors built on retrieval-augmented generation (RAG) rely on a knowledge base of curated code examples. In practice, this knowledge base accumulates noise over time: duplicate entries, semantically mismatched prompt-code pairs, and structurally broken snippets. Conventional code quality evaluators are calibrated for full production programs, not the partial, context-specific snippets typical of educational datasets (e.g., MBPP-style). Applying them blindly causes over-deletion of genuinely useful learning content.

**Gap:** No existing AI learning assistant includes an autonomous, snippet-aware quality control pipeline as a first-class system component.

### 3.2 The Hidden Environmental Cost

Calling a large language model for every student query has a measurable carbon, energy, and water footprint. This cost is typically invisible to both students and educators, creating no incentive for responsible, efficient AI usage.

**Gap:** AI learning platforms do not give learners visibility into — or agency over — the environmental impact of their AI interactions.

---

## 4. Proposed Solution: S-SPARC AI

S-SPARC is a full-stack AI-powered education platform composed of four tightly integrated layers:

### Layer 1: Adaptive Response Engine (Python + Flask)

The core backend decides, per query, whether to answer via:

- **Direct retrieval** — semantic embedding search returns a high-confidence match (similarity ≥ 0.90) from the curated knowledge base. Zero LLM tokens consumed.
- **LLM generation** — GPT-5.2 is invoked only when no sufficiently similar snippet exists. The generated response is then stored back to enrich the knowledge base for future learners.

This adaptive routing reduces unnecessary LLM usage, directly lowering cost and environmental impact.

### Layer 2: Autonomous Quality Governance Service

A dedicated microservice (`code_evaluator_service`) runs continuously and evaluates every snippet in the knowledge base using a multi-stage pipeline:

| Stage               | Method                                                             | What it catches                      |
| ------------------- | ------------------------------------------------------------------ | ------------------------------------ |
| Static analysis     | AST parsing + Radon (Cyclomatic Complexity, Maintainability Index) | Broken syntax, unmaintainable code   |
| Semantic alignment  | Multilingual-E5 embedding cosine similarity                        | Prompt-code mismatch                 |
| Quality scoring     | LLM-as-a-Judge (GPT-4o) with heuristic fallback                    | Low logic, readability, completeness |
| Duplicate detection | SHA-256 content hashing                                            | Exact duplicate entries              |
| Anomaly screening   | Isolation Forest (scikit-learn)                                    | Statistical outliers                 |

Entries are classified into **VALID**, **REVIEW_REQUIRED**, or **DELETE_CANDIDATE**. All deletions are preceded by automatic JSON backup to ensure audit trails. A weekly scheduled run ensures continuous hygiene.

**Empirical validation result (678 entries, live production dataset):**

- Valid: 647 entries (95.4%)
- Identified for removal: 30 entries (23 duplicates + 7 low-quality)
- Average semantic similarity: 0.8774
- Average quality score: 8.61 / 10

### Layer 3: Sustainability Dashboard

Every AI interaction is measured and displayed to learners:

- **Energy** (Wh consumed by query)
- **Carbon** (kgCO₂eq, tracked via CodeCarbon)
- **Water** (mL equivalent)
- **Everyday analogies**: "equivalent to watching X seconds of short-form video" or "equivalent to traveling Y meters by motorbike"

A dedicated **Sustainability page** shows cumulative impact and the comparative savings from retrieval-vs-LLM usage, making the environmental dimension tangible and educational.

### Layer 4: Gamification and Assessment System

- Token economy: students earn and spend tokens per interaction
- Points and leaderboard: engagement-driven ranking visible on a gamification dashboard
- Formal assessment module: structured programming exercises with AI-assisted feedback
- Course progression tracking: learning journeys across multiple topics

---

## 5. Innovation and Differentiation

| Dimension                  | Conventional AI Tutors                   | S-SPARC                                                |
| -------------------------- | ---------------------------------------- | ------------------------------------------------------ |
| Knowledge base quality     | Static or manually maintained            | Autonomous, continuously self-governing                |
| Evaluation approach        | Full-program metrics (not snippet-aware) | Calibrated for partial educational snippets            |
| LLM usage                  | Every query hits LLM                     | Adaptive: retrieval-first, LLM only when needed        |
| Environmental transparency | Not tracked                              | Tracked per query, visible to learner                  |
| Duplicate management       | Manual or absent                         | Automated SHA-256 hashing                              |
| Anomaly detection          | Not present                              | Isolation Forest on live data                          |
| Auditability               | Minimal                                  | Full JSON backup + report artifacts per evaluation run |

---

## 6. Alignment with AIREA 2026 Criteria

### Innovation

S-SPARC introduces the first empirically validated, snippet-aware autonomous knowledge base governance pipeline embedded inside an AI learning assistant. The combination of LLM-as-a-Judge + AST + Isolation Forest for educational code evaluation is novel.

### Educational Impact

- Learners receive higher-quality, more relevant answers because the knowledge base is actively maintained.
- The sustainability dashboard introduces a new literacy: understanding and reducing the environmental cost of AI usage.
- Gamification sustains motivation across programming courses.

### Relevance of AI Application

The system is grounded in a real running deployment with empirical results, not a prototype. Quality governance is operational, not theoretical.

### Responsible AI

- Adaptive routing minimises unnecessary LLM calls → lower carbon footprint.
- All deletions are backed up before execution → no irreversible data loss.
- Dry-run mode available for safe experimentation.
- Environmental impact is surfaced transparently to users.

---

## 7. Technical Stack Summary

| Component         | Technology                                              |
| ----------------- | ------------------------------------------------------- |
| Backend API       | Python 3.13, Flask, threading                           |
| Frontend          | PHP 8, Tailwind CSS, Chart.js                           |
| Embedding model   | `intfloat/multilingual-e5-base` (Sentence-Transformers) |
| LLM               | GPT-4o via OpenAI API                                   |
| Static analysis   | `radon` (complexity + maintainability)                  |
| AST parsing       | Python `ast` stdlib                                     |
| Anomaly detection | `sklearn.ensemble.IsolationForest`                      |
| Carbon tracking   | CodeCarbon                                              |
| Evaluator service | FastAPI + Uvicorn                                       |
| Scheduler         | APScheduler (weekly automated runs)                     |
| Database          | MySQL / MariaDB                                         |
| Containerisation  | Docker Compose                                          |

---

## 8. Demo Outline (for video submission)

Recommended structure for the 3–5 minute demonstration video:

1. **[0:00–0:30]** Problem framing — why knowledge base quality and environmental cost matter in AI education
2. **[0:30–1:30]** Live chatbot demo — ask a programming question, show retrieval path vs LLM path, show emissions tracking per response
3. **[1:30–2:30]** Code evaluator dashboard — show evaluation report, pipeline stages, classification results (valid/duplicate/low-quality)
4. **[2:30–3:30]** Sustainability page — show cumulative carbon/energy/water metrics, everyday analogies
5. **[3:30–4:30]** Gamification — token balance, leaderboard, assessment module
6. **[4:30–5:00]** Closing — empirical results summary (678 entries, 95.4% valid, avg score 8.61/10)

---

## 9. Team and Affiliation

_(Isi sesuai detail kamu dan anggota tim sebelum submit)_

- Team name: —
- Institution: —
- Members (max 4): —
- Contact: —

---

## 10. Supporting Materials Checklist

- [ ] Demo video (MP4, max 5 minutes)
- [ ] Slide deck (PDF, max 15 slides)
- [ ] This document as project overview
- [ ] GitHub repository link: https://github.com/06202003/s-sparc
- [ ] Screenshots of evaluator report, sustainability page, gamification dashboard
- [ ] Empirical results JSON: `code_evaluator_service/reports/evaluation_report_20260312T094424Z.json`
