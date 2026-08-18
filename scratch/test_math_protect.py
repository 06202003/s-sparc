import os
import re
from markdown_it import MarkdownIt

md_file = r"c:\S-SPARC_FINAL EDIT\UNU_AI_FOR_SDGS_2026_SUBMISSION.md"
with open(md_file, "r", encoding="utf-8") as f:
    text = f.read()

# Protect LaTeX Math from Markdown-it parser
math_blocks = []
def save_display_math(match):
    idx = len(math_blocks)
    math_blocks.append(match.group(0))
    return f"@@@MATH_BLOCK_{idx}@@@"

def save_inline_math(match):
    idx = len(math_blocks)
    math_blocks.append(match.group(0))
    return f"@@@MATH_INLINE_{idx}@@@"

# 1. Protect display math $$ ... $$
text_protected = re.sub(r'\$\$([\s\S]*?)\$\$', save_display_math, text)

# 2. Protect inline math $ ... $ (ensuring not matching escaped dollars or empty)
text_protected = re.sub(r'(?<!\\)\$([^\$\n]+?)(?<!\\)\$', save_inline_math, text_protected)

print(f"Total protected LaTeX expressions: {len(math_blocks)}")

# 3. Render Markdown
md = MarkdownIt("commonmark", {"html": True, "typographer": True}).enable("table")
rendered_html = md.render(text_protected)

# 4. Restore LaTeX expressions
for idx, original_math in enumerate(math_blocks):
    rendered_html = rendered_html.replace(f"@@@MATH_BLOCK_{idx}@@@", original_math)
    rendered_html = rendered_html.replace(f"@@@MATH_INLINE_{idx}@@@", original_math)

# Check line 47 equivalent
for line in rendered_html.splitlines():
    if "Every interaction computes" in line:
        print("Test line output:")
        print(line)
        if "<em>" in line:
            print("ERROR: Still contains <em>!")
        else:
            print("SUCCESS: Clean LaTeX restored without <em> tags!")
