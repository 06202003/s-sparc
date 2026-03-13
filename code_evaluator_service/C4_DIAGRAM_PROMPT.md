# Prompt untuk Generate C4 Diagram - Code Evaluator Service

Gunakan prompt ini untuk Gemini Nano / Claude / ChatGPT untuk generate C4 Architecture diagram.

---

## SYSTEM DESCRIPTION

**Project:** Code Evaluator Service (Standalone Microservice)

**Purpose:** Periodic quality control & cleanup on `code_embeddings` database table dalam S-SPARC platform.

**Architecture Type:** Hybrid Static + ML + LLM evaluation pipeline.

---

## SYSTEM CONTEXT (C4 Level 1)

### External Systems:

1. **MySQL Database** (`db_semantic_v3`) - existing S-SPARC database containing `code_embeddings` table
2. **OpenAI API** (gpt-4o-mini) - optional LLM judge for code quality evaluation
3. **Hugging Face Hub** - pretrained E5-multilingual embedding model (auto-downloaded on first run)

### Users/Actors:

- **System Administrator** - manual trigger via HTTP endpoints
- **Scheduler** (APScheduler) - automatic trigger every Sunday 03:00 AM (Asia/Jakarta)

---

## CONTAINER DIAGRAM (C4 Level 2)

### Primary Container: **Code Evaluator Service** (FastAPI on port 5055)

Consists of:

1. **FastAPI Web Server** - 3 HTTP endpoints
   - GET /health → health check
   - GET /run-evaluation → trigger evaluation (sync or async)
   - GET /stats → latest report metadata

2. **APScheduler** - weekly cron scheduler
   - Runs evaluation every Sunday 03:00 AM
   - Timezone: Asia/Jakarta

3. **Evaluation Engine** - orchestrator + pipeline logic
4. **Storage** - local backup/report directories

### External Containers (outside service):

- **MySQL** (database)
- **OpenAI API** (LLM)
- **Hugging Face Hub** (model registry)

### Communication:

- FastAPI ↔ MySQL (pymysql)
- Evaluation Engine ↔ OpenAI API (openai SDK)
- Evaluation Engine → Hugging Face (automatic download via sentence-transformers)
- Evaluation Engine → Local filesystem (backup, reports, logs)

---

## COMPONENT DIAGRAM (C4 Level 3)

### Components WITHIN Code Evaluator Service:

#### **A. Configuration Layer**

- `config.py` → Settings dataclass
  - Reads .env variables
  - Provides runtime configuration
  - Exposes: host, port, db credentials, thresholds, model names, timezone, etc.

#### **B. Data Access Layer**

- `database.py` → DatabaseClient
  - Connects to MySQL via pymysql
  - Auto-detects schema (code vs generated_code column)
  - Batch iteration over code_embeddings entries
  - Backup/deletion operations

#### **C. Feature Extraction Layer**

- `static_analysis.py`
  - Language detection (regex)
  - Code metrics (line count, loops, functions)
  - Cyclomatic complexity (radon library)
  - Maintainability index (radon)
  - Static score computation (0-10)

- `embedding_model.py`
  - Sentence-Transformers wrapper
  - E5-multilingual lazy-load
  - Semantic similarity: cosine(embedding(prompt), embedding(code))
  - Output: similarity_score (0-1)

#### **D. Evaluation Layer**

- `llm_judge.py` → LLMJudge
  - OpenAI gpt-4o-mini JSON mode call
  - Fallback heuristic scoring (if no API key)
  - Scores: alignment, logic, quality, readability, completeness (0-10 each)
  - Summary string

- `anomaly_detection.py` → AnomalyDetector
  - Isolation Forest (scikit-learn)
  - 5-feature input: line_count, complexity, similarity, static_score, final_score
  - Output: anomaly_score (0-1), suspicious (bool)

#### **E. Orchestration Layer**

- `evaluator_pipeline.py` → EvaluatorPipeline
  - Main entry point for 6-step evaluation
  - Row-by-row pipeline: language → static → embedding → llm → anomaly → score
  - Duplicate detection (SHA256 hashing)
  - Deletion decision logic (INVALID / LOW_QUALITY / DUPLICATE)
  - Backup creation (atomic transaction)
  - Report generation (JSON)

#### **F. API & Scheduler Layer**

- `evaluator_app.py` → FastAPI app
  - Endpoint handlers (/health, /run-evaluation, /stats)
  - BackgroundTasks integration
  - Startup/shutdown lifespan

- `scheduler.py` → EvaluatorScheduler
  - APScheduler CronTrigger wrapper
  - Automatic weekly execution
  - Timezone handling

---

## PIPELINE FLOW (Data/Control Flow - C4 Level 3 Detail)

### Evaluation Step Sequence:

```
START (via HTTP /run-evaluation or scheduled cron)
  ↓
[DatabaseClient.iterate_entries(batch_size=50)]
  ↓ (for each row in batch)
[1. StaticAnalysis.analyze(code)]
  → detected_language, line_count, cyclomatic_complexity, static_score
  ↓
[2. EmbeddingModel.similarity(prompt, code)]
  → semantic_similarity_score (0-1)
  ↓
[3. LLMJudge.judge(prompt, code, language, similarity, static_result)]
  → IF api_key exists:
     call OpenAI API → alignment, logic, quality, readability, completeness
  → ELSE:
     heuristic_judge() → same fields
  ↓
[4. AnomalyDetector.detect(features)]
  → anomaly_score, suspicious flag
  ↓
[5. EvaluatorPipeline._compute_final_score()]
  → final_score = 0.35*alignment + 0.25*logic + 0.20*(similarity*10) + 0.10*static + 0.10*readability
  ↓
[6. Deletion Decision Logic]
  IF semantic_similarity < 0.55 → INVALID
  ELIF final_score < 6.0 → LOW_QUALITY
  ELIF duplicate detected → DUPLICATE
  THEN:
    - Create JSON backup via DatabaseClient.backup()
    - Delete row from DB (unless dry_run=true)
    - Log deletion_reason
  ↓
[Generate Report JSON]
  - Per-entry evaluation details
  - Summary stats (total, deleted, deleted_by_reason)
  - Backup path, timestamps
  ↓
END (write report to code_evaluator_service/reports/{timestamp}.json)
```

---

## DEPLOYMENT ARCHITECTURE (C4 Level 2 Alternative View)

```
[Local Machine / Server]
├── Python 3.11+
├── code_evaluator_service/
│   ├── evaluator_app.py (FastAPI, port 5055)
│   ├── scheduler.py (APScheduler)
│   ├── evaluator/ (6 modules above)
│   ├── backup/ (JSON backups)
│   ├── reports/ (JSON reports)
│   └── logs/ (service logs)
├── .env (config)
└── requirements.txt (dependencies)

[Network]
└── MySQL (localhost:3306 or remote)
└── OpenAI API (https://api.openai.com)
└── Hugging Face Hub (https://huggingface.co)
```

---

## KEY DECISIONS & CONSTRAINTS

1. **Embedding Model:** E5-multilingual (pretrained, auto-download)
2. **LLM Provider:** OpenAI gpt-4o-mini (graceful fallback to heuristics if unavailable)
3. **Scheduler:** APScheduler (local, weekly cron)
4. **Database:** Adaptive to both `code` and `generated_code` column names
5. **Safety:** Mandatory backup before deletion, dry-run mode support
6. **Isolation Forest:** Advisory only, not deletion trigger
7. **Scoring:** Weighted composite formula (alignment 35%, logic 25%, similarity 20%, static 10%, readability 10%)

---

## REQUEST FOR C4 DIAGRAM

**Please generate a Mermaid C4 diagram (or PlantUML C4 syntax) that shows:**

1. **System Context (Level 1):**
   - Code Evaluator Service box
   - MySQL Database external system
   - OpenAI API external system
   - Hugging Face Hub external system
   - Administrator user
   - Scheduler user

2. **Container (Level 2):**
   - FastAPI Web Server
   - APScheduler
   - Evaluation Engine
   - MySQL Database (external)
   - OpenAI API (external)
   - HF Hub (external)
   - Local Storage (backup/reports/logs)

3. **Component (Level 3):**
   - Config (config.py)
   - Database Client (database.py)
   - Static Analysis (static_analysis.py)
   - Embedding Model (embedding_model.py)
   - LLM Judge (llm_judge.py)
   - Anomaly Detection (anomaly_detection.py)
   - Evaluator Pipeline (evaluator_pipeline.py)
   - FastAPI App (evaluator_app.py)
   - Scheduler (scheduler.py)

4. **Relationships & Data Flow:**
   - Show all component interactions
   - Label arrows with data types (int, float, JSON, etc.)
   - Color-code: blue for internal, orange for external APIs, gray for storage

**Output Format:**

- Mermaid C4 diagram (for GitHub/VS Code rendering)
- Or PlantUML C4 syntax (for draw.io / PlantUML editor)

---

## EXAMPLE PROMPT FOR LLM:

```
You are an expert software architect. Given the following system description,
generate a C4 architecture diagram in Mermaid syntax.

System: Code Evaluator Service (FastAPI microservice for code quality evaluation)

[PASTE FULL SYSTEM DESCRIPTION HERE]

Requirements:
- System Context: Show external dependencies (MySQL, OpenAI, HuggingFace)
- Container Level: Show FastAPI, Scheduler, Evaluation Engine, Storage
- Component Level: Show all 9 modules (config, database, static_analysis, embedding, llm, anomaly, pipeline, app, scheduler)
- Color-code: internal=blue, external APIs=orange, storage=gray
- Add data flow arrows with types
- Title: "Code Evaluator Service - C4 Architecture"

Generate Mermaid C4 diagram code ready to render.
```

---

## NOTES

- Isolation Forest is advisory only (does not trigger deletion)
- Deletion happens only for: INVALID (sim < 0.55), LOW_QUALITY (score < 6.0), DUPLICATE (hash match)
- Service reads .env from project root AND service root (service root overrides project root)
- Database column name is auto-detected on first run (code or generated_code)
- Backup is atomic transaction: if backup fails, deletion is skipped
- All dates/times logged in UTC, but scheduler runs in Asia/Jakarta timezone
