<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['id_user'])) {
    $_SESSION['id_user'] = 1;
}
$user_header_nama = 'Bidan / Kader';
$user_header_sub = 'Posyandu Melati';
if (isset($koneksi) && $koneksi) {
    $hid = (int)$_SESSION['id_user'];
    $qh = mysqli_query($koneksi, "SELECT u.nama, p.nama_posyandu, r.nama_role FROM user u LEFT JOIN posyandu p ON u.id_posyandu = p.id_posyandu LEFT JOIN role r ON u.id_role = r.id_role WHERE u.id_user = $hid LIMIT 1");
    if ($qh && $rh = mysqli_fetch_assoc($qh)) {
        $user_header_nama = $rh['nama'];
        $user_header_sub = !empty($rh['nama_posyandu']) ? $rh['nama_posyandu'] : ($rh['nama_role'] ?? 'Petugas');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="SiPantau Gizi - Sistem Pemantauan Gizi Terpadu">
  <title>SiPantau Gizi | Sistem Pemantauan Gizi</title>

  <!-- CSS Bawaan Template -->
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
  
  <!-- DataTables CSS untuk fitur Export Excel/PDF -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
</head>

<body>
  <div class="admin-shell">
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <!-- Panggil Sidebar -->
    <?php include 'sidebar.php'; ?>

    <div class="admin-main">
      <nav class="navbar admin-navbar navbar-expand bg-white border-bottom">
        <div class="container-fluid px-3 px-lg-4">
          <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="adminSidebar" aria-expanded="true" aria-label="Toggle sidebar">
            <span></span>
            <span></span>
            <span></span>
          </button>

          <form class="d-none d-md-flex ms-3 flex-grow-1" role="search">
            <input class="form-control search-input" type="search" placeholder="Cari data anak..." aria-label="Search">
          </form>

          <div class="navbar-actions ms-auto">
            <!-- Tombol Tema Gelap/Terang -->
            <button class="icon-button theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme" title="Switch color theme">
              <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
            </button>
            
            <!-- Notifikasi -->
            <div class="dropdown">
              <button class="icon-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                <span class="notification-dot"></span>
                <i class="bi bi-bell" aria-hidden="true"></i>
              </button>
              <div class="dropdown-menu dropdown-menu-end notification-menu">
                <div class="dropdown-header fw-bold text-body">Notifikasi Terbaru</div>
                <a class="dropdown-item" href="daftar_pantau.php">
                  <span class="notification-title text-danger">3 Anak berisiko stunting bulan ini</span>
                  <span class="notification-time">Baru saja</span>
                </a>
              </div>
            </div>

            <!-- Profil User -->
            <div class="dropdown">
              <button class="profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <img class="avatar-img avatar-sm" src="assets/images/avatar/avatar.jpg" alt="<?= htmlspecialchars($user_header_nama) ?>">
                <span class="profile-name d-none d-sm-inline"><?= htmlspecialchars($user_header_nama) ?></span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="profil.php"><i class="bi bi-person me-2"></i>Profil Saya</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="#"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
              </ul>
            </div>
          </div>
        </div>
      </nav>
