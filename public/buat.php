<?php
// Ganti 'passwordnya_disini' dengan password yang Anda mau
$password_plaintext = 'admin';

$hash = password_hash($password_plaintext, PASSWORD_DEFAULT);

echo "Salin dan gunakan hash ini di perintah SQL:<br><br>";
echo "<strong>" . $hash . "</strong>";
?>