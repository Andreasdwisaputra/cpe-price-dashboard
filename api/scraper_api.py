from flask import Flask, request, jsonify
from flask_cors import CORS
import requests
from bs4 import BeautifulSoup
import re
import concurrent.futures
from flask_mysqldb import MySQL

app = Flask(__name__)
CORS(app)

# Konfigurasi MySQL, ganti dengan kredensial Anda
app.config['MYSQL_HOST'] = 'localhost'
app.config['MYSQL_USER'] = 'root'
app.config['MYSQL_PASSWORD'] = '' # Ganti dengan password MySQL Anda
app.config['MYSQL_DB'] = 'cpe_price_dashboard' # Ganti dengan nama database Anda

mysql = MySQL(app)

def clean_price(price_text):
    if price_text is None: return 0
    cleaned_price = re.sub(r'\D', '', price_text)
    return int(cleaned_price) if cleaned_price else 0

def scrape_ecommerce(site, item_name):
    headers = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36'}
    search_query = requests.utils.quote(item_name)
    
    sites_config = {
        "Tokopedia": {
            "url": f"https://www.tokopedia.com/search?q={search_query}&st=product",
            "selectors": {
                "card": "div.css-llwpbs",
                "name": "div.prd_link-product-name",
                "price": "div.prd_link-product-price",
                "link": "a.pcv3__info-content"
            }
        },
        "Shopee": {
            "url": f"https://shopee.co.id/search?keyword={search_query}",
            "selectors": {
                "card": "div.shopee-search-item-result__item",
                "name": "div[data-sqe='name']",
                "price": "span._29R_un",
                "link": "a"
            }
        },
        "Blibli": {
            "url": f"https://www.blibli.com/jual/{search_query}",
            "selectors": {
                "card": "div.product-card-new",
                "name": "div.blp-card__title",
                "price": "div.blp-card__price-new",
                "link": "a.blp-link"
            }
        }
    }
    
    config = sites_config.get(site)
    if not config: return None
    
    print(f"Mencoba scraping {site} untuk '{item_name}'...")
    try:
        response = requests.get(config["url"], headers=headers, timeout=15)
        response.raise_for_status()
        soup = BeautifulSoup(response.text, 'html.parser')
        
        first_product = soup.select_one(config["selectors"]["card"])
        if not first_product: 
            print(f"Kartu produk tidak ditemukan di {site}")
            return None

        name_element = first_product.select_one(config["selectors"]["name"])
        price_element = first_product.select_one(config["selectors"]["price"])
        link_element = first_product.select_one(config["selectors"]["link"])
        
        if not all([name_element, price_element, link_element]): 
            print(f"Salah satu detail (nama/harga/link) tidak ditemukan di {site}")
            return None
            
        product_link = link_element.get('href', '')
        if site == "Shopee" and not product_link.startswith('http'):
            product_link = "https://shopee.co.id" + product_link

        print(f"Berhasil scrape dari {site}!")
        return {
            "nama_produk": name_element.text.strip(),
            "harga_terendah": clean_price(price_element.text.strip()),
            "uri_produk": product_link,
            "referensi": site
        }
    except Exception as e:
        print(f"Error scraping {site}: {e}")
        return None

def insert_to_db(data):
    try:
        cur = mysql.connection.cursor()
        query = """
            INSERT INTO product (nama_produk, harga_sumber, referensi, uri_produk, created_at)
            VALUES (%s, %s, %s, %s, NOW())
        """
        cur.execute(query, (
            data['nama_produk'],
            data['harga_terendah'],
            data['referensi'],
            data['uri_produk']
        ))
        mysql.connection.commit()
        cur.close()
        print("Data berhasil dimasukkan ke database.")
        return True
    except Exception as e:
        print(f"Error saat memasukkan data ke database: {e}")
        return False

@app.route('/scrape', methods=['POST'])
def scrape_product():
    nama_item = request.form.get('nama_item')
    referensi_str = request.form.get('referensi')
    margin_mitra = float(request.form.get('margin_mitra', 0))

    if not nama_item or not referensi_str:
        return jsonify({"error": "Nama item dan referensi wajib diisi."}), 400

    referensi_list = referensi_str.split(',')
    all_results = []
    
    with concurrent.futures.ThreadPoolExecutor() as executor:
        futures = {executor.submit(scrape_ecommerce, ref, nama_item) for ref in referensi_list}
        for future in concurrent.futures.as_completed(futures):
            result = future.result()
            if result:
                all_results.append(result)

    if not all_results:
        return jsonify({"error": "Produk tidak ditemukan di semua referensi yang dipilih."}), 404

    best_product = min(all_results, key=lambda x: x['harga_terendah'])
    harga_terendah = best_product['harga_terendah']
    
    # Menghitung harga wajar dan harga tawaran
    harga_wajar = harga_terendah
    harga_tawaran = harga_wajar / (1 - (margin_mitra / 100)) if margin_mitra < 100 else 0
    
    # Memperbarui data untuk dikirim kembali dan dimasukkan ke DB
    best_product['harga_wajar'] = harga_wajar
    best_product['harga_tawaran'] = harga_tawaran

    # Memasukkan data ke database
    insert_to_db(best_product)

    response_data = {
        "nama_produk": best_product['nama_produk'],
        "harga_sumber": best_product['harga_terendah'],
        "harga_wajar": round(best_product['harga_wajar']),
        "referensi": best_product['referensi'],
        "uri_produk": best_product['uri_produk'],
        "jumlah_penjualan": "N/A", "ulasan": "N/A"
    }
    
    return jsonify([response_data])

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
