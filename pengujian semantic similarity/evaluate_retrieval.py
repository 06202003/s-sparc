from __future__ import annotations

import argparse
from pathlib import Path
from typing import Dict, List

import numpy as np
import pandas as pd

from eval_utils import (
    avg_precision_at_k,
    build_normalized_embeddings,
    grouped_qrels,
    load_dataset,
    load_qrels,
    ndcg_at_k,
    rank_for_query,
    safe_div,
)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Evaluate retrieval quality metrics")
    parser.add_argument(
        "--dataset",
        default="semantic_similarity/mbpp_all_with_embedding_and_relevance_v2.json",
        help="Path to dataset JSON",
    )
    parser.add_argument(
        "--qrels",
        default="pengujian semantic similarity/data/qrels_weak.csv",
        help="Path to qrels CSV",
    )
    parser.add_argument(
        "--ks",
        default="1,3,5,10",
        help="Comma-separated k values (e.g., 1,3,5,10)",
    )
    parser.add_argument(
        "--output",
        default="pengujian semantic similarity/outputs/retrieval_summary.csv",
        help="Summary CSV output path",
    )
    parser.add_argument(
        "--detail-output",
        default="pengujian semantic similarity/outputs/retrieval_per_query.csv",
        help="Per-query CSV output path",
    )
    return parser.parse_args()


def first_relevant_rank(binary_hits: List[int], k: int) -> int | None:
    for i, hit in enumerate(binary_hits[:k], start=1):
        if hit == 1:
            return i
    return None


def main() -> None:
    args = parse_args()
    ks = [int(x.strip()) for x in args.ks.split(",") if x.strip()]
    ks = sorted(set(k for k in ks if k > 0))
    if not ks:
        raise ValueError("At least one valid k is required")

    df = load_dataset(args.dataset)
    embeddings = build_normalized_embeddings(df)
    qrels_df = load_qrels(args.qrels)
    qrels_map = grouped_qrels(qrels_df)

    valid_qids = [qid for qid in qrels_map.keys() if 0 <= qid < len(df)]
    if not valid_qids:
        raise ValueError("No valid query_id found in qrels")

    summary_rows = []
    per_query_rows = []

    for k in ks:
        hit_scores = []
        precision_scores = []
        recall_scores = []
        mrr_scores = []
        map_scores = []
        ndcg_scores = []

        for qid in valid_qids:
            gt = qrels_map[qid]
            ranked_ids, _ = rank_for_query(embeddings, qid)
            top_ids = ranked_ids[:k].tolist()

            pred_rels = [float(gt.get(doc_id, 0.0)) for doc_id in top_ids]
            binary_hits = [1 if rel > 0 else 0 for rel in pred_rels]

            num_rel_total = int(sum(1 for rel in gt.values() if rel > 0))
            num_rel_retrieved = int(sum(binary_hits))

            hit = 1.0 if num_rel_retrieved > 0 else 0.0
            precision = safe_div(num_rel_retrieved, k)
            recall = safe_div(num_rel_retrieved, num_rel_total)

            rr_rank = first_relevant_rank(binary_hits, k)
            mrr = 1.0 / rr_rank if rr_rank is not None else 0.0

            ap = avg_precision_at_k(binary_hits, num_rel_total, k)
            ideal_rels = sorted([float(x) for x in gt.values()], reverse=True)
            ndcg = ndcg_at_k(pred_rels, ideal_rels, k)

            hit_scores.append(hit)
            precision_scores.append(precision)
            recall_scores.append(recall)
            mrr_scores.append(mrr)
            map_scores.append(ap)
            ndcg_scores.append(ndcg)

            if k == max(ks):
                per_query_rows.append(
                    {
                        "query_id": qid,
                        "query_text": df.iloc[qid]["prompt"],
                        f"hit@{k}": hit,
                        f"precision@{k}": precision,
                        f"recall@{k}": recall,
                        f"mrr@{k}": mrr,
                        f"ap@{k}": ap,
                        f"ndcg@{k}": ndcg,
                    }
                )

        summary_rows.append(
            {
                "k": k,
                "queries_evaluated": len(valid_qids),
                "hit": float(np.mean(hit_scores)),
                "precision": float(np.mean(precision_scores)),
                "recall": float(np.mean(recall_scores)),
                "mrr": float(np.mean(mrr_scores)),
                "map": float(np.mean(map_scores)),
                "ndcg": float(np.mean(ndcg_scores)),
            }
        )

    summary_df = pd.DataFrame(summary_rows)
    per_query_df = pd.DataFrame(per_query_rows)

    out_summary = Path(args.output)
    out_summary.parent.mkdir(parents=True, exist_ok=True)
    summary_df.to_csv(out_summary, index=False, encoding="utf-8")

    out_detail = Path(args.detail_output)
    out_detail.parent.mkdir(parents=True, exist_ok=True)
    per_query_df.to_csv(out_detail, index=False, encoding="utf-8")

    print("\n=== Retrieval Evaluation Summary ===")
    print(summary_df.to_string(index=False))
    print(f"\n[OK] Summary saved to: {out_summary}")
    print(f"[OK] Per-query metrics saved to: {out_detail}")


if __name__ == "__main__":
    main()
