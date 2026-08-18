from __future__ import annotations

import json
import os
import sys
import time
from pathlib import Path

import pandas as pd
from openai import OpenAI

REPO_ROOT = Path(__file__).resolve().parents[1]
EVAL_UTILS_DIR = REPO_ROOT / "pengujian semantic similarity"
if str(EVAL_UTILS_DIR) not in sys.path:
    sys.path.insert(0, str(EVAL_UTILS_DIR))

from eval_utils import build_normalized_embeddings, load_dataset, rank_for_query  # noqa: E402


def _parse_json_array(text: str) -> list[str]:
    raw = (text or "").strip()
    if not raw:
        raise ValueError("Empty model output")
    try:
        arr = json.loads(raw)
    except Exception:
        start = raw.find("[")
        end = raw.rfind("]")
        if start == -1 or end == -1 or end <= start:
            raise
        arr = json.loads(raw[start : end + 1])
    if not isinstance(arr, list):
        raise ValueError("Model output is not a JSON list")
    return [" ".join(str(x).strip().split()) for x in arr]


def generate_20_paraphrases(client: OpenAI, prompt: str, model: str = "gpt-4.1-mini") -> list[str]:
    system = (
        "You paraphrase coding task prompts. Keep exact semantics and constraints. "
        "Return ONLY a JSON array with exactly 20 distinct English paraphrases."
    )
    user = (
        "Create 20 distinct paraphrases for this coding task prompt. "
        "Do not change the task meaning. Do not add extra requirements.\n\n"
        f"Prompt: {prompt}"
    )

    last_exc: Exception | None = None
    for attempt in range(1, 6):
        try:
            resp = client.responses.create(
                model=model,
                temperature=0.7,
                input=[
                    {"role": "system", "content": system},
                    {"role": "user", "content": user},
                ],
            )
            out = _parse_json_array(resp.output_text or "")
            if len(out) != 20:
                raise ValueError(f"Expected 20 paraphrases, got {len(out)}")
            if len(set(out)) != len(out):
                raise ValueError("Paraphrases are not distinct")
            return out
        except Exception as exc:  # noqa: BLE001
            last_exc = exc
            if attempt < 5:
                time.sleep(attempt)
            else:
                raise RuntimeError("Failed generating 20 paraphrases") from last_exc


def main() -> None:
    data_dir = REPO_ROOT / "pengujian semantic similarity" / "data"
    qrels_path = data_dir / "qrels_manual.csv"
    out_fallback = data_dir / "qrels_manual_plus_140.csv"
    query_out = data_dir / "qrels_manual_queries_224.csv"

    qrels = pd.read_csv(qrels_path)
    qrels["query_id"] = qrels["query_id"].astype(int)

    dataset_path = REPO_ROOT / "semantic_similarity" / "mbpp_all_with_embedding_and_relevance_v2.json"
    df = load_dataset(dataset_path)
    emb = build_normalized_embeddings(df)

    existing_qids = set(qrels["query_id"].tolist())
    existing_prompts = set(qrels["query_text"].astype(str).tolist())

    selected_qids: list[int] = []
    for qid, row in df.iterrows():
        qid_int = int(qid)
        prompt = str(row["prompt"])
        if qid_int in existing_qids:
            continue
        if prompt in existing_prompts:
            continue
        selected_qids.append(qid_int)
        if len(selected_qids) == 7:
            break

    if len(selected_qids) < 7:
        raise ValueError(f"Only found {len(selected_qids)} new prompts; need 7")

    api_key = os.getenv("OPENAI_API_KEY")
    if not api_key:
        raise EnvironmentError("OPENAI_API_KEY is not set")
    client = OpenAI(api_key=api_key)

    add_rows: list[dict] = []
    for qid in selected_qids:
        query_text = str(df.iloc[qid]["prompt"])
        paraphrases = generate_20_paraphrases(client, query_text)
        ranked_ids, ranked_scores = rank_for_query(emb, qid)
        top_ids = ranked_ids[:20]
        top_scores = ranked_scores[:20]

        for rank, (doc_id, score) in enumerate(zip(top_ids, top_scores), start=1):
            add_rows.append(
                {
                    "query_id": int(qid),
                    "query_text": query_text,
                    "doc_id": int(doc_id),
                    "doc_text": str(df.iloc[int(doc_id)]["prompt"]),
                    "pred_score": float(score),
                    "rank_hint": int(rank),
                    "rel": "",
                    "notes": "",
                    "query_text_paraphrase": paraphrases[rank - 1],
                }
            )

    add_df = pd.DataFrame(add_rows)
    if len(add_df) != 140:
        raise ValueError(f"Expected 140 appended rows, got {len(add_df)}")

    merged = pd.concat([qrels, add_df], ignore_index=True)
    if merged.duplicated(subset=["query_id", "rank_hint"]).any():
        raise ValueError("Duplicate (query_id, rank_hint) after append")

    merged = merged.sort_values(["query_id", "rank_hint"], ascending=[True, True]).reset_index(drop=True)

    write_target = qrels_path
    try:
        merged.to_csv(qrels_path, index=False, encoding="utf-8")
    except PermissionError:
        write_target = out_fallback
        merged.to_csv(out_fallback, index=False, encoding="utf-8")

    query_tbl = (
        merged[["query_id", "query_text", "query_text_paraphrase"]]
        .drop_duplicates(subset=["query_id", "query_text_paraphrase"])
        .sort_values(["query_id", "query_text_paraphrase"])
        .reset_index(drop=True)
    )
    query_tbl.to_csv(query_out, index=False, encoding="utf-8")

    print(f"Added query_ids: {selected_qids}")
    print(f"Added rows: {len(add_df)}")
    print(f"Saved qrels: {write_target}")
    print(f"Saved query export: {query_out}")
    print(
        "Stats: "
        f"rows={len(merged)}, "
        f"unique_query_id={merged['query_id'].nunique()}, "
        f"unique_query_text={merged['query_text'].nunique()}"
    )


if __name__ == "__main__":
    main()
