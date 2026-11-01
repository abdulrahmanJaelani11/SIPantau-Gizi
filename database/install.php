<?php
/**
 * Script Instalasi Database — SiPantau Gizi
 * =============================================
 * Jalankan script ini dari browser untuk mengimpor skema database db_sipantau_gizi
 * beserta 13 tabel dan data awal (master data & akun default).
 *
 * Akun default yang dibuat:
 *   Admin     : admin / admin123  (email: admin@sipantau.test)
 *   Kader     : kader / kader123  (email: kader@sipantau.test)
 *   Petugas   : petugas / petugas123 (email: puskesmas@sipantau.test)
 *   Koordinator: kordes / kordes123 (email: kordes@sipantau.test)
 */

$db_host = 'localhost';
$db_port = '3306';
$db_name = 'db_sipantau_gizi';
$db_user = 'root';
$db_pass = '';

$messages = [];
$success = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        $pdo = new PDO(
            "mysql:host={$db_host};port={$db_port};charset=utf8mb4",
            $db_user, $db_pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $messages[] = ['success', 'Koneksi ke MySQL host berhasil.'];

        $sql_file = __DIR__ . '/db_sipantau_gizi.sql';
        if (!file_exists($sql_file)) {
            throw new Exception("File database/db_sipantau_gizi.sql tidak ditemukan.");
        }

        $sql_content = file_get_contents($sql_file);

        // Eksekusi statement multi-query
        $pdo->exec($sql_content);
        $messages[] = ['success', "Database `{$db_name}` dan seluruh 13 tabel berhasil diimpor."];
        $messages[] = ['success', 'Data awal role, master wilayah, posyandu, puskesmas, dan 4 akun default siap digunakan.'];
    } catch (Exception $e) {
        $messages[] = ['danger', 'Gagal instalasi: ' . $e->getMessage()];
        $success = false;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Instalasi Database | SiPantau Gizi</title>
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-body">
  <main class="auth-page">
    <section class="auth-card" style="max-width: 620px;">
      <div class="mb-4">
        <h1 class="h3 mb-1"><i class="bi bi-database-gear me-2 text-primary"></i>Instalasi Database</h1>
        <p class="text-muted mb-0">SiPantau Gizi — Setup arsitektur database baru (<code>db_sipantau_gizi</code>).</p>
      </div>

      <?php if (!empty($messages)): ?>
        <div class="mb-3">
          <?php foreach ($messages as [$type, $text]): ?>
            <div class="alert alert-<?= $type ?> py-2 small mb-2">
              <i class="bi bi-<?= $type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill me-1"></i>
              <?= htmlspecialchars($text) ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!$messages || !$success): ?>
        <div class="alert alert-info py-2 small">
          <i class="bi bi-info-circle me-1"></i>
          Script ini akan mengimpor database <code>db_sipantau_gizi</code>,
          15 tabel terelasi, serta data awal sistem (termasuk Role & Otorisasi Menu).
        </div>

        <table class="table table-sm small mb-3">
          <tr><td class="fw-semibold">Host</td><td><?= $db_host ?>:<?= $db_port ?></td></tr>
          <tr><td class="fw-semibold">Database</td><td><?= $db_name ?></td></tr>
          <tr><td class="fw-semibold">User DB</td><td><?= $db_user ?></td></tr>
          <tr><td class="fw-semibold">Tabel (15)</td><td>role, menu, role_menu, user, wilayah, posyandu, puskesmas, orangtua, anak, jadwal_posyandu, kehadiran, pengukuran, status_gizi, pemantauan, catatan_intervensi</td></tr>
          <tr><td class="fw-semibold">Akun Admin</td><td>Username: <code>admin</code> / Password: <code>admin123</code></td></tr>
        </table>

        <form method="POST">
          <button class="btn btn-primary w-100" type="submit" name="install" value="1">
            <i class="bi bi-database-add me-1"></i> Jalankan Instalasi Database
          </button>
        </form>
      <?php else: ?>
        <div class="alert alert-success small">
          <i class="bi bi-check-all me-1"></i>
          Instalasi selesai! Anda sekarang dapat masuk menggunakan akun default.
        </div>
        <a href="../modules/auth/login.php" class="btn btn-success w-100">
          <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Halaman Login
        </a>
      <?php endif; ?>

      <div class="mt-3 text-center">
        <a href="../modules/auth/login.php" class="small text-muted">&larr; Kembali ke Login</a>
      </div>
    </section>
  </main>
</body>
</html>
