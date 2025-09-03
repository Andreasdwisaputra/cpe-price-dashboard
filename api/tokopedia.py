# -*- coding: utf-8 -*-
import os, sys, re, time, json, random, datetime, tempfile, shutil, traceback
import pandas as pd
import undetected_chromedriver as uc
from urllib.parse import quote_plus
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait as wait
from selenium.webdriver.common.keys import Keys
from selenium.common.exceptions import TimeoutException, NoSuchElementException, StaleElementReferenceException
import mysql.connector  # untuk MySQL

# ===================== UTIL =====================
def human_sleep(a=0.8, b=1.6):
    time.sleep(random.uniform(a, b))

def parse_price_to_int(s: str):
    """Konversi 'Rp 12.345' / '15 rb' / '1,2 jt' -> int rupiah."""
    if not s or not isinstance(s, str):
        return None
    sl = s.lower().replace('rp', '').strip()
    sl = sl.replace(' ', '')
    # dukung format "1,2jt" / "1.2jt"
    m_jt = re.search(r'(\d+[.,]?\d*)\s*jt', sl)
    if m_jt:
        return int(round(float(m_jt.group(1).replace(',', '.')) * 1_000_000))
    m_rb = re.search(r'(\d+[.,]?\d*)\s*rb', sl)
    if m_rb:
        return int(round(float(m_rb.group(1).replace(',', '.')) * 1_000))
    digits = re.findall(r'\d+', sl.replace('.', '').replace(',', ''))
    return int(''.join(digits)) if digits else None

def parse_sold_to_int(s: str):
    """'Terjual 5 rb+' -> 5000, '100 terjual' -> 100"""
    if not s or not isinstance(s, str):
        return 0
    s_lower = s.lower()
    try:
        m = re.search(r'(\d+[.,]?\d*)', s_lower)
        if not m:
            return 0
        num = float(m.group(1).replace(',', '.'))
        if 'rb' in s_lower or 'ribu' in s_lower:
            num *= 1000
        return int(round(num))
    except Exception:
        return 0

def safe_text(el, by, sel, default="N/A"):
    try:
        t = el.find_element(by, sel).text
        return t.strip() if isinstance(t, str) else default
    except (NoSuchElementException, StaleElementReferenceException):
        return default

def js_click(driver, el):
    try:
        driver.execute_script("arguments[0].click();", el); return True
    except Exception:
        try:
            el.click(); return True
        except Exception:
            return False

def debug_dump(driver, tag='page'):
    try:
        with open(f'debug_{tag}.html', 'w', encoding='utf-8') as f:
            f.write(driver.page_source)
        driver.save_screenshot(f'debug_{tag}.png')
        print(f"[debug] saved debug_{tag}.html & debug_{tag}.png")
    except Exception:
        pass

# ===================== MAIN =====================
PLATFORM = "Tokopedia"

print("Initializing undetected-chromedriver (Tokopedia)...")

RUN_FROM_PHP = os.getenv("RUN_FROM_PHP", "0") == "1"  # diset di proc_open PHP
profile_dir = None
driver = None

try:
    options = uc.ChromeOptions()

    # Headless WAJIB saat dipanggil dari PHP/Apache (tanpa desktop session)
    if RUN_FROM_PHP:
        options.add_argument('--headless=new')

    # Flag stabil di environment service/CI
    options.add_argument('--no-sandbox')
    options.add_argument('--disable-gpu')
    options.add_argument('--disable-dev-shm-usage')
    options.add_argument('--disable-extensions')
    options.add_argument('--disable-blink-features=AutomationControlled')
    options.add_argument('--remote-debugging-port=0')
    options.add_argument('--window-size=1366,900')
    options.add_argument('--lang=id-ID')
    options.add_argument('--no-first-run')
    options.add_argument('--no-default-browser-check')
    options.add_argument("--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36")

    # Profil Chrome sementara yang writable oleh Apache
    base_tmp = os.environ.get("TMP") or os.environ.get("TEMP") or os.path.join(os.getcwd(), "tmp")
    os.makedirs(base_tmp, exist_ok=True)
    profile_dir = tempfile.mkdtemp(prefix="uc_prof_", dir=base_tmp)
    options.add_argument(f"--user-data-dir={profile_dir}")
    options.add_argument(f"--data-path={profile_dir}")
    options.add_argument(f"--disk-cache-dir={os.path.join(profile_dir, 'cache')}")

    # (Opsional) set binary Chrome manual kalau perlu
    chrome_bin = os.environ.get("CHROME_BIN")
    if not chrome_bin:
        cands = [
            r"C:\Program Files\Google\Chrome\Application\chrome.exe",
            r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
        ]
        for c in cands:
            if os.path.isfile(c):
                chrome_bin = c
                break
    if chrome_bin and os.path.isfile(chrome_bin):
        options.binary_location = chrome_bin

    driver = uc.Chrome(options=options, use_subprocess=True, version_main=139)
    driver.set_page_load_timeout(90)

except Exception:
    # KUNCI: keluarkan stack trace ke stderr → akan tampil di UI lewat PHP
    traceback.print_exc(file=sys.stderr)
    sys.exit(120)

try:
    # ==== Input ====
    keywords = input("Keywords: ").strip()
    pages = int(input("Berapa halaman yang ingin di-scrape? ").strip() or "1")
    if pages < 1:
        pages = 1

    # ==== Ke hasil pencarian ====
    search_url = f"https://www.tokopedia.com/search?st=product&q={quote_plus(keywords)}"
    driver.get(search_url)
    human_sleep(2.0, 3.0)

    # Tutup popup (best-effort)
    for xp in [
        "//button[normalize-space()='Tutup']",
        "//button[normalize-space()='Nanti saja']",
        "//button[contains(., 'Tolak semua')]",
    ]:
        try:
            btn = wait(driver, 3).until(EC.element_to_be_clickable((By.XPATH, xp)))
            js_click(driver, btn); human_sleep()
        except Exception:
            pass

    product_data = []

    def gentle_scroll(steps=8, pause=(0.6, 1.1)):
        for _ in range(steps):
            driver.execute_script("window.scrollBy(0, Math.floor(window.innerHeight*0.9));")
            human_sleep(*pause)

    def wait_results_root(timeout=25):
        def _any(d):
            sels = [
                (By.XPATH, "//a[@data-testid='lnkProductContainer']"),
                (By.XPATH, "//span[@data-testid='spnSRPProdName']"),
                (By.XPATH, "//img[@alt='product-image']"),
                (By.XPATH, "//div[contains(@class,'css-5wh65g')]"),
            ]
            for by, sel in sels:
                if d.find_elements(by, sel):
                    return True
            return False
        wait(driver, timeout).until(_any)

    def find_cards():
        """Kembalikan list <a> produk unik."""
        wait_results_root(timeout=25)
        gentle_scroll(steps=8)

        anchors, tried = [], []

        tried.append("lnkProductContainer")
        anchors += driver.find_elements(By.XPATH, "//a[@data-testid='lnkProductContainer']")

        tried.append("ancestor-of-name")
        anchors += driver.find_elements(By.XPATH, "//span[@data-testid='spnSRPProdName']/ancestor::a[1]")

        tried.append("ancestor-of-image")
        anchors += driver.find_elements(By.XPATH, "//img[@alt='product-image']/ancestor::a[1]")

        tried.append("css-5wh65g-container")
        cards = driver.find_elements(By.XPATH, "//div[contains(@class,'css-5wh65g')]")
        for c in cards:
            try:
                a = c.find_element(By.XPATH, ".//a[1]")
                anchors.append(a)
            except Exception:
                continue

        uniq, seen = [], set()
        for a in anchors:
            href = (a.get_attribute('href') or '').strip()
            if href and href not in seen:
                seen.add(href)
                uniq.append(a)

        if not uniq:
            print("GAGAL: Tidak menemukan kartu produk. Menyimpan dump...")
            debug_dump(driver, tag='no_cards')
        else:
            print(f"Menemukan {len(uniq)} anchor produk (selectors: {', '.join(tried)})")
        return uniq

    def extract_data():
        cards = find_cards()
        if not cards:
            return 0
        count = 0
        for card in cards:
            try:
                details_link = (card.get_attribute('href') or '').strip()

                # Nama
                name = safe_text(card, By.XPATH, ".//span[@data-testid='spnSRPProdName']", default="").strip()
                if not name:
                    raw_text = (card.text or "").strip()
                    lines = [ln.strip() for ln in raw_text.split('\n') if ln.strip()]
                    def looks_like_name(ln):
                        return not any(x in ln.lower() for x in ['rp', '⭐', 'terjual', 'diskon', '%'])
                    name = next((ln for ln in lines if looks_like_name(ln)), (lines[0] if lines else ""))

                # Harga
                price_raw = safe_text(card, By.XPATH, ".//span[@data-testid='spnSRPProdPrice']", default="")
                if not price_raw:
                    m = re.search(r'Rp[0-9\.\, ]+[a-zA-Z]*', (card.text or ""))
                    price_raw = m.group(0) if m else ""

                # Lokasi
                location = safe_text(card, By.XPATH, ".//span[@data-testid='spnSRPProdTabShopLocation']", default="N/A")

                # Terjual
                sold = "N/A"
                try:
                    sold_el = card.find_element(By.XPATH, ".//span[contains(translate(., 'TERJUAL', 'terjual'), 'terjual')]")
                    sold = sold_el.text.strip()
                except Exception:
                    pass

                # Rating
                rating = safe_text(card, By.XPATH, ".//span[@data-testid='spnSRPProdRating']", default="N/A")

                if not details_link and not name:
                    continue

                product_data.append({
                    'platform': PLATFORM,
                    'name': name,
                    'price_raw': price_raw,
                    'price_num': parse_price_to_int(price_raw) if price_raw else None,
                    'location': location,
                    'rating': rating,
                    'sold_raw': sold,
                    'sold_num': parse_sold_to_int(sold),
                    'details_link': details_link
                })
                count += 1
            except StaleElementReferenceException:
                continue
            except Exception:
                continue
        return count

    def try_load_more():
        """Prioritaskan tombol 'Muat Lebih Banyak' jika ada (lazy load)."""
        try:
            driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
            human_sleep(0.8, 1.2)
            btn = wait(driver, 3).until(
                EC.element_to_be_clickable((By.XPATH, "//button[.//span[contains(normalize-space(),'Muat Lebih Banyak')]]"))
            )
            js_click(driver, btn); human_sleep(1.5, 2.4)
            return True
        except Exception:
            return False

    def go_next_page():
        if try_load_more():
            return True
        try:
            driver.execute_script("window.scrollTo(0, document.body.scrollHeight);"); human_sleep(0.5, 0.9)
            next_btn = wait(driver, 5).until(
                EC.element_to_be_clickable((By.CSS_SELECTOR, "[aria-label='Laman berikutnya']"))
            )
            js_click(driver, next_btn); human_sleep(1.6, 2.2)
            return True
        except TimeoutException:
            try:
                next_btn = driver.find_element(By.XPATH, "//a[@rel='next' or contains(.,'Berikutnya') or contains(.,'Next')]")
                js_click(driver, next_btn); human_sleep(1.6, 2.2)
                return True
            except Exception:
                return False

    # ===================== LOOP =====================
    for page_num in range(1, pages + 1):
        print(f"\n--- Scraping Page {page_num}/{pages} ---")
        grabbed = extract_data()
        print(f"→ Ditangkap: {grabbed} item")

        if grabbed == 0 and page_num == 1:
            print("Tidak ada produk terdeteksi. Cek debug_no_cards.html/png & coba perbesar timeout.")
            break

        if product_data:
            seen, unique = set(), []
            for r in product_data:
                key = r.get('details_link') or (r.get('name', '') + '|' + r.get('price_raw', ''))
                if key not in seen:
                    seen.add(key); unique.append(r)
            product_data = unique
        print(f"Total sementara: {len(product_data)} item (dedup).")

        if page_num == pages:
            print("Scraping selesai sesuai jumlah halaman yang diminta.")
            break

        print("Mencoba lanjut (Muat Lebih Banyak / Laman berikutnya)...")
        if not go_next_page():
            print("Tidak ada tombol untuk lanjut. Selesai.")
            break

    # ===================== FILTER, SORT, and SAVE =====================
    if product_data:
        print(f"\n--- Memproses {len(product_data)} total produk yang di-scrape ---")

        # 1) Filter: terjual > 5 ATAU ada rating
        filtered_products = [
            p for p in product_data
            if p.get('sold_num', 0) > 5 or (p.get('rating') and p.get('rating') != 'N/A')
        ]
        print(f"→ Ditemukan {len(filtered_products)} produk setelah filter.")

        # 2) Sort: harga tertinggi
        sorted_products = sorted(filtered_products, key=lambda p: p.get('price_num', 0) or 0, reverse=True)
        print("→ Produk diurutkan berdasarkan harga tertinggi.")

        # 3) Ambil top 3
        top_3_products = sorted_products[:3]
        print(f"→ Mengambil top {len(top_3_products)} produk.")

        if not top_3_products:
            print("\nTidak ada data yang cocok dengan kriteria filter.")
        else:
            now_str = datetime.datetime.today().strftime('%Y-%m-%d_%H-%M-%S')
            safe_kw = re.sub(r'[^0-9a-zA-Z_-]+', '_', keywords)
            base_filename = f'tokopedia_TOP3_{safe_kw}_{now_str}'

            # CSV
            df = pd.DataFrame(top_3_products)
            df = df.rename(columns={'sold_raw': 'sold', 'price_raw': 'price'})
            if 'sold_num' in df.columns:
                df = df.drop(columns=['sold_num'])
            csv_filename = f'{base_filename}.csv'
            df.to_csv(csv_filename, index=False, encoding='utf-8-sig')
            print(f"\n✅ Data CSV berhasil disimpan: {csv_filename} | {len(df)} baris")

            # JSON (INI YANG DICARI PHP)
            json_filename = f'{base_filename}.json'
            with open(json_filename, 'w', encoding='utf-8') as f:
                json.dump(top_3_products, f, ensure_ascii=False, indent=4)
            print(f"✅ Data JSON berhasil disimpan: {json_filename} | {len(top_3_products)} item")

            # MySQL INSERT/UPDATE
            try:
                DB_CFG = {
                    "host": "127.0.0.1",
                    "user": "root",
                    "password": "",   # ganti sesuai
                    "database": "cpe_price_dashboard"
                }
                conn = mysql.connector.connect(**DB_CFG)
                cur = conn.cursor()

                # cek apakah ada kolom 'platform'
                cur.execute("SHOW COLUMNS FROM products LIKE 'platform';")
                has_platform = cur.fetchone() is not None

                if has_platform:
                    sql = """
                    INSERT INTO products
                    (platform, name, price_raw, price_num, location, rating, sold_raw, sold_num, details_link, scraped_at)
                    VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                    ON DUPLICATE KEY UPDATE
                      platform=VALUES(platform),
                      price_raw=VALUES(price_raw),
                      price_num=VALUES(price_num),
                      location=VALUES(location),
                      rating=VALUES(rating),
                      sold_raw=VALUES(sold_raw),
                      sold_num=VALUES(sold_num),
                      scraped_at=VALUES(scraped_at)
                    """
                else:
                    sql = """
                    INSERT INTO products
                    (name, price_raw, price_num, location, rating, sold_raw, sold_num, details_link, scraped_at)
                    VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s)
                    ON DUPLICATE KEY UPDATE
                      price_raw=VALUES(price_raw),
                      price_num=VALUES(price_num),
                      location=VALUES(location),
                      rating=VALUES(rating),
                      sold_raw=VALUES(sold_raw),
                      sold_num=VALUES(sold_num),
                      scraped_at=VALUES(scraped_at)
                    """

                rows = []
                now_dt = datetime.datetime.now()
                for p in top_3_products:
                    if has_platform:
                        rows.append((
                            p.get('platform', PLATFORM),
                            p.get('name'),
                            p.get('price_raw'),
                            p.get('price_num'),
                            p.get('location'),
                            p.get('rating'),
                            p.get('sold_raw'),
                            p.get('sold_num'),
                            p.get('details_link'),
                            now_dt
                        ))
                    else:
                        rows.append((
                            p.get('name'),
                            p.get('price_raw'),
                            p.get('price_num'),
                            p.get('location'),
                            p.get('rating'),
                            p.get('sold_raw'),
                            p.get('sold_num'),
                            p.get('details_link'),
                            now_dt
                        ))

                if rows:
                    cur.executemany(sql, rows)
                    conn.commit()
                    print(f"✅ MySQL: {len(rows)} baris diproses (insert/update).")

            except Exception as db_err:
                print(f"⚠️ Gagal insert MySQL: {db_err}")
            finally:
                try:
                    cur.close(); conn.close()
                except Exception:
                    pass
    else:
        print("\nTidak ada data yang berhasil di-scrape.")

except Exception as e:
    print(f"\nFatal error: {e}")
    debug_dump(driver, tag='fatal_error')

finally:
    try:
        if driver:
            driver.quit()
    except Exception:
        pass
    # bersihkan profil sementara
    if profile_dir:
        shutil.rmtree(profile_dir, ignore_errors=True)
