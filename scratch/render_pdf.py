import os
import subprocess
import time

edge_paths = [
    r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
    r'C:\Program Files\Microsoft\Edge\Application\msedge.exe'
]
edge = next((p for p in edge_paths if os.path.exists(p)), None)
html = os.path.abspath(r'c:\S-SPARC_FINAL EDIT\scratch\UNU_AI_FOR_SDGS_2026_SUBMISSION.html')
pdf = os.path.abspath(r'c:\S-SPARC_FINAL EDIT\UNU_AI_FOR_SDGS_2026_SUBMISSION.pdf')
tmp_profile = os.path.abspath(r'c:\S-SPARC_FINAL EDIT\scratch\edge_profile')

os.makedirs(tmp_profile, exist_ok=True)

# Generate PDF using Edge with virtual-time-budget so Mermaid and MathJax JS finish executing and rendering SVG
cmd = [
    edge,
    '--headless',
    '--disable-gpu',
    '--no-sandbox',
    '--no-first-run',
    '--no-default-browser-check',
    f'--user-data-dir={tmp_profile}',
    '--run-all-compositor-stages-before-draw',
    '--virtual-time-budget=10000',
    f'--print-to-pdf={pdf}',
    f'file:///{html.replace(os.sep, "/")}'
]

print("Executing PDF compilation with Mermaid & MathJax rendering...")
p = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
try:
    stdout, stderr = p.communicate(timeout=25)
    print("Process finished.")
except subprocess.TimeoutExpired:
    print("Process reached virtual time budget, terminating.")
    p.terminate()

if os.path.exists(pdf):
    size = os.path.getsize(pdf)
    print(f"SUCCESS: PDF generated! File size: {size:,} bytes")
else:
    print("ERROR: PDF was not generated.")
