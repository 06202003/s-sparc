# S-SPARC — Unified Educational & Technical Architecture Blueprint
**System Designation:** S-SPARC: Specific Smart Prompting Assistant for peRformanCe  
**Target Competition:** UNU Macau & UNU Global AI Network — AI for SDGs 2026 (Track 1: AI for Education)  
**Document Designation:** `SSPARC_EDUCATION_ARCHITECTURE.md`  

---

## 1. High-Level Architectural Topology

```mermaid
flowchart TD
    subgraph Layer_1_Presentation ["1. Client & LMS Presentation Layer (E-STRANGE LMS)"]
        UI_Chat["Unified Chat Interface (chat.php)"]
        UI_CIOE_Bar["C-I-O-E Protocol Live Indicator Bar ([C] [I] [O] [E])"]
        UI_Bloom_Sel["Bloom Mode Selector (C1-C2 Summary / C3-C4 Code / C5-C6 Scaffolding)"]
        UI_Student_Dash["Student AI Literacy Profile (student_analytics.php)"]
        UI_Faculty_Dash["Faculty Learning Effectiveness Analytics (lecturer_analytics.php)"]
        UI_Defense_Flow["Plagiarism Defense Adjudication (student_assessment_submit_suspicious.php)"]
    end

    subgraph Layer_2_Metacognitive_Gateway ["2. Metacognitive Prompt Governance Layer (FastAPI)"]
        Len_Gate["200-Character Boundary Validator (200 <= chars <= 2000)"]
        Cooldown_Gate["60-Second Deliberate Metacognitive Reflection Cooldown"]
        Prompt_Linter["Real-Time Prompt Linter (Shannon Entropy & C-I-O-E Completeness)"]
    end

    subgraph Layer_3_Hybrid_Retrieval ["3. Dense-Sparse Hybrid Retrieval Layer"]
        Dense_HNSW["Dense Semantic Embedding Search (all-MiniLM-L6-v2, 384-Dim)"]
        Sparse_BM25["Sparse Lexical Keyword Search (BM25Okapi Inverted Index)"]
        RRF_Fusion["Reciprocal Rank Fusion (RRF k=60)"]
        Fast_Gate{"0-Token Fast Path Gate<br/>Cosine Sim s >= 0.88?"}
    end

    subgraph Layer_4_Headroom_Compression ["4. Headroom Context Compression Layer"]
        AST_Compressor["AST CodeCompressor (Strips comments, docstrings & boilerplate, -78.8%)"]
        Cache_Aligner["CacheAligner (Deterministic prefix freezing for >=85% KV-cache hit)"]
        Output_Shaper["Output Shaper & Verbosity Steering (Terse delta diffs, -63.5%)"]
    end

    subgraph Layer_5_Adaptive_Inference ["5. Multi-Tier Adaptive AI Gateway"]
        Tier1_Key["Tier 1: Personal Google Gemini Key (1,500 RPD User Isolation)"]
        Tier2_Pool["Tier 2: Institutional 6-Key Gemini Pool (Shared Failover)"]
        Tier3_Local["Tier 3: Local Sovereign Ollama (Qwen2.5-Coder 14B Offline)"]
    end

    subgraph Layer_6_Autonomous_Governance ["6. Closed-Loop Quality Governance Daemon (code_evaluator_service)"]
        AST_Checker["Python AST Syntax & Language Validator"]
        Radon_Metrics["Radon Cyclomatic Complexity & Maintainability Index (MI >= 75)"]
        LLM_Judge["Multi-Criteria LLM-as-a-Judge (Alignment, Logic, Quality)"]
        IsoForest_Screen["Isolation Forest 5D Anomaly Outlier Filter (gamma = 0.10)"]
        SHA_Dedup["SHA-256 Cryptographic Deduplication Engine"]
        Auto_Pruner["Automated Pre-Deletion Backup & Hygiene Engine"]
    end

    subgraph Layer_7_Storage_Analytics ["7. Enterprise Storage & Analytics Layer (MySQL 37 Tables)"]
        DB_Vectors[("code_embeddings (Vector Store & Knowledge Base)")]
        DB_Learning_Logs[("educational_learning_logs & ai_literacy_profiles")]
        DB_LMS_Tables[("course, assessment, submission, suspicion_reports, peer_reviews")]
    end

    UI_Chat --> UI_CIOE_Bar --> UI_Bloom_Sel --> Len_Gate --> Cooldown_Gate --> Prompt_Linter
    Prompt_Linter --> Dense_HNSW & Sparse_BM25 --> RRF_Fusion --> Fast_Gate
    
    Fast_Gate -- "Cache Hit (s >= 0.88)" --> DB_Learning_Logs
    Fast_Gate -- "Cache Hit (s >= 0.88)" --> UI_Chat
    
    Fast_Gate -- "Cache Miss (s < 0.88)" --> AST_Compressor --> Cache_Aligner --> Output_Shaper --> Tier1_Key
    Tier1_Key -- "Fail / Quota 429" --> Tier2_Pool
    Tier2_Pool -- "Exhausted" --> Tier3_Local
    
    Tier1_Key & Tier2_Pool & Tier3_Local --> DB_Learning_Logs & DB_Vectors
    
    Layer_6_Autonomous_Governance -.->|"Asynchronous Hygiene Cycle"| DB_Vectors
    UI_Defense_Flow & UI_Faculty_Dash & UI_Student_Dash <--> DB_LMS_Tables & DB_Learning_Logs
```

---

## 2. The Complete Student Learning & Data Flow

```mermaid
sequenceDiagram
    autonumber
    actor Student as Student (Learner)
    participant LMS as E-STRANGE LMS (chat.php)
    participant Linter as Prompt Linter & Gate
    participant FastPath as 0-Token Semantic Cache
    participant LLM as Multi-Tier AI Gateway
    participant Analytics as Learning Analytics Service
    participant Evaluator as Autonomous Evaluator Daemon

    Student->>LMS: Types prompt using C-I-O-E live indicator bar
    LMS->>Linter: Pre-flight check (Length >= 200, Entropy, C-I-O-E components)
    Linter-->>LMS: Return literacy score & pedagogical suggestions
    
    Student->>LMS: Submits prompt + selects Bloom Cognitive Mode
    LMS->>Linter: Validate 60s cooldown & API key registration
    
    Linter->>FastPath: Query vector store (Hybrid Dense-Sparse RRF k=60)
    alt Fast-Path Hit (Cosine Similarity s >= 0.88)
        FastPath-->>LMS: Return verified solution (0 Tokens, < 45ms)
        LMS->>Analytics: Log event (FastPath=1, Tokens=0)
    else Fast-Path Miss (s < 0.88)
        FastPath->>LLM: Compress RAG context (-78.8%) + Prefix Freeze + Verbosity Shaper
        LLM-->>LMS: Return Bloom-tiered response (Summary / Code / Scaffolding)
        LMS->>Analytics: Log event (PromptQuality, BloomMode, ConsumedTokens)
        LMS->>FastPath: Ingest candidate interaction into code_embeddings
    end
    
    LMS-->>Student: Render response + Start 60s Metacognitive Cooldown
    Student->>Student: Reads concept, traces logic, tests code in IDE
    
    opt Asynchronous Background Hygiene
        Evaluator->>FastPath: Audit ingested snippets (AST + Radon + Isolation Forest)
        Evaluator->>FastPath: Prune invalid/duplicate code, backup JSON
    end
```

---

## 3. The 4 Unified System Flows

1. **Student Metacognitive Learning Flow:**
   - Problem Encounter $\rightarrow$ C-I-O-E Formulation $\rightarrow$ 60s Reflection Cooldown $\rightarrow$ Bloom Cognitive Scaffolding $\rightarrow$ Code Tracing & Testing $\rightarrow$ Independent Mastery.
2. **AI Inference & Compression Flow:**
   - Query Ingestion $\rightarrow$ Hybrid RRF Retrieval $\rightarrow$ 0-Token Fast-Path Gate ($s \ge 0.88$) $\rightarrow$ AST CodeCompressor $\rightarrow$ CacheAligner $\rightarrow$ Multi-Tier Gateway (Gemini BYOK / Ollama Offline).
3. **Closed-Loop Knowledge Governance Flow:**
   - Unverified Pair Ingestion $\rightarrow$ Python AST Syntax Verification $\rightarrow$ Radon CC/MI Metric Scoring $\rightarrow$ LLM Judge Evaluation $\rightarrow$ Isolation Forest 5D Anomaly Filter $\rightarrow$ Pre-Deletion JSON Backup $\rightarrow$ Database Self-Healing.
4. **Learning Analytics & Sustainability Flow:**
   - Interaction Event Recording $\rightarrow$ Shannon Entropy Calculation $\rightarrow$ Cognitive Independence Indexing $\rightarrow$ Physical Footprint Estimation ($\text{Wh}$, $\text{CO}_2\text{e}$, $\text{H}_2\text{O}$) $\rightarrow$ Student Profile Badging & Faculty Dashboard Aggregation.
