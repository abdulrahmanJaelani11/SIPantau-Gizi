<?php
/**
 * Modul Pengukuran Gizi — Hapus Data Pengukuran
 * ==============================================================
 * Menghapus log penimbangan balita dan hasil status_gizi terkait.
 * Menerima request via POST dengan proteksi CSRF.
 */

$current_slug = 'pengukuran';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan memiliki hak akses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'pengukuran')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk menghapus data pengukuran.');
    redirect(base_url('modules/pengukuran/index.php'));
}

// Hanya menerima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Metode request tidak diizinkan.');
    redirect(base_url('modules/pengukuran/index.php'));
}

// Verifikasi token CSRF
if (!verify_csrf_token()) {
    set_flash('danger', 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba kembali.');
    redirect(base_url('modules/pengukuran/index.php'));
}

$id_pengukuran = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id_pengukuran) {
    set_flash('danger', 'ID Pengukuran tidak valid.');
    redirect(base_url('modules/pengukuran/index.php'));
}

try {
    if ($pdo === null) {
        throw new Exception('Koneksi database tidak tersedia.');
    }

    // Ambil data pengukuran terlebih dahulu untuk keperluan informasi flash
    $stmtCek = $pdo->prepare("
        SELECT p.id_pengukuran, p.tanggal_pengukuran, a.nama AS nama_anak
        FROM pengukuran p
        JOIN anak a ON p.id_anak = a.id_anak
        WHERE p.id_pengukuran = ?
        LIMIT 1
    ");
    $stmtCek->execute([$id_pengukuran]);
    $data = $stmtCek->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        set_flash('warning', 'Data pengukuran tidak ditemukan atau sudah dihapus sebelumnya.');
        redirect(base_url('modules/pengukuran/index.php'));
    }

    // Eksekusi penghapusan dalam satu transaksi
    $pdo->beginTransaction();

    // 1. Hapus status gizi terkait terlebih dahulu (child FK)
    $stmtDelStatus = $pdo->prepare("DELETE FROM status_gizi WHERE id_pengukuran = ?");
    $stmtDelStatus->execute([$id_pengukuran]);

    // 2. Hapus data pengukuran
    $stmtDelPengukuran = $pdo->prepare("DELETE FROM pengukuran WHERE id_pengukuran = ?");
    $stmtDelPengukuran->execute([$id_pengukuran]);

    $pdo->commit();

    $tgl_formatted = date('d/m/Y', strtotime($data['tanggal_pengukuran']));
    set_flash('success', "Data pengukuran balita <strong>" . e($data['nama_anak']) . "</strong> pada tanggal <strong>{$tgl_formatted}</strong> berhasil dihapus.");

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('danger', 'Gagal menghapus data pengukuran: ' . $e->getMessage());
}

redirect(base_url('modules/pengukuran/index.php'));
