from __future__ import annotations

import __main__
import importlib.util
from pathlib import Path

import joblib
import numpy as np
import pandas as pd


def load_retrieval_utils(repo_root: Path):
    module_path = repo_root / "semantic_similarity" / "retrieval_utils.py"
    if not module_path.exists():
        raise FileNotFoundError(f"Cannot find retrieval utils: {module_path}")

    spec = importlib.util.spec_from_file_location("retrieval_utils", module_path)
    if spec is None or spec.loader is None:
        raise ImportError(f"Failed to create module spec for {module_path}")

    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def cosine_similarity(a: np.ndarray, b: np.ndarray) -> float:
    a = a / np.linalg.norm(a, axis=1, keepdims=True)
    b = b / np.linalg.norm(b, axis=1, keepdims=True)
    return float((a @ b.T)[0, 0])


def main() -> None:
    repo_root = Path.cwd()
    dataset_path = repo_root / "dataset_final.xlsx"
    model_path = repo_root / "semantic_similarity" / "semantic_retrieval_model.pkl"

    if not dataset_path.exists():
        raise FileNotFoundError(f"Dataset not found: {dataset_path}")
    if not model_path.exists():
        raise FileNotFoundError(f"Model not found: {model_path}")

    retrieval_utils = load_retrieval_utils(repo_root)

    # Make symbols available for pickles saved from __main__ context.
    __main__.SemanticRetrievalModel = retrieval_utils.SemanticRetrievalModel
    __main__.get_ensemble_embedding = retrieval_utils.get_ensemble_embedding

    print("Loading retrieval model...")
    retrieval_model = joblib.load(model_path)
    print("Model loaded")

    if not callable(getattr(retrieval_model, "encoder_func", None)):
        raise ValueError("Loaded model has no callable encoder_func")

    if not hasattr(retrieval_model, "weights"):
        retrieval_model.weights = None

    model_weights = getattr(retrieval_model, "weights", None)

    def encode(text: str) -> np.ndarray:
        if model_weights is None:
            try:
                return retrieval_model.encoder_func(text)
            except TypeError:
                return retrieval_model.encoder_func(text, weights=None)
        return retrieval_model.encoder_func(text, weights=model_weights)

    df = pd.read_excel(dataset_path)

    required_cols = ["prompt_asli", "prompt_parafrase"]
    missing = [c for c in required_cols if c not in df.columns]
    if missing:
        raise ValueError(f"Missing required columns: {missing}")

    sims: list[float] = []
    total = len(df)

    print(f"Scoring {total} rows...")
    for idx, row in df.iterrows():
        p1 = str(row["prompt_asli"])
        p2 = str(row["prompt_parafrase"])

        sim = None

        # Fast path: use model search score for the exact original prompt match.
        try:
            top_k = min(100, len(getattr(retrieval_model, "df", []))) or 5
            res = retrieval_model.search(p2, top_k=top_k)
            matched = res[res["prompt"].astype(str) == p1]
            if not matched.empty:
                sim = float(matched.iloc[0]["score"])
            else:
                sim = float(res.iloc[0]["score"])
        except Exception:
            pass

        # Fallback: direct cosine on pair embeddings.
        if sim is None:
            emb1 = encode(p1)
            emb2 = encode(p2)
            sim = cosine_similarity(emb1, emb2)

        sims.append(round(sim, 6))

        if (idx + 1) % 25 == 0 or (idx + 1) == total:
            print(f"Processed {idx + 1}/{total}")

    df["similarity"] = sims
    df.to_excel(dataset_path, index=False, engine="openpyxl")

    print("Done. Updated dataset_final.xlsx with 'similarity' column.")
    print(f"Similarity stats: min={min(sims):.6f}, max={max(sims):.6f}, avg={float(np.mean(sims)):.6f}")


if __name__ == "__main__":
    main()
