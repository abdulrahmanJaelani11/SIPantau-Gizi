<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'koneksi.php';

// Jika session user belum ada, set default id_user = 1 (Siti Kader) agar tetap berjalan lancar
if (!isset($_SESSION['id_user'])) {
    $_SESSION['id_user'] = 1;
}

$id_user = (int)$_SESSION['id_user'];
$active_tab = 'profil'; // Tab default

// Variabel notifikasi
$alert_type = '';
$alert_message = '';

// ==========================================
// 1. PROSES UPDATE PROFIL
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_profile'])) {
    $active_tab = 'profil';
    
    $nama       = trim($_POST['nama'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $no_telepon = trim($_POST['no_telepon'] ?? '');

    // Validasi Input
    if (empty($nama) || empty($username) || empty($email)) {
        $alert_type = 'danger';
        $alert_message = 'Nama lengkap, username, dan email wajib diisi!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alert_type = 'danger';
        $alert_message = 'Format alamat email tidak valid!';
    } else {
        // Cek apakah username sudah digunakan oleh user lain
        $stmt_check = mysqli_prepare($koneksi, "SELECT id_user FROM user WHERE username = ? AND id_user != ?");
        mysqli_stmt_bind_param($stmt_check, "si", $username, $id_user);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);

        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $alert_type = 'danger';
            $alert_message = "Username '<strong>" . htmlspecialchars($username) . "</strong>' sudah digunakan oleh pengguna lain. Silakan pilih username lain.";
            mysqli_stmt_close($stmt_check);
        } else {
            mysqli_stmt_close($stmt_check);

            // Update data profil ke database
            $stmt_update = mysqli_prepare($koneksi, "UPDATE user SET nama = ?, username = ?, email = ?, no_telepon = ? WHERE id_user = ?");
            mysqli_stmt_bind_param($stmt_update, "ssssi", $nama, $username, $email, $no_telepon, $id_user);

            if (mysqli_stmt_execute($stmt_update)) {
                $alert_type = 'success';
                $alert_message = 'Data profil Anda berhasil diperbarui!';
                $_SESSION['nama'] = $nama;
                $_SESSION['username'] = $username;
            } else {
                $alert_type = 'danger';
                $alert_message = 'Gagal memperbarui profil: ' . mysqli_error($koneksi);
            }
            mysqli_stmt_close($stmt_update);
        }
    }
}

// ==========================================
// 2. PROSES UBAH KATA SANDI
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_password'])) {
    $active_tab = 'password';

    $password_lama        = $_POST['password_lama'] ?? '';
    $password_baru        = $_POST['password_baru'] ?? '';
    $konfirmasi_password  = $_POST['konfirmasi_password'] ?? '';

    // Ambil password saat ini dari database
    $stmt_pass = mysqli_prepare($koneksi, "SELECT password_hash FROM user WHERE id_user = ?");
    mysqli_stmt_bind_param($stmt_pass, "i", $id_user);
    mysqli_stmt_execute($stmt_pass);
    $res_pass = mysqli_stmt_get_result($stmt_pass);
    $user_pass = mysqli_fetch_assoc($res_pass);
    mysqli_stmt_close($stmt_pass);

    $current_hash = $user_pass['password_hash'] ?? '';

    // Validasi Password Lama (mendukung modern password_verify maupun plain text dummy awal)
    $password_lama_valid = false;
    if (password_verify($password_lama, $current_hash)) {
        $password_lama_valid = true;
    } elseif ($password_lama === $current_hash) {
        // Fallback untuk password dummy lama yang belum di-hash
        $password_lama_valid = true;
    }

    if (empty($password_lama) || empty($password_baru) || empty($konfirmasi_password)) {
        $alert_type = 'danger';
        $alert_message = 'Semua kolom kata sandi wajib diisi!';
    } elseif (!$password_lama_valid) {
        $alert_type = 'danger';
        $alert_message = 'Kata sandi saat ini yang Anda masukkan salah!';
    } elseif (strlen($password_baru) < 6) {
        $alert_type = 'danger';
        $alert_message = 'Kata sandi baru minimal harus terdiri dari 6 karakter!';
    } elseif ($password_baru !== $konfirmasi_password) {
        $alert_type = 'danger';
        $alert_message = 'Konfirmasi kata sandi baru tidak cocok!';
    } elseif ($password_baru === $password_lama) {
        $alert_type = 'warning';
        $alert_message = 'Kata sandi baru tidak boleh sama dengan kata sandi saat ini!';
    } else {
        // Enkripsi password baru menggunakan bcrypt
        $new_hash = password_hash($password_baru, PASSWORD_DEFAULT);

        $stmt_up_pass = mysqli_prepare($koneksi, "UPDATE user SET password_hash = ? WHERE id_user = ?");
        mysqli_stmt_bind_param($stmt_up_pass, "si", $new_hash, $id_user);

        if (mysqli_stmt_execute($stmt_up_pass)) {
            $alert_type = 'success';
            $alert_message = 'Kata sandi Anda berhasil diperbarui! Silakan gunakan kata sandi baru untuk login selanjutnya.';
        } else {
            $alert_type = 'danger';
            $alert_message = 'Gagal memperbarui kata sandi: ' . mysqli_error($koneksi);
        }
        mysqli_stmt_close($stmt_up_pass);
    }
}

// ==========================================
// 3. AMBIL DATA USER LENGKAP DARI DATABASE
// ==========================================
$sql_user = "SELECT 
                u.*, 
                r.nama_role, 
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
             LIMIT 1";

$stmt_user = mysqli_prepare($koneksi, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $id_user);
mysqli_stmt_execute($stmt_user);
$res_user = mysqli_stmt_get_result($stmt_user);
$user = mysqli_fetch_assoc($res_user);
mysqli_stmt_close($stmt_user);

if (!$user) {
    die("Pengguna tidak ditemukan.");
}

// Hitung data aktivitas user
$q_ukur = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM pengukuran WHERE id_user = $id_user");
$total_pengukuran = mysqli_fetch_assoc($q_ukur)['total'] ?? 0;

$q_intervensi = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM catatan_intervensi WHERE id_user = $id_user");
$total_intervensi = mysqli_fetch_assoc($q_intervensi)['total'] ?? 0;

include 'header.php';
?>

<main class="dashboard-content">
  <div class="container-fluid px-3 px-lg-4 py-4">

    <!-- Page Heading -->
    <div class="page-heading mb-4">
      <div class="page-heading-copy">
        <span class="page-icon"><i class="bi bi-person-badge-fill text-primary" aria-hidden="true"></i></span>
        <div>
          <p class="eyebrow mb-1">Pengaturan Akun</p>
          <h1 class="h3 mb-1">Profil Saya</h1>
          <p class="text-muted mb-0">Kelola informasi pribadi, kontak, dan pengaturan keamanan kata sandi akun Anda.</p>
        </div>
      </div>
      <div class="page-heading-actions">
        <a href="daftar_pantau.php" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
        </a>
      </div>
    </div>

    <!-- Alert Notifikasi -->
    <?php if (!empty($alert_message)): ?>
      <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm" role="alert">
        <i class="bi <?= $alert_type === 'success' ? 'bi-check-circle-fill text-success' : ($alert_type === 'warning' ? 'bi-exclamation-triangle-fill text-warning' : 'bi-x-circle-fill text-danger') ?> fs-4 me-3"></i>
        <div class="flex-grow-1">
          <?= $alert_message ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <div class="row g-4">
      
      <!-- ============================================== -->
      <!-- BAGIAN 1: LIHAT PROFIL (CARD RINGKASAN AKUN) -->
      <!-- ============================================== -->
      <div class="col-12 col-xl-4 col-lg-5">
        
        <!-- Card Profil Utama -->
        <div class="panel h-100 text-center profile-card position-relative overflow-hidden shadow-sm">
          <!-- Cover Banner Gradient -->
          <div class="profile-cover-gradient" style="height: 110px; background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #38bdf8 100%);"></div>
          
          <div class="px-4 pb-4">
            <!-- Avatar Foto Profil -->
            <div class="position-relative d-inline-block" style="margin-top: -55px;">
              <img class="avatar-img avatar-xl rounded-circle border border-4 border-white shadow" 
                   src="assets/images/avatar/avatar.jpg" 
                   alt="<?= htmlspecialchars($user['nama']) ?>"
                   style="width: 105px; height: 105px; object-fit: cover;">
              <span class="position-absolute bottom-0 end-0 bg-success border border-2 border-white rounded-circle p-2" 
                    title="Akun Aktif" style="width: 18px; height: 18px;"></span>
            </div>

            <!-- Nama & Username -->
            <h2 class="h5 mt-3 mb-1 fw-bold text-dark"><?= htmlspecialchars($user['nama']) ?></h2>
            <p class="text-muted small mb-2">@<?= htmlspecialchars($user['username']) ?></p>

            <!-- Badges Role & Status -->
            <div class="d-flex justify-content-center flex-wrap gap-2 mb-3">
              <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1">
                <i class="bi bi-person-fill-gear me-1"></i><?= htmlspecialchars($user['nama_role'] ?? 'Petugas') ?>
              </span>
              <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1">
                <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($user['status']) ?>
              </span>
            </div>

            <hr class="my-3 text-muted">

            <!-- Detail Informasi Akun (Info List) -->
            <div class="text-start">
              <div class="mb-3 d-flex align-items-start gap-2">
                <i class="bi bi-envelope text-primary mt-1"></i>
                <div class="flex-grow-1">
                  <small class="text-muted d-block">Email</small>
                  <strong class="text-dark small text-break"><?= htmlspecialchars($user['email'] ?? '-') ?></strong>
                </div>
              </div>

              <div class="mb-3 d-flex align-items-start gap-2">
                <i class="bi bi-telephone text-primary mt-1"></i>
                <div class="flex-grow-1">
                  <small class="text-muted d-block">No. Telepon / WhatsApp</small>
                  <?php if (!empty($user['no_telepon'])): ?>
                    <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $user['no_telepon'])) ?>" target="_blank" class="text-decoration-none small fw-semibold text-primary">
                      <?= htmlspecialchars($user['no_telepon']) ?> <i class="bi bi-box-arrow-up-right small"></i>
                    </a>
                  <?php else: ?>
                    <span class="text-muted small fst-italic">Belum diatur</span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="mb-3 d-flex align-items-start gap-2">
                <i class="bi bi-building text-primary mt-1"></i>
                <div class="flex-grow-1">
                  <small class="text-muted d-block">Posyandu Binaan</small>
                  <strong class="text-dark small"><?= htmlspecialchars($user['nama_posyandu'] ?? 'Tidak Terkait') ?></strong>
                </div>
              </div>

              <div class="mb-3 d-flex align-items-start gap-2">
                <i class="bi bi-geo-alt text-primary mt-1"></i>
                <div class="flex-grow-1">
                  <small class="text-muted d-block">Puskesmas & Wilayah</small>
                  <strong class="text-dark small">
                    <?= htmlspecialchars($user['nama_puskesmas'] ?? '-') ?>
                    <?php if (!empty($user['nama_wilayah'])): ?>
                      <br><span class="text-muted fw-normal"><?= htmlspecialchars($user['nama_wilayah']) ?>, Kec. <?= htmlspecialchars($user['kecamatan'] ?? '-') ?></span>
                    <?php endif; ?>
                  </strong>
                </div>
              </div>

              <div class="mb-2 d-flex align-items-start gap-2">
                <i class="bi bi-calendar3 text-primary mt-1"></i>
                <div class="flex-grow-1">
                  <small class="text-muted d-block">Terdaftar Sejak</small>
                  <span class="text-dark small"><?= !empty($user['created_at']) ? date('d F Y', strtotime($user['created_at'])) : '-' ?></span>
                </div>
              </div>
            </div>

            <!-- Kartu Aktivitas Ringkas -->
            <div class="mt-4 pt-3 border-top">
              <h6 class="text-muted text-uppercase small fw-bold mb-3 text-start">
                <i class="bi bi-activity me-1"></i> Aktivitas Anda
              </h6>
              <div class="row g-2 text-center">
                <div class="col-6">
                  <div class="p-2 rounded bg-light border">
                    <div class="fs-4 fw-bold text-primary"><?= $total_pengukuran ?></div>
                    <small class="text-muted" style="font-size: 0.75rem;">Pengukuran Dicatat</small>
                  </div>
                </div>
                <div class="col-6">
                  <div class="p-2 rounded bg-light border">
                    <div class="fs-4 fw-bold text-success"><?= $total_intervensi ?></div>
                    <small class="text-muted" style="font-size: 0.75rem;">Catatan Intervensi</small>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

      </div>

      <!-- ============================================== -->
      <!-- BAGIAN 2 & 3: TAB UPDATE PROFIL & UBAH PASSWORD -->
      <!-- ============================================== -->
      <div class="col-12 col-xl-8 col-lg-7">
        <div class="panel shadow-sm">
          
          <!-- Nav Tabs -->
          <div class="panel-header border-bottom p-0">
            <ul class="nav nav-tabs nav-fill border-0 w-100" id="profileTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link py-3 px-4 fw-semibold <?= $active_tab === 'profil' ? 'active' : '' ?>" 
                        id="tab-edit-profile" 
                        data-bs-toggle="tab" 
                        data-bs-target="#content-edit-profile" 
                        type="button" 
                        role="tab" 
                        aria-controls="content-edit-profile" 
                        aria-selected="<?= $active_tab === 'profil' ? 'true' : 'false' ?>">
                  <i class="bi bi-person-gear me-2 fs-5 align-middle"></i>Update Profil
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link py-3 px-4 fw-semibold <?= $active_tab === 'password' ? 'active' : '' ?>" 
                        id="tab-edit-password" 
                        data-bs-toggle="tab" 
                        data-bs-target="#content-edit-password" 
                        type="button" 
                        role="tab" 
                        aria-controls="content-edit-password" 
                        aria-selected="<?= $active_tab === 'password' ? 'true' : 'false' ?>">
                  <i class="bi bi-shield-lock me-2 fs-5 align-middle"></i>Ubah Kata Sandi
                </button>
              </li>
            </ul>
          </div>

          <div class="panel-body p-4">
            <div class="tab-content" id="profileTabsContent">

              <!-- ========================================== -->
              <!-- TAB 1: FORM UPDATE PROFIL -->
              <!-- ========================================== -->
              <div class="tab-pane fade <?= $active_tab === 'profil' ? 'show active' : '' ?>" 
                   id="content-edit-profile" 
                   role="tabpanel" 
                   aria-labelledby="tab-edit-profile">
                
                <div class="mb-4">
                  <h5 class="fw-bold text-dark mb-1">Perbarui Data Akun</h5>
                  <p class="text-muted small">Ubah informasi identitas nama, kontak, dan alamat email Anda.</p>
                </div>

                <form method="POST" action="profil.php" class="needs-validation" novalidate>
                  <input type="hidden" name="action_update_profile" value="1">

                  <div class="row g-3">
                    <!-- Nama Lengkap -->
                    <div class="col-md-6">
                      <label for="inputNama" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="inputNama" name="nama" 
                               value="<?= htmlspecialchars($user['nama']) ?>" required 
                               placeholder="Masukkan nama lengkap">
                      </div>
                      <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
                    </div>

                    <!-- Username -->
                    <div class="col-md-6">
                      <label for="inputUsername" class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <span class="input-group-text bg-light">@</span>
                        <input type="text" class="form-control" id="inputUsername" name="username" 
                               value="<?= htmlspecialchars($user['username']) ?>" required 
                               pattern="^[a-zA-Z0-9_.-]+$"
                               placeholder="Username untuk login">
                      </div>
                      <div class="form-text small">Hanya huruf, angka, titik, atau underscore.</div>
                    </div>

                    <!-- Email -->
                    <div class="col-md-6">
                      <label for="inputEmail" class="form-label fw-semibold">Alamat Email <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="inputEmail" name="email" 
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>" required 
                               placeholder="nama@email.com">
                      </div>
                      <div class="invalid-feedback">Masukkan format email yang valid.</div>
                    </div>

                    <!-- Nomor Telepon -->
                    <div class="col-md-6">
                      <label for="inputTelepon" class="form-label fw-semibold">Nomor Telepon / WhatsApp</label>
                      <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-whatsapp"></i></span>
                        <input type="tel" class="form-control" id="inputTelepon" name="no_telepon" 
                               value="<?= htmlspecialchars($user['no_telepon'] ?? '') ?>" 
                               placeholder="Contoh: 081234567890">
                      </div>
                      <div class="form-text small">Digunakan untuk koordinasi pemantauan gizi anak.</div>
                    </div>

                    <div class="col-12"><hr class="my-2"></div>

                    <!-- Informasi Penugasan (Readonly / Non-editable) -->
                    <div class="col-md-6">
                      <label class="form-label fw-semibold text-muted">Peran / Hak Akses</label>
                      <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['nama_role'] ?? 'Kader') ?>" readonly>
                      <small class="text-muted" style="font-size: 0.78rem;">Role diatur oleh Administrator sistem.</small>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label fw-semibold text-muted">Posyandu Tempat Bertugas</label>
                      <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['nama_posyandu'] ?? 'Semua Wilayah') ?>" readonly>
                      <small class="text-muted" style="font-size: 0.78rem;">Wilayah kerja terdaftar di database.</small>
                    </div>

                  </div>

                  <!-- Tombol Simpan -->
                  <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <button type="reset" class="btn btn-light px-3">
                      <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                      <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan Profil
                    </button>
                  </div>
                </form>

              </div>

              <!-- ========================================== -->
              <!-- TAB 2: FORM UBAH KATA SANDI -->
              <!-- ========================================== -->
              <div class="tab-pane fade <?= $active_tab === 'password' ? 'show active' : '' ?>" 
                   id="content-edit-password" 
                   role="tabpanel" 
                   aria-labelledby="tab-edit-password">
                
                <div class="mb-4">
                  <h5 class="fw-bold text-dark mb-1">Ubah Kata Sandi</h5>
                  <p class="text-muted small">Gunakan kata sandi yang kuat untuk menjaga keamanan akun SiPantau Gizi Anda.</p>
                </div>

                <!-- Info Tips Keamanan -->
                <div class="alert alert-info py-2 px-3 mb-4 d-flex align-items-center small">
                  <i class="bi bi-info-circle-fill fs-5 me-2 text-info"></i>
                  <span>Kata sandi baru minimal terdiri dari <strong>6 karakter</strong>. Disarankan kombinasi huruf besar, huruf kecil, dan angka.</span>
                </div>

                <form method="POST" action="profil.php" id="formUbahPassword" class="needs-validation" novalidate>
                  <input type="hidden" name="action_update_password" value="1">

                  <div class="row g-3">
                    
                    <!-- Kata Sandi Lama -->
                    <div class="col-12">
                      <label for="password_lama" class="form-label fw-semibold">
                        Kata Sandi Saat Ini <span class="text-danger">*</span>
                      </label>
                      <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                        <input type="password" class="form-control" id="password_lama" name="password_lama" required 
                               placeholder="Masukkan kata sandi lama Anda">
                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password_lama" tabindex="-1">
                          <i class="bi bi-eye"></i>
                        </button>
                      </div>
                      <div class="invalid-feedback">Kata sandi lama wajib diisi untuk verifikasi.</div>
                    </div>

                    <!-- Kata Sandi Baru -->
                    <div class="col-md-6">
                      <label for="password_baru" class="form-label fw-semibold">
                        Kata Sandi Baru <span class="text-danger">*</span>
                      </label>
                      <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password_baru" name="password_baru" minlength="6" required 
                               placeholder="Minimal 6 karakter">
                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password_baru" tabindex="-1">
                          <i class="bi bi-eye"></i>
                        </button>
                      </div>
                      <div class="form-text small" id="passwordStrengthText">Minimal 6 karakter.</div>
                    </div>

                    <!-- Konfirmasi Kata Sandi Baru -->
                    <div class="col-md-6">
                      <label for="konfirmasi_password" class="form-label fw-semibold">
                        Konfirmasi Kata Sandi Baru <span class="text-danger">*</span>
                      </label>
                      <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-shield-check"></i></span>
                        <input type="password" class="form-control" id="konfirmasi_password" name="konfirmasi_password" minlength="6" required 
                               placeholder="Ketik ulang kata sandi baru">
                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="konfirmasi_password" tabindex="-1">
                          <i class="bi bi-eye"></i>
                        </button>
                      </div>
                      <div class="form-text small" id="matchFeedback"></div>
                    </div>

                  </div>

                  <!-- Tombol Simpan Password -->
                  <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <button type="reset" class="btn btn-light px-3">
                      <i class="bi bi-arrow-counterclockwise me-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-warning px-4 fw-semibold text-dark">
                      <i class="bi bi-shield-lock-fill me-1"></i> Simpan Kata Sandi Baru
                    </button>
                  </div>
                </form>

              </div>

            </div>
          </div>
        </div>
      </div>

    </div>

  </div>
</main>

<!-- Script Khusus Halaman Profil -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Toggle Show/Hide Password
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);
            const icon = this.querySelector('i');

            if (targetInput.type === 'password') {
                targetInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                targetInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    });

    // 2. Realtime Password Match Validation
    const passBaru = document.getElementById('password_baru');
    const passKonfirmasi = document.getElementById('konfirmasi_password');
    const matchFeedback = document.getElementById('matchFeedback');

    function checkPasswordMatch() {
        if (!passBaru || !passKonfirmasi) return;
        const val1 = passBaru.value;
        const val2 = passKonfirmasi.value;

        if (val2.length === 0) {
            matchFeedback.textContent = '';
            matchFeedback.className = 'form-text small';
            return;
        }

        if (val1 === val2) {
            matchFeedback.textContent = '✓ Kata sandi cocok!';
            matchFeedback.className = 'form-text small text-success fw-semibold';
        } else {
            matchFeedback.textContent = '✗ Kata sandi belum cocok.';
            matchFeedback.className = 'form-text small text-danger fw-semibold';
        }
    }

    if (passBaru && passKonfirmasi) {
        passBaru.addEventListener('input', checkPasswordMatch);
        passKonfirmasi.addEventListener('input', checkPasswordMatch);
    }

    // 3. Form Validation Feedback
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});
</script>

<?php include 'footer.php'; ?>
