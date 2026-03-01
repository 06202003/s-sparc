"""
Production-ready WSGI server runner for S-SPARC AI
Uses Waitress (Windows-compatible) with background GPT worker thread
"""

import sys
import os
import threading
import atexit

# Import Flask app
from app import app, gpt_job_worker, save_global_emissions, global_tracker

def main():
    try:
        from waitress import serve
    except ImportError:
        print("[ERROR] Waitress not installed. Please run:")
        print("  pip install waitress")
        sys.exit(1)
    
    # Start global emissions tracker if available
    if global_tracker is not None:
        try:
            global_tracker.start()
            print("[INFO] Global emissions tracker started")
        except Exception as e:
            print(f"[WARNING] Could not start global emissions tracker: {e}")
    
    # Register cleanup on exit
    atexit.register(save_global_emissions)
    
    # Start background worker thread for GPT job processing
    print("[INFO] Starting background GPT job worker thread...")
    worker_thread = threading.Thread(target=gpt_job_worker, daemon=True, name="GPTWorker")
    worker_thread.start()
    print("[INFO] Background worker started successfully")
    
    # Pre-load models and retrieval cache in background
    def preload_all():
        import time
        time.sleep(2)  # Give Waitress time to initialize
        print("[INFO] Pre-loading models and retrieval cache...")
        try:
            # Import lazy loaded functions
            from app import _ensure_models_loaded, get_retrieval_model
            _ensure_models_loaded()
            print("[INFO] Sentence Transformer models loaded!")
            get_retrieval_model(force_refresh=True)
            print("[INFO] Retrieval model cache loaded!")
            print("[INFO] All pre-loading completed!")
        except Exception as e:
            print(f"[WARNING] Pre-loading failed: {e}")
    
    preload_thread = threading.Thread(target=preload_all, daemon=True, name="Preloader")
    preload_thread.start()
    
    # Get configuration from environment or defaults
    host = os.getenv('FLASK_HOST', 'localhost')  # Changed to localhost for local dev
    port = int(os.getenv('FLASK_PORT', '5000'))
    threads = int(os.getenv('WAITRESS_THREADS', '50'))  # High thread count for parallelism
    
    print(f"[INFO] Starting Waitress WSGI server on {host}:{port}")
    print(f"[INFO] Thread pool size: {threads} (optimized for I/O parallelism)")
    print(f"[INFO] Parallelism: Login/Register/Courses NEVER blocked by LLM")
    print(f"[INFO] Press Ctrl+C to stop the server")
    print("-" * 60)
    
    # Serve with Waitress (production-ready WSGI server)
    serve(
        app,
        host=host,
        port=port,
        threads=threads,  # High thread count
        channel_timeout=600,  # 10 minutes timeout for long-running requests
        connection_limit=500,  # Support 500 concurrent connections
        asyncore_use_poll=True,  # Better for many connections
        url_scheme='http',
        ident='S-SPARC-AI-Parallel/1.0'
    )

if __name__ == '__main__':
    main()
