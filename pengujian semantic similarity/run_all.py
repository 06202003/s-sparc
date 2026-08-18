from __future__ import annotations

import subprocess
import sys
from pathlib import Path


def run(cmd: list[str]) -> None:
    print("\n[RUN]", " ".join(cmd))
    subprocess.run(cmd, check=True)


def main() -> None:
    root = Path(__file__).resolve().parent
    project_root = root.parent

    dataset = str(project_root / "semantic_similarity" / "mbpp_all_with_embedding_and_relevance_v2.json")
    qrels_weak = str(root / "data" / "qrels_weak.csv")
    qrels_manual = str(root / "data" / "qrels_manual.csv")

    qrels_manual_template = str(root / "data" / "qrels_manual_template.csv")

    retrieval_summary_weak = str(root / "outputs" / "retrieval_summary_weak.csv")
    retrieval_per_query_weak = str(root / "outputs" / "retrieval_per_query_weak.csv")
    threshold_summary_weak = str(root / "outputs" / "threshold_summary_weak.csv")
    laporan_weak = str(root / "outputs" / "laporan_bab_evaluasi_weak.md")

    retrieval_summary_manual = str(root / "outputs" / "retrieval_summary_manual.csv")
    retrieval_per_query_manual = str(root / "outputs" / "retrieval_per_query_manual.csv")
    threshold_summary_manual = str(root / "outputs" / "threshold_summary_manual.csv")
    laporan_manual = str(root / "outputs" / "laporan_bab_evaluasi_manual.md")

    # 1) Generate weak qrels + manual template
    run(
        [
            sys.executable,
            str(root / "generate_qrels.py"),
            "--dataset",
            dataset,
            "--weak-output",
            qrels_weak,
            "--manual-template-output",
            qrels_manual_template,
        ]
    )

    # 2) Retrieval eval with weak qrels (baseline internal)
    run(
        [
            sys.executable,
            str(root / "evaluate_retrieval.py"),
            "--dataset",
            dataset,
            "--qrels",
            qrels_weak,
            "--ks",
            "1,3,5,10",
            "--output",
            retrieval_summary_weak,
            "--detail-output",
            retrieval_per_query_weak,
        ]
    )

    # 3) Threshold eval with weak qrels
    run(
        [
            sys.executable,
            str(root / "evaluate_threshold.py"),
            "--dataset",
            dataset,
            "--qrels",
            qrels_weak,
            "--thresholds",
            "0.80,0.90",
            "--scan",
            "--output",
            threshold_summary_weak,
        ]
    )

    # 3b) Generate thesis-ready markdown report (weak)
    run(
        [
            sys.executable,
            str(root / "generate_thesis_report.py"),
            "--retrieval-summary",
            retrieval_summary_weak,
            "--threshold-summary",
            threshold_summary_weak,
            "--output",
            laporan_weak,
            "--label",
            "weak",
            "--focus-thresholds",
            "0.80,0.90",
        ]
    )

    # 4) If manual qrels already exists, run gold evaluation too
    if Path(qrels_manual).exists():
        print("\n[INFO] Found manual qrels. Running gold evaluation...")
        run(
            [
                sys.executable,
                str(root / "evaluate_retrieval.py"),
                "--dataset",
                dataset,
                "--qrels",
                qrels_manual,
                "--ks",
                "1,3,5,10",
                "--output",
                retrieval_summary_manual,
                "--detail-output",
                retrieval_per_query_manual,
            ]
        )
        run(
            [
                sys.executable,
                str(root / "evaluate_threshold.py"),
                "--dataset",
                dataset,
                "--qrels",
                qrels_manual,
                "--thresholds",
                "0.80,0.90",
                "--scan",
                "--output",
                threshold_summary_manual,
            ]
        )
        run(
            [
                sys.executable,
                str(root / "generate_thesis_report.py"),
                "--retrieval-summary",
                retrieval_summary_manual,
                "--threshold-summary",
                threshold_summary_manual,
                "--output",
                laporan_manual,
                "--label",
                "manual",
                "--focus-thresholds",
                "0.80,0.90",
            ]
        )
    else:
        print("\n[INFO] Manual qrels not found at pengujian semantic similarity/data/qrels_manual.csv")
        print("[INFO] Please copy qrels_manual_template.csv -> qrels_manual.csv and fill rel labels (0-3), then rerun run_all.py")

    print("\n[DONE] Pengujian semantic similarity selesai.")


if __name__ == "__main__":
    main()
