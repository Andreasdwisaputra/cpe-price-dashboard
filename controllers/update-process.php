<?php
// Mulai session dan cek login
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../views/login.php");
    exit();
}

// Koneksi ke database
require_once '../config/db.php';

// Cek apakah request adalah POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form
    $id = $_POST['id'];
    $keterangan_obl = $_POST['keterangan_obl'];
    $harga_satuan = $_POST['harga_satuan'];
    $dok_referensi = $_POST['dok_referensi'];
    $evidence = $_POST['evidence'];

    // Validasi sederhana (pastikan tidak kosong)
    if (empty($id) || empty($keterangan_obl) || empty($harga_satuan) || empty($dok_referensi) || empty($evidence)) {
        // Sebaiknya berikan pesan error yang lebih spesifik
        die("Error: Semua field harus diisi.");
    }

    // Siapkan statement SQL untuk update data menggunakan prepared statements
    $sql = "UPDATE harga_wajar SET keterangan_obl = ?, harga_satuan = ?, dok_referensi = ?, evidence = ? WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    
    // Bind parameter ke statement
    // ssssi -> s: string, i: integer
    $stmt->bind_param("ssssi", $keterangan_obl, $harga_satuan, $dok_referensi, $evidence, $id);

    // Eksekusi statement
    if ($stmt->execute()) {
        // Jika berhasil, redirect kembali ke halaman daftar dengan status sukses
        header("Location: ../views/daftar_harga.php?status=updated");
        exit();
    } else {
        // Jika gagal, tampilkan error
        echo "Error: " . $stmt->error;
    }

    // Tutup statement
    $stmt->close();
} else {
    // Jika bukan metode POST, redirect ke halaman utama
    header("Location: ../views/dashboard.php");
    exit();
}

// Tutup koneksi
$conn->close();
?>
