from __future__ import annotations

import argparse
from pathlib import Path

import numpy as np
import pandas as pd

from eval_utils import build_normalized_embeddings, grouped_qrels, load_dataset, load_qrels, rank_for_query, safe_div


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Evaluate threshold decisions: retrieval vs fallback GPT")
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
        "--thresholds",
        default="0.80,0.90",
        help="Comma-separated thresholds to evaluate",
    )
    parser.add_argument(
        "--scan",
        action="store_true",
        help="Also scan thresholds from 0.50 to 0.99 (step 0.01)",
    )
    parser.add_argument(
        "--output",
        default="pengujian semantic similarity/outputs/threshold_summary.csv",
        help="Summary CSV output path",
    )
    return parser.parse_args()


def score_top1_for_query(embeddings: np.ndarray, query_idx: int) -> tuple[int, float]:
    ranked_ids, ranked_scores = rank_for_query(embeddings, query_idx)
    return int(ranked_ids[0]), float(ranked_scores[0])


def main() -> None:
    args = parse_args()

    base_thresholds = [float(x.strip()) for x in args.thresholds.split(",") if x.strip()]
    scan_thresholds = np.round(np.arange(0.50, 1.00, 0.01), 2).tolist() if args.scan else []
    thresholds = sorted(set(base_thresholds + scan_thresholds))

    df = load_dataset(args.dataset)
    embeddings = build_normalized_embeddings(df)
    qrels_df = load_qrels(args.qrels)
    qrels_map = grouped_qrels(qrels_df)

    valid_qids = [qid for qid in qrels_map.keys() if 0 <= qid < len(df)]
    if not valid_qids:
        raise ValueError("No valid query_id found in qrels")

    top1_data = []
    for qid in valid_qids:
        doc_id, score = score_top1_for_query(embeddings, qid)
        gt_rel = qrels_map[qid].get(doc_id, 0.0)
        gt_positive = 1 if gt_rel > 0 else 0
        top1_data.append((qid, doc_id, score, gt_positive))

    rows = []
    for thr in thresholds:
        tp = fp = tn = fn = 0

        for _, _, score, gt_positive in top1_data:
            pred_retrieval = 1 if score >= thr else 0
            if pred_retrieval == 1 and gt_positive == 1:
                tp += 1
            elif pred_retrieval == 1 and gt_positive == 0:
                fp += 1
            elif pred_retrieval == 0 and gt_positive == 0:
                tn += 1
            else:
                fn += 1

        precision = safe_div(tp, tp + fp)
        recall = safe_div(tp, tp + fn)
        f1 = safe_div(2 * precision * recall, precision + recall)
        accuracy = safe_div(tp + tn, tp + tn + fp + fn)
        retrieval_rate = safe_div(tp + fp, tp + tn + fp + fn)

        rows.append(
            {
                "threshold": thr,
                "queries_evaluated": len(top1_data),
                "tp": tp,
                "fp": fp,
                "tn": tn,
                "fn": fn,
                "precision": precision,
                "recall": recall,
                "f1": f1,
                "accuracy": accuracy,
                "retrieval_rate": retrieval_rate,
            }
        )

    out_df = pd.DataFrame(rows).sort_values("threshold").reset_index(drop=True)
    out_path = Path(args.output)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_df.to_csv(out_path, index=False, encoding="utf-8")

    best_row = out_df.loc[out_df["f1"].idxmax()]

    print("\n=== Threshold Evaluation Summary ===")
    print(out_df.to_string(index=False))
    print("\n=== Best Threshold by F1 ===")
    print(best_row.to_string())
    print(f"\n[OK] Threshold summary saved to: {out_path}")


if __name__ == "__main__":
    main()
