from __future__ import annotations

import ast
import math
import re
from dataclasses import asdict, dataclass

from radon.complexity import cc_visit
from radon.metrics import mi_visit


FUNCTION_PATTERNS = {
    "python": re.compile(r"^\s*def\s+\w+", re.MULTILINE),
    "javascript": re.compile(r"function\s+\w+|=>|const\s+\w+\s*=\s*\(", re.MULTILINE),
    "typescript": re.compile(r"function\s+\w+|=>|const\s+\w+\s*=\s*\(", re.MULTILINE),
    "java": re.compile(r"(?:public|private|protected)?\s+(?:static\s+)?[\w<>\[\]]+\s+\w+\s*\(", re.MULTILINE),
    "cpp": re.compile(r"[\w:<>~*&]+\s+\w+\s*\([^;]*\)\s*\{", re.MULTILINE),
    "c": re.compile(r"[\w\*]+\s+\w+\s*\([^;]*\)\s*\{", re.MULTILINE),
    "php": re.compile(r"function\s+\w+\s*\(", re.MULTILINE),
    "go": re.compile(r"func\s+\w+\s*\(", re.MULTILINE),
}

LOOP_PATTERN = re.compile(r"\b(for|while|foreach)\b")


@dataclass(slots=True)
class StaticAnalysisResult:
    detected_language: str
    syntax_valid: bool
    line_count: int
    function_count: int
    loop_count: int
    cyclomatic_complexity: float
    maintainability_index: float
    static_score: float
    notes: list[str]

    def to_dict(self) -> dict:
        return asdict(self)


def detect_language(code: str, prompt: str = "") -> str:
    text = (code or "").strip()
    prompt_text = (prompt or "").lower()

    if text.startswith("<?php") or "$" in text and "echo" in text:
        return "php"
    if re.search(r"^\s*def\s+\w+|import\s+\w+|from\s+\w+\s+import", text, re.MULTILINE):
        return "python"
    if "console.log" in text or "=>" in text or "let " in text or "const " in text:
        return "javascript"
    if "interface " in text or ": string" in text or ": number" in text:
        return "typescript"
    if "public class" in text or "System.out.println" in text:
        return "java"
    if "#include" in text:
        return "cpp" if "std::" in text else "c"
    if re.search(r"\bpackage\s+main\b|\bfmt\.Println\b|\bfunc\s+main", text):
        return "go"
    if "python" in prompt_text:
        return "python"
    if "java" in prompt_text:
        return "java"
    if "javascript" in prompt_text or "node" in prompt_text:
        return "javascript"
    if "php" in prompt_text:
        return "php"
    return "unknown"


def _count_lines(code: str) -> int:
    return sum(1 for line in (code or "").splitlines() if line.strip())


def _count_functions(language: str, code: str) -> int:
    pattern = FUNCTION_PATTERNS.get(language)
    if not pattern:
        return 0
    return len(pattern.findall(code or ""))


def _balanced_delimiters(code: str) -> bool:
    pairs = {")": "(", "]": "[", "}": "{"}
    stack: list[str] = []
    for char in code:
        if char in "([{":
            stack.append(char)
        elif char in pairs:
            if not stack or stack.pop() != pairs[char]:
                return False
    return not stack


def _score_static_metrics(
    syntax_valid: bool,
    line_count: int,
    function_count: int,
    loop_count: int,
    cyclomatic_complexity: float,
    maintainability_index: float,
) -> float:
    if line_count == 0:
        return 0.0

    score = 2.6 if syntax_valid else 0.0
    score += 0.6 if line_count >= 1 else 0.0
    score += 0.7 if line_count >= 3 else 0.4
    score += min(function_count, 2) * 0.45
    score += 0.4 if loop_count <= 4 else max(0.0, 0.4 - (loop_count - 4) * 0.05)

    if cyclomatic_complexity <= 5:
        score += 2.1
    elif cyclomatic_complexity <= 10:
        score += 1.6
    elif cyclomatic_complexity <= 20:
        score += 1.1
    else:
        score += 0.6

    score += max(0.0, min(maintainability_index / 33.0, 3.2))
    return round(max(0.0, min(score, 10.0)), 2)


def analyze_code(prompt: str, code: str) -> StaticAnalysisResult:
    code = code or ""
    language = detect_language(code, prompt)
    line_count = _count_lines(code)
    function_count = _count_functions(language, code)
    loop_count = len(LOOP_PATTERN.findall(code))
    notes: list[str] = []

    syntax_valid = bool(code.strip()) and _balanced_delimiters(code)
    cyclomatic_complexity = 0.0
    maintainability_index = 0.0

    if language == "python" and code.strip():
        try:
            tree = ast.parse(code)
            syntax_valid = True
            function_count = sum(isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef)) for node in ast.walk(tree))
            loop_count = sum(isinstance(node, (ast.For, ast.AsyncFor, ast.While)) for node in ast.walk(tree))
            complexities = cc_visit(code)
            cyclomatic_complexity = float(max((block.complexity for block in complexities), default=1.0))
            maintainability_index = float(mi_visit(code, multi=True))
        except SyntaxError as exc:
            syntax_valid = False
            notes.append(f"Python syntax error: {exc.msg}")
        except Exception as exc:
            notes.append(f"Python analysis degraded: {exc}")
    else:
        if not syntax_valid:
            notes.append("Delimiter balance check failed")
        cyclomatic_complexity = float(max(1.0, function_count + loop_count + len(re.findall(r"\b(if|case|elif|catch)\b", code))))
        maintainability_index = float(max(0.0, 100.0 - line_count * 1.25 - cyclomatic_complexity * 3.5))

    if line_count == 0:
        notes.append("Code is empty or whitespace only")
    if function_count == 0 and line_count > 12:
        notes.append("No obvious function detected")
    if maintainability_index and math.isnan(maintainability_index):
        maintainability_index = 0.0

    static_score = _score_static_metrics(
        syntax_valid=syntax_valid,
        line_count=line_count,
        function_count=function_count,
        loop_count=loop_count,
        cyclomatic_complexity=cyclomatic_complexity,
        maintainability_index=maintainability_index,
    )

    return StaticAnalysisResult(
        detected_language=language,
        syntax_valid=syntax_valid,
        line_count=line_count,
        function_count=function_count,
        loop_count=loop_count,
        cyclomatic_complexity=round(cyclomatic_complexity, 2),
        maintainability_index=round(maintainability_index, 2),
        static_score=static_score,
        notes=notes,
    )
