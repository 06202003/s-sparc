import sys
import os
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

from backend.core.db import get_db_connection

def clean_poisoned_embeddings():
    conn = get_db_connection()
    if not conn:
        print("Failed to connect to database.")
        return
        
    cur = conn.cursor()
    query = """
        SELECT id, prompt, code FROM code_embeddings 
        WHERE prompt LIKE '%adadsa%' 
           OR prompt LIKE '%ssssss%' 
           OR prompt LIKE '%zzzzzz%' 
           OR prompt LIKE '%aaaaaaa%'
           OR prompt LIKE '%nyaaaaaa%'
           OR prompt LIKE '%apaaaaaa%'
    """
    cur.execute(query)
    rows = cur.fetchall()
    print(f"Found {len(rows)} poisoned rows to delete:")
    for r in rows:
        print(f"  - Deleting ID: {r['id']} | Prompt: {repr(r['prompt'][:60])}")
        cur.execute("DELETE FROM code_embeddings WHERE id = %s", (r['id'],))
        
    conn.commit()
    print("Database cleanup committed successfully.")
    conn.close()

if __name__ == '__main__':
    clean_poisoned_embeddings()
