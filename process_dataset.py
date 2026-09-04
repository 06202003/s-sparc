import sys
import traceback
import json
import pandas as pd
from pathlib import Path

try:
    # Read raw data
    with open('semantic_similarity/mbpp_real.json') as f:
        real_raw = json.load(f)

    with open('semantic_similarity/mbpp_clone.json') as f:
        clone_raw = json.load(f)

    print(f"Loaded: real={len(real_raw)}, clone={len(clone_raw)}")

    # Transform and add task_id, source
    mbpp_real = []
    for idx, entry in enumerate(real_raw):
        mbpp_real.append({
            "task_id": idx,
            "source": "mbpp",
            "prompt": entry.get("prompt", "").strip(),
            "code": entry.get("code", "").strip()
        })

    mbpp_clone = []
    for idx, entry in enumerate(clone_raw):
        mbpp_clone.append({
            "task_id": idx,
            "source": "mbpp",
            "paraphrased_prompt": entry.get("prompt", "").strip(),
            "generated_code": entry.get("code", "").strip()
        })

    print("Transformed data successfully")

    # Create dataset folder
    dataset_dir = Path("dataset")
    dataset_dir.mkdir(exist_ok=True)
    print(f"Created dataset folder: {dataset_dir.absolute()}")

    # Save JSON files
    with open(dataset_dir / "MBPP_real.json", "w", encoding="utf-8") as f:
        json.dump(mbpp_real, f, indent=2, ensure_ascii=False)

    with open(dataset_dir / "MBPP_clone.json", "w", encoding="utf-8") as f:
        json.dump(mbpp_clone, f, indent=2, ensure_ascii=False)

    print("Saved JSON files")

    # Create combined dataframe
    df_combined = pd.DataFrame({
        "task_id": [entry["task_id"] for entry in mbpp_real],
        "source": [entry["source"] for entry in mbpp_real],
        "original_prompt": [entry["prompt"] for entry in mbpp_real],
        "original_code": [entry["code"] for entry in mbpp_real],
        "paraphrased_prompt": [entry["paraphrased_prompt"] for entry in mbpp_clone],
        "generated_code": [entry["generated_code"] for entry in mbpp_clone]
    })

    print(f"Created combined dataframe: {len(df_combined)} rows")

    # Validation
    null_count = df_combined.isnull().sum().sum()
    dup_count = df_combined.duplicated(subset=['original_prompt', 'original_code']).sum()
    
    print(f"Validation: nulls={null_count}, duplicates={dup_count}")

    # Save CSV
    csv_path = dataset_dir / "dataset_combined.csv"
    df_combined.to_csv(csv_path, index=False, encoding="utf-8")
    print(f"Saved CSV: {csv_path}")

    # Save Excel
    xlsx_path = dataset_dir / "dataset_combined.xlsx"
    df_combined.to_excel(xlsx_path, index=False, engine="openpyxl")
    print(f"Saved XLSX: {xlsx_path}")

    print("\n✅ SUCCESS - Dataset structure created")
    print(f"   Total entries: {len(df_combined)}")
    print(f"   Output directory: dataset/")

except Exception as e:
    print(f"ERROR: {e}")
    traceback.print_exc()
    sys.exit(1)
