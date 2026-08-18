import os
import json
import logging
from typing import Dict, Any, List, Optional
from datetime import datetime

logger = logging.getLogger("ssparc.learning_analytics")

class LearningAnalyticsService:
    """
    Manages student educational progress telemetry, cognitive progression tracking,
    AI Literacy levels, and research experiment aggregation.
    """

    # In-memory session store fallback if MySQL is offline or during testing
    _in_memory_logs: List[Dict[str, Any]] = []

    @classmethod
    def record_learning_event(
        cls,
        session_id: str,
        user_id: str,
        prompt_analysis: Dict[str, Any],
        bloom_mode: str,
        is_fast_path: bool,
        tokens_consumed: int,
        latency_ms: float,
        sustainability_telemetry: Optional[Dict[str, Any]] = None,
        course_id: Optional[int] = None
    ) -> Dict[str, Any]:
        """
        Records a discrete educational interaction event.
        """
        sust = sustainability_telemetry or {}
        event_record = {
            "session_id": session_id,
            "user_id": user_id or "anonymous",
            "course_id": course_id,
            "timestamp": datetime.utcnow().isoformat(),
            "prompt_length": prompt_analysis.get("prompt_length", 0),
            "prompt_quality_score": prompt_analysis.get("prompt_quality_score", 0.0),
            "shannon_entropy": prompt_analysis.get("shannon_entropy", 0.0),
            "cioe_components_present": prompt_analysis.get("cioe_breakdown", {}).get("components_present", 0),
            "bloom_cognitive_mode": bloom_mode,
            "is_fast_path_cache_hit": 1 if is_fast_path else 0,
            "tokens_consumed": tokens_consumed,
            "latency_ms": latency_ms,
            "energy_wh": sust.get("energy_wh", 0.0),
            "carbon_g_co2e": sust.get("carbon_g_co2e", 0.0),
            "water_ml": sust.get("water_ml", 0.0)
        }

        cls._in_memory_logs.append(event_record)

        # Attempt to persist to MySQL if database service is available
        try:
            from backend.services.db_service import execute_query
            query = """
            INSERT INTO educational_learning_logs 
            (session_id, user_id, course_id, prompt_length, prompt_quality_score, 
             shannon_entropy, cioe_components_present, bloom_cognitive_mode, 
             is_fast_path_cache_hit, tokens_consumed, latency_ms, energy_wh, carbon_g_co2e, water_ml)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            params = (
                event_record["session_id"],
                event_record["user_id"],
                event_record["course_id"],
                event_record["prompt_length"],
                event_record["prompt_quality_score"],
                event_record["shannon_entropy"],
                event_record["cioe_components_present"],
                event_record["bloom_cognitive_mode"],
                event_record["is_fast_path_cache_hit"],
                event_record["tokens_consumed"],
                event_record["latency_ms"],
                event_record["energy_wh"],
                event_record["carbon_g_co2e"],
                event_record["water_ml"]
            )
            execute_query(query, params)
        except Exception as e:
            logger.debug(f"Async DB logging fallback to memory: {e}")

        return event_record

    @classmethod
    def get_student_profile(cls, user_id: str) -> Dict[str, Any]:
        """
        Calculates student-specific AI literacy profile, cognitive progression,
        and independence index.
        """
        user_events = [e for e in cls._in_memory_logs if e["user_id"] == user_id]
        
        # If in-memory is empty, attempt DB query
        if not user_events:
            try:
                from backend.services.db_service import fetch_all
                query = "SELECT * FROM educational_learning_logs WHERE user_id = %s ORDER BY timestamp ASC"
                user_events = fetch_all(query, (user_id,)) or []
            except Exception:
                user_events = []

        total_prompts = len(user_events)
        if total_prompts == 0:
            return {
                "user_id": user_id,
                "total_prompts_submitted": 0,
                "average_prompt_quality": 0.0,
                "average_cioe_score": 0.0,
                "fast_path_utilization_rate": 0.0,
                "conceptual_mode_ratio": 0.0,
                "literacy_level": "Novice Prompter",
                "cognitive_independence_index": 0.0,
                "badges": ["Welcome to S-SPARC"]
            }

        avg_quality = round(sum(e.get("prompt_quality_score", 0.0) for e in user_events) / total_prompts, 2)
        avg_cioe = round(sum(e.get("cioe_components_present", 0) for e in user_events) / (4.0 * total_prompts), 2)
        fast_path_hits = sum(1 for e in user_events if e.get("is_fast_path_cache_hit", 0) == 1)
        fast_path_rate = round(fast_path_hits / total_prompts, 2)
        
        conceptual_modes = sum(1 for e in user_events if e.get("bloom_cognitive_mode", "") in ("summary", "summary_only"))
        conceptual_ratio = round(conceptual_modes / total_prompts, 2)

        # Cognitive Independence Index: High quality prompt + conceptual guidance + cache reuse
        independence_index = round((0.40 * avg_quality) + (0.35 * avg_cioe) + (0.25 * conceptual_ratio), 2)

        # Dynamic Badges
        badges = []
        if avg_cioe >= 0.75:
            badges.append("C-I-O-E Protocol Master")
        if avg_quality >= 0.80:
            badges.append("Prompt Architect")
        if fast_path_rate >= 0.40:
            badges.append("Zero-Waste Compute Champion")
        if conceptual_ratio >= 0.30:
            badges.append("Conceptual Learner")
        if not badges:
            badges.append("Developing AI Prompter")

        if independence_index >= 0.80:
            level = "Master AI Architect"
        elif independence_index >= 0.60:
            level = "Autonomous AI Learner"
        elif independence_index >= 0.40:
            level = "Structured Prompter"
        else:
            level = "Novice Prompter"

        return {
            "user_id": user_id,
            "total_prompts_submitted": total_prompts,
            "average_prompt_quality": avg_quality,
            "average_cioe_score": avg_cioe,
            "fast_path_utilization_rate": fast_path_rate,
            "conceptual_mode_ratio": conceptual_ratio,
            "literacy_level": level,
            "cognitive_independence_index": independence_index,
            "badges": badges
        }

    @classmethod
    def get_class_analytics_summary(cls, course_id: Optional[int] = None) -> Dict[str, Any]:
        """
        Generates aggregated educational metrics for faculty and UNU competition empirical evidence.
        """
        events = cls._in_memory_logs
        if not events:
            try:
                from backend.services.db_service import fetch_all
                query = "SELECT * FROM educational_learning_logs ORDER BY timestamp DESC LIMIT 1000"
                events = fetch_all(query) or []
            except Exception:
                events = []

        total_interactions = len(events)
        if total_interactions == 0:
            return {
                "total_student_interactions": 0,
                "average_cioe_adherence": "0%",
                "average_prompt_density_score": 0.0,
                "bloom_mode_distribution": {"summary": 0, "code": 0, "summary_code_explanation": 0},
                "zero_token_fast_path_ratio": "0%",
                "estimated_cloud_token_savings": "0 tokens",
                "empirical_evidence_summary": "Ready for live classroom logging."
            }

        avg_cioe = round(sum(e.get("cioe_components_present", 0) for e in events) / (4.0 * total_interactions) * 100, 1)
        avg_density = round(sum(e.get("prompt_quality_score", 0.0) for e in events) / total_interactions, 2)
        fast_path_count = sum(1 for e in events if e.get("is_fast_path_cache_hit", 0) == 1)
        fast_path_pct = round(fast_path_count / total_interactions * 100, 1)

        bloom_dist = {
            "summary": sum(1 for e in events if e.get("bloom_cognitive_mode") in ("summary", "summary_only")),
            "code": sum(1 for e in events if e.get("bloom_cognitive_mode") in ("code", "code_only")),
            "summary_code_explanation": sum(1 for e in events if e.get("bloom_cognitive_mode") in ("summary_code_explanation", "all"))
        }

        # Calculate estimated token savings compared to conventional 3,250 token uncompressed queries
        uncompressed_baseline = total_interactions * 3250
        actual_tokens_spent = sum(e.get("tokens_consumed", 0) for e in events)
        tokens_saved = max(0, uncompressed_baseline - actual_tokens_spent)

        return {
            "total_student_interactions": total_interactions,
            "average_cioe_adherence": f"{avg_cioe}%",
            "average_prompt_density_score": avg_density,
            "bloom_mode_distribution": bloom_dist,
            "zero_token_fast_path_ratio": f"{fast_path_pct}%",
            "estimated_cloud_token_savings": f"{tokens_saved:,} tokens ({(tokens_saved/max(1, uncompressed_baseline))*100:.1f}% reduction)",
            "empirical_evidence_summary": (
                f"Empirical telemetry across {total_interactions} interactions demonstrates a "
                f"{avg_cioe}% C-I-O-E adherence rate, {fast_path_pct}% zero-token cache hit rate, "
                f"and an average prompt information density score of {avg_density}/1.0."
            )
        }
