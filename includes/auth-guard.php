<?php
/**
 * Auth Guard — SiPantau Gizi
 * ============================
 * Di-include di setiap halaman yang memerlukan login.
 * Cek apakah pengguna sudah login; jika belum, redirect ke halaman login.
 *
 * Cara pakai di halaman modul:
 *   require_once __DIR__ . '/../../../config/app.php';
 *   require_once ROOT_PATH . '/includes/auth-guard.php';
 *
 * Fase 1 (sekarang): Hanya cek apakah session user_id ada.
 * Fase 4 (nanti)   : Akan ditambahkan pengecekan otorisasi per menu
 *                     menggunakan fungsi bolehAkses($roleId, $slugMenu).
 */

if (!defined('APP_LOADED')) {
    die('Akses langsung tidak diizinkan.');
}

// Cek apakah user sudah login
if (empty($_SESSION['user_id'])) {
    // Simpan pesan flash agar muncul di halaman login
    set_flash('warning', 'Silakan login terlebih dahulu.');
    redirect(base_url('modules/auth/login.php'));
}

// ============================================================
// Otorisasi Akses Menu (Role-Based Access Control)
// ============================================================
require_once ROOT_PATH . '/src/Otorisasi.php';

// Jika variabel $current_slug sudah diset sebelum include auth-guard.php
if (!empty($current_slug) && !bolehAkses($_SESSION['role_id'] ?? 0, $current_slug)) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.');
    redirect(base_url('modules/dashboard/index.php'));
}

/**
 * Helper untuk memeriksa hak akses secara manual di halaman modul jika $current_slug
 * didefinisikan setelah auth-guard.php di-include.
 */
function cek_akses_menu(?string $slug): void
{
    if (!empty($slug) && !bolehAkses($_SESSION['role_id'] ?? 0, $slug)) {
        set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.');
        redirect(base_url('modules/dashboard/index.php'));
    }
}

