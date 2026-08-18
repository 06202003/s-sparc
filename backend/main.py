import os
from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from starlette.middleware.sessions import SessionMiddleware
from dotenv import load_dotenv

# Initialize Environment
load_dotenv()

tags_metadata = [
 {
 "name": "Authentication",
 "description": "User registration, login session establishment, whoami profile validation, and secure password reset (with token / OTP or direct username + email verification)."
 },
 {
 "name": "AI Chatbot",
 "description": r"Eco-aware programming assistant with **Semantic Search Fast-Path (0 Token / FREE Tier)** for similarity >= 90%, streaming inference, pure code filtering, and auto-knowledge ingestion into `code_embeddings`."
 },
 {
 "name": "Domain/Learning",
 "description": r"Academic course enrollment, assessment tracking, daily/weekly token quota consumption, scientific environmental footprint metrics (Wh, kg CO2e, mL), and gamified leaderboards."
 },
 {
 "name": "Administrative",
 "description": "Restricted instructor and administrator tools: aggregate sustainability metrics, CSV exports, point recalculation algorithms, and system diagnostics."
 },
 {
 "name": "System Health",
 "description": "Core uptime, service diagnostics, and connection health endpoints."
 }
]

def create_app() -> FastAPI:
 app = FastAPI(
 title="S-SPARC AI & E-STRANGE Platform - API Reference",
 description=r"""
# S-SPARC AI & E-STRANGE Platform Documentation

**S-SPARC (Sustainable Smart Personal Assistant for Responsible Consumption)** integrated with the **E-STRANGE Learning Management Platform** is an AI-powered coding and tutoring system designed to optimize compute consumption, track scientific environmental footprints, and provide gamified learning feedback.

---

## Hybrid LLM Adaptive Router Rules

The system employs an **Adaptive Router** combining **Google Gemini Flash Lite** (Cloud 6-Key Pool) and **Ollama Qwen2.5-Coder 14B** (Local):

1. **Game ON Courses (`game_course.is_active = 1`)**:
 - Governed by E-STRANGE Gamification Points ($\ge 100$ pts required).
 - If points $\ge 100$: Routed to **Cloud (Gemini Flash Lite)**. Deducts 10 points per request.
 - If points $< 100$: Routed to **Local (Ollama Qwen2.5-Coder 14B)**. 0 points deducted.

2. **Game OFF Courses (`game_course.is_active = 0`)**:
 - Governed by Token Quota Limit (`GAME_OFF_TOKEN_LIMIT=5000` tokens). 0 gamification points deducted.
 - If tokens $< 5000$: Routed to **Cloud (Gemini Flash Lite)**.
 - If tokens $\ge 5000$: Routed to **Local (Ollama Qwen2.5-Coder 14B)**.

3. **Technical Failover (Rate Limit 429)**:
 - If all 6 Gemini API Keys hit HTTP 429 rate limits, request automatically fails over to **Local Ollama** without deducting points.

---

## Complete System Flow Diagrams (11 Visual Workflows)

Available in `docs/system_flow_diagrams.md` and `docs/DIAGRAM_ALIR_S-SPARC_ESTRANGE.md`:
- **Diagram 1-5 (E-STRANGE PHP)**: Code Submission & SIM Check, Peer Review Rating, Plagiarism Suspicion & Student Defense, Course/Game Admin (`game_course.is_active`), Leaderboard Aggregation.
- **Diagram 6-11 (S-SPARC FastAPI)**: System Architecture, Adaptive Router Engine, Gemini 6-Key Pool Failover, Cross-System Points Aggregation, Knowledge Base Ingestion & Evaluator Service, Environmental Footprint Telemetry.

---

## Key Architectural Pillars

1. **Semantic Vector Search (FREE Tier)**:
 - Queries with cosine similarity >= 90% are answered instantly from the semantic cache (`code_embeddings`) with **0 Token Cost**.

2. **Eco-Aware Token Threshold**:
 - Dynamic threshold calculated as: `Threshold = max(0, 1.10 * peer_average_usage)`.

3. **Scientific Environmental Footprint Tracking**:
 - Energy (Wh), Carbon Footprint (kg CO2e), Freshwater Consumption (mL).
 """,

 version="3.0.0",
 openapi_tags=tags_metadata,
 contact={
 "name": "E-STRANGE & S-SPARC Academic Engineering Team",
 "email": "support@s-sparc.ac.id",
 "url": "http://127.0.0.1:8080"
 },
 license_info={
 "name": "Academic Research & Sustainability License 1.0",
 },
 docs_url="/docs",
 redoc_url="/redoc",
 openapi_url="/openapi.json"
 )

 # Configure CORS
 app.add_middleware(
 CORSMiddleware,
 allow_origins=["*"],
 allow_credentials=True,
 allow_methods=["*"],
 allow_headers=["*"],
 )

 # Configure Session Middleware to match Flask/PHP cookie-based session
 secret_key = os.getenv("FLASK_SECRET_KEY", "supersecretkey")
 app.add_middleware(
 SessionMiddleware, 
 secret_key=secret_key,
 session_cookie="session"
 )

 @app.get(
 "/health",
 summary="Service Health Check",
 description="Returns current operational status and confirming that the FastAPI backend is running.",
 tags=["System Health"]
 )
 async def health_check():
     return {"status": "ok", "message": "S-SPARC FastAPI backend is running.", "version": "3.0.0"}

 @app.get(
 "/redocly",
 summary="Redocly Interactive API Documentation",
 description="Serves the compiled Redocly standalone HTML documentation bundle.",
 tags=["System Health"],
 include_in_schema=False
 )
 async def redocly_docs():
     from fastapi.responses import FileResponse
     docs_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), "docs", "index.html")
     if os.path.exists(docs_path):
         return FileResponse(docs_path, media_type="text/html")
     raise HTTPException(status_code=404, detail="Redocly documentation bundle not found.")

 # Include API routers (support both root and /api prefixes)
 from backend.api import auth, admin, domain, ai_chat
 app.include_router(auth.router, prefix="", tags=["Authentication"])
 app.include_router(admin.router, prefix="", tags=["Administrative"])
 app.include_router(domain.router, prefix="", tags=["Domain/Learning"])
 app.include_router(ai_chat.router, prefix="", tags=["AI Chatbot"])
 app.include_router(auth.router, prefix="/api", tags=["Authentication"], include_in_schema=False)
 app.include_router(admin.router, prefix="/api", tags=["Administrative"], include_in_schema=False)
 app.include_router(domain.router, prefix="/api", tags=["Domain/Learning"], include_in_schema=False)
 app.include_router(ai_chat.router, prefix="/api", tags=["AI Chatbot"], include_in_schema=False)

 return app

app = create_app()

if __name__ == "__main__":
 import uvicorn
 uvicorn.run("backend.main:app", host="0.0.0.0", port=8000, reload=True)

