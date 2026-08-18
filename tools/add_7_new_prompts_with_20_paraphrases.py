from __future__ import annotations

import json
import os
import sys
import time
from pathlib import Path

import pandas as pd

REPO_ROOT = Path(__file__).resolve().parents[1]
EVAL_UTILS_DIR = REPO_ROOT / "pengujian semantic similarity"
if str(EVAL_UTILS_DIR) not in sys.path:
    sys.path.insert(0, str(EVAL_UTILS_DIR))

from eval_utils import build_normalized_embeddings, load_dataset, rank_for_query  # noqa: E402


def generate_20_paraphrases(prompt: str) -> list[str]:
    text = " ".join(str(prompt).strip().split())
    lowers = text.lower()

    core = text
    prefixes = [
        "write a function to ",
        "write a python function to ",
        "create a function to ",
        "implement a function to ",
        "develop a function to ",
    ]
    for pf in prefixes:
        if lowers.startswith(pf):
            core = text[len(pf) :].strip()
            break

    starters = [
        "Create a function to",
        "Implement a function to",
        "Develop a function to",
        "Build a function to",
        "Design a function to",
        "Write a routine to",
        "Construct a function to",
        "Produce a function to",
        "Formulate a function to",
        "Define a function to",
        "Craft a function to",
        "Prepare a function to",
        "Generate a function to",
        "Compose a function to",
        "Provide a function to",
        "Code a function to",
        "Set up a function to",
        "Create a Python function to",
        "Implement a Python function to",
        "Develop a Python function to",
    ]

    out = [f"{s} {core}." for s in starters]
    # Uniqueness guard in case normalization causes duplicates.
    seen: dict[str, int] = {}
    uniq: list[str] = []
    for item in out:
        key = item.strip()
        count = seen.get(key, 0)
        if count == 0:
            uniq.append(key)
        else:
            uniq.append(f"{key} Variant {count + 1}.")
        seen[key] = count + 1

    if len(uniq) != 20:
        raise ValueError("Failed to produce 20 paraphrases")
    return uniq


def main() -> None:
    data_dir = REPO_ROOT / "pengujian semantic similarity" / "data"
    qrels_path = data_dir / "qrels_manual.csv"
    backup_path = data_dir / "qrels_manual.backup_before_add_7_new_prompts.csv"
    query_out = data_dir / "qrels_manual_queries_225.csv"

    qrels = pd.read_csv(qrels_path)
    qrels["query_id"] = qrels["query_id"].astype(int)

    dataset_path = REPO_ROOT / "semantic_similarity" / "mbpp_all_with_embedding_and_relevance_v2.json"
    df = load_dataset(dataset_path)
    emb = build_normalized_embeddings(df)

    existing_qids = set(qrels["query_id"].astype(int).tolist())
    existing_prompts = set(
        qrels.sort_values(["query_id", "rank_hint"]).groupby("query_id", as_index=False).first()["query_text"].astype(str).tolist()
    )

    candidate_qids: list[int] = []
    for qid, row in df.iterrows():
        qid_int = int(qid)
        prompt = str(row["prompt"])
        if qid_int in existing_qids:
            continue
        if prompt in existing_prompts:
            continue
        candidate_qids.append(qid_int)
        if len(candidate_qids) == 7:
            break

    if len(candidate_qids) < 7:
        raise ValueError(f"Only found {len(candidate_qids)} candidate qids, need 7")

    add_rows: list[dict] = []
    for idx, qid in enumerate(candidate_qids, start=1):
        query_text = str(df.iloc[qid]["prompt"])
        relevant_indices = df.iloc[qid].get("relevant_indices", [])
        relevant_set = set(int(x) for x in relevant_indices) if isinstance(relevant_indices, list) else set()
        paras = generate_20_paraphrases(query_text)

        ranked_ids, ranked_scores = rank_for_query(emb, qid)
        top_ids = ranked_ids[:20]
        top_scores = ranked_scores[:20]

        for rank, (doc_id, score) in enumerate(zip(top_ids, top_scores), start=1):
            d = int(doc_id)
            add_rows.append(
                {
                    "query_id": int(qid),
                    "query_text": query_text,
                    "doc_id": d,
                    "doc_text": str(df.iloc[d]["prompt"]),
                    "pred_score": float(score),
                    "rank_hint": int(rank),
                    "rel": int(1 if d in relevant_set else 0),
                    "notes": "",
                    "query_text_paraphrase": paras[rank - 1],
                }
            )
        print(f"Processed {idx}/7 -> query_id {qid}", flush=True)

    add_df = pd.DataFrame(add_rows)
    if len(add_df) != 140:
        raise ValueError(f"Expected 140 rows, got {len(add_df)}")

    merged = pd.concat([qrels, add_df], ignore_index=True)
    if merged.duplicated(subset=["query_id", "rank_hint"]).any():
        raise ValueError("Duplicate (query_id, rank_hint) detected")
    merged = merged.sort_values(["query_id", "rank_hint"], ascending=[True, True]).reset_index(drop=True)

    if not backup_path.exists():
        qrels.to_csv(backup_path, index=False, encoding="utf-8")

    merged.to_csv(qrels_path, index=False, encoding="utf-8")

    query_tbl = (
        merged.sort_values(["query_id", "rank_hint"]).groupby("query_id", as_index=False).first()[
            ["query_id", "query_text", "query_text_paraphrase"]
        ]
    )
    query_tbl.to_csv(query_out, index=False, encoding="utf-8")

    print(f"Added query_ids: {candidate_qids}", flush=True)
    print(f"Added rows: {len(add_df)}", flush=True)
    print(
        "Final stats: "
        f"rows={len(merged)}, "
        f"unique_query_id={merged['query_id'].nunique()}, "
        f"unique_query_text={query_tbl['query_text'].nunique()}, "
        f"rel_non_null={merged['rel'].notna().sum()}",
        flush=True,
    )


if __name__ == "__main__":
    main()
