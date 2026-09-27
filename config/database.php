<?php
/**
 * Konfigurasi Koneksi Database — PDO + MySQL
 * =============================================
 * Sesuaikan variabel di bawah dengan konfigurasi MySQL lokal.
 * Default menggunakan setting standar XAMPP (root tanpa password).
 *
 * PENTING: File ini di-require oleh config/app.php.
 *          Jangan require langsung dari halaman modul.
 */

// TODO: koordinasi tim — pastikan semua anggota pakai nama database yang sama
$db_host = 'localhost';
$db_port = '3306';
$db_name = 'sipantau_gizi';
$db_user = 'root';
$db_pass = '';

$db_dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";

$db_options = [
    // Lempar exception jika ada error query — mudah di-debug
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Hasil fetch selalu berupa array asosiatif
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Pakai prepared statement asli MySQL (bukan emulasi PHP)
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Variabel global $pdo dipakai di seluruh aplikasi.
// Jika koneksi gagal (misal database belum dibuat), $pdo = null.
$pdo = null;

try {
    $pdo = new PDO($db_dsn, $db_user, $db_pass, $db_options);
} catch (PDOException $e) {
    // Simpan pesan error — halaman login akan menampilkan pesan yang ramah.
    // Jangan die() di sini supaya halaman tetap bisa dirender untuk debugging.
    $db_error = 'Koneksi database gagal: ' . $e->getMessage()
              . ' — Pastikan MySQL sudah berjalan dan database "' . $db_name . '" sudah dibuat.';
}
