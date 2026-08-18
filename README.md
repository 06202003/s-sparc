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

### Backend (Python FastAPI)

- `backend/main.py`: FastAPI application entry point with interactive Swagger (`/docs`) & Redocly (`/redocly`).
- `backend/api/`: API router endpoints (`auth`, `ai_chat`, `domain`, `admin`, `health`).
- `backend/services/`: Hybrid Adaptive Router (Gemini Cloud 6-key pool + Ollama Local fallback), gamification, and sustainability telemetry.
- `run_fastapi.py`, `start_backend.bat`: FastAPI backend launch helpers.

### Frontend (PHP E-STRANGE LMS & S-SPARC)

- `estrange/`: E-STRANGE Learning Management Platform, assignments, peer review ratings, gamification, and AI Chat Assistant.
- `frontend/`: Standalone S-SPARC PHP Web Frontend.
- `start_frontend.bat`: 1-Click launcher for PHP server.

### 1-Click Full System Launch (Windows)

- `start_full_system.bat`: Automatically launches both FastAPI Backend (Port 8000) and E-STRANGE Web Portal (Port 8080).

### Evaluator Service

- `code_evaluator_service/`: standalone evaluation service for quality control on code snippets.
- Includes API endpoints, scheduler support, anomaly detection, static analysis, and report generation.

### Database and Migrations

- `database/db_semantic_vfinal.sql`: Main database schema and seed data.
- `db_migrations/`: SQL files for schema setup and incremental updates.

### Semantic Similarity and Embeddings

- `semantic_similarity/`: utilities for retrieval and embedding-related work.
- `pretrained_model/`: local pretrained model files (LaBSE, E5, MPNet).

## High-Level System Flow

1. A learner submits a programming question via the E-STRANGE / S-SPARC web interface.
2. The FastAPI backend computes cosine similarity against stored code embeddings in MySQL.
3. If similarity >= 90%, it returns an instant retrieval answer with 0 Token Cost (FREE Tier).
4. If similarity < 90%, the **Adaptive Router** checks gamification points & token quota:
   - Points >= 100 (or quota < 5000): Routes to **Google Gemini Flash Lite** (Cloud 6-Key Pool).
   - Points < 100 (or quota >= 5000 / HTTP 429 Failover): Routes to **Ollama Qwen2.5-Coder 14B** (Local).
5. Responses are delivered with token and environmental telemetry (Wh, kg CO2e, mL H2O).

## Quick Setup & Launch

Detailed step-by-step instructions are available in [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md).

### 1. 1-Click Launch (Windows)
Double-click `start_full_system.bat` to launch both Backend (Port 8000) and Frontend (Port 8080).

### 2. Manual CLI Launch

**Backend (FastAPI):**
```bash
python -m venv .venv
.venv\Scripts\activate       # Windows
# source .venv/bin/activate  # Linux

pip install -r requirements.txt
python -m uvicorn backend.main:app --host 0.0.0.0 --port 8000 --reload
```

**Frontend (PHP):**
```bash
cd estrange
php -S 0.0.0.0:8080
```

## Recommended Reading

- [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) - Official complete deployment and production guide
- [README_DEPLOYMENT.md](README_DEPLOYMENT.md) - Quick deployment summary
- [docs/SYSTEM_STARTUP_GUIDE.md](docs/SYSTEM_STARTUP_GUIDE.md) - System startup and verification guide
- [docs/system_flow_diagrams.md](docs/system_flow_diagrams.md) - 11 End-to-end visual workflow diagrams
- [code_evaluator_service/README.md](code_evaluator_service/README.md) - Automated code evaluator service docs

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
