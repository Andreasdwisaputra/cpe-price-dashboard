<?php
// Mulai session dan cek login
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Koneksi ke database
require_once '../config/db.php';

// Cek apakah ID ada di URL
if (!isset($_GET['id'])) {
    header("Location: daftar_harga.php");
    exit();
}

$id = $_GET['id'];

// Ambil data yang akan di-edit dari database
$sql = "SELECT id, keterangan_obl, harga_satuan, dok_referensi, evidence FROM harga_wajar WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    echo "Data tidak ditemukan.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Harga Wajar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Edit Data Harga Wajar</h2>
    <form action="../controllers/update_process.php" method="POST">
        <!-- Input tersembunyi untuk mengirimkan ID -->
        <input type="hidden" name="id" value="<?php echo $data['id']; ?>">

        <div class="mb-3">
            <label for="keterangan_obl" class="form-label">Keterangan OBL</label>
            <input type="text" class="form-control" id="keterangan_obl" name="keterangan_obl" value="<?php echo htmlspecialchars($data['keterangan_obl']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="harga_satuan" class="form-label">Harga Satuan</label>
            <input type="text" class="form-control" id="harga_satuan" name="harga_satuan" value="<?php echo htmlspecialchars($data['harga_satuan']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="dok_referensi" class="form-label">Dok. Referensi</label>
            <input type="text" class="form-control" id="dok_referensi" name="dok_referensi" value="<?php echo htmlspecialchars($data['dok_referensi']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="evidence" class="form-label">Evidence</label>
            <input type="text" class="form-control" id="evidence" name="evidence" value="<?php echo htmlspecialchars($data['evidence']); ?>" required>
        </div>
        
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="daftar_harga.php" class="btn btn-secondary">Batal</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
