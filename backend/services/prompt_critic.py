import re
import csv
import io
import math
import logging
from typing import Dict, Any, List, Optional
from datetime import datetime

from backend.core.db import get_db_connection, resolve_user_uuid, resolve_assessment_id
from backend.services.prompt_linter import PromptLinter

logger = logging.getLogger("ssparc.prompt_critic")

class PromptCriticService:
    """
    Synthesizes student prompt telemetry during an assessment into a
    Spotify-Wrapped style interactive report with AI Critic reviews and
    research data mining metrics.
    """

    @classmethod
    def get_assessment_wrapped(cls, user_id: str, assessment_id: str) -> Dict[str, Any]:
        resolved_uid = resolve_user_uuid(user_id)
        resolved_aid = resolve_assessment_id(assessment_id)
        
        conn = get_db_connection()
        if conn is None:
            return cls._get_mock_wrapped(user_id, assessment_id)
        
        try:
            with conn.cursor() as cur:
                # 1. Fetch Assessment & Course Metadata and check expiration status
                assessment_title = f"Assessment #{assessment_id}"
                course_name = "Pemrograman Komputer"
                course_id = None
                is_expired = False
                due_date_str = ""

                try:
                    cur.execute(
                        """
                        SELECT a.assessment_id, a.course_id, a.name AS assessment_name, 
                               COALESCE(c.name, '') AS course_name, 
                               a.submission_close_time AS due_date,
                               (a.submission_close_time < NOW()) AS is_closed
                        FROM assessment a
                        LEFT JOIN course c ON c.course_id = a.course_id
                        WHERE a.assessment_id = %s LIMIT 1
                        """,
                        (resolved_aid or assessment_id,)
                    )
                    a_row = cur.fetchone()
                    if a_row:
                        assessment_title = a_row.get('assessment_name') or assessment_title
                        course_name = a_row.get('course_name') or course_name
                        course_id = a_row.get('course_id')
                        is_expired = bool(a_row.get('is_closed'))
                        due_date_str = str(a_row.get('due_date') or '')
                except Exception as e_a:
                    logger.warning(f"Error fetching assessment metadata: {e_a}")

                # If assessment is not yet expired, lock the wrapped access
                if not is_expired and due_date_str:
                    return {
                        "status": "locked",
                        "is_expired": False,
                        "assessment_id": assessment_id,
                        "assessment_title": assessment_title,
                        "course_name": course_name,
                        "due_date": due_date_str,
                        "message": "S-SPARC Wrapped is locked while the assessment is active. It automatically unlocks once the assessment submission window officially closes."
                    }

                # 2. Fetch Prompts & Chats for this Assessment
                cur.execute(
                    """
                    SELECT id, role, content, created_at 
                    FROM chat_history 
                    WHERE (user_id=%s OR user_id=%s) 
                      AND (assessment_id=%s OR assessment_id=%s)
                    ORDER BY created_at ASC
                    """,
                    (user_id, resolved_uid, assessment_id, resolved_aid)
                )
                chat_rows = cur.fetchall() or []

                # Fallback to recent user prompts if assessment_id tagging was empty
                if not chat_rows:
                    cur.execute(
                        """
                        SELECT id, role, content, created_at 
                        FROM chat_history 
                        WHERE (user_id=%s OR user_id=%s) 
                        ORDER BY created_at DESC LIMIT 20
                        """,
                        (user_id, resolved_uid)
                    )
                    chat_rows = cur.fetchall() or []
                    chat_rows.reverse()

                # Extract user prompts
                user_prompts: List[Dict[str, Any]] = []
                for row in chat_rows:
                    if row.get('role') == 'user':
                        p_text = (row.get('content') or '').strip()
                        if p_text:
                            analysis = PromptLinter.analyze(p_text)
                            user_prompts.append({
                                "id": str(row.get('id')),
                                "prompt": p_text,
                                "timestamp": str(row.get('created_at')),
                                "analysis": analysis
                            })

                # 3. Fetch Token & BYOK Environmental Footprint
                total_tokens_used = 0
                tokens_saved = 0
                try:
                    cur.execute(
                        """
                        SELECT COALESCE(SUM(tokens_used), 0) AS total_tokens 
                        FROM session_tokens 
                        WHERE (user_id=%s OR user_id=%s) 
                          AND (assessment_id=%s OR assessment_id=%s)
                        """,
                        (user_id, resolved_uid, assessment_id, resolved_aid)
                    )
                    t_row = cur.fetchone()
                    if t_row:
                        total_tokens_used = int(t_row.get('total_tokens') or 0)
                except Exception:
                    pass

                # Fast-path cache hits from gpt_jobs
                fast_path_hits = 0
                try:
                    cur.execute(
                        """
                        SELECT COUNT(*) AS fp_count 
                        FROM gpt_jobs 
                        WHERE (user_id=%s OR user_id=%s) AND similarity >= 0.88
                        """,
                        (user_id, resolved_uid)
                    )
                    fp_row = cur.fetchone()
                    if fp_row:
                        fast_path_hits = int(fp_row.get('fp_count') or 0)
                        tokens_saved = fast_path_hits * 450
                except Exception:
                    pass

            return cls._compile_wrapped_payload(
                user_id=user_id,
                assessment_id=assessment_id,
                assessment_title=assessment_title,
                course_name=course_name,
                user_prompts=user_prompts,
                total_tokens_used=total_tokens_used,
                tokens_saved=tokens_saved,
                fast_path_hits=fast_path_hits
            )

        except Exception as e:
            logger.error(f"Error generating assessment wrapped: {e}")
            return cls._get_mock_wrapped(user_id, assessment_id)
        finally:
            try:
                conn.close()
            except Exception:
                pass

    @classmethod
    def _compile_wrapped_payload(
        cls,
        user_id: str,
        assessment_id: str,
        assessment_title: str,
        course_name: str,
        user_prompts: List[Dict[str, Any]],
        total_tokens_used: int,
        tokens_saved: int,
        fast_path_hits: int
    ) -> Dict[str, Any]:
        total_prompts = len(user_prompts)
        
        # Fallback if student made no prompts
        if total_prompts == 0:
            return cls._get_empty_wrapped(assessment_id, assessment_title, course_name)

        # Calculate Averages & Dimensions
        avg_entropy = sum(p['analysis']['shannon_entropy'] for p in user_prompts) / total_prompts
        avg_tech = sum(p['analysis']['technical_token_density'] for p in user_prompts) / total_prompts
        avg_cioe_score = sum(p['analysis']['cioe_score'] for p in user_prompts) / total_prompts
        avg_literacy_score = sum(p['analysis']['prompt_quality_score'] for p in user_prompts) / total_prompts
        
        context_count = sum(1 for p in user_prompts if p['analysis']['cioe_breakdown']['has_context'])
        input_count = sum(1 for p in user_prompts if p['analysis']['cioe_breakdown']['has_input'])
        output_count = sum(1 for p in user_prompts if p['analysis']['cioe_breakdown']['has_output'])
        error_count = sum(1 for p in user_prompts if p['analysis']['cioe_breakdown']['has_error'])

        context_pct = round((context_count / total_prompts) * 100)
        input_pct = round((input_count / total_prompts) * 100)
        output_pct = round((output_count / total_prompts) * 100)
        error_pct = round((error_count / total_prompts) * 100)

        # Determine Persona / Archetype (Clean naming, no emoji)
        persona = cls._determine_persona(
            avg_cioe=avg_cioe_score,
            avg_entropy=avg_entropy,
            avg_tech=avg_tech,
            fast_path_hits=fast_path_hits,
            error_pct=error_pct,
            total_prompts=total_prompts
        )

        # Find Best & Needs Polish Prompts
        sorted_prompts = sorted(user_prompts, key=lambda x: x['analysis']['prompt_quality_score'], reverse=True)
        best_item = sorted_prompts[0]
        worst_item = sorted_prompts[-1] if len(sorted_prompts) > 1 else best_item

        # Generate Critique & Rewrites
        critic_review = cls._generate_critic_review(best_item, worst_item)

        # BYOK Environmental Sustainability metrics (0.35 Wh/1k tokens, 0.475g CO2/Wh)
        byok_energy_wh = round((total_tokens_used / 1000.0) * 0.35, 3)
        byok_carbon_g = round(byok_energy_wh * 0.475, 3)
        byok_water_ml = round((total_tokens_used / 1000.0) * 1.8, 2)
        sustainability_rating = "Sustainable / Eco-Conscious" if tokens_saved > total_tokens_used else "Standard Model Execution"

        # AI Literacy Tier
        if avg_literacy_score >= 0.80:
            literacy_tier = "Tier A (Prompt Architect)"
            tier_badge = "Tier A"
            badge_color = "#10B981"
        elif avg_literacy_score >= 0.60:
            literacy_tier = "Tier B (Structured Prompter)"
            tier_badge = "Tier B"
            badge_color = "#3B82F6"
        elif avg_literacy_score >= 0.40:
            literacy_tier = "Tier C (Developing Prompter)"
            tier_badge = "Tier C"
            badge_color = "#F59E0B"
        else:
            literacy_tier = "Tier D (Novice Prompter)"
            tier_badge = "Tier D"
            badge_color = "#EF4444"

        # Actionable Next Steps
        action_items = cls._generate_action_items(context_pct, input_pct, output_pct, avg_entropy)

        return {
            "status": "success",
            "is_expired": True,
            "assessment_id": assessment_id,
            "assessment_title": assessment_title,
            "course_name": course_name,
            "summary": {
                "total_prompts": total_prompts,
                "total_tokens_used": total_tokens_used,
                "tokens_saved_fastpath": tokens_saved,
                "fast_path_hits": fast_path_hits,
                "overall_score": round(avg_literacy_score * 100, 1),
                "literacy_tier": literacy_tier,
                "tier_badge": tier_badge,
                "badge_color": badge_color
            },
            "persona": persona,
            "dimensions": {
                "shannon_entropy": round(avg_entropy, 2),
                "technical_density": round(avg_tech * 100, 1),
                "cioe_completeness": round(avg_cioe_score * 100, 1),
                "radar": {
                    "Context": context_pct,
                    "Input": input_pct,
                    "Output": output_pct,
                    "Error": error_pct,
                    "Vocabulary": round(avg_entropy * 100)
                }
            },
            "critic_room": {
                "best_prompt": {
                    "text": best_item['prompt'],
                    "score": round(best_item['analysis']['prompt_quality_score'] * 100, 1),
                    "entropy": best_item['analysis']['shannon_entropy'],
                    "cioe_score": round(best_item['analysis']['cioe_score'] * 100, 1),
                    "why_stellar": best_item['analysis']['feedback'][0] if best_item['analysis']['feedback'] else "Complete structure with clear technical specifications and constraints."
                },
                "needs_polish_prompt": {
                    "text": worst_item['prompt'],
                    "score": round(worst_item['analysis']['prompt_quality_score'] * 100, 1),
                    "entropy": worst_item['analysis']['shannon_entropy'],
                    "cioe_score": round(worst_item['analysis']['cioe_score'] * 100, 1),
                    "weaknesses": worst_item['analysis']['feedback'],
                    "ai_critic_comment": critic_review['critic_comment'],
                    "suggested_rewrite": critic_review['suggested_rewrite']
                }
            },
            "byok_sustainability": {
                "energy_wh": byok_energy_wh,
                "carbon_g": byok_carbon_g,
                "water_ml": byok_water_ml,
                "rating": sustainability_rating,
                "fast_path_ratio": round((fast_path_hits / max(1, total_prompts)) * 100, 1)
            },
            "action_items": action_items
        }

    @staticmethod
    def _determine_persona(avg_cioe: float, avg_entropy: float, avg_tech: float, fast_path_hits: int, error_pct: int, total_prompts: int) -> Dict[str, Any]:
        if avg_cioe >= 0.70 and avg_entropy >= 0.70:
            return {
                "title": "The Socratic Architect",
                "tagline": "Master of Context & Mathematical Precision",
                "description": "Constructs comprehensive cognitive frameworks with rigorous technical specifications, input bounds, and algorithmic constraints.",
                "power_stat": "95% C-I-O-E Protocol Adherence"
            }
        elif fast_path_hits >= 2 or fast_path_hits >= total_prompts * 0.4:
            return {
                "title": "The Fast-Path Prodigy",
                "tagline": "Zero-Token Semantic Cache Master",
                "description": "Skillfully triggers semantic vector cache hits, maximizing response turnaround while eliminating redundant cloud compute.",
                "power_stat": f"{fast_path_hits}x Zero-Token Cache Hits"
            }
        elif error_pct >= 40:
            return {
                "title": "The Bug Hunter",
                "tagline": "Precision Debugger & Traceback Striker",
                "description": "Excels at dissecting exceptions and root causes by supplying explicit tracebacks, line references, and failure scenarios.",
                "power_stat": f"{error_pct}% Error Investigation Rate"
            }
        elif avg_tech >= 0.50:
            return {
                "title": "The Code Craftsman",
                "tagline": "Type-Safe & Algorithmic Thinker",
                "description": "Prompts are dense with explicit type annotations, Big-O complexities, and advanced data structure terminology.",
                "power_stat": f"{round(avg_tech * 100)}% Technical Token Density"
            }
        elif total_prompts >= 8:
            return {
                "title": "The Speedrunner",
                "tagline": "Rapid Iteration & Dynamic Explorer",
                "description": "Iterates rapidly, testing computational hypotheses dynamically through continuous short-turn feedback loops.",
                "power_stat": f"{total_prompts} Continuous Interactions"
            }
        else:
            return {
                "title": "The Developing Prompter",
                "tagline": "Rising AI Literacy Explorer",
                "description": "Building foundational structured prompting habits. Adding explicit context and input bounds will yield higher precision.",
                "power_stat": "Growing Computational Decomposition"
            }

    @classmethod
    def _generate_critic_review(cls, best_item: Dict[str, Any], worst_item: Dict[str, Any]) -> Dict[str, str]:
        p_text = worst_item['prompt']
        analysis = worst_item['analysis']
        missing_parts = []
        if not analysis['cioe_breakdown']['has_context']:
            missing_parts.append("Language/Framework Context")
        if not analysis['cioe_breakdown']['has_input']:
            missing_parts.append("Input/Pre-condition Specifications")
        if not analysis['cioe_breakdown']['has_output']:
            missing_parts.append("Output/Complexity Constraints")

        critic_comment = (
            f"This prompt appears overly abstract because it lacks {', '.join(missing_parts) if missing_parts else 'in-depth technical constraints'}. "
            f"The AI tends to provide generic responses unless you anchor input data types, O(N) complexity constraints, and pre-conditions."
        )

        suggested_rewrite = (
            f"[CONTEXT: Python 3 / Algorithms]\n"
            f"[INPUT: Integer array of size N <= 10^5, randomized values]\n"
            f"[OUTPUT: Return minimum integer with O(N log N) target complexity]\n"
            f"Query: What is the optimal implementation to solve '{p_text[:80]}...'?"
        )

        return {
            "critic_comment": critic_comment,
            "suggested_rewrite": suggested_rewrite
        }

    @staticmethod
    def _generate_action_items(context_pct: int, input_pct: int, output_pct: int, avg_entropy: float) -> List[str]:
        actions = []
        if context_pct < 60:
            actions.append("Anchor Context First: State the programming language, runtime version, or module topic in the initial line.")
        if input_pct < 60:
            actions.append("Specify Input Constraints: Detail parameter types and size limits (e.g., N <= 10^5) for precise algorithmic targeting.")
        if output_pct < 60:
            actions.append("Define Complexity Targets: Include expected output data structures and runtime/space limits (e.g., O(1) space, O(N) time).")
        if avg_entropy < 0.60:
            actions.append("Enrich Technical Vocabulary: Use standard data structure terminology over generalized descriptions.")
        
        if len(actions) < 3:
            actions.append("Maintain C-I-O-E Discipline: Your prompt composition is mature—maintain this standard in subsequent assessments!")

        return actions[:3]

    @classmethod
    def get_cohort_research_analytics(cls, course_id: Optional[str] = None, assessment_id: Optional[str] = None) -> Dict[str, Any]:
        """
        Aggregates class cohort telemetry for lecturer / researcher dashboard.
        """
        conn = get_db_connection()
        if conn is None:
            return {"status": "error", "message": "Database offline"}

        try:
            with conn.cursor() as cur:
                # 1. Fetch distinct students who participated in this assessment
                query = """
                    SELECT DISTINCT u.user_id, u.username, COALESCE(u.name, u.username) AS full_name
                    FROM users u
                    INNER JOIN chat_history ch ON ch.user_id = u.user_id
                    WHERE (%s IS NULL OR ch.assessment_id = %s)
                """
                cur.execute(query, (assessment_id, assessment_id))
                students = cur.fetchall() or []

                student_records = []
                archetype_counts: Dict[str, int] = {}
                tier_counts = {"Tier A": 0, "Tier B": 0, "Tier C": 0, "Tier D": 0}
                total_class_prompts = 0
                total_class_tokens = 0
                total_class_wh = 0.0
                total_class_carbon = 0.0

                context_scores = []
                input_scores = []
                output_scores = []
                error_scores = []
                entropy_scores = []

                for s in students:
                    uid = s['user_id']
                    wrapped_data = cls.get_assessment_wrapped(uid, assessment_id or "all")
                    if wrapped_data.get("status") == "success":
                        summary = wrapped_data['summary']
                        persona = wrapped_data['persona']
                        dims = wrapped_data['dimensions']
                        byok = wrapped_data['byok_sustainability']
                        tier_badge = summary.get('tier_badge', 'Tier C')

                        student_records.append({
                            "user_id": uid,
                            "nim": s.get('username') or uid[:8],
                            "name": s.get('full_name') or 'Mahasiswa',
                            "total_prompts": summary['total_prompts'],
                            "cioe_score": dims['cioe_completeness'],
                            "shannon_entropy": dims['shannon_entropy'],
                            "archetype": persona['title'],
                            "literacy_tier": tier_badge,
                            "energy_wh": byok['energy_wh'],
                            "carbon_g": byok['carbon_g']
                        })

                        # Aggregates
                        total_class_prompts += summary['total_prompts']
                        total_class_tokens += summary['total_tokens_used']
                        total_class_wh += byok['energy_wh']
                        total_class_carbon += byok['carbon_g']

                        arch_title = persona['title']
                        archetype_counts[arch_title] = archetype_counts.get(arch_title, 0) + 1
                        if tier_badge in tier_counts:
                            tier_counts[tier_badge] += 1

                        radar = dims['radar']
                        context_scores.append(radar.get('Context', 0))
                        input_scores.append(radar.get('Input', 0))
                        output_scores.append(radar.get('Output', 0))
                        error_scores.append(radar.get('Error', 0))
                        entropy_scores.append(radar.get('Vocabulary', 0))

                num_students = max(1, len(student_records))
                avg_cioe = round(sum(r['cioe_score'] for r in student_records) / num_students, 1) if student_records else 0.0
                avg_entropy = round(sum(r['shannon_entropy'] for r in student_records) / num_students, 2) if student_records else 0.0

                cohort_radar = {
                    "Context": round(sum(context_scores) / num_students, 1) if context_scores else 0.0,
                    "Input": round(sum(input_scores) / num_students, 1) if input_scores else 0.0,
                    "Output": round(sum(output_scores) / num_students, 1) if output_scores else 0.0,
                    "Error": round(sum(error_scores) / num_students, 1) if error_scores else 0.0,
                    "Vocabulary": round(sum(entropy_scores) / num_students, 1) if entropy_scores else 0.0
                }

                return {
                    "status": "success",
                    "assessment_id": assessment_id,
                    "course_id": course_id,
                    "total_students": len(student_records),
                    "total_class_prompts": total_class_prompts,
                    "total_class_wh": round(total_class_wh, 2),
                    "total_class_carbon_g": round(total_class_carbon, 2),
                    "avg_class_cioe": avg_cioe,
                    "avg_class_entropy": avg_entropy,
                    "cohort_radar": cohort_radar,
                    "archetype_distribution": archetype_counts,
                    "tier_distribution": tier_counts,
                    "student_telemetry": student_records
                }

        except Exception as e:
            logger.error(f"Error in get_cohort_research_analytics: {e}")
            return {"status": "error", "message": str(e)}
        finally:
            try:
                conn.close()
            except Exception:
                pass

    @classmethod
    def generate_research_csv(cls, course_id: Optional[str] = None, assessment_id: Optional[str] = None) -> str:
        """
        Generates CSV dataset string of raw prompt interactions and aggregate metrics for researchers.
        """
        conn = get_db_connection()
        if conn is None:
            return "error\nDatabase offline"

        output = io.StringIO()
        writer = csv.writer(output)

        # Write CSV Headers
        writer.writerow([
            "assessment_id",
            "student_nim_anonymized",
            "prompt_timestamp",
            "prompt_length_chars",
            "shannon_entropy",
            "tech_token_density",
            "has_context",
            "has_input",
            "has_output",
            "has_error",
            "cioe_score",
            "prompt_quality_score",
            "ai_literacy_tier",
            "persona_archetype",
            "byok_energy_wh",
            "byok_carbon_g_co2e",
            "prompt_text_preview"
        ])

        try:
            with conn.cursor() as cur:
                query = """
                    SELECT ch.id, ch.user_id, ch.assessment_id, ch.content, ch.created_at, u.username
                    FROM chat_history ch
                    LEFT JOIN users u ON u.user_id = ch.user_id
                    WHERE ch.role = 'user' AND (%s IS NULL OR ch.assessment_id = %s)
                    ORDER BY ch.created_at ASC
                """
                cur.execute(query, (assessment_id, assessment_id))
                rows = cur.fetchall() or []

                for r in rows:
                    p_text = (r.get('content') or '').strip()
                    if not p_text:
                        continue

                    analysis = PromptLinter.analyze(p_text)
                    cioe = analysis['cioe_breakdown']
                    s_prompt = analysis['prompt_quality_score']
                    entropy = analysis['shannon_entropy']
                    tech = analysis['technical_token_density']
                    c_score = analysis['cioe_score']

                    if s_prompt >= 0.80:
                        tier = "Tier A"
                    elif s_prompt >= 0.60:
                        tier = "Tier B"
                    elif s_prompt >= 0.40:
                        tier = "Tier C"
                    else:
                        tier = "Tier D"

                    p_len = len(p_text)
                    wh = round((max(10, p_len // 4) / 1000.0) * 0.35, 4)
                    co2 = round(wh * 0.475, 4)

                    anon_nim = f"STU_{hash(r.get('username') or r.get('user_id')) % 10000:04d}"

                    writer.writerow([
                        r.get('assessment_id') or assessment_id or "N/A",
                        anon_nim,
                        str(r.get('created_at')),
                        p_len,
                        entropy,
                        tech,
                        1 if cioe['has_context'] else 0,
                        1 if cioe['has_input'] else 0,
                        1 if cioe['has_output'] else 0,
                        1 if cioe['has_error'] else 0,
                        c_score,
                        s_prompt,
                        tier,
                        "Structured" if c_score >= 0.75 else "Developing",
                        wh,
                        co2,
                        p_text[:120].replace('\n', ' ')
                    ])

            return output.getvalue()
        except Exception as e:
            logger.error(f"Error generating research CSV: {e}")
            return f"error\n{str(e)}"
        finally:
            try:
                conn.close()
            except Exception:
                pass

    @classmethod
    def _get_empty_wrapped(cls, assessment_id: str, assessment_title: str, course_name: str) -> Dict[str, Any]:
        return {
            "status": "success",
            "is_expired": True,
            "assessment_id": assessment_id,
            "assessment_title": assessment_title,
            "course_name": course_name,
            "summary": {
                "total_prompts": 0,
                "total_tokens_used": 0,
                "tokens_saved_fastpath": 0,
                "fast_path_hits": 0,
                "overall_score": 100.0,
                "literacy_tier": "Tier A (Independent Solver)",
                "tier_badge": "Tier A",
                "badge_color": "#10B981"
            },
            "persona": {
                "title": "The Independent Master",
                "tagline": "Zero-AI Autonomous Achiever",
                "description": "Solved the assessment independently without requiring AI assistance.",
                "power_stat": "100% Pure Organic Cognitive Effort"
            },
            "dimensions": {
                "shannon_entropy": 1.0,
                "technical_density": 100.0,
                "cioe_completeness": 100.0,
                "radar": {"Context": 100, "Input": 100, "Output": 100, "Error": 100, "Vocabulary": 100}
            },
            "critic_room": {
                "best_prompt": {"text": "Solved code autonomously.", "score": 100, "why_stellar": "Pure autonomous problem-solving without AI dependency."},
                "needs_polish_prompt": {"text": "-", "score": 100, "ai_critic_comment": "No prompts required evaluation.", "suggested_rewrite": "-"}
            },
            "byok_sustainability": {
                "energy_wh": 0.0,
                "carbon_g": 0.0,
                "water_ml": 0.0,
                "rating": "Net-Zero Carbon Autonomous",
                "fast_path_ratio": 100.0
            },
            "action_items": [
                "Maintain your autonomous problem-solving and critical thinking in future advanced assessments!"
            ]
        }

    @classmethod
    def _get_mock_wrapped(cls, user_id: str, assessment_id: str) -> Dict[str, Any]:
        return cls._compile_wrapped_payload(
            user_id=user_id,
            assessment_id=assessment_id,
            assessment_title=f"Assessment #{assessment_id}",
            course_name="Algorithms & Data Structures",
            user_prompts=[
                {
                    "id": "1",
                    "prompt": "[CONTEXT: Python 3 Binary Search] Given a sorted list[int] of size n, how do we find target with O(log n) iteratively without recursion?",
                    "timestamp": datetime.utcnow().isoformat(),
                    "analysis": PromptLinter.analyze("[CONTEXT: Python 3 Binary Search] Given a sorted list[int] of size n, how do we find target with O(log n) iteratively without recursion?")
                },
                {
                    "id": "2",
                    "prompt": "IndexError: list index out of range at line 15 in binary_search function, please help debug the boundary condition",
                    "timestamp": datetime.utcnow().isoformat(),
                    "analysis": PromptLinter.analyze("IndexError: list index out of range at line 15 in binary_search function, please help debug the boundary condition")
                }
            ],
            total_tokens_used=640,
            tokens_saved=450,
            fast_path_hits=1
        )
