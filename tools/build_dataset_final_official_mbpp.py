from __future__ import annotations

import argparse
import json
import os
import time
from pathlib import Path
from typing import Any

import pandas as pd
import requests
from openai import OpenAI

MBPP_URL = "https://raw.githubusercontent.com/google-research/google-research/master/mbpp/mbpp.jsonl"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Build dataset_final.xlsx from official MBPP test split (11-510)")
    parser.add_argument("--model", default="gpt-4.1-mini")
    parser.add_argument("--batch-size", type=int, default=20)
    parser.add_argument("--max-retries", type=int, default=6)
    parser.add_argument("--max-batches", type=int, default=0, help="Process at most this many batches per run (0 = all)")
    parser.add_argument("--checkpoint", default="DATASET/mbpp_official_progress.jsonl")
    parser.add_argument("--output", default="dataset_final.xlsx")
    return parser.parse_args()


def clean_text(s: str) -> str:
    return " ".join(str(s).strip().split())


def clean_code(s: str) -> str:
    return str(s).strip().replace("\r\n", "\n").replace("\r", "\n")


def parse_json_array(text: str) -> list[str]:
    raw = text.strip()
    try:
        val = json.loads(raw)
        if isinstance(val, list):
            return [str(x) for x in val]
    except json.JSONDecodeError:
        pass

    start = raw.find("[")
    end = raw.rfind("]")
    if start != -1 and end != -1 and end > start:
        sub = raw[start : end + 1]
        val = json.loads(sub)
        if isinstance(val, list):
            return [str(x) for x in val]

    raise ValueError("Model output is not a valid JSON array")


def call_batch_with_retry(
    client: OpenAI,
    model: str,
    system_prompt: str,
    user_prompt: str,
    expected_len: int,
    max_retries: int,
) -> list[str]:
    last_err: Exception | None = None
    for attempt in range(1, max_retries + 1):
        try:
            resp = client.responses.create(
                model=model,
                temperature=0.2,
                input=[
                    {"role": "system", "content": system_prompt},
                    {"role": "user", "content": user_prompt},
                ],
            )
            out = parse_json_array(resp.output_text or "")
            if len(out) != expected_len:
                raise ValueError(f"Expected {expected_len} items, got {len(out)}")
            return out
        except Exception as exc:  # noqa: BLE001
            last_err = exc
            if attempt == max_retries:
                break
            time.sleep(attempt)
    raise RuntimeError("Failed batch API call after retries") from last_err


def download_official_mbpp() -> list[dict[str, Any]]:
    resp = requests.get(MBPP_URL, timeout=60)
    resp.raise_for_status()

    records: list[dict[str, Any]] = []
    for i, line in enumerate(resp.text.splitlines(), start=1):
        line = line.strip()
        if not line:
            continue
        try:
            rec = json.loads(line)
        except json.JSONDecodeError as exc:
            raise ValueError(f"Invalid JSON at line {i}") from exc

        task_id = rec.get("task_id")
        text = rec.get("text")
        code = rec.get("code")
        if task_id is None or text is None or code is None:
            continue

        if 11 <= int(task_id) <= 510:
            records.append(
                {
                    "task_id": int(task_id),
                    "prompt_asli": clean_text(text),
                    "kode_asli": clean_code(code),
                }
            )

    records = sorted(records, key=lambda x: x["task_id"])

    expected_ids = list(range(11, 511))
    got_ids = [r["task_id"] for r in records]
    if got_ids != expected_ids:
        missing = sorted(set(expected_ids) - set(got_ids))
        extra = sorted(set(got_ids) - set(expected_ids))
        raise ValueError(f"MBPP split mismatch. Missing: {missing[:10]} Extra: {extra[:10]}")

    return records


def load_checkpoint(path: Path) -> dict[int, dict[str, Any]]:
    if not path.exists():
        return {}

    out: dict[int, dict[str, Any]] = {}
    with path.open("r", encoding="utf-8") as f:
        for i, line in enumerate(f, start=1):
            line = line.strip()
            if not line:
                continue
            try:
                row = json.loads(line)
            except json.JSONDecodeError as exc:
                raise ValueError(f"Invalid checkpoint JSON at line {i}") from exc
            tid = int(row["task_id"])
            out[tid] = row
    return out


def append_checkpoint(path: Path, row: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("a", encoding="utf-8") as f:
        f.write(json.dumps(row, ensure_ascii=False) + "\n")


def validate_final(df: pd.DataFrame) -> None:
    required = ["task_id", "prompt_asli", "kode_asli", "prompt_parafrase", "kode_parafrase", "sumber"]
    if list(df.columns) != required:
        raise ValueError("Column mismatch")

    if len(df) != 500:
        raise ValueError(f"Expected 500 rows, got {len(df)}")

    if df["task_id"].min() < 11 or df["task_id"].max() > 510:
        raise ValueError("task_id outside [11, 510]")

    if sorted(df["task_id"].tolist()) != list(range(11, 511)):
        raise ValueError("task_id set mismatch with expected test split")

    if df.isnull().sum().sum() != 0:
        raise ValueError("Missing values found")

    for col in ["prompt_asli", "kode_asli", "prompt_parafrase", "kode_parafrase", "sumber"]:
        if (df[col].astype(str).str.strip() == "").any():
            raise ValueError(f"Empty strings found in {col}")

    if (df["prompt_asli"].astype(str).str.strip() == df["prompt_parafrase"].astype(str).str.strip()).any():
        raise ValueError("Found identical paraphrase and original prompt")

    if not (df["sumber"] == "mbpp").all():
        raise ValueError("Invalid sumber values")


def main() -> None:
    args = parse_args()

    api_key = os.getenv("OPENAI_API_KEY")
    if not api_key:
        raise EnvironmentError("OPENAI_API_KEY is not set")

    base_records = download_official_mbpp()
    client = OpenAI(api_key=api_key)

    checkpoint_path = Path(args.checkpoint)
    done_map = load_checkpoint(checkpoint_path)

    pending = [r for r in base_records if r["task_id"] not in done_map]
    print(f"Official MBPP test rows: {len(base_records)}")
    print(f"Already completed: {len(done_map)}")
    print(f"Pending: {len(pending)}")

    batch_size = max(1, args.batch_size)

    batches_done = 0
    for i in range(0, len(pending), batch_size):
        if args.max_batches > 0 and batches_done >= args.max_batches:
            break
        batch = pending[i : i + batch_size]
        prompts = [b["prompt_asli"] for b in batch]

        numbered_prompts = "\n".join([f"{idx+1}. {p}" for idx, p in enumerate(prompts)])
        paraphrase_system = (
            "Paraphrase programming prompts. Keep exact semantics and constraints. "
            "Return ONLY a valid JSON array of strings, same order and same length."
        )
        paraphrase_user = (
            "Paraphrase each prompt below with non-trivial wording changes but identical meaning. "
            "Do not change requirements.\n\n"
            f"{numbered_prompts}"
        )

        paraphrases = call_batch_with_retry(
            client=client,
            model=args.model,
            system_prompt=paraphrase_system,
            user_prompt=paraphrase_user,
            expected_len=len(batch),
            max_retries=args.max_retries,
        )
        paraphrases = [clean_text(x) for x in paraphrases]

        for orig, para in zip(prompts, paraphrases):
            if clean_text(orig) == clean_text(para):
                raise ValueError("Paraphrase equals original in a batch")

        numbered_paraphrases = "\n".join([f"{idx+1}. {p}" for idx, p in enumerate(paraphrases)])
        code_system = (
            "Generate Python solutions for MBPP-style tasks. "
            "Return ONLY a valid JSON array of code strings, same order and same length. "
            "No markdown fences, no explanations."
        )
        code_user = f"Generate code for each prompt below:\n\n{numbered_paraphrases}"

        codes = call_batch_with_retry(
            client=client,
            model=args.model,
            system_prompt=code_system,
            user_prompt=code_user,
            expected_len=len(batch),
            max_retries=args.max_retries,
        )
        codes = [clean_code(x) for x in codes]

        for item, para, code in zip(batch, paraphrases, codes):
            if not code:
                raise ValueError(f"Empty generated code for task_id={item['task_id']}")

            row = {
                "task_id": int(item["task_id"]),
                "prompt_asli": item["prompt_asli"],
                "kode_asli": item["kode_asli"],
                "prompt_parafrase": para,
                "kode_parafrase": code,
                "sumber": "mbpp",
            }
            done_map[row["task_id"]] = row
            append_checkpoint(checkpoint_path, row)

        batches_done += 1
        print(f"Completed {len(done_map)}/500")

    final_rows = [done_map[tid] for tid in range(11, 511)]
    df = pd.DataFrame(final_rows, columns=["task_id", "prompt_asli", "kode_asli", "prompt_parafrase", "kode_parafrase", "sumber"])
    validate_final(df)

    out_path = Path(args.output)
    df.to_excel(out_path, index=False, engine="openpyxl")
    print(f"Saved: {out_path}")
    print(f"Rows: {len(df)}")


if __name__ == "__main__":
    main()
