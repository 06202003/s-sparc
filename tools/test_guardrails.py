import sys
import os
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

from backend.services.guardrails import PromptGuardrail

test_cases = [
    # 1. The exact prompt user reported
    ('''[CONTEXT: Mata kuliah Pemrograman / Python]
adadsadsadsadadadasdasdasdadadasdaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa

[INPUT: Parameter & Tipe Data]
[OUTPUT: Kondisi Akhir & Kompleksitas]
[ERROR TRACE / KENDALA]''', False),

    # 2. Blank template
    ('''[CONTEXT: Mata kuliah Pemrograman / Python]
[INPUT: Parameter & Tipe Data]
[OUTPUT: Kondisi Akhir & Kompleksitas]
[ERROR TRACE / KENDALA]''', False),

    # 3. Repeated sssssss
    ('''[CONTEXT: Mata kuliah Pemrograman / Python]
[INPUT: Parameter & Tipe Data]
[OUTPUT: Kondisi Akhir & Kompleksitas]
[ERROR TRACE / KENDALA]
SsssssssssssssSsssssssssssssSsssssssssssss''', False),

    # 4. Valid prompt
    ('''[CONTEXT: Mata kuliah Pemrograman / Python]
Saya sedang mengerjakan fungsi rekursif deret fibonacci.
[INPUT: Parameter & Tipe Data]
n bertipe integer positif (1 <= n <= 50)
[OUTPUT: Kondisi Akhir & Kompleksitas]
Nilai fibonacci ke-n dengan kompleksitas O(N) menggunakan memoization
[ERROR TRACE / KENDALA]
Fungsi saat ini berjalan lambat O(2^N) dan browser mengalami timeout pada n=45.''', True)
]

for i, (prompt, expected) in enumerate(test_cases):
    res = PromptGuardrail.validate(prompt)
    print(f"Test {i+1}: is_valid={res['is_valid']}, expected={expected}, reason={res['reason']}")
    assert res['is_valid'] == expected, f"Test {i+1} failed!"

print("\nALL GUARDRAIL TESTS PASSED PERFECTLY!")

