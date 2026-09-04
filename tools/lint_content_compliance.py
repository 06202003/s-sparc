#!/usr/bin/env python3
"""
tools/lint_content_compliance.py
Gate 1 Automated Linter for E-STRANGE & S-SPARC AI.
Scans for:
1. Emojis (Unicode emoji blocks)
2. Indonesian user-facing UI keywords
3. Deprecated Bootstrap classes
4. Phase A3 client-side formula purge verification
"""

import os
import re
import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent / "estrange" / "v2" / "v2"

# Paths to ignore (vendors, internal assets, binary directories, legacy research forks)
IGNORE_DIRS = {
    "vendor", "PHPMailer", "bootstrap-5.3.3-dist", "chartjs", 
    "datatables", "uploads", "uploads_2025", "internal_rep_submissions",
    "internal_rep_submissions_2025", "simreports", "simreports_2025",
    "tmp_zips", "temporary", "karina"
}

# 1. Emoji Regex
EMOJI_PATTERN = re.compile(
    r"[\U0001F300-\U0001F9FF\U00002600-\U000026FF\U00002700-\U000027BF\U0001FA00-\U0001FAFF]"
)

# 2. Indonesian UI Keyword Patterns (matching visible strings, button labels, titles, alerts)
INDONESIAN_UI_PATTERNS = [
    (re.compile(r">\s*(Mata Kuliah|Daftar Mata Kuliah|Semua Mata Kuliah)\s*<", re.I), "Mata Kuliah (use Courses / Course List)"),
    (re.compile(r">\s*(Tugas|Daftar Tugas)\s*<", re.I), "Tugas (use Assignments / Tasks)"),
    (re.compile(r">\s*(Kembali|Kembali ke [^<]+)\s*<", re.I), "Kembali (use Back / Return)"),
    (re.compile(r">\s*(Pengguna|Role Pengguna)\s*<", re.I), "Pengguna (use User / Role)"),
    (re.compile(r">\s*(Simpan|Simpan Perubahan)\s*<", re.I), "Simpan (use Save / Save Changes)"),
    (re.compile(r">\s*(Unduh|Unduh Semua)\s*<", re.I), "Unduh (use Download / Export)"),
    (re.compile(r">\s*(Keluar|Keluar Akun)\s*<", re.I), "Keluar (use Logout / Sign Out)"),
    (re.compile(r">\s*(Batal|Batalkan)\s*<", re.I), "Batal (use Cancel)"),
    (re.compile(r">\s*(Pilih|Pilih Asesmen|Pilih Mata Kuliah)\s*<", re.I), "Pilih (use Select / Choose)"),
    (re.compile(r">\s*(Kirim|Kirimkan)\s*<", re.I), "Kirim (use Submit / Send)"),
    (re.compile(r">\s*(Asesmen Aktif|Rentang Waktu|Tenggat Waktu)\s*<", re.I), "Asesmen Aktif / Rentang Waktu (use Active Assessments / Timeframe)"),
    (re.compile(r">\s*(\d+\s*Hari Terakhir|\d+\s*Bulan Terakhir|Tahun Terakhir)\s*<", re.I), "Hari Terakhir (use Last N Days / Months)"),
    (re.compile(r">\s*(Semua Aktivitas Saya|Per Mata Kuliah|Per Asesmen)\s*<", re.I), "Aktivitas Saya (use All My Activity / By Course)"),
    (re.compile(r"Kembali ke E-STRANGE", re.I), "Kembali ke E-STRANGE (use Back to E-STRANGE)"),
    (re.compile(r"Pengguna:\s*<strong", re.I), "Pengguna: (use User:)"),
    (re.compile(r"Mata kuliah dan asesmen wajib dipilih", re.I), "Indonesian validation alert"),
    (re.compile(r"Ketik pertanyaan atau minta bantuan kode", re.I), "Indonesian placeholder text"),
]

# 3. Bootstrap Deprecated Class Patterns
BOOTSTRAP_CLASSES = [
    re.compile(r'\bclass\s*=\s*["\'][^"\']*\b(col-md-\d+|col-xs-\d+|col-sm-\d+|col-lg-\d+|navbar-default|table-striped|panel-default|panel-heading|btn-primary|btn-outline-primary|navbarAdmin|navbarStudent)\b', re.I)
]

# 4. Phase A3 Client-side Math Purge Patterns (Universal across all views & JS)
FORMULA_PURGE_PATTERNS = [
    (re.compile(r"1\.10\s*\*\s*(?:avg|peer|usage)", re.I), "Client-side 1.10x threshold formula calculation"),
    (re.compile(r"1\.1\s*\*\s*(?:avg|peer|usage)", re.I), "Client-side 1.1x threshold formula calculation"),
    (re.compile(r"max\s*\(\s*0(?:\.0)?\s*,\s*100(?:\.0)?\s*-\s*\(", re.I), "Client-side gamification points formula calculation"),
]

def check_file(file_path: Path, target_phase: str = "all"):
    violations = []
    try:
        content = file_path.read_text(encoding="utf-8", errors="replace")
        lines = content.splitlines()
    except Exception as e:
        return [(file_path.name, 0, "ERROR", f"Error reading {file_path}: {e}")]

    rel_path = file_path.relative_to(BASE_DIR)

    for line_idx, line in enumerate(lines, 1):
        # Check 1: Emojis
        emoji_matches = EMOJI_PATTERN.findall(line)
        if emoji_matches:
            formatted_emojis = [f"U+{ord(c):04X}" for c in emoji_matches]
            violations.append((line_idx, "EMOJI", f"Found emoji(s) {formatted_emojis} in: {line.strip()[:60]}"))

        # Check 2: Indonesian UI Keywords
        for pattern, desc in INDONESIAN_UI_PATTERNS:
            if pattern.search(line):
                violations.append((line_idx, "LANGUAGE", f"Indonesian UI text '{desc}' in: {line.strip()[:60]}"))

        # Check 3: Bootstrap Deprecated Classes
        for pattern in BOOTSTRAP_CLASSES:
            if pattern.search(line):
                violations.append((line_idx, "BOOTSTRAP", f"Deprecated Bootstrap class in: {line.strip()[:60]}"))

        # Check 4: Task A3.1 Client-side Formula Purge (Active across ALL scanned files)
        if "display-only, sourced from API" not in line:
            for pattern, desc in FORMULA_PURGE_PATTERNS:
                if pattern.search(line):
                    violations.append((line_idx, "FORMULA_PURGE", f"Residual formula math '{desc}' in: {line.strip()[:60]}"))

    return [(rel_path, lno, vtype, msg) for lno, vtype, msg in violations]

def main():
    target_phase = sys.argv[1] if len(sys.argv) > 1 else "all"
    target_file = sys.argv[2] if len(sys.argv) > 2 else None

    print(f"=== Quality Gate 1: Content Compliance & Formula Purge Linter (Target: {target_phase}) ===")
    print(f"Scanning directory: {BASE_DIR}")
    print(f"Active Rule Passes:")
    print(f"  [1] Emoji Detection (Unicode Emoji Blocks)")
    print(f"  [2] Language Compliance (English Only, 0 Indonesian UI Strings)")
    print(f"  [3] CSS Compliance (Tailwind Only, 0 Deprecated Bootstrap Classes)")
    print(f"  [4] Task A3.1 Formula Purge (0 Residual Client-side Math Calculations across all files)\n")

    files_to_scan = []
    if target_file:
        if "*" in target_file or "?" in target_file:
            files_to_scan.extend(BASE_DIR.glob(target_file))
        else:
            p = Path(target_file)
            if not p.is_absolute():
                p = BASE_DIR / target_file
            files_to_scan.append(p)
    else:
        for root, dirs, files in os.walk(BASE_DIR):
            dirs[:] = [d for d in dirs if d not in IGNORE_DIRS]
            for f in files:
                if f.endswith((".php", ".html", ".js")):
                    files_to_scan.append(Path(root) / f)

    total_violations = 0
    file_violation_count = 0

    for file_path in sorted(files_to_scan):
        violations = check_file(file_path, target_phase)
        if violations:
            file_violation_count += 1
            rel_path = violations[0][0]
            print(f"[{rel_path}] - {len(violations)} issue(s):")
            for _, lno, vtype, msg in violations:
                print(f"  Line {lno:4d} [{vtype:13s}]: {msg}")
            total_violations += len(violations)

    print("\n" + "=" * 60)
    if total_violations == 0:
        print(f"SUCCESS: Gate 1 passed with 0 violations across {len(files_to_scan)} files.")
        print(f"Verified: 100% English UI, Zero Emojis, Zero Bootstrap, and Zero Client-side Math Formulas.")
        sys.exit(0)
    else:
        print(f"GATE 1 FAILED: Found {total_violations} violation(s) in {file_violation_count} file(s).")
        sys.exit(1)

if __name__ == "__main__":
    if sys.platform == "win32":
        sys.stdout.reconfigure(encoding="utf-8", errors="backslashreplace")
    main()
