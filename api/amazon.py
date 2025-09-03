# -*- coding: utf-8 -*-
import undetected_chromedriver as uc
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait as wait
from selenium.common.exceptions import TimeoutException, NoSuchElementException, StaleElementReferenceException
import pandas as pd
import datetime, time, re, random, json
import mysql.connector
from urllib.parse import quote_plus

# ===================== UTIL =====================

def human_sleep(a=0.8, b=1.6):
    time.sleep(random.uniform(a, b))

def parse_price_to_int(s: str):
    """Normalisasi harga Amazon (USD/EU) -> int dibulatkan (major currency)."""
    if not s or not isinstance(s, str):
        return None
    t = s.replace('\xa0', ' ').strip()
    t = re.sub(r'[^\d\.,]', '', t)  # sisakan angka, titik, koma
    if re.search(r'\d+\.\d{3},\d{2}', t):  # format EU: 1.299,99
        t = t.replace('.', '').replace(',', '.')
    else:  # format US: 1,299.99
        t = t.replace(',', '')
    m = re.search(r'(\d+(\.\d+)?)', t)
    if not m:
        digits = re.findall(r'\d+', t)
        return int(''.join(digits)) if digits else None
    try:
        return int(round(float(m.group(1))))
    except:
        return None

# ---- NEW: parser angka "terjual" yang robust (dukung K/M/+, over/more than, last/past) ----
_sold_regex = re.compile(
    r"(?i)\b(?:over|more than|at least|about|around|approx\.?)?\s*"
    r"(\d{1,3}(?:[,\.\s]\d{3})*|\d+(?:\.\d+)?)\s*([kKmM])?\s*\+?\s*"
    r"(?:bought|ordered|purchased)\b.*?(?:past|last)\s*(?:day|days|week|weeks|month|months|year|years)\b"
)

def parse_sold_general(txt: str) -> int:
    if not txt:
        return 0
    m = _sold_regex.search(txt)
    if not m:
        return 0
    num_str, suffix = m.group(1), m.group(2)
    # buang separator ribuan
    num_str = num_str.replace(',', '').replace('.', '').replace(' ', '') if re.search(r'\d{1,3}([,\.\s]\d{3})+', num_str) else num_str
    try:
        base = float(num_str)
    except:
        try:
            base = float(num_str.replace(',', '').replace(' ', ''))
        except:
            return 0
    if suffix:
        s = suffix.lower()
        if s == 'k':
            base *= 1_000
        elif s == 'm':
            base *= 1_000_000
    return int(round(base))

def parse_sold_to_int(s: str):
    """Back-compat: delegasikan ke parser baru."""
    return parse_sold_general(s)

def safe_text(el, by, sel, default="N/A"):
    try:
        return el.find_element(by, sel).text.strip()
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

print("Initializing undetected-chromedriver (Amazon)...")
options = uc.ChromeOptions()
# options.add_argument('--headless=new')  # non-headless lebih stabil utk Amazon
options.add_argument('--disable-gpu')
options.add_argument('--no-sandbox')
options.add_argument('--lang=en-US,en;q=0.9,id;q=0.8')
options.add_argument("--disable-blink-features=AutomationControlled")
options.add_argument("--window-size=1366,900")
options.add_argument("--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36")

driver = uc.Chrome(options=options, use_subprocess=True, version_main=139)
driver.set_page_load_timeout(120)

AMAZON_DOMAIN = "www.amazon.com"
PLATFORM = "Amazon"

try:
    keywords = input("Keywords: ").strip()
    pages = int(input("Berapa halaman yang ingin di-scrape? ").strip() or "1")
    if pages < 1:
        pages = 1

    search_url = f"https://{AMAZON_DOMAIN}/s?k={quote_plus(keywords)}"
    driver.get(search_url)
    human_sleep(2.0, 3.0)

    # Cookies (jika ada)
    for xp in [
        "//input[@id='sp-cc-accept']",
        "//button[@id='a-autoid-0' and contains(., 'Accept')]",
        "//button[contains(., 'Accept Cookies')]",
        "//input[@name='accept']"
    ]:
        try:
            btn = wait(driver, 3).until(EC.element_to_be_clickable((By.XPATH, xp)))
            js_click(driver, btn); human_sleep()
        except Exception:
            pass

    product_data = []

    def gentle_scroll(steps=6, pause=(0.6, 1.0)):
        for _ in range(steps):
            driver.execute_script("window.scrollBy(0, Math.floor(window.innerHeight*0.9));")
            human_sleep(*pause)

    def wait_results_root(timeout=25):
        def _any(d):
            try:
                main = d.find_element(By.CSS_SELECTOR, "div.s-main-slot.s-result-list")
                items = main.find_elements(By.XPATH, ".//div[@data-component-type='s-search-result' and @data-asin]")
                return len(items) > 0
            except Exception:
                return False
        wait(driver, timeout).until(_any)

    def find_cards():
        wait_results_root(timeout=30)
        gentle_scroll(steps=8)
        cards = driver.find_elements(
            By.XPATH,
            "//div[@data-component-type='s-search-result' and @data-asin and string-length(@data-asin)>0]"
        )
        uniq, seen = [], set()
        for c in cards:
            asin = (c.get_attribute("data-asin") or "").strip()
            if asin and asin not in seen:
                seen.add(asin)
                uniq.append(c)
        if not uniq:
            print("GAGAL: Tidak menemukan kartu produk. Menyimpan dump...")
            debug_dump(driver, tag='no_cards')
        else:
            print(f"Menemukan {len(uniq)} kartu produk dengan ASIN.")
        return uniq

    # ---- NEW: extractor harga yang aman (hindari old price) ----
    def extract_price(card):
        # 1) a-offscreen di dalam a-price (bukan a-text-price)
        try:
            el = card.find_element(By.XPATH, ".//span[contains(@class,'a-price') and not(contains(@class,'a-text-price'))]//span[@class='a-offscreen']")
            if el.text.strip():
                return el.text.strip()
        except Exception:
            pass
        # 2) whole + fraction
        try:
            box = card.find_element(By.XPATH, ".//span[contains(@class,'a-price') and not(contains(@class,'a-text-price'))]")
            whole = ""
            fraction = ""
            try:
                whole = box.find_element(By.CLASS_NAME, "a-price-whole").text.strip()
            except Exception:
                pass
            try:
                fraction = box.find_element(By.CLASS_NAME, "a-price-fraction").text.strip()
            except Exception:
                pass
            if whole:
                whole_clean = re.sub(r'[^\d]', '', whole)
                price_join = f"${whole_clean}"
                if fraction:
                    price_join += f".{fraction}"
                return price_join
        except Exception:
            pass
        # 3) fallback: a-offscreen umum
        try:
            any_off = card.find_element(By.XPATH, ".//span[@class='a-offscreen']")
            t = any_off.text.strip()
            if t:
                return t
        except Exception:
            pass
        return ""

    # ---- NEW: extractor terjual yang kuat (aria-label -> teks -> full card) ----
    def extract_sold(card):
        # 1) Cari aria-label yang menyebut bought/ordered/purchased
        try:
            el = card.find_element(By.XPATH,
                ".//*[@aria-label and (contains(translate(@aria-label,'BOUGHTORDEREDPURCHASED','boughtorderedpurchased'),'bought') "
                "or contains(translate(@aria-label,'BOUGHTORDEREDPURCHASED','boughtorderedpurchased'),'ordered') "
                "or contains(translate(@aria-label,'BOUGHTORDEREDPURCHASED','boughtorderedpurchased'),'purchased'))]"
            )
            aria = (el.get_attribute("aria-label") or "").strip()
            n = parse_sold_general(aria)
            if n > 0:
                return aria, n
        except Exception:
            pass
        # 2) Cari teks langsung dengan variasi kata
        for xp in [
            ".//span[contains(translate(.,'BOUGHTORDEREDPURCHASED','boughtorderedpurchased'),'bought')]",
            ".//span[contains(translate(.,'BOUGHTORDEREDPURCHASED','boughtorderedpurchased'),'ordered')]",
            ".//span[contains(translate(.,'BOUGHTORDEREDPURCHASED','boughtorderedpurchased'),'purchased')]",
            ".//*[contains(translate(.,'BOUGHTORDEREDPURCHASED','boughtorderedpurchased'),'bought') and contains(translate(.,'PASTLAST','pastlast'),'past')]",
        ]:
            try:
                t = card.find_element(By.XPATH, xp).text.strip()
                n = parse_sold_general(t)
                if n > 0:
                    return t, n
            except Exception:
                pass
        # 3) Fallback: scan seluruh teks kartu
        all_text = (card.text or "").strip()
        n = parse_sold_general(all_text)
        if n > 0:
            # potong kalimat yang relevan saja untuk sold_raw
            m = _sold_regex.search(all_text)
            sold_raw = m.group(0) if m else all_text
            return sold_raw.strip(), n
        return "N/A", 0

    def extract_data():
        cards = find_cards()
        if not cards:
            return 0
        count = 0
        for card in cards:
            try:
                asin = (card.get_attribute("data-asin") or "").strip()
                name = safe_text(card, By.XPATH, ".//h2//span", default="")
                # link
                details_link = ""
                try:
                    a = card.find_element(By.XPATH, ".//h2//a")
                    details_link = (a.get_attribute('href') or '').strip()
                except Exception:
                    if asin:
                        details_link = f"https://{AMAZON_DOMAIN}/dp/{asin}"
                # harga & rating
                price_raw = extract_price(card)
                rating = safe_text(card, By.XPATH, ".//span[@class='a-icon-alt']", default="N/A")
                # ---- pakai extractor sold baru ----
                sold_raw, sold_num = extract_sold(card)
                location = "Online"

                if not (asin or (details_link and name)):
                    continue

                # currency symbol (opsional)
                currency_symbol = ""
                mcur = re.search(r'([€£¥₹]|US?\$|C\$|A\$)', price_raw)
                if mcur:
                    currency_symbol = mcur.group(1)

                product_data.append({
                    'platform': PLATFORM,
                    'name': name,
                    'price_raw': price_raw,
                    'price_num': parse_price_to_int(price_raw) if price_raw else None,
                    'currency': currency_symbol or 'USD',
                    'location': location,
                    'rating': rating,
                    'sold_raw': sold_raw,
                    'sold_num': sold_num,
                    'details_link': details_link or (f"https://{AMAZON_DOMAIN}/dp/{asin}" if asin else "")
                })
                count += 1
            except StaleElementReferenceException:
                continue
            except Exception:
                continue
        return count

    def go_next_page():
        try:
            driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
            human_sleep(0.8, 1.3)
            next_btn = wait(driver, 5).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "a.s-pagination-next")))
            if "s-pagination-disabled" in (next_btn.get_attribute("class") or ""):
                return False
            js_click(driver, next_btn); human_sleep(1.8, 2.5)
            return True
        except Exception:
            try:
                next_btn = driver.find_element(By.XPATH, "//a[@rel='next' or contains(@aria-label,'Next')]")
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
            print("Tidak ada produk terdeteksi di halaman 1. Cek debug_no_cards.html/png & coba perbesar timeout / non-headless.")
            break

        if product_data:
            seen, unique = set(), []
            for r in product_data:
                key = r.get('details_link') or (r.get('name','') + '|' + r.get('price_raw',''))
                if key not in seen:
                    seen.add(key); unique.append(r)
            product_data = unique
        print(f"Total sementara: {len(product_data)} item (dedup).")

        if page_num == pages:
            print("Scraping selesai sesuai jumlah halaman yang diminta.")
            break

        print("Mencoba lanjut (Next Page)...")
        if not go_next_page():
            print("Tidak ada tombol untuk lanjut. Selesai.")
            break

    # ===================== FILTER, SORT, and SAVE =====================
    if product_data:
        print(f"\n--- Memproses {len(product_data)} total produk yang di-scrape ---")
        filtered_products = [
            p for p in product_data
            if p.get('sold_num', 0) > 5 or (p.get('rating') and p.get('rating') != 'N/A')
        ]
        print(f"→ Ditemukan {len(filtered_products)} produk setelah filter.")

        sorted_products = sorted(filtered_products, key=lambda p: p.get('price_num', 0) or 0, reverse=True)
        print("→ Produk diurutkan berdasarkan harga tertinggi.")

        top_3_products = sorted_products[:3]
        print(f"→ Mengambil top {len(top_3_products)} produk.")

        if not top_3_products:
            print("\nTidak ada data yang cocok dengan kriteria filter.")
        else:
            now_str = datetime.datetime.today().strftime('%Y-%m-%d_%H-%M-%S')
            safe_kw = re.sub(r'[^0-9a-zA-Z_-]+', '_', keywords)
            base_filename = f'amazon_TOP3_{safe_kw}_{now_str}'

            # CSV
            df = pd.DataFrame(top_3_products)
            df = df.rename(columns={'sold_raw': 'sold', 'price_raw': 'price'})
            if 'sold_num' in df.columns:
                df = df.drop(columns=['sold_num'])
            csv_filename = f'{base_filename}.csv'
            df.to_csv(csv_filename, index=False, encoding='utf-8-sig')
            print(f"\n✅ Data CSV berhasil disimpan: {csv_filename} | {len(df)} baris")

            # JSON
            json_filename = f'{base_filename}.json'
            with open(json_filename, 'w', encoding='utf-8') as f:
                json.dump(top_3_products, f, ensure_ascii=False, indent=4)
            print(f"✅ Data JSON berhasil disimpan: {json_filename} | {len(top_3_products)} item")

            # MySQL
            try:
                DB_CFG = {
                    "host": "127.0.0.1",
                    "user": "root",
                    "password": "",   # ganti
                    "database": "cpe_price_dashboard"
                }
                conn = mysql.connector.connect(**DB_CFG)
                cur = conn.cursor()

                # cek kolom platform
                cur.execute("SHOW COLUMNS FROM products LIKE 'platform';")
                has_platform_col = cur.fetchone() is not None

                if has_platform_col:
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
                    rows = []
                    now_dt = datetime.datetime.now()
                    for p in top_3_products:
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
        driver.quit()
    except Exception:
        pass
