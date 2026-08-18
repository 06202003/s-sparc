import os
import sys

sys.path.insert(0, r"c:\S-SPARC_FINAL EDIT")

print("--- 1. VERIFYING BACKEND PROMPT BOUNDS ---")
from backend.api.ai_chat import MIN_PROMPT_LENGTH, MAX_PROMPT_LENGTH
print(f"Backend Prompt Limits: MIN={MIN_PROMPT_LENGTH}, MAX={MAX_PROMPT_LENGTH}")
assert MIN_PROMPT_LENGTH == 200, f"Expected 200, got {MIN_PROMPT_LENGTH}"
assert MAX_PROMPT_LENGTH == 2000, f"Expected 2000, got {MAX_PROMPT_LENGTH}"
print("PASSED: Backend prompt bounds are strictly 200 - 2000 chars.")

print("\n--- 2. VERIFYING PROMPT REGISTRY & COMPRESSION ---")
from backend.core.prompts import PromptRegistry
dummy_rag_code = """
\"\"\"
This is a long module docstring explaining binary search.
It spans multiple lines and wastes tokens.
\"\"\"
# Import unnecessary libraries
import os
import sys

def binary_search(arr, target):
    # This is a comment inside binary search
    left = 0
    right = len(arr) - 1
    while left <= right:
        mid = (left + right) // 2
        if arr[mid] == target:
            return mid
        elif arr[mid] < target:
            left = mid + 1
        else:
            right = mid - 1
    return -1
"""
compressed = PromptRegistry.compress_context_snippet(dummy_rag_code)
print(f"Raw Code Lines: {len(dummy_rag_code.splitlines())}, Compressed Lines: {len(compressed.splitlines())}")
assert "long module docstring" not in compressed, "Docstring should be compressed out!"
assert "This is a comment" not in compressed, "Comments should be compressed out!"
assert "def binary_search" in compressed, "Core logic must be retained!"
print("PASSED: Headroom-style CodeCompressor successfully stripped non-essential tokens!")

print("\n--- 3. VERIFYING SYSTEM PROMPT CACHE ALIGNMENT ---")
harness = PromptRegistry.get_chat_harness(
    chat_history=[],
    new_query="[CONTEXT: Student Task] How to implement quicksort?",
    retrieved_context=dummy_rag_code,
    language="Python",
    mode="code"
)
print("Generated System Prompt:")
print(repr(harness[0]["content"]))
assert "[BLOOM TIER: C3-C4 APPLY & ANALYZE" in harness[0]["content"], "Bloom Tier should be present in system harness!"
assert "OUTPUT SHAPER: MAXIMUM CONCISENESS" in harness[0]["content"], "Output Shaper should be active in code mode!"
assert "binary_search" in harness[1]["content"], "Compressed context should be injected in user turn!"
print("PASSED: PromptRegistry produces deterministic, compressed chat harness.")

print("\n--- 4. VERIFYING FILE SIZES ---")
pdf_path = r"c:\S-SPARC_FINAL EDIT\UNU_AI_FOR_SDGS_2026_SUBMISSION.pdf"
md_path = r"c:\S-SPARC_FINAL EDIT\UNU_AI_FOR_SDGS_2026_SUBMISSION.md"
html_path = r"c:\S-SPARC_FINAL EDIT\scratch\UNU_AI_FOR_SDGS_2026_SUBMISSION.html"

assert os.path.exists(pdf_path), "PDF must exist!"
assert os.path.exists(md_path), "MD must exist!"
assert os.path.exists(html_path), "HTML must exist!"

pdf_size = os.path.getsize(pdf_path)
md_size = os.path.getsize(md_path)
html_size = os.path.getsize(html_path)

print(f"PDF Size : {pdf_size:,} bytes ({pdf_size/1024:.1f} KB)")
print(f"MD Size  : {md_size:,} bytes ({md_size/1024:.1f} KB)")
print(f"HTML Size: {html_size:,} bytes ({html_size/1024:.1f} KB)")

print("\nALL SYSTEM & ARCHITECTURAL VERIFICATIONS PASSED SUCCESSFULLY!")
