import sys
import os

sys.path.insert(0, r"c:\S-SPARC_FINAL EDIT")

from backend.services.prompt_linter import PromptLinter
from backend.services.learning_analytics import LearningAnalyticsService

print("--- TESTING EDUCATIONAL ANALYTICS SERVICE ---")

# 1. Simulate logging multiple student interaction events
prompts = [
    # Student 1: High quality prompt (C-I-O-E)
    """[CONTEXT: Python Dynamic Programming]
Mengerjakan tugas 0/1 Knapsack problem pada mata kuliah Desain Analisis Algoritma.
[INPUT: Parameters]
Kapasitas W = 50, bobot wt = [10, 20, 30], nilai val = [60, 100, 120], n = 3.
[OUTPUT: Complexity]
Mengembalikan nilai maksimum profit yang dapat dicapai dengan kompleksitas waktu O(N*W).
[ERROR TRACE]
Kode rekursif saya mengalami RecursionError saat W = 1000. Mohon bimbingan konsep memoization tabel 2D.""",
    
    # Student 2: Conceptual summary request
    """[CONTEXT: Graph Algorithms]
Saya sedang belajar perbedaan antara algoritma Dijkstra dan Bellman-Ford.
[INPUT: Graph Model]
Diberikan graf berbobot dengan kemungkinan edge bernilai negatif.
[OUTPUT: Conceptual Explanation]
Jelaskan kapan Dijkstra gagal dan bagaimana Bellman-Ford mendeteksi negative cycle.
[ERROR / PERTANYAAN]
Mengapa algoritma greedy Dijkstra tidak bisa memproses bobot negatif?""",

    # Student 3: Cache Hit Query
    """[CONTEXT: Python / Data Structures]
Saya membutuhkan fungsi binary search untuk mencari elemen target dalam array terurut arr.
[INPUT: Parameter]
Array integer arr berukuran N dan integer x.
[OUTPUT: Index]
Return index x jika ditemukan, atau -1 jika tidak ada dalam waktu O(log N).
[ERROR]
Edge case saat array kosong len(arr) == 0 belum ditangani dengan benar."""
]

for idx, p in enumerate(prompts):
    lint = PromptLinter.analyze(p)
    mode = "summary" if idx == 1 else "code"
    is_fast_path = (idx == 2)
    tokens = 0 if is_fast_path else 350
    
    LearningAnalyticsService.record_learning_event(
        session_id=f"test_session_{idx}",
        user_id="student_tester_01",
        prompt_analysis=lint,
        bloom_mode=mode,
        is_fast_path=is_fast_path,
        tokens_consumed=tokens,
        latency_ms=45.0 if is_fast_path else 480.0,
        sustainability_telemetry={"energy_wh": 0.0 if is_fast_path else 0.12, "carbon_g_co2e": 0.0 if is_fast_path else 0.046, "water_ml": 0.0 if is_fast_path else 0.52}
    )

# 2. Test Student Profile Retrieval
profile = LearningAnalyticsService.get_student_profile("student_tester_01")
print("\nGenerated Student Profile:")
print(f"User ID: {profile['user_id']}")
print(f"Total Prompts: {profile['total_prompts_submitted']}")
print(f"Avg Prompt Quality: {profile['average_prompt_quality']}")
print(f"Avg C-I-O-E Score: {profile['average_cioe_score']}")
print(f"Literacy Level: {profile['literacy_level']}")
print(f"Independence Index: {profile['cognitive_independence_index']}")
print(f"Earned Badges: {profile['badges']}")

assert profile['total_prompts_submitted'] == 3
assert profile['average_prompt_quality'] >= 0.80
assert "C-I-O-E Protocol Master" in profile['badges']
print("PASSED: Student AI Literacy profile correctly generated.")

# 3. Test Faculty Aggregated Analytics
summary = LearningAnalyticsService.get_class_analytics_summary()
print("\nGenerated Faculty Analytics Summary:")
print(f"Total Interactions: {summary['total_student_interactions']}")
print(f"C-I-O-E Adherence: {summary['average_cioe_adherence']}")
print(f"0-Token Fast-Path Ratio: {summary['zero_token_fast_path_ratio']}")
print(f"Bloom Distribution: {summary['bloom_mode_distribution']}")
print(f"Token Savings: {summary['estimated_cloud_token_savings']}")
print(f"Empirical Quote: {summary['empirical_evidence_summary']}")

assert summary['total_student_interactions'] >= 3
assert summary['bloom_mode_distribution']['summary'] >= 1
print("PASSED: Faculty analytics aggregated empirical data correctly.")

print("\nALL EDUCATIONAL ANALYTICS TESTS PASSED SUCCESSFULLY!")
