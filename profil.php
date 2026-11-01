<?php
/**
 * Root Redirect untuk Modul Profil — SiPantau Gizi
 * ===================================================
 * Meneruskan rute lama `profil.php` ke modul resmi `modules/profil/index.php`.
 */
require_once __DIR__ . '/config/app.php';

$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
redirect(base_url('modules/profil/index.php' . $query));
