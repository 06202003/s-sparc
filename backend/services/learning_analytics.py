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
        and independence index from all DB prompt sources (chat_history, gpt_jobs, code_embeddings).
        """
        from backend.core.db import get_db_connection, resolve_user_uuid
        from backend.services.prompt_linter import PromptLinter

        # Resolve all possible user identifiers
        user_ids = {str(user_id)}
        resolved_uid = resolve_user_uuid(user_id)
        if resolved_uid:
            user_ids.add(str(resolved_uid))
        
        raw_prompts: List[str] = []
        conn = get_db_connection()
        if conn:
            try:
                with conn.cursor() as cur:
                    # Discover all usernames, emails, IDs associated with this user
                    try:
                        cur.execute("SELECT user_id, username, email FROM user WHERE user_id=%s OR username=%s", (str(user_id), str(user_id)))
                        for r in cur.fetchall() or []:
                            if r.get('user_id'): user_ids.add(str(r['user_id']))
                            if r.get('username'): user_ids.add(str(r['username']))
                            if r.get('email'): user_ids.add(str(r['email']))
                    except Exception:
                        pass
                    
                    try:
                        cur.execute("SELECT user_id, username, email FROM users WHERE user_id=%s OR username=%s", (str(user_id), str(user_id)))
                        for r in cur.fetchall() or []:
                            if r.get('user_id'): user_ids.add(str(r['user_id']))
                            if r.get('username'): user_ids.add(str(r['username']))
                            if r.get('email'): user_ids.add(str(r['email']))
                    except Exception:
                        pass

                    u_list = list(user_ids)
                    placeholders = ','.join(['%s'] * len(u_list))

                    # 1. Fetch user prompts from chat_history
                    try:
                        cur.execute(
                            f"""
                            SELECT content FROM chat_history 
                            WHERE user_id IN ({placeholders}) AND (LOWER(role)='user' OR role IS NULL)
                            ORDER BY created_at ASC
                            """,
                            tuple(u_list)
                        )
                        for r in cur.fetchall() or []:
                            c = (r.get("content") or "").strip()
                            if c and c not in raw_prompts:
                                raw_prompts.append(c)
                    except Exception as err:
                        logger.debug(f"chat_history query notice: {err}")
                    
                    # 2. Fetch from gpt_jobs
                    try:
                        cur.execute(
                            f"""
                            SELECT prompt FROM gpt_jobs 
                            WHERE user_id IN ({placeholders}) AND prompt IS NOT NULL AND prompt != ''
                            ORDER BY created_at ASC
                            """,
                            tuple(u_list)
                        )
                        for r in cur.fetchall() or []:
                            p = (r.get("prompt") or "").strip()
                            if p and p not in raw_prompts:
                                raw_prompts.append(p)
                    except Exception as err:
                        logger.debug(f"gpt_jobs query notice: {err}")
                                
                    # 3. Fetch from code_embeddings
                    try:
                        cur.execute(
                            f"""
                            SELECT prompt FROM code_embeddings 
                            WHERE user_id IN ({placeholders}) AND prompt IS NOT NULL AND prompt != ''
                            ORDER BY created_at ASC
                            """,
                            tuple(u_list)
                        )
                        for r in cur.fetchall() or []:
                            p = (r.get("prompt") or "").strip()
                            if p and p not in raw_prompts:
                                raw_prompts.append(p)
                    except Exception as err:
                        logger.debug(f"code_embeddings query notice: {err}")
            except Exception as e:
                logger.warning(f"Error querying student profile prompts: {e}")
            finally:
                try:
                    conn.close()
                except Exception:
                    pass

        total_prompts = len(raw_prompts)
        if total_prompts == 0:
            return {
                "status": "success",
                "user_id": user_id,
                "total_prompts": 0,
                "total_prompts_submitted": 0,
                "average_prompt_quality": 0.0,
                "average_cioe_score": 0.0,
                "average_entropy": 0.0,
                "fast_path_utilization_rate": 0.0,
                "conceptual_mode_ratio": 0.0,
                "literacy_level": "Independent Scholar (Human-Only)",
                "persona_title": "The Independent Scholar",
                "cognitive_independence_index": 1.0,
                "bloom_distribution": [0, 0, 0],
                "radar_dimensions": {
                    "Context": 0,
                    "Input": 0,
                    "Output": 0,
                    "Error": 0,
                    "Vocabulary": 0
                },
                "badges": ["Welcome to S-SPARC"]
            }

        analyzed = [PromptLinter.analyze(p) for p in raw_prompts]
        
        sum_quality = sum(a.get("prompt_quality_score", 0.0) for a in analyzed)
        sum_cioe = sum(a.get("cioe_score", 0.0) for a in analyzed)
        sum_entropy = sum(a.get("shannon_entropy", 0.0) for a in analyzed)
        sum_tech = sum(a.get("technical_token_density", 0.0) for a in analyzed)

        context_count = sum(1 for a in analyzed if a.get("cioe_breakdown", {}).get("has_context"))
        input_count = sum(1 for a in analyzed if a.get("cioe_breakdown", {}).get("has_input"))
        output_count = sum(1 for a in analyzed if a.get("cioe_breakdown", {}).get("has_output"))
        error_count = sum(1 for a in analyzed if a.get("cioe_breakdown", {}).get("has_error"))

        c1c2_count = sum(1 for a in analyzed if a.get("cioe_breakdown", {}).get("has_context") and not a.get("cioe_breakdown", {}).get("has_error"))
        c3c4_count = sum(1 for a in analyzed if a.get("technical_token_density", 0.0) > 0.3)
        c5c6_count = max(0, total_prompts - (c1c2_count + c3c4_count))

        avg_quality = round(sum_quality / total_prompts, 2)
        avg_cioe = round(sum_cioe / total_prompts, 2)
        avg_entropy = round(sum_entropy / total_prompts, 2)
        conceptual_ratio = round(c1c2_count / total_prompts, 2)
        fast_path_rate = round(min(0.8, max(0.2, avg_cioe * 0.5)), 2)

        independence_index = round(min(1.0, max(0.4, (avg_quality * 0.7) + (avg_entropy * 0.3))), 2)

        if avg_quality >= 0.75:
            tier = "Tier A (Prompt Architect)"
            persona_title = "The Socratic Architect"
        elif avg_quality >= 0.55:
            tier = "Tier B (Structured Prompter)"
            persona_title = "The Algorithmic Synthesizer"
        elif avg_quality >= 0.40:
            tier = "Tier C (Developing Prompter)"
            persona_title = "The Resilient Debugger"
        else:
            tier = "Tier D (Novice Prompter)"
            persona_title = "The Direct Inquirer"

        return {
            "status": "success",
            "user_id": user_id,
            "total_prompts": total_prompts,
            "total_prompts_submitted": total_prompts,
            "average_prompt_quality": avg_quality,
            "average_cioe_score": avg_cioe,
            "average_entropy": avg_entropy,
            "fast_path_utilization_rate": fast_path_rate,
            "conceptual_mode_ratio": conceptual_ratio,
            "literacy_level": tier,
            "persona_title": persona_title,
            "cognitive_independence_index": independence_index,
            "bloom_distribution": [c1c2_count, c3c4_count, c5c6_count],
            "radar_dimensions": {
                "Context": min(100, round((context_count / total_prompts) * 100)),
                "Input": min(100, round((input_count / total_prompts) * 100)),
                "Output": min(100, round((output_count / total_prompts) * 100)),
                "Error": min(100, round((error_count / total_prompts) * 100)),
                "Vocabulary": min(100, round(avg_entropy * 100))
            },
            "badges": ["Active S-SPARC Prompter"]
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
