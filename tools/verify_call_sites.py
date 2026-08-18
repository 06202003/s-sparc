#!/usr/bin/env python3
"""
tools/verify_call_sites.py
Gate 2b: Static Multi-line Call-Site Token & Regex Scanner.
Audits all PHP call-sites of shared kernel functions in estrange/v2/v2/
to guarantee Signature Invariance and prevent broken caller invocations.
"""

import os
import re
import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent / "estrange" / "v2" / "v2"

IGNORE_DIRS = {
    "vendor", "PHPMailer", "bootstrap-5.3.3-dist", "chartjs", 
    "datatables", "uploads", "uploads_2025", "internal_rep_submissions",
    "internal_rep_submissions_2025", "simreports", "simreports_2025",
    "tmp_zips", "temporary"
}

# Expected signature definitions: (min_args, max_args, arg_names)
KERNEL_FUNCTIONS = {
    "setHeaderStudent": (2, 2, ["$selectedMenu", "$headerText"]),
    "setHeaderLecturer": (2, 2, ["$selectedMenu", "$headerText"]),
    "setHeaderAdmin": (2, 2, ["$selectedMenu", "$headerText"]),
    "setHeaderReport": (3, 3, ["$selectedMenu", "$submissionID", "$db"]),
    "renderSSOHeader": (0, 2, ["$activePage", "$title"]),
}

def split_php_args(arg_string: str):
    """Parses comma-separated arguments while respecting strings, quotes, and nested parens."""
    args = []
    current = []
    in_single_quote = False
    in_double_quote = False
    paren_depth = 0
    escape = False

    for char in arg_string:
        if escape:
            current.append(char)
            escape = False
            continue

        if char == "\\":
            escape = True
            current.append(char)
            continue

        if char == "'" and not in_double_quote:
            in_single_quote = not in_single_quote
            current.append(char)
        elif char == '"' and not in_single_quote:
            in_double_quote = not in_double_quote
            current.append(char)
        elif char in "([{" and not (in_single_quote or in_double_quote):
            paren_depth += 1
            current.append(char)
        elif char in ")]}" and not (in_single_quote or in_double_quote):
            paren_depth -= 1
            current.append(char)
        elif char == "," and not (in_single_quote or in_double_quote) and paren_depth == 0:
            arg = "".join(current).strip()
            if arg:
                args.append(arg)
            current = []
        else:
            current.append(char)

    last_arg = "".join(current).strip()
    if last_arg:
        args.append(last_arg)

    return args

def audit_file(file_path: Path):
    try:
        content = file_path.read_text(encoding="utf-8", errors="replace")
    except Exception as e:
        return [(0, "ERROR", f"Cannot read file: {e}")]

    rel_path = file_path.relative_to(BASE_DIR)
    results = []

    # Regex to capture multi-line function calls: functionName\s*\((.*?)\)
    for func_name, (min_args, max_args, arg_names) in KERNEL_FUNCTIONS.items():
        # Match function calls that are NOT function declarations (i.e. not preceded by 'function ')
        pattern = re.compile(rf'(?<!function\s)\b{func_name}\s*\((.*?)\)', re.DOTALL)
        for match in pattern.finditer(content):
            start_pos = match.start()
            line_no = content[:start_pos].count('\n') + 1
            raw_args = match.group(1).strip()
            
            parsed_args = split_php_args(raw_args)
            arg_count = len(parsed_args)

            if arg_count < min_args or arg_count > max_args:
                results.append((
                    rel_path,
                    line_no,
                    "INVALID_SIGNATURE",
                    f"Call to {func_name}() has {arg_count} args (expected {min_args}..{max_args}): ({raw_args})"
                ))
            else:
                results.append((
                    rel_path,
                    line_no,
                    "VALID",
                    f"Call to {func_name}({', '.join(parsed_args)}) [OK]"
                ))

    return results

def main():
    print("=== Quality Gate 2b: Static Multi-line Call-Site Scanner ===")
    print(f"Scanning directory: {BASE_DIR}\n")

    files_to_scan = []
    for root, dirs, files in os.walk(BASE_DIR):
        dirs[:] = [d for d in dirs if d not in IGNORE_DIRS]
        for f in files:
            if f.endswith(".php"):
                files_to_scan.append(Path(root) / f)

    total_call_sites = 0
    invalid_call_sites = 0
    call_site_log = []

    for file_path in sorted(files_to_scan):
        audit_results = audit_file(file_path)
        for rel_path, lno, status, msg in audit_results:
            total_call_sites += 1
            call_site_log.append((rel_path, lno, status, msg))
            if status != "VALID":
                invalid_call_sites += 1

    print(f"Found {total_call_sites} kernel call-site(s) across {len(files_to_scan)} PHP files:")
    for rel_path, lno, status, msg in call_site_log:
        status_tag = "[PASS]" if status == "VALID" else "[FAIL]"
        print(f"  {status_tag} {rel_path}:{lno} - {msg}")

    print("\n" + "=" * 60)
    if invalid_call_sites == 0:
        print(f"SUCCESS: Gate 2b passed! All {total_call_sites} call-sites match kernel signatures.")
        sys.exit(0)
    else:
        print(f"GATE 2b FAILED: Found {invalid_call_sites} invalid call-site(s).")
        sys.exit(1)

if __name__ == "__main__":
    main()
