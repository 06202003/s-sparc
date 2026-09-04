# E-STRANGE™ & S-SPARC AI: Smart Software Engineering & Pedagogical Adaptive Retrieval Assistant

> **An Enterprise-Grade Educational Platform Integrating Performance Support (E-STRANGE™), Code Similarity Detection (SSTRANGE LSH), and Pedagogical AI Retrieval (S-SPARC).**

[![Platform Version](https://img.shields.io/badge/Platform-v3.2_Enterprise-0052CC?style=for-the-badge&logo=rocket&logoColor=white)](#)
[![AIREA 2026 Award](https://img.shields.io/badge/Award-AIREA_2026_Merit_Award-FFD700?style=for-the-badge&logo=trophy&logoColor=black)](#publications)
[![IEEE Award](https://img.shields.io/badge/IEEE-ICALT_2023_Best_Paper-006699?style=for-the-badge&logo=ieee&logoColor=white)](#publications)
[![Python Version](https://img.shields.io/badge/Python-3.12-3776AB?style=for-the-badge&logo=python&logoColor=white)](https://python.org)
[![FastAPI](https://img.shields.io/badge/FastAPI-0.110.0-009688?style=for-the-badge&logo=fastapi&logoColor=white)](https://fastapi.tiangolo.com)
[![PHP](https://img.shields.io/badge/PHP-8.2_Enterprise-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.11_Cluster-003545?style=for-the-badge&logo=mariadb&logoColor=white)](https://mariadb.org)
[![Gemini 3.5 Flash Lite](https://img.shields.io/badge/AI_Engine-Google_Gemini_3.5_Flash_Lite-4285F4?style=for-the-badge&logo=google&logoColor=white)](https://ai.google.dev)
[![Green Computing](https://img.shields.io/badge/Green_Campus-Carbon_Tracking_IDN-2EA44F?style=for-the-badge&logo=leaf&logoColor=white)](#sustainability)

---

## 📌 Executive Summary & Foundational Platform

**S-SPARC AI** (*Smart Software Engineering & Pedagogical Adaptive Retrieval Assistant*) is an advanced AI-powered educational engine specifically built to enhance programming and software engineering instruction in higher education institutions.

```
+---------------------------------------------------------------------------------------------------+
|                                     S-SPARC AI ENTERPRISE ECOSYSTEM                               |
+---------------------------------------------------------------------------------------------------+
|  1. C-I-O-E PROTOCOL         |  2. MULTI-TIER ROUTER        |  3. HYBRID SEMANTIC CACHE            |
|  - Context, Input, Output,   |  - Tier 1: User Key         |  - BM25 Sparse Search                |
|    Error Trace Scaffolding   |  - Tier 2: System Key Pool   |  - SentenceTransformers Dense        |
|  - Shannon Entropy Eval      |  - Tier 3: Local Ollama      |  - Reciprocal Rank Fusion (RRF)      |
|  - Bloom's Taxonomy Alignment|  - IPv4 Socket Optimizer     |  - Sub-150ms / 0 Token Overhead      |
+---------------------------------------------------------------------------------------------------+
|  FOUNDATION: E-STRANGE™ LMS Platform (Student Performance Support) & SSTRANGE LSH Engine          |
+---------------------------------------------------------------------------------------------------+
```

---

### 🏛️ The Foundational LMS Baseline: E-STRANGE™ & SSTRANGE

S-SPARC AI is engineered to integrate into **E-STRANGE™**, an established educational Learning Management System (LMS) and performance support platform:

- **E-STRANGE™ Platform**: Serves as the overarching student performance support and assessment environment. It helps students understand code ethics (originality/similarity), code quality (readability and modularity), and computational efficiency through formative feedback and academic gamification.
- **SSTRANGE Similarity Engine**: The foundational background code similarity detection engine powered by **Locality Sensitive Hashing (MinHash & Super-Bit)** across Java, Python, C#, Dart, and Web Stack submissions. SSTRANGE's algorithmic foundation is backed by peer-reviewed publications, including the **Best Discussion Paper Award at IEEE ICALT 2023** ([IEEE ICALT Paper](https://ieeexplore.ieee.org/abstract/document/10260942)), **MDPI Education Sciences 2023** ([MDPI Paper](https://www.mdpi.com/2227-7102/13/1/54)), and **IEEE EDUNINE 2024** ([IEEE EDUNINE Paper](https://ieeexplore.ieee.org/abstract/document/10500603/)).
- **Evolution to S-SPARC AI**: S-SPARC AI elevates E-STRANGE™ into a next-generation smart LMS by embedding real-time AI tutoring, sub-150ms vector semantic caching, multi-tier failover routing, and green computing sustainability analytics directly into the student learning workflow.

---

### Strategic Institutional Value Proposition:

1. **For University Deans & Executive Leadership**:
   - **Zero Institutional API Cost ($0/year)**: Through the **Distributed Student API Protocol**, institutions deliver high-speed AI tutoring without recurring cloud LLM bills.
   - **Green Campus Compliance**: Integrated carbon footprint tracking calibrated for the Indonesian power grid baseline ($0.78 \text{ kg CO}_2/\text{kWh}$).

2. **For Computer Science & IT Faculty**:
   - **Automated Academic Integrity**: Seamless integration with E-STRANGE™ and SSTRANGE LSH for code quality, structure, and originality assessment.
   - **Formative Pedagogical Feedback**: Automatic evaluation of code structure, readability, and algorithmic complexity.

3. **For Students**:
   - **Structured AI Guidance (C-I-O-E Protocol)**: Prevents dependency on copy-paste AI by requiring context, input, output, and error trace definition.
   - **Sub-150ms Semantic Cache**: Instant solution retrieval with zero token overhead.

---

## 🌟 Core S-SPARC AI Architecture & Technological Pillars

### 1. Standardized C-I-O-E Pedagogical Protocol & Prompt Literacy Evaluator
S-SPARC AI trains students to develop structured computational thinking by enforcing the **C-I-O-E Protocol**:
- **[C] Context**: Task background, problem domain, and algorithmic constraints.
- **[I] Input**: Data pre-conditions, input data structures, and sample test cases.
- **[O] Output**: Expected post-conditions, return data types, and target time/space complexity.
- **[E] Error Trace / Constraints**: Compiler/interpreter error trace, failing test cases (WA/TLE), and self-debugging steps taken.

The system automatically assesses student prompt quality using mathematical **Shannon Entropy** ($H$) and **Technical Token Density** ($D$), assigning a real-time **Literacy Grade** (*Grade A / B / C*) alongside automated pedagogical guidance aligned with Bloom's Taxonomy.

---

### 2. Multi-Tier Adaptive Router & High-Availability Engine
To guarantee zero service disruption regardless of network conditions or individual quota limits, S-SPARC AI employs a 3-tier failover routing engine:
- **Tier 1 (User Personal Gemini Key)**: Students register their personal Google Gemini API Key (available for free via Google AI Studio). Inference requests execute directly via REST with latencies of **~1.5 - 2.5 seconds**.
- **Tier 2 (System Key Pool Fallback)**: If a user key is unconfigured or rate-limited, the router seamlessly falls back to a load-balanced *System API Key Pool* managed on the server.
- **Tier 3 (Local Zero-Cost Offline Fallback - Ollama)**: Should cloud network connectivity fail completely or hit rate limits (HTTP 429), the platform automatically redirects inference to a local **Ollama Qwen2.5-Coder 14B** instance, ensuring 100% offline reliability.

---

### 3. Hybrid Vector Semantic Caching (BM25 + SentenceTransformers RRF)
S-SPARC AI features a high-performance *Retrieval-Augmented Caching Engine* that combines:
- **Sparse Search (BM25)** for exact keyword, syntax, and function signature matching.
- **Dense Vector Search (SentenceTransformers `all-MiniLM-L6-v2`)** for semantic context and problem intent matching.
- **Reciprocal Rank Fusion (RRF)** to fuse sparse and dense rankings into a unified high-precision score.

When a student prompt exhibits high cosine similarity ($\ge 0.88$) with a verified solution in the `code_embeddings` database, S-SPARC AI responds **instantly (0.11 - 0.17 seconds)** without consuming API tokens (0 Tokens / FREE Tier).

---

### 4. Windows Network IPv4 Socket Optimizer
When deployed in university computer laboratories operating on Windows environments, standard Python `requests` calls to `generativelanguage.googleapis.com` often experience severe socket stalls due to IPv6 DNS resolution attempts (causing delays up to 78 seconds per request).

S-SPARC AI incorporates a custom **IPv4 Socket Resolver Patch** at the socket layer (`_ipv4_getaddrinfo`), which slashes execution latency from **78 seconds down to 2.7 seconds** consistently.

---

### 5. Green Computing & Environmental Footprint Tracking
S-SPARC AI advances institutional *Green Campus* initiatives by calculating the environmental footprint of every AI prompt in real time:

$$\text{Energy (kWh)} = \text{Tokens} \times \text{kWh/token} \times \text{PUE}$$

$$\text{Carbon (gCO2)} = \text{Energy (kWh)} \times \text{CIF} \times 1000$$

*Parameters:*
- **CIF (Carbon Intensity Factor - Indonesia)**: $0.78 \text{ kg CO}_2/\text{kWh}$ (Indonesian Electrical Grid Baseline).
- **PUE (Power Usage Effectiveness)**: $1.5$ (Efficient Data Center Benchmark).
- **Tree Absorption Equivalent**: Calculated based on standard annual absorption rates ($21.77 \text{ kg CO}_2/\text{year}$).

---

### 6. E-STRANGE Gamification Integration & Quotas
- **Daily Quota Badges**: Displays student daily allowance transparently (1,500 Requests/Day & 15 Requests/Minute for Gemini Free Tier).
- **Cooldown Rate Limiting**: Prevents automated spamming via enforced timers (60s for Live AI generation / 15s for Database Cache Hits).
- **Points Aggregator & Leaderboards**: E-STRANGE gamification scores are updated automatically based on prompt quality, code originality, and algorithmic efficiency.

---

## 🌐 Enterprise Request Architecture & Data Flow

```mermaid
sequenceDiagram
    autonumber
    actor Student as Student (Browser UI)
    participant PHP as PHP Frontend (cPanel)
    participant API as S-SPARC Backend (FastAPI)
    participant Cache as Semantic Cache (BM25 + Vector)
    participant Router as Adaptive AI Router
    participant DB as MariaDB Database

    Student->>PHP: Submit Prompt (C-I-O-E Protocol)
    PHP->>API: POST /api/generate-code (Header: X-User-ID)
    API->>Cache: Evaluate Cosine Similarity (Fast-Path >= 0.88)
    alt Cache Hit (0 Tokens / Sub-170ms)
        Cache-->>API: Return Verified Solution Code
    else Live Cloud / On-Prem AI Generation
        API->>Router: Route Request (User Key -> System Pool -> Local Ollama)
        Router-->>API: Generated AI Solution
        API->>DB: Auto-Ingest Solution & Log Carbon Metrics
    end
    API-->>PHP: Response Payload
    PHP-->>Student: Render Code Solution & Carbon Footprint Metrics
```

---

## 🔬 Empirical Validation & TRL Status

In strict alignment with competition parameters established by the **UNU Global AI Network** (which requires submitted solutions to demonstrate practical application potential and real-world validation beyond laboratory environments), S-SPARC AI is positioned at **Technology Readiness Level (TRL) 5/6**. The system has been rigorously evaluated in operational educational environments, demonstrating exceptional stability, pedagogical efficacy, and computational efficiency across multiple academic semesters.

### Key Performance Metrics & System Stability

Extensive multi-semester telemetry, utilizing live student cohorts within the E-STRANGE™ LMS environment, has yielded comprehensive empirical validation metrics spanning system architecture, retrieval accuracy, and educational outcomes:

| Evaluation Dimension | Empirical Metric / Result | System Validation Context |
| :--- | :--- | :--- |
| **System Regression & Stability** | **100% Pass Rate** (0 failures) | Comprehensive testing of all API bounds, prompt linters, and educational telemetry logged via automated test suites (`test_educational_api.py`). |
| **Retrieval Accuracy (Zero-LLM Gate)** | **MRR = 1.000**<br>Precision@1 = **100%**<br>Recall = **96.0%**<br>F1-Score = **97.96%** | Evaluated against a 200-query manual ground truth dataset, yielding zero False Positives at the 0.90 policy threshold. |
| **Context Payload Compression** | **78.8% Token Reduction** | The AST CodeCompressor effectively strips non-semantic boilerplate and comments, drastically reducing API payload weight before transmission. |
| **Knowledge Base Hygiene & Governance** | **95.43% Verified Retention Rate** | An autonomous evaluator daemon audited a 678-snippet live run over 18 minutes; it effectively purged 4.42% (comprising exact duplicates and low-quality code) while maintaining an average semantic similarity of 0.80. |
| **Semester-scale Inference Reduction** | **83.94% Token Reduction** | Across seven laboratory sessions, 1,781,845 of 2,122,873 potential tokens were handled through retrieval, leaving only 341,028 tokens for external inference. |
| **Reduction Consistency** | Early = **85.8%** (w1-w3)<br>Late = **86.9%** (w5-w7) | Early vs. late reduction rates showed no statistically significant difference ($t = -1.105, p = 0.273$). |
| **Weekly Reduction Variation** | $F = 0.859$, $p = 0.527$, $\eta^2_g = 0.0217$ | Repeated-measures analysis across seven sessions found no statistically significant weekly variation. |
| **Prompt Quality Association** | $\beta = 0.358$, $p < 0.001$, $R^2 = 0.438$ | Semester deployment regression showed a significant positive association between prompt quality and response quality. |
| **Inference Demand vs. Response Quality** | $\beta = -0.088$, $p = 0.683$, $R^2 = 0.003$ | No significant association was observed between inference-token ratio and response quality in the reported regression. |

---

### Pedagogical Efficacy & Cognitive Offloading Mitigation

The imposition of the 200-character **C-I-O-E protocol** and the 60-second reflection cooldown timer have demonstrated a profound and measurable impact on student interaction behaviors, directly mitigating the *LLM Dependency Paradox*. Telemetry data indicates a decisive shift in student behavior from "passive extraction" to "active formulation."

- **Reduction in Direct-Copy Submissions**: Prior to S-SPARC integration, standard chatbot interfaces facilitated rapid, thoughtless trial-and-error behaviors, with learning sessions averaging **7.4 interaction turns** as students repeatedly pasted errors. Following the implementation of metacognitive friction, the average debugging cycle plummeted to just **1.8 turns**. This confirms that students take the requisite time to formulate highly dense, accurate queries ($S_{\text{prompt}} \ge 0.80$) on their first attempt, significantly reducing cognitive offloading.
- **Improvement in Problem-Solving Retention**: The Plagiarism Defense mechanism has yielded an articulated code defense pass rate exceeding **85%**. This metric confirms that even when AI tools provide scaffolding and assist in code generation, the structural requirement to intellectually defend underlying logic prevents algorithmic comprehension atrophy, fostering genuine knowledge retention.
- **System Usability Scores (SUS) and Learning Autonomy**: The user experience, governed through an interactive C-I-O-E live indicator pill bar and dynamic profile badging (awarding titles such as *"Prompt Architect"* and *"Zero-Waste Compute Champion"*), resulted in exceptional self-reported learning autonomy. Students exhibit a clear, measurable progression over the semester, shifting from syntax-heavy requests (`mode="code"`) toward conceptual validation requests (`mode="summary"`) as their confidence and independent capabilities solidify.

---

## 🌍 UN Sustainable Development Goals (SDGs) Alignment

S-SPARC AI is systematically designed to advance multiple UN Sustainable Development Goals (SDGs), anchoring its pedagogical mechanisms in global sustainability, equitable access, and responsible resource consumption. The architectural philosophy reflects the UNU Macau mandate to bridge technological innovation with equitable growth, focusing on insights, challenges, and scalable contributions applicable to higher education in the Global South.

### 🎯 Primary Impact: SDG 4 (Quality Education)
- The primary mission of S-SPARC AI is to democratize specialized, high-quality computer science and software engineering tutoring.
- It addresses the severe imbalance between qualified technical faculty and students in developing nations, which limits personalized 1-on-1 pedagogical interventions for mastering complex programming concepts.
- Functioning as an infinitely scalable Metacognitive Scaffolding Coach, S-SPARC AI provides individualized, Bloom-tiered feedback at the point of cognitive failure.
- By embedding AI literacy into prompt submissions, S-SPARC AI keeps learning an active discipline rather than a passive transfer of generated code, expanding educational quality without depending on continuous human instructor availability.

### 💡 Secondary Impact: SDG 9 (Industry, Innovation & Infrastructure) & SDG 10 (Reduced Inequalities)
- S-SPARC AI fundamentally addresses technological and infrastructural inequalities dividing educational institutions in the Global North from those in developing nations.
- It overcomes reliance on high-bandwidth cloud connectivity and high token costs using a Zero-LLM Direct Retrieval bypass engine and multi-tier routing architecture.
- Queries exhibiting cosine similarity $\ge 0.88$ against the semantic cache bypass cloud LLMs, returning pre-verified answers from local infrastructure in **< 45ms** at zero token cost.
- Uncached queries cascade to an offline, locally hosted Ollama model (Qwen2.5-Coder 14B), enabling low-budget and bandwidth-constrained institutions to deliver advanced AI education.
- The backend calculates real-time environmental footprints using localized Grid Emission Factors ($\text{CIF} = 0.384$ for Indonesia) to provide Experiential Green AI Literacy. It displays energy, carbon, and water metrics on student dashboards and awards EcoPoints based on peer thresholds ($\text{Threshold} = 1.10 \times \text{Usage}_{\text{peers}}$) to incentivize efficient prompt engineering.

### 🤝 Secondary Impact: SDG 17 (Partnerships for the Goals)
- Built upon an open-architecture design, S-SPARC AI integrates seamlessly into institutional environments via the E-STRANGE™ Learning Management System framework.
- Its self-expanding knowledge repository uses semantic thresholding ($\max(\text{existing}) < 0.90$) to automatically capture new solutions, enabling higher education institutions to independently curate contextualized academic datasets without relying on proprietary commercial vendors.
- By facilitating the inter-university exchange of validated pedagogical vectors, this open-science ecosystem strengthens multi-institutional collaboration, directly advancing UN capacity-building goals and supporting the mission of the UNU Global AI Network.

---

## 🏢 Tech Stack & Infrastructure Matrix

| Layer | Component | Specification / Framework | Purpose |
| :--- | :--- | :--- | :--- |
| **AI Backend Core** | S-SPARC FastAPI Service | Python 3.12, Uvicorn Async | High-throughput asynchronous REST API gateway. |
| **LMS Frontend** | E-STRANGE Web Interface | PHP 8.2, Vanilla CSS, JS (ES6+) | Glassmorphism UI, Dark/Light mode, Responsive layout. |
| **Database Layer** | Enterprise Relational DB | MariaDB 10.11 / MySQL 8.0 | Stores user profiles, chat logs, queues, and vector embeddings. |
| **Vector Engine** | Hybrid Retrieval | SentenceTransformers (`MiniLM-L6-v2`), BM25 | RRF-fused dense and sparse vector search engine. |
| **LSH Engine** | Background Processor | Java 11+ Runnable JAR (`ScheduledSuspicionGenerator.jar`) | Computes SSTRANGE MinHash & Super-Bit similarity scores. |
| **AI Runtime** | Multi-Provider Gateway | Google Gemini 3.5 REST, LiteLLM, Ollama | Multi-tier cloud and offline AI execution engine. |

---

## 🔧 S-SPARC Backend Installation & Deployment Guide

Follow these step-by-step instructions to deploy the S-SPARC AI Python Backend service on institutional server infrastructure.

### 1. Prerequisites
- **Operating System**: Linux (Ubuntu 22.04 LTS / RHEL 9) or Windows Server.
- **Python**: Version 3.10 or 3.12.
- **Database**: MariaDB 10.6+ or MySQL 8.0+.

---

### 2. Environment Setup & Dependencies
```bash
# Clone the repository
git clone https://github.com/06202003/s-sparc.git
cd s-sparc

# Create virtual environment
python -m venv venv

# Activate venv (Windows)
.\venv\Scripts\activate
# Activate venv (Linux/macOS)
# source venv/bin/activate

# Install dependencies
pip install -r requirements.txt
```

---

### 3. Environment Configuration (`.env`)
Copy `.env.example` to `.env` and set your credentials:
```ini
FLASK_PORT=5000
FASTAPI_URL=https://estrangeinternal.itmaranatha.org
DB_HOST=127.0.0.1
DB_USER=your_db_user
DB_PASSWORD=your_db_password
DB_NAME=db_semantic
GEMINI_MODEL=gemini-3.5-flash-lite
```

---

### 4. Running the Backend Service
Start the S-SPARC FastAPI production server:
```bash
python run_fastapi.py
```

---

## 📡 Production REST API Specification

### 1. `POST /api/generate-code`
- **Headers**: `Content-Type: application/json`, `X-User-ID: <STUDENT_UUID>`
- **Request Body**:
```json
{
  "prompt": "[CONTEXT: Array Processing]\nFinding max element...\n[INPUT]\nnums = [1, 5, 3]\n[OUTPUT]\nReturn 5\n[ERROR TRACE]\nNone",
  "course_id": "COURSE_SE_2026",
  "assessment_id": "ASSESS_01"
}
```

### 2. `POST /api/user/api-key`
- **Request Body**:
```json
{
  "api_key": "AIzaSyYourPersonalGeminiApiKeyHere123",
  "terms_accepted": true
}
```

---

## 🏆 Major Awards & International Recognition

1. **🏆 AIREA 2026 Merit Award**: Awarded at the *2nd International Competition of AI in Education (AIREA 2026)* hosted by **The Education University of Hong Kong (EdUHK)**. Selected as a top winner among **212 project submissions from 13 countries**.
   - **Project Title**: *"S-SPARC: The Next Level of Eco-Conscious AI for Education, Empowering Sustainable Habits through Gamification"*
   - **Team**: Yehezkiel David Setiawan (Team Lead, Master of Computer Science), Johanes Mario Pranata, Archangela Sheilla Haryanto
   - **Faculty Advisor**: Oscar Karnalim, S.T., M.T., Ph.D., SMIEEE
2. **🏆 IEEE ICALT 2023 Best Discussion Paper Award**: Awarded by the IEEE Computer Society at the *2023 IEEE International Conference on Advanced Learning Technologies (ICALT)* for the SSTRANGE LSH engine algorithm.

---

## 📚 Scientific Citations & Publications

If referencing E-STRANGE™, SSTRANGE, or S-SPARC AI in academic literature, grant proposals, or institutional evaluations, please use the following citations:

```bibtex
@article{karnalim2023sstrange,
  title={SSTRANGE: Scalable Similarity TRacker in Academia with Natural Language Explanation},
  author={Karnalim, Oscar and others},
  journal={Education Sciences},
  volume={13},
  number={1},
  pages={54},
  year={2023},
  publisher={MDPI}
}

@inproceedings{karnalim2023csharp,
  title={Scalable Code Similarity Detection for C\# Submissions},
  author={Karnalim, Oscar and others},
  booktitle={2023 IEEE International Conference on Advanced Learning Technologies (ICALT)},
  year={2023},
  organization={IEEE},
  note={Best Discussion Paper Award}
}

@inproceedings{karnalim2024sensitive,
  title={Sensitive Code Similarity Detection for Higher Education},
  author={Karnalim, Oscar and others},
  booktitle={2024 IEEE World Engineering Education Conference (EDUNINE)},
  year={2024},
  organization={IEEE}
}
```

---

<p align="center">
  <b>S-SPARC AI Enterprise & E-STRANGE™ Ecosystem</b> • EdTech Research & Engineering Labs<br>
  <sub>Designed & Built for Next-Generation Higher Education in Indonesia. 🇮🇩</sub>
</p>
