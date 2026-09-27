<?php
/**
 * Logout — SiPantau Gizi
 * ========================
 * Menghapus semua data session dan redirect ke halaman login.
 */

require_once __DIR__ . '/../../config/app.php';

// Hapus semua data session
$_SESSION = [];

// Hapus cookie session jika ada
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Hancurkan session
session_destroy();

// Redirect ke login
header('Location: ' . rtrim(BASE_URL, '/') . '/modules/auth/login.php');
exit;
