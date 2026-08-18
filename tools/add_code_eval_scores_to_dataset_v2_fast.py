from __future__ import annotations

from pathlib import Path
import sys

import numpy as np
import pandas as pd
from sentence_transformers import SentenceTransformer

REPO_ROOT = Path(__file__).resolve().parents[1]
if str(REPO_ROOT) not in sys.path:
    sys.path.insert(0, str(REPO_ROOT))

from code_evaluator_service.evaluator.config import get_settings
from code_evaluator_service.evaluator.llm_judge import LLMJudge
from code_evaluator_service.evaluator.static_analysis import analyze_code


def compute_final_score(alignment: float, logic: float, similarity: float, static_score: float, quality: float) -> float:
    score = (0.40 * alignment) + (0.25 * logic) + (0.20 * (similarity * 10.0)) + (0.10 * quality) + (0.05 * static_score)
    return round(max(0.0, min(score, 10.0)), 2)


def batch_similarity(model: SentenceTransformer, prompts: list[str], codes: list[str]) -> np.ndarray:
    query_inputs = [f"query: {p.strip()}" for p in prompts]
    code_inputs = [f"passage: {c.strip()}" for c in codes]

    q_emb = model.encode(query_inputs, convert_to_numpy=True, normalize_embeddings=True, batch_size=64, show_progress_bar=False)
    c_emb = model.encode(code_inputs, convert_to_numpy=True, normalize_embeddings=True, batch_size=64, show_progress_bar=False)
    sim = np.sum(q_emb * c_emb, axis=1)
    sim = np.clip(sim, 0.0, 1.0)
    return sim


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
    settings.llm_api_key = None  # deterministic heuristic evaluator
    settings.embedding_model_name = str(REPO_ROOT / "pretrained_model" / "multilingual-e5-base")

    prompts_original = df["original_prompt"].astype(str).tolist()
    codes_original = df["original_code"].astype(str).tolist()
    prompts_generated = df["paraphrased_prompt"].astype(str).tolist()
    codes_generated = df["generated_code"].astype(str).tolist()

    print("Loading evaluator embedding model...")
    model = SentenceTransformer(settings.embedding_model_name)

    print("Computing semantic similarities in batch...")
    sim_original = batch_similarity(model, prompts_original, codes_original)
    sim_generated = batch_similarity(model, prompts_generated, codes_generated)

    llm_judge = LLMJudge(settings)

    score_original: list[float] = []
    score_generated: list[float] = []

    total = len(df)
    print(f"Scoring {total} rows with evaluator logic...")
    for i in range(total):
        so = analyze_code(prompts_original[i], codes_original[i])
        jo = llm_judge.judge(prompts_original[i], codes_original[i], so.detected_language, float(sim_original[i]), so)
        score_o = compute_final_score(jo.alignment, jo.logic, float(sim_original[i]), so.static_score, jo.quality)

        sg = analyze_code(prompts_generated[i], codes_generated[i])
        jg = llm_judge.judge(prompts_generated[i], codes_generated[i], sg.detected_language, float(sim_generated[i]), sg)
        score_g = compute_final_score(jg.alignment, jg.logic, float(sim_generated[i]), sg.static_score, jg.quality)

        score_original.append(score_o)
        score_generated.append(score_g)

        if (i + 1) % 50 == 0 or (i + 1) == total:
            print(f"Processed {i + 1}/{total}")

    df["eval_score_original_code"] = score_original
    df["eval_score_generated_code"] = score_generated

    df.to_excel(excel_path, index=False, engine="openpyxl")

    print("Done")
    print(f"Added columns: eval_score_original_code, eval_score_generated_code")
    print(f"Original avg: {float(np.mean(score_original)):.2f}")
    print(f"Generated avg: {float(np.mean(score_generated)):.2f}")


if __name__ == "__main__":
    main()
