

import json
import pymysql
import os
import uuid
from dotenv import load_dotenv

load_dotenv()

def get_db_connection():
    return pymysql.connect(
        host=os.getenv("MYSQL_HOST", "localhost"),
        user=os.getenv("MYSQL_USER", "root"),
        password=os.getenv("MYSQL_PASSWORD", ""),
        database=os.getenv("MYSQL_DB", "db_semantic_v3"),
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor
    )

with open("semantic_similarity/mbpp_all_with_embedding_v2.json", "r", encoding="utf-8") as f:
    data = json.load(f)

conn = get_db_connection()
try:
    with conn.cursor() as cur:
        # Pastikan ada user admin/import
        admin_user_id = str(uuid.uuid4())
        admin_username = "admin_import"
        admin_email = "admin_import@example.com"
        admin_password_hash = "testpass123"
        # Cek apakah user sudah ada
        cur.execute("SELECT user_id FROM users WHERE username=%s", (admin_username,))
        row = cur.fetchone()
        if row:
            admin_user_id = row["user_id"]
        else:
            cur.execute(
                "INSERT INTO users (user_id, username, email, password_hash) VALUES (%s, %s, %s, %s)",
                (admin_user_id, admin_username, admin_email, admin_password_hash)
            )

        for entry in data:
            prompt = entry["prompt"]
            code = entry["code"]
            embedding = json.dumps(entry["embedding"])
            uuid_id = str(uuid.uuid4())
            cur.execute(
                "INSERT INTO code_embeddings (id, user_id, prompt, code, embedding, created_at) VALUES (%s, %s, %s, %s, %s, NOW())",
                (uuid_id, admin_user_id, prompt, code, embedding)
            )
    conn.commit()
finally:
    conn.close()
print("Migrasi selesai.")