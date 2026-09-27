<?php
/**
 * Modul Utilitas — Tambah Pengguna Baru
 * =====================================
 * Halaman form untuk menambahkan akun pengguna baru ke sistem SiPantau Gizi.
 */

require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan hanya role Admin yang bisa menambah akun pengguna
if (($_SESSION['user_role_nama'] ?? '') !== 'Admin' && ($_SESSION['role_id'] ?? 0) != 1) {
    set_flash('danger', 'Akses ditolak. Halaman ini hanya untuk Administrator.');
    redirect(base_url('modules/dashboard/index.php'));
}

// Ambil daftar role untuk dropdown
$roles_list = [];
try {
    $roles_stmt = $pdo->query("SELECT id, nama_role, deskripsi FROM roles ORDER BY id ASC");
    $roles_list = $roles_stmt->fetchAll();
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat data role: ' . $e->getMessage());
}

$errors = [];

// ============================================================
// Proses Simpan Data (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Cek CSRF
    if (!verify_csrf_token()) {
        $errors[] = 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba lagi.';
    }

    $nama             = trim($_POST['nama'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $role_id          = trim($_POST['role_id'] ?? '');
    $status           = trim($_POST['status'] ?? 'aktif');
    $password         = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Simpan input lama agar user tidak perlu mengisi ulang jika error
    set_old_input([
        'nama'    => $nama,
        'email'   => $email,
        'role_id' => $role_id,
        'status'  => $status,
    ]);

    // 2. Validasi Nama
    if ($nama === '') {
        $errors['nama'] = 'Nama lengkap wajib diisi.';
    } elseif (mb_strlen($nama) < 3) {
        $errors['nama'] = 'Nama lengkap minimal 3 karakter.';
    }

    // 3. Validasi Email
    if ($email === '') {
        $errors['email'] = 'Alamat email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format alamat email tidak valid.';
    } else {
        // Cek duplikasi email di database
        try {
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $check_stmt->execute([$email]);
            if ($check_stmt->fetch()) {
                $errors['email'] = 'Email ini sudah terdaftar di sistem. Gunakan email lain.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Gagal memeriksa ketersediaan email: ' . $e->getMessage();
        }
    }

    // 4. Validasi Role
    $valid_role_ids = array_column($roles_list, 'id');
    if ($role_id === '' || !in_array($role_id, $valid_role_ids)) {
        $errors['role_id'] = 'Silakan pilih peran (role) yang valid.';
    }

    // 5. Validasi Status
    if (!in_array($status, ['aktif', 'nonaktif'], true)) {
        $status = 'aktif';
    }

    // 6. Validasi Password
    if ($password === '') {
        $errors['password'] = 'Password wajib diisi.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password minimal 6 karakter.';
    }

    if ($password !== $password_confirm) {
        $errors['password_confirm'] = 'Konfirmasi password tidak cocok.';
    }

    // 7. Jika tidak ada error, eksekusi INSERT
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_sql = "
                INSERT INTO users (nama, email, password, role_id, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([
                $nama,
                $email,
                $hashed_password,
                (int) $role_id,
                $status,
            ]);

            clear_old_input();
            set_flash('success', "Pengguna <strong>" . e($nama) . "</strong> berhasil ditambahkan.");
            redirect(base_url('modules/utilitas/pengguna/index.php'));
        } catch (PDOException $e) {
            $errors[] = 'Gagal menyimpan pengguna ke database: ' . $e->getMessage();
        }
    }
}

// Variabel layout
$page_title   = 'Tambah Pengguna';
$current_slug = 'pengguna';
$breadcrumbs  = [
    'Utilitas'        => base_url('modules/utilitas/pengguna/index.php'),
    'Daftar Pengguna' => base_url('modules/utilitas/pengguna/index.php'),
    'Tambah'          => '',
];

// Include header layout
require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Utilitas / Manajemen Akun</p>
      <h1 class="h3 mb-1">Tambah Pengguna Baru</h1>
      <p class="text-muted mb-0">Daftarkan akun pengguna baru dengan menetapkan role dan hak akses sistem.</p>
    </div>
  </div>
  <div class="heading-actions">
    <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('modules/utilitas/pengguna/index.php') ?>">
      <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali ke Daftar
    </a>
  </div>
</div>

<?php if (!empty($errors) && isset($errors[0])): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($errors[0]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
  </div>
<?php endif; ?>

<section class="row g-3">
  <!-- Kolom Kiri: Form Input -->
  <div class="col-12 col-xl-8">
    <form method="POST" action="" class="panel" novalidate>
      <?= csrf_field() ?>

      <div class="panel-header">
        <div>
          <h2 class="h5 mb-1 section-title">
            <i class="bi bi-person-lines-fill" aria-hidden="true"></i>
            <span>Informasi Akun</span>
          </h2>
          <p class="text-muted mb-0">Semua kolom bertanda bintang (<span class="text-danger">*</span>) wajib diisi.</p>
        </div>
      </div>

      <div class="p-3 p-md-4">
        <div class="row g-3">
          <!-- Nama Lengkap -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="nama">
              Nama Lengkap <span class="text-danger">*</span>
            </label>
            <input type="text"
                   class="form-control <?= isset($errors['nama']) ? 'is-invalid' : '' ?>"
                   id="nama"
                   name="nama"
                   placeholder="Contoh: Siti Rahmawati"
                   value="<?= e(old('nama')) ?>"
                   required autofocus>
            <?php if (isset($errors['nama'])): ?>
              <div class="invalid-feedback"><?= e($errors['nama']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Email -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="email">
              Alamat Email <span class="text-danger">*</span>
            </label>
            <input type="email"
                   class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                   id="email"
                   name="email"
                   placeholder="nama@contoh.id"
                   value="<?= e(old('email')) ?>"
                   required>
            <?php if (isset($errors['email'])): ?>
              <div class="invalid-feedback"><?= e($errors['email']) ?></div>
            <?php else: ?>
              <div class="form-text">Digunakan sebagai identitas saat login ke sistem.</div>
            <?php endif; ?>
          </div>

          <!-- Peran / Role -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="role_id">
              Peran (Role) <span class="text-danger">*</span>
            </label>
            <select class="form-select <?= isset($errors['role_id']) ? 'is-invalid' : '' ?>"
                    id="role_id"
                    name="role_id"
                    required>
              <option value="">-- Pilih Peran --</option>
              <?php foreach ($roles_list as $r): ?>
                <option value="<?= $r['id'] ?>" <?= (string)old('role_id') === (string)$r['id'] ? 'selected' : '' ?>>
                  <?= e($r['nama_role']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['role_id'])): ?>
              <div class="invalid-feedback"><?= e($errors['role_id']) ?></div>
            <?php else: ?>
              <div class="form-text">Menentukan menu dan hak akses yang dapat dibuka pengguna.</div>
            <?php endif; ?>
          </div>

          <!-- Status Akun -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="status">
              Status Akun <span class="text-danger">*</span>
            </label>
            <select class="form-select" id="status" name="status" required>
              <option value="aktif" <?= old('status', 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif (Dapat Login)</option>
              <option value="nonaktif" <?= old('status') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif (Akses Dibekukan)</option>
            </select>
            <div class="form-text">Pengguna nonaktif tidak akan bisa masuk ke aplikasi.</div>
          </div>

          <div class="col-12"><hr class="my-2 text-muted"></div>

          <!-- Password -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="password">
              Password <span class="text-danger">*</span>
            </label>
            <input type="password"
                   class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                   id="password"
                   name="password"
                   placeholder="Minimal 6 karakter"
                   minlength="6"
                   required>
            <?php if (isset($errors['password'])): ?>
              <div class="invalid-feedback"><?= e($errors['password']) ?></div>
            <?php else: ?>
              <div class="form-text">Gunakan kombinasi huruf dan angka.</div>
            <?php endif; ?>
          </div>

          <!-- Konfirmasi Password -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="password_confirm">
              Ulangi Password <span class="text-danger">*</span>
            </label>
            <input type="password"
                   class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>"
                   id="password_confirm"
                   name="password_confirm"
                   placeholder="Ketik ulang password"
                   minlength="6"
                   required>
            <?php if (isset($errors['password_confirm'])): ?>
              <div class="invalid-feedback"><?= e($errors['password_confirm']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4 pt-2 border-top">
          <a class="btn btn-outline-secondary" href="<?= base_url('modules/utilitas/pengguna/index.php') ?>">
            Batal
          </a>
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-person-check me-1" aria-hidden="true"></i> Simpan Pengguna
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- Kolom Kanan: Panduan Role & Akses -->
  <div class="col-12 col-xl-4">
    <div class="panel h-100">
      <div class="panel-header">
        <h2 class="h5 mb-0 section-title">
          <i class="bi bi-shield-check" aria-hidden="true"></i>
          <span>Panduan Hak Akses Role</span>
        </h2>
      </div>
      <div class="p-3">
        <div class="activity-list">
          <div class="activity-item">
            <span class="activity-dot bg-primary"></span>
            <div>
              <p class="mb-1 fw-semibold text-primary">Admin</p>
              <p class="text-muted small mb-0">Akses penuh ke semua modul sistem, termasuk Utilitas Pengguna dan Pengaturan Otorisasi.</p>
            </div>
          </div>

          <div class="activity-item">
            <span class="activity-dot bg-info"></span>
            <div>
              <p class="mb-1 fw-semibold text-info">Kader</p>
              <p class="text-muted small mb-0">Pencatatan data balita, input penimbangan, pengukuran tinggi badan, dan input gizi di posyandu.</p>
            </div>
          </div>

          <div class="activity-item">
            <span class="activity-dot bg-success"></span>
            <div>
              <p class="mb-1 fw-semibold text-success">Petugas Puskesmas</p>
              <p class="text-muted small mb-0">Validasi status gizi, pantau indikator stunting, dan generate laporan rekapitulasi posyandu.</p>
            </div>
          </div>

          <div class="activity-item">
            <span class="activity-dot bg-warning"></span>
            <div>
              <p class="mb-1 fw-semibold text-warning">Koordinator Desa</p>
              <p class="text-muted small mb-0">Monitoring capaian dan grafik perkembangan status gizi balita se-tingkat desa.</p>
            </div>
          </div>
        </div>

        <div class="alert alert-info mt-3 mb-0 small">
          <i class="bi bi-info-circle me-1"></i>
          <strong>Keamanan Sandi:</strong> Password akan otomatis dienkripsi dengan standar <code>bcrypt</code> saat disimpan.
        </div>
      </div>
    </div>
  </div>
</section>

<?php
// Include footer layout
require_once ROOT_PATH . '/includes/footer.php';
