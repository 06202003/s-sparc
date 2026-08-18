# S-SPARC AI

S-SPARC AI is an AI-powered learning assistant for programming and sustainability education. The project combines a Python backend, a PHP frontend, retrieval-augmented code assistance, automated knowledge-base quality control, gamification, assessment support, and environmental impact tracking.

The system is designed to answer programming questions efficiently while keeping the underlying code knowledge base clean, relevant, and reliable over time.

## Why This Project Exists

Most AI coding assistants focus only on generating answers. S-SPARC takes a broader approach:

- It serves learners through a chat-based programming assistant.
- It prefers retrieval over full LLM generation when a high-quality answer already exists.
- It continuously evaluates and cleans the code knowledge base using static analysis, semantic similarity, LLM-based judging, duplicate detection, and anomaly screening.
- It makes AI usage more transparent by tracking energy, carbon, and water impact.

This makes S-SPARC useful not only as a chatbot, but also as a controlled educational AI platform.

## Core Features

### 1. AI Programming Assistant (Hybrid LLM Architecture)

- Chat-based code support for learners with **Google Gemini Flash Lite** (Cloud) & **Ollama Qwen2.5-Coder 14B** (Local).
- Multi-Provider Gateway managing a 6 API key pool (`GEMINI_API_KEY_1..6`) with automatic round-robin rotation.
- **Adaptive Router** for intelligent hybrid routing:
 - **Game ON Courses (`game_course.is_active = 1`)**: Controlled by E-STRANGE Gamification Points ($\ge 100$ points required, 10 points deducted per successful Cloud request).
 - **Game OFF Courses (`game_course.is_active = 0`)**: Controlled by Token Quota Limit (`GAME_OFF_TOKEN_LIMIT=5000` tokens). Free Cloud access until token quota is exhausted, then routes to Local Ollama.
 - **Technical Rate Limit Failover**: Seamless automatic failover to Ollama Runtime if all 6 Gemini keys hit rate limits (HTTP 429).
- Support for educational programming scenarios and code explanation tasks.

### 2. Retrieval-Augmented Knowledge Base

- Uses semantic similarity to match user prompts with stored code snippets.
- Reduces unnecessary LLM calls when a suitable answer already exists (similarity $\ge 90\%$, 0 Token Cost).
- Stores and reuses useful programming examples across user sessions.

### 3. Automated Code Evaluator Service

- Periodically scans the `code_embeddings` knowledge base.
- Detects duplicates, low-quality snippets, and weak prompt-code matches.
- Produces JSON reports, backups, and latest evaluation statistics.
- Supports dry-run mode for safe validation before deletion.

### 4. E-STRANGE Gamification and Assessment Integration

- E-STRANGE Gamification Points formula:
 $$\text{Total Point} = \text{SUM}(\text{originality\_point}) + \text{SUM}(\text{efficiency\_point}) + \text{SUM}(\text{quality\_point})$$
- Default token threshold base set to 0 (`max(0, ...)`).
- Leaderboards and dashboards for student motivation.
- Assessment-related endpoints and frontend pages for learning workflows.

### 5. Sustainability Tracking

- Tracks environmental impact for AI usage.
- Surfaces carbon, energy, and water estimates to users.
- Supports sustainability-focused educational narratives and reporting.

## Repository Architecture

This repository is organized into several major areas.

### Backend (Python)

- `app.py`: main Flask application entry point.
- `run_production.py`, `run_production_server.py`: production-oriented launch helpers.
- `password_management.py`, `clear_old_retrieval_tokens.py`, and similar scripts: operational utilities.

### Evaluator Service

- `code_evaluator_service/`: standalone evaluation service for quality control on code snippets.
- Includes API endpoints, scheduler support, anomaly detection, static analysis, and report generation.

### Frontend (PHP)

- `frontend/`: web interface for login, chat, dashboards, gamification, course pages, and sustainability pages.

### Database and Migrations

- `db_migrations/`: SQL files for schema setup and incremental updates.

### Semantic Similarity and Experiments

- `semantic_similarity/`: utilities for retrieval and embedding-related work.
- `pengujian semantic similarity/`: local experimental scripts and visualization assets.

### Local Models and Assets

- `pretrained_model/`: local large model files used during development or evaluation.
- These files are ignored by Git because of size.

## High-Level System Flow

1. A learner submits a programming-related prompt.
2. The backend computes similarity against the stored knowledge base.
3. If a strong match is found, the system returns a retrieval-based answer.
4. If no strong match is found, the system routes the prompt to GPT.
5. The response is shown in the frontend and tracked for usage, gamification, and environmental impact.
6. Separately, the evaluator service periodically reviews the knowledge base and flags duplicates, low-quality content, or suspicious entries.

## Code Evaluator Service

The evaluator service is one of the main differentiators of this project.

### What It Does

- Reads code snippet entries from the `code_embeddings` table in batches.
- Detects programming language heuristically.
- Runs static analysis, including syntax and complexity-related checks.
- Measures semantic similarity between prompt and code.
- Uses LLM-as-a-Judge when an API key is available, with heuristic fallback when it is not.
- Detects exact duplicates through hashing.
- Applies anomaly detection on numerical evaluation features.
- Produces deletion candidates, review candidates, and valid entries.

### Main Methods Used

- AST parsing for Python syntax and structure analysis.
- Radon metrics for cyclomatic complexity and maintainability index.
- Embedding similarity for prompt-code alignment.
- GPT-based structured judging for semantic quality scoring.
- SHA-256 hashing for duplicate detection.
- Isolation Forest for anomaly detection.

### Evaluator Endpoints

- `GET /health`
- `GET /run-evaluation`
- `GET /run-evaluation?background=true`
- `GET /stats`

### Example Evaluation Result

From the latest recorded evaluation run:

- Total entries evaluated: 678
- Valid entries: 647
- Review entries: 1
- Delete candidates: 30
- Average semantic similarity: 0.8774
- Average final score: 8.6149 / 10
- Delete reasons: 23 duplicates, 7 low-quality

This indicates that the evaluator is not only theoretical; it already runs on real data and generates measurable results.

## Tech Stack

### Backend

- Python 3.13+
- Flask
- FastAPI (for evaluator service)
- APScheduler

### AI / ML

- OpenAI GPT-4o
- Sentence-Transformers / multilingual embeddings
- scikit-learn
- Radon
- AST (Python standard library)

### Frontend

- PHP 8+
- Composer
- Tailwind CSS
- Bootstrap
- Chart.js
- DataTables

### Data / Infra

- MySQL / MariaDB
- Docker Compose (optional)

## Local Setup

### 1. Python Backend

From the project root:

```bash
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
python app.py --port 5000
```

To expose the backend on your local network:

```bash
python app.py --host 0.0.0.0 --port 5000
```

For heavier concurrent usage:

```bash
python run_production.py
```

### 2. Frontend

```bash
cd frontend
composer install
php -S localhost:8000
```

Then open `http://localhost:8000`.

### 3. Evaluator Service

```bash
pip install -r code_evaluator_service/requirements.txt
python -m uvicorn code_evaluator_service.evaluator_app:app --host 0.0.0.0 --port 5055
```

Or run it as a module:

```bash
python -m code_evaluator_service
```

## Environment Configuration

This repository expects environment-based configuration for database credentials and API keys.

### Main database settings

- `MYSQL_HOST`
- `MYSQL_PORT`
- `MYSQL_USER`
- `MYSQL_PASSWORD`
- `MYSQL_DB`

### Evaluator-specific optional settings

- `EVALUATOR_PORT`
- `EVALUATOR_BATCH_SIZE`
- `EVALUATOR_SEMANTIC_THRESHOLD`
- `EVALUATOR_REVIEW_SCORE_THRESHOLD`
- `EVALUATOR_FINAL_SCORE_THRESHOLD`
- `EVALUATOR_EMBEDDING_MODEL`
- `EVALUATOR_LLM_MODEL`
- `EVALUATOR_OPENAI_API_KEY`
- `EVALUATOR_TIMEZONE`
- `EVALUATOR_DRY_RUN`

Use `.env.example` as the starting point where available.

## Database Migrations

The `db_migrations/` directory contains SQL scripts for schema creation and updates. Apply them in order using your preferred database client.

Examples include:

- `001_add_user_courses.sql`
- `002_add_env_impact_filters.sql`
- `003_add_assessment_fields.sql`

## Running with Docker

If you want a container-based workflow, review `docker-compose.yml` and run:

```bash
docker compose up --build
```

You may still need to adapt environment variables, ports, and database initialization to your local setup.

## Important Notes About Large Files

- Local pretrained models are intentionally excluded from Git.
- Evaluator outputs such as logs, reports, and backups are also ignored.
- If you clone this repository on a fresh machine, you will need to restore model files manually before running the full semantic or evaluation pipeline.

## Recommended Reading

- [code_evaluator_service/README.md](code_evaluator_service/README.md)
- [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)
- [QUICK_START_PRODUCTION.md](QUICK_START_PRODUCTION.md)
- [PERFORMANCE_OPTIMIZATION.md](PERFORMANCE_OPTIMIZATION.md)
- [report.md](report.md)

## Use Cases

This repository is relevant for:

- AI-assisted programming education
- Retrieval-augmented code tutoring
- Knowledge-base quality control for code snippets
- Sustainability-aware AI systems
- Gamified educational platforms

## Project Status

This repository is an active research and engineering project. It contains production-oriented components, experiment artifacts, and supporting utilities in the same workspace. If you are using it externally, start from the backend, frontend, and evaluator service directories first.

## Contributing

Contributions are easier to review when they are small and focused.

- Keep large model files out of Git.
- Prefer focused changes over broad refactors.
- Update documentation when changing architecture, setup, or environment requirements.

## License

No repository-wide license file is currently included. Add a `LICENSE` file if you want to publish this repository with an explicit open-source license.
