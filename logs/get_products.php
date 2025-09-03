<?php
// Mengimpor koneksi database
require_once '../config/db.php';

// Memeriksa apakah parameter 'nama_item', 'referensi', dan 'margin' diberikan
if (!isset($_GET['nama_item']) || !isset($_GET['referensi']) || !isset($_GET['margin'])) {
    echo json_encode(['error' => 'Parameter nama_item, referensi, dan margin diperlukan']);
    exit;
}

$nama_item = $_GET['nama_item']; // Nama produk yang dicari
$referensi = $_GET['referensi']; // Referensi platform (misalnya Tokopedia, Shopee)
$margin = $_GET['margin']; // Margin mitra dalam persen

// Fungsi untuk mencari produk dari database berdasarkan nama item dan referensi
function searchProductFromDatabase($nama_item, $referensi, $conn) {
    $sql = "SELECT * FROM produk WHERE nama_item LIKE ? AND referensi LIKE ? AND jumlah_penjualan > 5 AND ulasan > 5";
    $stmt = $conn->prepare($sql);
    $search_name = "%" . $nama_item . "%";
    $search_referensi = "%" . $referensi . "%";
    $stmt->bind_param("ss", $search_name, $search_referensi);
    $stmt->execute();
    $result = $stmt->get_result();

    // Menyiapkan array untuk menyimpan hasil produk
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'nama_item' => $row['nama_item'],
            'harga_tawaran' => $row['harga_tawaran'],
            'referensi' => $row['referensi'],
            'link_produk' => $row['link_produk'],
            'jumlah_penjualan' => $row['jumlah_penjualan'],
            'ulasan' => $row['ulasan']
        ];
    }

    return $products;
}

// Fungsi untuk menghitung harga wajar berdasarkan margin
function calculateReasonablePrice($harga, $margin) {
    return $harga * (1 + ($margin / 100));
}

// Fungsi untuk mengambil data produk dari e-commerce (misalnya Tokopedia atau Shopee)
function searchProductFromEcommerce($nama_item, $referensi) {
    $products = [];

    // Cek platform dan lakukan pencarian produk
    if ($referensi == 'Shopee') {
        $shopee_url = "https://shopee.co.id/search?keyword=" . urlencode($nama_item);
        $shopee_data = getDataFromUrl($shopee_url);
        $products['shopee'] = $shopee_data;
    } elseif ($referensi == 'Tokopedia') {
        $tokopedia_url = "https://www.tokopedia.com/search?st=product&q=" . urlencode($nama_item);
        $tokopedia_data = getDataFromUrl($tokopedia_url);
        $products['tokopedia'] = $tokopedia_data;
    } elseif ($referensi == 'Blibli') {
        $blibli_url = "https://www.blibli.com/search?searchTerm=" . urlencode($nama_item);
        $blibli_data = getDataFromUrl($blibli_url);
        $products['blibli'] = $blibli_data;
    }

    return $products;
}

// Fungsi untuk mengambil data produk dari URL (scraping atau API)
function getDataFromUrl($url) {
    // Inisialisasi cURL untuk mengambil data dari URL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36");
    $response = curl_exec($ch);
    curl_close($ch);

    // Cek apakah respons valid
    if (!$response) {
        return [
            'error' => 'Tidak dapat mengambil data dari URL.'
        ];
    }

    // Parsing HTML untuk mengekstrak informasi produk
    $dom = new DOMDocument();
    @$dom->loadHTML($response);  // Mengabaikan error saat mem-parsing HTML yang tidak valid

    $xpath = new DOMXPath($dom);

    // Menemukan elemen-elemen yang dibutuhkan menggunakan XPath
    $product_name = extractTextFromXPath($xpath, '//h1[contains(@class, "product-title")]'); // Ganti dengan XPath yang sesuai
    $price = extractTextFromXPath($xpath, '//span[contains(@class, "price")]'); // Ganti dengan XPath yang sesuai
    $rating = extractTextFromXPath($xpath, '//span[contains(@class, "rating")]'); // Ganti dengan XPath yang sesuai
    $link = $url;  // Link produk yang sama dengan URL yang diminta

    // Kembalikan data produk yang ditemukan
    return [
        'product_name' => $product_name ? $product_name : 'Nama Produk Tidak Ditemukan',
        'price' => $price ? $price : 'Harga Tidak Ditemukan',
        'rating' => $rating ? $rating : 'Rating Tidak Ditemukan',
        'link' => $link
    ];
}

// Fungsi untuk mengekstrak teks menggunakan XPath
function extractTextFromXPath($xpath, $query) {
    $nodes = $xpath->query($query);
    return $nodes->length > 0 ? trim($nodes->item(0)->textContent) : null;
}
  
