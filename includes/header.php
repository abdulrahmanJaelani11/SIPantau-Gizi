<?php
/**
 * Header Layout — SiPantau Gizi
 * ===============================
 * Di-include di awal setiap halaman modul (setelah require config/app.php).
 *
 * Variabel yang HARUS di-set sebelum include:
 *   $page_title   — string, judul halaman (misal 'Daftar Pengguna')
 *   $current_slug  — string, slug menu aktif untuk highlight sidebar (misal 'pengguna')
 *
 * Variabel opsional:
 *   $extra_css     — string, tag <link> atau <style> tambahan khusus halaman ini
 */

if (!defined('APP_LOADED')) {
    die('Akses langsung tidak diizinkan.');
}

// Default nilai variabel
$page_title   = $page_title ?? 'Dashboard';
$current_slug = $current_slug ?? '';

// Data user dari session — dipakai di topbar
$user_nama = $_SESSION['user_nama'] ?? 'Pengguna';
$user_role = $_SESSION['user_role_nama'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e(APP_NAME) ?> — Sistem Informasi Pemantauan Gizi">
  <title><?= e($page_title) ?> | <?= e(APP_NAME) ?></title>

  <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= asset('vendors/bootstrap-icons/bootstrap-icons.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
  <?php if (!empty($extra_css)) echo $extra_css; ?>
</head>

<body>
  <div class="admin-shell">
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <?php include ROOT_PATH . '/includes/sidebar.php'; ?>

    <div class="admin-main">
      <!-- Topbar / Navbar -->
      <nav class="navbar admin-navbar navbar-expand bg-white">
        <div class="container-fluid px-3 px-lg-4">
          <button class="sidebar-toggle" type="button" data-sidebar-toggle
                  aria-controls="adminSidebar" aria-expanded="true" aria-label="Toggle sidebar">
            <span></span><span></span><span></span>
          </button>

          <div class="navbar-actions ms-auto">
            <!-- Tombol dark/light mode -->
            <button class="icon-button theme-toggle" type="button" data-theme-toggle
                    aria-label="Ganti tema warna" title="Ganti tema warna">
              <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
            </button>

            <!-- Dropdown profil user -->
            <div class="dropdown">
              <button class="profile-button dropdown-toggle" type="button"
                      data-bs-toggle="dropdown" aria-expanded="false">
                <img class="avatar-img avatar-sm"
                     src="<?= asset('images/avatar/avatar.jpg') ?>"
                     alt="<?= e($user_nama) ?>">
                <span class="profile-name d-none d-sm-inline"><?= e($user_nama) ?></span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li>
                  <span class="dropdown-item-text small text-muted">
                    Role: <?= e($user_role) ?>
                  </span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                  <a class="dropdown-item" href="<?= base_url('modules/auth/logout.php') ?>">
                    <i class="bi bi-box-arrow-left me-1"></i> Keluar
                  </a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </nav>

      <!-- Awal Konten Halaman -->
      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">

<?php
// Tampilkan pesan flash (notifikasi satu kali setelah redirect)
$flash = get_flash();
if ($flash): ?>
          <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
          </div>
<?php endif; ?>
