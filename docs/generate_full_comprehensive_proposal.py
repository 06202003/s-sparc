# -*- coding: utf-8 -*-
import subprocess
import os

html_content = r"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>S-SPARC AI: Quantifying AI Over-Reliance in Programming Education - Comprehensive Proposal</title>
<style>
  @page {
    size: A4 portrait;
    margin: 20mm 18mm 22mm 18mm;
    @bottom-right {
      content: counter(page);
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      font-size: 8.5pt;
      color: #64748b;
    }
  }
  * {
    box-sizing: border-box;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  body {
    margin: 0;
    padding: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #1e293b;
    background-color: #ffffff;
    font-size: 9.5pt;
    line-height: 1.55;
  }

  /* Header */
  .doc-header {
    border-bottom: 1.5px solid #0f172a;
    padding-bottom: 6px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 8pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #475569;
  }
  .doc-header .inst {
    color: #1d4ed8;
  }

  /* Title Block */
  .title-block {
    margin-bottom: 18px;
  }
  .doc-category {
    display: inline-block;
    font-size: 8pt;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #1d4ed8;
    background-color: #eff6ff;
    border: 1px solid #bfdbfe;
    padding: 2px 8px;
    border-radius: 4px;
    margin-bottom: 8px;
  }
  h1.doc-title {
    font-size: 17pt;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.25;
    margin: 0 0 6px 0;
    letter-spacing: -0.01em;
  }
  .doc-subtitle {
    font-size: 10.5pt;
    font-weight: 500;
    color: #475569;
    line-height: 1.4;
    margin: 0 0 12px 0;
  }

  /* Metadata Strip */
  .meta-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 8px 12px;
    margin-bottom: 16px;
  }
  .meta-item .label {
    font-size: 7pt;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.04em;
    margin-bottom: 1px;
  }
  .meta-item .val {
    font-size: 8.5pt;
    font-weight: 600;
    color: #0f172a;
  }

  /* Abstract Box */
  .abstract-box {
    background-color: #f8fafc;
    border-left: 3.5px solid #1d4ed8;
    border-radius: 0 6px 6px 0;
    padding: 10px 14px;
    margin-bottom: 18px;
  }
  .abstract-title {
    font-size: 8.5pt;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #1d4ed8;
    margin-bottom: 3px;
  }
  .abstract-text {
    font-size: 9pt;
    color: #334155;
    line-height: 1.45;
    margin: 0;
  }

  /* Headings */
  h2.sec-title {
    font-size: 11.5pt;
    font-weight: 800;
    color: #0f172a;
    margin: 18px 0 6px 0;
    padding-bottom: 3px;
    border-bottom: 1px solid #e2e8f0;
    letter-spacing: -0.01em;
    page-break-after: avoid;
  }
  h3.sub-title {
    font-size: 9.8pt;
    font-weight: 700;
    color: #1e293b;
    margin: 10px 0 3px 0;
    page-break-after: avoid;
  }

  /* Boxes & Grids */
  .grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin: 8px 0;
    page-break-inside: avoid;
  }
  .grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 8px;
    margin: 8px 0;
    page-break-inside: avoid;
  }
  .grid-4 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1fr;
    gap: 8px;
    margin: 8px 0;
    page-break-inside: avoid;
  }
  .card {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 8px 10px;
  }
  .card-title {
    font-size: 8.5pt;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 3px;
  }

  /* Tables */
  table.doc-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8.3pt;
    margin: 8px 0 12px 0;
    page-break-inside: avoid;
  }
  table.doc-table th {
    background-color: #f8fafc;
    color: #0f172a;
    font-weight: 700;
    text-align: left;
    padding: 6px 8px;
    border-top: 1px solid #cbd5e1;
    border-bottom: 1px solid #cbd5e1;
  }
  table.doc-table td {
    padding: 5px 8px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
    color: #334155;
    line-height: 1.4;
  }
  table.doc-table tr:last-child td {
    border-bottom: 1px solid #cbd5e1;
  }

  /* Formula */
  .math-box {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 8px 12px;
    text-align: center;
    margin: 8px 0;
    page-break-inside: avoid;
  }
  .math-text {
    font-family: "Courier New", Courier, monospace;
    font-size: 10.5pt;
    font-weight: 700;
    color: #0f172a;
  }
  .math-sub {
    font-size: 8pt;
    color: #64748b;
    margin-top: 3px;
  }

  /* Lists & Typography */
  p {
    margin: 0 0 6px 0;
  }
  ul, ol {
    margin: 0 0 6px 0;
    padding-left: 18px;
  }
  li {
    margin-bottom: 3px;
  }
  code {
    font-family: "Courier New", Courier, monospace;
    font-size: 8pt;
    background-color: #f1f5f9;
    color: #0f172a;
    padding: 1px 3px;
    border-radius: 3px;
  }

  .references {
    font-size: 7.8pt;
    color: #475569;
    line-height: 1.35;
    margin-top: 12px;
  }
  .references p {
    margin-bottom: 4px;
    text-indent: -16px;
    padding-left: 16px;
  }
</style>
</head>
<body>

<!-- HEADER -->
<div class="doc-header">
  <span class="inst">Universitas Kristen Maranatha &bull; Faculty of Information Technology</span>
  <span>Comprehensive Research Proposal & Technical Specification (2026)</span>
</div>

<!-- TITLE BLOCK -->
<div class="title-block">
  <div class="doc-category">Formal Research Proposal & Technical Specification</div>
  <h1 class="doc-title">Quantifying AI Over-Reliance in Programming Education: A Telemetry-Driven Behavioral Metric with Productive Friction in S-SPARC</h1>
  <div class="doc-subtitle">A Complete Empirical Framework for Diagnosing Epistemic Dependence, Latent Construct Modeling, and Closed-Loop Scaffolding on the E-STRANGE Learning Platform</div>

  <div class="meta-strip">
    <div class="meta-item">
      <div class="label">Principal Investigator</div>
      <div class="val">Faculty Research Team &bull; S-SPARC Group</div>
    </div>
    <div class="meta-item">
      <div class="label">Research Domains</div>
      <div class="val">AIED, EDM, Learning Analytics</div>
    </div>
    <div class="meta-item">
      <div class="label">Operational Testbed</div>
      <div class="val">S-SPARC Engine & E-STRANGE LMS</div>
    </div>
    <div class="meta-item">
      <div class="label">Target Publication</div>
      <div class="val">IEEE TALE / Scopus Q1 (IEEE TLT)</div>
    </div>
  </div>
</div>

<!-- ABSTRACT -->
<div class="abstract-box">
  <div class="abstract-title">Executive Summary</div>
  <p class="abstract-text">
    Generative AI accelerates software development but introduces severe pedagogical risks in computer science education, including cognitive offloading, epistemic dependence, and degradation of analytical self-debugging skills. This research proposal details the design, mathematical formulation, data architecture, and empirical validation of the <strong>AI Over-Reliance Index (ORI)</strong>. Integrated within the S-SPARC AI assistant and E-STRANGE automated assessment system, the framework captures millisecond-level telemetry (Shannon entropy, C-I-O-E problem completeness, conceptual ratio, keystroke cadence) with zero manual survey overhead. We establish a robust two-phase psychometric roadmap (Phase 1: Exploratory Factor Analysis; Phase 2: Independent Confirmatory Factor Analysis and convergent validation against the standardized Hou et al. 2025 scale) while resolving critical methodological threats including maturation confounders, entropy inversion paradox, classifier error propagation, and Pygmalion bias.
  </p>
</div>

<!-- SECTION 1 -->
<h2 class="sec-title">1. Introduction & Problem Formulation</h2>
<p>
  The proliferation of Large Language Models (LLMs) has introduced a critical challenge to higher computing education: while students can generate syntactically correct code instantaneously, unassisted problem-solving ability declines. In laboratory sessions, students frequently execute a passive feedback loop:
</p>
<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px; text-align: center; font-family: monospace; font-size: 8.5pt; font-weight: 600; margin: 6px 0;">
  Copy Assignment Prompt &rarr; Paste into AI &rarr; Copy Output Code &rarr; Submit to Auto-Grader
</div>
<p>
  Because automated graders evaluate only input/output correctness, students pass unit tests without engaging in syntax tracing, logic decomposition, or algorithmic reflection. This phenomenon (cognitive atrophy) produces severe failures during closed-book, unassisted evaluations [2], [3], [6].
</p>

<!-- SECTION 2 -->
<h2 class="sec-title">2. State of the Art & Research Gap</h2>
<table class="doc-table">
  <thead>
    <tr>
      <th style="width: 24%;">Methodological Paradigm</th>
      <th style="width: 38%;">Critical Limitations & Vulnerabilities</th>
      <th style="width: 38%;">S-SPARC Telemetry Framework</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>Subjective Likert Surveys</strong> [2], [7]</td>
      <td>Recall bias, social desirability, cannot measure live behavior during coding.</td>
      <td><strong>Passive Millisecond Telemetry:</strong> Automatic logging of typing speed, pauses, and prompt quality per millisecond.</td>
    </tr>
    <tr>
      <td><strong>Binary Plagiarism Detectors</strong></td>
      <td>High false positives, purely punitive, zero formative learning guidance.</td>
      <td><strong>Cognitive Inquiry Profiling:</strong> Longitudinally maps problem decomposition and epistemic autonomy [5].</td>
    </tr>
    <tr>
      <td><strong>Post-Hoc Eye-Tracking</strong> [16]</td>
      <td>High laboratory rigor but cannot run continuously in active classroom settings.</td>
      <td><strong>Real-Time Metric (ORI):</strong> Live composite calculation triggering dynamic adaptive scaffolding.</td>
    </tr>
    <tr>
      <td><strong>Unregulated AI Tools</strong> [5]</td>
      <td>Encourages prompt spamming and full code outsourcing without reflection.</td>
      <td><strong>Productive Friction:</strong> Mandatory 60s pause with stepped Socratic hints [9], [10].</td>
    </tr>
  </tbody>
</table>

<!-- SECTION 3 -->
<h2 class="sec-title">3. Mathematical Metric Formulation (AI Over-Reliance Index)</h2>
<p>
  The composite AI Over-Reliance Index (ORI) is defined on the domain [0, 1], where 0.0 represents fully autonomous problem-solving and 1.0 indicates complete epistemic dependence:
</p>

<div class="math-box">
  <div class="math-text">ORI = 1 - (w1 &middot; Q_prompt + w2 &middot; S_CIOE + w3 &middot; R_concept + w4 &middot; F_reflect)</div>
  <div class="math-sub">Where indicators are normalized to [0, 1] &bull; Baseline exploratory weights: w1=0.30, w2=0.25, w3=0.20, w4=0.25</div>
</div>

<div class="grid-4">
  <div class="card">
    <div class="card-title">1. Shannon Prompt Entropy (Q_prompt)</div>
    <p style="font-size: 8pt; color: #475569;">
      <code>H(X) = -&sum; p(x) log2 p(x)</code> [11]. Measures lexical diversity and syntax token density in prompts [13], [17]. Penalizes vague queries like "fix my code". Baseline weight: w1 = 0.30.
    </p>
  </div>
  <div class="card">
    <div class="card-title">2. Context Completeness (S_CIOE)</div>
    <p style="font-size: 8pt; color: #475569;">
      Evaluates 4 structural dimensions: Context, Input specs, Expected Output, and Error Trace [12]. Rewards rigorous problem framing. Baseline weight: w2 = 0.25.
    </p>
  </div>
  <div class="card">
    <div class="card-title">3. Conceptual Ratio (R_concept)</div>
    <p style="font-size: 8pt; color: #475569;">
      Ratio of conceptual inquiries (Bloom C2-C4) versus direct code generation requests (Bloom C1). Differentiates learning from outsourcing. Baseline weight: w3 = 0.20.
    </p>
  </div>
  <div class="card">
    <div class="card-title">4. Friction Adherence (F_reflect)</div>
    <p style="font-size: 8pt; color: #475569;">
      Tracks adherence to the 60s reflection pause [9] with keystroke cadence verification to detect external copy-pasting. Baseline weight: w4 = 0.25.
    </p>
  </div>
</div>

<!-- SECTION 4 -->
<h2 class="sec-title">4. Relational Database Telemetry Schema</h2>
<p>
  The data collection pipeline operates passively within the MySQL database of S-SPARC and E-STRANGE:
</p>
<table class="doc-table">
  <thead>
    <tr>
      <th style="width: 25%;">Database Variable</th>
      <th style="width: 45%;">Data Definition & Measurement Granularity</th>
      <th style="width: 30%;">Construct Mapping</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><code>prompt_raw_text</code></td>
      <td>UTF-8 query string with millisecond timestamp</td>
      <td>Query formulation quality</td>
    </tr>
    <tr>
      <td><code>entropy_score</code></td>
      <td>Shannon Information Entropy H(X) calculated over token stream</td>
      <td>Lexical richness ($Q_{\text{prompt}}$)</td>
    </tr>
    <tr>
      <td><code>cioe_bitmask</code></td>
      <td>4-bit vector for Context, Input, Output, and Error stack trace</td>
      <td>Problem completeness ($S_{\text{CIOE}}$)</td>
    </tr>
    <tr>
      <td><code>keystroke_dwell</code></td>
      <td>Inter-keystroke timing variance and anti-paste dwell metrics</td>
      <td>Friction integrity ($F_{\text{reflect}}$)</td>
    </tr>
    <tr>
      <td><code>execution_metrics</code></td>
      <td>Unit test pass rate, runtime errors, and compilation attempts</td>
      <td>Objective mastery</td>
    </tr>
    <tr>
      <td><code>carbon_footprint</code></td>
      <td>Estimated inference token energy consumption and CO2 (g)</td>
      <td>Compute sustainability</td>
    </tr>
  </tbody>
</table>

<!-- SECTION 5 -->
<h2 class="sec-title">5. Four Secondary Exploratory Hypotheses (Bonferroni Corrected &alpha; = 0.0125)</h2>
<div class="grid-2">
  <div class="card">
    <div class="card-title">H1: Green AI Volume Paradox</div>
    <p style="font-size: 8.2pt; color: #334155;">
      Over-reliant students generate higher cumulative carbon emissions due to excessive prompt iteration volumes rather than per-query token complexity (controlled for Module Fixed-Effects and semantic cache hits).
    </p>
  </div>
  <div class="card">
    <div class="card-title">H2: AST Structural Distance Delta</div>
    <p style="font-size: 8.2pt; color: #334155;">
      Autonomous students exhibit significantly larger Abstract Syntax Tree (AST) structural distances between AI suggestions and final submitted code (evaluated against the final session AI suggestion).
    </p>
  </div>
  <div class="card">
    <div class="card-title">H3: Reflexive Pause vs First-Attempt Pass Rate</div>
    <p style="font-size: 8.2pt; color: #334155;">
      Adherence to reflection pauses positively predicts first-attempt pass rates on programming tasks (controlling for baseline prior ability as a covariate; claims remain strictly associative).
    </p>
  </div>
  <div class="card">
    <div class="card-title">H4: Semantic Cache Novelty & Lifecycle</div>
    <p style="font-size: 8.2pt; color: #334155;">
      Semantic cache misses reflect deeper student exploratory inquiry into complex edge cases (controlled for cache maturity lifecycle to eliminate cold-start artifacts).
    </p>
  </div>
</div>

<!-- SECTION 6 -->
<h2 class="sec-title">6. Methodological Defenses & Threats to Validity</h2>
<ul>
  <li><strong>Reactivity & Maturation Confound Mitigation:</strong> The study employs an A-B design (Modules 1-2 baseline unconstrained vs Modules 3-4 active 60s friction). For a single-cohort setting, the maturation effect of time is formally disclosed as a limitation; if 2 parallel sections are available, counterbalancing (A-B vs B-A) is implemented.</li>
  <li><strong>Two-Phase Psychometric Roadmap:</strong>
    <ul>
      <li><strong>Phase 1 (Semester 1 / Cohort 1, N=35-50):</strong> Instrument development and Exploratory Factor Analysis (EFA) to discover latent factor structure and calibrate initial weights.</li>
      <li><strong>Phase 2 (Semester 2 / Cohort 2, N &ge; 80):</strong> Independent Confirmatory Factor Analysis (CFA), construct validation against the standardized Hou et al. (2025 [1]) scale, and testing interaction models without factor overfitting.</li>
    </ul>
  </li>
  <li><strong>Entropy Inversion Paradox (Interaction Modeling):</strong> Advanced students author brief, highly specific prompts with low raw entropy. We model an interaction term (<code>Entropy &times; Baseline Prior Ability</code>) so expert conciseness is not misdiagnosed as novice over-reliance.</li>
  <li><strong>NLP Classifier Validation Sub-Study:</strong> Intent classification for <code>R_concept</code> is validated on &ge; 100 sample prompts with double annotation by 2 independent raters, requiring Cohen's Kappa &kappa; &ge; 0.80.</li>
  <li><strong>Ethical Masking & Pygmalion Effect:</strong> Individual student ORI scores and personas are blind-masked from instructors during Phase 1 to prevent subjective grading bias. Live lecturer dashboards unlock in Phase 2 post-validation.</li>
  <li><strong>Anti-Circularity & P-Hacking:</strong> The analysis plan is pre-registered on OSF.io. Regresi bobot diuji dengan 5-Fold Cross-Validation, and VIF is checked (VIF &lt; 5.0).</li>
</ul>

<!-- SECTION 7 -->
<h2 class="sec-title">7. Implementation Timeline & Two-Phase Milestones</h2>
<table class="doc-table">
  <thead>
    <tr>
      <th style="width: 20%;">Period</th>
      <th style="width: 45%;">Research Activities</th>
      <th style="width: 35%;">Key Deliverables</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>Weeks 1 - 2</strong></td>
      <td>OSF Pre-Registration, Ethics Approval, Baseline Phase (Modules 1-2)</td>
      <td>Locked OSF protocol, baseline interaction logs</td>
    </tr>
    <tr>
      <td><strong>Weeks 3 - 4</strong></td>
      <td>Intervention Phase (Modules 3-4 with active 60s productive friction)</td>
      <td>Complete pre/post telemetry dataset</td>
    </tr>
    <tr>
      <td><strong>Weeks 5 - 6</strong></td>
      <td>MySQL ETL extraction, NLP double annotation (&kappa; &ge; 0.80), EFA factor structure</td>
      <td>Psychometric factor report</td>
    </tr>
    <tr>
      <td><strong>Weeks 7 - 8</strong></td>
      <td>Manuscript drafting and submission to IEEE International Conference</td>
      <td>Submitted paper (IEEE TALE / EDUCON)</td>
    </tr>
    <tr>
      <td><strong>Semester 2</strong></td>
      <td>Phase 2 independent replication (CFA, Hou et al. validation, Scopus Q1 submission)</td>
      <td>Scopus Q1 Journal (IEEE TLT / C&E: AI)</td>
    </tr>
  </tbody>
</table>

<!-- SECTION 8 -->
<h2 class="sec-title">8. Scope Boundary & Advisor Collaboration</h2>
<div class="grid-2">
  <div class="card">
    <div class="card-title">Methodological Scope Boundary</div>
    <p style="font-size: 8pt; color: #475569;">
      The ORI metric is specifically formulated for programming assignments evaluable via deterministic unit testing (Computer Science). Generalization to non-computing fields requires qualitative rubrics and is defined as future work.
    </p>
  </div>
  <div class="card">
    <div class="card-title">Advisor Action Items</div>
    <p style="font-size: 8pt; color: #475569;">
      1. Approval to deploy S-SPARC in 1 active lab class (35-50 students).<br>
      2. Ethics review endorsement and OSF co-sponsorship.<br>
      3. Collaborative statistical review and co-authorship through publication.
    </p>
  </div>
</div>

<!-- REFERENCES -->
<h2 class="sec-title">9. Academic References (IEEE Citation Standard)</h2>
<div class="references">
  <p>[1] Y. Hou, X. Zhu, P. Sudarshan, C. P. Lim, and Y. S. Ong, "Measuring undergraduate students' reliance on Generative AI during problem-solving: Scale development and validation," <em>Comput. Educ.</em>, vol. 234, Art. no. 105329, 2025.</p>
  <p>[2] C. Zhai, S. Wibowo, and L. D. Li, "The effects of over-reliance on AI dialogue systems on students' cognitive abilities: A systematic review," <em>Smart Learn. Environ.</em>, vol. 11, no. 1, Art. no. 28, 2024.</p>
  <p>[3] S. Li, J. Liu, and Q. Dong, "Generative artificial intelligence-supported programming education: Effects on learning performance, self-efficacy and processes," <em>Australas. J. Educ. Technol.</em>, vol. 41, no. 1, pp. 45-62, 2025.</p>
  <p>[4] G. Jo&scaron;t, V. Taneski, and S. Karakati&#269;, "The impact of large language models on programming education and student learning outcomes," <em>Appl. Sci.</em>, vol. 14, no. 10, Art. no. 4115, 2024.</p>
  <p>[5] S. L&oacute;pez-Pernas, K. Misiejuk, E. Oliveira, and M. Saqr, "The dynamics of the self-regulation process in student-AI interactions: The case of problem-solving in programming education," in <em>Proc. 25th Koli Calling Int. Conf. Comput. Educ. Res.</em>, 2025, pp. 1-12.</p>
  <p>[6] D. Kohen-Vacs, M. Usher, and M. Jansen, "Integrating generative AI into programming education: Student perceptions and the challenge of correcting AI errors," <em>Int. J. Artif. Intell. Educ.</em>, vol. 35, no. 4, pp. 3166-3184, 2025.</p>
  <p>[7] J. Prather et al., "The robots are here: Navigating the generative AI revolution in computing education," in <em>Proc. 2023 Work. Group Rep. Innov. Technol. Comput. Sci. Educ.</em>, 2023, pp. 108-159.</p>
  <p>[8] A. Kharrufa, S. Alghamdi, A. Aziz, and C. Bull, "LLMs integration in software engineering team projects: Roles, impact, and a pedagogical design space," <em>ACM Trans. Comput. Educ.</em>, vol. 26, no. 1, pp. 1-27, 2024.</p>
  <p>[9] P. Denny et al., "Prompt Problems: A new programming exercise for the generative AI era," in <em>Proc. 55th ACM Tech. Symp. Comput. Sci. Educ.</em>, 2024, pp. 296-302.</p>
  <p>[10] C. Vieira, J. L. De La Hoz, A. J. Magana, and D. Restrepo, "Engineering students' experiences with ChatGPT to generate code," <em>Comput. Appl. Eng. Educ.</em>, vol. 33, no. 1, Art. no. e70090, 2025.</p>
  <p>[11] C. E. Shannon, "A mathematical theory of communication," <em>Bell Syst. Tech. J.</em>, vol. 27, no. 3, pp. 379-423, 1948.</p>
  <p>[12] P. Yang et al., "Large language models for software testing education: An experience report," in <em>Proc. 34th ACM Int. Conf. Found. Softw. Eng.</em>, 2026, pp. 1-12.</p>
  <p>[13] X. Gong, W. Xu, and A.-L. Qiao, "Exploring undergraduates' computational thinking in progressive prompt-assisted programming learning," <em>Int. J. Educ. Technol. High. Educ.</em>, vol. 22, no. 1, Art. no. 14, 2025.</p>
  <p>[14] G. Pitts, N. Rani, W. Mildort, and E.-M. Cook, "Students' reliance on AI in higher education: Identifying contributing factors," <em>arXiv preprint</em> arXiv:2506.13845, 2025.</p>
  <p>[15] J. Zheng et al., "Do students rely on AI? Analysis of student-ChatGPT conversations from a field study," in <em>Proc. 8th AAAI/ACM Conf. AI, Ethics, and Society (AIES)</em>, 2025.</p>
  <p>[16] G. Salib and Z. Sharafi, "AI or ally? Understanding student reliance on GitHub Copilot and human peers through eye tracking," <em>IEEE Trans. Softw. Eng.</em>, pp. 1-13, 2026, doi: 10.1109/TSE.2026.3705249.</p>
  <p>[17] T.-Y. Yang et al., "Leveraging LLMs for automated extraction and structuring of educational concepts," <em>Mach. Learn. Knowl. Extr.</em>, vol. 7, no. 3, p. 103, 2025.</p>
</div>

</body>
</html>
"""

html_path = r"c:\final_estrange\s-sparc\docs\full_proposal_export.html"
pdf_path = r"c:\final_estrange\s-sparc\docs\S_SPARC_AI_Over_Reliance_Comprehensive_Proposal.pdf"

with open(html_path, "w", encoding="utf-8") as f:
    f.write(html_content)

chrome_exe = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
cmd = [
    chrome_exe,
    "--headless=new",
    "--disable-gpu",
    "--no-pdf-header-footer",
    f"--print-to-pdf={pdf_path}",
    f"file:///{html_path.replace(os.sep, '/')}"
]

res = subprocess.run(cmd, capture_output=True, text=True)
print("Returncode:", res.returncode)
print("Stdout:", res.stdout)
print("Stderr:", res.stderr)

if os.path.exists(pdf_path):
    print("Full Comprehensive Proposal PDF generated successfully! Size:", os.path.getsize(pdf_path), "bytes")
