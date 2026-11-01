<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "sipantau_gizi";

$conn = mysqli_connect($host, $user, $pass);
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Buat database jika belum ada
mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS $db");
mysqli_select_db($conn, $db);

// Baca isi file SQL
$sql = file_get_contents("database_SiPantau_Gizi.sql");

// Karena file SQL-nya mengandung perintah "USE sipantau_gizi;" yang bikin error phpMyAdmin,
// kita hapus baris itu secara otomatis dari PHP sebelum di-execute
$sql = str_replace("USE sipantau_gizi;", "", $sql);

// Eksekusi semua query secara otomatis
if (mysqli_multi_query($conn, $sql)) {
    echo "SUKSES! Semua tabel berhasil dibuat di database sipantau_gizi.\n";
    // Bersihkan hasil query agar tidak nyangkut
    while (mysqli_more_results($conn) && mysqli_next_result($conn)) {;}
} else {
    echo "GAGAL: " . mysqli_error($conn) . "\n";
}

mysqli_close($conn);
?>
