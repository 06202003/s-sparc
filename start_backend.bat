@echo off
title S-SPARC FastAPI AI Backend (Port 8000)
echo Starting S-SPARC FastAPI Backend on port 8000...
python -m uvicorn backend.main:app --host 0.0.0.0 --port 8000 --reload
pause
