import os
import shutil

SRC = r"c:\S-SPARC_FINAL EDIT"
DST = r"C:\S-SPARC_DEPLOY"

EXCLUDE_DIRS = {
    ".git",
    ".gemini",
    "__pycache__",
    ".pytest_cache",
    "edge_tmp_profile"
}

EXCLUDE_FILES = {
    ".DS_Store",
    "Thumbs.db"
}

def sync_directories(src, dst):
    print(f"Syncing from {src} to {dst}...")
    copied_files = 0
    copied_dirs = 0

    for root, dirs, files in os.walk(src):
        # Filter out excluded directories in-place
        dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS and not d.startswith(".gemini")]
        
        # Relative path from src
        rel_path = os.path.relpath(root, src)
        if rel_path == ".":
            dest_dir = dst
        else:
            dest_dir = os.path.join(dst, rel_path)
            
        os.makedirs(dest_dir, exist_ok=True)
        
        for file in files:
            if file in EXCLUDE_FILES or file.endswith(".tmp"):
                continue
            src_file = os.path.join(root, file)
            dest_file = os.path.join(dest_dir, file)
            
            # Copy if file does not exist or size/mtime differ
            should_copy = False
            if not os.path.exists(dest_file):
                should_copy = True
            else:
                src_stat = os.stat(src_file)
                dst_stat = os.stat(dest_file)
                if src_stat.st_size != dst_stat.st_size or src_stat.st_mtime > dst_stat.st_mtime:
                    should_copy = True
                    
            if should_copy:
                shutil.copy2(src_file, dest_file)
                copied_files += 1

    print(f"Sync complete! Copied/updated {copied_files} files.")

if __name__ == "__main__":
    sync_directories(SRC, DST)
