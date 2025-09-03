<?php
// Pengaturan koneksi database
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'cpe_price_dashboard');

// Menonaktifkan laporan error mysqli untuk menangani error secara manual
mysqli_report(MYSQLI_REPORT_OFF);

// Buat koneksi ke database
// Tanda @ di depan mysqli digunakan untuk menekan warning bawaan PHP
$conn = @new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Periksa koneksi dengan cara yang benar
if ($conn->connect_error) {
    // Catat error yang sebenarnya di log server untuk developer
    error_log("Koneksi Database Gagal: " . $conn->connect_error);
    
    // Tampilkan pesan error yang umum kepada pengguna dan hentikan skrip
    die("Gagal terhubung ke database. Silakan coba lagi nanti.");
}

// Set karakter set ke utf8mb4 untuk mendukung berbagai karakter
$conn->set_charset("utf8mb4");

// Variabel $conn sekarang siap digunakan di file lain yang menyertakan file ini
?>