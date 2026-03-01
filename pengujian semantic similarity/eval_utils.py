from __future__ import annotations

import json
import math
from pathlib import Path
from typing import Dict, Iterable, List, Tuple

import numpy as np
import pandas as pd


def load_dataset(dataset_path: str | Path) -> pd.DataFrame:
    dataset_path = Path(dataset_path)
    with dataset_path.open("r", encoding="utf-8") as f:
        data = json.load(f)
    df = pd.DataFrame(data)
    required_cols = {"prompt", "code", "embedding"}
    missing = required_cols - set(df.columns)
    if missing:
        raise ValueError(f"Dataset missing required columns: {sorted(missing)}")
    return df


def build_normalized_embeddings(df: pd.DataFrame) -> np.ndarray:
    emb = np.array(df["embedding"].tolist(), dtype=np.float32)
    norms = np.linalg.norm(emb, axis=1, keepdims=True)
    norms = np.clip(norms, 1e-12, None)
    return emb / norms


def rank_for_query(embeddings: np.ndarray, query_idx: int) -> Tuple[np.ndarray, np.ndarray]:
    query_vec = embeddings[query_idx]
    scores = embeddings @ query_vec
    indices = np.arange(len(scores), dtype=np.int32)

    # Exclude self-match
    mask = indices != query_idx
    filt_scores = scores[mask]
    filt_idx = indices[mask]

    order = np.argsort(-filt_scores)
    return filt_idx[order], filt_scores[order]


def load_qrels(qrels_path: str | Path) -> pd.DataFrame:
    qrels_path = Path(qrels_path)
    if not qrels_path.exists():
        raise FileNotFoundError(f"qrels not found: {qrels_path}")
    qrels = pd.read_csv(qrels_path)
    required_cols = {"query_id", "doc_id", "rel"}
    missing = required_cols - set(qrels.columns)
    if missing:
        raise ValueError(f"Qrels missing required columns: {sorted(missing)}")
    qrels = qrels.dropna(subset=["query_id", "doc_id", "rel"]).copy()
    qrels["query_id"] = qrels["query_id"].astype(int)
    qrels["doc_id"] = qrels["doc_id"].astype(int)
    qrels["rel"] = qrels["rel"].astype(float)
    return qrels


def grouped_qrels(qrels: pd.DataFrame) -> Dict[int, Dict[int, float]]:
    out: Dict[int, Dict[int, float]] = {}
    for qid, chunk in qrels.groupby("query_id"):
        out[int(qid)] = {int(r.doc_id): float(r.rel) for r in chunk.itertuples(index=False)}
    return out


def dcg_at_k(rels: Iterable[float], k: int) -> float:
    vals = np.array(list(rels), dtype=np.float32)[:k]
    if len(vals) == 0:
        return 0.0
    discounts = 1.0 / np.log2(np.arange(2, len(vals) + 2))
    gains = (2.0 ** vals - 1.0) * discounts
    return float(gains.sum())


def ndcg_at_k(pred_rels: List[float], ideal_rels: List[float], k: int) -> float:
    ideal_dcg = dcg_at_k(ideal_rels, k)
    if ideal_dcg <= 0:
        return 0.0
    return dcg_at_k(pred_rels, k) / ideal_dcg


def avg_precision_at_k(binary_hits: List[int], num_relevant_total: int, k: int) -> float:
    if num_relevant_total <= 0:
        return 0.0
    hits = np.array(binary_hits[:k], dtype=np.int32)
    if hits.sum() == 0:
        return 0.0
    precisions = []
    cum_hit = 0
    for i, h in enumerate(hits, start=1):
        if h:
            cum_hit += 1
            precisions.append(cum_hit / i)
    return float(np.sum(precisions) / num_relevant_total)


def safe_div(a: float, b: float) -> float:
    if b == 0:
        return 0.0
    return a / b


def ensure_parent(path: str | Path) -> None:
    Path(path).parent.mkdir(parents=True, exist_ok=True)
