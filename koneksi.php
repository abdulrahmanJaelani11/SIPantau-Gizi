<?php
$host = "localhost";
$user = "root";
$pass = ""; // kosongkan jika password root XAMPP Anda memang kosong
$db   = "sipantau_gizi";

// Melakukan koneksi ke database
$koneksi = mysqli_connect($host, $user, $pass, $db);

// Cek apakah koneksi berhasil
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>
