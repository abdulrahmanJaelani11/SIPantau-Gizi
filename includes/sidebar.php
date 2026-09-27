<?php
/**
 * Sidebar Navigasi — SiPantau Gizi
 * ===================================
 * Menampilkan menu navigasi sidebar berdasarkan daftar menu.
 *
 * Fase 1 (sekarang): Menu dirender dari array statis di bawah.
 * Fase 4 (nanti)   : Array ini diganti query dinamis dari tabel menus + role_menu,
 *                     sehingga menu yang tampil sesuai hak akses role pengguna.
 *
 * Variabel yang harus tersedia (dari header.php):
 *   $current_slug — slug halaman aktif untuk highlight menu (string)
 */

// ============================================================
// Daftar Menu Sidebar (Statis)
// ============================================================
// Struktur setiap item:
//   'type'  => 'link' (default) atau 'label' (pemisah grup)
//   'slug'  => slug unik menu (cocokkan dengan $current_slug)
//   'nama'  => teks yang ditampilkan
//   'icon'  => class Bootstrap Icon (tanpa prefix 'bi ')
//   'url'   => URL tujuan (pakai base_url() agar path tidak pecah)
//
// Fase 4: array ini akan diganti hasil query dari tabel `menus` + `role_menu`

$sidebar_menus = [
    ['slug' => 'dashboard',    'nama' => 'Dashboard',          'icon' => 'bi-speedometer2',  'url' => base_url('modules/dashboard/index.php')],

    ['type' => 'label', 'nama' => 'Data & Laporan'],
    ['slug' => 'data-anak',    'nama' => 'Data Balita & Anak', 'icon' => 'bi-person-hearts', 'url' => '#'], // TODO: koordinasi tim
    ['slug' => 'pengukuran',   'nama' => 'Pengukuran Gizi',    'icon' => 'bi-rulers',        'url' => '#'], // TODO: koordinasi tim
    ['slug' => 'laporan-gizi', 'nama' => 'Laporan & Grafik',   'icon' => 'bi-bar-chart-line','url' => '#'], // TODO: koordinasi tim

    ['type' => 'label', 'nama' => 'Master Data'],
    ['slug' => 'posyandu',     'nama' => 'Data Posyandu',      'icon' => 'bi-hospital',      'url' => '#'], // TODO: koordinasi tim
    ['slug' => 'puskesmas',    'nama' => 'Data Puskesmas',     'icon' => 'bi-building',      'url' => '#'], // TODO: koordinasi tim

    ['type' => 'label', 'nama' => 'Utilitas'],
    ['slug' => 'pengguna',     'nama' => 'Daftar Pengguna',    'icon' => 'bi-people',        'url' => base_url('modules/utilitas/pengguna/index.php')],
    ['slug' => 'role',         'nama' => 'Role & Otorisasi',   'icon' => 'bi-shield-lock',   'url' => base_url('modules/utilitas/role/index.php')],
];

// Data user untuk widget sidebar bawah
$sidebar_user_nama = $_SESSION['user_nama'] ?? 'Pengguna';
$sidebar_user_role = $_SESSION['user_role_nama'] ?? '';
?>

<!-- Sidebar -->
<aside class="admin-sidebar" id="adminSidebar" aria-label="Navigasi utama">
  <div class="sidebar-header">
    <a class="brand-mark" href="<?= base_url('index.php') ?>" aria-label="<?= e(APP_NAME) ?>">
      <span class="brand-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span>
      <span class="brand-copy">
        <span class="brand-title"><?= e(APP_NAME) ?></span>
        <span class="brand-subtitle">Sistem Pemantauan Gizi</span>
      </span>
    </a>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($sidebar_menus as $item): ?>

      <?php if (($item['type'] ?? 'link') === 'label'): ?>
        <!-- Label pemisah grup menu -->
        <div class="nav-label"><?= e($item['nama']) ?></div>

      <?php else: ?>
        <!-- Item menu -->
        <a class="nav-link<?= ($current_slug === $item['slug']) ? ' active' : '' ?>"
           href="<?= $item['url'] ?>"
           <?= ($current_slug === $item['slug']) ? 'aria-current="page"' : '' ?>>
          <span class="nav-icon"><i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i></span>
          <span class="nav-text"><?= e($item['nama']) ?></span>
        </a>
      <?php endif; ?>

    <?php endforeach; ?>
  </nav>

  <div class="sidebar-user">
    <img class="avatar-img avatar-md sidebar-user-avatar"
         src="<?= asset('images/avatar/avatar.jpg') ?>"
         alt="<?= e($sidebar_user_nama) ?>">
    <strong><?= e($sidebar_user_nama) ?></strong>
    <small><?= e($sidebar_user_role) ?></small>
  </div>

  <div class="sidebar-footer">
    <span class="status-dot"></span>
    <span class="sidebar-footer-text"><?= e(APP_NAME) ?> v1.0</span>
  </div>
</aside>
