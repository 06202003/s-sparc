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

    # Specific Technical / Programming Terms ONLY (used for domain validation)
    PROGRAMMING_KEYWORDS = {
        # Languages & Environments
        "python", "java", "c++", "cpp", "c#", "csharp", "javascript", "js", "typescript", "ts", "php", "sql", "html", "css",
        "golang", "rust", "kotlin", "swift", "r", "matlab", "ruby", "perl", "vscode", "terminal", "bash", "shell", "git",
        "pandas", "numpy", "library", "framework",
        # Syntax & Programming Concepts
        "def", "function", "class", "return", "import", "public", "private", "protected", "void", "static", "int", "float",
        "double", "char", "string", "str", "bool", "boolean", "array", "list", "dict", "dictionary", "map", "set", "tuple",
        "vector", "matrix", "matriks", "tensor", "struct", "enum", "const", "let", "var", "val", "file", "csv", "json",
        "io", "input", "output", "read", "write", "object", "objek", "oop", "pewarisan", "inheritance", "polymorphism",
        "encapsulation", "enkapsulasi", "abstraction", "abstraksi", "interface", "method", "prosedur",
        # Flow & Control
        "loop", "looping", "for", "while", "do", "if", "else", "elif", "switch", "case", "break", "continue", "pass",
        "try", "except", "catch", "finally", "throw", "raise", "yield", "perulangan", "percabangan", "kondisi",
        # Memory & Pointers
        "pointer", "reference", "ref", "value", "dereference", "memory", "memori", "alokasi", "heap", "stack",
        "garbage", "collector", "leak", "segfault", "segmentation", "overflow", "underflow", "buffer",
        # Algorithms & DSA
        "algorithm", "algoritma", "rekursi", "rekursif", "recursion", "recursive", "fibonacci", "sorting", "sort", "search",
        "searching", "binary", "linear", "hash", "hashing", "tree", "pohon", "traversal", "preorder", "inorder", "postorder",
        "graph", "graf", "node", "edge", "queue", "antrean", "antrian", "tumpukan", "linked", "faktorial", "prima", "deret",
        "fpb", "kpk", "knapsack", "greedy", "dp", "dynamic", "programming", "memoization", "backtracking", "bfs", "dfs",
        "dijkstra", "merge", "quick", "bubble", "selection", "insertion", "heap", "sliding", "window", "two", "pointers",
        # Complexity & Analysis
        "kompleksitas", "complexity", "time", "space", "waktu", "ruang", "big-o", "o(n)", "o(1)", "o(log", "o(n^2)",
        "efisiensi", "runtime", "benchmark", "overhead", "log",
        # Errors & Debugging
        "error", "bug", "traceback", "indexerror", "typeerror", "valueerror", "recursionerror", "syntaxerror",
        "zerodivisionerror", "nullpointer", "exception", "freeze", "hang", "debugging", "debug", "koding", "coding", "code",
        "kode", "program", "programmer", "sintaks", "syntax", "kompilasi", "compile", "eksekusi",
        # Academic & University
        "praktikum", "modul", "tugas", "soal", "ujian", "asisten", "lab", "dosen", "pseudocode", "flowchart", "komputer",
        "informatika", "variabel", "parameter", "argumen", "tipe", "data", "database", "kendala", "materi"
    }

    CODE_SYNTAX_PATTERNS = [
        r'\bdef\s+\w+\s*\(', r'\bclass\s+\w+', r'\[.*?\]', r'\{.*?\}',
        r'==|!=|<=|>=|\+=|-=|\*=|/=|->|=>', r'None|True|False',
        r'System\.out|console\.log|printf|cout\s*<<|cin\s*>>',
        r';\s*$', r'//.*', r'/\*.*?\*/', r'#.*'
    ]

    MEME_SLANG_BRAINROT_PATTERNS = [
        r'\b(?:rizz|gyatt|skibidi|sigma|mewing|fanum|kai\s+cenat|baby\s+shark|ohio|brainrot|chud)\b',
        r'\b(?:dungtak|taraktak|taratak|sakarakeding|dungding|dumpak|dungking|takdung)\b',
        r'\b(?:twinkle\s+twinkle|little\s+star|little\s+stars)\b',
        r'\b(?:ea\s+bro|anjay|anjir|wkwk|awkoakwok|cie\s+cie|xixixi|hahaha)\b'
    ]

    CURHAT_PATTERNS = [
        r'\bcurhat\b',
        r'\bgalau\b',
        r'\bpusing\s+(?:hidup|banget|mau\s+ngapain)\b',
        r'\bmasa\s+depan\b',
        r'\bkerjaan\s+gini\s+gini\b',
        r'\buang\s+segini\s+gini\b',
        r'\bhidup\s+(?:gua|gue|gw|ku|saya)\b',
        r'\b(?:perkembangan\s+hidup|hidup.*stuck)\b',
        r'\b(?:putus\s+cinta|patah\s+hati|masalah\s+pacar)\b',
        r'\b(?:capek|lelah)\s+(?:banget\s+)?(?:hidup|kuliah|jalanin)\b',
        r'\b(?:pengen|mau)\s+nyerah\b',
        r'\bga\s+ada\s+perkembangan\b',
        r'\bgak\s+ada\s+perkembangan\b',
        r'\btidak\s+ada\s+perkembangan\b',
        r'\b(?:stuck|buntu)\s+(?:hidup|karir|karier|finansial)\b'
    ]

    OFF_TOPIC_PATTERNS = [
        r'\b(?:resep|memasak|masak|bumbu|makanan|minuman|kuliner|rendang|nasi\s+goreng)\b',
        r'\b(?:presiden|menteri|partai|pemilu|politik|pilkada|dpr)\b',
        r'\b(?:lagu|lirik|penyanyi|artis|film|sinetron|drakor|anime|manga)\b',
        r'\b(?:zodiak|ramalan|horoskop|shio|cuaca|hujan|gempa)\b',
        r'\b(?:lelucon|jokes|lawak|tebak-tebakan|puisi|pantun|cerpen)\b',
        r'^(?:halo|hai|hey|hi|pagi|siang|sore|malam|test|tes)\s*(?:bro|min|admin|ai|bang)?[\s\!\?\.]*$',
        r'\b(?:kamu\s+siapa|siapa\s+(?:kamu|namamu)|siapa\s+yang\s+membuatmu)\b'
    ]

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

        # 5. Check for onomatopoeia sound effects / internet slang / brainrot memes
        body_lower = body.lower()
        words = set(re.findall(r'[a-zA-Z0-9_+]+', body_lower))
        prog_matches = words.intersection(cls.PROGRAMMING_KEYWORDS)
        if len(prog_matches) == 0:
            for pat in cls.MEME_SLANG_BRAINROT_PATTERNS:
                if re.search(pat, body_lower):
                    return True, "Terdeteksi teks berupa kata tiruan bunyi (onomatope), bahasa gaul acak (slang/meme), atau konten non-edukatif."

        # 6. Check if query body contains recognized CS domain terms or code syntax
        tokens = [t.lower() for t in re.findall(r'[a-zA-Z0-9_#+]+', body) if len(t) >= 2]
        if len(tokens) >= 5:
            recognized = sum(1 for t in tokens if t in cls.PROGRAMMING_KEYWORDS)
            has_code = any(re.search(pat, body) for pat in cls.CODE_SYNTAX_PATTERNS)
            # If prompt has >= 5 words, 0 recognized programming terms, and no code syntax
            if recognized == 0 and not has_code:
                return True, "Prompt tidak mengandung konteks pembelajaran pemrograman, algoritma, atau kode komputer."

        return False, ""

    @classmethod
    def is_curhat_or_emotional(cls, text: str) -> Tuple[bool, str]:
        """
        Detects personal emotional curhat, feeling stuck/lost in life, career, or salary issues,
        provided there are no technical/programming keywords present.
        """
        body = cls.strip_boilerplate(text)
        if not body:
            return False, ""

        body_lower = body.lower()
        words = set(re.findall(r'[a-zA-Z0-9_+]+', body_lower))
        prog_matches = words.intersection(cls.PROGRAMMING_KEYWORDS)

        # If user explicitly included programming keywords, treat as coding frustration (do not reject as curhat)
        if len(prog_matches) > 0:
            return False, ""

        for pat in cls.CURHAT_PATTERNS:
            if re.search(pat, body_lower):
                return True, "Prompt terdeteksi berupa curahan hati (curhat) atau topik kehidupan pribadi non-akademis."

        return False, ""

    @classmethod
    def is_off_topic_non_programming(cls, text: str) -> Tuple[bool, str]:
        """
        Detects general non-programming topics (recipes, politics, celebrity, astrology, pure greetings)
        without any technical programming context.
        """
        body = cls.strip_boilerplate(text)
        if not body:
            return False, ""

        body_lower = body.lower()
        words = set(re.findall(r'[a-zA-Z0-9_+]+', body_lower))
        prog_matches = words.intersection(cls.PROGRAMMING_KEYWORDS)

        # If user explicitly included programming keywords, not off-topic
        if len(prog_matches) > 0:
            return False, ""

        for pat in cls.OFF_TOPIC_PATTERNS:
            if re.search(pat, body_lower):
                return True, "Pertanyaan berada di luar domain pembelajaran pemrograman & algoritma komputer S-SPARC."

        return False, ""

    @classmethod
    def validate(cls, raw_prompt: str) -> Dict[str, Any]:
        """
        Main validation entry point for inbound user queries.
        Returns validation status, educational guide if rejected, category, and cleaned body.
        """
        if not raw_prompt or not raw_prompt.strip():
            return {
                "is_valid": False,
                "reason": "Prompt kosong.",
                "category": "EMPTY",
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
                "category": "HOLLOW_TEMPLATE",
                "status": "REJECTED",
                "educational_message": cls.build_educational_rejection(hollow_reason)
            }

        # Check emotional curhat / personal life distress
        is_curhat, curhat_reason = cls.is_curhat_or_emotional(prompt_clean)
        if is_curhat:
            return {
                "is_valid": False,
                "reason": curhat_reason,
                "category": "CURHAT",
                "status": "REJECTED",
                "educational_message": cls.build_curhat_response()
            }

        # Check off-topic non-programming queries
        is_off_topic, off_topic_reason = cls.is_off_topic_non_programming(prompt_clean)
        if is_off_topic:
            return {
                "is_valid": False,
                "reason": off_topic_reason,
                "category": "OFF_TOPIC",
                "status": "REJECTED",
                "educational_message": cls.build_off_topic_response(off_topic_reason)
            }

        # Check gibberish / spam
        is_gibberish, gibberish_reason = cls.is_gibberish_or_spam(prompt_clean)
        if is_gibberish:
            return {
                "is_valid": False,
                "reason": gibberish_reason,
                "category": "GIBBERISH",
                "status": "REJECTED",
                "educational_message": cls.build_educational_rejection(gibberish_reason)
            }

        return {
            "is_valid": True,
            "reason": "Valid academic programming query.",
            "category": "ACADEMIC",
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
            "[S-SPARC Guardrail]",
            "[S-SPARC Scope]",
            "S-SPARC AI Guardrail",
            "S-SPARC Academic Scope",
            "S-SPARC AI Analysis - Fibonacci",
            "Status Eksekusi:",
            "Solution processing completed",
            "Layanan AI lokal/cloud saat ini sedang",
            "Halo dari S-SPARC Academic Assistant",
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
        return f"""### [S-SPARC Guardrail] Kualitas Prompt Belum Memenuhi Standar

Prompt yang Anda kirimkan tidak dapat diproses karena:
> **Kendala:** {specific_reason}

---

#### Mengapa S-SPARC Memeriksa Kualitas Prompt?
S-SPARC dirancang untuk melatih **Literasi AI dan Kemandirian Kognitif (*Prompt Engineering*)**. Pengiriman teks acak (*gibberish*), template kosong, atau karakter berulang tidak dapat dianalisis oleh *Semantic Search Engine* dan dapat menimbulkan pemborosan komputasi (*GPU energy waste*).

#### Panduan Mengisi Protokol C-I-O-E yang Benar:
1. **[CONTEXT]**: Jelaskan materi kuliah dan algoritma yang sedang dipelajari (misal: *Dynamic Programming*, *Array*, *Binary Search*).
2. **[INPUT]**: Tuliskan tipe data input, parameter fungsi, dan contoh kasus uji (*test case*).
3. **[OUTPUT]**: Jelaskan hasil akhir yang diharapkan serta target kompleksitas waktu/ruang (misal: $O(N)$ atau $O(\\log N)$).
4. **[ERROR TRACE / KENDALA]**: Cantumkan baris kode Anda yang mengalami error atau penjelasan logika di mana Anda merasa buntu.

*Silakan lengkapi prompt Anda dengan pertanyaan atau kode yang spesifik lalu kirimkan kembali.*"""

    @classmethod
    def build_curhat_response(cls) -> str:
        """
        Generates an empathetic, human-centered response when a student shares personal/life struggles.
        """
        return """### Halo dari S-SPARC Academic Assistant

Halo bro, terima kasih sudah mau terbuka dan berbagi ceritamu di sini. Perasaan galau, merasa *stuck*, bingung dengan arah masa depan, atau merasa ritme kerjaan dan finansial jalan di tempat adalah hal yang sangat manusiawi, banyak orang (termasuk sesama mahasiswa, asisten lab, maupun staf) pernah atau sedang melewati fase serupa.

Ingat bahwa perjalanan hidup dan karier bukan lomba *sprint*, melainkan maraton panjang. Rasa stagnan sering kali menjadi sinyal tubuh dan pikiran bahwa kamu butuh jeda sejenak untuk menata ulang energi dan perspektif.

Berikut beberapa langkah sederhana yang bisa dicoba:
1. **Beri Jeda dan Istirahat**: Jangan paksakan diri saat pikiran sedang penuh. Ambil waktu sejenak dari layar komputer, jalan-jalan santai, dan pastikan istirahat cukup.
2. **Fokus pada Langkah Kecil (*Micro-progress*)**: Jangan bebani diri memikirkan segalanya sekaligus. Fokus pada satu hal konkret yang bisa diselesaikan hari ini.
3. **Cari Teman Cerita Nyata**: Jika perasaan ini terasa membebani, sangat dianjurkan untuk bercerita dengan teman terdekat, keluarga, dosen wali, atau unit bimbingan konseling kampus.

---
> **Catatan Sistem:** Sebagai asisten AI akademik, S-SPARC difokuskan secara khusus untuk membantu pembelajaran koding, algoritma, dan debugging. Kalau ada tugas praktikum atau tantangan pemrograman yang lagi bikin pusing, tanyakan kodinganmu di sini ya bro, kita diskusikan bareng-bareng! Tetap semangat terus!"""

    @classmethod
    def build_off_topic_response(cls, reason: str) -> str:
        """
        Generates boundary clarification when user sends general off-topic/non-programming queries.
        """
        return f"""### [S-SPARC Scope] Pertanyaan di Luar Pembelajaran Pemrograman

Halo! Pertanyaan atau topik yang Anda kirimkan berada di luar domain sistem pembelajaran **S-SPARC**.
> **Keterangan:** {reason}

---

#### Mengenai Fokus Akademik S-SPARC:
S-SPARC (*Sustainable Semantic Prompting & Academic Reasoning Corpus*) adalah sistem asisten akademik yang dirancang secara khusus untuk:
- Pembelajaran Pemrograman dan Algoritma (Python, Java, C++, Struktur Data, OOP, dll.)
- Analisis Bug, Error Trace, dan Optimasi Kompleksitas (Big-O)
- Pelatihan Prompt Engineering Akademis (Protokol C-I-O-E)

#### Rekomendasi Langkah:
Silakan ajukan pertanyaan terkait koding, algoritma, atau tugas praktikum Anda menggunakan format protokol **C-I-O-E**:
1. **[CONTEXT]**: Materi kuliah / bahasa pemrograman yang digunakan.
2. **[INPUT]**: Parameter masukan, tipe data, atau kasus uji.
3. **[OUTPUT]**: Hasil keluaran dan target kompleksitas yang diharapkan.
4. **[ERROR TRACE / KENDALA]**: Kode yang error atau letak kebingungan Anda."""

