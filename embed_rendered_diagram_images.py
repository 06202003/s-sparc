import os
import re
import yaml
import json
import subprocess

def update_all_docs_with_images():
    base_dir = os.path.dirname(os.path.abspath(__file__))
    
    # 1. Update docs/system_flow_diagrams.md
    sys_flow_path = os.path.join(base_dir, "docs", "system_flow_diagrams.md")
    with open(sys_flow_path, "r", encoding="utf-8") as f:
        content = f.read()

    # Replace ```mermaid ... ``` blocks with rendered PNG image + Mermaid code block
    count = 0
    def replacer(match):
        nonlocal count
        count += 1
        code_block = match.group(0)
        img_filename = f"diagram_{count}.png"
        return f"![Diagram {count} Flowchart](images/{img_filename})\n\n<details>\n<summary>Klik untuk melihat kode Mermaid Diagram {count}</summary>\n\n{code_block}\n\n</details>"

    updated_md = re.sub(r'```mermaid\s*\n.*?\n```', replacer, content, flags=re.DOTALL)
    
    with open(sys_flow_path, "w", encoding="utf-8") as f:
        f.write(updated_md)

    # Sync to root DIAGRAM_ALIR_S-SPARC_ESTRANGE.md and docs/DIAGRAM_ALIR_S-SPARC_ESTRANGE.md
    with open(os.path.join(base_dir, "DIAGRAM_ALIR_S-SPARC_ESTRANGE.md"), "w", encoding="utf-8") as f:
        f.write(updated_md)
    with open(os.path.join(base_dir, "docs", "DIAGRAM_ALIR_S-SPARC_ESTRANGE.md"), "w", encoding="utf-8") as f:
        f.write(updated_md)

    print(f"Updated Markdown docs with rendered images for {count} diagrams!")

    # 2. Update Redocly openapi.yaml & openapi.json with HTML image embeddings
    openapi_yaml_path = os.path.join(base_dir, "docs", "openapi.yaml")
    with open(openapi_yaml_path, "r", encoding="utf-8") as f:
        spec_yaml = yaml.safe_load(f)

    # Clean description and insert HTML img tags for diagrams
    desc = spec_yaml["info"]["description"]

    count_redocly = 0
    def redocly_replacer(match):
        nonlocal count_redocly
        count_redocly += 1
        code_block = match.group(0)
        img_filename = f"diagram_{count_redocly}.png"
        return f'<div align="center" style="margin: 20px 0;">\n  <img src="images/{img_filename}" alt="Diagram {count_redocly}" style="max-width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);" />\n</div>\n\n{code_block}'

    updated_desc = re.sub(r'```mermaid\s*\n.*?\n```', redocly_replacer, desc, flags=re.DOTALL)
    spec_yaml["info"]["description"] = updated_desc

    with open(openapi_yaml_path, "w", encoding="utf-8") as f:
        yaml.dump(spec_yaml, f, sort_keys=False, allow_unicode=True, width=1000)

    # Sync openapi.json
    with open(os.path.join(base_dir, "docs", "openapi.json"), "w", encoding="utf-8") as f:
        json.dump(spec_yaml, f, indent=2, ensure_ascii=False)

    print(f"Updated Redocly openapi.yaml and openapi.json with {count_redocly} rendered diagram images!")

if __name__ == "__main__":
    update_all_docs_with_images()
