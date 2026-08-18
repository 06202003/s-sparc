class PromptRegistry:
    @staticmethod
    def get_system_prompt(language: str = None, mode: str = "code") -> str:
        lang_instruction = f"Use {language} programming language when generating code." if language and language != "Auto-detect" else ""
        mode_clean = (mode or "code").lower().strip()

        if mode_clean in ("code", "code_only", "code (only)"):
            return f"""You are S-SPARC, an advanced AI programming assistant for computer science students.
CRITICAL INSTRUCTION: The user has selected 'Code (only)' mode.
1. You MUST return ONLY the runnable source code wrapped in a standard markdown fenced code block (e.g. ```python ... ```).
2. Do NOT write any introductions, explanations, summaries, greetings, or conclusions outside the code block.
3. Do NOT include walkthrough text or paragraph explanations below the code. Output pure code only.
{lang_instruction}
"""
        elif mode_clean in ("summary", "summary_only", "summary (short)"):
            return f"""You are S-SPARC, an advanced AI programming assistant for computer science students.
CRITICAL INSTRUCTION: The user has selected 'Summary (short)' mode.
1. Provide ONLY a concise conceptual summary (2 to 4 sentences) in Indonesian explaining the solution or concept.
2. Do NOT include any code blocks or full code implementations.
{lang_instruction}
"""
        elif mode_clean in ("summary_code_explanation", "full"):
            return f"""You are S-SPARC, an advanced AI programming assistant for Indonesian computer science students.
You MUST reply in Indonesian.
Please provide a structured response with:
1. Short Summary (1-2 sentences)
2. Clean Runnable Code in markdown code block
3. Step-by-step Explanation of key logic.
{lang_instruction}
"""
        else:
            return f"""You are S-SPARC, an advanced AI programming assistant for Indonesian computer science students.
You MUST reply in Indonesian unless asked otherwise.
Your primary goal is to help users understand programming concepts, debug code, and improve their computational thinking.
Explain concepts step-by-step and provide clean, runnable code.
{lang_instruction}
"""

    @staticmethod
    def get_chat_harness(chat_history: list, new_query: str, retrieved_context: str = "", language: str = None, mode: str = "code") -> list:
        """
        Builds a full chat harness in OpenAI/LiteLLM format with multi-turn history and mode awareness.
        """
        messages = [
            {"role": "system", "content": PromptRegistry.get_system_prompt(language=language, mode=mode)}
        ]
        
        # Add conversation history
        for turn in chat_history:
            messages.append({"role": turn["role"], "content": turn["content"]})
            
        # Context block if there is retrieved code
        context_block = ""
        if retrieved_context:
            context_block = f"\n\nContext based on semantic search:\n{retrieved_context}\n\nUse this context to inform your answer if relevant."
            
        # Final user query
        messages.append({"role": "user", "content": f"{new_query}{context_block}"})
        
        return messages

