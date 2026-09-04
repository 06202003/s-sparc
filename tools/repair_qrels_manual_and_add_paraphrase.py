from __future__ import annotations

import json
import os
import time
from pathlib import Path

import pandas as pd
from openai import OpenAI


def paraphrase_batch(client: OpenAI, prompts: list[str], model: str, max_retries: int = 5) -> list[str]:
    numbered = "\n".join([f"{i+1}. {p}" for i, p in enumerate(prompts)])
    system = (
        "You paraphrase coding task prompts. Preserve exact meaning and constraints. "
        "Return ONLY valid JSON array of strings in the same order and same length."
    )
    user = (
        "Paraphrase each prompt with clear structural rewrite (not trivial), keep semantics exactly.\n"
        "Make each output concise and natural English.\n\n"
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
            text = (resp.output_text or "").strip()
            arr = json.loads(text)
            if not isinstance(arr, list) or len(arr) != len(prompts):
                raise ValueError(f"Invalid response shape: got {type(arr)} len={len(arr) if isinstance(arr, list) else 'NA'}")
            out = [" ".join(str(x).strip().split()) for x in arr]
            if any(not x for x in out):
                raise ValueError("Empty paraphrase detected")
            return out
        except Exception as exc:  # noqa: BLE001
            last_exc = exc
            if attempt < max_retries:
                time.sleep(attempt)
            else:
                raise RuntimeError("Paraphrase batch failed") from last_exc


def main() -> None:
    repo_root = Path.cwd()
    csv_path = repo_root / "pengujian semantic similarity" / "data" / "qrels_manual.csv"
    if not csv_path.exists():
        raise FileNotFoundError(f"Not found: {csv_path}")

    df = pd.read_csv(csv_path)

    # Canonical structure check
    if len(df) != 4000:
        raise ValueError(f"Expected 4000 rows, got {len(df)}")
    if df["query_id"].nunique() != 200:
        raise ValueError(f"Expected 200 query_id, got {df['query_id'].nunique()}")

    # Build unique query table by id (this is the true 200 prompt count)
    query_tbl = (
        df[["query_id", "query_text"]]
        .drop_duplicates(subset=["query_id"]) 
        .sort_values("query_id")
        .reset_index(drop=True)
    )
    if len(query_tbl) != 200:
        raise ValueError(f"Expected 200 unique query_id rows, got {len(query_tbl)}")

    api_key = os.getenv("OPENAI_API_KEY")
    if not api_key:
        raise EnvironmentError("OPENAI_API_KEY is not set")

    client = OpenAI(api_key=api_key)
    model = "gpt-4.1-mini"

    # Generate paraphrase prompt per query_id in batches
    all_paraphrases: list[str] = []
    batch_size = 20
    prompts = query_tbl["query_text"].astype(str).tolist()
    for i in range(0, len(prompts), batch_size):
        batch = prompts[i : i + batch_size]
        out = paraphrase_batch(client, batch, model=model)
        all_paraphrases.extend(out)
        print(f"Paraphrased {len(all_paraphrases)}/200")

    query_tbl["query_text_paraphrase"] = all_paraphrases

    # Repair csv by adding paraphrase mapped by query_id
    fixed = df.merge(query_tbl[["query_id", "query_text_paraphrase"]], on="query_id", how="left")

    if fixed["query_text_paraphrase"].isna().any():
        raise ValueError("Missing paraphrase mapping after merge")

    # backup + overwrite
    backup_path = csv_path.with_name("qrels_manual.backup_before_repair.csv")
    if not backup_path.exists():
        df.to_csv(backup_path, index=False, encoding="utf-8")

    fixed.to_csv(csv_path, index=False, encoding="utf-8")

    # also export query-only file for quick counting/inspection
    queries_out = csv_path.with_name("qrels_manual_queries_200.csv")
    query_tbl.to_csv(queries_out, index=False, encoding="utf-8")

    print(f"Saved repaired csv: {csv_path}")
    print(f"Saved query list: {queries_out}")
    print(f"Rows: {len(fixed)} | unique query_id: {fixed['query_id'].nunique()} | unique query_text: {fixed['query_text'].nunique()} | unique paraphrase: {fixed['query_text_paraphrase'].nunique()}")


if __name__ == "__main__":
    main()
