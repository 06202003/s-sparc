from __future__ import annotations

from dataclasses import asdict, dataclass

import numpy as np
from sklearn.ensemble import IsolationForest


@dataclass(slots=True)
class AnomalyResult:
    is_anomaly: bool
    anomaly_score: float

    def to_dict(self) -> dict:
        return asdict(self)


class AnomalyDetector:
    def detect(self, feature_rows: list[dict]) -> dict[str, AnomalyResult]:
        if len(feature_rows) < 10:
            return {
                row["id"]: AnomalyResult(is_anomaly=False, anomaly_score=0.0)
                for row in feature_rows
            }

        matrix = np.array(
            [
                [
                    row["line_count"],
                    row["cyclomatic_complexity"],
                    row["semantic_similarity"],
                    row["static_score"],
                    row["final_score_pre_anomaly"],
                ]
                for row in feature_rows
            ],
            dtype=float,
        )

        model = IsolationForest(contamination=0.1, random_state=42)
        labels = model.fit_predict(matrix)
        raw_scores = -model.score_samples(matrix)
        max_score = float(raw_scores.max()) if len(raw_scores) else 1.0

        results: dict[str, AnomalyResult] = {}
        for row, label, raw_score in zip(feature_rows, labels, raw_scores):
            normalized = float(raw_score / max_score) if max_score else 0.0
            results[row["id"]] = AnomalyResult(
                is_anomaly=bool(label == -1),
                anomaly_score=float(round(normalized, 4)),
            )
        return results

