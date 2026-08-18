from __future__ import annotations

import json
import logging
from dataclasses import asdict, dataclass

from openai import OpenAI

from .config import Settings
from .static_analysis import StaticAnalysisResult


@dataclass(slots=True)
class JudgeResult:
    alignment: float
    logic: float
    quality: float
    readability: float
    completeness: float
    summary: str
    source: str

    def to_dict(self) -> dict:
        return asdict(self)


class LLMJudge:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.logger = logging.getLogger(__name__)
        self.client = OpenAI(api_key=settings.llm_api_key, timeout=30.0, max_retries=2) if settings.llm_api_key else None

    def judge(
        self,
        prompt: str,
        code: str,
        language: str,
        semantic_similarity: float,
        static_result: StaticAnalysisResult,
    ) -> JudgeResult:
        if self.client is None:
            return self._heuristic_judge(prompt, code, language, semantic_similarity, static_result)

        truncated_code = code[:6000]
        system_prompt = (
            "You are evaluating code snippets for a programming chatbot retrieval knowledge base. "
            "Many entries are intentionally partial snippets rather than full standalone programs. "
            "Reward semantic relevance, local correctness, syntax sanity, and usefulness as a retrievable snippet. "
            "Do not heavily penalize brevity, missing boilerplate, or incomplete surrounding context if the snippet is still useful. "
            "Return JSON only with numeric fields alignment, logic, quality, readability, completeness in range 0-10, "
            "and a short summary string. Penalize semantic mismatch, obviously broken syntax, nonsense code, or misleading snippets."
        )
        user_prompt = (
            f"Prompt:\n{prompt}\n\n"
            f"Detected language: {language}\n"
            f"Static score: {static_result.static_score}\n"
            f"Semantic similarity: {semantic_similarity:.4f}\n\n"
            f"Code:\n{truncated_code}"
        )

        try:
            response = self.client.chat.completions.create(
                model=self.settings.llm_model,
                temperature=0.0,
                response_format={"type": "json_object"},
                messages=[
                    {"role": "system", "content": system_prompt},
                    {"role": "user", "content": user_prompt},
                ],
            )
            content = response.choices[0].message.content or "{}"
            payload = json.loads(content)
            return JudgeResult(
                alignment=float(payload.get("alignment", 0.0)),
                logic=float(payload.get("logic", 0.0)),
                quality=float(payload.get("quality", 0.0)),
                readability=float(payload.get("readability", 0.0)),
                completeness=float(payload.get("completeness", 0.0)),
                summary=str(payload.get("summary", "LLM evaluation completed.")),
                source=f"llm:{self.settings.llm_model}",
            )
        except Exception as exc:
            self.logger.warning("LLM judge failed, falling back to heuristic scoring: %s", exc)
            return self._heuristic_judge(prompt, code, language, semantic_similarity, static_result)

    @staticmethod
    def _clamp(score: float) -> float:
        return round(max(0.0, min(score, 10.0)), 2)

    def _heuristic_judge(
        self,
        prompt: str,
        code: str,
        language: str,
        semantic_similarity: float,
        static_result: StaticAnalysisResult,
    ) -> JudgeResult:
        alignment = self._clamp(semantic_similarity * 10.0)
        snippet_bonus = 1.0 if 1 <= static_result.line_count <= 12 else 0.4 if static_result.line_count > 0 else 0.0
        logic = self._clamp((3.0 if static_result.syntax_valid else 0.5) + static_result.static_score * 0.55 + snippet_bonus)
        quality = self._clamp(alignment * 0.35 + static_result.static_score * 0.55 + (1.0 if static_result.syntax_valid else 0.0))

        avg_line_length = sum(len(line) for line in code.splitlines()) / max(len(code.splitlines()), 1)
        readability_base = 7.5 if avg_line_length < 100 else 6.5 if avg_line_length < 140 else 5.0
        readability = self._clamp(
            readability_base + (static_result.maintainability_index / 100.0) * 1.5 + (0.5 if static_result.line_count <= 20 else 0.0)
        )

        completeness = self._clamp(
            (5.0 if code.strip() else 0.0)
            + (1.0 if static_result.syntax_valid else 0.0)
            + (1.0 if static_result.function_count > 0 or static_result.line_count <= 12 else 0.0)
            + alignment * 0.2
        )

        if not code.strip():
            summary = "Code is empty and should be removed from the retrieval knowledge base."
        elif not static_result.syntax_valid:
            summary = "Code has structural issues and is likely not safe to keep in the retrieval knowledge base."
        elif semantic_similarity < 0.80:
            summary = "Code appears weakly aligned to the prompt and should be reviewed or deleted."
        else:
            summary = "Heuristic evaluation indicates the snippet is useful for retrieval, even if it is not a full standalone program."

        return JudgeResult(
            alignment=alignment,
            logic=logic,
            quality=quality,
            readability=readability,
            completeness=completeness,
            summary=summary,
            source="heuristic",
        )
