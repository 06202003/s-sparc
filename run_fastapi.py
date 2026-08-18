import sys
import os
import uvicorn
from backend.main import create_app

app = create_app()

if __name__ == "__main__":
    print("[INFO] Starting S-SPARC FastAPI Production Backend on port 5000...", flush=True)
    uvicorn.run("run_fastapi:app", host="127.0.0.1", port=5000, log_level="info", access_log=True)
