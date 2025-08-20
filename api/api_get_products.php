<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informasi Produk dari API</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
      body {
        font-family: 'Inter', sans-serif;
      }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
        <h1 class="text-3xl font-bold mb-6 text-gray-800 text-center">Informasi Produk</h1>
        
        <div id="product-card" class="bg-gray-50 p-6 rounded-lg shadow-inner border border-gray-200">
            <p id="loading-message" class="text-center text-gray-500 font-medium">Memuat data produk...</p>
            <div id="product-info" class="hidden">
                <p class="text-xl font-semibold text-gray-900 mb-2">Nama Item: <span id="product-name" class="font-normal text-gray-700"></span></p>
                <p class="text-xl font-semibold text-gray-900 mb-2">Harga Tawaran: <span id="offer-price" class="font-normal text-gray-700"></span></p>
                <p class="text-xl font-semibold text-gray-900 mb-2">Harga Wajar: <span id="fair-price" class="font-normal text-gray-700"></span></p>
                <p class="text-xl font-semibold text-gray-900 mb-2">Referensi: <span id="product-reference" class="font-normal text-gray-700"></span></p>
                <p class="text-xl font-semibold text-gray-900 mb-2">Link Produk: <a href="#" id="product-link" class="font-normal text-blue-600 hover:underline"></a></p>
                <p class="text-xl font-semibold text-gray-900 mb-2">Jumlah Penjualan: <span id="sales-count" class="font-normal text-gray-700"></span></p>
                <p class="text-xl font-semibold text-gray-900">Ulasan: <span id="reviews-count" class="font-normal text-gray-700"></span></p>
            </div>
        </div>

        <div id="error-message" class="hidden mt-4 p-4 bg-red-100 text-red-700 border border-red-300 rounded-lg text-center font-medium">
            Terjadi kesalahan saat mengambil data. Mohon coba lagi nanti.
        </div>
    </div>

    <script>
        // URL endpoint API PHP Anda. Ganti 'nama_file_php_anda.php' dengan nama file yang benar.
        // Anda juga harus mengganti parameter sesuai kebutuhan.
        const apiUrl = 'http://localhost/kerja_praktek/api/get_product.php?nama_item=router&referensi=tokopedia&margin=15';

        async function fetchProductData() {
            try {
                // Mengirim permintaan GET ke API PHP
                const response = await fetch(apiUrl);
                const data = await response.json();

                // Dapatkan elemen-elemen HTML
                const loadingMessage = document.getElementById('loading-message');
                const productInfo = document.getElementById('product-info');
                const errorMessage = document.getElementById('error-message');
                
                // Sembunyikan pesan loading
                loadingMessage.classList.add('hidden');

                if (data.error) {
                    errorMessage.textContent = data.error;
                    errorMessage.classList.remove('hidden');
                } else if (Array.isArray(data) && data.length > 0) {
                    // Ambil produk pertama dari array (jika ada)
                    const product = data[0];

                    // Perbarui elemen-elemen HTML dengan data yang diterima
                    document.getElementById('product-name').textContent = product.nama_item;
                    document.getElementById('offer-price').textContent = `Rp ${product.harga_tawaran.toLocaleString('id-ID')}`;
                    document.getElementById('fair-price').textContent = `Rp ${product.harga_wajar.toLocaleString('id-ID')}`;
                    document.getElementById('product-reference').textContent = product.referensi;
                    
                    const productLinkElement = document.getElementById('product-link');
                    productLinkElement.href = product.link_produk;
                    productLinkElement.textContent = product.link_produk;

                    document.getElementById('sales-count').textContent = product.jumlah_penjualan;
                    document.getElementById('reviews-count').textContent = product.ulasan;

                    // Tampilkan kartu produk
                    productInfo.classList.remove('hidden');
                } else {
                    errorMessage.textContent = 'Tidak ditemukan produk yang cocok.';
                    errorMessage.classList.remove('hidden');
                }

            } catch (error) {
                // Tangani kesalahan jaringan
                const loadingMessage = document.getElementById('loading-message');
                const errorMessage = document.getElementById('error-message');
                loadingMessage.classList.add('hidden');
                errorMessage.textContent = 'Tidak dapat terhubung ke server API.';
                errorMessage.classList.remove('hidden');
                console.error('Terjadi kesalahan saat memuat data:', error);
            }
        }

        // Panggil fungsi untuk mengambil data saat halaman dimuat
        document.addEventListener('DOMContentLoaded', fetchProductData);
    </script>
</body>
</html>
