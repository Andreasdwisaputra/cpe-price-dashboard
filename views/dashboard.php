<?php
// Pastikan session sudah dimulai
session_start();

// Cek apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil data pengguna
$user_id = $_SESSION['user_id'];
$username = 'Pengguna'; // Default username jika data tidak ditemukan

// Koneksi ke database
require_once '../config/db.php';

// Query untuk mendapatkan data pengguna (menggunakan 'id' agar konsisten)
$sql = "SELECT username FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $username = $user['username'];
} else {
    session_unset();
    session_destroy();
    header("Location: login.php?error=invalid_session"); // Redirect ke login dengan pesan
    exit();
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Input Data Produk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #F4F6F9;
        }
        .container {
            margin-top: 30px;
        }
        .form-control {
            margin-bottom: 15px;
        }
        button {
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Dashboard</h1>
    <p>Selamat datang, <?php echo htmlspecialchars($username); ?>!</p>

    <h2>Cari Produk</h2>
    <form id="searchForm">
        <div class="mb-3">
            <label for="nama_item" class="form-label">Nama Item</label>
            <input type="text" class="form-control" id="nama_item" name="nama_item" required>
        </div>
        <div class="mb-3">
            <label for="referensi" class="form-label">Referensi</label><br>
            <input type="checkbox" name="referensi[]" value="Tokopedia"> Tokopedia<br>
            <input type="checkbox" name="referensi[]" value="Shopee"> Shopee<br>
            <input type="checkbox" name="referensi[]" value="Blibli"> Blibli<br>
        </div>
        <div class="mb-3">
            <label for="margin" class="form-label">Margin Mitra (%)</label>
            <input type="number" class="form-control" id="margin" name="margin" required>
        </div>
        <button type="submit" class="btn btn-primary">Cari Produk</button>
    </form>

    <h2>Hasil Pencarian Produk</h2>
    <div id="productResults"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
<script>
    document.getElementById('searchForm').addEventListener('submit', function(event) {
        event.preventDefault();

        const namaItem = document.getElementById('nama_item').value;
        const margin = document.getElementById('margin').value;
        const referensi = Array.from(document.querySelectorAll('input[name="referensi[]"]:checked')).map(e => e.value);

        if (referensi.length === 0) {
            alert('Pilih setidaknya satu referensi.');
            return;
        }

        const apiUrl = `http://localhost/api_get_products.php?nama_item=${encodeURIComponent(namaItem)}&referensi=${encodeURIComponent(referensi.join(','))}&margin=${encodeURIComponent(margin)}`;

        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                let output = '<table class="table table-striped"><thead><tr><th>Nama Produk</th><th>Harga Tawaran</th><th>Referensi</th><th>Link Produk</th><th>Harga Wajar</th></tr></thead><tbody>';
                if (data.error) {
                    output = `<p>${data.error}</p>`;
                } else {
                    data.forEach(product => {
                        output += `
                            <tr>
                                <td>${product.nama_item}</td>
                                <td>${product.harga_tawaran}</td>
                                <td>${product.referensi}</td>
                                <td><a href="${product.link_produk}" target="_blank">Lihat Produk</a></td>
                                <td>${product.harga_wajar}</td>
                            </tr>
                        `;
                    });
                    output += '</tbody></table>';
                }
                document.getElementById('productResults').innerHTML = output;
            })
            .catch(error => {
                document.getElementById('productResults').innerHTML = 'Terjadi kesalahan saat mengambil data.';
            });
    });
</script>

</body>
</html>
