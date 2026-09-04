import os
import sys
import unittest
import logging

# Ensure project root is in sys.path
sys.path.insert(0, os.path.abspath(os.path.dirname(__file__)))

from backend.services.points_aggregator import PointsAggregator
from backend.services.adaptive_router import AdaptiveRouter, GeminiRateLimitExhausted

logging.basicConfig(level=logging.INFO)

class TestAdaptiveRouterArchitecture(unittest.TestCase):

    def setUp(self):
        PointsAggregator.clear_mock_points()
        os.environ["MIN_POINTS_CLOUD"] = "100"
        os.environ["POINTS_PER_CLOUD_REQUEST"] = "10"
        os.environ["OLLAMA_TIMEOUT"] = "1"

    def tearDown(self):
        PointsAggregator.clear_mock_points()


    def test_option_2_insufficient_points_routes_to_local_ollama(self):
        """
        Scenario A (Opsi 2 - Business Policy):
        Student has insufficient points (45 < 100).
        Must route directly to Local (Ollama) with 0 points deducted.
        """
        username = "student_low_points"
        PointsAggregator.set_mock_points(username, 45.0)

        router = AdaptiveRouter()
        router.ollama_client.generate = lambda msgs, model=None: "# Local Ollama solution"
        messages = [{"role": "user", "content": "Write a quicksort algorithm in Python."}]


        res = router.route_and_generate(messages, username=username)

        print("\n--- Test Scenario A (Low Points) ---")
        print(f"Routed To: {res['routed_to']}")
        print(f"Reason: {res['routing_reason']}")
        print(f"Points Before: {res['points_before']}, Deducted: {res['points_deducted']}")

        self.assertEqual(res["routed_to"], "local_ollama")
        self.assertEqual(res["points_deducted"], 0.0)
        self.assertFalse(res["fallback_triggered"])
        self.assertIn("Points", res["routing_reason"])
        self.assertIn("Routing to Local", res["routing_reason"])

    def test_sufficient_points_attempts_cloud_gemini(self):
        """
        Scenario B (Cloud Normal / Sufficient Points):
        Student has sufficient points (150 >= 100).
        Gemini Flash Lite succeeds -> routed to cloud_gemini, 10 points deducted.
        """
        username = "student_high_points"
        PointsAggregator.set_mock_points(username, 150.0)

        router = AdaptiveRouter()
        
        # Mock Gemini gateway generation success
        def mock_successful_gemini(messages, model=None):
            return "def quicksort(arr): return arr"

        router.gemini_gateway.generate = mock_successful_gemini

        messages = [{"role": "user", "content": "Hello Gemini"}]

        res = router.route_and_generate(messages, username=username)

        print("\n--- Test Scenario B (High Points + Cloud Success) ---")
        print(f"Routed To: {res['routed_to']}")
        print(f"Reason: {res['routing_reason']}")
        print(f"Points Before: {res['points_before']}, Deducted: {res['points_deducted']}")

        self.assertEqual(res["routed_to"], "cloud_gemini")
        self.assertEqual(res["points_deducted"], 10.0)
        self.assertEqual(PointsAggregator.get_user_points(username), 140.0)
        self.assertFalse(res["fallback_triggered"])

    def test_option_1_gemini_rate_limit_failover_to_ollama(self):
        """
        Scenario C (Opsi 1 - Technical Failover):
        Student has sufficient points (150 >= 100), but all 6 Gemini API keys encounter rate limits.
        Gateway triggers technical failover to Local (Ollama) with 0 points deducted.
        """
        username = "student_rate_limit_test"
        PointsAggregator.set_mock_points(username, 150.0)

        router = AdaptiveRouter()
        router.ollama_client.generate = lambda msgs, model=None: "# Local Ollama fallback solution"
        
        # Mock Gemini gateway to simulate rate limit on all 6 keys

        def mock_failing_generate(messages, model=None):
            raise GeminiRateLimitExhausted("Simulated 429 Rate Limit across all 6 Gemini keys")

        router.gemini_gateway.generate = mock_failing_generate

        messages = [{"role": "user", "content": "Write a binary search algorithm."}]
        res = router.route_and_generate(messages, username=username)

        print("\n--- Test Scenario C (Gemini Rate Limit Failover) ---")
        print(f"Routed To: {res['routed_to']}")
        print(f"Reason: {res['routing_reason']}")
        print(f"Fallback Triggered: {res['fallback_triggered']}")
        print(f"Points Before: {res['points_before']}, Deducted: {res['points_deducted']}")

        self.assertEqual(res["routed_to"], "local_ollama")
        self.assertEqual(res["points_deducted"], 0.0)
        self.assertTrue(res["fallback_triggered"])
        self.assertIn("rate limited", res["routing_reason"].lower())
        # Verify points were NOT deducted due to technical failover
        self.assertEqual(PointsAggregator.get_user_points(username), 150.0)

    def test_game_off_uses_token_quota_limit(self):
        """
        Scenario D (Course Game Feature OFF):
        Student has 0 gamification points, but course game feature is OFF.
        - Under Token Quota Limit (1200 < 5000): Granted Cloud (Gemini) with 0 points deducted.
        - Exceeds Token Quota Limit (5500 >= 5000): Routed to Local (Ollama) with 0 points deducted.
        """
        username_under = "student_game_off_under_quota"
        username_over = "student_game_off_over_quota"
        PointsAggregator.set_mock_points(username_under, 0.0)
        PointsAggregator.set_mock_points(username_over, 0.0)
        PointsAggregator.set_mock_token_usage(username_under, 1200)
        PointsAggregator.set_mock_token_usage(username_over, 5500)

        # Mock is_game_active_for_assessment to return False (Game OFF)
        original_check = PointsAggregator.is_game_active_for_assessment
        PointsAggregator.is_game_active_for_assessment = lambda assessment_id=None, course_id=None: False

        try:
            router = AdaptiveRouter()
            router.gemini_gateway.generate = lambda msgs, model=None: "def cloud_solution(): pass"
            router.ollama_client.generate = lambda msgs, model=None: "def local_solution(): pass"

            messages = [{"role": "user", "content": "Explain binary tree traversal"}]
            
            # Sub-test 1: Under quota -> Cloud
            res_under = router.route_and_generate(messages, username=username_under, course_id="course_game_off")
            print("\n--- Test Scenario D1 (Game OFF + Under Quota 1200/5000) ---")
            print(f"Routed To: {res_under['routed_to']}")
            print(f"Reason: {res_under['routing_reason']}")
            print(f"Points Deducted: {res_under['points_deducted']}")

            self.assertEqual(res_under["routed_to"], "cloud_gemini")
            self.assertEqual(res_under["points_deducted"], 0.0)

            # Sub-test 2: Over quota -> Local Ollama
            res_over = router.route_and_generate(messages, username=username_over, course_id="course_game_off")
            print("\n--- Test Scenario D2 (Game OFF + Exceeded Quota 5500/5000) ---")
            print(f"Routed To: {res_over['routed_to']}")
            print(f"Reason: {res_over['routing_reason']}")
            print(f"Points Deducted: {res_over['points_deducted']}")

            self.assertEqual(res_over["routed_to"], "local_ollama")
            self.assertEqual(res_over["points_deducted"], 0.0)
            self.assertIn("Kuota token", res_over["routing_reason"])
        finally:
            PointsAggregator.is_game_active_for_assessment = original_check

if __name__ == "__main__":
    unittest.main()


