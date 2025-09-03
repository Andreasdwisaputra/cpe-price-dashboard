from db_connection import connect_db

def log_search(user_id, product_queried, command):
    try:
        conn = connect_db()
        cursor = conn.cursor()
        cursor.execute("""
            INSERT INTO telegram_logs (user_id, command, product_queried, timestamp)
            VALUES (%s, %s, %s, NOW())
        """, (user_id, command, product_queried))
        conn.commit()
        cursor.close()
        conn.close()
        print("Log pencarian berhasil disimpan!")
    except Exception as e:
        print(f"Error menyimpan log pencarian: {e}")
