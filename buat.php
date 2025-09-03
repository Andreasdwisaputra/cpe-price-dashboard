<?php
// Masukkan password yang ingin Anda hash di sini
$passwordSaya = 'admin';

// Hasilkan hash menggunakan algoritma standar dan aman
$hash = password_hash($passwordSaya, PASSWORD_DEFAULT);

// Tampilkan hasilnya
echo "Password Anda: " . $passwordSaya . "<br>";
echo "Hash untuk disimpan di database: <br>";
echo "<strong>" . $hash . "</strong>";

?>
