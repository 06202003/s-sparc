from __future__ import annotations

from pathlib import Path
import sys

import pandas as pd

REPO_ROOT = Path(__file__).resolve().parents[1]
if str(REPO_ROOT) not in sys.path:
    sys.path.insert(0, str(REPO_ROOT))

from code_evaluator_service.evaluator.static_analysis import analyze_code


def main() -> None:
    excel_path = Path("dataset_final_v2.xlsx")
    if not excel_path.exists():
        raise FileNotFoundError(f"File not found: {excel_path}")

    df = pd.read_excel(excel_path)

    required = ["original_prompt", "original_code", "paraphrased_prompt", "generated_code"]
    missing = [c for c in required if c not in df.columns]
    if missing:
        raise ValueError(f"Missing required columns: {missing}")

    original_scores: list[float] = []
    generated_scores: list[float] = []

    total = len(df)
    print(f"Evaluating static scores for {total} rows...")

    for i, row in df.iterrows():
        so = analyze_code(str(row["original_prompt"]), str(row["original_code"]))
        sg = analyze_code(str(row["paraphrased_prompt"]), str(row["generated_code"]))
        original_scores.append(float(so.static_score))
        generated_scores.append(float(sg.static_score))

        if (i + 1) % 50 == 0 or (i + 1) == total:
            print(f"Processed {i + 1}/{total}")

    df["eval_score_original_code"] = original_scores
    df["eval_score_generated_code"] = generated_scores

    df.to_excel(excel_path, index=False, engine="openpyxl")

    print("Done")
    print(f"Added columns: eval_score_original_code, eval_score_generated_code")


if __name__ == "__main__":
    main()
