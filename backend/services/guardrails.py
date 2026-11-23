import re
import math
import logging
from typing import Dict, Any, Tuple

logger = logging.getLogger("ssparc.guardrails")

class PromptGuardrail:
    """
    S-SPARC Cognitive & AI Safety Guardrails:
    1. Anti-Gibberish & Keyboard Smash Detection (character entropy, repeating n-grams, consonant clusters).
    2. Hollow / Empty C-I-O-E Template Detection (boilerplate frames with blank or placeholder bodies).
    3. RAG Query Sanitization (stripping boilerplate to prevent artificial high cosine similarity).
    4. Knowledge Base Ingestion Filter (prevents poisoned, hollow, or fallback responses from entering code_embeddings).
    """

    # Static template tags commonly present in S-SPARC UI prompts
    TEMPLATE_PATTERNS = [
        r'\[CONTEXT(?::\s*[^\]]*)?\]',
        r'\[INPUT(?::\s*[^\]]*)?\]',
        r'\[OUTPUT(?::\s*[^\]]*)?\]',
        r'\[ERROR(?::\s*[^\]]*|\s+TRACE\s*/\s*KENDALA)?\]',
        r'\[LANG:[^\]]*\]',
        r'\[MODE:[^\]]*\]',
        r'\[ASSESSMENT_ID:[^\]]*\]',
        r'\[FORCE_GPT:[^\]]*\]',
        r'\[AUTO_FALLBACK:[^\]]*\]'
    ]

    # Standard Indonesian & English programming keywords to recognize valid technical context
    PROGRAMMING_KEYWORDS = {
        "python", "java", "c++", "cpp", "c#", "csharp", "javascript", "typescript", "php", "sql", "html", "css",
        "def", "function", "class", "return", "import", "public", "private", "void", "static", "int", "float",
        "string", "str", "bool", "boolean", "array", "list", "dict", "map", "set", "tuple", "vector",
        "loop", "for", "while", "if", "else", "elif", "switch", "case", "break", "continue", "try", "except", "catch",
        "algorithm", "algoritma", "rekursif", "recursion", "recursive", "fibonacci", "sorting", "sort", "search",
        "searching", "binary", "linear", "hash", "tree", "graph", "node", "edge", "stack", "queue", "pointer",
        "sliding", "window", "two", "pointers", "dp", "dynamic", "programming", "memoization", "prefix", "sum",
        "kompleksitas", "complexity", "time", "space", "waktu", "ruang", "big-o", "o(n)", "o(1)", "o(log", "o(n^2)",
        "parameter", "input", "output", "error", "bug", "traceback", "indexerror", "typeerror", "valueerror",
        "kode", "code", "fungsi", "method", "mengembalikan", "hasil", "kendala", "masalah", "mengapa", "bagaimana",
        "solusi", "perbaikan", "bantu", "buatkan", "tuliskan", "optimasi", "penjelasan", "jelaskan", "soal", "tugas"
    }

    VOWELS = set('aeiouAEIOU')

    @classmethod
    def strip_boilerplate(cls, text: str) -> str:
        """
        Removes all C-I-O-E header tags, returning the actual user-written content.
        """
        if not text:
            return ""
        clean = text
        for pat in cls.TEMPLATE_PATTERNS:
            clean = re.sub(pat, ' ', clean, flags=re.IGNORECASE)
        # Normalize whitespace
        return re.sub(r'\s+', ' ', clean).strip()

    @classmethod
    def extract_cioe_sections(cls, text: str) -> Dict[str, str]:
        """
        Parses text into individual C-I-O-E section contents.
        """
        sections = {"context": "", "input": "", "output": "", "error": ""}
        if not text:
            return sections

        # Pattern to capture each section until the next section header or end
        c_m = re.search(r'\[CONTEXT(?::\s*[^\]]*)?\]([\s\S]*?)(?=\[(?:INPUT|OUTPUT|ERROR)|$)', text, re.I)
        i_m = re.search(r'\[INPUT(?::\s*[^\]]*)?\]([\s\S]*?)(?=\[(?:CONTEXT|OUTPUT|ERROR)|$)', text, re.I)
        o_m = re.search(r'\[OUTPUT(?::\s*[^\]]*)?\]([\s\S]*?)(?=\[(?:CONTEXT|INPUT|ERROR)|$)', text, re.I)
        e_m = re.search(r'\[ERROR[^\]]*\]([\s\S]*?)(?=\[(?:CONTEXT|INPUT|OUTPUT)|$)', text, re.I)

        if c_m:
            sections["context"] = c_m.group(1).strip()
        if i_m:
            sections["input"] = i_m.group(1).strip()
        if o_m:
            sections["output"] = o_m.group(1).strip()
        if e_m:
            sections["error"] = e_m.group(1).strip()

        return sections

    @classmethod
    def is_hollow_template(cls, text: str) -> Tuple[bool, str]:
        """
        Detects if user simply submitted the template brackets with empty or trivial body.
        """
        body = cls.strip_boilerplate(text)
        # Count alphanumeric characters in body
        alnum_chars = [c for c in body if c.isalnum()]
        if len(alnum_chars) < 12:
            return True, "Template C-I-O-E kosong atau isi deskripsi masalah kurang dari 12 karakter."
        return False, ""

    @classmethod
    def is_gibberish_or_spam(cls, text: str) -> Tuple[bool, str]:
        """
        Detects repeating characters, repeated n-grams, keystroke smash, and low-entropy spam.
        """
        body = cls.strip_boilerplate(text)
        if not body:
            return True, "Konten teks kosong setelah header template dihapus."

        # 1. Check for single character repetition: 5+ consecutive identical characters (e.g. 'aaaaa', 'zzzzz')
        if re.search(r'(.)\1{4,}', body):
            match = re.search(r'(.)\1{4,}', body).group(0)
            return True, f"Terdeteksi pengulangan karakter berulang secara tidak wajar ('{match[:8]}...')."

        # 2. Check for repeating 2 to 5 character pattern: 4+ consecutive repetitions (e.g. 'adadadad', 'asdasdasd')
        if re.search(r'(.{2,5})\1{3,}', body):
            match = re.search(r'(.{2,5})\1{3,}', body).group(0)
            return True, f"Terdeteksi pola karakter berulang acak ('{match[:12]}...')."

        # 3. Check character diversity: If text length > 25 but distinct alphanumeric characters < 5
        alnum_chars = [c.lower() for c in body if c.isalnum()]
        if len(alnum_chars) > 25:
            unique_chars = len(set(alnum_chars))
            if unique_chars < 5:
                return True, f"Variasi karakter terlalu rendah ({unique_chars} karakter unik dari {len(alnum_chars)} huruf)."

        # 4. Check for consonant cluster / keystroke smash (words >= 7 characters with 0 vowels or vowel ratio < 0.12)
        words = re.findall(r'[a-zA-Z]{6,}', body)
        for w in words:
            v_count = sum(1 for c in w if c in cls.VOWELS)
            v_ratio = v_count / len(w)
            if len(w) >= 7 and (v_count == 0 or v_ratio < 0.12):
                return True, f"Terdeteksi rangkaian kata acak tanpa susunan vokal wajar ('{w[:12]}...')."

        # 5. Check if query body contains at least one meaningful word
        tokens = [t.lower() for t in re.findall(r'[a-zA-Z0-9_#+]+', body) if len(t) >= 2]
        if len(tokens) >= 5:
            recognized = sum(1 for t in tokens if t in cls.PROGRAMMING_KEYWORDS)
            # If prompt has >= 5 words but 0 recognized programming/domain terms and low dictionary matches
            if recognized == 0 and len(tokens) > 8:
                # Calculate Shannon entropy of the body
                freqs = {}
                for c in body.lower():
                    freqs[c] = freqs.get(c, 0) + 1
                entropy = 0.0
                for count in freqs.values():
                    p = count / len(body)
                    entropy -= p * math.log2(p)
                if entropy < 2.5:
                    return True, "Entropi informasi terlalu rendah untuk sebuah pertanyaan pemrograman yang valid."

        return False, ""

    @classmethod
    def validate(cls, raw_prompt: str) -> Dict[str, Any]:
        """
        Main validation entry point for inbound user queries.
        Returns validation status, educational guide if rejected, and cleaned body.
        """
        if not raw_prompt or not raw_prompt.strip():
            return {
                "is_valid": False,
                "reason": "Prompt kosong.",
                "status": "REJECTED",
                "educational_message": cls.build_educational_rejection("Prompt tidak boleh kosong.")
            }

        prompt_clean = raw_prompt.strip()

        # Check hollow template
        is_hollow, hollow_reason = cls.is_hollow_template(prompt_clean)
        if is_hollow:
            return {
                "is_valid": False,
                "reason": hollow_reason,
                "status": "REJECTED",
                "educational_message": cls.build_educational_rejection(hollow_reason)
            }

        # Check gibberish / spam
        is_gibberish, gibberish_reason = cls.is_gibberish_or_spam(prompt_clean)
        if is_gibberish:
            return {
                "is_valid": False,
                "reason": gibberish_reason,
                "status": "REJECTED",
                "educational_message": cls.build_educational_rejection(gibberish_reason)
            }

        return {
            "is_valid": True,
            "reason": "Valid academic programming query.",
            "status": "APPROVED",
            "educational_message": "",
            "cleaned_body": cls.strip_boilerplate(prompt_clean)
        }

    @classmethod
    def sanitize_for_rag(cls, query: str) -> str:
        """
        Sanitizes query before passing to Vector Embeddings / RAG search.
        Strips repetitive boilerplate tags so SentenceTransformers computes similarity
        based on the ACTUAL programming problem rather than the shared template tags.
        """
        body = cls.strip_boilerplate(query)
        if len(body) >= 15:
            return body
        return query.strip()

    @classmethod
    def is_safe_for_knowledge_ingestion(cls, prompt: str, code: str) -> bool:
        """
        Strict quality gate before inserting new items into code_embeddings:
        - Prompt must pass all quality guardrails.
        - Code must be genuine runnable code (not an error, alert, or fallback notice).
        """
        if not prompt or not code:
            return False

        # 1. Prompt must be valid
        val = cls.validate(prompt)
        if not val["is_valid"]:
            logger.info(f"[INGESTION GATE] Rejected auto-ingestion: prompt invalid ({val['reason']})")
            return False

        code_clean = code.strip()
        # 2. Code length check
        if len(code_clean) < 45:
            return False

        # 3. Reject fallback notices or guardrail messages
        reject_markers = [
            "### ⚠️",
            "S-SPARC AI Guardrail",
            "S-SPARC AI Analysis — Fibonacci",
            "Status Eksekusi:",
            "Solution processing completed",
            "Layanan AI lokal/cloud saat ini sedang",
            "Input Quality Guardrail"
        ]
        if any(marker in code_clean for marker in reject_markers):
            logger.info("[INGESTION GATE] Rejected auto-ingestion: code contains fallback/guardrail text.")
            return False

        # 4. Code must contain programming structure
        code_markers = [
            "def ", "class ", "return", "for ", "while ", "import ", "function ",
            "public ", "private ", "void ", "int ", "let ", "const ", "var "
        ]
        marker_count = sum(1 for m in code_markers if m in code_clean)
        if marker_count < 2:
            logger.info("[INGESTION GATE] Rejected auto-ingestion: code lacks standard programming structures.")
            return False

        return True

    @classmethod
    def build_educational_rejection(cls, specific_reason: str) -> str:
        """
        Generates constructive, pedagogical feedback in Markdown when a prompt violates guardrails.
        """
        return f"""### ⚠️ S-SPARC AI Guardrail — Kualitas Prompt Belum Memenuhi Standar

Prompt yang Anda kirimkan tidak dapat diproses karena:
> **Kendala:** {specific_reason}

---

#### 💡 Mengapa S-SPARC Memeriksa Kualitas Prompt?
S-SPARC dirancang untuk melatih **Literasi AI & Kemandirian Kognitif (*Prompt Engineering*)**. Pengiriman teks acak (*gibberish*), template kosong, atau karakter berulang tidak dapat dianalisis oleh *Semantic Search Engine* dan dapat menimbulkan pemborosan komputasi (*GPU energy waste*).

#### 📝 Panduan Mengisi Protokol C-I-O-E yang Benar:
1. **[CONTEXT]**: Jelaskan materi kuliah dan algoritma yang sedang dipelajari (misal: *Dynamic Programming*, *Array*, *Binary Search*).
2. **[INPUT]**: Tuliskan tipe data input, parameter fungsi, dan contoh kasus uji (*test case*).
3. **[OUTPUT]**: Jelaskan hasil akhir yang diharapkan serta target kompleksitas waktu/ruang (misal: $O(N)$ atau $O(\\log N)$).
4. **[ERROR TRACE / KENDALA]**: Cantumkan baris kode Anda yang mengalami error atau penjelasan logika di mana Anda merasa buntu.

*Silakan lengkapi prompt Anda dengan pertanyaan atau kode yang spesifik lalu kirimkan kembali.*"""
