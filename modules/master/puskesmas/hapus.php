<?php
/**
 * Master Data — Hapus Puskesmas (Proses POST)
 * =============================================
 * Menangani penghapusan data Puskesmas dengan proteksi integritas relasi data:
 * - Proteksi CSRF Token
 * - Proteksi relasi Posyandu binaan
 * - Proteksi relasi akun petugas pengguna
 */

$current_slug = 'puskesmas';
require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan hanya role Admin yang bisa menghapus Puskesmas
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
$is_admin = ($user_role_id === Otorisasi::ADMIN_ROLE_ID || strcasecmp($_SESSION['user_role_nama'] ?? '', 'Admin') === 0);
if (!$is_admin) {
    set_flash('danger', 'Akses ditolak. Hanya Administrator yang dapat menghapus data Puskesmas.');
    redirect(base_url('modules/master/puskesmas/index.php'));
}

// 1. Hanya menerima request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Metode request tidak valid.');
    redirect(base_url('modules/master/puskesmas/index.php'));
}

// 2. Validasi Token CSRF
if (!verify_csrf_token()) {
    set_flash('danger', 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba lagi.');
    redirect(base_url('modules/master/puskesmas/index.php'));
}

// 3. Validasi ID Puskesmas
$puskesmas_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$puskesmas_id) {
    set_flash('danger', 'ID Puskesmas tidak valid.');
    redirect(base_url('modules/master/puskesmas/index.php'));
}

// 4. Cek keberadaan data
try {
    $stmt = $pdo->prepare("SELECT id_puskesmas, nama_puskesmas FROM puskesmas WHERE id_puskesmas = ? LIMIT 1");
    $stmt->execute([$puskesmas_id]);
    $puskesmas = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$puskesmas) {
        set_flash('danger', 'Data Puskesmas tidak ditemukan atau sudah dihapus sebelumnya.');
        redirect(base_url('modules/master/puskesmas/index.php'));
    }

    // 5. Cek keterikatan data Posyandu
    $stmtPos = $pdo->prepare("SELECT COUNT(*) FROM posyandu WHERE id_puskesmas = ?");
    $stmtPos->execute([$puskesmas_id]);
    $pos_count = (int)$stmtPos->fetchColumn();

    if ($pos_count > 0) {
        set_flash('danger', "Puskesmas <strong>" . e($puskesmas['nama_puskesmas']) . "</strong> tidak dapat dihapus karena masih membina <strong>{$pos_count} unit Posyandu</strong>. Hapus atau pindahkan Posyandu terlebih dahulu.");
        redirect(base_url('modules/master/puskesmas/index.php'));
    }

    // 6. Cek keterikatan akun petugas
    $stmtUser = $pdo->prepare("SELECT COUNT(*) FROM user WHERE id_puskesmas = ?");
    $stmtUser->execute([$puskesmas_id]);
    $user_count = (int)$stmtUser->fetchColumn();

    if ($user_count > 0) {
        set_flash('danger', "Puskesmas <strong>" . e($puskesmas['nama_puskesmas']) . "</strong> tidak dapat dihapus karena masih memiliki <strong>{$user_count} akun petugas kesehatan</strong> tertaut.");
        redirect(base_url('modules/master/puskesmas/index.php'));
    }

    // 7. Eksekusi Hapus
    $stmtDel = $pdo->prepare("DELETE FROM puskesmas WHERE id_puskesmas = ?");
    $stmtDel->execute([$puskesmas_id]);

    set_flash('success', "Data Puskesmas <strong>" . e($puskesmas['nama_puskesmas']) . "</strong> berhasil dihapus dari sistem.");

} catch (PDOException $e) {
    set_flash('danger', 'Gagal menghapus Puskesmas: ' . $e->getMessage());
}

redirect(base_url('modules/master/puskesmas/index.php'));
