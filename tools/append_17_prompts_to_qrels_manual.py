from __future__ import annotations

import json
import os
import time
from pathlib import Path

import pandas as pd
from openai import OpenAI

import sys

REPO_ROOT = Path(__file__).resolve().parents[1]
EVAL_UTILS_DIR = REPO_ROOT / "pengujian semantic similarity"
if str(EVAL_UTILS_DIR) not in sys.path:
    sys.path.insert(0, str(EVAL_UTILS_DIR))

from eval_utils import build_normalized_embeddings, load_dataset, rank_for_query  # noqa: E402


def paraphrase_batch(client: OpenAI, prompts: list[str], model: str = "gpt-4.1-mini", max_retries: int = 5) -> list[str]:
    system = (
        "You paraphrase coding task prompts. Preserve exact meaning and constraints. "
        "Return ONLY a valid JSON array of strings in the same order and same length."
    )
    numbered = "\n".join([f"{i + 1}. {p}" for i, p in enumerate(prompts)])
    user = (
        "Paraphrase each prompt into clear natural English while keeping the exact same task.\n"
        "Do not add or remove constraints.\n\n"
        f"{numbered}"
    )

    last_exc: Exception | None = None
    for attempt in range(1, max_retries + 1):
        try:
            resp = client.responses.create(
                model=model,
                temperature=0.4,
                input=[
                    {"role": "system", "content": system},
                    {"role": "user", "content": user},
                ],
            )
            raw = (resp.output_text or "").strip()
            try:
                arr = json.loads(raw)
            except Exception:
                start = raw.find("[")
                end = raw.rfind("]")
                if start == -1 or end == -1 or end <= start:
                    raise
                arr = json.loads(raw[start : end + 1])
            if not isinstance(arr, list) or len(arr) != len(prompts):
                raise ValueError(f"Invalid response length: expected {len(prompts)}, got {len(arr) if isinstance(arr, list) else 'NA'}")
            out = [" ".join(str(x).strip().split()) for x in arr]
            if any(not x for x in out):
                raise ValueError("Empty paraphrase found")
            return out
        except Exception as exc:  # noqa: BLE001
            last_exc = exc
            if attempt < max_retries:
                time.sleep(attempt)
            else:
                raise RuntimeError("Failed generating paraphrases") from last_exc


def main() -> None:
    data_dir = REPO_ROOT / "pengujian semantic similarity" / "data"
    qrels_path = data_dir / "qrels_manual.csv"
    query_list_200_path = data_dir / "qrels_manual_queries_200.csv"
    query_list_217_path = data_dir / "qrels_manual_queries_217.csv"
    backup_path = data_dir / "qrels_manual.backup_before_add_17.csv"

    if not qrels_path.exists():
        raise FileNotFoundError(f"Not found: {qrels_path}")

    qrels = pd.read_csv(qrels_path)
    required_cols = [
        "query_id",
        "query_text",
        "doc_id",
        "doc_text",
        "pred_score",
        "rank_hint",
        "rel",
        "notes",
        "query_text_paraphrase",
    ]
    missing = [c for c in required_cols if c not in qrels.columns]
    if missing:
        raise ValueError(f"qrels_manual.csv missing columns: {missing}")

    dataset_path = REPO_ROOT / "semantic_similarity" / "mbpp_all_with_embedding_and_relevance_v2.json"
    df = load_dataset(dataset_path)
    emb = build_normalized_embeddings(df)

    qrels["query_id"] = qrels["query_id"].astype(int)
    existing_qids = set(qrels["query_id"].tolist())
    existing_prompts = set(qrels["query_text"].astype(str).tolist())

    candidates: list[int] = []
    for qid, row in df.iterrows():
        qid_int = int(qid)
        prompt = str(row["prompt"])
        if qid_int in existing_qids:
            continue
        if prompt in existing_prompts:
            continue
        candidates.append(qid_int)
        if len(candidates) == 17:
            break

    if len(candidates) < 17:
        raise ValueError(f"Only found {len(candidates)} candidate prompts, need 17")

    api_key = os.getenv("OPENAI_API_KEY")
    if not api_key:
        raise EnvironmentError("OPENAI_API_KEY is not set")

    client = OpenAI(api_key=api_key)
    selected_prompts = [str(df.iloc[qid]["prompt"]) for qid in candidates]
    new_paraphrases = paraphrase_batch(client, selected_prompts, model="gpt-4.1-mini")
    paraphrase_by_qid = {qid: para for qid, para in zip(candidates, new_paraphrases)}

    new_rows: list[dict] = []
    for qid in candidates:
        ranked_ids, ranked_scores = rank_for_query(emb, qid)
        top_ids = ranked_ids[:20]
        top_scores = ranked_scores[:20]
        query_text = str(df.iloc[qid]["prompt"])
        query_para = paraphrase_by_qid[qid]

        for rank, (doc_id, score) in enumerate(zip(top_ids, top_scores), start=1):
            new_rows.append(
                {
                    "query_id": int(qid),
                    "query_text": query_text,
                    "doc_id": int(doc_id),
                    "doc_text": str(df.iloc[int(doc_id)]["prompt"]),
                    "pred_score": float(score),
                    "rank_hint": int(rank),
                    "rel": "",
                    "notes": "",
                    "query_text_paraphrase": query_para,
                }
            )

    add_df = pd.DataFrame(new_rows)
    if len(add_df) != 340:
        raise ValueError(f"Expected 340 new rows, got {len(add_df)}")

    # Ensure no duplicate (query_id, rank_hint)
    merged = pd.concat([qrels, add_df], ignore_index=True)
    dup = merged.duplicated(subset=["query_id", "rank_hint"]).any()
    if dup:
        raise ValueError("Duplicate (query_id, rank_hint) detected after append")

    merged = merged.sort_values(["query_id", "rank_hint"], ascending=[True, True]).reset_index(drop=True)

    if not backup_path.exists():
        qrels.to_csv(backup_path, index=False, encoding="utf-8")

    merged.to_csv(qrels_path, index=False, encoding="utf-8")

    # Export full query table after append (217 query IDs)
    query_tbl = (
        merged[["query_id", "query_text", "query_text_paraphrase"]]
        .drop_duplicates(subset=["query_id"])
        .sort_values("query_id")
        .reset_index(drop=True)
    )
    query_tbl.to_csv(query_list_217_path, index=False, encoding="utf-8")

    # Keep legacy filename in sync with latest state for downstream scripts.
    # If the file is open/locked, keep processing successful outputs.
    try:
        query_tbl.to_csv(query_list_200_path, index=False, encoding="utf-8")
    except PermissionError:
        print(f"[WARN] Skipped writing locked file: {query_list_200_path}")

    print(f"Added query_ids: {candidates}")
    print(f"Added rows: {len(add_df)}")
    print(
        "Final stats: "
        f"rows={len(merged)}, "
        f"unique_query_id={merged['query_id'].nunique()}, "
        f"unique_query_text={merged['query_text'].nunique()}, "
        f"unique_paraphrase={query_tbl['query_text_paraphrase'].nunique()}"
    )
    print(f"Saved: {qrels_path}")
    print(f"Saved: {query_list_217_path}")


if __name__ == "__main__":
    main()
