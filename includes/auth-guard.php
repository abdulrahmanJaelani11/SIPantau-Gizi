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
// Fase 4: Tambahkan pengecekan otorisasi di sini
// ============================================================
// Contoh nanti:
//   require_once ROOT_PATH . '/src/Otorisasi.php';
//   if (!bolehAkses($_SESSION['role_id'], $current_slug)) {
//       set_flash('danger', 'Anda tidak memiliki akses ke halaman ini.');
//       redirect(base_url('modules/dashboard/index.php'));
//   }
