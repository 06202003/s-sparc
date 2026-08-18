You are a **Senior AI Researcher, Software Architect, and Technical Reverse Engineer**.

Your task is to deeply understand the existing **S-SPARC** project by inspecting the entire repository and reconstructing its **technical, functional, and research-level knowledge**.

### Context

S-SPARC has evolved over time, so its documentation, code, and terminology may not be perfectly consistent.

Do **not** assume what S-SPARC is supposed to be.

Instead:

> **Discover what S-SPARC actually is from the evidence inside the project.**

---

## TASK

Analyze the entire project and build a reliable mental model of S-SPARC.

Think through the project in this order:

**Purpose → Problem → Users → Features → Architecture → AI → Data → Algorithms → Research → Evaluation → Current Limitations**

Inspect source code, configuration, database structure, documentation, prompts, experiments, and other relevant project artifacts.

Do not modify anything.

---

## 1. Understand the "WHY"

Determine:

* What problem is S-SPARC trying to solve?
* Why was it created?
* Who uses it?
* What is the intended outcome?
* What is the core idea that makes S-SPARC different?

If documentation and implementation disagree, identify the discrepancy.

---

## 2. Understand the "WHAT"

Build a mental model of everything S-SPARC can currently do.

Identify:

* core features;
* secondary features;
* user workflows;
* AI capabilities;
* educational capabilities;
* analytics;
* gamification;
* sustainability mechanisms;
* integrations.

For each capability, determine whether it is:

**Implemented / Partial / Planned / Deprecated**

---

## 3. Understand the "HOW"

Reverse-engineer the actual architecture.

Trace important flows such as:

**User → Frontend → Backend → AI Processing → LLM → Response → Storage → Analytics**

Identify the real components involved at every step.

Understand:

* frontend;
* backend;
* APIs;
* database;
* services;
* middleware;
* AI orchestration;
* external services;
* local models;
* caching;
* queues;
* deployment.

---

## 4. Understand the AI

This is a critical section.

Determine exactly how S-SPARC uses AI.

Analyze:

* LLMs;
* model providers;
* system prompts;
* prompt construction;
* prompt processing;
* prompt enhancement;
* context management;
* conversation memory;
* retrieval;
* embeddings;
* semantic similarity;
* response reuse;
* model routing;
* fallback;
* response processing.

For every important AI mechanism explain:

> **Input → Process → Decision → Output**

Include important thresholds, formulas, models, or parameters when they exist.

---

## 5. Understand the Intelligence

Identify what makes S-SPARC more than a normal chatbot.

Look specifically for:

* decision mechanisms;
* classification;
* similarity detection;
* recommendation;
* adaptive behavior;
* context awareness;
* personalization;
* optimization;
* reuse;
* behavioral regulation.

Determine which mechanisms are actually implemented versus merely described.

---

## 6. Understand the Research

Extract the research knowledge embedded in the project.

Identify:

* research problem;
* research objectives;
* hypotheses/questions;
* methodology;
* algorithms;
* datasets;
* experiments;
* evaluation metrics;
* results;
* claimed contributions;
* limitations.

Separate:

**What has been demonstrated**

from

**What is only proposed or intended.**

---

## 7. Understand Sustainability

Determine exactly what "sustainability" means within the current S-SPARC implementation.

Inspect:

* computational efficiency;
* token reduction;
* semantic reuse;
* caching;
* energy estimation;
* carbon estimation;
* water estimation;
* local inference;
* cost reduction.

Extract any formulas, constants, assumptions, and measurements.

Do not judge their validity yet.

---

## 8. Understand Education

Identify everything already related to:

* students;
* programming;
* learning;
* tutoring;
* feedback;
* assessment;
* LMS;
* AI literacy;
* student behavior;
* learning performance;
* gamification.

Do not force an educational interpretation onto unrelated features.

---

## 9. Identify the Core

After analyzing everything, answer:

### What is the CORE intellectual idea of S-SPARC?

Not the framework.

Not Laravel.

Not the LLM.

Not the UI.

Identify the **research/innovation idea** that gives S-SPARC its identity.

Then identify:

### What are S-SPARC's 3–5 strongest assets?

These will later become the foundation for its future direction.

---

## 10. Identify the Gaps

Identify the most important weaknesses:

* technical;
* architectural;
* research;
* educational;
* AI;
* evaluation;
* deployment;
* scalability.

Do not propose solutions yet.

---

## 11. Build the Mental Model

At the end, provide a concise conceptual model:

> **S-SPARC is a [type of system] that solves [problem] for [users] by using [core mechanism], resulting in [outcome].**

Then explain the system in approximately **5–10 paragraphs** as if you were explaining it to another AI researcher who has never seen the repository.

---

# OUTPUT

Create:

`SSPARC_KNOWLEDGE.md`

Structure it as:

1. Executive Understanding
2. Problem & Purpose
3. Users & Use Cases
4. Current Features
5. System Architecture
6. AI/LLM Architecture
7. Intelligence Mechanisms
8. Data & Algorithms
9. Research & Experiments
10. Sustainability
11. Educational Components
12. Core Innovation
13. Strongest Assets
14. Current Limitations
15. Current Maturity / TRL
16. Complete Mental Model
17. Evidence & Important Files

---

# ANALYSIS RULES

**Do not modify the project.**

**Do not redesign the project.**

**Do not suggest AI for Education improvements yet.**

**Do not assume undocumented functionality.**

Always distinguish:

* **FACT** — directly verified from implementation.
* **INFERENCE** — reasonable interpretation from evidence.
* **UNKNOWN** — cannot be determined.

Prioritize the **actual implementation over documentation claims**.

The objective is not to produce a generic software summary.

The objective is:

> **Understand S-SPARC deeply enough that a separate AI researcher can later redesign its direction without needing to inspect the original repository.**
