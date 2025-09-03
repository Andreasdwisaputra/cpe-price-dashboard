<?php
// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Demo: set user id (di real app: set saat login)
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'Pengguna Demo';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Input Data & Scraper Produk</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #F4F6F9; }
    .hidden { display: none; }
    .spinner-border { width: 3rem; height: 3rem; }
    .card-body a { word-break: break-all; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="#">Product Scraper</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link" href="#">Selamat Datang, <?php echo htmlspecialchars($username); ?>!</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-5 mb-5">
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="card-title mb-4">Input Data Produk</h1>

      <form id="searchForm">
        <div class="mb-3">
          <label for="nama_item" class="form-label">Nama Item</label>
          <input type="text" class="form-control" id="nama_item" name="nama_item" placeholder="Contoh: laptop gaming" required>
        </div>

        <div class="mb-3">
          <!-- FIX: jangan pakai for="referensi" karena tidak ada id "referensi" -->
          <label class="form-label">Referensi Marketplace</label>
          <div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="referensi[]" value="Tokopedia" id="ref_tokopedia" checked>
              <label class="form-check-label" for="ref_tokopedia">Tokopedia</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="referensi[]" value="Shopee" id="ref_shopee" disabled>
              <label class="form-check-label" for="ref_shopee">Shopee (Segera)</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="referensi[]" value="Blibli" id="ref_blibli" disabled>
              <label class="form-check-label" for="ref_blibli">Blibli (Segera)</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="referensi[]" value="Amazon" id="ref_amazon" checked>
              <label class="form-check-label" for="ref_amazon">Amazon</label>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label for="margin_mitra" class="form-label">Margin Mitra (%)</label>
          <input type="number" class="form-control" id="margin_mitra" name="margin_mitra" value="10" required min="0" max="100">
        </div>

        <div class="mb-3">
          <label for="pages" class="form-label">Jumlah Halaman</label>
          <input type="number" class="form-control" id="pages" name="pages" value="1" min="1" max="10">
        </div>

        <button type="submit" class="btn btn-primary w-100">Cari Produk Teratas</button>
      </form>
    </div>
  </div>

  <div id="loading-message" class="text-center mt-4 hidden">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-2 text-secondary">Harap tunggu, proses scraping bisa memakan waktu hingga 1–2 menit...</p>
  </div>

  <div id="results-container" class="mt-4"></div>
  <div id="error-message-container" class="alert alert-danger mt-4 hidden"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('searchForm');
  const resultsContainer = document.getElementById('results-container');
  const loadingMessage = document.getElementById('loading-message');
  const errorMessageContainer = document.getElementById('error-message-container');

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const namaItem = document.getElementById('nama_item').value;
    const marginMitra = document.getElementById('margin_mitra').value;

    const referensiCheckboxes = document.querySelectorAll('input[name="referensi[]"]:checked');
    if (referensiCheckboxes.length === 0) {
      alert('Pilih setidaknya satu referensi marketplace.');
      return;
    }
    const referensi = Array.from(referensiCheckboxes).map(cb => cb.value).join(',');

    const pagesInput = document.getElementById('pages');
    const pages = pagesInput ? Math.max(1, parseInt(pagesInput.value, 10) || 1) : 1;

    // Reset UI
    resultsContainer.innerHTML = '';
    errorMessageContainer.classList.add('hidden');
    loadingMessage.classList.remove('hidden');
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    try {
      // FIX PATH: file ini berada di /views/, endpoint ada di /api/
      const apiUrl = '../api/scrape.php';

      const response = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          nama_item: namaItem,
          margin_mitra: marginMitra,
          referensi: referensi,
          pages: pages
        })
      });

      // Baca sebagai teks dulu (antisipasi HTML error)
      const resText = await response.text();

      let data = null;
      try { data = JSON.parse(resText); } catch { data = null; }

      if (!response.ok) {
        const msg = (data && data.error) ? data.error : resText.slice(0, 700);
        throw new Error(msg);
      }
      if (!data) {
        throw new Error('Server mengembalikan non-JSON:\n' + resText.slice(0, 700));
      }
      if (data.error) {
        throw new Error(data.error);
      }

      displayResults(data, namaItem, marginMitra);
    } catch (err) {
      errorMessageContainer.textContent = 'Terjadi kesalahan: ' + err.message;
      errorMessageContainer.classList.remove('hidden');
      console.error('Fetch error:', err);
    } finally {
      loadingMessage.classList.add('hidden');
      submitBtn.disabled = false;
    }
  });

  function displayResults(products, itemName, margin) {
    resultsContainer.innerHTML = '';

    if (!Array.isArray(products) || products.length === 0) {
      resultsContainer.innerHTML = `<div class="alert alert-warning">Tidak ditemukan produk yang cocok untuk "${itemName}".</div>`;
      return;
    }

    const title = document.createElement('h2');
    title.className = 'mb-3';
    title.textContent = `Top ${products.length} Produk untuk "${itemName}"`;
    resultsContainer.appendChild(title);

    products.forEach(product => {
      const hargaWajar = product.price_num || 0;
      const hargaTawaran = hargaWajar * (1 + (parseInt(margin, 10) / 100));

      const card = document.createElement('div');
      card.className = 'card mb-3 shadow-sm';
      card.innerHTML = `
        <div class="card-body">
          <h5 class="card-title">${product.name || '-'}</h5>
          <p class="card-text mb-1"><strong>Referensi:</strong> ${product.platform || '-'}</p>
          <p class="card-text mb-1"><strong>Harga Wajar:</strong> <span class="text-success fw-bold">Rp ${hargaWajar.toLocaleString('id-ID')}</span></p>
          <p class="card-text mb-1"><strong>Harga Tawaran (Margin ${margin}%):</strong> <span class="text-primary fw-bold">Rp ${Math.round(hargaTawaran).toLocaleString('id-ID')}</span></p>
          <p class="card-text mb-1"><strong>Rating:</strong> ${product.rating || 'N/A'}</p>
          <p class="card-text mb-1"><strong>Terjual:</strong> ${product.sold_raw || 'N/A'}</p>
          <p class="card-text mb-1"><strong>Lokasi:</strong> ${product.location || 'N/A'}</p>
          <a href="${product.details_link}" class="card-link" target="_blank" rel="noopener noreferrer">Lihat Produk</a>
        </div>
      `;
      resultsContainer.appendChild(card);
    });
  }
});
</script>

</body>
</html>
