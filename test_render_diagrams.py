import os
import re
import base64
import urllib.request
import zlib

def render_mermaid_to_png_or_svg(mermaid_code: str, output_png: str):
    """
    Renders Mermaid code string into a PNG image file using mermaid.ink or Kroki service.
    """
    try:
        # Method 1: mermaid.ink (PNG)
        encoded_bytes = base64.b64encode(mermaid_code.strip().encode('utf-8'))
        encoded_str = encoded_bytes.decode('ascii')
        url = f"https://mermaid.ink/img/{encoded_str}?type=png"
        
        req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
        with urllib.request.urlopen(req, timeout=15) as response:
            data = response.read()
            if len(data) > 100:
                with open(output_png, 'wb') as f:
                    f.write(data)
                print(f"Successfully rendered via mermaid.ink: {output_png} ({len(data)} bytes)")
                return True
    except Exception as e:
        print(f"mermaid.ink failed for {output_png}: {e}")

    try:
        # Method 2: Kroki.io fallback (SVG/PNG)
        compressed = zlib.compress(mermaid_code.strip().encode('utf-8'))
        encoded_kroki = base64.urlsafe_b64encode(compressed).decode('ascii')
        url = f"https://kroki.io/mermaid/png/{encoded_kroki}"
        
        req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
        with urllib.request.urlopen(req, timeout=15) as response:
            data = response.read()
            if len(data) > 100:
                with open(output_png, 'wb') as f:
                    f.write(data)
                print(f"Successfully rendered via Kroki: {output_png} ({len(data)} bytes)")
                return True
    except Exception as e2:
        print(f"Kroki failed for {output_png}: {e2}")

    return False

def process_diagrams():
    base_dir = os.path.dirname(os.path.abspath(__file__))
    img_dir = os.path.join(base_dir, "docs", "images")
    os.makedirs(img_dir, exist_ok=True)
    
    with open(os.path.join(base_dir, "docs", "system_flow_diagrams.md"), 'r', encoding='utf-8') as f:
        md_text = f.read()

    # Find all ```mermaid ... ``` blocks
    pattern = re.compile(r'```mermaid\s*\n(.*?)\n```', re.DOTALL)
    matches = pattern.findall(md_text)

    print(f"Found {len(matches)} Mermaid diagrams to render into PNG images...")

    rendered_images = []
    for idx, code in enumerate(matches, 1):
        filename = f"diagram_{idx}.png"
        filepath = os.path.join(img_dir, filename)
        rel_path = f"images/{filename}"
        
        success = render_mermaid_to_png_or_svg(code, filepath)
        if success:
            rendered_images.append((idx, rel_path, filepath))
        else:
            print(f"Failed to render diagram {idx}")

    print(f"\nRendered {len(rendered_images)} / {len(matches)} diagrams successfully!")

if __name__ == "__main__":
    process_diagrams()
