from __future__ import annotations

from pathlib import Path

import numpy as np
import pandas as pd
from sentence_transformers import SentenceTransformer


def l2_normalize(x: np.ndarray) -> np.ndarray:
    return x / np.linalg.norm(x, axis=1, keepdims=True)


def main() -> None:
    repo_root = Path.cwd()
    excel_path = repo_root / "dataset_final.xlsx"

    if not excel_path.exists():
        raise FileNotFoundError(f"File not found: {excel_path}")

    df = pd.read_excel(excel_path)
    for col in ["prompt_asli", "prompt_parafrase"]:
        if col not in df.columns:
            raise ValueError(f"Missing required column: {col}")

    prompts_asli = df["prompt_asli"].astype(str).tolist()
    prompts_para = df["prompt_parafrase"].astype(str).tolist()

    # Use the same local ensemble model set used by your semantic retrieval system.
    model1_path = str(repo_root / "pretrained_model" / "paraphrase-multilingual-mpnet-base-v2")
    model2_path = str(repo_root / "pretrained_model" / "LaBSE")
    model3_path = str(repo_root / "pretrained_model" / "multilingual-e5-base")

    weights = (0.5, 0.5, 1.5)

    print("Loading local SentenceTransformer models...")
    model1 = SentenceTransformer(model1_path)
    model2 = SentenceTransformer(model2_path)
    model3 = SentenceTransformer(model3_path)
    print("Models loaded")

    print("Encoding original prompts...")
    o1 = model1.encode(prompts_asli, convert_to_numpy=True, batch_size=32, show_progress_bar=False)
    o2 = model2.encode(prompts_asli, convert_to_numpy=True, batch_size=32, show_progress_bar=False)
    o3 = model3.encode(prompts_asli, convert_to_numpy=True, batch_size=32, show_progress_bar=False)

    print("Encoding paraphrased prompts...")
    p1 = model1.encode(prompts_para, convert_to_numpy=True, batch_size=32, show_progress_bar=False)
    p2 = model2.encode(prompts_para, convert_to_numpy=True, batch_size=32, show_progress_bar=False)
    p3 = model3.encode(prompts_para, convert_to_numpy=True, batch_size=32, show_progress_bar=False)

    # Mirror existing retrieval pipeline: per-model normalize -> weight -> concat -> normalize.
    o1 = l2_normalize(o1) * weights[0]
    o2 = l2_normalize(o2) * weights[1]
    o3 = l2_normalize(o3) * weights[2]

    p1 = l2_normalize(p1) * weights[0]
    p2 = l2_normalize(p2) * weights[1]
    p3 = l2_normalize(p3) * weights[2]

    o = np.concatenate([o1, o2, o3], axis=1)
    p = np.concatenate([p1, p2, p3], axis=1)

    o = l2_normalize(o)
    p = l2_normalize(p)

    similarities = np.sum(o * p, axis=1)
    df["similarity"] = np.round(similarities.astype(float), 6)

    df.to_excel(excel_path, index=False, engine="openpyxl")

    print("Updated dataset_final.xlsx")
    print(f"Rows: {len(df)}")
    print(f"Similarity range: {df['similarity'].min():.6f} .. {df['similarity'].max():.6f}")


if __name__ == "__main__":
    main()
