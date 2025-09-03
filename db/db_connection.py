import mysql.connector

def connect_db():
    return mysql.connector.connect(
        host="localhost",  # Ganti dengan host MySQL Anda
        user="root",       # Ganti dengan username MySQL Anda
        password="password",  # Ganti dengan password MySQL Anda
        database="cpe_price_dashboard"  # Nama database yang Anda buat
    )