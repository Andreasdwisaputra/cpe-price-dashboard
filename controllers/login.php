<?php
// Selalu mulai session di baris paling atas
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hanya proses jika request adalah metode POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Sertakan koneksi database
    require_once '../config/db.php';

    // Ambil input dari form
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validasi dasar, pastikan input tidak kosong
    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = 'Username dan Password tidak boleh kosong!';
        // Redirect kembali ke halaman utama/login
        header('Location: /kerja_praktek/public/');
        exit();
    }

    try {
        // PERUBAHAN DI SINI: Mengubah 'id' menjadi 'user_id' agar cocok dengan database
        // Kode Baru yang Lebih Aman
$sql = "SELECT user_id, password FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    // Jika prepare gagal, hentikan eksekusi dan tampilkan error dari database
    die("Error preparing statement: " . $conn->error);
}

// Lanjutkan ke bind_param hanya jika prepare berhasil
$stmt->bind_param("s", $username);
        
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Login berhasil
                session_regenerate_id(true);

                // PERUBAHAN DI SINI: Menyimpan 'user_id' dari hasil query
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['last_login'] = time();

                unset($_SESSION['login_error']);

                // Redirect ke dashboard
                header('Location: /kerja_praktek/views/index.php');
                exit();

            } else {
                // Password salah
                $_SESSION['login_error'] = 'Username atau Password salah!';
                header('Location: /kerja_praktek/public/index.php');
                exit();
            }

        } else {
            // User tidak ditemukan
            $_SESSION['login_error'] = 'Username atau Password salah!';
            header('Location: /kerja_praktek/public/index.php');
            exit();
        }

    } catch (Exception $e) {
        // Tangani error database
        $_SESSION['login_error'] = 'Terjadi masalah pada sistem. Coba lagi nanti.';
        // Untuk debugging: uncomment baris di bawah untuk melihat error sebenarnya
        // $_SESSION['login_error'] = $e->getMessage(); 
        header('Location: /kerja_praktek/public/index.php');
        exit();
    }

} else {
    // Jika akses bukan via POST, redirect
    header('Location: /kerja_praktek/public/index.php');
    exit();
}