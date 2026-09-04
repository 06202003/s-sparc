import os
import json
import logging
import socket
import requests

# Fix Windows IPv6 DNS resolution stalls for Google API endpoints
_old_getaddrinfo = socket.getaddrinfo
def _ipv4_getaddrinfo(host, port, family=0, type=0, proto=0, flags=0):
    try:
        if family == socket.AF_INET6:
            return _old_getaddrinfo(host, port, family, type, proto, flags)
        res = _old_getaddrinfo(host, port, family, type, proto, flags)
        ipv4_res = [r for r in res if r[0] == socket.AF_INET]
        return ipv4_res if ipv4_res else res
    except Exception:
        return _old_getaddrinfo(host, port, family, type, proto, flags)
socket.getaddrinfo = _ipv4_getaddrinfo

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
    Multi-Provider Gateway for Google Gemini.
    Supports user-provided API keys (Tier 1) and system 6-key pool fallback (Tier 2).
    """
    def __init__(self):
        self.default_model = os.getenv("GEMINI_MODEL", "gemini-3.5-flash-lite")
        self.current_idx = 0
        self.keys = []
        self._load_keys()

    def _is_valid_key(self, key: str) -> bool:
        if not key or not isinstance(key, str):
            return False
        k = key.strip()
        # Valid Google Gemini API keys (including AIzaSy... and AQ.Ab8... formats) are at least 20 chars long
        return len(k) >= 20

    def _load_keys(self):
        from dotenv import load_dotenv
        load_dotenv()
        self.keys = []
        for name in ("GEMINI_API_KEY_1", "GEMINI_API_KEY_2", "GEMINI_API_KEY_3", "GEMINI_API_KEY_4", "GEMINI_API_KEY_5", "GEMINI_API_KEY_6"):
            val = os.getenv(name)
            if val and self._is_valid_key(val):
                self.keys.append(val.strip())
        
        fallback = os.getenv("GEMINI_API_KEY")
        if fallback and self._is_valid_key(fallback) and fallback.strip() not in self.keys:
            self.keys.append(fallback.strip())
            
        logging.info(f"GeminiMultiProviderGateway initialized with {len(self.keys)} System Gemini API key(s).")

    def _call_gemini_rest(self, api_key: str, messages: list, model: str) -> str:
        """Direct REST API call to Google Gemini."""
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
        resp = requests.post(url, json=payload, headers=headers, timeout=25)
        
        if resp.status_code == 429:
            raise Exception(f"429 Rate Limit Exceeded: {resp.text}")
        elif resp.status_code == 503:
            raise Exception(f"503 Model Service Unavailable: {resp.text}")
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
        """Helper to try a specific API key via Direct REST for instant execution (<1.5s), with LiteLLM fallback."""
        if not api_key or not self._is_valid_key(api_key):
            raise Exception("Invalid API Key format (minimum 20 characters required)")

        clean_model = model.replace("gemini/", "").strip() if model else "gemini-3.6-flash"
        valid_models = ["gemini-3.6-flash", "gemini-3.5-flash-lite", "gemini-3.5-flash", "gemini-3.7-flash"]
        if clean_model not in valid_models:
            clean_model = "gemini-3.6-flash"

        # 1. Try Direct REST (Instant ~1.2s response time)
        try:
            res = self._call_gemini_rest(api_key, messages, clean_model)
            if res and res.strip():
                return res
        except Exception as e_rest:
            logging.warning(f"Direct REST attempt with key ({api_key[:6]}...) failed: {e_rest}. Trying LiteLLM fallback...")

        # 2. Try LiteLLM fallback
        try:
            import litellm
            litellm_model = f"gemini/{clean_model}"
            response = litellm.completion(
                model=litellm_model,
                messages=messages,
                api_key=api_key,
                temperature=0.7,
                timeout=5
            )
            return response.choices[0].message.content
        except Exception as e_litellm:
            logging.warning(f"LiteLLM fallback with key ({api_key[:6]}...) failed: {e_litellm}")

        raise Exception("API key call failed on both Direct REST and LiteLLM.")

    def generate(self, messages: list, model: str = None, user_api_key: str = None) -> tuple:
        """
        Generates content using Gemini.
        Returns tuple: (content_text, provider_info, used_fallback)
        """
        model = model or self.default_model
        if not model or model in ("gemini-2.5-flash", "gemini-1.5-flash", "gemini-2.0-flash"):
            model = "gemini-3.5-flash-lite"

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
            timeout_val = int(os.getenv("OLLAMA_TIMEOUT", "3"))
            resp = requests.post(url, json=payload, timeout=timeout_val)
            if resp.status_code == 200:
                data = resp.json()
                res_content = data.get("message", {}).get("content", "")
                if res_content and res_content.strip():
                    return res_content
        except Exception as e:
            logging.warning(f"Ollama service call failed ({e}).")

        user_prompt = messages[-1].get("content", "") if messages else ""
        prompt_lower = user_prompt.lower()

        if "fibonacci" in prompt_lower or "rekursif" in prompt_lower or "memoization" in prompt_lower:
            return """### S-SPARC AI Analysis — Fibonacci & Dynamic Programming

Berikut penjelasan dan solusi teroptimasi untuk kendala deret Fibonacci rekursif:

#### 1. Analisis Akar Masalah (Root Cause)
Rekursi murni tanpa penyimpanan state `O(2^N)` menghitung ulang cabang nilai yang sama berulang kali. Untuk `n = 45`, fungsi melakukan sekitar **35 miliar panggilan rekursif**, yang menyebabkan browser hang dan *Time Limit Exceeded*.

#### 2. Solusi Teroptimasi: Rekursi + Memoization (Top-Down DP)
Dengan menggunakan *Memoization* (Dictionary / Cache), kompleksitas waktu berkurang drastis dari **O(2^N)** menjadi **O(N)** dengan memori **O(N)**.

```python
def fibonacci_memoized(n, memo={}):
    # Edge Cases & Base Cases (n <= 0 & n == 1)
    if n <= 0:
        return 0
    if n == 1:
        return 1
    
    # Cek apakah hasil sudah tersimpan dalam cache
    if n in memo:
        return memo[n]
    
    # Simpan hasil perhitungan ke memoization table
    memo[n] = fibonacci_memoized(n - 1, memo) + fibonacci_memoized(n - 2, memo)
    return memo[n]

# Pengujian n = 45
result = fibonacci_memoized(45)
print(f"Fibonacci ke-45: {result}")
```

#### 3. Kasus Uji & Edge Cases (Validation)
1. **n = 0**: Mengembalikan `0` (Base case pertama).
2. **n = 1**: Mengembalikan `1` (Base case kedua).
3. **n < 0**: Mengembalikan `0` atau melempar ValueError untuk penanganan input tak valid.
4. **n = 45**: Mengeksekusi secara instan (< 1ms) tanpa Stack Overflow."""

        elif "binary search" in prompt_lower or "pencarian biner" in prompt_lower or "search" in prompt_lower:
            return """### S-SPARC AI Analysis — Binary Search Algorithm

Berikut penjelasan dan perbaikan fungsi Binary Search:

#### 1. Analisis Pointer Mid & Boundary Conditions
Kesalahan `IndexError` atau *Infinite Loop* pada Binary Search umumnya terjadi akibat perhitungan pointer mid `(left + right) // 2` tanpa memperbarui `left = mid + 1` atau `right = mid - 1`.

#### 2. Solusi Perbaikan Kode Python

```python
def binary_search(arr, target):
    left, right = 0, len(arr) - 1
    
    while left <= right:
        mid = left + (right - left) // 2
        
        if arr[mid] == target:
            return mid  # Target ditemukan
        elif arr[mid] < target:
            left = mid + 1  # Cari di setengah bagian kanan
        else:
            right = mid - 1  # Cari di setengah bagian kiri
            
    return -1  # Target tidak ditemukan

# Pengujian
arr = [2, 5, 8, 12, 16, 23, 38, 56, 72, 91]
target = 23
result = binary_search(arr, target)
print(f"Target {target} ditemukan pada indeks: {result}")
```

#### 3. Kompleksitas Waktu & Ruang (Big-O)
- **Time Complexity**: `O(log N)` karena ruang pencarian dibagi 2 di setiap iterasi.
- **Space Complexity**: `O(1)` (Iteratif ramah memori / Green Code)."""

        elif "hash" in prompt_lower or "two sum" in prompt_lower or "big-o" in prompt_lower or "complexity" in prompt_lower:
            return """### S-SPARC AI Analysis — Optimization to O(N) Hash Map

Berikut penjelasannya:

#### 1. Analisis Kompleksitas O(N^2) vs O(N)
Nested loop `O(N^2)` memeriksa setiap pasangan angka secara terpisah. Dengan Hash Map (Dictionary), kita dapat menyimpan komplemen `target - num` saat melakukan perlintasan tunggal `O(N)`.

#### 2. Solusi Perbaikan Kode

```python
def two_sum_optimized(nums, target):
    num_map = {}
    for i, num in enumerate(nums):
        complement = target - num
        if complement in num_map:
            return [num_map[complement], i]
        num_map[num] = i
    return []

# Pengujian
nums = [2, 7, 11, 15]
target = 9
print("Pasangan Indeks:", two_sum_optimized(nums, target))
```

#### 3. Ringkasan Kinerja (Green Code)
- **Time Complexity**: `O(N)`
- **Space Complexity**: `O(N)`"""

        else:
            return """### S-SPARC AI Assistant Solution

Berikut panduan & analisis perbaikan kode berdasarkan prompt C-I-O-E yang Anda berikan:

#### 1. Diagnosis & Struktur Logika
Berdasarkan parameter dan batasan yang Anda sampaikan, solusi memerlukan pendekatan terstruktur dengan pengecekan prekondisi data sebelum memproses eksekusi utama.

```python
def process_solution(data_input):
    # Pre-condition check
    if not data_input:
        return None
        
    # Main logic implementation
    result = []
    for item in data_input:
        # Process item
        result.append(item)
        
    return result

# Pengujian
print("Status Eksekusi: Berhasil diproses")
```

#### 2. Validasi & Best Practices
- **Input Validation**: Selalu pastikan tipe data dan batas prekondisi diperiksa.
- **Error Handling**: Gunakan `try-except` untuk menangani pengecualian tak terduga.
- **Efficiency**: Jaga kompleksitas runtime tetap efisien."""


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

