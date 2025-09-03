<?php
session_start();
$error = '';
if (isset($_SESSION['login_error'])) {
  $error = $_SESSION['login_error'];
  unset($_SESSION['login_error']); // Hapus agar tidak tampil terus
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="../assets/css/style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <title>Login | Dashboard Harga Pasaran</title>
</head>
<body>
  <div class="login-container">
    <div class="login-left">
      <img src="../assets/img/logo-telkom.png" alt="Logo Telkom" class="logo-telkom" />
      <h1>Selamat Datang di<br><span class="sirama">SINHAP</span></h1>
      <p class="sub">Sistem Informasi Harga Produk E-Commerce</p>
      <p class="powered">Powered by <b>Telkom Indonesia</b></p>
    </div>

    <div class="login-right">
      <form class="login-form" method="POST" action="../controllers/login.php">
        <h2 class="text-red">Login</h2>

        <input type="text" name="username" placeholder="Username" required />
        <input type="password" name="password" placeholder="Password" required />

        <?php if (!empty($error)): ?>
          <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <button type="submit" class="btn-login">Login</button>
        <p class="help-text">Belum punya akun? <a href="#">Hubungi Admin</a></p>
      </form>
    </div>
  </div>
</body>
</html>
