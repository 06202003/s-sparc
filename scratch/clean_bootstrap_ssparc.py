import re
import os

files = [
    r"c:\S-SPARC_FINAL EDIT\estrange\v2\v2\ssparc\about.php",
    r"c:\S-SPARC_FINAL EDIT\estrange\v2\v2\ssparc\features.php",
    r"c:\S-SPARC_FINAL EDIT\estrange\v2\v2\ssparc\index.php",
    r"c:\S-SPARC_FINAL EDIT\estrange\v2\v2\ssparc\sustainability.php"
]

replacements = [
    (r'\bcol-lg-6\b', 'w-full lg:w-1/2'),
    (r'\bcol-lg-10\b', 'w-full lg:w-5/6'),
    (r'\bcol-md-6\b', 'w-full md:w-1/2'),
    (r'\bcol-md-4\b', 'w-full md:w-1/3'),
    (r'\brow\b', 'flex flex-wrap'),
]

for fpath in files:
    if os.path.exists(fpath):
        with open(fpath, 'r', encoding='utf-8') as f:
            content = f.read()
        for pat, repl in replacements:
            content = re.sub(pat, repl, content)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Cleaned Bootstrap classes in {os.path.basename(fpath)}")
