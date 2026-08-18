import os
import re
import json
import time
import subprocess
from markdown_it import MarkdownIt

# Input & Output Paths
MD_PATH = r"c:\S-SPARC_FINAL EDIT\UNU_AI_FOR_SDGS_2026_SUBMISSION.md"
HTML_OUTPUT = r"c:\S-SPARC_FINAL EDIT\scratch\UNU_AI_FOR_SDGS_2026_SUBMISSION.html"
PDF_OUTPUT = r"c:\S-SPARC_FINAL EDIT\UNU_AI_FOR_SDGS_2026_SUBMISSION.pdf"

with open(MD_PATH, "r", encoding="utf-8") as f:
    md_content = f.read()

# 1. Protect all LaTeX expressions ($...$ and $$...$$) from Markdown-it underscore parsing
math_blocks = []
def save_display_math(match):
    idx = len(math_blocks)
    math_blocks.append(match.group(0))
    return f"@@@MATH_BLOCK_{idx}@@@"

def save_inline_math(match):
    idx = len(math_blocks)
    math_blocks.append(match.group(0))
    return f"@@@MATH_INLINE_{idx}@@@"

# Protect display math $$ ... $$
content_protected = re.sub(r'\$\$([\s\S]*?)\$\$', save_display_math, md_content)

# Protect inline math $ ... $
content_protected = re.sub(r'(?<!\\)\$([^\$\n]+?)(?<!\\)\$', save_inline_math, content_protected)

# 2. Render Markdown
md = MarkdownIt("commonmark", {"html": True, "typographer": True}).enable("table")
body_html = md.render(content_protected)

# 3. Restore all LaTeX expressions intact
for idx, original_math in enumerate(math_blocks):
    body_html = body_html.replace(f"@@@MATH_BLOCK_{idx}@@@", original_math)
    body_html = body_html.replace(f"@@@MATH_INLINE_{idx}@@@", original_math)

# 4. Convert mermaid code blocks into interactive card wrappers with zoom hints
diagram_counter = 0
def wrap_mermaid(match):
    global diagram_counter
    diagram_counter += 1
    code = match.group(1)
    return f'''<div class="mermaid-card" onclick="openDiagramModal(this)" title="Click to enlarge diagram">
        <div class="diagram-header-tag">
            <span class="diagram-number">FIGURE {diagram_counter}</span>
            <span class="diagram-zoom-badge">🔍 Click to enlarge / zoom</span>
        </div>
        <div class="mermaid">{code}</div>
    </div>'''

body_html = re.sub(
    r'<pre><code class="language-mermaid">([\s\S]*?)</code></pre>',
    wrap_mermaid,
    body_html
)

html_template = f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UNU AI for SDGs 2026 - S-SPARC Research Proposal</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=JetBrains+Mono:wght@400;500;600;700&family=STIX+Two+Text:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    
    <!-- MathJax 3 with Full LaTeX Support -->
    <script>
    window.MathJax = {{
      tex: {{
        inlineMath: [['$', '$'], ['\\\\(', '\\\\)']],
        displayMath: [['$$', '$$'], ['\\\\[', '\\\\]']],
        processEscapes: true,
        processEnvironments: true,
        packages: {{'[+]': ['ams', 'textmacros']}}
      }},
      options: {{
        skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code']
      }},
      svg: {{
        fontCache: 'global',
        displayAlign: 'center'
      }},
      startup: {{
        typeset: true
      }}
    }};
    </script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>

    <!-- Mermaid.js for Vector Diagrams -->
    <script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
    <script>
      mermaid.initialize({{
        startOnLoad: false,
        theme: 'default',
        themeVariables: {{
          fontFamily: 'Plus Jakarta Sans, sans-serif',
          fontSize: '12px',
          primaryColor: '#f0f9ff',
          primaryTextColor: '#0f172a',
          primaryBorderColor: '#0284c7',
          lineColor: '#0284c7',
          secondaryColor: '#f8fafc',
          tertiaryColor: '#ffffff',
          clusterBkg: '#f8fafc',
          clusterBorder: '#cbd5e1'
        }},
        flowchart: {{ useMaxWidth: false, htmlLabels: true, curve: 'basis' }},
        sequence: {{ useMaxWidth: false, actorMargin: 50, messageMargin: 35 }},
        gantt: {{ useMaxWidth: false }},
        pie: {{ useMaxWidth: false }}
      }});

      window.addEventListener('DOMContentLoaded', async () => {{
        try {{
          await mermaid.run({{
            querySelector: '.mermaid'
          }});
          console.log('Mermaid diagrams compiled successfully');
          window.mermaidDone = true;
        }} catch (err) {{
          console.error('Mermaid render error:', err);
          window.mermaidDone = true;
        }}
      }});
    </script>

    <style>
        @page {{
            size: A4;
            margin: 18mm 14mm 18mm 14mm;
            @top-left {{
                content: "UNITED NATIONS UNIVERSITY (UNU) | AI FOR SDGS 2026";
                font-family: 'Plus Jakarta Sans', sans-serif;
                font-size: 7pt;
                font-weight: 700;
                color: #64748b;
                letter-spacing: 0.5px;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 4px;
            }}
            @top-right {{
                content: "S-SPARC: Specific Smart Prompting (TRL 7)";
                font-family: 'Plus Jakarta Sans', sans-serif;
                font-size: 7pt;
                font-weight: 700;
                color: #0284c7;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 4px;
            }}
            @bottom-right {{
                content: "Page " counter(page) " of " counter(pages);
                font-family: 'Plus Jakarta Sans', sans-serif;
                font-size: 8pt;
                color: #64748b;
            }}
        }}

        *, *:before, *:after {{
            box-sizing: border-box;
        }}

        body {{
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1e293b;
            line-height: 1.65;
            font-size: 9.5pt;
            max-width: 980px;
            margin: 0 auto;
            padding: 30px;
            background-color: #ffffff;
            -webkit-font-smoothing: antialiased;
        }}

        /* Academic Paper Masthead */
        h1:first-of-type {{
            background: linear-gradient(135deg, #0b192c 0%, #0369a1 100%);
            color: #ffffff;
            font-size: 14pt;
            font-weight: 800;
            line-height: 1.4;
            padding: 18px 22px;
            border-radius: 8px;
            margin-top: 0;
            margin-bottom: 14px;
            letter-spacing: -0.2px;
            border: none;
            box-shadow: 0 4px 12px rgba(3, 105, 161, 0.15);
        }}

        h1 {{
            font-size: 14pt;
            font-weight: 800;
            color: #0f172a;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 6px;
            margin-top: 26px;
            margin-bottom: 10px;
            letter-spacing: -0.2px;
            page-break-after: avoid;
        }}

        h2 {{
            font-size: 12pt;
            font-weight: 700;
            color: #0369a1;
            border-left: 3.5px solid #0284c7;
            padding-left: 10px;
            margin-top: 24px;
            margin-bottom: 10px;
            page-break-after: avoid;
        }}

        h3 {{
            font-size: 10.5pt;
            font-weight: 600;
            color: #0f172a;
            margin-top: 16px;
            margin-bottom: 8px;
            page-break-after: avoid;
        }}

        h4 {{
            font-size: 9.5pt;
            font-weight: 600;
            color: #334155;
            margin-top: 12px;
            margin-bottom: 6px;
            page-break-after: avoid;
        }}

        p {{
            margin-top: 0;
            margin-bottom: 10px;
            text-align: justify;
            text-justify: inter-word;
        }}

        ul, ol {{
            margin-top: 4px;
            margin-bottom: 12px;
            padding-left: 22px;
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
            padding: 1.5px 5px;
            border-radius: 4px;
            font-size: 8.5pt;
            border: 1px solid #e2e8f0;
        }}

        pre {{
            background-color: #0f172a;
            color: #f8fafc;
            padding: 12px 14px;
            border-radius: 6px;
            overflow-x: auto;
            font-family: 'JetBrains Mono', Consolas, Monaco, monospace;
            font-size: 8pt;
            line-height: 1.45;
            margin: 12px 0;
            page-break-inside: avoid;
            break-inside: avoid;
        }}

        pre code {{
            background-color: transparent;
            color: inherit;
            padding: 0;
            border: none;
        }}

        /* Executive Academic Table Design */
        table {{
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 16px 0;
            font-size: 8.5pt;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
            page-break-inside: avoid;
            break-inside: avoid;
        }}

        th, td {{
            padding: 7px 10px;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: middle;
        }}

        th:last-child, td:last-child {{
            border-right: none;
        }}

        tr:last-child td {{
            border-bottom: none;
        }}

        th {{
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 600;
            letter-spacing: 0.2px;
            font-size: 8.5pt;
        }}

        tr:nth-child(even) {{
            background-color: #f8fafc;
        }}

        tr:hover td {{
            background-color: #f1f5f9;
        }}

        blockquote {{
            border-left: 3.5px solid #0284c7;
            margin: 12px 0;
            padding: 8px 14px;
            background-color: #f0f9ff;
            color: #0369a1;
            font-style: normal;
            border-radius: 0 6px 6px 0;
            page-break-inside: avoid;
            break-inside: avoid;
        }}

        hr {{
            border: 0;
            height: 1px;
            background: #e2e8f0;
            margin: 20px 0;
        }}

        /* Interactive Mermaid Card Styles with Academic Figure Headers */
        .mermaid-card {{
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 12px 16px 16px 16px;
            margin: 18px 0;
            page-break-inside: avoid;
            break-inside: avoid;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
            cursor: zoom-in;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-x: auto;
        }}

        .mermaid-card:hover {{
            border-color: #0284c7;
            box-shadow: 0 6px 16px rgba(2, 132, 199, 0.12);
            transform: translateY(-2px);
        }}

        .diagram-header-tag {{
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #f1f5f9;
        }}

        .diagram-number {{
            font-size: 7.5pt;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 0.5px;
        }}

        .diagram-zoom-badge {{
            font-size: 7.5pt;
            font-weight: 600;
            color: #0284c7;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            padding: 2px 8px;
            border-radius: 12px;
            pointer-events: none;
            opacity: 0.9;
            transition: all 0.2s ease;
        }}

        .mermaid-card:hover .diagram-zoom-badge {{
            opacity: 1;
            background: #0284c7;
            color: #ffffff;
            border-color: #0284c7;
        }}

        .mermaid {{
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            pointer-events: none;
        }}

        .mermaid svg {{
            max-width: 100%;
            height: auto;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }}

        /* Pure LaTeX Math Typography & Display Environment */
        .MathJax_Display, mjx-container[display="true"] {{
            margin: 12px 0 !important;
            padding: 10px 16px !important;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            page-break-inside: avoid;
            break-inside: avoid;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);
        }}

        mjx-container:not([display="true"]) {{
            padding: 0 1px;
            font-family: 'STIX Two Text', serif !important;
        }}

        /* Modal Lightbox for Diagram Zoom */
        .diagram-modal {{
            display: none;
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(15, 23, 42, 0.82);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.25s ease;
        }}

        .diagram-modal.active {{
            display: flex;
            opacity: 1;
        }}

        .diagram-modal-container {{
            position: relative;
            background: #ffffff;
            border-radius: 12px;
            width: 95vw;
            max-width: 1280px;
            height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            animation: modalPop 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }}

        @keyframes modalPop {{
            0% {{ transform: scale(0.95); opacity: 0; }}
            100% {{ transform: scale(1); opacity: 1; }}
        }}

        .diagram-modal-header {{
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            background: #0f172a;
            color: #ffffff;
            border-bottom: 1px solid #334155;
        }}

        .modal-title {{
            font-size: 11pt;
            font-weight: 700;
            color: #f8fafc;
            display: flex;
            align-items: center;
            gap: 8px;
        }}

        .modal-controls {{
            display: flex;
            align-items: center;
            gap: 8px;
        }}

        .modal-btn {{
            background: #1e293b;
            color: #f8fafc;
            border: 1px solid #475569;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 9pt;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }}

        .modal-btn:hover {{
            background: #0284c7;
            border-color: #38bdf8;
            color: #ffffff;
        }}

        .modal-close-btn {{
            background: #e11d48;
            border-color: #f43f5e;
            color: #ffffff;
            font-size: 11pt;
            padding: 4px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.15s ease;
        }}

        .modal-close-btn:hover {{
            background: #be123c;
        }}

        .diagram-modal-body {{
            flex: 1;
            overflow: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
            background: radial-gradient(circle, #f8fafc 10%, #f1f5f9 100%);
            cursor: grab;
            user-select: none;
        }}

        .diagram-modal-body:active {{
            cursor: grabbing;
        }}

        .diagram-zoom-wrapper {{
            transform-origin: center center;
            transition: transform 0.15s ease-out;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
        }}

        .diagram-zoom-wrapper svg {{
            max-width: 100% !important;
            max-height: 100% !important;
            width: auto !important;
            height: auto !important;
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.06));
        }}

        .diagram-modal-footer {{
            padding: 8px 16px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            font-size: 8pt;
            color: #64748b;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }}

        .diagram-modal-footer kbd {{
            background: #e2e8f0;
            color: #334155;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            font-family: inherit;
            font-size: 7.5pt;
        }}

        @media print {{
            body {{
                padding: 0;
                font-size: 9pt;
            }}
            .diagram-zoom-badge, .diagram-modal {{
                display: none !important;
            }}
            .mermaid-card {{
                border: 1px solid #cbd5e1;
                box-shadow: none;
                padding: 10px;
                margin: 10px 0;
                cursor: default;
                transform: none !important;
            }}
            pre, table, .mermaid-card, mjx-container {{
                page-break-inside: avoid;
                break-inside: avoid;
            }}
            h1, h2, h3, h4 {{
                page-break-after: avoid;
                break-after: avoid;
            }}
        }}
    </style>
</head>
<body>
    {body_html}

    <!-- Interactive Diagram Lightbox Modal -->
    <div id="diagramModal" class="diagram-modal" onclick="handleModalBackdropClick(event)">
        <div class="diagram-modal-container" onclick="event.stopPropagation()">
            <div class="diagram-modal-header">
                <div class="modal-title">
                    <span>📊</span>
                    <span id="modalDiagramTitle">S-SPARC Architectural Diagram Inspection</span>
                </div>
                <div class="modal-controls">
                    <button class="modal-btn" onclick="zoomDiagram(1.25)" title="Zoom In">🔍 Zoom In (+)</button>
                    <button class="modal-btn" onclick="zoomDiagram(0.8)" title="Zoom Out">🔍 Zoom Out (−)</button>
                    <button class="modal-btn" onclick="resetDiagramZoom()" title="Reset Zoom">↺ Reset (100%)</button>
                    <button class="modal-close-btn" onclick="closeDiagramModal()" title="Close Viewer (Esc)">✕</button>
                </div>
            </div>
            <div class="diagram-modal-body" id="diagramModalBody" onwheel="handleModalWheel(event)">
                <div class="diagram-zoom-wrapper" id="diagramZoomWrapper">
                    <!-- Cloned SVG goes here -->
                </div>
            </div>
            <div class="diagram-modal-footer">
                <span>💡 Use mouse wheel or buttons to zoom • Click & drag to pan diagram</span>
                <span>Press <kbd>ESC</kbd> or click outside to close</span>
            </div>
        </div>
    </div>

    <script>
        let currentZoom = 1;
        let isDragging = false;
        let startX, startY, translateX = 0, translateY = 0;

        function openDiagramModal(cardElement) {{
            const svgElement = cardElement.querySelector('.mermaid svg');
            if (!svgElement) return;

            const modal = document.getElementById('diagramModal');
            const wrapper = document.getElementById('diagramZoomWrapper');
            const titleElement = document.getElementById('modalDiagramTitle');

            // Find closest heading for context title
            let prevHeading = cardElement.previousElementSibling;
            while (prevHeading && !['H1', 'H2', 'H3', 'H4'].includes(prevHeading.tagName)) {{
                prevHeading = prevHeading.previousElementSibling;
            }}
            titleElement.textContent = prevHeading ? `📊 ${{prevHeading.textContent}}` : '📊 S-SPARC Architectural Diagram Inspection';

            // Clone SVG
            wrapper.innerHTML = '';
            const clonedSvg = svgElement.cloneNode(true);
            clonedSvg.style.maxWidth = '100%';
            clonedSvg.style.height = 'auto';
            wrapper.appendChild(clonedSvg);

            // Reset state
            currentZoom = 1;
            translateX = 0;
            translateY = 0;
            updateTransform();

            // Show modal
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }}

        function closeDiagramModal() {{
            const modal = document.getElementById('diagramModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }}

        function handleModalBackdropClick(event) {{
            if (event.target.id === 'diagramModal') {{
                closeDiagramModal();
            }}
        }}

        function zoomDiagram(factor) {{
            currentZoom = Math.min(Math.max(0.4, currentZoom * factor), 4.0);
            updateTransform();
        }}

        function resetDiagramZoom() {{
            currentZoom = 1;
            translateX = 0;
            translateY = 0;
            updateTransform();
        }}

        function updateTransform() {{
            const wrapper = document.getElementById('diagramZoomWrapper');
            if (wrapper) {{
                wrapper.style.transform = `translate(${{translateX}}px, ${{translateY}}px) scale(${{currentZoom}})`;
            }}
        }}

        function handleModalWheel(event) {{
            event.preventDefault();
            const delta = event.deltaY > 0 ? 0.85 : 1.18;
            zoomDiagram(delta);
        }}

        // Keyboard navigation (Escape to close)
        document.addEventListener('keydown', (e) => {{
            if (e.key === 'Escape') {{
                closeDiagramModal();
            }} else if (e.key === '+' || e.key === '=') {{
                zoomDiagram(1.2);
            }} else if (e.key === '-') {{
                zoomDiagram(0.8);
            }} else if (e.key === '0') {{
                resetDiagramZoom();
            }}
        }});

        // Pan and Drag support
        const modalBody = document.getElementById('diagramModalBody');
        modalBody.addEventListener('mousedown', (e) => {{
            isDragging = true;
            startX = e.clientX - translateX;
            startY = e.clientY - translateY;
        }});

        window.addEventListener('mousemove', (e) => {{
            if (!isDragging) return;
            translateX = e.clientX - startX;
            translateY = e.clientY - startY;
            updateTransform();
        }});

        window.addEventListener('mouseup', () => {{
            isDragging = false;
        }});
    </script>
</body>
</html>
"""

os.makedirs(os.path.dirname(HTML_OUTPUT), exist_ok=True)
with open(HTML_OUTPUT, "w", encoding="utf-8") as f:
    f.write(html_template)

print(f"Academic LaTeX Protected HTML written to {HTML_OUTPUT}")
