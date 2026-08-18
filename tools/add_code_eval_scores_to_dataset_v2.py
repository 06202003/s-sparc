from __future__ import annotations

from pathlib import Path
import sys

import pandas as pd


REPO_ROOT = Path(__file__).resolve().parents[1]
if str(REPO_ROOT) not in sys.path:
    sys.path.insert(0, str(REPO_ROOT))

from code_evaluator_service.evaluator.config import get_settings
from code_evaluator_service.evaluator.embedding_model import EmbeddingModel
from code_evaluator_service.evaluator.llm_judge import LLMJudge
from code_evaluator_service.evaluator.static_analysis import analyze_code


def compute_final_score(alignment: float, logic: float, similarity: float, static_score: float, quality: float) -> float:
    score = (0.40 * alignment) + (0.25 * logic) + (0.20 * (similarity * 10.0)) + (0.10 * quality) + (0.05 * static_score)
    return round(max(0.0, min(score, 10.0)), 2)


def evaluate_code(prompt: str, code: str, embedding_model: EmbeddingModel, llm_judge: LLMJudge) -> float:
    static_result = analyze_code(prompt, code)
    semantic_similarity = embedding_model.similarity(prompt, code) if code.strip() else 0.0
    judge_result = llm_judge.judge(
        prompt=prompt,
        code=code,
        language=static_result.detected_language,
        semantic_similarity=semantic_similarity,
        static_result=static_result,
    )
    return compute_final_score(
        alignment=judge_result.alignment,
        logic=judge_result.logic,
        similarity=semantic_similarity,
        static_score=static_result.static_score,
        quality=judge_result.quality,
    )


def main() -> None:
    excel_path = Path("dataset_final_v2.xlsx")
    if not excel_path.exists():
        raise FileNotFoundError(f"File not found: {excel_path}")

    df = pd.read_excel(excel_path)

    required = ["original_prompt", "original_code", "paraphrased_prompt", "generated_code"]
    missing = [c for c in required if c not in df.columns]
    if missing:
        raise ValueError(f"Missing required columns: {missing}")

    settings = get_settings()
    # Force deterministic heuristic judge to avoid external API dependency/cost during batch scoring.
    settings.llm_api_key = None

    embedding_model = EmbeddingModel(settings)
    llm_judge = LLMJudge(settings)

    original_scores: list[float] = []
    paraphrase_scores: list[float] = []

    total = len(df)
    print(f"Evaluating {total} rows...")

    for idx, row in df.iterrows():
        prompt_asli = str(row["original_prompt"])
        kode_asli = str(row["original_code"])
        prompt_para = str(row["paraphrased_prompt"])
        kode_para = str(row["generated_code"])

        score_asli = evaluate_code(prompt_asli, kode_asli, embedding_model, llm_judge)
        score_para = evaluate_code(prompt_para, kode_para, embedding_model, llm_judge)

        original_scores.append(score_asli)
        paraphrase_scores.append(score_para)

        if (idx + 1) % 20 == 0 or (idx + 1) == total:
            print(f"Processed {idx + 1}/{total}")

    df["eval_score_original_code"] = original_scores
    df["eval_score_generated_code"] = paraphrase_scores

    df.to_excel(excel_path, index=False, engine="openpyxl")

    print("Done.")
    print("Added columns: eval_score_original_code, eval_score_generated_code")
    print(
        f"Stats original: min={min(original_scores):.2f}, max={max(original_scores):.2f}, avg={sum(original_scores)/len(original_scores):.2f}"
    )
    print(
        f"Stats generated: min={min(paraphrase_scores):.2f}, max={max(paraphrase_scores):.2f}, avg={sum(paraphrase_scores)/len(paraphrase_scores):.2f}"
    )


if __name__ == "__main__":
    main()
