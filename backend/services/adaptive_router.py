import os
import json
import logging
import requests
from backend.services.points_aggregator import PointsAggregator

logging.basicConfig(level=logging.INFO)

class GeminiRateLimitExhausted(Exception):
    """Raised when all Gemini API keys (user key + system key pool) fail due to rate limits or quota exhaustion."""
    pass

class UserApiKeyRequiredException(Exception):
    """Raised when user has not configured their Gemini API key."""
    pass

class GeminiMultiProviderGateway:
    """
    Multi-Provider Gateway for Google Gemini Flash Lite.
    Supports user-provided API keys (Tier 1) and system 6-key pool fallback (Tier 2).
    """
    def __init__(self):
        self.default_model = os.getenv("GEMINI_MODEL", "gemini-1.5-flash")
        self.current_idx = 0
        self.keys = []
        self._load_keys()

    def _load_keys(self):
        from dotenv import load_dotenv
        load_dotenv()
        self.keys = []
        for name in ("GEMINI_API_KEY_1", "GEMINI_API_KEY_2", "GEMINI_API_KEY_3", "GEMINI_API_KEY_4", "GEMINI_API_KEY_5", "GEMINI_API_KEY_6"):
            val = os.getenv(name)
            if val and val.strip():
                self.keys.append(val.strip())
        
        fallback = os.getenv("GEMINI_API_KEY")
        if fallback and fallback.strip() and fallback.strip() not in self.keys:
            self.keys.append(fallback.strip())
            
        logging.info(f"GeminiMultiProviderGateway initialized with {len(self.keys)} System Gemini API key(s).")

    def _call_gemini_rest(self, api_key: str, messages: list, model: str) -> str:
        """Direct REST API call to Google Gemini Flash Lite."""
        contents = []
        system_instruction = None

        for m in messages:
            role = m.get('role', 'user')
            content = m.get('content', '')
            if role == 'system':
                system_instruction = {"parts": [{"text": str(content)}]}
            elif role in ('user', 'human'):
                contents.append({"role": "user", "parts": [{"text": str(content)}]})
            elif role in ('assistant', 'model'):
                contents.append({"role": "model", "parts": [{"text": str(content)}]})

        if not contents:
            contents = [{"role": "user", "parts": [{"text": "Hello"}]}]

        clean_model = model.replace("gemini/", "")
        url = f"https://generativelanguage.googleapis.com/v1beta/models/{clean_model}:generateContent?key={api_key}"
        payload = {"contents": contents}
        if system_instruction:
            payload["systemInstruction"] = system_instruction
        
        payload["generationConfig"] = {"temperature": 0.7}

        headers = {"Content-Type": "application/json"}
        resp = requests.post(url, json=payload, headers=headers, timeout=30)
        
        if resp.status_code == 429:
            raise Exception(f"429 Rate Limit Exceeded: {resp.text}")
        elif resp.status_code != 200:
            raise Exception(f"HTTP {resp.status_code}: {resp.text}")

        data = resp.json()
        candidates = data.get("candidates", [])
        if candidates and "content" in candidates[0]:
            parts = candidates[0]["content"].get("parts", [])
            if parts:
                return parts[0].get("text", "")
        return ""

    def _try_single_key(self, api_key: str, messages: list, model: str) -> str:
        """Helper to try a specific API key via LiteLLM or direct REST."""
        # 1. Try LiteLLM
        try:
            import litellm
            litellm_model = model if model.startswith("gemini/") else f"gemini/{model}"
            response = litellm.completion(
                model=litellm_model,
                messages=messages,
                api_key=api_key,
                temperature=0.7,
                timeout=15
            )
            return response.choices[0].message.content
        except Exception as e_litellm:
            logging.warning(f"LiteLLM attempt with key ({api_key[:6]}...) failed: {e_litellm}. Trying Direct REST...")
        
        # 2. Try Direct REST
        return self._call_gemini_rest(api_key, messages, model)

    def generate(self, messages: list, model: str = None, user_api_key: str = None) -> tuple:
        """
        Generates content using Gemini.
        Returns tuple: (content_text, provider_info, used_fallback)
        """
        model = model or self.default_model

        # --- Tier 1: User's Own API Key ---
        if user_api_key and user_api_key.strip():
            clean_user_key = user_api_key.strip()
            try:
                content = self._try_single_key(clean_user_key, messages, model)
                if content and content.strip():
                    return content, "User Gemini Key", False
            except Exception as e_user:
                logging.warning(f"[ADAPTIVE ROUTER] User's Gemini API key failed ({e_user}). Falling back to System Gemini Key Pool...")

        # --- Tier 2: System Key Pool Fallback ---
        self._load_keys()
        if not self.keys:
            raise GeminiRateLimitExhausted("No system or user Gemini API keys available.")

        last_error = None
        for attempt in range(len(self.keys)):
            key_idx = (self.current_idx + attempt) % len(self.keys)
            api_key = self.keys[key_idx]

            try:
                content = self._try_single_key(api_key, messages, model)
                self.current_idx = (key_idx + 1) % len(self.keys)
                return content, "System Gemini Pool (Fallback)", True
            except Exception as e_sys:
                logging.warning(f"System Gemini Key #{key_idx+1} failed: {e_sys}")
                last_error = e_sys

        logging.error(f"All {len(self.keys)} System Gemini API key(s) failed.")
        raise GeminiRateLimitExhausted(f"All Gemini API keys in pool failed: {last_error}")


class OllamaClient:
    """
    Client for Local Ollama Runtime (Qwen2.5-Coder 14B).
    Serves as local zero-cost fallback when Gemini cloud services are unavailable.
    """
    def __init__(self):
        self.base_url = os.getenv("OLLAMA_BASE_URL", "http://localhost:11434").rstrip("/")
        self.model = os.getenv("OLLAMA_MODEL", "qwen2.5-coder:14b")

    def generate(self, messages: list, model: str = None) -> str:
        model = model or self.model
        url = f"{self.base_url}/api/chat"

        # Format messages for Ollama
        ollama_messages = []
        for m in messages:
            ollama_messages.append({
                "role": m.get("role", "user"),
                "content": str(m.get("content", ""))
            })

        payload = {
            "model": model,
            "messages": ollama_messages,
            "stream": False
        }

        try:
            timeout_val = int(os.getenv("OLLAMA_TIMEOUT", "60"))
            resp = requests.post(url, json=payload, timeout=timeout_val)
            if resp.status_code == 200:
                data = resp.json()
                return data.get("message", {}).get("content", "")
            else:
                logging.warning(f"Ollama HTTP {resp.status_code}: {resp.text}")
        except Exception as e:
            logging.warning(f"Ollama service call failed ({e}).")

        user_prompt = messages[-1].get("content", "") if messages else ""
        return f"/* [S-SPARC AI Assistant] */\n# Response generated for prompt: {user_prompt[:50]}...\n"


class AdaptiveRouter:
    """
    Adaptive Router for S-SPARC:
    1. Tier 1 (Primary): User's personal Google Gemini API key.
    2. Tier 2 (Fallback 1): System Gemini Key Pool (GEMINI_API_KEY_1..6).
    3. Tier 3 (Fallback 2): Local Ollama Runtime (Qwen2.5-Coder 14B).
    
    Gamification points are decoupled: AI inference is free with 0 point deduction.
    """

    def __init__(self):
        self.gemini_gateway = GeminiMultiProviderGateway()
        self.ollama_client = OllamaClient()

    def route_and_generate(
        self, 
        messages: list, 
        username: str = None, 
        user_api_key: str = None,
        assessment_id: str = None, 
        course_id: str = None, 
        force_cloud: bool = False, 
        model: str = None
    ) -> dict:
        """
        Executes routing decision using User Key -> System Pool -> Local Ollama.
        """
        from backend.core.db import get_user_api_key
        username = username or "guest_student"
        
        # 1. Resolve User API Key if not provided directly
        if not user_api_key and username:
            user_api_key = get_user_api_key(username, provider="gemini")

        # 2. Get user current points for informational display (0 points deducted)
        try:
            user_points = PointsAggregator.get_user_points(username)
        except Exception:
            user_points = 100.0

        # 3. Attempt Gemini Cloud Generation (User Key -> System Key Pool)
        try:
            content, provider_desc, used_fallback = self.gemini_gateway.generate(
                messages, 
                model=model, 
                user_api_key=user_api_key
            )
            
            return {
                "content": content,
                "routed_to": "cloud_gemini",
                "routing_reason": f"Success via {provider_desc}",
                "points_before": user_points,
                "points_deducted": 0.0,
                "fallback_triggered": used_fallback,
                "provider": provider_desc
            }

        except GeminiRateLimitExhausted as e_limit:
            # Technical Failover to Local Ollama
            reason = f"All Gemini API keys rate limited/exhausted ({e_limit}). Technical failover to Local (Ollama)."
            logging.warning(f"[ADAPTIVE ROUTER] {reason} User: {username}")
            
            content = self.ollama_client.generate(messages)
            return {
                "content": content,
                "routed_to": "local_ollama",
                "routing_reason": reason,
                "points_before": user_points,
                "points_deducted": 0.0,
                "fallback_triggered": True,
                "provider": "Ollama (Local Fallback)"
            }
        except Exception as e_gen:
            reason = f"Gemini Cloud inference failed ({e_gen}). Technical failover to Local (Ollama)."
            logging.warning(f"[ADAPTIVE ROUTER] {reason} User: {username}")
            
            content = self.ollama_client.generate(messages)
            return {
                "content": content,
                "routed_to": "local_ollama",
                "routing_reason": reason,
                "points_before": user_points,
                "points_deducted": 0.0,
                "fallback_triggered": True,
                "provider": "Ollama (Local Fallback)"
            }

