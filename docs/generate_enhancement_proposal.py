# -*- coding: utf-8 -*-
import subprocess
import os

html_content = r"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Research Proposal: Measuring AI Over-Reliance in Programming Labs (BORI)</title>
<style>
  @page {
    size: A4 portrait;
    margin: 16mm 16mm 16mm 16mm;
    @bottom-right {
      content: counter(page);
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      font-size: 8.5pt;
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
    font-size: 9.2pt;
    line-height: 1.45;
  }

  /* Header */
  .doc-header {
    border-bottom: 1.5px solid #e2e8f0;
    padding-bottom: 4px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 8pt;
    color: #64748b;
    font-weight: 600;
  }

  /* Title Block */
  .title-block {
    margin-bottom: 10px;
  }
  h1.doc-title {
    font-size: 14pt;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.25;
    margin: 0 0 3px 0;
  }
  .doc-subtitle {
    font-size: 9.3pt;
    color: #475569;
    line-height: 1.35;
    margin: 0;
  }

  /* Executive Summary Box */
  .summary-box {
    background-color: #f0fdf4;
    border-left: 4px solid #16a34a;
    border-radius: 0 6px 6px 0;
    padding: 8px 12px;
    margin: 8px 0 12px 0;
  }
  .summary-title {
    font-size: 8pt;
    font-weight: 700;
    text-transform: uppercase;
    color: #15803d;
    margin-bottom: 2px;
  }
  .summary-text {
    font-size: 8.8pt;
    color: #166534;
    line-height: 1.4;
    margin: 0;
  }

  /* Headings */
  h2.sec-title {
    font-size: 10.5pt;
    font-weight: 700;
    color: #0f172a;
    margin: 12px 0 5px 0;
    padding-bottom: 2px;
    border-bottom: 1.5px solid #e2e8f0;
    page-break-after: avoid;
  }

  /* Grid & Cards */
  .grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin: 6px 0;
    page-break-inside: avoid;
  }
  .card {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 7px 10px;
  }
  .card-title {
    font-size: 8.6pt;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 2px;
  }
  .card-body {
    font-size: 8.2pt;
    color: #475569;
    line-height: 1.35;
    margin: 0;
  }

  /* Tables */
  table.doc-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8.3pt;
    margin: 6px 0 10px 0;
    page-break-inside: avoid;
  }
  table.doc-table th {
    background-color: #f1f5f9;
    color: #0f172a;
    font-weight: 700;
    text-align: left;
    padding: 5px 7px;
    border-top: 1.5px solid #cbd5e1;
    border-bottom: 1.5px solid #cbd5e1;
  }
  table.doc-table td {
    padding: 4.5px 7px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
    color: #334155;
    line-height: 1.32;
  }
  table.doc-table tr:last-child td {
    border-bottom: 1.5px solid #cbd5e1;
  }

  /* Math Box */
  .math-box {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 8px 12px;
    text-align: center;
    margin: 7px 0;
    page-break-inside: avoid;
  }
  .math-display {
    font-size: 10.5pt;
    margin: 2px 0;
  }
  .math-sub {
    font-size: 7.9pt;
    color: #64748b;
    margin-top: 3px;
    line-height: 1.3;
  }

  /* Text & Lists */
  p {
    margin: 0 0 5px 0;
  }
  ul, ol {
    margin: 0 0 5px 0;
    padding-left: 16px;
  }
  li {
    margin-bottom: 2px;
  }
  code {
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
    font-size: 7.8pt;
    background-color: #f1f5f9;
    color: #0f172a;
    padding: 1px 3px;
    border-radius: 3px;
  }

  /* References */
  .references {
    font-size: 7.5pt;
    color: #475569;
    line-height: 1.32;
    margin-top: 6px;
  }
  .references p {
    margin-bottom: 3px;
    text-indent: -12px;
    padding-left: 12px;
  }
  
  math {
    font-family: "Cambria Math", "Latin Modern Math", "STIX Two Math", serif;
  }
</style>
</head>
<body>

<!-- HEADER -->
<div class="doc-header">
  <span>S-SPARC Telemetry &amp; Learning Analytics Study</span>
  <span>Research Proposal &bull; Faculty of Smart Technology &amp; Engineering (FTRC)</span>
</div>

<!-- TITLE BLOCK -->
<div class="title-block">
  <h1 class="doc-title">Measuring Student Over-Reliance on AI in Programming Labs: A Simple Behavioral Index (BORI) in E-STRANGE</h1>
  <div class="doc-subtitle">Research Proposal: Core Concept, Automated Telemetry Pipeline, and Statistical Validation</div>
</div>

<!-- EXECUTIVE SUMMARY -->
<div class="summary-box">
  <div class="summary-title">Summary in Brief</div>
  <p class="summary-text">
    This study measures how beginner students use AI assistants in lab assignments: do they ask for explanations to learn (constructive), or do they blindly copy-paste full code (over-reliance)? We calculate an automated <strong>Behavioral Over-Reliance Index (BORI)</strong> from existing server logs without disturbing students. We then test whether over-reliance harms closed-book exam scores and whether a 1-sentence reflection prompt helps students think more independently.
  </p>
</div>

<!-- SECTION 1 -->
<h2 class="sec-title">1. Why This Study Matters</h2>
<p>
  Generative AI tools help students solve programming tasks faster, but how students ask for help matters greatly [4], [9]:
</p>
<ul>
  <li><strong>Healthy Help-Seeking (Instrumental):</strong> Asking AI for conceptual hints, debugging guidance, or logic explanations. The student remains in charge of writing the code [1], [7].</li>
  <li><strong>Over-Reliance (Executive):</strong> Asking AI for complete solutions and copying them directly into the editor without thinking [1], [2].</li>
</ul>
<p>
  Prior studies suggest students who lean heavily on blind copy-pasting may struggle when taking unassisted, closed-book exams [2], [3]. Standard chat interfaces lack reflection friction, which can encourage fast, unread pasting [1], [10].
</p>

<!-- SECTION 2 -->
<h2 class="sec-title">2. The BORI Formula (Simple &amp; Intuitive)</h2>
<p>
  BORI is a single number from <strong>0.0 (fully independent / healthy)</strong> to <strong>1.0 (total uncritical over-reliance)</strong>. It is simply the equal-weighted average of 4 observable behaviors [6], [8]:
</p>

<div class="math-box">
  <div class="math-display">
    <math display="block">
      <msub><mi>BORI</mi><mi>prompt</mi></msub>
      <mo>=</mo>
      <mfrac>
        <mrow>
          <msub><mi>E</mi><mi>direct</mi></msub>
          <mo>+</mo>
          <msub><mi>S</mi><mi>copy</mi></msub>
          <mo>+</mo>
          <msub><mi>P</mi><mi>paste</mi></msub>
          <mo>+</mo>
          <msub><mi>F</mi><mi>fast</mi></msub>
        </mrow>
        <mn>4</mn>
      </mfrac>
    </math>
  </div>
  <div class="math-sub">
    Each component is scored from 0.0 to 1.0 &bull; If AI returns text/hints only (no code), BORI is the average of <math><msub><mi>E</mi><mi>direct</mi></msub></math> and <math><msub><mi>S</mi><mi>copy</mi></msub></math> (50% each) &bull; Zero AI use is logged as missing.
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-title">1. Direct Code Request (<math><msub><mi>E</mi><mi>direct</mi></msub><mo>&isin;</mo><mo>{</mo><mn>0</mn><mo>,</mo><mn>1</mn><mo>}</mo></math>)</div>
    <div class="card-body">
      Did the student ask for complete solution code (1) or ask for a concept/hint (0)? Classified locally via a Llama-3.1-8B model with regex rules (validated on 200 pilot prompts, <math><msub><mi>&kappa;</mi><mi>human</mi></msub><mo>&ge;</mo><mn>0.85</mn></math>).
    </div>
  </div>
  <div class="card">
    <div class="card-title">2. Problem Text Copying (<math><msub><mi>S</mi><mi>copy</mi></msub><mo>&isin;</mo><mo>[</mo><mn>0</mn><mo>,</mo><mn>1</mn><mo>]</mo></math>)</div>
    <div class="card-body">
      How much of the prompt is verbatim copied from the problem description? Measured by 4-gram overlap (0 = student phrased own words, 1 = exact copy-paste of assignment).
    </div>
  </div>
  <div class="card">
    <div class="card-title">3. Unedited Code Pasting (<math><msub><mi>P</mi><mi>paste</mi></msub><mo>&isin;</mo><mo>[</mo><mn>0</mn><mo>,</mo><mn>1</mn><mo>]</mo></math>)</div>
    <div class="card-body">
      Did the student paste the AI's code snippet directly into their editor? Measured via normalized edit distance (1 = exact paste without any edits, 0 = heavily modified).
    </div>
  </div>
  <div class="card">
    <div class="card-title">4. Fast Paste Without Reading (<math><msub><mi>F</mi><mi>fast</mi></msub><mo>&isin;</mo><mo>[</mo><mn>0</mn><mo>,</mo><mn>1</mn><mo>]</mo></math>)</div>
    <div class="card-body">
      Did the student paste within seconds without reading? <math><msub><mi>F</mi><mi>fast</mi></msub><mo>=</mo><mn>1</mn><mo>-</mo><mo>min</mo><mo>(</mo><mn>1.0</mn><mo>,</mo><msub><mi>T</mi><mi>active</mi></msub><mo>/</mo><msub><mi>T</mi><mi>expected</mi></msub><mo>)</mo></math>, where active time subtracts tab-blur/defocus time and expected reading speed is 5 tokens/s.
    </div>
  </div>
</div>

<!-- SECTION 3 -->
<h2 class="sec-title">3. How Data is Collected (Zero Extra Work for Students)</h2>
<p>
  All data is pulled automatically from existing E-STRANGE server logs and course records:
</p>

<table class="doc-table">
  <thead>
    <tr>
      <th style="width: 22%;">Data Source</th>
      <th style="width: 40%;">What is Recorded</th>
      <th style="width: 38%;">Purpose in Study</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>1. Chat Logs</strong></td>
      <td>Prompt text, AI response, response token count, timestamp.</td>
      <td>Calculates Direct Code Request (<math><msub><mi>E</mi><mi>direct</mi></msub></math>) and Problem Copying (<math><msub><mi>S</mi><mi>copy</mi></msub></math>).</td>
    </tr>
    <tr>
      <td><strong>2. Editor &amp; Tab Logs</strong></td>
      <td>Submitted code, paste timestamp, tab-defocus (blur) duration.</td>
      <td>Calculates Unedited Pasting (<math><msub><mi>P</mi><mi>paste</mi></msub></math>) and Reading Time (<math><msub><mi>F</mi><mi>fast</mi></msub></math>).</td>
    </tr>
    <tr>
      <td><strong>3. Course Grades &amp; Surveys</strong></td>
      <td>Pre-test ability (<math><msub><mi>Z</mi><mi>c</mi></msub></math>), Hou et al. [5] survey (W3/W6), and exam score (<math><msub><mi>Y</mi><mi>exam</mi></msub></math>).</td>
      <td>Validates BORI against survey and evaluates unassisted exam impact.</td>
    </tr>
  </tbody>
</table>

<!-- SECTION 4 -->
<h2 class="sec-title">4. Research Questions &amp; Statistical Testing Plan</h2>
<p>
  We answer 3 practical questions using standard statistical tests. <strong>RQ3 is our primary confirmatory test</strong>, while RQ1 and RQ2 are exploratory pilot analyses:
</p>

<table class="doc-table">
  <thead>
    <tr>
      <th style="width: 28%;">Research Question</th>
      <th style="width: 36%;">Statistical Test &amp; Model</th>
      <th style="width: 36%;">What We Expect to Learn</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>RQ1: Exam Impact</strong><br>Does high over-reliance predict lower exam scores?</td>
      <td><strong>Hierarchical Regression:</strong><br><math><msub><mi>Y</mi><mi>exam</mi></msub><mo>=</mo><msub><mi>&beta;</mi><mn>0</mn></msub><mo>+</mo><msub><mi>&beta;</mi><mn>1</mn></msub><msub><mi>Z</mi><mi>pretest</mi></msub><mo>+</mo><msub><mi>&beta;</mi><mn>2</mn></msub><mi>BORI</mi></math></td>
      <td>Checks if BORI explains exam performance after controlling for prior student ability (<math><msub><mi>&beta;</mi><mn>2</mn></msub><mo>&lt;</mo><mn>0</mn></math>).</td>
    </tr>
    <tr>
      <td><strong>RQ2: Reflection Gate</strong><br>Does a 1-sentence prompt reduce over-reliance?</td>
      <td><strong>Linear Mixed Model (LMM):</strong><br>Compares BORI before vs after reflection gate.</td>
      <td>Tests if requiring students to write a bug hypothesis before seeing AI code reduces BORI (<math><msub><mi>&beta;</mi><mi>phase</mi></msub><mo>&lt;</mo><mn>0</mn></math>).</td>
    </tr>
    <tr>
      <td><strong>RQ3: Survey Alignment</strong><br><em>(Primary Confirmatory Test)</em></td>
      <td><strong>Pearson / Spearman Correlation:</strong><br>BORI vs Hou et al. (2025) [5] Reliance Scale.</td>
      <td>Confirms that server-measured BORI matches students' self-reported reliance (<math><mi>r</mi><mo>&gt;</mo><mn>0</mn><mo>,</mo><mi>p</mi><mo>&lt;</mo><mn>0.05</mn></math>).</td>
    </tr>
  </tbody>
</table>

<!-- SECTION 5 -->
<h2 class="sec-title">5. Study Setup, Sample Size &amp; Privacy</h2>
<ul>
  <li><strong>Setting &amp; Sample:</strong> 1 introductory lab section (<math><mi>N</mi><mo>&approx;</mo><mn>45</mn></math> enrolled; ~38 active after accounting for absence and zero-use). For a moderate-to-large correlation (<math><mi>r</mi><mo>=</mo><mn>0.40</mn></math>), statistical power is 70.8% at <math><mi>N</mi><mo>=</mo><38></math> (reaching 80% if expanded to 2 sections, <math><mi>N</mi><mo>&ge;</mo><mn>70</mn></math>).</li>
  <li><strong>Reflection Gate Intervention:</strong> In Modules 3&ndash;4, before AI responses appear, students must write 1 brief sentence stating their bug hypothesis or coding plan. Low-effort text (e.g. repeated keystrokes) is tracked.</li>
  <li><strong>Privacy &amp; Ethics:</strong> Prompt classification runs 100% locally on the lab server (no cloud API). Student survey participation is managed by an independent research assistant and masked from the instructor until final grades are submitted.</li>
</ul>

<!-- SECTION 6 -->
<h2 class="sec-title">6. Lab Schedule &amp; Collaboration</h2>
<div class="grid-2">
  <div class="card">
    <div class="card-title">Lab Schedule (6 Weeks)</div>
    <div class="card-body">
      <strong>Week 1&ndash;2 (Baseline):</strong> Modules 1&ndash;2 with standard AI + Mid-Survey.<br>
      <strong>Week 3&ndash;4 (Intervention):</strong> Modules 3&ndash;4 with Reflection Gate + Post-Survey.<br>
      <strong>Week 5:</strong> Unassisted closed-book lab exam.<br>
      <strong>Week 6:</strong> Automated data extraction and statistical testing.
    </div>
  </div>
  <div class="card">
    <div class="card-title">Faculty Collaboration (FTRC)</div>
    <div class="card-body">
      1. Deploy study in 1 lab section (2 sections if feasible).<br>
      2. No changes required to existing teaching materials.<br>
      3. Joint co-authorship on resulting research papers.
    </div>
  </div>
</div>

<!-- REFERENCES (IEEE FORMAT) -->
<h2 class="sec-title">References</h2>
<div class="references">
  <p>[1] M. Liffiton, B. E. Sheese, J. Savelka, and P. Denny, "CodeHelp: Using large language models with guardrails for scalable support in programming classes," in <em>Proc. 23rd Koli Calling Int. Conf. Comput. Educ. Res. (Koli Calling '23)</em>, 2023, pp. 1&ndash;11, doi: 10.1145/3631802.3631830.</p>
  <p>[2] J. Prather, B. N. Reeves, P. Denny, B. A. Becker, J. Leinonen, A. Luxton-Reilly, G. Powell, J. Finnie-Ansley, and E. A. Santos, "&ldquo;It's weird that it knows what I want&rdquo;: Usability and interactions with Copilot for novice programmers," <em>ACM Trans. Comput.-Hum. Interact.</em>, vol. 31, no. 1, pp. 1&ndash;31, 2024, doi: 10.1145/3617367.</p>
  <p>[3] H. Bastani, O. Bastani, A. Sungu, H. Ge, &Ouml;. Kabakc&#305;, and R. Mariman, "Generative AI without guardrails can harm learning: Evidence from high school mathematics," <em>Proc. Natl. Acad. Sci. U.S.A.</em>, vol. 122, no. 26, Art. no. e2422633122, 2025, doi: 10.1073/pnas.2422633122.</p>
  <p>[4] M. Kazemitabaar, J. Chow, C. K. T. Ma, B. J. Ericson, D. Weintrop, and T. Grossman, "Studying the effect of AI code generators on supporting novice learners in introductory programming," in <em>Proc. 2023 CHI Conf. Hum. Factors Comput. Syst. (CHI '23)</em>, 2023, pp. 1&ndash;23, doi: 10.1145/3544548.3580919.</p>
  <p>[5] C. Hou, G. Zhu, V. Sudarshan, F. S. Lim, and Y. S. Ong, "Measuring undergraduate students' reliance on generative AI during problem-solving: Scale development and validation," <em>Comput. Educ.</em>, vol. 234, Art. no. 105329, 2025, doi: 10.1016/j.compedu.2025.105329.</p>
  <p>[6] R. M. Dawes, "The robust beauty of improper linear models in decision making," <em>Amer. Psychol.</em>, vol. 34, no. 7, pp. 571&ndash;582, 1979, doi: 10.1037/0003-066X.34.7.571.</p>
  <p>[7] S. Nelson-LeGall, "Help-seeking: An active instrumental strategy for learning," <em>J. Educ. Psychol.</em>, vol. 73, no. 6, pp. 813&ndash;824, 1981, doi: 10.1037/0022-0663.73.6.813.</p>
  <p>[8] A. Diamantopoulos and H. M. Winklhofer, "Index construction with formative indicators: An alternative to scale development," <em>J. Marketing Res.</em>, vol. 38, no. 2, pp. 269&ndash;277, 2001, doi: 10.1509/jmkr.38.2.269.18845.</p>
  <p>[9] T. W. Price, Z. Liu, V. Catet&eacute;, and T. Barnes, "Factors influencing students' help-seeking behavior while programming with human and computer tutors," in <em>Proc. 2017 ACM Conf. Int. Comput. Educ. Res. (ICER '17)</em>, 2017, pp. 127&ndash;135, doi: 10.1145/3105726.3106179.</p>
  <p>[10] P. Denny, J. Leinonen, J. Prather, A. Luxton-Reilly, T. Amarouche, B. A. Becker, and B. N. Reeves, "Prompt Problems: A new programming exercise for the generative AI era," in <em>Proc. 55th ACM Tech. Symp. Comput. Sci. Educ. (SIGCSE 2024)</em>, 2024, vol. 1, pp. 296&ndash;302, doi: 10.1145/3626252.3630909.</p>
</div>

</body>
</html>
"""

html_path = r"c:\final_estrange\s-sparc\docs\enhancement_proposal_export.html"
pdf_path = r"c:\final_estrange\s-sparc\docs\S_SPARC_Prompting_Analysis_Proposal.pdf"

with open(html_path, "w", encoding="utf-8") as f:
    f.write(html_content)

chrome_path = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
cmd = [
    chrome_path,
    "--headless=new",
    "--disable-gpu",
    "--no-pdf-header-footer",
    f"--print-to-pdf={pdf_path}",
    html_path
]

res = subprocess.run(cmd, capture_output=True, text=True)
print("Returncode:", res.returncode)
print("Stdout:", res.stdout)
print("Stderr:", res.stderr)
print(f"S-SPARC Simple Proposal PDF generated! Size: {os.path.getsize(pdf_path)} bytes")
