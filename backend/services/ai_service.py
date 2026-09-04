import os
import time
import json
import logging
import litellm
import numpy as np
from rank_bm25 import BM25Okapi
from sentence_transformers import SentenceTransformer
from backend.core.db import get_db_connection
from backend.services.chat_history import get_chat_history, insert_chat_history
from backend.core.prompts import PromptRegistry

logging.basicConfig(level=logging.INFO)

from backend.services.adaptive_router import GeminiMultiProviderGateway, AdaptiveRouter

class AIGateway:
    """
    Custom AI Gateway delegating requests to GeminiMultiProviderGateway (6 API Keys pool)
    and AdaptiveRouter (Ollama fallback + Gamification Points).
    """
    def __init__(self):
        self.gateway = GeminiMultiProviderGateway()

    def generate(self, messages: list, model: str = None, username: str = None) -> str:
        if username:
            router = AdaptiveRouter()
            res = router.route_and_generate(messages, username=username, model=model)
            return res.get("content", "")
        return self.gateway.generate(messages, model=model)



_CORPUS_CACHE = None
_EMB_MAP_CACHE = {}
_BM25_CACHE = None
_CACHE_TIMESTAMP = 0
_CACHE_TTL = 300

def invalidate_corpus_cache():
    global _CORPUS_CACHE, _EMB_MAP_CACHE, _BM25_CACHE, _CACHE_TIMESTAMP
    _CORPUS_CACHE = None
    _EMB_MAP_CACHE = {}
    _BM25_CACHE = None
    _CACHE_TIMESTAMP = 0

def fetch_code_by_id(doc_id: str) -> str:
    conn = get_db_connection()
    if conn is None:
        return ""
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT code FROM code_embeddings WHERE id=%s LIMIT 1", (doc_id,))
            r = cur.fetchone()
            return r['code'] if r else ""
    except Exception:
        return ""
    finally:
        try:
            conn.close()
        except Exception:
            pass

def fetch_codes_by_ids(doc_ids: list) -> dict:
    if not doc_ids:
        return {}
    conn = get_db_connection()
    if conn is None:
        return {}
    try:
        with conn.cursor() as cur:
            format_strings = ','.join(['%s'] * len(doc_ids))
            cur.execute(f"SELECT id, code FROM code_embeddings WHERE id IN ({format_strings})", tuple(doc_ids))
            rows = cur.fetchall() or []
            return {r['id']: r['code'] for r in rows}
    except Exception:
        return {}
    finally:
        try:
            conn.close()
        except Exception:
            pass

def get_cached_corpus_and_embeddings():
    global _CORPUS_CACHE, _EMB_MAP_CACHE, _BM25_CACHE, _CACHE_TIMESTAMP
    now = time.time()
    if _CORPUS_CACHE is not None and (now - _CACHE_TIMESTAMP) < _CACHE_TTL:
        return _CORPUS_CACHE, _EMB_MAP_CACHE, _BM25_CACHE

    conn = get_db_connection()
    if conn is None:
        return [], {}, None

    try:
        with conn.cursor() as cur:
            cur.execute("SELECT id, prompt, embedding FROM code_embeddings")
            rows = cur.fetchall() or []

        all_docs = []
        dim_map_docs = {}
        dim_map_embs = {}

        for r in rows:
            if r.get('embedding'):
                try:
                    emb = json.loads(r['embedding'])
                    if isinstance(emb, list) and len(emb) > 0:
                        doc = {
                            'id': r['id'],
                            'prompt': r['prompt'],
                            'embedding': emb
                        }
                        all_docs.append(doc)
                        dim = len(emb)
                        if dim not in dim_map_docs:
                            dim_map_docs[dim] = []
                            dim_map_embs[dim] = []
                        dim_map_docs[dim].append(doc)
                        dim_map_embs[dim].append(emb)
                except Exception:
                    pass

        emb_map = {}
        for dim, embs in dim_map_embs.items():
            docs = dim_map_docs[dim]
            matrix = np.array(embs, dtype=np.float32)
            norms = np.linalg.norm(matrix, axis=1)
            norms[norms == 0] = 1.0
            emb_map[dim] = (docs, matrix, norms)

        # Pre-build BM25 index once
        tokenized_corpus = [doc['prompt'].lower().split() for doc in all_docs]
        bm25 = BM25Okapi(tokenized_corpus) if all_docs else None

        _CORPUS_CACHE = all_docs
        _EMB_MAP_CACHE = emb_map
        _BM25_CACHE = bm25
        _CACHE_TIMESTAMP = now
        return _CORPUS_CACHE, _EMB_MAP_CACHE, _BM25_CACHE
    except Exception as e:
        logging.error(f"Error loading corpus cache: {e}")
        return _CORPUS_CACHE or [], _EMB_MAP_CACHE or {}, _BM25_CACHE
    finally:
        try:
            conn.close()
        except Exception:
            pass

_HYBRID_MODEL = None

def get_hybrid_model():
    global _HYBRID_MODEL
    if _HYBRID_MODEL is None:
        try:
            _HYBRID_MODEL = SentenceTransformer('all-MiniLM-L6-v2')
        except Exception as e:
            logging.warning(f"SentenceTransformer load failed: {e}")
    return _HYBRID_MODEL

class HybridSearcher:
    """
    Combines Dense (Sentence Transformers/FAISS) and Sparse (BM25) search with Reciprocal Rank Fusion (RRF).
    Uses vectorized NumPy matrix dot-product search with dimension-matched caching and on-demand code fetching.
    """
    def __init__(self):
        self.model = get_hybrid_model()
        self.k = 60 # RRF constant
        
    def check_fast_path(self, query: str, threshold: float = 0.88):
        """
        Checks if query has a direct high-similarity match (>= threshold) in code_embeddings
        for 0-token immediate response.
        """
        all_docs, emb_map, _ = get_cached_corpus_and_embeddings()
        if not all_docs:
            return False, None, 0.0

        try:
            query_embedding = self.model.encode(query)
            query_arr = np.asarray(query_embedding, dtype=np.float32)
            q_dim = len(query_arr)
            query_norm = np.linalg.norm(query_arr)
            if query_norm == 0:
                query_norm = 1.0

            if q_dim not in emb_map:
                return False, None, 0.0

            docs, emb_matrix, emb_norms = emb_map[q_dim]
            dot_products = np.dot(emb_matrix, query_arr)
            dense_scores = dot_products / (emb_norms * query_norm)

            if len(dense_scores) == 0:
                return False, None, 0.0

            best_idx = int(np.argmax(dense_scores))
            best_score = float(dense_scores[best_idx])
            best_doc = dict(docs[best_idx])

            if best_score >= threshold:
                best_doc['code'] = fetch_code_by_id(best_doc['id'])
                return True, best_doc, best_score
            return False, best_doc, best_score
        except Exception as e:
            logging.error(f"check_fast_path error: {e}")
            return False, None, 0.0

    def search(self, query: str, top_k: int = 3) -> str:
        all_docs, emb_map, bm25 = get_cached_corpus_and_embeddings()
        if not all_docs:
            return ""

        # Sparse Search (BM25) over cached pre-built index
        if bm25:
            tokenized_query = query.lower().split()
            sparse_scores = bm25.get_scores(tokenized_query)
        else:
            sparse_scores = [0.0] * len(all_docs)

        # Dense Search (Vectorized)
        dense_scores_map = {} # doc_id -> score
        try:
            query_embedding = self.model.encode(query)
            query_arr = np.asarray(query_embedding, dtype=np.float32)
            q_dim = len(query_arr)
            query_norm = np.linalg.norm(query_arr)
            if query_norm == 0:
                query_norm = 1.0

            if q_dim in emb_map:
                docs, emb_matrix, emb_norms = emb_map[q_dim]
                dot_products = np.dot(emb_matrix, query_arr)
                scores = dot_products / (emb_norms * query_norm)
                for d, s in zip(docs, scores):
                    dense_scores_map[d['id']] = float(s)
        except Exception as e:
            logging.error(f"Dense search error: {e}")

        # RRF (Reciprocal Rank Fusion)
        sparse_ranks = {all_docs[i]['id']: rank for rank, i in enumerate(np.argsort(sparse_scores)[::-1])}
        dense_scores_list = [dense_scores_map.get(d['id'], 0.0) for d in all_docs]
        dense_ranks = {all_docs[i]['id']: rank for rank, i in enumerate(np.argsort(dense_scores_list)[::-1])}

        rrf_scores = []
        for doc in all_docs:
            doc_id = doc['id']
            rrf_score = 1.0 / (self.k + sparse_ranks[doc_id]) + 1.0 / (self.k + dense_ranks[doc_id])
            rrf_scores.append((rrf_score, doc))

        rrf_scores.sort(key=lambda x: x[0], reverse=True)
        top_docs = [doc for score, doc in rrf_scores[:top_k]]

        if not top_docs:
            return ""

        # Fetch code for the top_k docs
        doc_ids = [d['id'] for d in top_docs]
        code_map = fetch_codes_by_ids(doc_ids)

        context_parts = []
        for doc in top_docs:
            c_text = code_map.get(doc['id'], '')
            context_parts.append(f"Q: {doc['prompt']}\nA:\n{c_text}")

        return "\n\n---\n\n".join(context_parts)

def process_chat_job(job_id: str, gpt_prompt_marked: str):
    """
    Worker function to process a chat job using the Context-Aware AI Gateway.
    """
    from backend.api.ai_chat import get_gpt_job
    job = get_gpt_job(job_id)
    if not job:
        logging.error(f"Job {job_id} not found")
        return
        
    try:
        user_id = job['user_id']
        
        # 1. Parse markers
        lines = gpt_prompt_marked.split('\n')
        markers = {}
        clean_prompt_lines = []
        for line in lines:
            if line.startswith('[LANG:'):
                markers['language'] = line.replace('[LANG:', '').replace(']', '')
            elif line.startswith('[MODE:'):
                markers['mode'] = line.replace('[MODE:', '').replace(']', '')
            elif line.startswith('[ASSESSMENT_ID:'):
                markers['assessment_id'] = line.replace('[ASSESSMENT_ID:', '').replace(']', '')
            elif line.startswith('[FORCE_GPT:true]'):
                markers['force_gpt'] = True
            elif line.startswith('[AUTO_FALLBACK:true]'):
                markers['auto_fallback'] = True
            else:
                clean_prompt_lines.append(line)
                
        clean_prompt = "\n".join(clean_prompt_lines).strip()
        
        # 2. Fetch Chat History
        assessment_id = markers.get('assessment_id')
        
        conn = get_db_connection()
        session_id = "default_session"
        if conn is not None:
            try:
                with conn.cursor() as cur:
                    cur.execute("SELECT session_id FROM session_tokens WHERE user_id=%s ORDER BY used_at DESC LIMIT 1", (user_id,))
                    s_row = cur.fetchone()
                    session_id = s_row['session_id'] if s_row else "default_session"
            except Exception:
                pass
            finally:
                try:
                    conn.close()
                except Exception:
                    pass
            
        chat_history = get_chat_history(user_id, session_id, assessment_id, limit=5)
        
        # 3. Check Fast-Path Semantic Cache (>= 0.88 similarity -> 0 Tokens FREE Tier)
        is_fast_path = False
        matched_doc = None
        sim_score = 0.0
        retrieved_context = ""
        
        searcher = HybridSearcher()
        if not markers.get('force_gpt', False):
            try:
                is_fast_path, matched_doc, sim_score = searcher.check_fast_path(clean_prompt)
                if not is_fast_path:
                    retrieved_context = searcher.search(clean_prompt)
            except Exception as e:
                logging.error(f"Search error: {e}")
        else:
            sim_score = 0.0
                
        if is_fast_path and matched_doc:
            logging.info(f"[SEMANTIC CACHE HIT] Fast-path triggered with similarity {sim_score:.3f}. 0 Tokens consumed.")
            response_text = matched_doc['code']
            raw_response = response_text
            
            try:
                from backend.api.ai_chat import _in_memory_jobs
                _in_memory_jobs[job_id] = {
                    "job_id": job_id,
                    "status": "done",
                    "code": response_text,
                    "raw_response": raw_response,
                    "similarity": sim_score,
                    "tokens_used": 0
                }
            except Exception as e_mem:
                logging.warning(f"Failed to update in-memory job state: {e_mem}")

            conn = get_db_connection()
            if conn is not None:
                try:
                    with conn.cursor() as cur:
                        cur.execute(
                            "UPDATE gpt_jobs SET status='done', code=%s, raw_response=%s, similarity=%s, prompt_matched=%s, updated_at=NOW() WHERE job_id=%s",
                            (response_text, raw_response, sim_score, matched_doc['prompt'], job_id)
                        )
                    conn.commit()
                except Exception:
                    pass
                finally:
                    try:
                        conn.close()
                    except Exception:
                        pass
                
            insert_chat_history(user_id, session_id, "user", clean_prompt, assessment_id)
            insert_chat_history(user_id, session_id, "assistant", response_text, assessment_id)
            return

        # 4. Build Prompt Harness with Mode and Language awareness
        mode = markers.get('mode', 'code')
        language = markers.get('language')
        messages = PromptRegistry.get_chat_harness(
            chat_history=chat_history,
            new_query=clean_prompt,
            retrieved_context=retrieved_context,
            language=language,
            mode=mode
        )
        
        # 5. Query Adaptive Router (Gemini Flash Lite Cloud vs Ollama Local)
        router = AdaptiveRouter()
        router_res = router.route_and_generate(messages, username=user_id, assessment_id=assessment_id)
        raw_response = router_res.get("content", "")
        response_text = raw_response

        # Count actual tokens (system harness, chat history, prompt, and response)
        total_tokens = 0
        input_tokens = 0
        output_tokens = 0
        try:
            import tiktoken
            encoding = tiktoken.get_encoding("cl100k_base")
            for m in messages:
                input_tokens += 4 + len(encoding.encode(str(m.get('content', ''))))
            input_tokens += 2
            output_tokens = len(encoding.encode(str(raw_response)))
            total_tokens = input_tokens + output_tokens
        except Exception:
            input_tokens = max(10, len(clean_prompt) // 4)
            output_tokens = max(10, len(raw_response) // 4)
            total_tokens = input_tokens + output_tokens

        # Update in-memory job state
        try:
            from backend.api.ai_chat import _in_memory_jobs
            _in_memory_jobs[job_id] = {
                "job_id": job_id,
                "status": "done",
                "code": response_text,
                "raw_response": raw_response,
                "similarity": sim_score,
                "tokens_used": total_tokens
            }
        except Exception:
            pass

        # Update Job in database with exact status
        conn = get_db_connection()
        if conn is not None:
            try:
                with conn.cursor() as cur:
                    cur.execute(
                        "UPDATE gpt_jobs SET status='done', code=%s, raw_response=%s, similarity=%s, updated_at=NOW() WHERE job_id=%s",
                        (response_text, raw_response, sim_score, job_id)
                    )
                conn.commit()
            except Exception as e:
                logging.warning(f"Failed to update job status in DB: {e}")
            finally:
                try:
                    conn.close()
                except Exception:
                    pass

        # Fetch course_id if assessment_id is present
        course_id = None
        if assessment_id:
            conn_c = get_db_connection()
            if conn_c is not None:
                try:
                    with conn_c.cursor() as cur_c:
                        cur_c.execute("SELECT course_id FROM assessment WHERE assessment_id=%s LIMIT 1", (assessment_id,))
                        row_c = cur_c.fetchone()
                        if row_c:
                            course_id = row_c.get('course_id')
                except Exception:
                    pass
                finally:
                    try:
                        conn_c.close()
                    except Exception:
                        pass

        # Log session tokens for gamification
        try:
            from backend.services.gamification import log_token_usage
            log_token_usage(
                user_id=user_id,
                session_id=session_id,
                tokens_used=total_tokens,
                assessment_id=assessment_id,
                course_id=course_id
            )
        except Exception as e_game:
            logging.error(f"Failed to log tokens in gamification: {e_game}")

        # Scientific Environmental Footprint tracking
        try:
            from backend.services.sustainability import log_environmental_impact
            log_environmental_impact(
                user_id=user_id,
                job_id=job_id,
                total_tokens=total_tokens,
                assessment_id=assessment_id,
                course_id=course_id
            )
        except Exception as e_sust:
            logging.error(f"Failed to log sustainability: {e_sust}")

        # Save to chat history
        insert_chat_history(user_id, session_id, "user", clean_prompt, assessment_id)
        insert_chat_history(user_id, session_id, "assistant", response_text, assessment_id)
        
        # Self-Growing Knowledge Base: Auto-ingest new code solution into code_embeddings
        try:
            auto_ingest_knowledge(user_id=user_id, prompt=clean_prompt, code=response_text)
        except Exception as e_ingest:
            logging.warning(f"Self-growing auto-ingestion skipped: {e_ingest}")

        # Update Job in database
        conn = get_db_connection()
        if conn is not None:
            try:
                with conn.cursor() as cur:
                    cur.execute(
                        "UPDATE gpt_jobs SET status='done', code=%s, raw_response=%s, updated_at=NOW() WHERE job_id=%s",
                        (response_text, raw_response, job_id)
                    )
                conn.commit()
            except Exception:
                pass
            finally:
                try:
                    conn.close()
                except Exception:
                    pass
                
    except Exception as e:
        logging.error(f"Job {job_id} failed: {e}")
        conn = get_db_connection()
        if conn is not None:
            try:
                with conn.cursor() as cur:
                    cur.execute(
                        "UPDATE gpt_jobs SET status='error', error=%s, updated_at=NOW() WHERE job_id=%s",
                        (str(e), job_id)
                    )
                conn.commit()
            except Exception:
                pass
            finally:
                try:
                    conn.close()
                except Exception:
                    pass


def auto_ingest_knowledge(user_id: str, prompt: str, code: str):
    """
    Self-Growing Knowledge Base Auto-Ingestion:
    Automatically computes vector embeddings for newly generated GPT code solutions
    and inserts them into code_embeddings so future similar queries are retrieved for FREE.
    """
    if not prompt or not code or len(code.strip()) < 10:
        return

    import uuid
    import json
    from backend.core.db import resolve_user_uuid
    resolved_uid = resolve_user_uuid(user_id)
    
    # Check if this exact prompt already exists in code_embeddings
    conn = get_db_connection()
    if conn is None:
        return
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM code_embeddings WHERE prompt=%s LIMIT 1", (prompt.strip(),))
            if cur.fetchone():
                return  # already in knowledge base
                
        # Generate embedding vector
        searcher = HybridSearcher()
        emb_vector = searcher.model.encode(prompt.strip()).tolist()
        emb_json = json.dumps(emb_vector)
        entry_id = str(uuid.uuid4())
        
        with conn.cursor() as cur:
            cur.execute(
                "INSERT INTO code_embeddings (id, user_id, prompt, code, embedding, created_at) "
                "VALUES (%s, %s, %s, %s, %s, NOW())",
                (entry_id, resolved_uid, prompt.strip(), code.strip(), emb_json)
            )
        conn.commit()
        invalidate_corpus_cache()
        logging.info(f"[SELF-GROWING] Auto-ingested new knowledge into code_embeddings (id: {entry_id})")
    except Exception as e:
        logging.warning(f"auto_ingest_knowledge DB write failed: {e}")
    finally:
        try:
            conn.close()
        except Exception:
            pass

def _warmup_cache_async():
    import threading
    def _warmup():
        try:
            get_cached_corpus_and_embeddings()
            get_hybrid_model()
            logging.info("[WARMUP] Corpus cache & embedding model successfully warmed up!")
        except Exception as e:
            logging.warning(f"[WARMUP] Cache warmup skipped: {e}")
    t = threading.Thread(target=_warmup, daemon=True)
    t.start()

_warmup_cache_async()
