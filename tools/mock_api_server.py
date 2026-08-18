#!/usr/bin/env python3
"""
tools/mock_api_server.py
Standalone mock HTTP server serving the exact locked JSON schema for Track A testing.
Used as a contract-compliant fixture layer for Phase A3 when FastAPI backend is not running.
"""

import json
import http.server
import socketserver
import urllib.parse

PORT = 5000

MOCK_COURSES = {
    "courses": [
        {"course_id": "1", "name": "Algoritma & Struktur Data", "description": "Dasar-dasar algoritma pemrograman"},
        {"course_id": "2", "name": "Pemrograman Berorientasi Objek", "description": "Konsep OOP dengan Java dan Python"},
        {"course_id": "3", "name": "Basis Data Relasional", "description": "Perancangan dan query SQL database"}
    ]
}

MOCK_ASSESSMENTS = {
    "assessments": [
        {
            "assessment_id": "101",
            "course_id": "1",
            "name": "Tugas 1: Linked List & Queue",
            "description": "Implementasikan queue dengan doubly-linked list dalam bahasa Python atau C++.",
            "submission_file_extension": "py,cpp",
            "end_date": "2026-08-30 23:59:59"
        },
        {
            "assessment_id": "102",
            "course_id": "2",
            "name": "Tugas 2: Inheritance & Polymorphism",
            "description": "Buat class hierarchy untuk sistem perbankan.",
            "submission_file_extension": "java",
            "end_date": "2026-08-25 23:59:59"
        }
    ]
}

class MockAPIHandler(http.server.BaseHTTPRequestHandler):
    def _set_headers(self, status=200):
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, Authorization, X-User-ID")
        self.end_headers()

    def do_OPTIONS(self):
        self._set_headers(204)

    def do_GET(self):
        parsed = urllib.parse.urlparse(self.path)
        path = parsed.path

        if path == "/health":
            self._set_headers(200)
            self.wfile.write(json.dumps({"status": "ok", "mode": "mock_fixture"}).encode("utf-8"))
            return

        if path in ("/api/courses", "/courses"):
            self._set_headers(200)
            self.wfile.write(json.dumps(MOCK_COURSES).encode("utf-8"))
            return

        if path in ("/api/assessments", "/assessments"):
            self._set_headers(200)
            self.wfile.write(json.dumps(MOCK_ASSESSMENTS).encode("utf-8"))
            return

        if path in ("/api/gamification", "/gamification"):
            self._set_headers(200)
            response_data = {
                "gamification": {
                    "user_id": "student_01",
                    "cumulative_tokens_used": 450,
                    "dynamic_threshold": 2500.0,
                    "peer_avg_usage": 1820.5,
                    "remaining_quota": 2050,
                    "points": 100.0,
                    "tier": "Gold Eco-Coder",
                    "history": [
                        {"date": "2026-08-01", "tokens": 120},
                        {"date": "2026-08-02", "tokens": 80},
                        {"date": "2026-08-03", "tokens": 150},
                        {"date": "2026-08-04", "tokens": 100}
                    ]
                }
            }
            self.wfile.write(json.dumps(response_data).encode("utf-8"))
            return

        if path in ("/api/environmental/footprint", "/environmental/footprint", "/api/footprint"):
            self._set_headers(200)
            response_data = {
                "footprint": {
                    "total_energy_wh": 142.85,
                    "total_energy_kwh": 0.14285,
                    "carbon_footprint_kg": 0.05485,
                    "cooling_water_ml": 664.25,
                    "cooling_water_l": 0.66425,
                    "daily_avg_kwh": 0.00476,
                    "equivalents": {
                        "smartphone_charges": 11.9,
                        "led_bulb_hours": 15.87,
                        "kettle_boils": 0.095,
                        "car_travel_km": 0.285,
                        "tree_absorption_days": 0.954,
                        "shower_minutes": 0.074
                    }
                }
            }
            self.wfile.write(json.dumps(response_data).encode("utf-8"))
            return

        self._set_headers(404)
        self.wfile.write(json.dumps({"error": f"Endpoint '{path}' not found in mock server"}).encode("utf-8"))

    def do_POST(self):
        parsed = urllib.parse.urlparse(self.path)
        path = parsed.path

        if path in ("/api/generate-code", "/generate-code", "/api/chat"):
            content_len = int(self.headers.get("Content-Length", 0))
            body = self.rfile.read(content_len).decode("utf-8") if content_len > 0 else "{}"
            try:
                data = json.loads(body)
            except Exception:
                data = {}

            prompt = data.get("prompt", "").lower()
            # Simulate FREE tier cache hit for common queries (e.g. factorial, fibonacci)
            is_cache_hit = "factorial" in prompt or "fibonacci" in prompt or "linked list" in prompt

            if is_cache_hit:
                resp = {
                    "status": "completed",
                    "job_id": "mock-cache-hit-001",
                    "response": "# [Cached Response - FREE Tier]\ndef solve():\n    return 'High similarity cache hit from database.'",
                    "is_retrieval_hit": True,
                    "similarity_score": 0.965,
                    "request_tokens_used": 0,
                    "token_info": {
                        "session_cumulative_tokens": 120,
                        "session_remaining_tokens": 2630,
                        "dynamic_threshold": 2750.0,
                        "current_points": 100.0
                    }
                }
            else:
                resp = {
                    "status": "completed",
                    "job_id": "mock-inference-002",
                    "response": "# Generated Solution\ndef solve():\n    # Process query\n    return 'Fresh inferenced response'",
                    "is_retrieval_hit": False,
                    "similarity_score": 0.450,
                    "request_tokens_used": 45,
                    "token_info": {
                        "session_cumulative_tokens": 165,
                        "session_remaining_tokens": 2585,
                        "dynamic_threshold": 2750.0,
                        "current_points": 100.0
                    }
                }

            self._set_headers(200)
            self.wfile.write(json.dumps(resp).encode("utf-8"))
            return

        self._set_headers(404)
        self.wfile.write(json.dumps({"error": f"POST '{path}' not found in mock server"}).encode("utf-8"))

def run_mock_server():
    with socketserver.TCPServer(("", PORT), MockAPIHandler) as httpd:
        print(f"Mock API Server running on http://127.0.0.1:{PORT}")
        try:
            httpd.serve_forever()
        except KeyboardInterrupt:
            print("\nShutting down Mock API Server...")

if __name__ == "__main__":
    run_mock_server()
