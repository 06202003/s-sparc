from flask import Flask, request, jsonify
from flask_cors import CORS
from flask_limiter import Limiter
from flask_limiter.util import get_remote_address
from dotenv import load_dotenv
import os


import openai
import logging
import joblib
import numpy as np
import pandas as pd
import threading
import queue
import time
try:
    from codecarbon import EmissionsTracker 
except ImportError:
    EmissionsTracker = None 

# Load environment variables from .env file
load_dotenv()


# === Environmental Impact Calculation Constants and Function ===
# These constants and the function below are used to calculate the environmental impact
# (energy, water, carbon) per token/query, based on prompt size. Keep this section in sync with app copy.py.

OPENAI_MODEL = "gpt-4"  # ganti dari gpt-3.5-turbo ke gpt-4
DEFAULT_LIMITS = ["100 per day", "10 per minute"]

# Constants for environmental impact per token based on prompt size
ENERGY_PER_TOKEN_WH_SHORT = 0.0010525
ENERGY_PER_TOKEN_WH_MEDIUM = 0.0006070
ENERGY_PER_TOKEN_WH_LONG = 0.0001555

# Total Air and Carbon per query based on prompt size
WATER_PER_QUERY_SHORT = 0.00625  # Total air per short-form query
WATER_PER_QUERY_MEDIUM = 0.001875  # Total air per medium-form query
WATER_PER_QUERY_LONG = 5.217391304347826e-4  # Total air per long-form query

CARBON_PER_QUERY_SHORT = 0.0004375  # Total carbon per short-form query (in kgCO2e)
CARBON_PER_QUERY_MEDIUM = 0.000002  # Total carbon per medium-form query (in kgCO2e)
CARBON_PER_QUERY_LONG = 5.217391304347826e-7  # Total carbon per long-form query (in kgCO2e)

def compute_environmental_impact(token_count: int):
    """
    Calculate energy (Wh), water (ml), and carbon (kgCO2e) based on token count.
    Uses different per-token rates for short, medium, and long prompts.
    """
    if 0 < token_count <= 400:
        energy = token_count * ENERGY_PER_TOKEN_WH_SHORT
        water = token_count * WATER_PER_QUERY_SHORT 
        carbon = token_count * CARBON_PER_QUERY_SHORT
    elif 400 < token_count <= 2000:
        energy = token_count * ENERGY_PER_TOKEN_WH_MEDIUM
        water = token_count * WATER_PER_QUERY_MEDIUM
        carbon = token_count * CARBON_PER_QUERY_MEDIUM 
    elif token_count > 2000:
        energy = token_count * ENERGY_PER_TOKEN_WH_LONG
        water = token_count * WATER_PER_QUERY_LONG  
        carbon = token_count * CARBON_PER_QUERY_LONG 
    else:
        raise ValueError("Token count must be greater than 0")
    return energy, water, carbon


# Initialize Flask app
app = Flask(__name__)
CORS(app)

# Initialize Flask-Limiter
limiter = Limiter(
    get_remote_address,
    app=app,
    default_limits=DEFAULT_LIMITS,
)

# Configure OpenAI API key
OPENAI_API_KEY = os.getenv("OPENAI_API_KEY")
openai.api_key = OPENAI_API_KEY

# Create OpenAI client for openai>=1.0.0
try:
    client = openai.OpenAI(api_key=OPENAI_API_KEY) if OPENAI_API_KEY else openai.OpenAI()
except Exception:
    client = openai.OpenAI()  # fallback


# --- GPT Request Queue and Worker ---
gpt_queue = queue.Queue()
gpt_results = {}  # key: job_id, value: result dict

# --- Session token tracking (in-memory, per session_id) ---
session_tokens = {}  # key: session_id, value: total_tokens

import json as _json
def _write_queue_status():
    try:
        with open("gpt_queue_status.json", "w", encoding="utf-8") as f:
            _json.dump(gpt_results, f, ensure_ascii=False, indent=2)
    except Exception as e:
        print(f"[WARNING] Failed to write gpt_queue_status.json: {e}")

def gpt_worker():
    while True:
        job = gpt_queue.get()
        if job is None:
            break
        job_id, messages, model, gpt_prompt = job["job_id"], job["messages"], job["model"], job["gpt_prompt"]
        max_retry = 10
        retry_delay = 5
        for attempt in range(max_retry):
            try:
                response = client.chat.completions.create(
                    model=model,
                    temperature=0.3,
                    messages=messages,
                )
                generated_code = response.choices[0].message.content.strip()
                gpt_results[job_id] = {"status": "done", "code": generated_code, "prompt": gpt_prompt}
                _write_queue_status()
                break
            except Exception as e:
                # Check for rate limit
                if hasattr(e, 'status_code') and e.status_code == 429:
                    time.sleep(retry_delay)
                else:
                    gpt_results[job_id] = {"status": "error", "error": str(e)}
                    _write_queue_status()
                    break
        else:
            gpt_results[job_id] = {"status": "error", "error": "Max retry reached (rate limit)"}
            _write_queue_status()

# Start worker thread
threading.Thread(target=gpt_worker, daemon=True).start()



from semantic_similarity.retrieval_utils import SemanticRetrievalModel
try:
    # --- Copied get_ensemble_embedding from main.ipynb ---
    from langdetect import detect
    # --- Load local models from pretrained_model (no downloads) ---
    from sentence_transformers import SentenceTransformer
    from transformers import pipeline
    import torch
    import os

    MODEL_DIR = 'pretrained_model'
    def _local_path(subdir: str) -> str:
        return os.path.join(MODEL_DIR, subdir)
    def _find_st_model(subdir: str) -> str:
        import glob
        base = _local_path(subdir)
        snapshot_glob = os.path.join(base, 'models--*', 'snapshots', '*')
        candidates = glob.glob(snapshot_glob)
        indicators = {'sentence_bert_config.json', 'config_sentence_transformers.json', 'modules.json', 'model.safetensors', 'pytorch_model.bin'}
        for cand in candidates:
            files = set(os.listdir(cand))
            if indicators & files:
                return cand
        files = set(os.listdir(base)) if os.path.isdir(base) else set()
        if indicators & files:
            return base
        return base

    model1_path = _find_st_model('paraphrase-multilingual-mpnet-base-v2')
    model2_path = _find_st_model('LaBSE')
    model3_path = _find_st_model('multilingual-e5-base')
    print(f"[DEBUG] model1_path: {os.path.abspath(model1_path)}")
    print(f"[DEBUG] model2_path: {os.path.abspath(model2_path)}")
    print(f"[DEBUG] model3_path: {os.path.abspath(model3_path)}")
    model1 = SentenceTransformer(model1_path)
    model2 = SentenceTransformer(model2_path)
    model3 = SentenceTransformer(model3_path)
    translator = pipeline('translation', model=_local_path('opus-mt-id-en'), tokenizer=_local_path('opus-mt-id-en'), device=0 if torch.cuda.is_available() else -1)

    def get_ensemble_embedding(text):
        global model1, model2, model3, translator
        try:
            lang = detect(text)
        except Exception:
            lang = 'en'
        if lang == 'id':
            text = translator(text)[0]['translation_text']
        emb1 = model1.encode([text], convert_to_numpy=True)
        emb2 = model2.encode([text], convert_to_numpy=True)
        emb3 = model3.encode([text], convert_to_numpy=True)
        emb = np.concatenate([emb1, emb2, emb3], axis=1)
        return emb

    retrieval_model = joblib.load("semantic_similarity/semantic_retrieval_model.pkl")
    retrieval_model.encoder_func = get_ensemble_embedding
except Exception as e:
    retrieval_model = None
    print(f"[WARNING] semantic_retrieval_model.pkl not loaded: {e}")
# Configure logging
logging.basicConfig(level=logging.INFO)


@app.route('/generate-code', methods=['POST'])
@limiter.limit("6 per minute")
def generate_code():
    data = request.get_json(silent=True) or {}
    prompt = data.get("prompt")
    if not prompt or not isinstance(prompt, str):
        return jsonify({"error": "Missing or invalid 'prompt' in request body"}), 400

    # SEMANTIC RETRIEVAL
    if retrieval_model is not None:

        tracker = None
        emissions = None
        if EmissionsTracker is not None:
            tracker = EmissionsTracker(measure_power_secs=1, log_level="error", output_dir=".")
            tracker.start()
        retrieval_results = retrieval_model.search(prompt, top_k=1)
        top_row = retrieval_results.iloc[0]
        similarity = float(top_row['score'])
        code_retrieved = top_row['code']
        prompt_retrieved = top_row['prompt']
        if tracker is not None:
            emissions = tracker.stop()

        # Calculate token_count using tiktoken (same as GPT branch)
        def count_tokens_for_retrieval(text, model="gpt-4"):
            try:
                import tiktoken
            except ImportError:
                return len(str(text).split())
            try:
                encoding = tiktoken.encoding_for_model(model)
            except Exception:
                encoding = tiktoken.get_encoding("cl100k_base")
            # Simulate a single user message for retrieval
            num_tokens = 4  # message metadata
            num_tokens += len(encoding.encode(str(text)))
            num_tokens += 2  # reply primed
            return num_tokens
        token_count = count_tokens_for_retrieval(code_retrieved)

        def _read_last_emissions_csv():
            import csv
            import os
            csv_path = os.path.join(os.getcwd(), "emissions.csv")
            if not os.path.exists(csv_path):
                return None
            try:
                with open(csv_path, "r", encoding="utf-8") as f:
                    rows = list(csv.reader(f))
                    if len(rows) < 2:
                        return None
                    header = rows[0]
                    last_row = rows[-1]
                    # Find the index for 'emissions' and 'energy_consumed'
                    try:
                        idx_emissions = header.index("emissions")
                        idx_energy = header.index("energy_consumed")
                        idx_duration = header.index("duration")
                        idx_cpu_energy = header.index("cpu_energy")
                        idx_gpu_energy = header.index("gpu_energy")
                        idx_ram_energy = header.index("ram_energy")
                    except Exception:
                        return None
                    try:
                        return {
                            "energy_wh": float(last_row[idx_energy]),
                            "carbon_kg": float(last_row[idx_emissions]),
                            "duration_s": float(last_row[idx_duration]),
                            "cpu_energy_wh": float(last_row[idx_cpu_energy]),
                            "gpu_energy_wh": float(last_row[idx_gpu_energy]),
                            "ram_energy_wh": float(last_row[idx_ram_energy]),
                            "water_ml": 0
                        }
                    except Exception:
                        return None
            except Exception:
                return None

        def _format_impact(emissions):
            if emissions is None:
                return None
            return {
                "energy_wh": getattr(emissions, "energy_consumed", 0),
                "carbon_kg": getattr(emissions, "emissions", 0),
                "duration_s": getattr(emissions, "duration", 0),
                "cpu_energy_wh": getattr(emissions, "cpu_energy", 0),
                "gpu_energy_wh": getattr(emissions, "gpu_energy", 0),
                "ram_energy_wh": getattr(emissions, "ram_energy", 0),
                "water_ml": 0
            }

        def _get_impact(emissions):
            # If CodeCarbon returns 0 or None, read from emissions.csv
            # Always calculate energy and water using per-token formula
            # Only use CodeCarbon for carbon_kg if available and nonzero
            # Use token_count from tiktoken if available, else fallback
            token_count = 10
            try:
                if 'token_count' in locals():
                    token_count = token_count
                elif 'code_retrieved' in locals():
                    # fallback: use tiktoken if available
                    try:
                        import tiktoken
                        encoding = tiktoken.encoding_for_model("gpt-4")
                        num_tokens = 4 + len(encoding.encode(str(code_retrieved))) + 2
                        token_count = num_tokens
                    except Exception:
                        token_count = len(str(code_retrieved).split())
                else:
                    token_count = 10
            except Exception:
                token_count = 10
            energy, water, carbon_formula = compute_environmental_impact(token_count)

            carbon_kg = None
            if emissions is not None:
                # Try to get carbon from CodeCarbon
                try:
                    carbon_kg = getattr(emissions, "emissions", None)
                except Exception:
                    carbon_kg = None
                if carbon_kg is None or abs(carbon_kg) < 1e-12:
                    # Try to read from emissions.csv
                    csv_impact = _read_last_emissions_csv()
                    if csv_impact is not None and abs(csv_impact.get("carbon_kg", 0)) >= 1e-12:
                        carbon_kg = csv_impact.get("carbon_kg", None)
            else:
                # fallback: read from emissions.csv
                csv_impact = _read_last_emissions_csv()
                if csv_impact is not None and abs(csv_impact.get("carbon_kg", 0)) >= 1e-12:
                    carbon_kg = csv_impact.get("carbon_kg", None)

            # If still not found, use formula
            if carbon_kg is None or abs(carbon_kg) < 1e-12:
                carbon_kg = carbon_formula

            impact = {
                "energy_wh": energy,
                "water_ml": water,
                "carbon_kg": carbon_kg
            }
            return impact

        if similarity >= 0.95:
            impact = _get_impact(emissions)
            return jsonify({
                "mode": "retrieval",
                "similarity": similarity,
                "prompt_matched": prompt_retrieved,
                "code": code_retrieved,
                "message": "Kode ditemukan di database dengan similarity >=95%. Jawaban diambil dari database.",
                "environmental_impact": impact
            }), 200
        elif similarity >= 0.8:
            impact = _get_impact(emissions)
            return jsonify({
                "mode": "suggestion",
                "similarity": similarity,
                "prompt_matched": prompt_retrieved,
                "code": code_retrieved,
                "message": "Ditemukan kode mirip di database (similarity 80–95%). Jika ingin jawaban lebih spesifik, balas dengan 'GPT Mode'.",
                "environmental_impact": impact
            }), 200
        # else: similarity < 0.8, fallback to GPT

    # Fallback ke GPT jika similarity < 0.8 atau user balas 'GPT Mode'
    if not openai.api_key:
        return jsonify({"error": "OpenAI API key not configured"}), 500

    # Cek trigger GPT Mode
    if prompt.strip().lower() == "gpt mode":
        gpt_prompt = data.get("last_prompt") or "Silakan masukkan ulang permintaan Anda."
    else:
        gpt_prompt = prompt

    system_content = (
        "You are an expert programming assistant helping undergraduate computer science students. "
        "Your task is to generate only the source code that solves the user's request. "
        "Output only the code, no explanation, no comments, no markdown."
    )

    messages = [
        {"role": "system", "content": system_content},
        {"role": "user", "content": gpt_prompt},
    ]

    # --- Queue GPT request ---
    import uuid
    job_id = str(uuid.uuid4())

    gpt_results[job_id] = {"status": "pending"}
    _write_queue_status()
    gpt_queue.put({
        "job_id": job_id,
        "messages": messages,
        "model": OPENAI_MODEL,
        "gpt_prompt": gpt_prompt
    })

    return jsonify({
        "mode": "gpt-queued",
        "job_id": job_id,
        "message": "Permintaan Anda sedang diproses karena antrian atau rate limit. Silakan cek status dengan job_id ini di endpoint /check-status/{job_id}."
    }), 202
# Endpoint untuk cek status job GPT
@app.route('/check-status/<job_id>', methods=['GET'])
def check_status(job_id):
    result = gpt_results.get(job_id)
    if not result:
        return jsonify({"status": "not_found", "message": "Job ID tidak ditemukan."}), 404
    if result["status"] == "pending":
        return jsonify({"status": "pending", "message": "Pertanyaan Anda masih dalam antrian, silakan tunggu. Cek file gpt_queue_status.json untuk memantau status."}), 200
    if result["status"] == "done":
        # --- Append new prompt+answer to retrieval DB (mbpp_all_with_embedding.json) ---
        try:
            import json
            import traceback
            from langdetect import detect
            emb = retrieval_model.encoder_func(result["prompt"])
            emb = emb[0] if hasattr(emb, '__len__') and len(emb.shape) > 1 else emb
            new_entry = {
                "prompt": result["prompt"],
                "code": result["code"],
                "embedding": emb.tolist()
            }
            db_path = "semantic_similarity/mbpp_all_with_embedding.json"
            try:
                with open(db_path, "r", encoding="utf-8") as f:
                    data = json.load(f)
                data.append(new_entry)
                with open(db_path, "w", encoding="utf-8") as f:
                    json.dump(data, f, ensure_ascii=False, indent=2)
            except Exception as e:
                print(f"[ERROR] Failed to append to retrieval DB: {e}\n{traceback.format_exc()}")
        except Exception as e:
            print(f"[ERROR] Could not save GPT answer to retrieval DB: {e}\n{traceback.format_exc()}")
        # --- Environmental impact calculation for GPT result ---
        code = result.get("code")
        # Ambil session_id dari result jika ada, fallback ke remote_addr
        session_id = result.get("session_id") if isinstance(result, dict) and "session_id" in result else request.remote_addr or "default"
        # Hitung token dengan tiktoken
        def count_tokens(messages, model="gpt-4"):
            try:
                import tiktoken
            except ImportError:
                return 0
            try:
                encoding = tiktoken.encoding_for_model(model)
            except Exception:
                encoding = tiktoken.get_encoding("cl100k_base")
            num_tokens = 0
            for msg in messages:
                num_tokens += 4
                for key, value in msg.items():
                    num_tokens += len(encoding.encode(str(value)))
            num_tokens += 2
            return num_tokens
        messages = [
            {"role": "system", "content": "You are an expert programming assistant helping undergraduate computer science students. Output only the code, no explanation, no comments, no markdown."},
            {"role": "user", "content": result.get("prompt", "")},
        ]
        token_count = count_tokens(messages)
        # Update session token
        global session_tokens
        if 'session_tokens' not in globals():
            session_tokens = {}
        if session_id not in session_tokens:
            session_tokens[session_id] = 0
        session_tokens[session_id] += token_count
        total_tokens = session_tokens[session_id]
        remaining_tokens = max(0, 25000 - total_tokens)
        gamification_points = remaining_tokens if total_tokens < 25000 else 0
        try:
            energy, water, carbon = compute_environmental_impact(token_count)
        except Exception:
            energy, water, carbon = 0, 0, 0
        if total_tokens >= 25000:
            return jsonify({
                "status": "done",
                "code": result["code"],
                "environmental_impact": {
                    "energy_wh": energy,
                    "water_ml": water,
                    "carbon_kg": carbon
                },
                "gamification": {
                    "total_tokens": total_tokens,
                    "remaining_tokens": 0,
                    "points": 0
                },
                "message": "Token limit reached (25000). Tidak bisa chatting lagi."
            }), 200
        return jsonify({
            "status": "done",
            "code": result["code"],
            "environmental_impact": {
                "energy_wh": energy,
                "water_ml": water,
                "carbon_kg": carbon
            },
            "gamification": {
                "total_tokens": total_tokens,
                "remaining_tokens": remaining_tokens,
                "points": gamification_points
            }
        }), 200
    if result["status"] == "error":
        return jsonify({"status": "error", "message": result.get("error", "Unknown error")}), 500
            
        
if __name__ == '__main__':
    # Jalankan Flask server (bukan CLI)
    app.run(debug=True)
