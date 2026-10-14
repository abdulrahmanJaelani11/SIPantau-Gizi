<?php
/**
 * Halaman Login — SiPantau Gizi
 * ================================
 * Halaman ini menggunakan layout khusus (auth layout) tanpa sidebar.
 * Tidak perlu include header.php / sidebar.php / footer.php.
 */

require_once __DIR__ . '/../../config/app.php';

// Jika sudah login, langsung ke dashboard
if (!empty($_SESSION['user_id'])) {
    redirect(base_url('modules/dashboard/index.php'));
}

// ============================================================
// Proses Form Login (POST)
// ============================================================
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = trim($_POST['email'] ?? $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validasi input kosong
    if ($login_id === '' || $password === '') {
        $error = 'Username/Email dan password wajib diisi.';

    // Cek koneksi database
    } elseif ($pdo === null) {
        $error = 'Database belum tersedia. Pastikan MySQL aktif dan database db_sipantau_gizi sudah dibuat.';

    } else {
        // Cari user berdasarkan username atau email
        $stmt = $pdo->prepare('
            SELECT u.id_user, u.nama, u.username, u.email, u.password_hash, u.status,
                   u.id_role, r.nama_role, u.id_posyandu, u.id_puskesmas, u.id_wilayah
            FROM user u
            JOIN role r ON r.id_role = u.id_role
            WHERE u.username = ? OR u.email = ?
            LIMIT 1
        ');
        $stmt->execute([$login_id, $login_id]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Username/email atau password salah.';
        } elseif (strcasecmp($user['status'], 'Aktif') !== 0) {
            $error = 'Akun Anda sedang dinonaktifkan. Hubungi administrator.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Username/email atau password salah.';
        } else {
            // Login berhasil — simpan data ke session
            $_SESSION['user_id']        = $user['id_user'];
            $_SESSION['user_nama']      = $user['nama'];
            $_SESSION['user_username']  = $user['username'];
            $_SESSION['user_email']     = $user['email'];
            $_SESSION['role_id']        = $user['id_role'];
            $_SESSION['user_role_nama'] = $user['nama_role'];
            $_SESSION['user_posyandu']  = $user['id_posyandu'];
            $_SESSION['user_puskesmas'] = $user['id_puskesmas'];
            $_SESSION['user_wilayah']   = $user['id_wilayah'];

            set_flash('success', 'Selamat datang, ' . $user['nama'] . '!');
            redirect(base_url('modules/dashboard/index.php'));
        }
    }
}

// Ambil flash message (misal dari auth-guard redirect)
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Login — <?= e(APP_NAME) ?>">
  <title>Login | <?= e(APP_NAME) ?></title>

  <!-- Google Fonts: Poppins -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= asset('vendors/bootstrap-icons/bootstrap-icons.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>

<body class="auth-body">
  <!-- Tombol dark/light mode -->
  <button class="icon-button theme-toggle auth-theme-toggle" type="button"
          data-theme-toggle aria-label="Ganti tema warna" title="Ganti tema warna">
    <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
  </button>

  <main class="auth-page">
    <section class="auth-card">
      <!-- Brand -->
      <a class="auth-brand" href="<?= base_url('index.php') ?>">
        <span class="brand-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span>
        <span>
          <strong><?= e(APP_NAME) ?></strong>
          <small>Sistem Informasi Pemantauan Gizi</small>
        </span>
      </a>

      <!-- Visual -->
      <div class="auth-visual">
        <img src="<?= asset('images/png/dasher-ui-bootstrap-5.jpg') ?>"
             alt="<?= e(APP_NAME) ?> dashboard">
      </div>

      <!-- Form Login -->
      <form method="POST" action="" novalidate>
        <div class="mb-4">
          <p class="eyebrow mb-1">Akses Aman</p>
          <h1 class="h3 mb-1">Login</h1>
          <p class="text-muted mb-0">Masuk ke workspace Anda.</p>
        </div>

        <?php if ($flash): ?>
          <div class="alert alert-<?= e($flash['type']) ?> py-2 small" role="alert">
            <?= e($flash['message']) ?>
          </div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2 small" role="alert">
            <i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?>
          </div>
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label" for="loginEmail">Username atau Email</label>
          <input class="form-control" id="loginEmail" name="email" type="text"
                 placeholder="admin atau admin@sipantau.test"
                 value="<?= e($login_id ?? '') ?>" required autofocus>
        </div>

        <div class="mb-3">
          <label class="form-label" for="loginPassword">Password</label>
          <input class="form-control" id="loginPassword" name="password"
                 type="password" minlength="6" required>
        </div>

        <button class="btn btn-primary w-100" type="submit">
          <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Masuk
        </button>
      </form>

    </section>
  </main>

  <script src="<?= asset('js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= asset('js/main.js') ?>"></script>
</body>
</html>
