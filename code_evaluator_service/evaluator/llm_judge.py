from __future__ import annotations

import json
import logging
import os
import re
import time
from dataclasses import asdict, dataclass
from typing import Any

import litellm

from .config import Settings
from .static_analysis import StaticAnalysisResult

# Suppress noisy LiteLLM logs
litellm.suppress_debug_info = True
logging.getLogger("LiteLLM").setLevel(logging.ERROR)
logging.getLogger("litellm").setLevel(logging.ERROR)


@dataclass(slots=True)
class JudgeResult:
    alignment: float
    logic: float
    quality: float
    readability: float
    completeness: float
    summary: str
    source: str

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


class LLMJudge:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.logger = logging.getLogger(__name__)
        self.keys = self._load_gemini_keys()
        self.current_idx = 0
        self.disabled_keys: set[str] = set()
        self.rate_limited_until: dict[str, float] = {}

    def _load_gemini_keys(self) -> list[str]:
        keys = []
        for name in ("GEMINI_API_KEY_1", "GEMINI_API_KEY_2", "GEMINI_API_KEY_3", "GEMINI_API_KEY_4", "GEMINI_API_KEY_5", "GEMINI_API_KEY_6"):
            val = os.getenv(name)
            if val and len(val.strip()) >= 20 and val.strip() not in keys:
                keys.append(val.strip())
        
        for fallback in ("GEMINI_API_KEY", "GOOGLE_API_KEY", "EVALUATOR_GEMINI_API_KEY"):
            val = os.getenv(fallback)
            if val and len(val.strip()) >= 20 and val.strip() not in keys:
                keys.append(val.strip())

        self.logger.info("LLMJudge initialized with %d Google Gemini API key(s)", len(keys))
        return keys

    def _clean_json_text(self, text: str) -> str:
        text = text.strip()
        if text.startswith("```"):
            lines = text.splitlines()
            if lines[0].startswith("```"):
                lines = lines[1:]
            if lines and lines[-1].startswith("```"):
                lines = lines[:-1]
            text = "\n".join(lines).strip()
        match = re.search(r"\{.*\}", text, re.DOTALL)
        if match:
            return match.group(0)
        return text

    def judge(
        self,
        prompt: str,
        code: str,
        language: str,
        semantic_similarity: float,
        static_result: StaticAnalysisResult,
    ) -> JudgeResult:
        # Fast-Path for clear high-confidence or obviously empty code
        if not code.strip() or not static_result.syntax_valid or semantic_similarity < 0.40:
            return self._heuristic_judge(prompt, code, language, semantic_similarity, static_result)

        now = time.time()
        active_keys = [
            k for k in self.keys
            if k not in self.disabled_keys and self.rate_limited_until.get(k, 0) < now
        ]

        if not active_keys:
            return self._heuristic_judge(prompt, code, language, semantic_similarity, static_result)

        truncated_code = code[:4000]
        system_prompt = (
            "You are evaluating code snippets for a programming chatbot retrieval knowledge base. "
            "Reward semantic relevance, local correctness, syntax sanity, and usefulness as a retrievable snippet. "
            "Return JSON only with numeric fields alignment, logic, quality, readability, completeness in range 0-10, "
            "and a short summary string."
        )
        user_prompt = (
            f"Prompt:\n{prompt}\n\n"
            f"Detected language: {language}\n"
            f"Static score: {static_result.static_score}\n"
            f"Semantic similarity: {semantic_similarity:.4f}\n\n"
            f"Code:\n{truncated_code}"
        )

        model_name = self.settings.llm_model.strip()
        if not model_name.startswith("gemini/") and not model_name.startswith("google/"):
            litellm_model = f"gemini/{model_name}"
        else:
            litellm_model = model_name

        for _ in range(len(active_keys)):
            key = active_keys[self.current_idx % len(active_keys)]
            self.current_idx += 1

            try:
                res = litellm.completion(
                    model=litellm_model,
                    api_key=key,
                    messages=[
                        {"role": "system", "content": system_prompt},
                        {"role": "user", "content": user_prompt},
                    ],
                    timeout=15.0,
                )
                raw_text = res.choices[0].message.content or "{}"
                cleaned = self._clean_json_text(raw_text)
                payload = json.loads(cleaned)
                return JudgeResult(
                    alignment=float(payload.get("alignment", 0.0)),
                    logic=float(payload.get("logic", 0.0)),
                    quality=float(payload.get("quality", 0.0)),
                    readability=float(payload.get("readability", 0.0)),
                    completeness=float(payload.get("completeness", 0.0)),
                    summary=str(payload.get("summary", "Gemini evaluation completed.")),
                    source=f"gemini:{model_name}",
                )
            except Exception as exc:
                err_str = str(exc)
                if "PERMISSION_DENIED" in err_str or "403" in err_str or "invalid_api_key" in err_str:
                    self.logger.warning("Gemini key disabled due to 403 Permission Denied (%s...)", key[:8])
                    self.disabled_keys.add(key)
                elif "429" in err_str or "RESOURCE_EXHAUSTED" in err_str or "Quota exceeded" in err_str:
                    self.logger.info("Gemini key hit 429 rate limit (%s...), pausing key for 60s", key[:8])
                    self.rate_limited_until[key] = time.time() + 60.0
                else:
                    self.logger.warning("Gemini API call warning (%s...): %s", key[:8], err_str[:100])

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
