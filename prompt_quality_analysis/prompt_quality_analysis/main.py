from __future__ import annotations

import argparse
import time
from pathlib import Path
from typing import Any

import pandas as pd

try:
    from dotenv import load_dotenv
except ImportError:  # pragma: no cover - optional dependency
    load_dotenv = None

if load_dotenv is not None:
    load_dotenv()

from .evaluator import PromptQualityEvaluator


PROMPT_COLUMN_CANDIDATES = ["prompt", "prompt_text", "instruction", "question", "input", "task"]
RESPONSE_COLUMN_CANDIDATES = ["response", "response_text", "answer", "output", "completion", "generated_code", "reply", "code", "raw_response"]
DEFAULT_INPUT_PATH = Path(__file__).resolve().parent.parent.parent / "prompt_token_calc" / "output_with_tokens.xlsx"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Evaluate prompt and response quality from an Excel file.")
    parser.add_argument(
        "--input",
        default=str(DEFAULT_INPUT_PATH),
        help=f"Path to the source Excel file (default: {DEFAULT_INPUT_PATH})",
    )
    parser.add_argument(
        "--output",
        help="Path to the output Excel file (default: same folder as input with _evaluated suffix)",
    )
    parser.add_argument("--sheet", default=0, help="Sheet name or zero-based index to read")
    parser.add_argument("--prompt-column", dest="prompt_column", help="Column containing prompts")
    parser.add_argument("--response-column", dest="response_column", help="Column containing responses")
    parser.add_argument("--id-column", dest="id_column", help="Optional row id column")
    parser.add_argument("--model", default="gpt-4o-mini", help="OpenAI model name, overrides OPENAI_MODEL")
    parser.add_argument("--api-key", dest="api_key", help="OpenAI API key, overrides OPENAI_API_KEY")
    parser.add_argument("--max-retries", type=int, default=3, help="Number of retries for API calls")
    parser.add_argument("--request-timeout", type=float, default=60.0, help="Timeout in seconds for each OpenAI request")
    parser.add_argument("--checkpoint-every", type=int, default=25, help="Write the output workbook every N rows")
    parser.add_argument("--start-row", type=int, default=0, help="Start evaluating from this zero-based row index")
    parser.add_argument("--max-rows", type=int, default=0, help="Maximum number of rows to evaluate (0 means all)")
    parser.add_argument("--sample-rate", type=float, default=1.0, help="Fraction of rows to evaluate, from 0<rate<=1")
    parser.add_argument("--seed", type=int, default=42, help="Random seed used when sample-rate < 1")
    parser.add_argument("--only-non-retrieval", action="store_true", help="Evaluate only rows where is_retrieval is false (if column exists)")
    parser.add_argument("--cheap-model", action="store_true", help="Shortcut to use gpt-4o-mini regardless of --model")
    return parser.parse_args()


def detect_column(columns: list[str], preferred: list[str]) -> str | None:
    lower_map = {column.lower(): column for column in columns}
    for candidate in preferred:
        if candidate.lower() in lower_map:
            return lower_map[candidate.lower()]
    return None


def load_source_data(path: Path, sheet: str | int) -> pd.DataFrame:
    if not path.exists():
        raise FileNotFoundError(f"Input file not found: {path}")
    return pd.read_excel(path, sheet_name=sheet)


def build_summary(df: pd.DataFrame) -> pd.DataFrame:
    summary: dict[str, Any] = {
        "rows": len(df),
        "prompt_score_mean": round(float(df["prompt_score"].mean()), 2) if not df.empty else 0.0,
        "response_score_mean": round(float(df["response_score"].mean()), 2) if not df.empty else 0.0,
        "overall_score_mean": round(float(df["overall_score"].mean()), 2) if not df.empty else 0.0,
        "needs_human_review_count": int(df["needs_human_review"].sum()) if "needs_human_review" in df.columns else 0,
    }
    if not df.empty and "verdict" in df.columns:
        verdict_counts = df["verdict"].value_counts(dropna=False).to_dict()
        for key, value in verdict_counts.items():
            summary[f"verdict_{key}"] = int(value)
    if not df.empty and "evaluation_status" in df.columns:
        status_counts = df["evaluation_status"].value_counts(dropna=False).to_dict()
        for key, value in status_counts.items():
            summary[f"status_{key}"] = int(value)
    return pd.DataFrame([summary])


def write_output(output_path: Path, evaluated_rows: list[dict[str, Any]]) -> None:
    evaluated_df = pd.DataFrame(evaluated_rows)
    summary_df = build_summary(evaluated_df)

    with pd.ExcelWriter(output_path, engine="openpyxl") as writer:
        evaluated_df.to_excel(writer, index=False, sheet_name="evaluated_data")
        summary_df.to_excel(writer, index=False, sheet_name="summary")


def select_rows(df: pd.DataFrame, args: argparse.Namespace) -> pd.DataFrame:
    selected = df.copy()

    if args.only_non_retrieval and "is_retrieval" in selected.columns:
        retrieval_values = selected["is_retrieval"].astype(str).str.strip().str.lower()
        is_retrieval = retrieval_values.isin({"1", "true", "yes", "y", "t"})
        selected = selected.loc[~is_retrieval]

    start = max(0, int(args.start_row))
    if start > 0:
        selected = selected.iloc[start:]

    max_rows = int(args.max_rows)
    if max_rows > 0:
        selected = selected.iloc[:max_rows]

    sample_rate = float(args.sample_rate)
    if sample_rate <= 0 or sample_rate > 1:
        raise ValueError("sample-rate must be in range (0, 1]")
    if sample_rate < 1.0 and not selected.empty:
        selected = selected.sample(frac=sample_rate, random_state=int(args.seed)).sort_index()

    return selected


def main() -> int:
    args = parse_args()
    input_path = Path(args.input).expanduser().resolve()
    output_path = Path(args.output).expanduser().resolve() if args.output else input_path.with_name(f"{input_path.stem}_evaluated.xlsx")
    sheet_value: str | int
    try:
        sheet_value = int(args.sheet)
    except (TypeError, ValueError):
        sheet_value = args.sheet

    source_df = load_source_data(input_path, sheet_value)
    if source_df.empty:
        raise ValueError("Input Excel file is empty")

    prompt_column = args.prompt_column or detect_column(list(source_df.columns), PROMPT_COLUMN_CANDIDATES)
    response_column = args.response_column or detect_column(list(source_df.columns), RESPONSE_COLUMN_CANDIDATES)

    if not prompt_column:
        raise ValueError(f"Prompt column not found. Available columns: {list(source_df.columns)}")
    if not response_column:
        raise ValueError(f"Response column not found. Available columns: {list(source_df.columns)}")

    model_name = "gpt-4o-mini" if args.cheap_model else args.model

    evaluator = PromptQualityEvaluator(
        model=model_name,
        api_key=args.api_key,
        max_retries=args.max_retries,
        request_timeout_seconds=args.request_timeout,
    )

    working_df = select_rows(source_df, args)
    if working_df.empty:
        raise ValueError("No rows selected after applying filters. Adjust start-row/max-rows/sample-rate/filter options.")

    evaluated_rows: list[dict[str, Any]] = []
    result_cache: dict[tuple[str, str], dict[str, Any]] = {}
    total_rows = len(working_df)
    print(f"Loaded {len(source_df)} rows from {input_path}")
    print(f"Selected {total_rows} rows after filters")
    print(f"Using prompt column: {prompt_column}")
    print(f"Using response column: {response_column}")
    print(f"Using model: {model_name}")
    started_at = time.perf_counter()

    for index, (_, row) in enumerate(working_df.iterrows(), start=1):
        row_id = row.get(args.id_column) if args.id_column else row.name
        prompt_text = row.get(prompt_column, "")
        response_text = row.get(response_column, "")
        if (response_text is None or str(response_text).strip() == "") and "raw_response" in source_df.columns:
            response_text = row.get("raw_response", "")

        print(f"[{index}/{total_rows}] Evaluating row {row_id}...")
        row_output = row.to_dict()
        cache_key = (str(prompt_text or "").strip(), str(response_text or "").strip())
        try:
            if cache_key in result_cache:
                cached = dict(result_cache[cache_key])
                cached["row_id"] = row_id
                row_output.update(cached)
                row_output["evaluation_status"] = "cached"
                row_output["evaluation_error"] = ""
                print(f"[CACHE] Reused evaluation for row {row_id}")
            else:
                result = evaluator.evaluate(prompt_text, response_text, row_id=row_id)
                result_dict = result.to_dict()
                row_output.update(result_dict)
                result_cache[cache_key] = dict(result_dict)
                row_output["evaluation_status"] = "ok"
                row_output["evaluation_error"] = ""
        except Exception as exc:
            row_output.update(
                {
                    "row_id": row_id,
                    "prompt_score": 0.0,
                    "response_score": 0.0,
                    "overall_score": 0.0,
                    "prompt_strengths": "",
                    "prompt_issues": "",
                    "response_strengths": "",
                    "response_issues": "",
                    "prompt_rewrite": "",
                    "response_improvement_suggestions": "",
                    "verdict": "error",
                    "needs_human_review": True,
                    "model": model_name,
                    "raw_json": "",
                    "evaluation_status": "error",
                    "evaluation_error": str(exc),
                }
            )
            print(f"[WARN] Row {row_id} failed: {exc}")
        evaluated_rows.append(row_output)

        if index % max(1, int(args.checkpoint_every)) == 0 or index == total_rows:
            write_output(output_path, evaluated_rows)
            elapsed = time.perf_counter() - started_at
            rate = index / elapsed if elapsed > 0 else 0.0
            remaining = max(0, total_rows - index)
            eta_seconds = remaining / rate if rate > 0 else 0.0
            print(f"Checkpoint saved to {output_path} | elapsed={elapsed:.1f}s | eta={eta_seconds/60:.1f} min")

    print(f"Saved evaluated data to {output_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
