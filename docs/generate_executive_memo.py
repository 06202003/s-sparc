# -*- coding: utf-8 -*-
import subprocess
import os

html_content = r"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>S-SPARC AI: Quantifying AI Over-Reliance in Programming Education</title>
<style>
  @page {
    size: A4 portrait;
    margin: 20mm 20mm 22mm 20mm;
    @bottom-right {
      content: counter(page);
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      font-size: 9pt;
      color: #94a3b8;
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
    font-size: 10pt;
    line-height: 1.6;
  }

  /* Header banner */
  .memo-header {
    border-bottom: 1.5px solid #e2e8f0;
    padding-bottom: 12px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 8.5pt;
    color: #64748b;
    font-weight: 600;
  }
  .memo-tag {
    background-color: #f1f5f9;
    color: #0f172a;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 8pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  /* Title Block */
  .title-block {
    margin-bottom: 22px;
  }
  h1.memo-title {
    font-size: 19pt;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
    margin: 0 0 6px 0;
    letter-spacing: -0.02em;
  }
  .memo-subtitle {
    font-size: 11pt;
    color: #475569;
    line-height: 1.45;
    margin: 0 0 14px 0;
  }

  /* Info Meta Box */
  .meta-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 14px;
    margin-bottom: 20px;
  }
  .meta-col .label {
    font-size: 7.5pt;
    font-weight: 700;
    text-transform: uppercase;
    color: #94a3b8;
    letter-spacing: 0.05em;
    margin-bottom: 2px;
  }
  .meta-col .val {
    font-size: 9pt;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.35;
  }

  /* Overview Box */
  .overview-box {
    background-color: #f8fafc;
    border-left: 3.5px solid #2563eb;
    border-radius: 0 6px 6px 0;
    padding: 12px 16px;
    margin-bottom: 22px;
  }
  .overview-title {
    font-size: 9pt;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #2563eb;
    margin-bottom: 4px;
  }
  .overview-text {
    font-size: 9.5pt;
    color: #334155;
    line-height: 1.5;
    margin: 0;
  }

  /* Headings */
  h2.sec-title {
    font-size: 12pt;
    font-weight: 800;
    color: #0f172a;
    margin: 22px 0 8px 0;
    padding-bottom: 4px;
    border-bottom: 1px solid #e2e8f0;
    letter-spacing: -0.01em;
    page-break-after: avoid;
  }
  h3.sub-title {
    font-size: 10pt;
    font-weight: 700;
    color: #1e293b;
    margin: 12px 0 4px 0;
    page-break-after: avoid;
  }

  /* Clean Tables */
  table.clean-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8.8pt;
    margin: 10px 0 14px 0;
    page-break-inside: avoid;
  }
  table.clean-table th {
    background-color: #f8fafc;
    color: #0f172a;
    font-weight: 700;
    text-align: left;
    padding: 7px 10px;
    border-top: 1px solid #cbd5e1;
    border-bottom: 1px solid #cbd5e1;
  }
  table.clean-table td {
    padding: 6px 10px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
    color: #334155;
  }
  table.clean-table tr:last-child td {
    border-bottom: 1px solid #cbd5e1;
  }

  /* Math equation box */
  .formula-box {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 10px 14px;
    text-align: center;
    margin: 10px 0 14px 0;
    page-break-inside: avoid;
  }
  .formula-text {
    font-family: "Courier New", Courier, monospace;
    font-size: 11pt;
    font-weight: 700;
    color: #0f172a;
  }
  .formula-sub {
    font-size: 8.5pt;
    color: #64748b;
    margin-top: 4px;
  }

  /* Callout notes */
  .callout {
    background-color: #f8fafc;
    border-left: 3px solid #64748b;
    padding: 8px 12px;
    font-size: 9pt;
    color: #475569;
    border-radius: 0 4px 4px 0;
    margin: 8px 0;
  }

  /* Grid 2-col */
  .grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin: 8px 0;
    page-break-inside: avoid;
  }
  .box {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 10px 12px;
  }
  .box-title {
    font-size: 9pt;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 4px;
  }

  p {
    margin: 0 0 8px 0;
  }
  ul, ol {
    margin: 0 0 8px 0;
    padding-left: 18px;
  }
  li {
    margin-bottom: 3px;
  }
  code {
    font-family: "Courier New", Courier, monospace;
    font-size: 8.5pt;
    background-color: #f1f5f9;
    color: #0f172a;
    padding: 1px 4px;
    border-radius: 3px;
  }

  /* References */
  .references {
    font-size: 8pt;
    color: #475569;
    line-height: 1.4;
    margin-top: 14px;
  }
  .references p {
    margin-bottom: 4px;
    text-indent: -18px;
    padding-left: 18px;
  }
</style>
</head>
<body>

<!-- HEADER -->
<div class="memo-header">
  <span>Faculty of Information Technology &bull; Universitas Kristen Maranatha</span>
  <span class="memo-tag">Research Executive Brief</span>
</div>

<!-- TITLE & META -->
<div class="title-block">
  <h1 class="memo-title">Measuring & Mitigating AI Over-Reliance in Programming Education</h1>
  <div class="memo-subtitle">A Telemetry-Driven Behavioral Metric with Productive Friction on S-SPARC & E-STRANGE</div>

  <div class="meta-grid">
    <div class="meta-col">
      <div class="label">Research Team</div>
      <div class="val">S-SPARC Project Team</div>
    </div>
    <div class="meta-col">
      <div class="label">Target Field</div>
      <div class="val">AIED & Learning Analytics</div>
    </div>
    <div class="meta-col">
      <div class="label">Platform</div>
      <div class="val">S-SPARC & E-STRANGE LMS</div>
    </div>
    <div class="meta-col">
      <div class="label">Publication Target</div>
      <div class="val">IEEE TALE / Scopus Q1</div>
    </div>
  </div>
</div>

<!-- EXECUTIVE OVERVIEW -->
<div class="overview-box">
  <div class="overview-title">Executive Summary</div>
  <p class="overview-text">
    Generative AI can generate working code in seconds, but unchecked student access often leads to cognitive offloading and surface-level learning. This research project introduces the <strong>AI Over-Reliance Index (ORI)</strong>: an automated, real-time behavioral metric built into the S-SPARC AI engine and E-STRANGE auto-grader. By logging unobtrusive interaction telemetry and enforcing structured productive friction (60-second reflection pauses), this study provides higher education instructors with an empirical framework to diagnose and prevent cognitive atrophy without banning AI tools.
  </p>
</div>

<!-- SECTION 1 -->
<h2 class="sec-title">1. The Problem: Cognitive Offloading in Programming Labs</h2>
<p>
  In programming classes, students frequently fall into a passive interaction loop: copying problem prompts into AI, copying generated code, and submitting it directly to automated graders. While lab assignment scores look high, students struggle during unassisted exams when AI is not available [2], [3].
</p>
<ul>
  <li><strong>Critical Thinking Decay:</strong> Literature shows excessive reliance on conversational AI degrades independent problem-solving and self-debugging skills [2].</li>
  <li><strong>Weak Conceptual Transfer:</strong> Instant AI assistance shows minimal correlation with genuine conceptual mastery [3], [14].</li>
  <li><strong>Impact on Course Outcomes:</strong> High LLM dependency is negatively correlated with final exam scores [4].</li>
  <li><strong>Superficial Engagement:</strong> Studies of student-ChatGPT dialogues show students rarely reflect deeply on the code they receive [5].</li>
</ul>

<!-- SECTION 2 -->
<h2 class="sec-title">2. Why Existing Solutions Fall Short</h2>
<table class="clean-table">
  <thead>
    <tr>
      <th style="width: 25%;">Current Method</th>
      <th style="width: 38%;">Main Limitation</th>
      <th style="width: 37%;">S-SPARC Solution</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>Surveys / Questionnaires</strong> [2], [7]</td>
      <td>Subjective, prone to recall bias, cannot measure live behavior.</td>
      <td><strong>Continuous Telemetry:</strong> Automatic logging of typing speed, pauses, and prompt quality per millisecond.</td>
    </tr>
    <tr>
      <td><strong>AI Plagiarism Checkers</strong></td>
      <td>High false-positive rate, purely punitive, zero learning feedback.</td>
      <td><strong>Cognitive Profiling:</strong> Maps student problem-solving habits over time.</td>
    </tr>
    <tr>
      <td><strong>Lab Eye-Tracking</strong> [16]</td>
      <td>Rigorous but post-hoc and only works in specialized lab setups.</td>
      <td><strong>Real-Time Metric (ORI):</strong> Computes live reliance scores to adjust AI scaffolding automatically.</td>
    </tr>
    <tr>
      <td><strong>Unregulated AI Tools</strong> [5]</td>
      <td>Commercial chatbots encourage rapid prompt spamming without thought.</td>
      <td><strong>Productive Friction:</strong> Mandatory 60s pause and stepped Socratic hints [9], [10].</td>
    </tr>
  </tbody>
</table>

<!-- SECTION 3 -->
<h2 class="sec-title">3. The Proposed Metric: AI Over-Reliance Index (ORI)</h2>
<p>
  The ORI combines four unobtrusive telemetry indicators into a normalized composite score from 0.0 (independent problem solver) to 1.0 (heavily over-reliant):
</p>

<div class="formula-box">
  <div class="formula-text">ORI = 1 - (w1 &middot; Q_prompt + w2 &middot; S_CIOE + w3 &middot; R_concept + w4 &middot; F_reflect)</div>
  <div class="formula-sub">All components normalized between 0 and 1 &bull; Baseline weights: w1=0.30, w2=0.25, w3=0.20, w4=0.25</div>
</div>

<div class="grid-2">
  <div class="box">
    <div class="box-title">1. Shannon Prompt Entropy (Q_prompt)</div>
    <p style="font-size: 8.8pt; color: #475569;">
      Uses Shannon Information Entropy <code>H(X) = -&sum; p(x) log2 p(x)</code> [11] to measure technical vocabulary richness and specificity in student queries [13], [17]. Penalizes vague prompts like "why error".
    </p>
  </div>
  <div class="box">
    <div class="box-title">2. Context Completeness (S_CIOE)</div>
    <p style="font-size: 8.8pt; color: #475569;">
      Checks compliance across four structured dimensions: Context, Input specs, Expected Output, and Error Trace [12]. Rewards well-formulated problem descriptions.
    </p>
  </div>
  <div class="box">
    <div class="box-title">3. Conceptual Ratio (R_concept)</div>
    <p style="font-size: 8.8pt; color: #475569;">
      Measures the proportion of queries asking for explanations/logic (Bloom C2-C4) versus requests for instant code generation (Bloom C1).
    </p>
  </div>
  <div class="box">
    <div class="box-title">4. Reflection Adherence (F_reflect)</div>
    <p style="font-size: 8.8pt; color: #475569;">
      Tracks adherence to the 60-second reflection pause [9] combined with keystroke cadence analysis to detect and penalize instant copy-pasting from external AI tools.
    </p>
  </div>
</div>

<!-- SECTION 4 -->
<h2 class="sec-title">4. Methodological Defenses & Research Rigor</h2>
<p>
  To ensure the study meets top-tier peer-review standards (IEEE / Scopus Q1), the research design explicitly addresses key threats to validity:
</p>
<ul>
  <li><strong>Reactivity & Maturation Confound:</strong> The study uses an A-B design (Modules 1-2 baseline without friction, Modules 3-4 with friction). If only 1 cohort is available, the maturation effect of time is transparently acknowledged as a limitation in the paper; if 2 sections exist, counterbalancing (A-B vs B-A) is applied.</li>
  <li><strong>Two-Phase Psychometric Roadmap:</strong> To prevent sample size issues and factor overfitting, the research is split into two phases:
    <ul>
      <li><strong>Phase 1 (Semester 1 / Cohort 1, N=35-50):</strong> Instrument development and Exploratory Factor Analysis (EFA) to discover factor structure and calibrate initial weights.</li>
      <li><strong>Phase 2 (Semester 2 / Cohort 2, N &ge; 80):</strong> Independent Confirmatory Factor Analysis (CFA), construct validation against the standardized Hou et al. (2025 [1]) scale, and testing interaction models.</li>
    </ul>
  </li>
  <li><strong>Entropy Inversion Paradox:</strong> Advanced students write brief, concise prompts because they know exactly what they need. We model an interaction term (<code>Entropy &times; Prior Ability</code>) so expert conciseness is not misclassified as over-reliance.</li>
  <li><strong>NLP Classifier Validation:</strong> Intent classification for <code>R_concept</code> is validated on &ge; 100 sample prompts with double annotation by 2 independent raters, requiring Cohen's Kappa &kappa; &ge; 0.80.</li>
  <li><strong>Ethical Masking (Anti-Pygmalion Bias):</strong> Individual student ORI scores and personas are blind-masked from course lecturers during Phase 1 to prevent subjective grading bias. Live lecturer dashboards unlock in Phase 2 post-validation.</li>
  <li><strong>Anti P-Hacking & Anti-Circularity:</strong> The analysis plan is pre-registered on OSF.io. Bobot regresi diuji menggunakan 5-Fold Cross-Validation, dan 4 hipotesis eksploratori dikoreksi dengan Bonferroni (&alpha; = 0.0125).</li>
</ul>

<!-- SECTION 5 -->
<h2 class="sec-title">5. Implementation Timeline (8 Weeks to Submission)</h2>
<table class="clean-table">
  <thead>
    <tr>
      <th style="width: 20%;">Timeline</th>
      <th style="width: 45%;">Activities</th>
      <th style="width: 35%;">Deliverables</th>
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
      <td>Intervention Phase (Modules 3-4 with active 60s friction)</td>
      <td>Complete telemetry dataset</td>
    </tr>
    <tr>
      <td><strong>Weeks 5 - 6</strong></td>
      <td>MySQL ETL extraction, NLP double annotation (&kappa; &ge; 0.80), EFA analysis</td>
      <td>Psychometric factor report</td>
    </tr>
    <tr>
      <td><strong>Weeks 7 - 8</strong></td>
      <td>Manuscript drafting and submission to IEEE Conference</td>
      <td>Submitted paper (IEEE TALE / EDUCON)</td>
    </tr>
    <tr>
      <td><strong>Next Semester</strong></td>
      <td>Phase 2 replication (CFA, Hou et al. validation, full journal paper)</td>
      <td>Scopus Q1 Journal (IEEE TLT / C&E: AI)</td>
    </tr>
  </tbody>
</table>

<!-- SECTION 6 -->
<h2 class="sec-title">6. Scope & Advisor Collaboration</h2>
<div class="grid-2">
  <div class="box">
    <div class="box-title">Scope Boundary</div>
    <p style="font-size: 8.8pt; color: #475569;">
      The ORI metric is designed specifically for programming assignments with deterministic unit testing (Computer Science). Generalization to non-computing fields is explicitly noted as future work.
    </p>
  </div>
  <div class="box">
    <div class="box-title">What We Need from Advisor</div>
    <p style="font-size: 8.8pt; color: #475569;">
      1. Approval to run S-SPARC in 1 active lab class (35-50 students).<br>
      2. Ethics review support & OSF co-sponsorship.<br>
      3. Joint mentorship and co-authorship on the resulting papers.
    </p>
  </div>
</div>

<!-- REFERENCES -->
<div class="references">
  <h3 class="sub-title" style="margin-top: 10px; font-size: 8.5pt;">Key References (IEEE Style)</h3>
  <p>[1] Y. Hou et al., "Measuring undergraduate students' reliance on Generative AI," <em>Comput. Educ.</em>, vol. 234, 2025.</p>
  <p>[2] C. Zhai et al., "Effects of over-reliance on AI dialogue systems on cognitive abilities," <em>Smart Learn. Environ.</em>, vol. 11, 2024.</p>
  <p>[3] S. Li et al., "Generative AI-supported programming education," <em>AJET</em>, vol. 41, 2025.</p>
  <p>[4] G. Jo&scaron;t et al., "Impact of LLMs on programming education," <em>Appl. Sci.</em>, vol. 14, 2024.</p>
  <p>[5] S. L&oacute;pez-Pernas et al., "Self-regulation in student-AI interactions," in <em>Proc. Koli Calling</em>, 2025.</p>
  <p>[6] D. Kohen-Vacs et al., "Integrating generative AI into programming education," <em>IJAIED</em>, vol. 35, 2025.</p>
  <p>[7] J. Prather et al., "The robots are here: Generative AI in computing education," in <em>ITiCSE</em>, 2023.</p>
  <p>[8] A. Kharrufa et al., "LLMs integration in software engineering projects," <em>ACM TOCE</em>, vol. 26, 2024.</p>
  <p>[9] P. Denny et al., "Prompt Problems: Programming in the AI era," in <em>Proc. ACM SIGCSE</em>, 2024.</p>
  <p>[10] C. Vieira et al., "Engineering students' experiences with ChatGPT," <em>CAEE</em>, vol. 33, 2025.</p>
  <p>[11] C. E. Shannon, "A mathematical theory of communication," <em>Bell Syst. Tech. J.</em>, 1948.</p>
  <p>[12] P. Yang et al., "LLMs for software testing education," in <em>Proc. ACM FSE</em>, 2026.</p>
  <p>[13] X. Gong et al., "Computational thinking in prompt-assisted programming," <em>IJETHE</em>, vol. 22, 2025.</p>
  <p>[14] G. Pitts et al., "Students' reliance on AI in higher education," <em>arXiv</em>, 2025.</p>
  <p>[15] J. Zheng et al., "Do students rely on AI? Field study," in <em>Proc. AAAI/ACM AIES</em>, 2025.</p>
  <p>[16] G. Salib & Z. Sharafi, "Student reliance on Copilot through eye tracking," <em>IEEE TSE</em>, 2026.</p>
  <p>[17] T.-Y. Yang et al., "Automated structuring of educational concepts," <em>MLKE</em>, vol. 7, 2025.</p>
</div>

</body>
</html>
"""

html_path = r"c:\final_estrange\s-sparc\docs\memo_export.html"
pdf_path = r"c:\final_estrange\s-sparc\docs\S_SPARC_Research_Proposal_Executive_Brief.pdf"

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
    print("Executive Memo PDF generated successfully! Size:", os.path.getsize(pdf_path), "bytes")
