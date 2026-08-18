import re
import os
import subprocess
import time
from markdown_it import MarkdownIt

md_file = r"c:\S-SPARC_FINAL EDIT\UNU_AI_FOR_SDGS_2026_SUBMISSION.md"
html_file = r"c:\S-SPARC_FINAL EDIT\scratch_proposal.html"
pdf_file = r"c:\S-SPARC_FINAL EDIT\UNU_AI_FOR_SDGS_2026_SUBMISSION.pdf"

with open(md_file, "r", encoding="utf-8") as f:
    content = f.read()

# Setup markdown parser with tables and code fences
md = MarkdownIt("commonmark", {"html": True, "typographer": True}).enable("table")
html_body = md.render(content)

# Fix mermaid blocks so mermaid.js can render them
html_body = re.sub(
    r'<pre><code class="language-mermaid">([\s\S]*?)</code></pre>',
    r'<div class="mermaid">\1</div>',
    html_body
)

# Convert \[ ... \] or $$ ... $$ math blocks for MathJax
full_html = f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UNU AI for SDGs 2026 - S-SPARC Project Proposal</title>
    <!-- MathJax for LaTeX -->
    <script>
    MathJax = {{
      tex: {{
        inlineMath: [['$', '$'], ['\\\\(', '\\\\)']],
        displayMath: [['$$', '$$'], ['\\\\[', '\\\\]']]
      }},
      svg: {{
        fontCache: 'global'
      }}
    }};
    </script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <!-- Mermaid.js -->
    <script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
    <script>
      mermaid.initialize({{ startOnLoad: true, theme: 'neutral' }});
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap');
        
        @page {{
            size: A4;
            margin: 20mm 15mm 20mm 15mm;
            @bottom-right {{
                content: counter(page) " / " counter(pages);
                font-family: 'Inter', sans-serif;
                font-size: 8pt;
                color: #64748b;
            }}
        }}

        body {{
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1e293b;
            line-height: 1.6;
            font-size: 10.5pt;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
        }}

        h1 {{
            font-size: 18pt;
            font-weight: 800;
            color: #0f172a;
            border-bottom: 2.5px solid #0284c7;
            padding-bottom: 8px;
            margin-top: 24px;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }}

        h2 {{
            font-size: 14pt;
            font-weight: 700;
            color: #0369a1;
            border-bottom: 1.5px solid #e2e8f0;
            padding-bottom: 6px;
            margin-top: 28px;
            margin-bottom: 12px;
            page-break-after: avoid;
        }}

        h3 {{
            font-size: 12pt;
            font-weight: 600;
            color: #334155;
            margin-top: 18px;
            margin-bottom: 8px;
            page-break-after: avoid;
        }}

        h4 {{
            font-size: 11pt;
            font-weight: 600;
            color: #475569;
            margin-top: 14px;
            margin-bottom: 6px;
            page-break-after: avoid;
        }}

        p {{
            margin-bottom: 10px;
            text-align: justify;
        }}

        ul, ol {{
            margin-top: 4px;
            margin-bottom: 12px;
            padding-left: 24px;
        }}

        li {{
            margin-bottom: 4px;
        }}

        strong {{
            color: #0f172a;
            font-weight: 600;
        }}

        code {{
            font-family: 'JetBrains Mono', Consolas, Monaco, monospace;
            background-color: #f1f5f9;
            color: #0f766e;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 9pt;
        }}

        pre {{
            background-color: #0f172a;
            color: #f8fafc;
            padding: 12px 16px;
            border-radius: 8px;
            overflow-x: auto;
            font-family: 'JetBrains Mono', Consolas, Monaco, monospace;
            font-size: 8.5pt;
            line-height: 1.45;
            margin: 14px 0;
            page-break-inside: avoid;
        }}

        pre code {{
            background-color: transparent;
            color: inherit;
            padding: 0;
        }}

        table {{
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            font-size: 9pt;
            page-break-inside: avoid;
        }}

        th, td {{
            border: 1px solid #cbd5e1;
            padding: 7px 10px;
            text-align: left;
        }}

        th {{
            background-color: #f8fafc;
            color: #0f172a;
            font-weight: 700;
            border-bottom: 2px solid #94a3b8;
        }}

        tr:nth-child(even) {{
            background-color: #f8fafc;
        }}

        blockquote {{
            border-left: 4px solid #0284c7;
            margin: 14px 0;
            padding: 8px 16px;
            background-color: #f0f9ff;
            color: #0369a1;
            font-style: italic;
            border-radius: 0 6px 6px 0;
        }}

        hr {{
            border: 0;
            height: 1px;
            background: #cbd5e1;
            margin: 20px 0;
        }}

        .mermaid {{
            display: flex;
            justify-content: center;
            margin: 16px 0;
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            page-break-inside: avoid;
        }}

        .header-box {{
            background: linear-gradient(135deg, #0f172a 0%, #0369a1 100%);
            color: #ffffff;
            padding: 24px;
            border-radius: 10px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }}

        .header-box h1 {{
            color: #ffffff;
            border-bottom: 2px solid #38bdf8;
            margin-top: 0;
        }}

        .header-meta {{
            font-size: 9.5pt;
            color: #e0f2fe;
            line-height: 1.5;
        }}
    </style>
</head>
<body>
    {html_body}
</body>
</html>
"""

os.makedirs(r"c:\S-SPARC_FINAL EDIT\scratch", exist_ok=True)
scratch_html = r"c:\S-SPARC_FINAL EDIT\scratch\UNU_AI_FOR_SDGS_2026_SUBMISSION.html"
with open(scratch_html, "w", encoding="utf-8") as f:
    f.write(full_html)

print("HTML generated successfully at:", scratch_html)
