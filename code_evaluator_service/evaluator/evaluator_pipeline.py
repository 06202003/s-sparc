from __future__ import annotations

import json
import logging
import threading
from dataclasses import asdict, dataclass
from datetime import datetime, timezone
from hashlib import sha256
from pathlib import Path
from statistics import mean
from typing import Any

from .anomaly_detection import AnomalyDetector
from .config import Settings
from .database import DatabaseClient
from .embedding_model import EmbeddingModel
from .llm_judge import LLMJudge
from .static_analysis import analyze_code


@dataclass(slots=True)
class EvaluatedEntry:
    id: str
    prompt: str
    generated_code: str
    created_at: str
    metadata: dict[str, Any]
    detected_language: str
    syntax_valid: bool
    line_count: int
    function_count: int
    loop_count: int
    cyclomatic_complexity: float
    maintainability_index: float
    static_score: float
    semantic_similarity: float
    llm_alignment: float
    llm_logic: float
    llm_quality: float
    llm_readability: float
    llm_completeness: float
    llm_summary: str
    llm_source: str
    final_score: float
    final_score_pre_anomaly: float
    anomaly_score: float = 0.0
    suspicious: bool = False
    duplicate_of: str | None = None
    deletion_reason: str | None = None

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


class EvaluatorPipeline:
    def __init__(
        self,
        settings: Settings,
        database: DatabaseClient | None = None,
        embedding_model: EmbeddingModel | None = None,
        llm_judge: LLMJudge | None = None,
        anomaly_detector: AnomalyDetector | None = None,
    ) -> None:
        self.settings = settings
        self.database = database or DatabaseClient(settings)
        self.embedding_model = embedding_model or EmbeddingModel(settings)
        self.llm_judge = llm_judge or LLMJudge(settings)
        self.anomaly_detector = anomaly_detector or AnomalyDetector()
        self.logger = logging.getLogger(__name__)
        self._run_lock = threading.Lock()

    def is_running(self) -> bool:
        return self._run_lock.locked()

    def run(self, trigger: str = "manual") -> dict[str, Any]:
        if not self._run_lock.acquire(blocking=False):
            raise RuntimeError("Evaluation run already in progress")

        started_at = datetime.now(timezone.utc)
        run_key = started_at.strftime("%Y%m%dT%H%M%SZ")
        self.logger.info("Evaluation started | trigger=%s", trigger)

        try:
            evaluations: list[EvaluatedEntry] = []
            batch_count = 0
            seen_hashes: dict[str, str] = {}

            for batch in self.database.iterate_entries(self.settings.batch_size):
                batch_count += 1
                self.logger.info("Processing batch %s with %s entries", batch_count, len(batch))
                for row in batch:
                    entry = self._evaluate_row(row, seen_hashes)
                    evaluations.append(entry)

            feature_rows = [
                {
                    "id": entry.id,
                    "line_count": entry.line_count,
                    "cyclomatic_complexity": entry.cyclomatic_complexity,
                    "semantic_similarity": entry.semantic_similarity,
                    "static_score": entry.static_score,
                    "final_score_pre_anomaly": entry.final_score_pre_anomaly,
                }
                for entry in evaluations
            ]
            anomaly_results = self.anomaly_detector.detect(feature_rows)

            to_delete: list[EvaluatedEntry] = []
            review_entries: list[EvaluatedEntry] = []
            valid_entries: list[EvaluatedEntry] = []
            deleted_reasons: dict[str, int] = {}
            review_breakdown: dict[str, int] = {}

            review_cutoff = min(self.settings.review_score_threshold, self.settings.final_score_threshold)

            for entry in evaluations:
                anomaly = anomaly_results.get(entry.id)
                if anomaly is not None:
                    entry.anomaly_score = float(anomaly.anomaly_score)
                    entry.suspicious = bool(anomaly.is_anomaly)

                if entry.duplicate_of:
                    entry.deletion_reason = "DUPLICATE"
                elif entry.semantic_similarity < self.settings.semantic_similarity_threshold:
                    entry.deletion_reason = "INVALID"
                elif entry.final_score < review_cutoff:
                    entry.deletion_reason = "LOW_QUALITY"
                elif entry.final_score < self.settings.final_score_threshold:
                    entry.deletion_reason = "REVIEW_REQUIRED"

                if entry.deletion_reason in {"DUPLICATE", "INVALID", "LOW_QUALITY"}:
                    deleted_reasons[entry.deletion_reason] = deleted_reasons.get(entry.deletion_reason, 0) + 1
                    to_delete.append(entry)
                elif entry.deletion_reason == "REVIEW_REQUIRED":
                    review_breakdown[entry.deletion_reason] = review_breakdown.get(entry.deletion_reason, 0) + 1
                    review_entries.append(entry)
                else:
                    valid_entries.append(entry)

            backup_path = self._write_backup(run_key, to_delete)
            deleted_entries = 0
            if to_delete and not self.settings.dry_run:
                deleted_entries = self.database.delete_entries([entry.id for entry in to_delete])

            report = self._build_report(
                run_key=run_key,
                trigger=trigger,
                started_at=started_at,
                finished_at=datetime.now(timezone.utc),
                evaluations=evaluations,
                valid_entries=valid_entries,
                review_entries=review_entries,
                deleted_entries=deleted_entries,
                backup_path=backup_path,
                deleted_breakdown=deleted_reasons,
                review_breakdown=review_breakdown,
            )
            report_path = self._write_report(run_key, report)
            report["report_path"] = str(report_path)
            self._write_latest_stats(report)
            self.logger.info("Evaluation completed | deleted=%s | report=%s", deleted_entries, report_path)
            return report
        finally:
            self._run_lock.release()

    def _evaluate_row(self, row: dict[str, Any], seen_hashes: dict[str, str]) -> EvaluatedEntry:
        prompt = str(row.get("prompt") or "")
        generated_code = str(row.get("generated_code") or "")
        static_result = analyze_code(prompt, generated_code)
        semantic_similarity = self.embedding_model.similarity(prompt, generated_code) if generated_code.strip() else 0.0
        judge_result = self.llm_judge.judge(
            prompt=prompt,
            code=generated_code,
            language=static_result.detected_language,
            semantic_similarity=semantic_similarity,
            static_result=static_result,
        )

        final_score = self._compute_final_score(
            alignment=judge_result.alignment,
            logic=judge_result.logic,
            similarity=semantic_similarity,
            static_score=static_result.static_score,
            quality=judge_result.quality,
        )

        content_hash = sha256(f"{prompt.strip()}\n---\n{generated_code.strip()}".encode("utf-8")).hexdigest()
        duplicate_of = seen_hashes.get(content_hash)
        if duplicate_of is None:
            seen_hashes[content_hash] = str(row["id"])

        return EvaluatedEntry(
            id=str(row["id"]),
            prompt=prompt,
            generated_code=generated_code,
            created_at=row.get("created_at").isoformat() if row.get("created_at") else "",
            metadata=row.get("metadata") or {},
            detected_language=static_result.detected_language,
            syntax_valid=static_result.syntax_valid,
            line_count=static_result.line_count,
            function_count=static_result.function_count,
            loop_count=static_result.loop_count,
            cyclomatic_complexity=static_result.cyclomatic_complexity,
            maintainability_index=static_result.maintainability_index,
            static_score=static_result.static_score,
            semantic_similarity=round(semantic_similarity, 4),
            llm_alignment=judge_result.alignment,
            llm_logic=judge_result.logic,
            llm_quality=judge_result.quality,
            llm_readability=judge_result.readability,
            llm_completeness=judge_result.completeness,
            llm_summary=judge_result.summary,
            llm_source=judge_result.source,
            final_score=final_score,
            final_score_pre_anomaly=final_score,
            duplicate_of=duplicate_of,
        )

    @staticmethod
    def _compute_final_score(
        alignment: float,
        logic: float,
        similarity: float,
        static_score: float,
        quality: float,
    ) -> float:
        score = (
            0.40 * alignment
            + 0.25 * logic
            + 0.20 * (similarity * 10.0)
            + 0.10 * quality
            + 0.05 * static_score
        )
        return round(max(0.0, min(score, 10.0)), 2)

    def _write_backup(self, run_key: str, entries: list[EvaluatedEntry]) -> str | None:
        if not entries:
            return None

        backup_path = self.settings.backup_dir / f"code_embeddings_backup_{run_key}.json"
        payload = {
            "created_at": datetime.now(timezone.utc).isoformat(),
            "entries": [
                {
                    "id": entry.id,
                    "prompt": entry.prompt,
                    "generated_code": entry.generated_code,
                    "evaluation_scores": entry.to_dict(),
                    "timestamp": datetime.now(timezone.utc).isoformat(),
                }
                for entry in entries
            ],
        }
        backup_path.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")
        self.logger.info("Backup created before deletion: %s", backup_path)
        return str(backup_path)

    def _build_report(
        self,
        run_key: str,
        trigger: str,
        started_at: datetime,
        finished_at: datetime,
        evaluations: list[EvaluatedEntry],
        valid_entries: list[EvaluatedEntry],
        review_entries: list[EvaluatedEntry],
        deleted_entries: int,
        backup_path: str | None,
        deleted_breakdown: dict[str, int],
        review_breakdown: dict[str, int],
    ) -> dict[str, Any]:
        similarities = [entry.semantic_similarity for entry in evaluations]
        final_scores = [entry.final_score for entry in evaluations]
        suspicious_count = sum(1 for entry in evaluations if entry.suspicious)

        return {
            "run_id": run_key,
            "service": self.settings.service_name,
            "trigger": trigger,
            "started_at": started_at.isoformat(),
            "finished_at": finished_at.isoformat(),
            "duration_seconds": round((finished_at - started_at).total_seconds(), 2),
            "total_entries": len(evaluations),
            "valid_entries": len(valid_entries),
            "review_entries": len(review_entries),
            "deleted_entries": deleted_entries if not self.settings.dry_run else sum(deleted_breakdown.values()),
            "average_similarity": round(mean(similarities), 4) if similarities else 0.0,
            "average_score": round(mean(final_scores), 4) if final_scores else 0.0,
            "suspicious_entries": suspicious_count,
            "backup_path": backup_path,
            "dry_run": self.settings.dry_run,
            "thresholds": {
                "semantic_similarity_threshold": self.settings.semantic_similarity_threshold,
                "review_score_threshold": self.settings.review_score_threshold,
                "final_score_threshold": self.settings.final_score_threshold,
            },
            "review_breakdown": review_breakdown,
            "deleted_breakdown": deleted_breakdown,
            "sample_review_ids": [entry.id for entry in review_entries][:20],
            "sample_deleted_ids": [entry.id for entry in evaluations if entry.deletion_reason in {"DUPLICATE", "INVALID", "LOW_QUALITY"}][:20],
        }

    def _write_report(self, run_key: str, report: dict[str, Any]) -> Path:
        report_path = self.settings.report_dir / f"evaluation_report_{run_key}.json"
        report_path.write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding="utf-8")
        return report_path

    def _write_latest_stats(self, report: dict[str, Any]) -> None:
        self.settings.stats_file.write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding="utf-8")

    def read_latest_stats(self) -> dict[str, Any]:
        if not self.settings.stats_file.exists():
            return {
                "service": self.settings.service_name,
                "message": "No evaluation report has been generated yet.",
            }
        return json.loads(self.settings.stats_file.read_text(encoding="utf-8"))
