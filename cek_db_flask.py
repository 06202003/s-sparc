import pymysql
import pymysql
import os
from dotenv import load_dotenv

# Pastikan .env dibaca agar variabel lingkungan terisi
load_dotenv()

print("MYSQL_USER from env:", os.getenv("MYSQL_USER"))

conn = pymysql.connect(
    host=os.getenv("MYSQL_HOST", "localhost"),
    user=os.getenv("MYSQL_USER", "root"),
    password=os.getenv("MYSQL_PASSWORD", ""),
    database=os.getenv("MYSQL_DB", "db_semantic"),
    charset="utf8mb4",
    cursorclass=pymysql.cursors.DictCursor
)
print("DB Connection established.")

with conn.cursor() as cur:
    cur.execute("SELECT DATABASE() AS db")
    print("DB used by Flask:", cur.fetchone()['db'])
    cur.execute("SHOW CREATE TABLE code_embeddings;")
    print("Table structure:", cur.fetchone())
conn.close()
