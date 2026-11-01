<?php
/**
 * Modul Profil Saya & Keamanan Akun — SiPantau Gizi
 * =========================================================================
 * Mengimplementasikan 3 fitur utama:
 * 1. Lihat Profil: Menampilkan data lengkap akun milik pengguna yang sedang login.
 * 2. Update Profil: Mengubah data diri (nama, kontak, username, email).
 * 3. Ubah Kata Sandi: Mengganti kata sandi dengan verifikasi kata sandi lama.
 *
 * Desain diselaraskan 100% dengan adminHMD template & design system.
 */

$current_slug = 'profil';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan user terautentikasi
$id_user = (int)($_SESSION['user_id'] ?? 0);
if ($id_user <= 0) {
    set_flash('warning', 'Silakan login terlebih dahulu untuk mengakses profil.');
    redirect(base_url('modules/auth/login.php'));
}

$page_title  = 'Profil Saya';
$breadcrumbs = [
    'Pengaturan Akun' => '#',
    'Profil Saya'     => '',
];

$active_tab = $_GET['tab'] ?? 'profil';

// ============================================================
// 1. PROSES UPDATE PROFIL (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $active_tab = 'profil';

    // Verifikasi CSRF Token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf($csrf_token)) {
        set_flash('danger', 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.');
        redirect(base_url('modules/profil/index.php?tab=profil'));
    }

    $nama       = trim($_POST['nama'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $no_telepon = trim($_POST['no_telepon'] ?? '');

    // Validasi Kolom Wajib
    if ($nama === '' || $username === '' || $email === '') {
        set_flash('danger', 'Nama lengkap, username, dan email wajib diisi.');
        redirect(base_url('modules/profil/index.php?tab=profil'));
    }

    // Validasi Format Email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('danger', 'Format alamat email tidak valid.');
        redirect(base_url('modules/profil/index.php?tab=profil'));
    }

    // Validasi Format Username (alfanumerik, underscore, titik)
    if (!preg_match('/^[a-zA-Z0-9_.]+$/', $username)) {
        set_flash('danger', 'Username hanya boleh memuat huruf, angka, garis bawah (_), dan titik (.).');
        redirect(base_url('modules/profil/index.php?tab=profil'));
    }

    try {
        // Cek duplikasi username pada user lain
        $stmt_check = $pdo->prepare("SELECT id_user FROM user WHERE username = ? AND id_user != ? LIMIT 1");
        $stmt_check->execute([$username, $id_user]);
        if ($stmt_check->fetch()) {
            set_flash('danger', "Username <strong>" . e($username) . "</strong> sudah digunakan oleh pengguna lain. Silakan pilih username lain.");
            redirect(base_url('modules/profil/index.php?tab=profil'));
        }

        // Cek duplikasi email pada user lain
        $stmt_email = $pdo->prepare("SELECT id_user FROM user WHERE email = ? AND id_user != ? LIMIT 1");
        $stmt_email->execute([$email, $id_user]);
        if ($stmt_email->fetch()) {
            set_flash('danger', "Alamat email <strong>" . e($email) . "</strong> sudah terdaftar pada akun lain.");
            redirect(base_url('modules/profil/index.php?tab=profil'));
        }

        // Eksekusi Update Profil ke Database
        $stmt_update = $pdo->prepare("
            UPDATE user 
            SET nama = ?, username = ?, email = ?, no_telepon = ?, update_at = NOW() 
            WHERE id_user = ?
        ");
        $stmt_update->execute([$nama, $username, $email, $no_telepon, $id_user]);

        // Perbarui data di sesi aktif
        $_SESSION['user_nama']     = $nama;
        $_SESSION['user_username'] = $username;
        $_SESSION['user_email']    = $email;

        set_flash('success', 'Data profil Anda berhasil diperbarui!');
        redirect(base_url('modules/profil/index.php?tab=profil'));
    } catch (PDOException $e) {
        set_flash('danger', 'Terjadi kesalahan sistem saat memperbarui profil: ' . $e->getMessage());
        redirect(base_url('modules/profil/index.php?tab=profil'));
    }
}

// ============================================================
// 2. PROSES UBAH KATA SANDI (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    $active_tab = 'password';

    // Verifikasi CSRF Token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf($csrf_token)) {
        set_flash('danger', 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.');
        redirect(base_url('modules/profil/index.php?tab=password'));
    }

    $password_lama       = $_POST['password_lama'] ?? '';
    $password_baru       = $_POST['password_baru'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

    // Validasi Kolom
    if ($password_lama === '' || $password_baru === '' || $konfirmasi_password === '') {
        set_flash('danger', 'Semua kolom kata sandi wajib diisi.');
        redirect(base_url('modules/profil/index.php?tab=password'));
    }

    if (strlen($password_baru) < 6) {
        set_flash('danger', 'Kata sandi baru minimal harus terdiri dari 6 karakter.');
        redirect(base_url('modules/profil/index.php?tab=password'));
    }

    if ($password_baru !== $konfirmasi_password) {
        set_flash('danger', 'Konfirmasi kata sandi baru tidak cocok. Mohon ulangi kembali.');
        redirect(base_url('modules/profil/index.php?tab=password'));
    }

    if ($password_baru === $password_lama) {
        set_flash('warning', 'Kata sandi baru tidak boleh sama dengan kata sandi saat ini.');
        redirect(base_url('modules/profil/index.php?tab=password'));
    }

    try {
        // Ambil hash password saat ini
        $stmt_pwd = $pdo->prepare("SELECT password_hash FROM user WHERE id_user = ? LIMIT 1");
        $stmt_pwd->execute([$id_user]);
        $row_pwd = $stmt_pwd->fetch(PDO::FETCH_ASSOC);

        $current_hash = $row_pwd['password_hash'] ?? '';

        // Verifikasi kata sandi lama (mendukung bcrypt dan legacy fallback)
        $password_lama_valid = false;
        if (password_verify($password_lama, $current_hash)) {
            $password_lama_valid = true;
        } elseif ($password_lama === $current_hash) {
            $password_lama_valid = true;
        }

        if (!$password_lama_valid) {
            set_flash('danger', 'Kata sandi lama / saat ini yang Anda masukkan salah.');
            redirect(base_url('modules/profil/index.php?tab=password'));
        }

        // Buat hash bcrypt baru
        $new_hash = password_hash($password_baru, PASSWORD_DEFAULT);

        // Simpan ke database
        $stmt_save_pwd = $pdo->prepare("UPDATE user SET password_hash = ?, update_at = NOW() WHERE id_user = ?");
        $stmt_save_pwd->execute([$new_hash, $id_user]);

        set_flash('success', 'Kata sandi Anda berhasil diperbarui! Silakan gunakan kata sandi baru untuk login selanjutnya.');
        redirect(base_url('modules/profil/index.php?tab=password'));
    } catch (PDOException $e) {
        set_flash('danger', 'Terjadi kesalahan sistem saat memperbarui kata sandi: ' . $e->getMessage());
        redirect(base_url('modules/profil/index.php?tab=password'));
    }
}

// ============================================================
// 3. AMBIL DATA USER LENGKAP & STATISTIK AKTIVITAS
// ============================================================
$user = null;
$total_pengukuran = 0;
$total_intervensi = 0;

try {
    $sql_user = "
        SELECT 
            u.*, 
            r.nama_role, 
            r.deskripsi AS deskripsi_role,
            p.nama_posyandu, 
            p.alamat AS alamat_posyandu,
            pk.nama_puskesmas, 
            w.nama_wilayah, 
            w.kecamatan, 
            w.kabupaten 
        FROM user u 
        LEFT JOIN role r ON u.id_role = r.id_role 
        LEFT JOIN posyandu p ON u.id_posyandu = p.id_posyandu 
        LEFT JOIN puskesmas pk ON (u.id_puskesmas = pk.id_puskesmas OR p.id_puskesmas = pk.id_puskesmas) 
        LEFT JOIN wilayah w ON (u.id_wilayah = w.id_wilayah OR p.id_wilayah = w.id_wilayah) 
        WHERE u.id_user = ? 
        LIMIT 1
    ";

    $stmt_u = $pdo->prepare($sql_user);
    $stmt_u->execute([$id_user]);
    $user = $stmt_u->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        set_flash('danger', 'Data pengguna tidak ditemukan di database.');
        redirect(base_url('modules/dashboard/index.php'));
    }

    // Hitung total penimbangan/pengukuran yang diinput oleh user ini
    $stmt_m = $pdo->prepare("SELECT COUNT(*) FROM pengukuran WHERE id_user = ?");
    $stmt_m->execute([$id_user]);
    $total_pengukuran = (int)$stmt_m->fetchColumn();

    // Hitung total intervensi yang dicatat oleh user ini
    $stmt_i = $pdo->prepare("SELECT COUNT(*) FROM catatan_intervensi WHERE id_user = ?");
    $stmt_i->execute([$id_user]);
    $total_intervensi = (int)$stmt_i->fetchColumn();

} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat profil: ' . $e->getMessage());
}

// CSS Khusus untuk Tampilan Profil adminHMD di Light & Dark Mode
$extra_css = '
<style>
  .profile-cover-gradient {
    height: 105px;
    background: linear-gradient(135deg, #1e40af 0%, #2563eb 50%, #38bdf8 100%);
    border-radius: 8px 8px 0 0;
    margin: -1.35rem -1.35rem 0.9rem;
  }
  .profile-avatar-wrap {
    margin-top: -55px;
    position: relative;
    display: inline-block;
  }
  .profile-avatar-img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border: 4px solid var(--admin-surface);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
  }
  .info-list-custom {
    display: grid;
    gap: 0.65rem;
  }
  .info-list-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    border: 1px solid var(--admin-border);
    border-radius: 6px;
    background: var(--admin-surface-soft);
    font-size: 0.85rem;
  }
  .info-list-item span {
    color: var(--admin-muted);
    font-size: 0.82rem;
  }
  .info-list-item strong {
    color: var(--admin-text);
    font-weight: 600;
    text-align: right;
  }
  .stat-card-profile {
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    background: var(--admin-surface-soft);
    padding: 0.85rem 0.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
  }
  .stat-card-profile:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
  }
  .stat-icon {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    margin-bottom: 0.45rem;
    flex-shrink: 0;
  }
  /* Tab styling */
  .profile-tabs .nav-link {
    color: var(--admin-muted);
    font-weight: 600;
    font-size: 0.9rem;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 0.75rem 1.25rem;
  }
  .profile-tabs .nav-link:hover {
    color: var(--admin-primary);
    border-color: transparent;
  }
  .profile-tabs .nav-link.active {
    color: var(--admin-primary);
    background: transparent;
    border-bottom: 2px solid var(--admin-primary);
  }
  /* Dark mode overrides */
  html[data-theme="dark"] .profile-avatar-img {
    border-color: var(--admin-surface);
  }
  html[data-theme="dark"] .info-list-item {
    background-color: var(--admin-surface-soft) !important;
    border-color: var(--admin-border) !important;
  }
  html[data-theme="dark"] .stat-card-profile {
    background-color: var(--admin-surface-soft) !important;
    border-color: var(--admin-border) !important;
  }
  html[data-theme="dark"] .form-control:disabled,
  html[data-theme="dark"] .form-control[readonly] {
    background-color: rgba(30, 41, 59, 0.7) !important;
    border-color: var(--admin-border) !important;
    color: var(--admin-muted) !important;
  }
</style>
';

// Include Header Layout
require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-person-circle text-primary" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Pengaturan Akun</p>
      <h1 class="h3 mb-1">Profil Saya</h1>
      <p class="text-muted mb-0">Kelola data pribadi, informasi kontak, dan keamanan kata sandi akun Anda.</p>
    </div>
  </div>
  <div class="heading-actions d-flex gap-2">
    <a href="<?= base_url('modules/dashboard/index.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
    </a>
  </div>
</div>

<div class="row g-3 mt-1">

  <!-- ============================================================ -->
  <!-- KOLOM KIRI: LIHAT PROFIL (RINGKASAN AKUN & AKTIVITAS)        -->
  <!-- ============================================================ -->
  <div class="col-12 col-xl-4 col-lg-5">
    
    <!-- Kartu Identitas Profil -->
    <div class="panel h-100 text-center profile-card position-relative overflow-hidden shadow-sm">
      <!-- Banner Gradient Header -->
      <div class="profile-cover-gradient"></div>

      <!-- Avatar & Indikator Status -->
      <div class="profile-avatar-wrap">
        <img class="avatar-img rounded-circle profile-avatar-img"
             src="<?= asset('images/avatar/avatar.jpg') ?>" 
             alt="<?= e($user['nama']) ?>">
        <span class="position-absolute bottom-0 end-0 bg-success border border-2 border-white rounded-circle"
              style="width: 18px; height: 18px;" 
              title="Akun Aktif"></span>
      </div>

      <!-- Nama & Username -->
      <h2 class="h5 mt-3 mb-1 fw-bold" style="color: var(--admin-text);"><?= e($user['nama']) ?></h2>
      <p class="text-muted small mb-2 font-monospace">@<?= e($user['username']) ?></p>

      <!-- Badges Role & Status -->
      <div class="d-flex justify-content-center flex-wrap gap-2 mb-3">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1">
          <i class="bi bi-person-fill-gear me-1"></i><?= e($user['nama_role'] ?? 'Pengguna') ?>
        </span>
        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1">
          <i class="bi bi-check-circle me-1"></i><?= e($user['status'] ?? 'Aktif') ?>
        </span>
      </div>

      <!-- Ringkasan Statistik Kontribusi Pengguna -->
      <div class="row g-2 mb-3">
        <div class="col-6">
          <div class="stat-card-profile">
            <span class="stat-icon bg-primary-subtle text-primary">
              <i class="bi bi-clipboard2-pulse"></i>
            </span>
            <div class="h4 mb-0 fw-bold" style="color: var(--admin-text); line-height: 1.1;">
              <?= number_format($total_pengukuran) ?>
            </div>
            <span class="text-muted fw-semibold text-uppercase d-block mt-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
              Pengukuran
            </span>
          </div>
        </div>
        <div class="col-6">
          <div class="stat-card-profile">
            <span class="stat-icon bg-info-subtle text-info">
              <i class="bi bi-heart-pulse"></i>
            </span>
            <div class="h4 mb-0 fw-bold" style="color: var(--admin-text); line-height: 1.1;">
              <?= number_format($total_intervensi) ?>
            </div>
            <span class="text-muted fw-semibold text-uppercase d-block mt-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
              Intervensi
            </span>
          </div>
        </div>
      </div>

      <hr class="my-3" style="border-color: var(--admin-border);">

      <!-- Daftar Rincian Informasi Akun -->
      <div class="info-list-custom text-start">
        <div class="info-list-item">
          <span><i class="bi bi-envelope me-1 text-primary"></i> Email</span>
          <strong class="text-break"><?= e($user['email'] ?: '—') ?></strong>
        </div>
        <div class="info-list-item">
          <span><i class="bi bi-telephone me-1 text-success"></i> No. Telepon</span>
          <strong><?= e($user['no_telepon'] ?: '—') ?></strong>
        </div>
        <div class="info-list-item">
          <span><i class="bi bi-hospital me-1 text-danger"></i> Posyandu</span>
          <strong><?= e($user['nama_posyandu'] ?: 'Semua / Lintas Posyandu') ?></strong>
        </div>
        <div class="info-list-item">
          <span><i class="bi bi-building me-1 text-info"></i> Puskesmas</span>
          <strong><?= e($user['nama_puskesmas'] ?: 'Dinas Kesehatan') ?></strong>
        </div>
        <div class="info-list-item">
          <span><i class="bi bi-geo-alt me-1 text-warning"></i> Wilayah</span>
          <strong>
            <?php 
              $loc = array_filter([$user['nama_wilayah'], $user['kecamatan'], $user['kabupaten']]);
              echo e(!empty($loc) ? implode(', ', $loc) : 'Kota Depok');
            ?>
          </strong>
        </div>
        <div class="info-list-item">
          <span><i class="bi bi-calendar3 me-1 text-secondary"></i> Bergabung</span>
          <strong><?= !empty($user['created_at']) ? date('d M Y', strtotime($user['created_at'])) : '—' ?></strong>
        </div>
      </div>
    </div>
  </div>

  <!-- ============================================================ -->
  <!-- KOLOM KANAN: TABBED FORMS (UPDATE PROFIL & UBAH KATA SANDI)  -->
  <!-- ============================================================ -->
  <div class="col-12 col-xl-8 col-lg-7">
    <div class="panel shadow-sm">
      
      <!-- Nav Tabs -->
      <ul class="nav profile-tabs border-bottom mb-4" id="profileTab" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $active_tab !== 'password' ? 'active' : '' ?>" 
                  id="tab-profil-btn" 
                  data-bs-toggle="tab" 
                  data-bs-target="#tab-profil-pane" 
                  type="button" 
                  role="tab" 
                  aria-controls="tab-profil-pane" 
                  aria-selected="<?= $active_tab !== 'password' ? 'true' : 'false' ?>">
            <i class="bi bi-person-lines-fill me-2"></i>Data Profil & Kontak
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $active_tab === 'password' ? 'active' : '' ?>" 
                  id="tab-password-btn" 
                  data-bs-toggle="tab" 
                  data-bs-target="#tab-password-pane" 
                  type="button" 
                  role="tab" 
                  aria-controls="tab-password-pane" 
                  aria-selected="<?= $active_tab === 'password' ? 'true' : 'false' ?>">
            <i class="bi bi-shield-lock me-2"></i>Ubah Kata Sandi
          </button>
        </li>
      </ul>

      <div class="tab-content" id="profileTabContent">

        <!-- ======================================================== -->
        <!-- TAB 1: FORM PERBARUI PROFIL & KONTAK                    -->
        <!-- ======================================================== -->
        <div class="tab-pane fade <?= $active_tab !== 'password' ? 'show active' : '' ?>" 
             id="tab-profil-pane" 
             role="tabpanel" 
             aria-labelledby="tab-profil-btn" 
             tabindex="0">
          
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
              <h2 class="h6 fw-bold mb-1" style="color: var(--admin-text);">
                <i class="bi bi-person-gear text-primary me-2"></i>Perbarui Informasi Profil
              </h2>
              <p class="text-muted small mb-0">Ubah nama lengkap, username login, email resmi, dan nomor kontak Anda.</p>
            </div>
          </div>

          <form method="POST" action="<?= base_url('modules/profil/index.php') ?>" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">

            <div class="row g-3">
              <!-- Nama Lengkap -->
              <div class="col-md-6">
                <label for="nama" class="form-label small fw-semibold">
                  Nama Lengkap <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-person"></i></span>
                  <input type="text" class="form-control" id="nama" name="nama" 
                         value="<?= e($user['nama']) ?>" required 
                         placeholder="Contoh: Siti Rahmawati">
                </div>
                <div class="form-text" style="font-size: 0.75rem;">Nama yang akan tampil pada catatan pengukuran & intervensi.</div>
              </div>

              <!-- Username -->
              <div class="col-md-6">
                <label for="username" class="form-label small fw-semibold">
                  Username Akun <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-at"></i></span>
                  <input type="text" class="form-control" id="username" name="username" 
                         value="<?= e($user['username']) ?>" required 
                         placeholder="Contoh: sitir_kader">
                </div>
                <div class="form-text" style="font-size: 0.75rem;">Digunakan untuk masuk (login) ke dalam sistem SiPantau Gizi.</div>
              </div>

              <!-- Alamat Email -->
              <div class="col-md-6">
                <label for="email" class="form-label small fw-semibold">
                  Alamat Email <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                  <input type="email" class="form-control" id="email" name="email" 
                         value="<?= e($user['email']) ?>" required 
                         placeholder="nama@email.com">
                </div>
                <div class="form-text" style="font-size: 0.75rem;">Email aktif untuk keperluan notifikasi sistem.</div>
              </div>

              <!-- Nomor Telepon / WhatsApp -->
              <div class="col-md-6">
                <label for="no_telepon" class="form-label small fw-semibold">
                  Nomor Telepon / WhatsApp
                </label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                  <input type="text" class="form-control" id="no_telepon" name="no_telepon" 
                         value="<?= e($user['no_telepon']) ?>" 
                         placeholder="Contoh: 081234567890">
                </div>
                <div class="form-text" style="font-size: 0.75rem;">Nomor kontak aktif yang dapat dihubungi kader/dinkes.</div>
              </div>

              <!-- Pembatas Readonly Info Akun -->
              <div class="col-12 mt-4">
                <div class="p-3 rounded border" style="background-color: var(--admin-surface-soft); border-color: var(--admin-border) !important;">
                  <h6 class="small fw-bold mb-2 text-primary">
                    <i class="bi bi-info-circle-fill me-1"></i> Informasi Penugasan Akun
                  </h6>
                  <div class="row g-2 small text-muted">
                    <div class="col-md-4">
                      <span>Peran / Role:</span>
                      <strong class="d-block" style="color: var(--admin-text);"><?= e($user['nama_role'] ?? '—') ?></strong>
                    </div>
                    <div class="col-md-4">
                      <span>Unit Posyandu:</span>
                      <strong class="d-block" style="color: var(--admin-text);"><?= e($user['nama_posyandu'] ?: 'Lintas Posyandu') ?></strong>
                    </div>
                    <div class="col-md-4">
                      <span>Puskesmas Pembina:</span>
                      <strong class="d-block" style="color: var(--admin-text);"><?= e($user['nama_puskesmas'] ?: 'Dinas Kesehatan') ?></strong>
                    </div>
                  </div>
                  <small class="text-muted d-block mt-2 fst-italic" style="font-size: 0.75rem;">
                    * Penyesuaian hak akses (role) atau mutasi unit kerja dikelola oleh Administrator Dinas Kesehatan.
                  </small>
                </div>
              </div>

              <!-- Tombol Simpan Profil -->
              <div class="col-12 text-end mt-4">
                <button type="submit" class="btn btn-primary px-4">
                  <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan Profil
                </button>
              </div>
            </div>
          </form>
        </div>

        <!-- ======================================================== -->
        <!-- TAB 2: FORM UBAH KATA SANDI                             -->
        <!-- ======================================================== -->
        <div class="tab-pane fade <?= $active_tab === 'password' ? 'show active' : '' ?>" 
             id="tab-password-pane" 
             role="tabpanel" 
             aria-labelledby="tab-password-btn" 
             tabindex="0">
          
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
              <h2 class="h6 fw-bold mb-1" style="color: var(--admin-text);">
                <i class="bi bi-key-fill text-warning me-2"></i>Pengaturan Kata Sandi Akun
              </h2>
              <p class="text-muted small mb-0">Pastikan akun Anda tetap aman dengan menggunakan kata sandi yang kuat.</p>
            </div>
          </div>

          <!-- Petunjuk Keamanan Kata Sandi -->
          <div class="alert alert-info py-2 px-3 small d-flex align-items-center mb-4">
            <i class="bi bi-shield-check fs-5 me-2 text-info"></i>
            <div>
              Kata sandi baru harus minimal <strong>6 karakter</strong>. Kami sarankan kombinasi huruf besar, huruf kecil, dan angka.
            </div>
          </div>

          <form method="POST" action="<?= base_url('modules/profil/index.php') ?>" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_password">

            <div class="row g-3">
              <!-- Kata Sandi Lama / Saat Ini -->
              <div class="col-12">
                <label for="password_lama" class="form-label small fw-semibold">
                  Kata Sandi Saat Ini <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-lock"></i></span>
                  <input type="password" class="form-control" id="password_lama" name="password_lama" 
                         required placeholder="Masukkan kata sandi yang Anda gunakan saat ini">
                  <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('password_lama', 'icon-pass-lama')">
                    <i class="bi bi-eye" id="icon-pass-lama"></i>
                  </button>
                </div>
                <div class="form-text" style="font-size: 0.75rem;">Verifikasi kepemilikan akun sebelum mengubah kata sandi.</div>
              </div>

              <!-- Kata Sandi Baru -->
              <div class="col-md-6">
                <label for="password_baru" class="form-label small fw-semibold">
                  Kata Sandi Baru <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-key"></i></span>
                  <input type="password" class="form-control" id="password_baru" name="password_baru" 
                         required minlength="6" placeholder="Minimal 6 karakter">
                  <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('password_baru', 'icon-pass-baru')">
                    <i class="bi bi-eye" id="icon-pass-baru"></i>
                  </button>
                </div>
                <div class="form-text" style="font-size: 0.75rem;">Kata sandi baru tidak boleh sama dengan kata sandi saat ini.</div>
              </div>

              <!-- Konfirmasi Kata Sandi Baru -->
              <div class="col-md-6">
                <label for="konfirmasi_password" class="form-label small fw-semibold">
                  Konfirmasi Kata Sandi Baru <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-check2-square"></i></span>
                  <input type="password" class="form-control" id="konfirmasi_password" name="konfirmasi_password" 
                         required minlength="6" placeholder="Ulangi kata sandi baru">
                  <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('konfirmasi_password', 'icon-pass-konfirm')">
                    <i class="bi bi-eye" id="icon-pass-konfirm"></i>
                  </button>
                </div>
                <div class="form-text" style="font-size: 0.75rem;">Pastikan penulisan cocok persis dengan kata sandi baru.</div>
              </div>

              <!-- Tombol Simpan Kata Sandi -->
              <div class="col-12 text-end mt-4">
                <button type="submit" class="btn btn-primary px-4">
                  <i class="bi bi-lock-fill me-1"></i> Perbarui Kata Sandi
                </button>
              </div>
            </div>
          </form>
        </div>

      </div>
    </div>
  </div>

</div>

<!-- Script Pembantu Tampilkan/Sembunyikan Kata Sandi -->
<script>
function togglePassVisibility(inputId, iconId) {
  const input = document.getElementById(inputId);
  const icon = document.getElementById(iconId);
  if (!input || !icon) return;

  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('bi-eye');
    icon.classList.add('bi-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.remove('bi-eye-slash');
    icon.classList.add('bi-eye');
  }
}
</script>

<?php
// Include Footer Layout
require_once ROOT_PATH . '/includes/footer.php';
?>

