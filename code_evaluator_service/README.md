# Code Evaluator Service

Standalone evaluator service for periodic quality control on the `code_embeddings` table.

## What it does

- Scans `code_embeddings` in batches
- Detects programming language heuristically
- Runs static code analysis
- Measures semantic similarity between prompt and stored code
- Uses LLM-as-a-Judge when an API key is available, otherwise falls back to deterministic heuristics
- Flags duplicates, invalid entries, and low-quality entries
- Creates JSON backups before deletion
- Deletes bad rows only after backup succeeds
- Generates JSON evaluation reports
- Runs automatically every Sunday at 03:00 AM with APScheduler

## Endpoints

- `GET /health`
- `GET /run-evaluation`
- `GET /run-evaluation?background=true`
- `GET /stats`

## Run locally

```powershell
pip install -r code_evaluator_service/requirements.txt
python -m uvicorn code_evaluator_service.evaluator_app:app --host 0.0.0.0 --port 5055
```

Or:

```powershell
python -m code_evaluator_service
```

## Environment variables

The service reads database credentials from the same `.env` keys already used by the existing platform:

- `MYSQL_HOST`
- `MYSQL_PORT`
- `MYSQL_USER`
- `MYSQL_PASSWORD`
- `MYSQL_DB`

Optional evaluator-specific variables:

- `EVALUATOR_PORT=5055`
- `EVALUATOR_BATCH_SIZE=50`
- `EVALUATOR_SEMANTIC_THRESHOLD=0.80`
- `EVALUATOR_REVIEW_SCORE_THRESHOLD=4.8`
- `EVALUATOR_FINAL_SCORE_THRESHOLD=5.2`
- `EVALUATOR_EMBEDDING_MODEL=intfloat/multilingual-e5-base`
- `EVALUATOR_LLM_MODEL=gpt-4o`
- `EVALUATOR_OPENAI_API_KEY=...`
- `EVALUATOR_TIMEZONE=Asia/Jakarta`
- `EVALUATOR_DRY_RUN=false`

## Output directories

- `code_evaluator_service/backup/`
- `code_evaluator_service/reports/`
- `code_evaluator_service/logs/`

## Notes

- The current repository schema uses `code` instead of `generated_code` inside `code_embeddings`. The evaluator detects that automatically and aliases it to `generated_code` internally.
- Duplicate rows are detected using exact prompt+code hashing. The first copy is kept; later copies are removed.
- Anomaly detection is advisory. It marks suspicious rows in the report but does not delete rows by anomaly signal alone.
- The default scoring is calibrated for retrieval-oriented code snippets, not only for full standalone production-style programs.
- Entries with `final_score` between `EVALUATOR_REVIEW_SCORE_THRESHOLD` and `EVALUATOR_FINAL_SCORE_THRESHOLD` are flagged as `REVIEW_REQUIRED` (kept in DB, not auto-deleted).
