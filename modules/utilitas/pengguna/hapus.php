<?php
/**
 * Modul Utilitas — Hapus Pengguna (Proses POST)
 * =============================================
 * Menangani penghapusan akun pengguna dengan proteksi CSRF dan proteksi akun sendiri.
 * Disesuaikan dengan arsitektur database db_sipantau_gizi (tabel user).
 */

require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan hanya role Admin yang bisa menghapus akun pengguna (id_role = 4)
$user_role = $_SESSION['user_role_nama'] ?? '';
$user_role_id = $_SESSION['role_id'] ?? 0;
if (strcasecmp($user_role, 'Admin') !== 0 && $user_role_id != 4) {
    set_flash('danger', 'Akses ditolak. Tindakan ini hanya untuk Administrator.');
    redirect(base_url('modules/dashboard/index.php'));
}

// Hanya menerima request POST untuk mencegah penghapusan via URL GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Metode request tidak valid.');
    redirect(base_url('modules/utilitas/pengguna/index.php'));
}

// Validasi Token CSRF
if (!verify_csrf_token()) {
    set_flash('danger', 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba lagi.');
    redirect(base_url('modules/utilitas/pengguna/index.php'));
}

// Validasi ID pengguna
$user_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$user_id) {
    set_flash('danger', 'ID pengguna tidak valid.');
    redirect(base_url('modules/utilitas/pengguna/index.php'));
}

// Proteksi: Pengguna tidak boleh menghapus akunnya sendiri
if ($user_id === (int) $_SESSION['user_id']) {
    set_flash('danger', 'Anda tidak dapat menghapus akun yang sedang Anda gunakan.');
    redirect(base_url('modules/utilitas/pengguna/index.php'));
}

// Cek apakah data pengguna ada di database
try {
    $stmt = $pdo->prepare("SELECT id_user, nama, username FROM user WHERE id_user = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        set_flash('danger', 'Pengguna tidak ditemukan atau sudah dihapus sebelumnya.');
        redirect(base_url('modules/utilitas/pengguna/index.php'));
    }

    // Eksekusi penghapusan dari tabel user
    $delete_stmt = $pdo->prepare("DELETE FROM user WHERE id_user = ?");
    $delete_stmt->execute([$user_id]);

    set_flash('success', "Pengguna <strong>" . e($user['nama']) . "</strong> (@" . e($user['username']) . ") berhasil dihapus dari sistem.");
} catch (PDOException $e) {
    set_flash('danger', 'Gagal menghapus pengguna: ' . $e->getMessage());
}

redirect(base_url('modules/utilitas/pengguna/index.php'));
