<?php
/**
 * Script Instalasi Database — SiPantau Gizi
 * =============================================
 * Jalankan script ini SATU KALI dari browser untuk:
 *   1. Membuat database sipantau_gizi (jika belum ada)
 *   2. Membuat semua tabel (roles, menus, role_menu, users)
 *   3. Mengisi data awal (roles, menu, hak akses, user admin)
 *
 * Cara pakai:
 *   1. Pastikan MySQL/XAMPP sudah berjalan
 *   2. Buka di browser: http://localhost/sipantau-gizi/database/install.php
 *   3. Klik tombol "Jalankan Instalasi"
 *   4. Setelah berhasil, HAPUS atau rename file ini demi keamanan
 *
 * Akun admin default yang dibuat:
 *   Email    : admin@sipantau.test
 *   Password : admin123
 */

// ============================================================
// Konfigurasi database (duplikasi dari config/database.php
// karena script ini perlu membuat database sebelum USE)
// ============================================================
$db_host = 'localhost';
$db_port = '3306';
$db_name = 'sipantau_gizi';
$db_user = 'root';
$db_pass = '';

// Password default admin (akan di-hash dengan password_hash)
$admin_password = 'admin123';

// ============================================================
// Proses instalasi
// ============================================================
$messages = [];
$success = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {

    try {
        // ── Langkah 1: Koneksi tanpa memilih database ──
        $pdo = new PDO(
            "mysql:host={$db_host};port={$db_port};charset=utf8mb4",
            $db_user, $db_pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $messages[] = ['success', 'Koneksi ke MySQL berhasil.'];

        // ── Langkah 2: Buat database ──
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$db_name}`");
        $messages[] = ['success', "Database `{$db_name}` siap digunakan."];

        // ── Langkah 3: Buat tabel roles ──
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `roles` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nama_role` VARCHAR(50) NOT NULL UNIQUE,
                `deskripsi` VARCHAR(255) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        ");
        $messages[] = ['success', 'Tabel `roles` dibuat.'];

        // ── Langkah 4: Buat tabel menus ──
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `menus` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nama_menu` VARCHAR(100) NOT NULL,
                `slug` VARCHAR(100) NOT NULL UNIQUE,
                `icon` VARCHAR(50) NULL,
                `parent_id` INT NULL,
                `urutan` INT DEFAULT 0,
                FOREIGN KEY (`parent_id`) REFERENCES `menus`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");
        $messages[] = ['success', 'Tabel `menus` dibuat.'];

        // ── Langkah 5: Buat tabel role_menu ──
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `role_menu` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `role_id` INT NOT NULL,
                `menu_id` INT NOT NULL,
                FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`menu_id`) REFERENCES `menus`(`id`) ON DELETE CASCADE,
                UNIQUE KEY `unique_role_menu` (`role_id`, `menu_id`)
            ) ENGINE=InnoDB
        ");
        $messages[] = ['success', 'Tabel `role_menu` dibuat.'];

        // ── Langkah 6: Buat tabel users ──
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nama` VARCHAR(100) NOT NULL,
                `email` VARCHAR(100) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `role_id` INT NOT NULL,
                `posyandu_id` INT NULL,
                `puskesmas_id` INT NULL,
                `status` ENUM('aktif','nonaktif') DEFAULT 'aktif',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB
        ");
        $messages[] = ['success', 'Tabel `users` dibuat.'];

        // ── Langkah 7: Seed data roles ──
        $stmt = $pdo->query("SELECT COUNT(*) FROM `roles`");
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("
                INSERT INTO `roles` (`id`, `nama_role`, `deskripsi`) VALUES
                (1, 'Admin',             'Administrator sistem — akses penuh ke semua menu'),
                (2, 'Kader',             'Kader Posyandu — input data pengukuran balita'),
                (3, 'Petugas Puskesmas', 'Petugas kesehatan — validasi dan laporan'),
                (4, 'Koordinator Desa',  'Koordinator tingkat desa — monitoring dan rekapitulasi')
            ");
            $messages[] = ['success', 'Data awal `roles` (4 role) berhasil ditambahkan.'];
        } else {
            $messages[] = ['info', 'Tabel `roles` sudah berisi data — dilewati.'];
        }

        // ── Langkah 8: Seed data menus ──
        $stmt = $pdo->query("SELECT COUNT(*) FROM `menus`");
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("
                INSERT INTO `menus` (`id`, `nama_menu`, `slug`, `icon`, `parent_id`, `urutan`) VALUES
                (1, 'Dashboard',          'dashboard',    'bi-speedometer2',   NULL, 1),
                (2, 'Data Balita & Anak', 'data-anak',    'bi-person-hearts',  NULL, 2),
                (3, 'Pengukuran Gizi',    'pengukuran',   'bi-rulers',         NULL, 3),
                (4, 'Laporan & Grafik',   'laporan-gizi', 'bi-bar-chart-line', NULL, 4),
                (5, 'Data Posyandu',      'posyandu',     'bi-hospital',       NULL, 5),
                (6, 'Data Puskesmas',     'puskesmas',    'bi-building',       NULL, 6),
                (7, 'Utilitas',           'utilitas',     'bi-gear',           NULL, 10),
                (8, 'Daftar Pengguna',    'pengguna',     'bi-people',         7,   11),
                (9, 'Role & Otorisasi',   'role',         'bi-shield-lock',    7,   12)
            ");
            $messages[] = ['success', 'Data awal `menus` (9 menu) berhasil ditambahkan.'];
        } else {
            $messages[] = ['info', 'Tabel `menus` sudah berisi data — dilewati.'];
        }

        // ── Langkah 9: Seed data role_menu ──
        $stmt = $pdo->query("SELECT COUNT(*) FROM `role_menu`");
        if ((int)$stmt->fetchColumn() === 0) {
            // Admin: akses semua menu
            $pdo->exec("
                INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
                (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9)
            ");
            // Kader: Dashboard, Data Balita, Pengukuran, Posyandu
            $pdo->exec("
                INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
                (2,1),(2,2),(2,3),(2,5)
            ");
            // Petugas Puskesmas: Dashboard, Data Balita, Pengukuran, Laporan, Posyandu, Puskesmas
            $pdo->exec("
                INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
                (3,1),(3,2),(3,3),(3,4),(3,5),(3,6)
            ");
            // Koordinator Desa: Dashboard, Laporan, Posyandu, Puskesmas
            $pdo->exec("
                INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
                (4,1),(4,4),(4,5),(4,6)
            ");
            $messages[] = ['success', 'Data awal `role_menu` (hak akses) berhasil ditambahkan.'];
        } else {
            $messages[] = ['info', 'Tabel `role_menu` sudah berisi data — dilewati.'];
        }

        // ── Langkah 10: Seed user admin ──
        $stmt = $pdo->query("SELECT COUNT(*) FROM `users`");
        if ((int)$stmt->fetchColumn() === 0) {
            $hashedPassword = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO `users` (`nama`, `email`, `password`, `role_id`, `status`)
                VALUES (:nama, :email, :password, :role_id, 'aktif')
            ");
            $stmt->execute([
                ':nama'     => 'Administrator',
                ':email'    => 'admin@sipantau.test',
                ':password' => $hashedPassword,
                ':role_id'  => 1,
            ]);
            $messages[] = ['success', "User admin berhasil dibuat: <strong>admin@sipantau.test</strong> / <strong>{$admin_password}</strong>"];
        } else {
            $messages[] = ['info', 'Tabel `users` sudah berisi data — dilewati.'];
        }

        $messages[] = ['success', '<strong>Instalasi selesai!</strong> Silakan <a href="../modules/auth/login.php">login</a> dengan akun admin.'];

    } catch (PDOException $e) {
        $success = false;
        $messages[] = ['danger', 'Error: ' . htmlspecialchars($e->getMessage())];
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
    <section class="auth-card" style="max-width: 600px;">
      <div class="mb-4">
        <h1 class="h3 mb-1"><i class="bi bi-database-gear me-2"></i>Instalasi Database</h1>
        <p class="text-muted mb-0">SiPantau Gizi — Setup awal database dan data seed.</p>
      </div>

      <?php if (!empty($messages)): ?>
        <?php foreach ($messages as [$type, $msg]): ?>
          <div class="alert alert-<?= $type ?> py-2 small mb-2">
            <i class="bi bi-<?= $type === 'success' ? 'check-circle' : ($type === 'danger' ? 'x-circle' : 'info-circle') ?> me-1"></i>
            <?= $msg ?>
          </div>
        <?php endforeach; ?>

        <?php if ($success): ?>
          <div class="alert alert-warning py-2 small mt-3">
            <i class="bi bi-shield-exclamation me-1"></i>
            <strong>Penting:</strong> Hapus atau rename file <code>database/install.php</code> ini setelah instalasi demi keamanan.
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="alert alert-info py-2 small">
          <i class="bi bi-info-circle me-1"></i>
          Script ini akan membuat database <code><?= htmlspecialchars($db_name) ?></code>,
          4 tabel, dan data awal. Pastikan MySQL sudah berjalan.
        </div>

        <table class="table table-sm small mb-3">
          <tr><td class="fw-semibold">Host</td><td><?= htmlspecialchars($db_host) ?>:<?= htmlspecialchars($db_port) ?></td></tr>
          <tr><td class="fw-semibold">Database</td><td><?= htmlspecialchars($db_name) ?></td></tr>
          <tr><td class="fw-semibold">User DB</td><td><?= htmlspecialchars($db_user) ?></td></tr>
          <tr><td class="fw-semibold">Tabel</td><td>roles, menus, role_menu, users</td></tr>
          <tr><td class="fw-semibold">Akun Admin</td><td>admin@sipantau.test / <?= htmlspecialchars($admin_password) ?></td></tr>
        </table>

        <form method="POST">
          <button class="btn btn-primary w-100" type="submit" name="install" value="1">
            <i class="bi bi-database-add me-1"></i> Jalankan Instalasi
          </button>
        </form>
      <?php endif; ?>

      <div class="mt-3 text-center">
        <a href="../modules/auth/login.php" class="small text-muted">← Kembali ke Login</a>
      </div>
    </section>
  </main>
</body>
</html>
