from __future__ import annotations

import os
from dataclasses import dataclass, field
from functools import lru_cache
from pathlib import Path

from dotenv import load_dotenv


SERVICE_ROOT = Path(__file__).resolve().parents[1]
PROJECT_ROOT = SERVICE_ROOT.parent

load_dotenv(PROJECT_ROOT / ".env")
load_dotenv(SERVICE_ROOT / ".env", override=True)


@dataclass(slots=True)
class Settings:
    service_name: str = "code_evaluator_service"
    host: str = field(default_factory=lambda: os.getenv("EVALUATOR_HOST", "0.0.0.0"))
    port: int = field(default_factory=lambda: int(os.getenv("EVALUATOR_PORT", "5055")))
    mysql_host: str = field(default_factory=lambda: os.getenv("MYSQL_HOST", "localhost"))
    mysql_port: int = field(default_factory=lambda: int(os.getenv("MYSQL_PORT", "3306")))
    mysql_user: str = field(default_factory=lambda: os.getenv("MYSQL_USER", "root"))
    mysql_password: str = field(default_factory=lambda: os.getenv("MYSQL_PASSWORD", ""))
    mysql_db: str = field(default_factory=lambda: os.getenv("MYSQL_DB", "db_semantic"))
    batch_size: int = field(default_factory=lambda: int(os.getenv("EVALUATOR_BATCH_SIZE", "50")))
    semantic_similarity_threshold: float = field(
        default_factory=lambda: float(os.getenv("EVALUATOR_SEMANTIC_THRESHOLD", "0.80"))
    )
    review_score_threshold: float = field(
        default_factory=lambda: float(os.getenv("EVALUATOR_REVIEW_SCORE_THRESHOLD", "4.8"))
    )
    final_score_threshold: float = field(
        default_factory=lambda: float(os.getenv("EVALUATOR_FINAL_SCORE_THRESHOLD", "5.2"))
    )
    embedding_model_name: str = field(
        default_factory=lambda: os.getenv("EVALUATOR_EMBEDDING_MODEL", "intfloat/multilingual-e5-base")
    )
    llm_model: str = field(default_factory=lambda: os.getenv("EVALUATOR_LLM_MODEL", "gpt-4o-mini"))
    llm_api_key: str | None = field(
        default_factory=lambda: os.getenv("EVALUATOR_OPENAI_API_KEY") or os.getenv("OPENAI_API_KEY")
    )
    timezone: str = field(default_factory=lambda: os.getenv("EVALUATOR_TIMEZONE", "Asia/Jakarta"))
    dry_run: bool = field(default_factory=lambda: os.getenv("EVALUATOR_DRY_RUN", "false").lower() == "true")
    max_rows_per_run: int = field(default_factory=lambda: int(os.getenv("EVALUATOR_MAX_ROWS_PER_RUN", "0")))
    log_level: str = field(default_factory=lambda: os.getenv("EVALUATOR_LOG_LEVEL", "INFO"))
    backup_dir: Path = field(default_factory=lambda: SERVICE_ROOT / "backup")
    report_dir: Path = field(default_factory=lambda: SERVICE_ROOT / "reports")
    log_dir: Path = field(default_factory=lambda: SERVICE_ROOT / "logs")
    stats_file: Path = field(default_factory=lambda: SERVICE_ROOT / "reports" / "latest_stats.json")

    def ensure_directories(self) -> None:
        self.backup_dir.mkdir(parents=True, exist_ok=True)
        self.report_dir.mkdir(parents=True, exist_ok=True)
        self.log_dir.mkdir(parents=True, exist_ok=True)


@lru_cache(maxsize=1)
def get_settings() -> Settings:
    settings = Settings()
    settings.ensure_directories()
    return settings
