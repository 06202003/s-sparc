from __future__ import annotations

import asyncio
import json
import logging
from contextlib import asynccontextmanager
from logging.handlers import RotatingFileHandler
from pathlib import Path

from fastapi import BackgroundTasks, FastAPI, HTTPException, Query
from fastapi.responses import FileResponse, HTMLResponse, PlainTextResponse

from .evaluator.config import get_settings
from .evaluator.database import DatabaseClient
from .evaluator.evaluator_pipeline import EvaluatorPipeline
from .scheduler import EvaluatorScheduler


settings = get_settings()


def configure_logging() -> None:
    log_path = settings.log_dir / "code_evaluator_service.log"
    settings.log_dir.mkdir(parents=True, exist_ok=True)
    
    file_handler = RotatingFileHandler(log_path, maxBytes=5_000_000, backupCount=5, encoding="utf-8")
    formatter = logging.Formatter("%(asctime)s | %(levelname)s | %(name)s | %(message)s")
    file_handler.setFormatter(formatter)

    for logger_name in ("", "code_evaluator_service", "code_evaluator_service.evaluator.evaluator_pipeline", "code_evaluator_service.evaluator.llm_judge", "code_evaluator_service.evaluator.database"):
        log_obj = logging.getLogger(logger_name)
        log_obj.setLevel(logging.INFO)
        has_file = any(isinstance(h, RotatingFileHandler) and str(getattr(h, "baseFilename", "")) == str(log_path.resolve()) for h in log_obj.handlers)
        if not has_file:
            log_obj.addHandler(file_handler)


configure_logging()

database = DatabaseClient(settings)
pipeline = EvaluatorPipeline(settings=settings, database=database)
scheduler = EvaluatorScheduler(pipeline=pipeline, settings=settings)


@asynccontextmanager
async def lifespan(_: FastAPI):
    scheduler.start()
    try:
        yield
    finally:
        scheduler.shutdown()


app = FastAPI(title="Code Evaluator Service", version="1.0.0", lifespan=lifespan)


@app.get("/health")
async def health() -> dict:
    return {
        "status": "ok",
        "service": settings.service_name,
        "db_connected": database.ping(),
        "scheduler_running": scheduler.scheduler.running,
        "evaluation_running": pipeline.is_running(),
        "port": settings.port,
    }


@app.get("/run-evaluation")
async def run_evaluation(background_tasks: BackgroundTasks = None, background: bool = True) -> dict:
    if pipeline.is_running():
        return {
            "status": "already_running",
            "message": "Evaluation is already running in background",
        }

    if background:
        import threading
        thread = threading.Thread(target=pipeline.run, args=("manual-background",), daemon=True)
        thread.start()
        return {
            "status": "accepted",
            "message": "Evaluation started in background",
        }

    try:
        report = await asyncio.to_thread(pipeline.run, "manual")
        return report
    except RuntimeError as exc:
        raise HTTPException(status_code=409, detail=str(exc)) from exc
    except Exception as exc:
        raise HTTPException(status_code=500, detail=str(exc)) from exc


@app.get("/stop-evaluation")
async def stop_evaluation() -> dict:
    pipeline.reset_state()
    return {"status": "ok", "message": "Evaluation state has been reset to Idle."}


@app.get("/stats")
async def stats() -> dict:
    latest = pipeline.read_latest_stats()
    try:
        latest["current_db_entries"] = database.count_entries()
        latest["db_connected"] = True
    except Exception as exc:
        logging.getLogger(__name__).warning("Failed to count DB entries: %s", exc)
        latest["current_db_entries"] = None
        latest["db_connected"] = False
        latest["db_error"] = str(exc)
    latest["scheduler_running"] = scheduler.scheduler.running
    latest["evaluation_running"] = pipeline.is_running()
    latest["service_root"] = str(Path(__file__).resolve().parent)
    return latest


@app.get("/logs", response_class=PlainTextResponse)
async def get_logs(lines: int = Query(200, ge=1, le=5000)):
    log_path = settings.log_dir / "code_evaluator_service.log"
    if not log_path.exists():
        return "Log file not found."
    try:
        content = log_path.read_text(encoding="utf-8", errors="replace")
        log_lines = content.splitlines()
        return "\n".join(log_lines[-lines:])
    except Exception as exc:
        raise HTTPException(status_code=500, detail=str(exc)) from exc


@app.get("/clear-logs")
async def clear_logs() -> dict:
    log_path = settings.log_dir / "code_evaluator_service.log"
    try:
        if log_path.exists():
            log_path.write_text("", encoding="utf-8")
        return {"status": "ok", "message": "Log file cleared successfully."}
    except Exception as exc:
        raise HTTPException(status_code=500, detail=str(exc)) from exc


@app.get("/reports")
async def list_reports():
    reports = []
    if settings.report_dir.exists():
        for p in sorted(settings.report_dir.glob("*.json"), reverse=True):
            reports.append({
                "filename": p.name,
                "size_bytes": p.stat().st_size,
                "created_at": p.stat().st_mtime,
                "url": f"/reports/{p.name}"
            })
    return {"total": len(reports), "reports": reports}


@app.get("/reports/{filename}")
async def get_report_file(filename: str):
    file_path = settings.report_dir / filename
    if not file_path.exists() or not file_path.is_file():
        raise HTTPException(status_code=404, detail="Report file not found")
    return FileResponse(path=file_path, filename=filename, media_type="application/json")


@app.get("/backups")
async def list_backups():
    backups = []
    if settings.backup_dir.exists():
        for p in sorted(settings.backup_dir.glob("*.json"), reverse=True):
            backups.append({
                "filename": p.name,
                "size_bytes": p.stat().st_size,
                "created_at": p.stat().st_mtime,
                "url": f"/backups/{p.name}"
            })
    return {"total": len(backups), "backups": backups}


@app.get("/backups/{filename}")
async def get_backup_file(filename: str):
    file_path = settings.backup_dir / filename
    if not file_path.exists() or not file_path.is_file():
        raise HTTPException(status_code=404, detail="Backup file not found")
    return FileResponse(path=file_path, filename=filename, media_type="application/json")


@app.get("/", response_class=HTMLResponse)
async def web_dashboard():
    html_content = """<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S-SPARC Code Evaluator & Maintenance Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --card-border: rgba(255, 255, 255, 0.1);
            --teal-primary: #14b8a6;
            --teal-hover: #0d9488;
            --emerald-accent: #10b981;
            --amber-accent: #f59e0b;
            --rose-accent: #f43f5e;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top right, #1e1b4b, #0f172a);
            color: var(--text-main);
            min-height: 100vh;
            padding: 2rem;
        }

        .container { max-width: 1200px; margin: 0 auto; }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--card-border);
        }

        .logo-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #2dd4bf, #38bdf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .logo-title p { color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem; }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(52, 211, 153, 0.3);
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: #34d399;
            border-radius: 50%;
            box-shadow: 0 0 10px #34d399;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

        .grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            border-radius: 1rem;
            padding: 1.25rem;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
        }

        .card-label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; }
        .card-val { font-size: 1.75rem; font-weight: 700; margin-top: 0.5rem; color: #ffffff; }
        .card-sub { font-size: 0.8rem; color: var(--teal-primary); margin-top: 0.25rem; }

        .controls {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary { background: linear-gradient(135deg, #0d9488, #0284c7); color: white; box-shadow: 0 4px 14px rgba(13, 148, 136, 0.4); }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }

        .btn-rose { background: linear-gradient(135deg, #e11d48, #be123c); color: white; box-shadow: 0 4px 14px rgba(225, 29, 72, 0.4); }
        .btn-rose:hover { opacity: 0.9; transform: translateY(-1px); }

        .btn-secondary { background: rgba(255, 255, 255, 0.05); color: var(--text-main); border: 1px solid var(--card-border); }
        .btn-secondary:hover { background: rgba(255, 255, 255, 0.1); }

        .terminal-box {
            background: #090d16;
            border: 1px solid var(--card-border);
            border-radius: 1rem;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .terminal-header {
            background: #111827;
            padding: 0.75rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--card-border);
        }

        .terminal-title { font-size: 0.875rem; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem; }
        .terminal-body {
            padding: 1rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.825rem;
            color: #38bdf8;
            height: 380px;
            overflow-y: auto;
            white-space: pre-wrap;
            line-height: 1.5;
        }

        .links-flex {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .file-list { list-style: none; margin-top: 0.75rem; }
        .file-item {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0.8rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.85rem;
        }
        .file-item a { color: var(--teal-primary); text-decoration: none; }
        .file-item a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo-title">
                <h1>S-SPARC Code Evaluator Service</h1>
                <p>Knowledge Base Quality Assurance & Automated Maintenance System</p>
            </div>
            <div class="badge-status" id="health-badge">
                <span class="pulse-dot"></span> <span id="health-text">Service Healthy</span>
            </div>
        </header>

        <div class="grid-stats">
            <div class="card">
                <div class="card-label">Total DB Embeddings</div>
                <div class="card-val" id="db-entries">-</div>
                <div class="card-sub">Table: code_embeddings</div>
            </div>
            <div class="card">
                <div class="card-label">Status Evaluasi</div>
                <div class="card-val" id="eval-status">Idle</div>
                <div class="card-sub" id="eval-sub">Ready for manual / cron execution</div>
            </div>
            <div class="card">
                <div class="card-label">Cronjob Auto Clean</div>
                <div class="card-val">Aktif</div>
                <div class="card-sub">Setiap Minggu @ 03:00 WIB</div>
            </div>
            <div class="card">
                <div class="card-label">Port Active</div>
                <div class="card-val">5055</div>
                <div class="card-sub">http://localhost:5055</div>
            </div>
        </div>

        <div class="controls">
            <button class="btn btn-primary" onclick="triggerEvaluation()">
                ⚡ Jalankan Pembersihan Manual (Background)
            </button>
            <button class="btn btn-rose" onclick="stopEvaluation()">
                🛑 Stop / Reset Evaluasi
            </button>
            <button class="btn btn-secondary" onclick="fetchLogs()">
                🔄 Refresh Logs
            </button>
            <button class="btn btn-secondary" onclick="clearLogs()">
                🧹 Clear Logs
            </button>
            <a href="/stats" target="_blank" class="btn btn-secondary">📊 JSON Stats</a>
            <a href="/health" target="_blank" class="btn btn-secondary">🩺 JSON Health</a>
            <a href="/logs" target="_blank" class="btn btn-secondary">📜 Raw Log File</a>
        </div>

        <div class="terminal-box">
            <div class="terminal-header">
                <div class="terminal-title">
                    <span>💻 Dokumentasi Real-Time System Log (code_evaluator_service.log)</span>
                </div>
                <span style="font-size:0.75rem; color:#64748b;" id="log-status">Auto-refreshing every 3s</span>
            </div>
            <div class="terminal-body" id="log-console">Memuat log sistem...</div>
        </div>

        <div class="links-flex">
            <div class="card">
                <div class="card-label">Laporan Evaluasi Terbaru (Reports)</div>
                <ul class="file-list" id="reports-list">
                    <li class="file-item">Memuat laporan...</li>
                </ul>
            </div>
            <div class="card">
                <div class="card-label">Backup Data Otomatis (JSON Safety)</div>
                <ul class="file-list" id="backups-list">
                    <li class="file-item">Memuat file backup...</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        async function fetchStats() {
            try {
                let res = await fetch('stats');
                if(!res.ok) res = await fetch('/stats');
                const data = await res.json();
                document.getElementById('db-entries').innerText = data.current_db_entries !== undefined && data.current_db_entries !== null ? data.current_db_entries.toLocaleString() : '-';
                
                const isRunning = data.evaluation_running;
                document.getElementById('eval-status').innerText = isRunning ? 'Running' : 'Idle';
                document.getElementById('eval-status').style.color = isRunning ? '#f59e0b' : '#10b981';
                document.getElementById('eval-sub').innerText = isRunning ? 'Processing code batches in background...' : 'Ready for manual / cron execution';
            } catch (err) {
                console.error(err);
            }
        }

        async function fetchLogs() {
            try {
                let res = await fetch('logs?lines=250');
                if(!res.ok) res = await fetch('/logs?lines=250');
                let text = await res.text();
                if (text.startsWith('"') && text.endsWith('"')) {
                    try { text = JSON.parse(text); } catch(e) {}
                }
                const consoleElem = document.getElementById('log-console');
                consoleElem.innerText = text;
                consoleElem.scrollTop = consoleElem.scrollHeight;
            } catch (err) {
                console.error(err);
            }
        }

        async function fetchFiles() {
            try {
                let resR = await fetch('reports');
                if(!resR.ok) resR = await fetch('/reports');
                const dataR = await resR.json();
                const rList = document.getElementById('reports-list');
                if (dataR.reports && dataR.reports.length > 0) {
                    rList.innerHTML = dataR.reports.map(r => `
                        <li class="file-item">
                            <a href="${r.url.startsWith('/') ? '.' + r.url : r.url}" target="_blank">📄 ${r.filename}</a>
                            <span style="color:#64748b">${(r.size_bytes / 1024).toFixed(1)} KB</span>
                        </li>
                    `).join('');
                } else {
                    rList.innerHTML = '<li class="file-item" style="color:#64748b">Belum ada laporan evaluasi.</li>';
                }

                let resB = await fetch('backups');
                if(!resB.ok) resB = await fetch('/backups');
                const dataB = await resB.json();
                const bList = document.getElementById('backups-list');
                if (dataB.backups && dataB.backups.length > 0) {
                    bList.innerHTML = dataB.backups.map(b => `
                        <li class="file-item">
                            <a href="${b.url.startsWith('/') ? '.' + b.url : b.url}" target="_blank">📦 ${b.filename}</a>
                            <span style="color:#64748b">${(b.size_bytes / 1024).toFixed(1)} KB</span>
                        </li>
                    `).join('');
                } else {
                    bList.innerHTML = '<li class="file-item" style="color:#64748b">Belum ada file backup.</li>';
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function triggerEvaluation() {
            if(!confirm("Jalankan pembersihan & evaluasi anomali sekarang di background?")) return;
            try {
                let res = await fetch('run-evaluation?background=true');
                if(!res.ok) res = await fetch('/run-evaluation?background=true');
                const data = await res.json();
                alert(data.message || "Evaluasi berhasil dijalankan!");
                setTimeout(fetchStats, 500);
                setTimeout(fetchLogs, 500);
            } catch (err) {
                alert("Gagal menjalankan evaluasi: " + err);
            }
        }

        async function stopEvaluation() {
            if(!confirm("Hentikan/reset proses evaluasi sekarang?")) return;
            try {
                let res = await fetch('stop-evaluation');
                if(!res.ok) res = await fetch('/stop-evaluation');
                const data = await res.json();
                alert(data.message || "Evaluasi dihentikan.");
                setTimeout(fetchStats, 500);
                setTimeout(fetchLogs, 500);
            } catch (err) {
                alert("Gagal menghentikan evaluasi: " + err);
            }
        }

        async function clearLogs() {
            if(!confirm("Hapus seluruh isi log sistem sekarang?")) return;
            try {
                let res = await fetch('clear-logs');
                if(!res.ok) res = await fetch('/clear-logs');
                const data = await res.json();
                alert(data.message || "Log berhasil dihapus!");
                fetchLogs();
            } catch (err) {
                alert("Gagal menghapus log: " + err);
            }
        }

        fetchStats();
        fetchLogs();
        fetchFiles();
        setInterval(fetchStats, 3000);
        setInterval(fetchLogs, 3000);
        setInterval(fetchFiles, 5000);
    </script>
</body>
</html>"""
    return HTMLResponse(content=html_content)
