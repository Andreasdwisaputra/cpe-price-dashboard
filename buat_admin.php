<?php
require_once 'config/db.php';

$username = 'admin';
$password_polos = 'admin';

$password_hash = password_hash($password_polos, PASSWORD_DEFAULT);

// Cek dulu apakah user admin sudah ada
// PERUBAHAN DI SINI: Mengubah 'id' menjadi 'user_id' agar sesuai dengan database Anda
$stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$stmt_check->bind_param("s", $username);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    die("<h1>User 'admin' sudah ada di database.</h1> <p>Tidak perlu dibuat lagi. Silakan langsung coba login.</p> <a href='/kerja_praktek/public/'>Kembali ke Halaman Login</a>");
}
$stmt_check->close();


// Jika belum ada, masukkan data baru
$sql = "INSERT INTO users (username, password) VALUES (?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $password_hash);

if ($stmt->execute()) {
    echo "<h1>User 'admin' BERHASIL DIBUAT!</h1>";
    echo "<p>Passwordnya sekarang sudah di-hash dengan benar di database.</p>";
    echo "<p>Silakan kembali ke halaman login dan coba lagi.</p>";
    echo '<a href="/kerja_praktek/public/">Kembali ke Halaman Login</a>';
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>