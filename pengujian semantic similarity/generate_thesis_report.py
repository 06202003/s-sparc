from __future__ import annotations

import argparse
from pathlib import Path
from typing import Iterable, List

import pandas as pd


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Generate thesis-ready evaluation report from CSV outputs")
    parser.add_argument(
        "--retrieval-summary",
        required=True,
        help="Path to retrieval summary CSV",
    )
    parser.add_argument(
        "--threshold-summary",
        required=True,
        help="Path to threshold summary CSV",
    )
    parser.add_argument(
        "--output",
        required=True,
        help="Output markdown path",
    )
    parser.add_argument(
        "--label",
        default="weak",
        help="Label for report context (e.g., weak/manual)",
    )
    parser.add_argument(
        "--focus-thresholds",
        default="0.80,0.90",
        help="Thresholds to highlight in report",
    )
    return parser.parse_args()


def fmt_pct(x: float) -> str:
    return f"{x * 100:.2f}%"


def fmt_float(x: float) -> str:
    return f"{x:.4f}"


def markdown_table(headers: List[str], rows: Iterable[Iterable[str]]) -> str:
    lines = ["| " + " | ".join(headers) + " |", "|" + "|".join(["---"] * len(headers)) + "|"]
    for r in rows:
        lines.append("| " + " | ".join(r) + " |")
    return "\n".join(lines)


def select_threshold_rows(df: pd.DataFrame, thresholds: List[float]) -> pd.DataFrame:
    picked = []
    for t in thresholds:
        idx = (df["threshold"] - t).abs().idxmin()
        picked.append(df.loc[idx])
    if not picked:
        return pd.DataFrame(columns=df.columns)
    out = pd.DataFrame(picked).drop_duplicates(subset=["threshold"]).sort_values("threshold")
    return out


def build_report(retrieval_df: pd.DataFrame, threshold_df: pd.DataFrame, label: str, focus_thresholds: List[float]) -> str:
    ks_target = [1, 3, 5, 10]
    retrieval_show = retrieval_df[retrieval_df["k"].isin(ks_target)].copy()
    if retrieval_show.empty:
        retrieval_show = retrieval_df.copy()

    retrieval_show = retrieval_show.sort_values("k")
    n_queries = int(retrieval_show["queries_evaluated"].iloc[0]) if not retrieval_show.empty else 0

    rows_retrieval = []
    for r in retrieval_show.itertuples(index=False):
        rows_retrieval.append(
            [
                str(int(r.k)),
                fmt_pct(float(r.hit)),
                fmt_pct(float(r.precision)),
                fmt_pct(float(r.recall)),
                fmt_float(float(r.mrr)),
                fmt_float(float(r.map)),
                fmt_float(float(r.ndcg)),
            ]
        )

    retrieval_table = markdown_table(
        ["k", "Hit@k", "Precision@k", "Recall@k", "MRR@k", "MAP@k", "nDCG@k"],
        rows_retrieval,
    )

    best_ndcg_row = retrieval_show.loc[retrieval_show["ndcg"].idxmax()] if not retrieval_show.empty else None
    best_map_row = retrieval_show.loc[retrieval_show["map"].idxmax()] if not retrieval_show.empty else None

    thr_show = select_threshold_rows(threshold_df, focus_thresholds)
    rows_thr = []
    for r in thr_show.itertuples(index=False):
        rows_thr.append(
            [
                f"{float(r.threshold):.2f}",
                str(int(r.tp)),
                str(int(r.fp)),
                str(int(r.tn)),
                str(int(r.fn)),
                fmt_pct(float(r.precision)),
                fmt_pct(float(r.recall)),
                fmt_float(float(r.f1)),
                fmt_pct(float(r.accuracy)),
                fmt_pct(float(r.retrieval_rate)),
            ]
        )

    threshold_table = markdown_table(
        ["Threshold", "TP", "FP", "TN", "FN", "Precision", "Recall", "F1", "Accuracy", "Retrieval Rate"],
        rows_thr,
    )

    best_f1_row = threshold_df.loc[threshold_df["f1"].idxmax()]

    interp_lines = []
    interp_lines.append(
        f"Pada skenario **{label}**, evaluasi dilakukan pada **{n_queries} query**."
    )
    if best_ndcg_row is not None and best_map_row is not None:
        interp_lines.append(
            f"Kualitas ranking terbaik terhadap nDCG pada tabel ini terjadi pada **k={int(best_ndcg_row['k'])}** dengan nDCG={best_ndcg_row['ndcg']:.4f}."
        )
        interp_lines.append(
            f"Nilai MAP tertinggi terjadi pada **k={int(best_map_row['k'])}** dengan MAP={best_map_row['map']:.4f}."
        )
    interp_lines.append(
        "Secara umum, ketika nilai k membesar, recall meningkat karena lebih banyak kandidat dikembalikan, sedangkan precision cenderung menurun."
    )

    decision_lines = []
    decision_lines.append(
        f"Threshold terbaik berdasarkan F1 global berada pada **{best_f1_row['threshold']:.2f}** dengan F1={best_f1_row['f1']:.4f}."
    )
    decision_lines.append(
        "Untuk implementasi aplikasi, threshold yang lebih tinggi (misalnya 0.90) biasanya meningkatkan kehati-hatian retrieval, namun menambah fallback ke GPT."
    )
    decision_lines.append(
        "Pemilihan akhir threshold disarankan mempertimbangkan trade-off kualitas jawaban retrieval, biaya token GPT, dan latensi sistem."
    )

    report = []
    report.append("# Hasil Evaluasi Semantic Similarity (Siap Tempel Bab Tesis)")
    report.append("")
    report.append("## Ringkasan Setup")
    report.append(f"- Sumber hasil: **{label} labels**")
    report.append(f"- Jumlah query dievaluasi: **{n_queries}**")
    report.append("")
    report.append("## Tabel 1. Kinerja Retrieval")
    report.append(retrieval_table)
    report.append("")
    report.append("### Interpretasi Otomatis (Retrieval)")
    for line in interp_lines:
        report.append(f"- {line}")
    report.append("")
    report.append("## Tabel 2. Evaluasi Threshold Keputusan")
    report.append(threshold_table)
    report.append("")
    report.append("### Interpretasi Otomatis (Threshold)")
    for line in decision_lines:
        report.append(f"- {line}")
    report.append("")
    report.append("## Kesimpulan Singkat")
    report.append(
        "Model retrieval menunjukkan performa tinggi pada skenario evaluasi ini. Untuk pelaporan ilmiah utama, disarankan menjadikan hasil **manual labels (gold qrels)** sebagai hasil utama, sedangkan weak labels digunakan sebagai baseline internal."
    )
    report.append("")

    return "\n".join(report)


def main() -> None:
    args = parse_args()

    retrieval_path = Path(args.retrieval_summary)
    threshold_path = Path(args.threshold_summary)
    output_path = Path(args.output)

    retrieval_df = pd.read_csv(retrieval_path)
    threshold_df = pd.read_csv(threshold_path)

    required_ret = {"k", "queries_evaluated", "hit", "precision", "recall", "mrr", "map", "ndcg"}
    missing_ret = required_ret - set(retrieval_df.columns)
    if missing_ret:
        raise ValueError(f"Retrieval summary missing columns: {sorted(missing_ret)}")

    required_thr = {"threshold", "tp", "fp", "tn", "fn", "precision", "recall", "f1", "accuracy", "retrieval_rate"}
    missing_thr = required_thr - set(threshold_df.columns)
    if missing_thr:
        raise ValueError(f"Threshold summary missing columns: {sorted(missing_thr)}")

    focus_thresholds = [float(x.strip()) for x in args.focus_thresholds.split(",") if x.strip()]

    report_md = build_report(
        retrieval_df=retrieval_df,
        threshold_df=threshold_df,
        label=args.label,
        focus_thresholds=focus_thresholds,
    )

    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(report_md, encoding="utf-8")

    print(f"[OK] Thesis-ready report generated: {output_path}")


if __name__ == "__main__":
    main()
