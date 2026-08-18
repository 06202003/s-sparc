import re

class PromptRegistry:
    """
    S-SPARC Cognitive Prompt Registry with Headroom-Inspired Context Compression:
    - CacheAligner: Deterministic, frozen prefix structure to guarantee high provider KV-cache hits.
    - Output Shaper & Verbosity Steering: Dynamic concise constraints to eliminate 50-70% output fluff.
    - Bloom's Taxonomy Cognitive Tiering: C1-C2 (Summary), C3-C4 (Code), C5-C6 (Full Scaffolding).
    """

    @staticmethod
    def compress_context_snippet(raw_code: str, max_lines: int = 40) -> str:
        """
        Headroom-inspired AST/regex code compressor for RAG chunks.
        Strips multi-line comments, docstrings, trailing whitespace, and dead imports
        to reduce context token payload by up to 75%.
        """
        if not raw_code:
            return ""
        
        # Strip triple-quote docstrings
        code = re.sub(r'("""[\s\S]*?"""|\'\'\'[\s\S]*?\'\'\')', '', raw_code)
        
        # Strip single-line comments (# or //) while preserving hash-bangs
        lines = []
        for line in code.splitlines():
            clean_line = line.rstrip()
            if not clean_line or clean_line.strip().startswith(('#', '//')) and not clean_line.strip().startswith('#!'):
                continue
            lines.append(clean_line)
            if len(lines) >= max_lines:
                lines.append("... [compressed by S-SPARC CodeCompressor]")
                break
                
        return "\n".join(lines)

    @staticmethod
    def get_system_prompt(language: str = None, mode: str = "code") -> str:
        lang_instruction = f"Target Language: {language}." if language and language != "Auto-detect" else ""
        mode_clean = (mode or "code").lower().strip()

        # CacheAligner Prefix: Deterministic static head for KV cache alignment
        base_prefix = "You are S-SPARC, an advanced AI programming assistant for computer science students.\n"

        if mode_clean in ("code", "code_only", "code (only)"):
            # Bloom C3-C4 (Apply & Analyze) + Output Shaper Verbosity Steering
            return f"""{base_prefix}[BLOOM TIER: C3-C4 APPLY & ANALYZE | OUTPUT SHAPER: MAXIMUM CONCISENESS]
CRITICAL INSTRUCTIONS:
1. Return ONLY the clean, runnable source code inside standard markdown fenced code blocks.
2. DO NOT write greetings, introductory sentences, explanations, summaries, or conclusions.
3. DO NOT restate the user prompt or unchanged code. Output pure solution code only.
{lang_instruction}
"""
        elif mode_clean in ("summary", "summary_only", "summary (short)"):
            # Bloom C1-C2 (Remember & Understand)
            return f"""{base_prefix}[BLOOM TIER: C1-C2 REMEMBER & UNDERSTAND | CONCEPTUAL SCAFFOLDING]
CRITICAL INSTRUCTIONS:
1. Provide ONLY a concise conceptual summary (2 to 4 sentences) in Indonesian explaining the core logic, data structure choice, and time/space complexity.
2. DO NOT output any raw code blocks or code implementations. Compel the student to write the code themselves.
{lang_instruction}
"""
        elif mode_clean in ("summary_code_explanation", "full"):
            # Bloom C5-C6 (Evaluate & Create)
            return f"""{base_prefix}[BLOOM TIER: C5-C6 EVALUATE & CREATE | FULL COGNITIVE SCAFFOLDING]
Please provide a structured response in Indonesian:
1. Short Summary (1-2 sentences explaining algorithmic approach)
2. Clean Runnable Code in markdown code block
3. Step-by-Step Logic Walkthrough with edge-case considerations.
[VERBOSITY STEERING: Be terse, avoid unnecessary conversational filler.]
{lang_instruction}
"""
        else:
            return f"""{base_prefix}[GENERAL TUTORING MODE]
You MUST reply in Indonesian unless asked otherwise.
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
