from __future__ import annotations

import argparse
from pathlib import Path

import numpy as np
import pandas as pd

from eval_utils import build_normalized_embeddings, load_dataset, rank_for_query


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Generate qrels (weak + manual template) for semantic similarity evaluation")
    parser.add_argument(
        "--dataset",
        default="semantic_similarity/mbpp_all_with_embedding_and_relevance_v2.json",
        help="Path to dataset JSON containing prompt/code/embedding",
    )
    parser.add_argument(
        "--weak-output",
        default="pengujian semantic similarity/data/qrels_weak.csv",
        help="Output path for weak qrels CSV",
    )
    parser.add_argument(
        "--manual-template-output",
        default="pengujian semantic similarity/data/qrels_manual_template.csv",
        help="Output path for manual labeling template CSV",
    )
    parser.add_argument(
        "--manual-queries",
        type=int,
        default=200,
        help="How many query prompts to sample for manual labeling template",
    )
    parser.add_argument(
        "--manual-candidates",
        type=int,
        default=20,
        help="How many top candidates to include per query in manual template",
    )
    parser.add_argument("--seed", type=int, default=42)
    return parser.parse_args()


def make_weak_qrels(df: pd.DataFrame) -> pd.DataFrame:
    if "relevant_indices" not in df.columns:
        raise ValueError("Column 'relevant_indices' not found. Use dataset with relevance mapping.")

    rows = []
    for qid, row in df.iterrows():
        rel_indices = row.get("relevant_indices")
        if not isinstance(rel_indices, list):
            continue
        for doc_id in rel_indices:
            rows.append(
                {
                    "query_id": int(qid),
                    "query_text": row["prompt"],
                    "doc_id": int(doc_id),
                    "doc_text": df.iloc[int(doc_id)]["prompt"],
                    "rel": 1,
                    "label_source": "weak_auto_from_relevant_indices",
                }
            )
    return pd.DataFrame(rows)


def make_manual_template(df: pd.DataFrame, embeddings: np.ndarray, num_queries: int, top_candidates: int, seed: int) -> pd.DataFrame:
    rng = np.random.default_rng(seed)
    query_ids = np.arange(len(df), dtype=np.int32)
    sample_size = min(num_queries, len(query_ids))
    sampled_queries = rng.choice(query_ids, size=sample_size, replace=False)

    rows = []
    for qid in sampled_queries:
        ranked_ids, ranked_scores = rank_for_query(embeddings, int(qid))
        top_ids = ranked_ids[:top_candidates]
        top_scores = ranked_scores[:top_candidates]

        for rank, (doc_id, score) in enumerate(zip(top_ids, top_scores), start=1):
            rows.append(
                {
                    "query_id": int(qid),
                    "query_text": df.iloc[int(qid)]["prompt"],
                    "doc_id": int(doc_id),
                    "doc_text": df.iloc[int(doc_id)]["prompt"],
                    "pred_score": float(score),
                    "rank_hint": rank,
                    "rel": "",  # fill manually: 0/1/2/3
                    "notes": "",
                }
            )

    out = pd.DataFrame(rows)
    out = out.sort_values(["query_id", "rank_hint"], ascending=[True, True]).reset_index(drop=True)
    return out


def main() -> None:
    args = parse_args()
    df = load_dataset(args.dataset)
    embeddings = build_normalized_embeddings(df)

    weak_qrels = make_weak_qrels(df)
    weak_path = Path(args.weak_output)
    weak_path.parent.mkdir(parents=True, exist_ok=True)
    weak_qrels.to_csv(weak_path, index=False, encoding="utf-8")

    manual_template = make_manual_template(
        df=df,
        embeddings=embeddings,
        num_queries=args.manual_queries,
        top_candidates=args.manual_candidates,
        seed=args.seed,
    )
    manual_path = Path(args.manual_template_output)
    manual_path.parent.mkdir(parents=True, exist_ok=True)
    manual_template.to_csv(manual_path, index=False, encoding="utf-8")

    print(f"[OK] Weak qrels saved: {weak_path} ({len(weak_qrels)} rows)")
    print(f"[OK] Manual labeling template saved: {manual_path} ({len(manual_template)} rows)")
    print("[INFO] Isi kolom 'rel' pada manual template dengan skala 0-3 (0 tidak relevan, 3 sangat relevan).")


if __name__ == "__main__":
    main()
