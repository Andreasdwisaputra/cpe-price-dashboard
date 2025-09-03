<?php
// Mulai session dan cek login
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Koneksi ke database
require_once '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $keterangan_obl = $_POST['keterangan_obl'];
    $harga_satuan = $_POST['harga_satuan'];
    $dok_referensi = $_POST['dok_referensi'];
    $evidence = $_POST['evidence'];

    // Insert data ke database
    $sql = "INSERT INTO harga_wajar (keterangan_obl, harga_satuan, dok_referensi, evidence) 
            VALUES ('$keterangan_obl', '$harga_satuan', '$dok_referensi', '$evidence')";

    if ($conn->query($sql) === TRUE) {
        header("Location: daftar_harga.php?status=added");
        exit();
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Data Harga Wajar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h1>Tambah Data Harga Wajar</h1>

    <form method="POST" action="add_data.php">
        <div class="mb-3">
            <label for="keterangan_obl" class="form-label">Keterangan OBL</label>
            <input type="text" class="form-control" id="keterangan_obl" name="keterangan_obl" required>
        </div>
        <div class="mb-3">
            <label for="harga_satuan" class="form-label">Harga Satuan</label>
            <input type="text" class="form-control" id="harga_satuan" name="harga_satuan" required>
        </div>
        <div class="mb-3">
            <label for="dok_referensi" class="form-label">Dok. Referensi</label>
            <input type="text" class="form-control" id="dok_referensi" name="dok_referensi" required>
        </div>
        <div class="mb-3">
            <label for="evidence" class="form-label">Evidence</label>
            <input type="text" class="form-control" id="evidence" name="evidence" required>
        </div>
        <button type="submit" class="btn btn-primary">Tambah Data</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
