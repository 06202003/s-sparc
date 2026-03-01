"""
Clear old session_tokens data that was incorrectly logged from retrieval (before fix).

This script deletes all session_tokens from the current week to reset token usage.
After running this script, only NEW requests (after fix) will be logged correctly:
- Retrieval (FREE): No tokens logged ✅
- GPT: Normal tokens logged ✅

CAUTION: This deletes ALL tokens from current week, including valid GPT tokens!
Only run if you want to reset the week's token usage completely.
"""

import pymysql
import os
from dotenv import load_dotenv

load_dotenv()

DB_HOST = os.getenv('DB_HOST', 'localhost')
DB_PORT = int(os.getenv('DB_PORT', 3306))
DB_USER = os.getenv('DB_USER', 'root')
DB_PASSWORD = os.getenv('DB_PASSWORD', '')
DB_NAME = os.getenv('DB_NAME', 's_sparc')

def get_connection():
    return pymysql.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_USER,
        password=DB_PASSWORD,
        database=DB_NAME,
        cursorclass=pymysql.cursors.DictCursor
    )

def main():
    print("=" * 70)
    print("CLEAR OLD RETRIEVAL TOKENS - Session Tokens Cleanup")
    print("=" * 70)
    print()
    print("This script will DELETE all session_tokens from the current week.")
    print("After deletion, token usage will reset to 0.")
    print()
    print("⚠️  WARNING: This includes valid GPT tokens logged this week!")
    print("⚠️  Only proceed if you want to completely reset this week's usage.")
    print()
    
    confirm = input("Type 'YES' to proceed, or anything else to cancel: ").strip()
    if confirm != 'YES':
        print("❌ Cancelled. No data deleted.")
        return
    
    print()
    print("Connecting to database...")
    
    conn = get_connection()
    try:
        with conn.cursor() as cur:
            # Check current week's data
            cur.execute("""
                SELECT 
                    COUNT(*) AS total_records,
                    COALESCE(SUM(tokens_used), 0) AS total_tokens
                FROM session_tokens
                WHERE YEARWEEK(used_at, 1) = YEARWEEK(NOW(), 1)
            """)
            before = cur.fetchone()
            
            print(f"📊 Current week's data:")
            print(f"   Records: {before['total_records']}")
            print(f"   Total tokens: {before['total_tokens']}")
            print()
            
            if before['total_records'] == 0:
                print("✅ No data to delete. Token usage already clean.")
                return
            
            # Delete current week's session_tokens
            print("🗑️  Deleting current week's session_tokens...")
            cur.execute("""
                DELETE FROM session_tokens
                WHERE YEARWEEK(used_at, 1) = YEARWEEK(NOW(), 1)
            """)
            deleted = cur.rowcount
            conn.commit()
            
            print(f"✅ Deleted {deleted} records.")
            print()
            
            # Verify deletion
            cur.execute("""
                SELECT 
                    COUNT(*) AS total_records,
                    COALESCE(SUM(tokens_used), 0) AS total_tokens
                FROM session_tokens
                WHERE YEARWEEK(used_at, 1) = YEARWEEK(NOW(), 1)
            """)
            after = cur.fetchone()
            
            print(f"📊 After cleanup:")
            print(f"   Records: {after['total_records']}")
            print(f"   Total tokens: {after['total_tokens']}")
            print()
            
            if after['total_records'] == 0:
                print("=" * 70)
                print("✅ SUCCESS! Token usage reset to 0.")
                print("=" * 70)
                print()
                print("Next steps:")
                print("1. Restart backend: python run_production_server.py")
                print("2. Refresh frontend: F5 in browser")
                print("3. Test retrieval (similarity >=0.95): Token usage should NOT increase")
                print("4. Test GPT (new question): Token usage WILL increase (correct!)")
                print()
            else:
                print("⚠️  Warning: Some records remain. Check database manually.")
    
    except Exception as e:
        print(f"❌ Error: {e}")
        conn.rollback()
    finally:
        conn.close()

if __name__ == '__main__':
    main()
