import pandas as pd
import tiktoken
from datetime import datetime

INPUT_FILE = "output_with_tokens.xlsx"
OUTPUT_FILE = f"token_retrieval_analysis_{datetime.now().strftime('%Y%m%d_%H%M%S')}.xlsx"
MODEL_NAME = "gpt-3.5-turbo"

# ========================
# LOAD DATA
# ========================
df = pd.read_excel(INPUT_FILE)

print("=== LOADING DATA ===")
print(f"Total rows: {len(df)}")
print("Kolom:", df.columns.tolist())

# ========================
# VALIDASI KOLOM
# ========================
required_cols = ["prompt", "raw_response", "is_retrieval"]
for col in required_cols:
    if col not in df.columns:
        raise ValueError(f"Kolom '{col}' tidak ditemukan.")

# ========================
# CLEAN DATA
# ========================
df["prompt"] = df["prompt"].astype(str)
df["raw_response"] = df["raw_response"].astype(str)

# ========================
# TOKENIZER
# ========================
try:
    enc = tiktoken.encoding_for_model(MODEL_NAME)
except KeyError:
    print(f"Model {MODEL_NAME} tidak ditemukan, pakai cl100k_base")
    enc = tiktoken.get_encoding("cl100k_base")

# ========================
# DETEKSI RETRIEVAL DETAIL
# ========================
def extract_retrieval_info(text):
    """Extract informasi dari retrieval response"""
    if text.strip().startswith("[RETRIEVAL]"):
        # Parse retrieval format
        try:
            # Format: [RETRIEVAL] <content>
            content = text.replace("[RETRIEVAL]", "", 1).strip()
            return {
                "is_retrieval": True,
                "retrieval_content": content,
                "retrieval_type": "RETRIEVED"
            }
        except:
            return {
                "is_retrieval": True,
                "retrieval_content": text,
                "retrieval_type": "RETRIEVAL_ERROR"
            }
    return {
        "is_retrieval": False,
        "retrieval_content": "",
        "retrieval_type": "GENERATED"
    }

# Apply extraction
retrieval_info = df["raw_response"].apply(extract_retrieval_info)
df["is_retrieval"] = retrieval_info.apply(lambda x: x["is_retrieval"])
df["retrieval_content"] = retrieval_info.apply(lambda x: x["retrieval_content"])
df["retrieval_type"] = retrieval_info.apply(lambda x: x["retrieval_type"])

# ========================
# HITUNG TOKEN - LEBIH DETAIL
# ========================
def count_tokens(text):
    """Hitung jumlah token dari text"""
    return len(enc.encode(text))

# Token calculation
df["prompt_tokens"] = df["prompt"].apply(count_tokens)
df["response_tokens"] = df["raw_response"].apply(count_tokens)
df["total_tokens"] = df["prompt_tokens"] + df["response_tokens"]

# Token retrieval vs generated
df["retrieval_tokens"] = df.apply(
    lambda row: row["response_tokens"] if row["is_retrieval"] else 0,
    axis=1
)
df["generated_tokens"] = df.apply(
    lambda row: 0 if row["is_retrieval"] else row["response_tokens"],
    axis=1
)

# ========================
# METRIK TAMBAHAN
# ========================
df["prompt_char_length"] = df["prompt"].str.len()
df["response_char_length"] = df["raw_response"].str.len()
df["token_to_char_ratio"] = df.apply(
    lambda row: row["total_tokens"] / row["response_char_length"] 
                if row["response_char_length"] > 0 else 0,
    axis=1
)

# ========================
# CREATE SUMMARY SHEET
# ========================
summary_data = {
    "Metrik": [
        "Total Queries",
        "Total Retrieval Queries",
        "Total Generated Queries",
        "Retrieval Percentage",
        "",
        "Total Tokens (All)",
        "Total Prompt Tokens",
        "Total Response Tokens",
        "Total Retrieval Tokens",
        "Total Generated Tokens",
        "",
        "Avg Tokens per Query",
        "Avg Prompt Tokens",
        "Avg Response Tokens",
        "Avg Retrieval Tokens",
        "Avg Generated Tokens",
    ],
    "Nilai": [
        len(df),
        df["is_retrieval"].sum(),
        (~df["is_retrieval"]).sum(),
        f"{(df['is_retrieval'].sum() / len(df) * 100):.2f}%",
        "",
        df["total_tokens"].sum(),
        df["prompt_tokens"].sum(),
        df["response_tokens"].sum(),
        df["retrieval_tokens"].sum(),
        df["generated_tokens"].sum(),
        "",
        f"{df['total_tokens'].mean():.2f}",
        f"{df['prompt_tokens'].mean():.2f}",
        f"{df['response_tokens'].mean():.2f}",
        f"{df['retrieval_tokens'].mean():.2f}",
        f"{df['generated_tokens'].mean():.2f}",
    ]
}

summary_df = pd.DataFrame(summary_data)

# ========================
# BREAKDOWN BY TYPE
# ========================
breakdown_data = {
    "Tipe": ["Retrieval", "Generated"],
    "Count": [
        df["is_retrieval"].sum(),
        (~df["is_retrieval"]).sum()
    ],
    "Avg Prompt Tokens": [
        df[df["is_retrieval"]]["prompt_tokens"].mean(),
        df[~df["is_retrieval"]]["prompt_tokens"].mean()
    ],
    "Avg Response Tokens": [
        df[df["is_retrieval"]]["response_tokens"].mean(),
        df[~df["is_retrieval"]]["response_tokens"].mean()
    ],
    "Total Tokens": [
        df[df["is_retrieval"]]["total_tokens"].sum(),
        df[~df["is_retrieval"]]["total_tokens"].sum()
    ]
}

breakdown_df = pd.DataFrame(breakdown_data)

# ========================
# SAVE OUTPUT - MULTIPLE SHEETS
# ========================
with pd.ExcelWriter(OUTPUT_FILE, engine='openpyxl') as writer:
    # Sheet 1: Detail data
    df.to_excel(writer, sheet_name='Detail Data', index=False)
    
    # Sheet 2: Summary
    summary_df.to_excel(writer, sheet_name='Summary', index=False)
    
    # Sheet 3: Breakdown
    breakdown_df.to_excel(writer, sheet_name='Breakdown', index=False)
    
    # Sheet 4: Retrieval only
    df[df["is_retrieval"]].to_excel(writer, sheet_name='Retrieval Only', index=False)
    
    # Sheet 5: Generated only
    df[~df["is_retrieval"]].to_excel(writer, sheet_name='Generated Only', index=False)

print("\n=== SUMMARY ===")
print(summary_df.to_string(index=False))
print("\n=== BREAKDOWN ===")
print(breakdown_df.to_string(index=False))
print(f"\n✅ File disimpan ke: {OUTPUT_FILE}")
