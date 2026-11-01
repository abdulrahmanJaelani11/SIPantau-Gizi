<?php
/**
 * Modul Utilitas — Hapus Role (Proses POST)
 * ============================================
 * Menangani penghapusan role dengan proteksi keamanan bertingkat:
 * - Proteksi CSRF Token
 * - Proteksi Super Admin & Role Bawaan Sistem
 * - Proteksi Role yang sedang aktif digunakan pengguna
 * - Proteksi Role dari user yang sedang login saat ini
 */

$current_slug = 'role';
require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan hanya role yang berhak yang bisa menghapus
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'role')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk menghapus Role.');
    redirect(base_url('modules/dashboard/index.php'));
}

// 1. Hanya menerima request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Metode request tidak valid.');
    redirect(base_url('modules/utilitas/role/index.php'));
}

// 2. Validasi Token CSRF
if (!verify_csrf_token()) {
    set_flash('danger', 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba lagi.');
    redirect(base_url('modules/utilitas/role/index.php'));
}

// 3. Validasi ID Role
$role_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$role_id) {
    set_flash('danger', 'ID role tidak valid.');
    redirect(base_url('modules/utilitas/role/index.php'));
}

// 4. Proteksi: Tidak boleh menghapus role akun yang sedang login
if ($role_id === (int)$_SESSION['role_id']) {
    set_flash('danger', 'Anda tidak dapat menghapus role yang saat ini sedang Anda gunakan.');
    redirect(base_url('modules/utilitas/role/index.php'));
}

// 5. Cek data role di database
try {
    $stmt = $pdo->prepare("SELECT id_role, nama_role FROM role WHERE id_role = ? LIMIT 1");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        set_flash('danger', 'Role tidak ditemukan atau sudah dihapus sebelumnya.');
        redirect(base_url('modules/utilitas/role/index.php'));
    }

    // 6. Proteksi Role Administrator
    if ($role_id === Otorisasi::ADMIN_ROLE_ID || strcasecmp($role['nama_role'], 'Admin') === 0) {
        set_flash('danger', 'Role <strong>Administrator</strong> adalah peran inti sistem dan tidak dapat dihapus.');
        redirect(base_url('modules/utilitas/role/index.php'));
    }

    // 7. Proteksi Role Bawaan Sistem
    if (Otorisasi::isSystemRole($role_id)) {
        set_flash('danger', 'Role <strong>' . e($role['nama_role']) . '</strong> merupakan peran bawaan sistem terproteksi dan tidak dapat dihapus.');
        redirect(base_url('modules/utilitas/role/index.php'));
    }

    // 8. Proteksi Keterikatan User
    $user_count = Otorisasi::getUserCount($role_id);
    if ($user_count > 0) {
        set_flash('danger', 'Role <strong>' . e($role['nama_role']) . '</strong> tidak dapat dihapus karena masih digunakan oleh <strong>' . $user_count . ' akun pengguna</strong>. Harap pindahkan pengguna ke role lain terlebih dahulu.');
        redirect(base_url('modules/utilitas/role/index.php'));
    }

    // 9. Eksekusi Penghapusan (role_menu otomatis terhapus via CASCADE / transaksi)
    $pdo->beginTransaction();

    $stmtDelRm = $pdo->prepare("DELETE FROM role_menu WHERE id_role = ?");
    $stmtDelRm->execute([$role_id]);

    $stmtDelRole = $pdo->prepare("DELETE FROM role WHERE id_role = ?");
    $stmtDelRole->execute([$role_id]);

    $pdo->commit();

    set_flash('success', "Role <strong>" . e($role['nama_role']) . "</strong> berhasil dihapus dari sistem.");

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('danger', 'Gagal menghapus role: ' . $e->getMessage());
}

redirect(base_url('modules/utilitas/role/index.php'));
