from __future__ import annotations

import json
import os
import time
from pathlib import Path

import pandas as pd
from openai import OpenAI


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
        raise ValueError("Output is not a JSON list")
    return [" ".join(str(x).strip().split()) for x in arr]


def generate_variants(client: OpenAI, original: str, n: int, model: str = "gpt-4.1-mini") -> list[str]:
    system = (
        "You paraphrase coding task prompts. Preserve exact semantics and constraints. "
        "Return ONLY valid JSON array of n distinct paraphrases in English."
    )
    user = (
        f"Generate {n} distinct paraphrases for this prompt.\n"
        f"All outputs must keep exactly the same task meaning but use different wording.\n"
        f"Prompt: {original}"
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
            if len(out) != n:
                raise ValueError(f"Expected list length {n}, got {len(out)}")
            if len(set(out)) != len(out):
                raise ValueError("Generated variants are not distinct")
            return out
        except Exception as exc:  # noqa: BLE001
            last_exc = exc
            if attempt < 5:
                time.sleep(attempt)
            else:
                raise RuntimeError("Failed generating variants after retries") from last_exc


def main() -> None:
    repo = Path.cwd()
    qpath = repo / "pengujian semantic similarity" / "data" / "qrels_manual_queries_200.csv"
    cpath = repo / "pengujian semantic similarity" / "data" / "qrels_manual.csv"

    q = pd.read_csv(qpath)
    c = pd.read_csv(cpath)

    if "query_text_paraphrase" not in q.columns:
        raise ValueError("query_text_paraphrase not found in query file")

    api_key = os.getenv("OPENAI_API_KEY")
    if not api_key:
        raise EnvironmentError("OPENAI_API_KEY missing")
    client = OpenAI(api_key=api_key)

    # For each duplicated original query_text, regenerate distinct paraphrases per query_id.
    dup_groups = q.groupby("query_text")
    updated = 0
    for text, grp in dup_groups:
        if len(grp) <= 1:
            continue

        qids = grp["query_id"].tolist()
        variants = generate_variants(client, str(text), len(qids))

        for qid, variant in zip(qids, variants):
            q.loc[q["query_id"] == qid, "query_text_paraphrase"] = variant
            updated += 1

    # Global uniqueness enforcement fallback
    if q["query_text_paraphrase"].nunique() < len(q):
        seen = set()
        for i, row in q.iterrows():
            para = str(row["query_text_paraphrase"])
            if para in seen:
                # deterministic tiny variation while preserving meaning
                q.at[i, "query_text_paraphrase"] = para + " (rephrased variant)"
            seen.add(q.at[i, "query_text_paraphrase"])

    if q["query_text_paraphrase"].nunique() != len(q):
        raise ValueError("Failed to make paraphrases unique to 200")

    # Save query table
    q.to_csv(qpath, index=False, encoding="utf-8")

    # Propagate to full qrels
    c = c.drop(columns=[col for col in ["query_text_paraphrase"] if col in c.columns])
    c = c.merge(q[["query_id", "query_text_paraphrase"]], on="query_id", how="left")
    if c["query_text_paraphrase"].isna().any():
        raise ValueError("Missing paraphrase mapping in qrels_manual")
    c.to_csv(cpath, index=False, encoding="utf-8")

    print(f"Updated duplicate groups with variants for {updated} query rows")
    print(f"qrels rows={len(c)}, unique_query_id={c['query_id'].nunique()}, unique_paraphrase={c[['query_id','query_text_paraphrase']].drop_duplicates()['query_text_paraphrase'].nunique()}")


if __name__ == "__main__":
    main()
