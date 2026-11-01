<?php
/**
 * Modul Utilitas — Tambah Pengguna Baru
 * =====================================
 * Halaman form untuk menambahkan akun pengguna baru ke sistem SiPantau Gizi.
 * Disesuaikan dengan arsitektur database db_sipantau_gizi (tabel user, role, posyandu, puskesmas).
 */

require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan hanya role Admin yang bisa menambah akun pengguna (id_role = 4)
$user_role = $_SESSION['user_role_nama'] ?? '';
$user_role_id = $_SESSION['role_id'] ?? 0;
if (strcasecmp($user_role, 'Admin') !== 0 && $user_role_id != 4) {
    set_flash('danger', 'Akses ditolak. Halaman ini hanya untuk Administrator.');
    redirect(base_url('modules/dashboard/index.php'));
}

// Ambil daftar role untuk dropdown
$roles_list = [];
$posyandu_list = [];
$puskesmas_list = [];
$wilayah_list = [];

try {
    $roles_list     = $pdo->query("SELECT id_role, nama_role FROM role ORDER BY id_role ASC")->fetchAll();
    $posyandu_list  = $pdo->query("SELECT id_posyandu, nama_posyandu FROM posyandu ORDER BY nama_posyandu ASC")->fetchAll();
    $puskesmas_list = $pdo->query("SELECT id_puskesmas, nama_puskesmas FROM puskesmas ORDER BY nama_puskesmas ASC")->fetchAll();
    $wilayah_list   = $pdo->query("SELECT id_wilayah, nama_wilayah FROM wilayah ORDER BY nama_wilayah ASC")->fetchAll();
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat master data: ' . $e->getMessage());
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
    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $no_telepon       = trim($_POST['no_telepon'] ?? '');
    $id_role          = trim($_POST['id_role'] ?? '');
    $id_posyandu      = !empty($_POST['id_posyandu']) ? (int)$_POST['id_posyandu'] : null;
    $id_puskesmas     = !empty($_POST['id_puskesmas']) ? (int)$_POST['id_puskesmas'] : null;
    $id_wilayah       = !empty($_POST['id_wilayah']) ? (int)$_POST['id_wilayah'] : null;
    $status           = trim($_POST['status'] ?? 'Aktif');
    $password         = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Simpan input lama agar user tidak perlu mengisi ulang jika error
    set_old_input([
        'nama'         => $nama,
        'username'     => $username,
        'email'        => $email,
        'no_telepon'   => $no_telepon,
        'id_role'      => $id_role,
        'id_posyandu'  => $id_posyandu,
        'id_puskesmas' => $id_puskesmas,
        'id_wilayah'   => $id_wilayah,
        'status'       => $status,
    ]);

    // 2. Validasi Nama
    if ($nama === '') {
        $errors['nama'] = 'Nama lengkap wajib diisi.';
    } elseif (mb_strlen($nama) < 3) {
        $errors['nama'] = 'Nama lengkap minimal 3 karakter.';
    }

    // 3. Validasi Username
    if ($username === '') {
        $errors['username'] = 'Username wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $errors['username'] = 'Username hanya boleh huruf, angka, underscore (3-30 karakter).';
    } else {
        // Cek keunikan username
        try {
            $stmt = $pdo->prepare("SELECT id_user FROM user WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $errors['username'] = 'Username ini sudah digunakan. Silakan pilih username lain.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Gagal memeriksa ketersediaan username: ' . $e->getMessage();
        }
    }

    // 4. Validasi Email (Opsional, tapi jika diisi harus valid & unik)
    if ($email !== '') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format alamat email tidak valid.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id_user FROM user WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors['email'] = 'Email ini sudah terdaftar di sistem. Gunakan email lain.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Gagal memeriksa email: ' . $e->getMessage();
            }
        }
    } else {
        $email = null;
    }

    // 5. Validasi Role
    $valid_role_ids = array_column($roles_list, 'id_role');
    if ($id_role === '' || !in_array($id_role, $valid_role_ids)) {
        $errors['id_role'] = 'Silakan pilih peran (role) yang valid.';
    }

    // 6. Validasi Status
    if (!in_array(strtolower($status), ['aktif', 'nonaktif'], true)) {
        $status = 'Aktif';
    } else {
        $status = ucfirst(strtolower($status));
    }

    // 7. Validasi Password
    if ($password === '') {
        $errors['password'] = 'Password wajib diisi.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password minimal 6 karakter.';
    }

    if ($password !== $password_confirm) {
        $errors['password_confirm'] = 'Konfirmasi password tidak cocok.';
    }

    // 8. Jika tidak ada error, eksekusi INSERT
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_sql = "
                INSERT INTO user 
                (id_role, id_posyandu, id_puskesmas, id_wilayah, nama, username, password_hash, email, no_telepon, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([
                (int) $id_role,
                $id_posyandu,
                $id_puskesmas,
                $id_wilayah,
                $nama,
                $username,
                $hashed_password,
                $email,
                $no_telepon ?: null,
                $status,
            ]);

            clear_old_input();
            set_flash('success', "Pengguna <strong>" . e($nama) . "</strong> (@" . e($username) . ") berhasil ditambahkan.");
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

          <!-- Username -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="username">
              Username <span class="text-danger">*</span>
            </label>
            <div class="input-group">
              <span class="input-group-text">@</span>
              <input type="text"
                     class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                     id="username"
                     name="username"
                     placeholder="contoh: sitirahma"
                     value="<?= e(old('username')) ?>"
                     required>
            </div>
            <?php if (isset($errors['username'])): ?>
              <div class="text-danger small mt-1"><?= e($errors['username']) ?></div>
            <?php else: ?>
              <div class="form-text">Digunakan sebagai ID unik untuk login.</div>
            <?php endif; ?>
          </div>

          <!-- Email -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="email">Alamat Email</label>
            <input type="email"
                   class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                   id="email"
                   name="email"
                   placeholder="nama@contoh.id"
                   value="<?= e(old('email')) ?>">
            <?php if (isset($errors['email'])): ?>
              <div class="invalid-feedback"><?= e($errors['email']) ?></div>
            <?php endif; ?>
          </div>

          <!-- No Telepon -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="no_telepon">Nomor Telepon / WhatsApp</label>
            <input type="tel"
                   class="form-control"
                   id="no_telepon"
                   name="no_telepon"
                   placeholder="08123456789"
                   value="<?= e(old('no_telepon')) ?>">
          </div>

          <!-- Peran / Role -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="id_role">
              Peran (Role) <span class="text-danger">*</span>
            </label>
            <select class="form-select <?= isset($errors['id_role']) ? 'is-invalid' : '' ?>"
                    id="id_role"
                    name="id_role"
                    required>
              <option value="">-- Pilih Peran --</option>
              <?php foreach ($roles_list as $r): ?>
                <option value="<?= $r['id_role'] ?>" <?= (string)old('id_role') === (string)$r['id_role'] ? 'selected' : '' ?>>
                  <?= e($r['nama_role']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['id_role'])): ?>
              <div class="invalid-feedback"><?= e($errors['id_role']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Status Akun -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="status">
              Status Akun <span class="text-danger">*</span>
            </label>
            <select class="form-select" id="status" name="status" required>
              <option value="Aktif" <?= old('status', 'Aktif') === 'Aktif' ? 'selected' : '' ?>>Aktif (Dapat Login)</option>
              <option value="Nonaktif" <?= old('status') === 'Nonaktif' ? 'selected' : '' ?>>Nonaktif (Akses Dibekukan)</option>
            </select>
          </div>

          <!-- Penugasan Posyandu (Opsional) -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="id_posyandu">Penugasan Posyandu (Khusus Kader)</label>
            <select class="form-select" id="id_posyandu" name="id_posyandu">
              <option value="">-- Tanpa Penugasan Posyandu --</option>
              <?php foreach ($posyandu_list as $p): ?>
                <option value="<?= $p['id_posyandu'] ?>" <?= (string)old('id_posyandu') === (string)$p['id_posyandu'] ? 'selected' : '' ?>>
                  <?= e($p['nama_posyandu']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Penugasan Puskesmas (Opsional) -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="id_puskesmas">Penugasan Puskesmas</label>
            <select class="form-select" id="id_puskesmas" name="id_puskesmas">
              <option value="">-- Tanpa Penugasan Puskesmas --</option>
              <?php foreach ($puskesmas_list as $pk): ?>
                <option value="<?= $pk['id_puskesmas'] ?>" <?= (string)old('id_puskesmas') === (string)$pk['id_puskesmas'] ? 'selected' : '' ?>>
                  <?= e($pk['nama_puskesmas']) ?>
                </option>
              <?php endforeach; ?>
            </select>
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
              <p class="text-muted small mb-0">Akses penuh ke seluruh sistem, master data, dan pengelolaan akun.</p>
            </div>
          </div>

          <div class="activity-item">
            <span class="activity-dot bg-info"></span>
            <div>
              <p class="mb-1 fw-semibold text-info">Kader</p>
              <p class="text-muted small mb-0">Input data balita, penimbangan posyandu, dan pengukuran gizi bulanan.</p>
            </div>
          </div>

          <div class="activity-item">
            <span class="activity-dot bg-success"></span>
            <div>
              <p class="mb-1 fw-semibold text-success">Bidan / Petugas Puskesmas</p>
              <p class="text-muted small mb-0">Validasi status gizi, pantau stunting, dan input catatan intervensi.</p>
            </div>
          </div>

          <div class="activity-item">
            <span class="activity-dot bg-warning"></span>
            <div>
              <p class="mb-1 fw-semibold text-warning">Koordinator Desa</p>
              <p class="text-muted small mb-0">Monitoring capaian status gizi balita dan grafik pemantauan desa.</p>
            </div>
          </div>
        </div>

        <div class="alert alert-info mt-3 mb-0 small">
          <i class="bi bi-info-circle me-1"></i>
          <strong>Keamanan Sandi:</strong> Password dienkripsi dengan standar <code>bcrypt</code> pada kolom <code>password_hash</code>.
        </div>
      </div>
    </div>
  </div>
</section>

<?php
// Include footer layout
require_once ROOT_PATH . '/includes/footer.php';
