from db_connection import connect_db

def save_product_to_db(product_name, nett_price, margin_mitra, max_reasonable_price):
    try:
        conn = connect_db()
        cursor = conn.cursor()
        cursor.execute("""
            INSERT INTO products (product_name, nett_price, margin_mitra, max_reasonable_price, created_at)
            VALUES (%s, %s, %s, %s, NOW())
        """, (product_name, nett_price, margin_mitra, max_reasonable_price))
        conn.commit()
        cursor.close()
        conn.close()
        print("Data produk berhasil disimpan!")
    except Exception as e:
        print(f"Error menyimpan data produk: {e}")

def display_products_by_platform(platform):
    try:
        conn = connect_db()
        cursor = conn.cursor()
        cursor.execute("""
            SELECT p.product_name, p.nett_price, p.margin_mitra, p.max_reasonable_price, e.product_title
            FROM ecommerce_data e
            JOIN products p ON e.product_id = p.product_id
            WHERE e.platform = %s
        """, (platform,))
        
        rows = cursor.fetchall()
        for row in rows:
            print(f"Nama Produk: {row[0]}")
            print(f"Net Price: {row[1]}")
            print(f"Margin Mitra: {row[2]}")
            print(f"Max Reasonable Price: {row[3]}")
            print(f"Title from Ecommerce Data: {row[4]}")
            print("-" * 30)
        
        cursor.close()
        conn.close()
    except Exception as e:
        print(f"Error menampilkan produk: {e}")
