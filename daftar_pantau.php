<?php
/**
 * Redirect ke Modul Daftar Pantau Resmi
 * ========================================
 * Menjamin kompatibilitas backward terhadap link lama / bookmark.
 */
require_once __DIR__ . '/config/app.php';
redirect(base_url('modules/daftar-pantau/index.php'));
exit;
