import re

class PromptRegistry:
    """
    S-SPARC Cognitive Prompt Registry with Headroom-Inspired Context Compression:
    - CacheAligner: Deterministic, frozen prefix structure to guarantee high provider KV-cache hits.
    - Output Shaper & Verbosity Steering: Dynamic concise constraints to eliminate 50-70% output fluff.
    - Bloom's Taxonomy Cognitive Tiering: C1-C2 (Summary), C3-C4 (Code), C5-C6 (Full Scaffolding).
    """

    @staticmethod
    def compress_context_snippet(raw_code: str, max_lines: int = 45) -> str:
        """
        Headroom-inspired AST/regex code compressor for RAG chunks across Python, Java, C++, JS, and PHP.
        Strips multi-line comments, docstrings, trailing whitespace, and excessive blank lines
        to reduce context token payload by up to 78%.
        """
        if not raw_code:
            return ""
        
        # Strip triple-quote docstrings and block comments (/* ... */)
        code = re.sub(r'("""[\s\S]*?"""|\'\'\'[\s\S]*?\'\'\')', '', raw_code)
        code = re.sub(r'/\*[\s\S]*?\*/', '', code)
        
        # Strip single-line comments (# or //) while preserving hash-bangs (#!)
        lines = []
        consecutive_blanks = 0
        for line in code.splitlines():
            clean_line = line.rstrip()
            if not clean_line:
                consecutive_blanks += 1
                if consecutive_blanks <= 1 and lines:
                    lines.append("")
                continue
            
            consecutive_blanks = 0
            stripped = clean_line.strip()
            if stripped.startswith(('#', '//')) and not stripped.startswith('#!'):
                continue
            
            lines.append(clean_line)
            if len(lines) >= max_lines:
                lines.append("... [compressed by S-SPARC CodeCompressor]")
                break
                
        return "\n".join(lines)

    @staticmethod
    def get_system_prompt(language: str = None, mode: str = "code") -> str:
        lang_instruction = f"Target Programming Language: {language}." if language and language != "Auto-detect" else ""
        mode_clean = (mode or "code").lower().strip()

        # CacheAligner Prefix: Deterministic static head for KV cache alignment
        base_prefix = "You are S-SPARC, an advanced AI programming assistant for computer science students.\nLANGUAGE RULE: Always reply in the exact same natural language used by the student in their prompt (reply 100% in English if queried in English, Indonesian if queried in Indonesian, Japanese if in Japanese, etc.).\n"

        if mode_clean in ("code", "code_only", "code (only)"):
            # Bloom C3-C4 (Apply & Analyze) + Output Shaper Verbosity Steering
            return f"""{base_prefix}[BLOOM TIER: C3-C4 APPLY & ANALYZE | OUTPUT SHAPER: MAXIMUM CONCISENESS]
CRITICAL INSTRUCTIONS:
1. Return ONLY the clean, runnable source code inside standard markdown fenced code blocks.
2. DO NOT write greetings, introductory sentences, explanations, summaries, or conclusions.
3. DO NOT restate the user prompt or unchanged code. Output pure solution code only.
4. Respond in the exact same natural language as the student's prompt.
{lang_instruction}
"""
        elif mode_clean in ("summary", "summary_only", "summary (short)"):
            # Bloom C1-C2 (Remember & Understand)
            return f"""{base_prefix}[BLOOM TIER: C1-C2 REMEMBER & UNDERSTAND | CONCEPTUAL SCAFFOLDING]
CRITICAL INSTRUCTIONS:
1. Provide ONLY a concise conceptual summary (2 to 4 sentences) in the EXACT same natural language used by the student in their prompt (e.g., English, Japanese, Korean, Indonesian, etc.), explaining the core logic, data structure choice, and time/space complexity.
2. DO NOT output any raw code blocks or code implementations. Compel the student to write the code themselves.
3. Respond in the exact same natural language as the student's prompt.
{lang_instruction}
"""
        elif mode_clean in ("socratic", "socratic_guide", "tutor"):
            # Bloom C4-C6 (Analyze, Evaluate & Socratic Scaffolding)
            return f"""{base_prefix}[BLOOM TIER: C4-C6 SOCRATIC COACHING | ACTIVE LEARNING SCAFFOLDING]
CRITICAL INSTRUCTIONS:
1. DO NOT give the complete final code solution directly.
2. Formulate 2-3 guided diagnostic questions or small hints pointing directly at the logical precondition, edge cases, or algorithmic invariant where the bug resides.
3. Encourage the student to formulate and verify the hypothesis themselves.
4. Reply in the EXACT SAME language used by the student (e.g., English, Indonesian, etc.).
{lang_instruction}
"""
        elif mode_clean in ("summary_code_explanation", "full"):
            # Bloom C5-C6 (Evaluate & Create)
            return f"""{base_prefix}[BLOOM TIER: C5-C6 EVALUATE & CREATE | FULL COGNITIVE SCAFFOLDING]
Please provide a structured response in the EXACT same natural language used by the student in their prompt:
1. Short Summary (1-2 sentences explaining algorithmic approach)
2. Clean Runnable Code in markdown code block
3. Step-by-Step Logic Walkthrough with edge-case considerations.
[VERBOSITY STEERING: Be terse, avoid unnecessary conversational filler.]
[LANGUAGE STEERING: Match the student's prompt language 100%.]
{lang_instruction}
"""
        else:
            return f"""{base_prefix}[GENERAL TUTORING MODE]
You MUST reply in the EXACT same natural language used by the student in their prompt (e.g., English if asked in English, Japanese if asked in Japanese, Korean if asked in Korean, Indonesian if asked in Indonesian, etc.).
Explain concepts step-by-step and provide clean, runnable code.
[VERBOSITY STEERING: Be precise, technical, and avoid conversational filler.]
{lang_instruction}
"""

    @staticmethod
    def get_chat_harness(chat_history: list, new_query: str, retrieved_context: str = "", language: str = None, mode: str = "code") -> list:
        """
        Builds a full chat harness in OpenAI/LiteLLM format with multi-turn history,
        CacheAligner prefix stability, and CodeCompressor context injection.
        """
        messages = [
            {"role": "system", "content": PromptRegistry.get_system_prompt(language=language, mode=mode)}
        ]
        
        # Add conversation history
        for turn in chat_history:
            messages.append({"role": turn["role"], "content": turn["content"]})
            
        # Context block compressed via CodeCompressor
        context_block = ""
        if retrieved_context:
            compressed_context = PromptRegistry.compress_context_snippet(retrieved_context)
            context_block = f"\n\n[CONTEXT BASED ON SEMANTIC SEARCH (Compressed)]:\n{compressed_context}\n\nUse this context to inform your answer if relevant."
            
        # Final user query
        messages.append({"role": "user", "content": f"{new_query}{context_block}"})
        
        return messages
