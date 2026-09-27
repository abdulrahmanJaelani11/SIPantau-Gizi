<?php
/**
 * Entry Point — SiPantau Gizi
 * =============================
 * Halaman awal project. Mengarahkan ke dashboard (jika sudah login)
 * atau ke halaman login (jika belum).
 */

require_once __DIR__ . '/config/app.php';

if (!empty($_SESSION['user_id'])) {
    redirect(base_url('modules/dashboard/index.php'));
} else {
    redirect(base_url('modules/auth/login.php'));
}
