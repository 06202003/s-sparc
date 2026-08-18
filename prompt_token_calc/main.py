import pandas as pd
import tiktoken

INPUT_FILE = "retrieval_only.xlsx"
OUTPUT_FILE = "retrieval_only_output_with_tokens.xlsx"
MODEL_NAME = "gpt-5.2"

# ========================
# LOAD DATA
# ========================
df = pd.read_excel(INPUT_FILE)

print("=== HEAD DATA ===")
print(df.head())
print("\nKolom:", df.columns.tolist())

# ========================
# VALIDASI KOLOM
# ========================
required_cols = ["prompt", "raw_response"]
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
# DETEKSI RETRIEVAL
# ========================
def is_retrieval(text):
    if pd.isna(text) or not isinstance(text, str):
        return False
    return text.strip().startswith("[RETRIEVAL]")

df["is_retrieval"] = df["raw_response"].apply(is_retrieval)

# ========================
# HITUNG TOKEN
# ========================
def count_tokens(text):
    if pd.isna(text):
        return 0
    return len(enc.encode(str(text)))

# Prompt tokens
df["prompt_tokens"] = df["prompt"].apply(count_tokens)

# Response tokens
df["response_tokens"] = df["raw_response"].apply(count_tokens)

# Total tokens
df["total_tokens"] = df["prompt_tokens"] + df["response_tokens"]

# ========================
# (OPSIONAL) METRIK TAMBAHAN
# ========================
df["prompt_char_length"] = df["prompt"].str.len()
df["response_char_length"] = df["raw_response"].str.len()

# ========================
# SAVE OUTPUT
# ========================
df.to_excel(OUTPUT_FILE, index=False)

print("\n=== DONE ===")
print(f"File disimpan ke: {OUTPUT_FILE}")