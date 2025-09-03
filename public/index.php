<?php
session_start();

// Jika sudah login, arahkan ke halaman view
if (isset($_SESSION['username']) && $_SESSION['username'] === 'admin') {
    header("Location: ../views/index.php");
    exit;
}

// Jika belum login, arahkan ke login
header("Location: ../views/login.php");
exit;
?>
