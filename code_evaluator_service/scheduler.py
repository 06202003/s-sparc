from __future__ import annotations

import logging

from apscheduler.schedulers.background import BackgroundScheduler
from apscheduler.triggers.cron import CronTrigger

from .evaluator.config import Settings
from .evaluator.evaluator_pipeline import EvaluatorPipeline


class EvaluatorScheduler:
    def __init__(self, pipeline: EvaluatorPipeline, settings: Settings) -> None:
        self.pipeline = pipeline
        self.settings = settings
        self.logger = logging.getLogger(__name__)
        self.scheduler = BackgroundScheduler(timezone=settings.timezone)

    def start(self) -> None:
        if self.scheduler.running:
            return

        self.scheduler.add_job(
            self._scheduled_run,
            trigger=CronTrigger(day_of_week="sun", hour=3, minute=0),
            id="weekly_code_evaluation",
            replace_existing=True,
            coalesce=True,
            max_instances=1,
        )
        self.scheduler.start()
        self.logger.info("Scheduler started: Sunday 03:00 %s", self.settings.timezone)

    def shutdown(self) -> None:
        if self.scheduler.running:
            self.scheduler.shutdown(wait=False)
            self.logger.info("Scheduler stopped")

    def _scheduled_run(self) -> None:
        try:
            self.logger.info("Scheduled evaluation triggered")
            self.pipeline.run(trigger="scheduler")
        except Exception as exc:
            self.logger.exception("Scheduled evaluation failed: %s", exc)
