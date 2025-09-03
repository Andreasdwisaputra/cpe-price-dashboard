<?php
// 1. Cara memulai session yang lebih robust
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Cek apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 3. Gunakan require_once dan path relatif yang benar
require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$username = 'Pengguna'; // Default username jika data tidak ditemukan

// Query untuk mendapatkan data pengguna (menggunakan 'id' agar konsisten)
$sql = "SELECT username FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    // Ambil username. Pastikan untuk membersihkannya sebelum ditampilkan nanti
    $username = $user['username'];
} else {
    // 4. Penanganan error yang lebih baik: jika user tidak ditemukan, hancurkan session dan redirect
    session_unset();
    session_destroy();
    header("Location: login.php?error=invalid_session"); // Redirect ke login dengan pesan
    exit();
}

$stmt->close();
$conn->close();
?>

<<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
  <!-- Link ke Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #F4F6F9;
    }
    .sidebar {
      position: fixed;
      top: 0;
      bottom: 0;
      left: 0;
      width: 250px;
      background-color: #E60012;
      color: white;
      padding-top: 20px;
      transition: transform 0.3s ease;
      transform: translateX(-250px); /* Sidebar disembunyikan */
    }
    .sidebar.open {
      transform: translateX(0); /* Sidebar muncul */
    }
    .content {
      margin-left: 0;
      padding: 20px;
      background-color: white;
      transition: margin-left 0.3s ease;
    }
    .content.open {
      margin-left: 250px; /* Konten bergeser ketika sidebar terbuka */
    }
    .menu-toggle {
      position: fixed;
      top: 10px;
      left: 20px;
      background-color: #E60012;
      color: white;
      border: none;
      padding: 15px;
      border-radius: 5px;
      cursor: pointer;
      z-index: 1000;
      font-size: 20px;
    }
    .close-menu {
      display: none;
      position: fixed;
      top: 10px;
      left: 20px;
      background-color: #E60012;
      color: white;
      border: none;
      padding: 15px;
      border-radius: 5px;
      cursor: pointer;
      z-index: 1000;
      font-size: 20px;
    }
    /* Responsif untuk tampilan mobile */
    @media (max-width: 768px) {
      .sidebar {
        width: 200px;
      }
      .content {
        margin-left: 0;
      }
      .content.open {
        margin-left: 200px;
      }
    }
  </style>
</head>
<body>

  <!-- Tombol Menu (Ikon Hamburger) -->
  <button class="menu-toggle" onclick="toggleSidebar()">&#9776;</button>

  <!-- Tombol Close (Ikon X) untuk menutup Sidebar -->
  <button class="close-menu" onclick="toggleSidebar()">&#10005;</button>

  <!-- Sidebar -->
  <div class="sidebar">
    <div class="text-center">
      <img src="../assets/img/default-profile.png" alt="User Profile" class="rounded-circle" style="width: 80px; height: 80px;">
      <p class="mt-2">Username</p>
    </div>
    <ul class="nav flex-column px-2">
      <li class="nav-item">
        <a class="nav-link text-white" href="#settings">Pengaturan Profil</a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="../views/dashboard.php">Dashboard</a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="../views/daftar-harga.php">update-data</a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="../views/input-data.php">Data</a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="../controllers/logout.php">Logout</a>
      </li>
    </ul>
  </div>

  <!-- Main Content -->
  <div class="content" onclick="closeSidebarOnClick()">
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="#">User Profile</a>
          </li>
        </ul>
      </div>
    </nav>

    <!-- Dashboard Content -->
    <div class="container">
      <h1>Selamat datang di Dashboard!</h1>
      <p>Ini adalah halaman dashboard pengguna. Anda dapat melihat data dan melakukan pengaturan profil di sini.</p>
    </div>
  </div>

  <!-- Bootstrap 5 JS -->
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

  <script>
    // Fungsi untuk membuka dan menutup sidebar
    function toggleSidebar() {
      const sidebar = document.querySelector('.sidebar');
      const content = document.querySelector('.content');
      const menuToggle = document.querySelector('.menu-toggle');
      const closeMenu = document.querySelector('.close-menu');

      sidebar.classList.toggle('open');
      content.classList.toggle('open');
      menuToggle.classList.toggle('d-none'); // Sembunyikan ikon menu
      closeMenu.classList.toggle('d-none'); // Tampilkan ikon close
    }

    // Fungsi untuk menutup sidebar jika area konten diklik
    function closeSidebarOnClick() {
      const sidebar = document.querySelector('.sidebar');
      const content = document.querySelector('.content');
      if (sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        content.classList.remove('open');
        document.querySelector('.menu-toggle').classList.remove('d-none');
        document.querySelector('.close-menu').classList.add('d-none');
      }
    }
  </script>
</body>
</html>

