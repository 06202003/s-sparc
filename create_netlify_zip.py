import os
import zipfile

def create_zips():
    base_dir = os.path.dirname(os.path.abspath(__file__))
    
    # 1. Create docs-specific Netlify Zip (ideal for Netlify direct Drag-and-Drop deploy)
    docs_zip_path = os.path.join(base_dir, "ssparc_redocly_docs_netlify.zip")
    with zipfile.ZipFile(docs_zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
        # Add index.html and docs files
        for root, dirs, files in os.walk(os.path.join(base_dir, "docs")):
            for file in files:
                file_path = os.path.join(root, file)
                rel_path = os.path.relpath(file_path, os.path.join(base_dir, "docs"))
                zipf.write(file_path, rel_path)
                
        # Also add netlify.toml & redocly.yaml at root of zip
        if os.path.exists(os.path.join(base_dir, "netlify.toml")):
            zipf.write(os.path.join(base_dir, "netlify.toml"), "netlify.toml")
        if os.path.exists(os.path.join(base_dir, "redocly.yaml")):
            zipf.write(os.path.join(base_dir, "redocly.yaml"), "redocly.yaml")

    print(f"Created docs Netlify zip: {docs_zip_path} (Size: {os.path.getsize(docs_zip_path)} bytes)")

    # 2. Create full clean project Zip (excluding .venv, .git, heavy sql dumps)
    project_zip_path = os.path.join(base_dir, "ssparc_project_netlify.zip")
    exclude_dirs = {'.venv', '.git', '__pycache__', '.system_checkpoints', '.vscode', 'pretrained_model', 'tmp'}
    exclude_files = {'ssparc_project_netlify.zip', 'ssparc_redocly_docs_netlify.zip'}

    with zipfile.ZipFile(project_zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(base_dir):
            dirs[:] = [d for d in dirs if d not in exclude_dirs]
            for file in files:
                if file in exclude_files or file.endswith('.sql') or file.endswith('.gz.part01') or file.endswith('.gz.part02') or file.endswith('.gz.part03'):
                    continue
                file_path = os.path.join(root, file)
                rel_path = os.path.relpath(file_path, base_dir)
                zipf.write(file_path, rel_path)

    print(f"Created full project Netlify zip: {project_zip_path} (Size: {os.path.getsize(project_zip_path)} bytes)")

if __name__ == "__main__":
    create_zips()
