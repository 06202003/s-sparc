import yaml
import json
import re
import os

def strip_emojis(text: str) -> str:
    # Regex to remove common emoji characters
    emoji_pattern = re.compile(
        "["
        "\U0001F600-\U0001F64F"  # emoticons
        "\U0001F300-\U0001F5FF"  # symbols & pictographs
        "\U0001F680-\U0001F6FF"  # transport & map symbols
        "\U0001F1E0-\U0001F1FF"  # flags (iOS)
        "\U00002702-\U000027B0"
        "\U000024C2-\U0001F251"
        "\U0001F900-\U0001F9FF"  # Supplemental Symbols and Pictographs
        "\U0001FA70-\U0001FAFF"  # Symbols and Pictographs Extended-A
        "\U00002600-\U000026FF"  # Miscellaneous Symbols
        "]+", flags=re.UNICODE
    )
    clean_text = emoji_pattern.sub("", text)
    # Clean double spaces caused by emoji removal
    clean_text = re.sub(r' +', ' ', clean_text)
    return clean_text

def build_openapi_desc():
    # Read the full system flow diagrams content
    with open('docs/system_flow_diagrams.md', 'r', encoding='utf-8') as f:
        diagrams_content = f.read()

    # Clean emojis from diagrams content
    diagrams_content = strip_emojis(diagrams_content)

    desc = f"""# S-SPARC AI & E-STRANGE Platform Documentation

Welcome to the official developer documentation and API reference for **S-SPARC (Sustainable Smart Personal Assistant for Responsible Consumption)** integrated with the **E-STRANGE Learning Management Platform**.

---

## System Architecture Overview

S-SPARC is engineered around 4 core operational tiers designed for high-concurrency academic software engineering tutoring:

1. **E-STRANGE Parent LMS (PHP Platform)**:
   - Handles student course enrollment, assignment submissions, automated similarity analysis (`suspicion`), peer reviews (`code_clarity_suggestion`), gamification leaderboards, and grade tracking.
2. **S-SPARC AI Frontend (PHP/JS Client)**:
   - Interactive eco-aware coding workspace featuring token usage gauges, real-time AI responses, and environmental footprint visualizers.
3. **S-SPARC FastAPI Backend (Python Core)**:
   - Core API layer delivering user session auth, semantic search cache (0 Token FREE tier), **Adaptive Router** (Gemini Flash Lite 6-Key Pool + Ollama Qwen2.5-Coder 14B), and environmental footprint telemetry (Wh, kg CO2e, mL H2O).
4. **Code Evaluator Microservice (Quality Control Engine)**:
   - Snippet-aware governance service utilizing LLM-as-a-Judge, static code analysis (Radon/AST), cosine similarity filtering, and Isolation Forest anomaly detection.

---

## Hybrid LLM Adaptive Router Rules

The updated **Adaptive Router** intelligently routes student inference requests based on course game policy and Gemini key availability:

* **Game ON Courses (`game_course.is_active = 1`)**:
  - Governed by E-STRANGE Gamification Points (>= 100 pts required).
  - If points >= 100: Routed to **Cloud (Google Gemini Flash Lite)**. Deducts 10 points per successful request.
  - If points < 100: Routed to **Local (Ollama Qwen2.5-Coder 14B)**. Zero points deducted.
* **Game OFF Courses (`game_course.is_active = 0`)**:
  - Governed by Token Quota Limit (`GAME_OFF_TOKEN_LIMIT=5000` tokens). Gamification points are NOT deducted (0 pts).
  - If tokens < 5000: Routed to **Cloud (Google Gemini Flash Lite)**.
  - If tokens >= 5000: Routed to **Local (Ollama Qwen2.5-Coder 14B)**.
* **Technical Failover (Rate Limit 429)**:
  - If all 6 Gemini API Keys in the key pool (`GEMINI_API_KEY_1..6`) experience rate limits, request automatically fails over to **Local Ollama** (`fallback_triggered = True`) without deducting points.

---

## Complete System Flow Diagrams (11 Workflows & Visual Diagrams)

{diagrams_content}

---

## Quick Start: Running the Full Stack (FE + BE + E-STRANGE + S-SPARC)

### 1. Database Setup (MySQL / MariaDB)
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS estrange_ssparc;"
mysql -u root -p estrange_ssparc < db_semantic_vfinal.sql
```

### 2. Environment Configuration (`.env`)
```env
MYSQL_HOST=127.0.0.1
MYSQL_PORT=3306
MYSQL_USER=root
MYSQL_PASSWORD=your_password
MYSQL_DB=estrange_ssparc

GEMINI_API_KEY_1=AIzaSy...
GEMINI_API_KEY_2=AIzaSy...
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=qwen2.5-coder:14b
MIN_POINTS_CLOUD=100
POINTS_PER_CLOUD_REQUEST=10
GAME_OFF_TOKEN_LIMIT=5000
FLASK_SECRET_KEY=supersecretkey_ssparc_2026
```

### 3. Running Component 1: E-STRANGE Parent Platform (PHP Core)
```bash
cd estrange/v2/v2
php -S 0.0.0.0:8080
```

### 4. Running Component 2: S-SPARC Frontend (PHP/JS App)
```bash
cd frontend
php -S 0.0.0.0:8000
```

### 5. Running Component 3: S-SPARC FastAPI Backend (Python Core)
```bash
python run_fastapi.py
```

---

## Scientific Environmental Footprint Formulas

$$\text{{Energy (Wh)}} = P_{{\text{{GPU/CPU}}}} \times T_{{\text{{inference}}}}$$

$$\text{{Carbon (kg CO}}_2\text{{e)}} = \frac{{\text{{Energy (Wh)}}}}{{1000}} \times \text{{Emission Factor (0.725 kg CO}}_2\text{{e/kWh)}}$$

$$\text{{Water (mL)}} = \text{{Energy (kWh)}} \times \text{{Cooling Factor (1.8 L/kWh)}} \times 1000$$

$$\text{{Eco Token Threshold}} = \max(0, 1.10 \times \text{{Peer Average Tokens}})$$

---
"""
    return desc

def main():
    full_desc = build_openapi_desc()
    
    # 1. Update docs/openapi.yaml
    with open('docs/openapi.yaml', 'r', encoding='utf-8') as f:
        spec_yaml = yaml.safe_load(f)
    
    spec_yaml['info']['description'] = full_desc
    
    with open('docs/openapi.yaml', 'w', encoding='utf-8') as f:
        yaml.dump(spec_yaml, f, sort_keys=False, allow_unicode=True, width=1000)

    # 2. Sync docs/openapi.json
    with open('docs/openapi.json', 'w', encoding='utf-8') as f:
        json.dump(spec_yaml, f, indent=2, ensure_ascii=False)

    # 3. Clean emojis from backend/main.py
    with open('backend/main.py', 'r', encoding='utf-8') as f:
        main_py = f.read()
    main_py_clean = strip_emojis(main_py)
    with open('backend/main.py', 'w', encoding='utf-8') as f:
        f.write(main_py_clean)

    # 4. Clean emojis from markdown docs
    for path in ['docs/system_flow_diagrams.md', 'docs/DIAGRAM_ALIR_S-SPARC_ESTRANGE.md', 'DIAGRAM_ALIR_S-SPARC_ESTRANGE.md', 'README.md']:
        if os.path.exists(path):
            with open(path, 'r', encoding='utf-8') as f:
                content = f.read()
            clean_content = strip_emojis(content)
            with open(path, 'w', encoding='utf-8') as f:
                f.write(clean_content)

    print("Updated docs/openapi.yaml, openapi.json, backend/main.py, and all docs without emojis!")

if __name__ == "__main__":
    main()

