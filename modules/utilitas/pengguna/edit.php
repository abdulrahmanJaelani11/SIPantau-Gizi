<?php
/**
 * Modul Utilitas — Edit Pengguna
 * ===============================
 * Halaman untuk memperbarui data akun pengguna.
 * Disesuaikan dengan arsitektur database db_sipantau_gizi (tabel user, role, posyandu, puskesmas).
 */

require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan hanya role Admin yang bisa mengelola akun pengguna (id_role = 4)
$user_role = $_SESSION['user_role_nama'] ?? '';
$user_role_id = $_SESSION['role_id'] ?? 0;
if (strcasecmp($user_role, 'Admin') !== 0 && $user_role_id != 4) {
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
        FROM user u 
        LEFT JOIN role r ON u.id_role = r.id_role 
        WHERE u.id_user = ? 
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
$is_own_account = ((int)$user['id_user'] === (int)$_SESSION['user_id']);

// 3. Ambil daftar master data
$roles_list = [];
$posyandu_list = [];
$puskesmas_list = [];

try {
    $roles_list     = $pdo->query("SELECT id_role, nama_role FROM role ORDER BY id_role ASC")->fetchAll();
    $posyandu_list  = $pdo->query("SELECT id_posyandu, nama_posyandu FROM posyandu ORDER BY nama_posyandu ASC")->fetchAll();
    $puskesmas_list = $pdo->query("SELECT id_puskesmas, nama_puskesmas FROM puskesmas ORDER BY nama_puskesmas ASC")->fetchAll();
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
    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $no_telepon       = trim($_POST['no_telepon'] ?? '');
    $id_role          = $is_own_account ? (int)$user['id_role'] : trim($_POST['id_role'] ?? '');
    $id_posyandu      = !empty($_POST['id_posyandu']) ? (int)$_POST['id_posyandu'] : null;
    $id_puskesmas     = !empty($_POST['id_puskesmas']) ? (int)$_POST['id_puskesmas'] : null;
    $status           = $is_own_account ? 'Aktif' : trim($_POST['status'] ?? 'Aktif');
    $password         = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validasi Nama
    if ($nama === '') {
        $errors['nama'] = 'Nama lengkap wajib diisi.';
    } elseif (mb_strlen($nama) < 3) {
        $errors['nama'] = 'Nama lengkap minimal 3 karakter.';
    }

    // Validasi Username
    if ($username === '') {
        $errors['username'] = 'Username wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $errors['username'] = 'Username hanya boleh huruf, angka, underscore (3-30 karakter).';
    } else {
        // Cek duplikasi username pada user LAIN
        try {
            $stmt = $pdo->prepare("SELECT id_user FROM user WHERE username = ? AND id_user != ? LIMIT 1");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                $errors['username'] = 'Username ini sudah digunakan akun lain.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Gagal memeriksa username: ' . $e->getMessage();
        }
    }

    // Validasi Email (jika diisi)
    if ($email !== '') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format alamat email tidak valid.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id_user FROM user WHERE email = ? AND id_user != ? LIMIT 1");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetch()) {
                    $errors['email'] = 'Email ini sudah digunakan oleh akun lain.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Gagal memeriksa email: ' . $e->getMessage();
            }
        }
    } else {
        $email = null;
    }

    // Validasi Role
    if (!$is_own_account) {
        $valid_role_ids = array_column($roles_list, 'id_role');
        if ($id_role === '' || !in_array($id_role, $valid_role_ids)) {
            $errors['id_role'] = 'Silakan pilih peran (role) yang valid.';
        }
    }

    // Validasi Status
    if (!$is_own_account) {
        $status = ucfirst(strtolower($status));
        if (!in_array($status, ['Aktif', 'Nonaktif'], true)) {
            $status = 'Aktif';
        }
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
                    UPDATE user 
                    SET nama = ?, username = ?, email = ?, no_telepon = ?, id_role = ?, 
                        id_posyandu = ?, id_puskesmas = ?, status = ?, password_hash = ?
                    WHERE id_user = ?
                ";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([
                    $nama, $username, $email, $no_telepon ?: null, (int)$id_role,
                    $id_posyandu, $id_puskesmas, $status, $hashed_password, $user_id
                ]);
            } else {
                $update_sql = "
                    UPDATE user 
                    SET nama = ?, username = ?, email = ?, no_telepon = ?, id_role = ?, 
                        id_posyandu = ?, id_puskesmas = ?, status = ?
                    WHERE id_user = ?
                ";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([
                    $nama, $username, $email, $no_telepon ?: null, (int)$id_role,
                    $id_posyandu, $id_puskesmas, $status, $user_id
                ]);
            }

            // Jika mengedit akun sendiri, update session
            if ($is_own_account) {
                $_SESSION['user_nama']     = $nama;
                $_SESSION['user_username'] = $username;
                $_SESSION['user_email']    = $email;
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
                     value="<?= e($_POST['username'] ?? $user['username']) ?>"
                     required>
            </div>
            <?php if (isset($errors['username'])): ?>
              <div class="text-danger small mt-1"><?= e($errors['username']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Email -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="email">Alamat Email</label>
            <input type="email"
                   class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                   id="email"
                   name="email"
                   value="<?= e($_POST['email'] ?? $user['email'] ?? '') ?>">
            <?php if (isset($errors['email'])): ?>
              <div class="invalid-feedback"><?= e($errors['email']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Nomor Telepon -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="no_telepon">Nomor Telepon / WhatsApp</label>
            <input type="tel"
                   class="form-control"
                   id="no_telepon"
                   name="no_telepon"
                   value="<?= e($_POST['no_telepon'] ?? $user['no_telepon'] ?? '') ?>">
          </div>

          <!-- Peran / Role -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="id_role">
              Peran (Role) <span class="text-danger">*</span>
            </label>
            <?php if ($is_own_account): ?>
              <input type="text" class="form-control bg-light" value="<?= e($user['nama_role']) ?> (Akun Anda Saat Ini)" readonly>
              <input type="hidden" name="id_role" value="<?= $user['id_role'] ?>">
              <div class="form-text text-muted">Peran Anda dilindungi untuk mencegah hilangnya hak akses Administrator.</div>
            <?php else: ?>
              <select class="form-select <?= isset($errors['id_role']) ? 'is-invalid' : '' ?>"
                      id="id_role"
                      name="id_role"
                      required>
                <?php foreach ($roles_list as $r): ?>
                  <?php $selected = ((string)($_POST['id_role'] ?? $user['id_role']) === (string)$r['id_role']); ?>
                  <option value="<?= $r['id_role'] ?>" <?= $selected ? 'selected' : '' ?>>
                    <?= e($r['nama_role']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['id_role'])): ?>
                <div class="invalid-feedback"><?= e($errors['id_role']) ?></div>
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
              <input type="hidden" name="status" value="Aktif">
              <div class="form-text text-muted">Anda tidak dapat menonaktifkan akun yang sedang login.</div>
            <?php else: ?>
              <?php $cur_status = ucfirst(strtolower($_POST['status'] ?? $user['status'])); ?>
              <select class="form-select" id="status" name="status" required>
                <option value="Aktif" <?= $cur_status === 'Aktif' ? 'selected' : '' ?>>Aktif (Dapat Login)</option>
                <option value="Nonaktif" <?= $cur_status === 'Nonaktif' ? 'selected' : '' ?>>Nonaktif (Akses Dibekukan)</option>
              </select>
            <?php endif; ?>
          </div>

          <!-- Penugasan Posyandu -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="id_posyandu">Penugasan Posyandu</label>
            <select class="form-select" id="id_posyandu" name="id_posyandu">
              <option value="">-- Tanpa Penugasan Posyandu --</option>
              <?php foreach ($posyandu_list as $p): ?>
                <?php $sel_p = ((string)($_POST['id_posyandu'] ?? $user['id_posyandu']) === (string)$p['id_posyandu']); ?>
                <option value="<?= $p['id_posyandu'] ?>" <?= $sel_p ? 'selected' : '' ?>>
                  <?= e($p['nama_posyandu']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Penugasan Puskesmas -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="id_puskesmas">Penugasan Puskesmas</label>
            <select class="form-select" id="id_puskesmas" name="id_puskesmas">
              <option value="">-- Tanpa Penugasan Puskesmas --</option>
              <?php foreach ($puskesmas_list as $pk): ?>
                <?php $sel_pk = ((string)($_POST['id_puskesmas'] ?? $user['id_puskesmas']) === (string)$pk['id_puskesmas']); ?>
                <option value="<?= $pk['id_puskesmas'] ?>" <?= $sel_pk ? 'selected' : '' ?>>
                  <?= e($pk['nama_puskesmas']) ?>
                </option>
              <?php endforeach; ?>
            </select>
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
        <p class="text-primary small mb-1 fw-medium">@<?= e($user['username']) ?></p>
        <p class="text-muted small mb-2"><?= e($user['email'] ?? 'Tidak ada email') ?></p>

        <div class="d-flex justify-content-center gap-2 mb-3">
          <span class="badge text-bg-primary">
            <i class="bi bi-shield-check me-1"></i><?= e($user['nama_role'] ?? 'Tanpa Role') ?>
          </span>
          <?php if (strcasecmp($user['status'], 'aktif') === 0): ?>
            <span class="badge text-bg-success">Aktif</span>
          <?php else: ?>
            <span class="badge text-bg-secondary">Nonaktif</span>
          <?php endif; ?>
        </div>

        <div class="border-top pt-3 text-start small">
          <div class="d-flex justify-content-between py-1">
            <span class="text-muted">ID Pengguna:</span>
            <span class="fw-semibold">#<?= $user['id_user'] ?></span>
          </div>
          <div class="d-flex justify-content-between py-1">
            <span class="text-muted">No. Telepon:</span>
            <span><?= e($user['no_telepon'] ?? '—') ?></span>
          </div>
          <div class="d-flex justify-content-between py-1">
            <span class="text-muted">Tanggal Dibuat:</span>
            <span><?= !empty($user['created_at']) ? date('d M Y, H:i', strtotime($user['created_at'])) : '—' ?></span>
          </div>
          <div class="d-flex justify-content-between py-1">
            <span class="text-muted">Terakhir Update:</span>
            <span><?= !empty($user['update_at']) ? date('d M Y, H:i', strtotime($user['update_at'])) : '—' ?></span>
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
