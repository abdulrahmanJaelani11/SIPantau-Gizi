<?php
/**
 * Konfigurasi Utama Aplikasi SiPantau Gizi
 * ==========================================
 * File ini WAJIB di-require di awal setiap halaman PHP.
 * 
 * Cara pakai di halaman modul:
 *   require_once __DIR__ . '/../../config/app.php';   // sesuaikan jumlah ../
 *   require_once ROOT_PATH . '/includes/auth-guard.php';
 */

// Cegah loading ganda
if (defined('APP_LOADED')) return;
define('APP_LOADED', true);

// ============================================================
// Konstanta Path & Identitas Aplikasi
// ============================================================

// Path absolut ke root project (satu level di atas folder config/)
define('ROOT_PATH', dirname(__DIR__));

// Nama aplikasi — ditampilkan di title, sidebar, footer
define('APP_NAME', 'SiPantau Gizi');

// Base URL — sesuaikan dengan konfigurasi web server lokal.
// Contoh XAMPP: jika project ada di htdocs/sipantau-gizi/ → '/sipantau-gizi/'
// Contoh PHP built-in server (php -S localhost:8000): → '/'
// PENTING: harus diawali dan diakhiri dengan /
define('BASE_URL', '/sipantau-gizi/');

// ============================================================
// Inisialisasi Session
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// Koneksi Database
// ============================================================
require_once ROOT_PATH . '/config/database.php';

// ============================================================
// Fungsi Helper
// ============================================================

/**
 * Menghasilkan URL lengkap relatif terhadap BASE_URL.
 * Contoh: base_url('modules/auth/login.php') → '/sipantau-gizi/modules/auth/login.php'
 */
function base_url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Menghasilkan URL ke file asset (css, js, images).
 * Contoh: asset('css/style.css') → '/sipantau-gizi/assets/css/style.css'
 */
function asset(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

/**
 * Shortcut htmlspecialchars() untuk mencegah XSS.
 * Gunakan di setiap output data dinamis ke HTML:
 *   <?= e($nama) ?>
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect ke URL dan hentikan eksekusi script.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Simpan pesan flash ke session (ditampilkan sekali setelah redirect).
 * Tipe: 'success', 'danger', 'warning', 'info'
 *
 * Contoh:
 *   set_flash('success', 'Data berhasil disimpan.');
 *   redirect(base_url('modules/utilitas/pengguna/index.php'));
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Ambil dan hapus pesan flash dari session.
 * Mengembalikan ['type' => ..., 'message' => ...] atau null.
 */
function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Ambil nilai input lama setelah redirect gagal validasi.
 * Berguna untuk mengisi ulang form supaya user tidak perlu ketik ulang.
 *
 * Contoh di form:
 *   <input name="nama" value="<?= e(old('nama')) ?>">
 */
function old(string $key, string $default = ''): string
{
    return $_SESSION['old_input'][$key] ?? $default;
}

/**
 * Simpan semua input ke session (untuk form repopulation setelah redirect).
 * Panggil sebelum redirect saat validasi gagal.
 */
function set_old_input(array $data): void
{
    $_SESSION['old_input'] = $data;
}

/**
 * Hapus old input dari session.
 * Panggil setelah form berhasil diproses.
 */
function clear_old_input(): void
{
    unset($_SESSION['old_input']);
}
