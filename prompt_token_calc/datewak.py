import pandas as pd

# Load data
df = pd.read_excel("gpt_jobs.xlsx")

# Pastikan datetime
df["created_at"] = pd.to_datetime(df["created_at"])

# Tabel range week
week_ranges = [
    ("W1",  "2026-02-16", "2026-02-22"),
    ("W2",  "2026-02-23", "2026-03-01"),
    ("W3",  "2026-03-02", "2026-03-08"),
    ("W4",  "2026-03-09", "2026-03-15"),
    ("W5",  "2026-03-16", "2026-03-22"),
    ("W6",  "2026-03-23", "2026-03-29"),
    ("W7",  "2026-03-30", "2026-04-05"),
    ("W8",  "2026-04-06", "2026-04-12"),
    ("W9",  "2026-04-13", "2026-04-19"),
    ("W10", "2026-04-20", "2026-04-26"),
    ("W11", "2026-04-27", "2026-05-03"),
    ("W12", "2026-05-04", "2026-05-10"),
    ("W13", "2026-05-11", "2026-05-17"),
    ("W14", "2026-05-18", "2026-05-24"),
    ("W15", "2026-05-25", "2026-05-31"),
    ("W16", "2026-06-01", "2026-06-07"),

    # tambahan khusus
    ("W116", "2026-02-24 11:21", "2026-06-24 11:21"),
    ("T03",  "2026-03-09", "2026-03-15"),
]

# Convert ke DataFrame
week_df = pd.DataFrame(week_ranges, columns=["week", "start", "end"])
week_df["start"] = pd.to_datetime(week_df["start"], format="mixed")
week_df["end"] = pd.to_datetime(week_df["end"], format="mixed")

# Function mapping
def get_week(dt):
    match = week_df[(dt >= week_df["start"]) & (dt <= week_df["end"])]
    if not match.empty:
        return match.iloc[0]["week"]  # ambil yang pertama
    return None

# Apply ke data
df["week"] = df["created_at"].apply(get_week)

# Save
df.to_excel("gpt_jobs_output_with_week.xlsx", index=False)

print("DONE")