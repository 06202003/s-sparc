import math
import re
from typing import Dict, Any, List

class PromptLinter:
    """
    Evaluates prompt information density, technical vocabulary,
    Shannon entropy, and C-I-O-E protocol completeness.
    """

    # Keyword indicators for C-I-O-E components
    CONTEXT_KEYWORDS = [
        "python", "java", "c++", "cpp", "javascript", "php", "sql", "framework",
        "library", "react", "fastapi", "django", "laravel", "algorithm", "data structure",
        "graph", "tree", "array", "linked list", "hash table", "matrix", "recursion",
        "dynamic programming", "sorting", "searching", "backend", "frontend", "api"
    ]

    INPUT_KEYWORDS = [
        "input", "given", "parameter", "arguments", "precondition", "array of",
        "list of", "integer", "string", "matrix size", "n =", "k =", "range",
        "constraints", "positive", "non-empty", "sorted", "graph with"
    ]

    OUTPUT_KEYWORDS = [
        "output", "return", "expected", "postcondition", "complexity", "time complexity",
        "space complexity", "o(n)", "o(log n)", "o(1)", "o(n^2)", "o(v+e)", "result",
        "boolean", "index of", "modified array", "maximum", "minimum", "sum of"
    ]

    ERROR_KEYWORDS = [
        "error", "bug", "traceback", "exception", "failed", "indexerror", "typeerror",
        "valueerror", "keyerror", "recursionerror", "nullpointer", "segfault",
        "stack trace", "infinite loop", "wrong output", "expected", "but got",
        "line", "assertionerror", "time limit exceeded", "memory limit"
    ]

    TECHNICAL_PATTERNS = [
        r'\bdef\s+\w+\s*\(', r'\bclass\s+\w+', r'\[.*?\]', r'\{.*?\}',
        r'O\([1nN\^log\s\+\*]+\)', r'==|!=|<=|>=|->|=>', r'None|True|False',
        r'\bint\b|\bstr\b|\bfloat\b|\bbool\b|\blist\b|\bdict\b|\bset\b'
    ]

    @staticmethod
    def calculate_shannon_entropy(text: str) -> float:
        """
        Calculates normalized Shannon entropy of the prompt character distribution.
        Low entropy indicates repetitive/spam characters (e.g. 'aaaaa...').
        """
        if not text:
            return 0.0
        
        freqs: Dict[str, int] = {}
        for char in text.lower():
            freqs[char] = freqs.get(char, 0) + 1
            
        length = len(text)
        entropy = 0.0
        for count in freqs.values():
            p = count / length
            entropy -= p * math.log2(p)
            
        # Normalize against theoretical max entropy for charset size (approx ~4.5 for alphanumeric text)
        normalized = min(1.0, entropy / 4.5)
        return round(normalized, 3)

    @classmethod
    def analyze(cls, prompt: str) -> Dict[str, Any]:
        """
        Performs a full multi-dimensional AI Literacy analysis of the prompt.
        """
        clean_text = prompt.strip()
        length = len(clean_text)
        lower_text = clean_text.lower()

        # 1. Component Detection (C-I-O-E)
        has_context = any(kw in lower_text for kw in cls.CONTEXT_KEYWORDS) or bool(re.search(r'\[CONTEXT|in\s+\w+|using\s+\w+', lower_text))
        has_input = any(kw in lower_text for kw in cls.INPUT_KEYWORDS) or bool(re.search(r'\[INPUT|given\s+|parameters?|args', lower_text))
        has_output = any(kw in lower_text for kw in cls.OUTPUT_KEYWORDS) or bool(re.search(r'\[OUTPUT|returns?|complexity|o\(', lower_text))
        has_error = any(kw in lower_text for kw in cls.ERROR_KEYWORDS) or bool(re.search(r'\[ERROR|error:|line\s+\d+|exception', lower_text))

        # Technical pattern density
        tech_matches = sum(len(re.findall(pat, clean_text, re.IGNORECASE)) for pat in cls.TECHNICAL_PATTERNS)
        tech_density = min(1.0, tech_matches / 4.0)

        # Shannon Entropy
        entropy = cls.calculate_shannon_entropy(clean_text)

        # Calculate C-I-O-E score (0.0 to 1.0)
        cioe_count = sum([has_context, has_input, has_output, has_error])
        cioe_score = round(cioe_count / 4.0, 2)

        # Length factor (optimal between 200 and 1500 chars)
        if length < 200:
            length_score = round(length / 200.0, 2)
        elif length <= 1500:
            length_score = 1.0
        else:
            length_score = max(0.6, round(1.0 - (length - 1500) / 2000.0, 2))

        # Overall Information Density / Literacy Score (S_prompt)
        # Weights: 40% C-I-O-E completeness, 25% Shannon Entropy, 20% Technical Density, 15% Length
        s_prompt = round(
            (0.40 * cioe_score) +
            (0.25 * entropy) +
            (0.20 * tech_density) +
            (0.15 * length_score),
            2
        )

        # Rating tier
        if s_prompt >= 0.80:
            literacy_grade = "A (Prompt Architect)"
        elif s_prompt >= 0.60:
            literacy_grade = "B (Structured Prompter)"
        elif s_prompt >= 0.40:
            literacy_grade = "C (Developing Prompter)"
        else:
            literacy_grade = "D (Novice / Needs Decomposition)"

        # Constructive Pedagogical Feedback
        feedback_items: List[str] = []
        if not has_context:
            feedback_items.append("Tambahkan konteks bahasa pemrograman, framework, atau topik algoritma yang sedang dipelajari.")
        if not has_input:
            feedback_items.append("Sebutkan tipe data input, struktur data awal, atau batasan pre-kondisi (misal: array terurut, nilai n <= 10^5).")
        if not has_output:
            feedback_items.append("Jelaskan output yang diharapkan, kondisi akhir (post-condition), atau target kompleksitas waktu O(N).")
        if not has_error and ("error" in lower_text or "bug" in lower_text or "fix" in lower_text or "salah" in lower_text):
            feedback_items.append("Sertakan pesan error compiler, traceback baris kode, atau selisih antara hasil aktual dan hasil yang diharapkan.")
        if entropy < 0.60:
            feedback_items.append("Variasi kata terlalu rendah. Hindari pengulangan karakter atau teks pengisi.")
        if length < 200:
            feedback_items.append(f"Panjang prompt saat ini ({length} karakter) belum memenuhi batas minimum 200 karakter untuk dekomposisi masalah.")

        if not feedback_items:
            feedback_items.append("Struktur prompt sangat baik! Memenuhi standar C-I-O-E dan spesifikasi teknis tingkat tinggi.")

        return {
            "prompt_length": length,
            "shannon_entropy": entropy,
            "technical_token_density": round(tech_density, 2),
            "cioe_breakdown": {
                "has_context": has_context,
                "has_input": has_input,
                "has_output": has_output,
                "has_error_trace": has_error,
                "components_present": cioe_count,
                "cioe_score": cioe_score
            },
            "prompt_quality_score": s_prompt,
            "literacy_grade": literacy_grade,
            "pedagogical_feedback": feedback_items
        }
