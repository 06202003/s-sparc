from __future__ import annotations

import json
import os
import re
import time
from dataclasses import asdict, dataclass
from typing import Any

from openai import OpenAI

try:
    from dotenv import load_dotenv
except ImportError:  # pragma: no cover - optional dependency
    load_dotenv = None


if load_dotenv is not None:
    load_dotenv()


@dataclass(slots=True)
class EvaluationResult:
    row_id: str | int | None
    prompt_score: float
    response_score: float
    overall_score: float
    prompt_strengths: str
    prompt_issues: str
    response_strengths: str
    response_issues: str
    prompt_rewrite: str
    response_improvement_suggestions: str
    verdict: str
    needs_human_review: bool
    model: str
    raw_json: str

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


class PromptQualityEvaluator:
    """Evaluate prompt and response quality using OpenAI."""

    def __init__(
        self,
        model: str | None = None,
        api_key: str | None = None,
        max_retries: int = 3,
        retry_sleep_seconds: float = 2.0,
        request_timeout_seconds: float = 60.0,
    ) -> None:
        self.model = model or os.getenv("OPENAI_MODEL", "gpt-4o-mini")
        self.max_retries = max(1, int(max_retries))
        self.retry_sleep_seconds = max(0.0, float(retry_sleep_seconds))
        self.request_timeout_seconds = max(1.0, float(request_timeout_seconds))
        self.client = OpenAI(
            api_key=api_key or os.getenv("OPENAI_API_KEY"),
            timeout=self.request_timeout_seconds,
        )

    def evaluate(self, prompt: str, response: str, row_id: str | int | None = None) -> EvaluationResult:
        prompt_text = self._normalize_text(prompt)
        response_text = self._normalize_text(response)

        system_message = (
            "You are a strict but practical evaluator for AI prompt/response pairs. "
            "Score prompt quality and response quality separately using a 0-100 scale, where 0 is very poor and 100 is excellent. "
            "Be consistent, concise, and evidence-based. "
            "Return ONLY valid JSON with the required keys."
        )

        user_message = {
            "row_id": row_id,
            "prompt": prompt_text,
            "response": response_text,
            "rubric": {
                "prompt_quality": [
                    "clarity",
                    "specificity",
                    "context",
                    "constraints",
                    "actionability",
                ],
                "response_quality": [
                    "relevance",
                    "correctness",
                    "completeness",
                    "clarity",
                    "helpfulness",
                ],
            },
            "required_output_schema": {
                "prompt_score": "number between 0 and 100",
                "response_score": "number between 0 and 100",
                "overall_score": "number between 0 and 100",
                "prompt_strengths": "short string",
                "prompt_issues": "short string",
                "response_strengths": "short string",
                "response_issues": "short string",
                "prompt_rewrite": "short improved prompt",
                "response_improvement_suggestions": "short actionable suggestions",
                "verdict": "one of: excellent, good, fair, weak, poor",
                "needs_human_review": "boolean",
            },
        }

        payload = self._call_openai(system_message, user_message)
        return self._build_result(payload, row_id=row_id)

    def _call_openai(self, system_message: str, user_message: dict[str, Any]) -> dict[str, Any]:
        last_error: Exception | None = None
        for attempt in range(1, self.max_retries + 1):
            try:
                messages = [
                    {"role": "system", "content": system_message},
                    {"role": "user", "content": json.dumps(user_message, ensure_ascii=False)},
                ]
                completion = self.client.chat.completions.create(
                    model=self.model,
                    temperature=0,
                    messages=messages,
                )
                content = completion.choices[0].message.content or "{}"
                return self._parse_json(content)
            except Exception as exc:  # pragma: no cover - network/API failures
                last_error = exc
                if attempt < self.max_retries:
                    time.sleep(self.retry_sleep_seconds * attempt)
                else:
                    break
        raise RuntimeError(f"OpenAI evaluation failed after {self.max_retries} attempts: {last_error}") from last_error

    @staticmethod
    def _normalize_text(value: Any) -> str:
        text = "" if value is None else str(value)
        text = re.sub(r"\s+", " ", text).strip()
        return text

    @staticmethod
    def _parse_json(content: str) -> dict[str, Any]:
        try:
            parsed = json.loads(content)
            if isinstance(parsed, dict):
                return parsed
        except json.JSONDecodeError:
            pass

        match = re.search(r"\{[\s\S]*\}", content)
        if match:
            parsed = json.loads(match.group(0))
            if isinstance(parsed, dict):
                return parsed

        raise ValueError("Model output is not valid JSON")

    def _build_result(self, payload: dict[str, Any], row_id: str | int | None) -> EvaluationResult:
        prompt_score = self._coerce_score(payload.get("prompt_score"))
        response_score = self._coerce_score(payload.get("response_score"))
        overall_score = self._coerce_score(payload.get("overall_score"))

        if overall_score is None:
            overall_score = round((prompt_score + response_score) / 2.0, 2)

        verdict = str(payload.get("verdict") or self._verdict_from_score(overall_score)).strip().lower()
        needs_human_review = bool(payload.get("needs_human_review", overall_score < 60.0))

        return EvaluationResult(
            row_id=row_id,
            prompt_score=prompt_score,
            response_score=response_score,
            overall_score=overall_score,
            prompt_strengths=str(payload.get("prompt_strengths") or "").strip(),
            prompt_issues=str(payload.get("prompt_issues") or "").strip(),
            response_strengths=str(payload.get("response_strengths") or "").strip(),
            response_issues=str(payload.get("response_issues") or "").strip(),
            prompt_rewrite=str(payload.get("prompt_rewrite") or "").strip(),
            response_improvement_suggestions=str(payload.get("response_improvement_suggestions") or "").strip(),
            verdict=verdict,
            needs_human_review=needs_human_review,
            model=self.model,
            raw_json=json.dumps(payload, ensure_ascii=False),
        )

    @staticmethod
    def _coerce_score(value: Any) -> float:
        try:
            score = float(value)
        except (TypeError, ValueError):
            score = 0.0
        return round(max(0.0, min(100.0, score)), 2)

    @staticmethod
    def _verdict_from_score(score: float) -> str:
        if score >= 90:
            return "excellent"
        if score >= 76:
            return "good"
        if score >= 56:
            return "fair"
        if score >= 36:
            return "weak"
        return "poor"
