import sys
import os

sys.path.insert(0, r"c:\S-SPARC_FINAL EDIT")

from backend.services.prompt_linter import PromptLinter

print("--- TESTING PROMPT LINTER & AI LITERACY SCORING ---")

# 1. Test Low Entropy / Spam Prompt
spam_prompt = "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
res_spam = PromptLinter.analyze(spam_prompt)
print(f"Spam Prompt Length: {res_spam['prompt_length']}, Entropy: {res_spam['shannon_entropy']}, Score: {res_spam['prompt_quality_score']}")
assert res_spam['shannon_entropy'] < 0.20, "Spam entropy should be very low!"
assert res_spam['prompt_quality_score'] < 0.40, "Spam prompt score should be low!"
assert "D (" in res_spam['literacy_grade'], "Spam grade should be D!"
print("PASSED: Low entropy spam correctly detected and scored low.")

# 2. Test Partial Prompt (Sub-200 chars / Incomplete)
partial_prompt = "Tolong benerin kodingan python saya yang error di bagian binary search."
res_partial = PromptLinter.analyze(partial_prompt)
print(f"Partial Prompt Length: {res_partial['prompt_length']}, CIOE Components: {res_partial['cioe_breakdown']['components_present']}, Score: {res_partial['prompt_quality_score']}")
assert res_partial['cioe_breakdown']['components_present'] < 4, "Partial prompt should miss components!"
assert len(res_partial['pedagogical_feedback']) > 0, "Pedagogical feedback should provide actionable suggestions!"
print("PASSED: Partial prompt identified missing C-I-O-E components with feedback.")

# 3. Test Full C-I-O-E Exemplary Prompt (> 200 chars)
full_cioe_prompt = """[CONTEXT: Python / Data Structures & Algorithms]
Saya sedang mengerjakan tugas pemrograman implementasi Binary Search Tree (BST) untuk mata kuliah Struktur Data.

[INPUT: Parameter & Constraints]
Diberikan input berupa array integer tidak terurut arr = [15, 10, 20, 8, 12, 17, 25] dengan panjang N <= 10^5, dan target nilai key yang dicari.

[OUTPUT: Post-conditions & Complexity]
Fungsi search_bst(root, key) harus mengembalikan pointer ke node jika ditemukan, atau None jika tidak ada, dengan target waktu O(log N).

[ERROR TRACE / BOTTLENECK]
Saat mengeksekusi fungsi pencarian pada node daun, muncul AttributeError: 'NoneType' object has no attribute 'val' pada baris 24 saat mengevaluasi root.left.val."""

res_full = PromptLinter.analyze(full_cioe_prompt)
print(f"Full C-I-O-E Prompt Length: {res_full['prompt_length']}, Entropy: {res_full['shannon_entropy']}, Score: {res_full['prompt_quality_score']}, Grade: {res_full['literacy_grade']}")
assert res_full['cioe_breakdown']['components_present'] == 4, "All 4 C-I-O-E components must be detected!"
assert res_full['prompt_quality_score'] >= 0.80, "Exemplary prompt should score >= 0.80!"
assert "A (" in res_full['literacy_grade'], "Grade should be A (Prompt Architect)!"
print("PASSED: Full C-I-O-E prompt scored A (Prompt Architect) with full components detected!")

print("\nALL PROMPT LINTER TESTS PASSED SUCCESSFULLY!")
