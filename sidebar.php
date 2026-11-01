<?php
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
?>
    <aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
      <div class="sidebar-header">
        <a class="brand-mark" href="daftar_pantau.php" aria-label="SiPantau Gizi">
          <span class="brand-icon"><i class="bi bi-heart-pulse-fill text-danger" aria-hidden="true"></i></span>
          <span class="brand-copy">
            <span class="brand-title">SiPantau Gizi</span>
            <span class="brand-subtitle">Desa Sejahtera</span>
          </span>
        </a>
      </div>

      <nav class="sidebar-nav">
        <a class="nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>" href="daftar_pantau.php">
          <span class="nav-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></span>
          <span class="nav-text">Dashboard</span>
        </a>
        <a class="nav-link <?= $current_page === 'data_anak.php' ? 'active' : '' ?>" href="data_anak.php">
          <span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
          <span class="nav-text">Data Anak</span>
        </a>
        <a class="nav-link <?= $current_page === 'input_pengukuran.php' ? 'active' : '' ?>" href="input_pengukuran.php">
          <span class="nav-icon"><i class="bi bi-rulers" aria-hidden="true"></i></span>
          <span class="nav-text">Input Pengukuran</span>
        </a>
        <a class="nav-link <?= $current_page === 'daftar_pantau.php' ? 'active' : '' ?>" href="daftar_pantau.php">
          <span class="nav-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
          <span class="nav-text">Daftar Pantau</span>
        </a>
        <a class="nav-link <?= $current_page === 'jadwal_kunjungan.php' ? 'active' : '' ?>" href="jadwal_kunjungan.php">
          <span class="nav-icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
          <span class="nav-text">Jadwal & Kunjungan</span>
        </a>
        <a class="nav-link <?= $current_page === 'laporan.php' ? 'active' : '' ?>" href="laporan.php">
          <span class="nav-icon"><i class="bi bi-file-earmark-text" aria-hidden="true"></i></span>
          <span class="nav-text">Laporan</span>
        </a>
        
        <hr class="dropdown-divider my-2">
        <!-- Pengaturan Akun -->
        <a class="nav-link <?= $current_page === 'profil.php' ? 'active' : '' ?>" href="profil.php">
          <span class="nav-icon"><i class="bi bi-person-circle" aria-hidden="true"></i></span>
          <span class="nav-text">Profil Saya</span>
        </a>

        <!-- Menu Admin / Bidan Khusus -->
        <a class="nav-link <?= $current_page === 'data_master.php' ? 'active' : '' ?>" href="data_master.php">
          <span class="nav-icon"><i class="bi bi-building" aria-hidden="true"></i></span>
          <span class="nav-text">Posyandu & Wilayah</span>
        </a>
        <a class="nav-link <?= $current_page === 'pengguna.php' ? 'active' : '' ?>" href="pengguna.php">
          <span class="nav-icon"><i class="bi bi-person-gear" aria-hidden="true"></i></span>
          <span class="nav-text">Pengguna</span>
        </a>
      </nav>

      <div class="sidebar-user">
        <a href="profil.php" class="text-decoration-none text-reset d-block w-100" title="Klik untuk kelola Profil Saya">
          <img class="avatar-img avatar-md sidebar-user-avatar" src="assets/images/avatar/avatar.jpg" alt="User">
          <div class="mt-1">
            <strong class="d-block text-truncate"><?= htmlspecialchars($user_header_nama ?? 'Bidan / Kader') ?></strong>
            <small class="d-block text-truncate"><?= htmlspecialchars($user_header_sub ?? 'Posyandu Melati') ?></small>
          </div>
        </a>
      </div>
    </aside>
