<?php
/**
 * Modul Utilitas — Edit Pengguna
 * ===============================
 * Halaman untuk memperbarui data akun pengguna (nama, email, role, status, dan password opsional).
 */

require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan hanya role Admin yang bisa mengelola akun pengguna
if (($_SESSION['user_role_nama'] ?? '') !== 'Admin' && ($_SESSION['role_id'] ?? 0) != 1) {
    set_flash('danger', 'Akses ditolak. Halaman ini hanya untuk Administrator.');
    redirect(base_url('modules/dashboard/index.php'));
}

// 1. Ambil dan validasi ID pengguna dari query string
$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$user_id) {
    set_flash('danger', 'ID pengguna tidak valid.');
    redirect(base_url('modules/utilitas/pengguna/index.php'));
}

// 2. Ambil data pengguna dari database
$user = null;
try {
    $stmt = $pdo->prepare("
        SELECT u.*, r.nama_role 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ? 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat data pengguna: ' . $e->getMessage());
    redirect(base_url('modules/utilitas/pengguna/index.php'));
}

if (!$user) {
    set_flash('danger', 'Pengguna dengan ID tersebut tidak ditemukan.');
    redirect(base_url('modules/utilitas/pengguna/index.php'));
}

// Cek apakah sedang mengedit akun sendiri
$is_own_account = ((int)$user['id'] === (int)$_SESSION['user_id']);

// 3. Ambil daftar role untuk dropdown
$roles_list = [];
try {
    $roles_stmt = $pdo->query("SELECT id, nama_role, deskripsi FROM roles ORDER BY id ASC");
    $roles_list = $roles_stmt->fetchAll();
} catch (PDOException $e) {
    $roles_list = [];
}

$errors = [];

// ============================================================
// Proses Update Data (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cek CSRF
    if (!verify_csrf_token()) {
        $errors[] = 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba lagi.';
    }

    $nama             = trim($_POST['nama'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $role_id          = $is_own_account ? (int)$user['role_id'] : trim($_POST['role_id'] ?? '');
    $status           = $is_own_account ? 'aktif' : trim($_POST['status'] ?? 'aktif');
    $password         = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validasi Nama
    if ($nama === '') {
        $errors['nama'] = 'Nama lengkap wajib diisi.';
    } elseif (mb_strlen($nama) < 3) {
        $errors['nama'] = 'Nama lengkap minimal 3 karakter.';
    }

    // Validasi Email
    if ($email === '') {
        $errors['email'] = 'Alamat email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format alamat email tidak valid.';
    } else {
        // Cek duplikasi email pada user LAIN
        try {
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $check_stmt->execute([$email, $user_id]);
            if ($check_stmt->fetch()) {
                $errors['email'] = 'Email ini sudah digunakan oleh akun lain. Gunakan email lain.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Gagal memeriksa ketersediaan email: ' . $e->getMessage();
        }
    }

    // Validasi Role (jika bukan akun sendiri)
    if (!$is_own_account) {
        $valid_role_ids = array_column($roles_list, 'id');
        if ($role_id === '' || !in_array($role_id, $valid_role_ids)) {
            $errors['role_id'] = 'Silakan pilih peran (role) yang valid.';
        }
    }

    // Validasi Status (jika bukan akun sendiri)
    if (!$is_own_account && !in_array($status, ['aktif', 'nonaktif'], true)) {
        $status = 'aktif';
    }

    // Validasi Password Baru (Hanya jika diisi)
    $update_password = false;
    if ($password !== '' || $password_confirm !== '') {
        if (strlen($password) < 6) {
            $errors['password'] = 'Password baru minimal 6 karakter.';
        } elseif ($password !== $password_confirm) {
            $errors['password_confirm'] = 'Konfirmasi password baru tidak cocok.';
        } else {
            $update_password = true;
        }
    }

    // Eksekusi Update jika tidak ada error
    if (empty($errors)) {
        try {
            if ($update_password) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_sql = "
                    UPDATE users 
                    SET nama = ?, email = ?, password = ?, role_id = ?, status = ?, updated_at = NOW() 
                    WHERE id = ?
                ";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([$nama, $email, $hashed_password, (int)$role_id, $status, $user_id]);
            } else {
                $update_sql = "
                    UPDATE users 
                    SET nama = ?, email = ?, role_id = ?, status = ?, updated_at = NOW() 
                    WHERE id = ?
                ";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([$nama, $email, (int)$role_id, $status, $user_id]);
            }

            // Jika mengedit akun sendiri, update data session agar nama langsung berubah di topbar
            if ($is_own_account) {
                $_SESSION['user_nama'] = $nama;
                $_SESSION['user_email'] = $email;
            }

            set_flash('success', "Data pengguna <strong>" . e($nama) . "</strong> berhasil diperbarui.");
            redirect(base_url('modules/utilitas/pengguna/index.php'));
        } catch (PDOException $e) {
            $errors[] = 'Gagal memperbarui data pengguna: ' . $e->getMessage();
        }
    }
}

// Helper inisial nama untuk avatar preview
function get_initials(string $name): string {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $w) {
        $initials .= mb_substr($w, 0, 1);
    }
    return strtoupper($initials ?: 'U');
}

// Variabel layout
$page_title   = 'Edit Pengguna: ' . $user['nama'];
$current_slug = 'pengguna';
$breadcrumbs  = [
    'Utilitas'        => base_url('modules/utilitas/pengguna/index.php'),
    'Daftar Pengguna' => base_url('modules/utilitas/pengguna/index.php'),
    'Edit Pengguna'   => '',
];

// Include header layout
require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-pencil-square" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Utilitas / Manajemen Akun</p>
      <h1 class="h3 mb-1">Edit Pengguna</h1>
      <p class="text-muted mb-0">Perbarui profil akun, penugasan role, status, atau setel ulang password.</p>
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
  <!-- Kolom Kiri: Form Edit -->
  <div class="col-12 col-xl-8">
    <form method="POST" action="" class="panel" novalidate>
      <?= csrf_field() ?>

      <div class="panel-header">
        <div>
          <h2 class="h5 mb-1 section-title">
            <i class="bi bi-person-lines-fill" aria-hidden="true"></i>
            <span>Informasi Pengguna</span>
          </h2>
          <p class="text-muted mb-0">Ubah data di bawah ini sesuai kebutuhan.</p>
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
                   value="<?= e($_POST['nama'] ?? $user['nama']) ?>"
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
                   value="<?= e($_POST['email'] ?? $user['email']) ?>"
                   required>
            <?php if (isset($errors['email'])): ?>
              <div class="invalid-feedback"><?= e($errors['email']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Peran / Role -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="role_id">
              Peran (Role) <span class="text-danger">*</span>
            </label>
            <?php if ($is_own_account): ?>
              <input type="text" class="form-control bg-light" value="<?= e($user['nama_role']) ?> (Akun Anda Saat Ini)" readonly>
              <input type="hidden" name="role_id" value="<?= $user['role_id'] ?>">
              <div class="form-text text-muted">Peran Anda dilindungi untuk mencegah hilangnya hak akses Administrator.</div>
            <?php else: ?>
              <select class="form-select <?= isset($errors['role_id']) ? 'is-invalid' : '' ?>"
                      id="role_id"
                      name="role_id"
                      required>
                <?php foreach ($roles_list as $r): ?>
                  <?php $selected = ((string)($_POST['role_id'] ?? $user['role_id']) === (string)$r['id']); ?>
                  <option value="<?= $r['id'] ?>" <?= $selected ? 'selected' : '' ?>>
                    <?= e($r['nama_role']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['role_id'])): ?>
                <div class="invalid-feedback"><?= e($errors['role_id']) ?></div>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <!-- Status Akun -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="status">
              Status Akun <span class="text-danger">*</span>
            </label>
            <?php if ($is_own_account): ?>
              <input type="text" class="form-control bg-light text-success fw-semibold" value="Aktif (Akun Anda Saat Ini)" readonly>
              <input type="hidden" name="status" value="aktif">
              <div class="form-text text-muted">Anda tidak dapat menonaktifkan akun yang sedang login.</div>
            <?php else: ?>
              <?php $cur_status = $_POST['status'] ?? $user['status']; ?>
              <select class="form-select" id="status" name="status" required>
                <option value="aktif" <?= $cur_status === 'aktif' ? 'selected' : '' ?>>Aktif (Dapat Login)</option>
                <option value="nonaktif" <?= $cur_status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif (Akses Dibekukan)</option>
              </select>
            <?php endif; ?>
          </div>

          <!-- Bagian Ganti Password (Opsional) -->
          <div class="col-12">
            <div class="p-3 bg-light rounded border mt-2">
              <h3 class="h6 fw-bold mb-1">
                <i class="bi bi-key me-1 text-primary"></i> Ubah Password (Opsional)
              </h3>
              <p class="text-muted small mb-3">
                Biarkan kedua kolom di bawah ini <strong>kosong</strong> jika Anda tidak ingin mengganti password pengguna ini.
              </p>

              <div class="row g-3">
                <div class="col-12 col-md-6">
                  <label class="form-label small fw-semibold" for="password">Password Baru</label>
                  <input type="password"
                         class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                         id="password"
                         name="password"
                         placeholder="Minimal 6 karakter"
                         minlength="6">
                  <?php if (isset($errors['password'])): ?>
                    <div class="invalid-feedback"><?= e($errors['password']) ?></div>
                  <?php endif; ?>
                </div>

                <div class="col-12 col-md-6">
                  <label class="form-label small fw-semibold" for="password_confirm">Ulangi Password Baru</label>
                  <input type="password"
                         class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>"
                         id="password_confirm"
                         name="password_confirm"
                         placeholder="Ketik ulang password baru"
                         minlength="6">
                  <?php if (isset($errors['password_confirm'])): ?>
                    <div class="invalid-feedback"><?= e($errors['password_confirm']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4 pt-2 border-top">
          <a class="btn btn-outline-secondary" href="<?= base_url('modules/utilitas/pengguna/index.php') ?>">
            Batal
          </a>
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-check-lg me-1" aria-hidden="true"></i> Simpan Perubahan
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- Kolom Kanan: Ringkasan Pengguna -->
  <div class="col-12 col-xl-4">
    <div class="panel">
      <div class="panel-header">
        <h2 class="h5 mb-0 section-title">
          <i class="bi bi-person-badge" aria-hidden="true"></i>
          <span>Ringkasan Akun</span>
        </h2>
      </div>
      <div class="p-3 text-center">
        <div class="avatar-xl rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold bg-primary bg-gradient mb-3"
             style="width: 72px; height: 72px; font-size: 1.6rem;">
          <?= get_initials($user['nama']) ?>
        </div>

        <h3 class="h5 mb-0"><?= e($user['nama']) ?></h3>
        <p class="text-muted small mb-2"><?= e($user['email']) ?></p>

        <div class="d-flex justify-content-center gap-2 mb-3">
          <span class="badge text-bg-primary">
            <i class="bi bi-shield-check me-1"></i><?= e($user['nama_role'] ?? 'Tanpa Role') ?>
          </span>
          <?php if ($user['status'] === 'aktif'): ?>
            <span class="badge text-bg-success">Aktif</span>
          <?php else: ?>
            <span class="badge text-bg-secondary">Nonaktif</span>
          <?php endif; ?>
        </div>

        <div class="border-top pt-3 text-start small">
          <div class="d-flex justify-content-between py-1">
            <span class="text-muted">ID Pengguna:</span>
            <span class="fw-semibold">#<?= $user['id'] ?></span>
          </div>
          <div class="d-flex justify-content-between py-1">
            <span class="text-muted">Tanggal Dibuat:</span>
            <span><?= date('d M Y, H:i', strtotime($user['created_at'])) ?></span>
          </div>
          <div class="d-flex justify-content-between py-1">
            <span class="text-muted">Terakhir Diperbarui:</span>
            <span><?= date('d M Y, H:i', strtotime($user['updated_at'])) ?></span>
          </div>
        </div>

        <?php if ($is_own_account): ?>
          <div class="alert alert-warning mt-3 mb-0 small text-start">
            <i class="bi bi-shield-exclamation me-1"></i>
            <strong>Catatan:</strong> Ini adalah akun Anda yang sedang login. Peran dan status dikunci untuk mencegah Anda kehilangan akses admin.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php
// Include footer layout
require_once ROOT_PATH . '/includes/footer.php';
