from __future__ import annotations

import asyncio
import logging
from contextlib import asynccontextmanager
from logging.handlers import RotatingFileHandler
from pathlib import Path

from fastapi import BackgroundTasks, FastAPI, HTTPException

from .evaluator.config import get_settings
from .evaluator.database import DatabaseClient
from .evaluator.evaluator_pipeline import EvaluatorPipeline
from .scheduler import EvaluatorScheduler


settings = get_settings()


def configure_logging() -> None:
    log_path = settings.log_dir / "code_evaluator_service.log"
    root_logger = logging.getLogger()
    if root_logger.handlers:
        return

    root_logger.setLevel(getattr(logging, settings.log_level.upper(), logging.INFO))
    formatter = logging.Formatter("%(asctime)s | %(levelname)s | %(name)s | %(message)s")

    file_handler = RotatingFileHandler(log_path, maxBytes=2_000_000, backupCount=5, encoding="utf-8")
    file_handler.setFormatter(formatter)
    root_logger.addHandler(file_handler)

    console_handler = logging.StreamHandler()
    console_handler.setFormatter(formatter)
    root_logger.addHandler(console_handler)


configure_logging()

database = DatabaseClient(settings)
pipeline = EvaluatorPipeline(settings=settings, database=database)
scheduler = EvaluatorScheduler(pipeline=pipeline, settings=settings)


@asynccontextmanager
async def lifespan(_: FastAPI):
    scheduler.start()
    try:
        yield
    finally:
        scheduler.shutdown()


app = FastAPI(title="Code Evaluator Service", version="1.0.0", lifespan=lifespan)


@app.get("/health")
async def health() -> dict:
    return {
        "status": "ok",
        "service": settings.service_name,
        "db_connected": database.ping(),
        "scheduler_running": scheduler.scheduler.running,
        "evaluation_running": pipeline.is_running(),
        "port": settings.port,
    }


@app.get("/run-evaluation")
async def run_evaluation(background_tasks: BackgroundTasks, background: bool = True) -> dict:
    if pipeline.is_running():
        raise HTTPException(status_code=409, detail="Evaluation is already running")

    if background:
        background_tasks.add_task(pipeline.run, "manual-background")
        return {
            "status": "accepted",
            "message": "Evaluation started in background",
        }

    try:
        report = await asyncio.to_thread(pipeline.run, "manual")
        return report
    except RuntimeError as exc:
        raise HTTPException(status_code=409, detail=str(exc)) from exc
    except Exception as exc:
        raise HTTPException(status_code=500, detail=str(exc)) from exc


@app.get("/stats")
async def stats() -> dict:
    latest = pipeline.read_latest_stats()
    try:
        latest["current_db_entries"] = database.count_entries()
        latest["db_connected"] = True
    except Exception as exc:
        logging.getLogger(__name__).warning("Failed to count DB entries: %s", exc)
        latest["current_db_entries"] = None
        latest["db_connected"] = False
        latest["db_error"] = str(exc)
    latest["scheduler_running"] = scheduler.scheduler.running
    latest["evaluation_running"] = pipeline.is_running()
    latest["service_root"] = str(Path(__file__).resolve().parent)
    return latest
