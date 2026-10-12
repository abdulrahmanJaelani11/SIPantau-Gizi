<?php
/**
 * Modul Utilitas — Detail Role & Otorisasi
 * ==========================================
 * Halaman detail profil peran, daftar hak akses menu, dan akun pengguna tertaut.
 */

$current_slug = 'role';
require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan hanya pengguna yang berhak yang bisa mengakses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'role')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk melihat detail Role.');
    redirect(base_url('modules/dashboard/index.php'));
}

// Validasi ID Role
$role_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$role_id) {
    set_flash('danger', 'ID Role tidak valid.');
    redirect(base_url('modules/utilitas/role/index.php'));
}

// Ambil data role
$role = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM role WHERE id_role = ? LIMIT 1");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat data role: ' . $e->getMessage());
    redirect(base_url('modules/utilitas/role/index.php'));
}

if (!$role) {
    set_flash('danger', 'Role tidak ditemukan.');
    redirect(base_url('modules/utilitas/role/index.php'));
}

$is_admin = ((int)$role['id_role'] === Otorisasi::ADMIN_ROLE_ID || strcasecmp($role['nama_role'], 'Admin') === 0);
$is_system = Otorisasi::isSystemRole((int)$role['id_role']);

// Ambil pengguna tertaut
$assigned_users = [];
try {
    $stmtUsers = $pdo->prepare("
        SELECT id_user, nama, username, email, status, created_at
        FROM user
        WHERE id_role = ?
        ORDER BY nama ASC
    ");
    $stmtUsers->execute([$role_id]);
    $assigned_users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $assigned_users = [];
}

// Ambil daftar menu sistem & hak akses role
$menus_grouped = Otorisasi::getAllMenusGrouped();
$allowed_menu_ids = Otorisasi::getMenuIdsForRole($role_id);

// Variabel layout
$page_title  = 'Detail Role — ' . $role['nama_role'];
$breadcrumbs = [
    'Utilitas'          => '#',
    'Role & Otorisasi'  => base_url('modules/utilitas/role/index.php'),
    'Detail Role'       => '',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-shield-shaded" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Utilitas Sistem &rsaquo; Role & Otorisasi</p>
      <h1 class="h3 mb-1">Detail Role: <?= e($role['nama_role']) ?></h1>
      <p class="text-muted mb-0">Informasi spesifikasi peran, pengguna terhubung, dan matriks hak akses menu.</p>
    </div>
  </div>
  <div class="heading-actions d-flex gap-2">
    <a class="btn btn-primary btn-sm" href="<?= base_url('modules/utilitas/role/edit.php?id=' . (int)$role['id_role']) ?>">
      <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Edit Role & Otorisasi
    </a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('modules/utilitas/role/index.php') ?>">
      <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
  </div>
</div>

<div class="row g-4 mt-1">
  <!-- Kolom Kiri: Profil Role & Daftar Pengguna -->
  <div class="col-12 col-lg-5">
    <!-- Panel Profil Ringkas -->
    <div class="panel mb-4">
      <div class="panel-header d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0 section-title">
          <i class="bi bi-award text-primary me-2"></i>Spesifikasi Role
        </h2>
        <span class="badge <?= Otorisasi::getBadgeClass($role['nama_role']) ?> px-2 py-1">
          ID #<?= (int)$role['id_role'] ?>
        </span>
      </div>
      <div class="p-3 p-lg-4">
        <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
          <div class="brand-icon" style="width: 52px; height: 52px; font-size: 1.5rem;">
            <i class="bi bi-shield-check"></i>
          </div>
          <div>
            <h4 class="mb-0 fw-bold"><?= e($role['nama_role']) ?></h4>
            <div class="d-flex gap-2 mt-1">
              <?php if ($is_system): ?>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                  <i class="bi bi-lock-fill me-1"></i>Role Sistem Terproteksi
                </span>
              <?php else: ?>
                <span class="badge bg-info-subtle text-info border border-info-subtle">
                  Role Kustom
                </span>
              <?php endif; ?>

              <?php if ($is_admin): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle">
                  Super Admin
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label class="small text-muted fw-semibold d-block mb-1">Deskripsi & Cakupan Fungsi</label>
          <div class="p-3 bg-light rounded-3 text-secondary small">
            <?= !empty($role['deskripsi']) ? nl2br(e($role['deskripsi'])) : '<em>Tidak ada catatan deskripsi khusus untuk role ini.</em>' ?>
          </div>
        </div>

        <div class="row g-2 text-center pt-2">
          <div class="col-6">
            <div class="p-2 border rounded-3 bg-light-subtle">
              <span class="small text-muted d-block">Pengguna Tertaut</span>
              <strong class="fs-5 text-primary"><?= count($assigned_users) ?></strong>
            </div>
          </div>
          <div class="col-6">
            <div class="p-2 border rounded-3 bg-light-subtle">
              <span class="small text-muted d-block">Menu Diizinkan</span>
              <strong class="fs-5 text-success"><?= $is_admin ? 'Semua' : count($allowed_menu_ids) ?></strong>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Panel Pengguna yang Menggunakan Role Ini -->
    <div class="panel">
      <div class="panel-header d-flex justify-content-between align-items-center">
        <div>
          <h2 class="h5 mb-0 section-title">
            <i class="bi bi-people text-primary me-2"></i>Pengguna Terhubung
          </h2>
          <small class="text-muted"><?= count($assigned_users) ?> akun terdaftar dengan role ini</small>
        </div>
        <a href="<?= base_url('modules/utilitas/pengguna/tambah.php') ?>" class="btn btn-outline-primary btn-sm" title="Tambah Pengguna">
          <i class="bi bi-person-plus"></i>
        </a>
      </div>
      <div class="p-0">
        <?php if (empty($assigned_users)): ?>
          <div class="p-4 text-center text-muted small">
            <i class="bi bi-person-x fs-3 d-block mb-1 text-secondary opacity-50"></i>
            Belum ada akun pengguna yang menggunakan role ini.
          </div>
        <?php else: ?>
          <ul class="list-group list-group-flush small mb-0">
            <?php foreach ($assigned_users as $u): ?>
              <li class="list-group-item d-flex align-items-center justify-content-between px-3 py-2">
                <div class="d-flex align-items-center gap-2">
                  <div class="profile-avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                       style="width: 32px; height: 32px; font-size: 0.75rem; font-weight: bold;">
                    <?= strtoupper(substr($u['nama'], 0, 1)) ?>
                  </div>
                  <div>
                    <strong class="d-block text-dark"><?= e($u['nama']) ?></strong>
                    <span class="text-muted" style="font-size: 0.75rem;">@<?= e($u['username']) ?> <?= !empty($u['email']) ? '· ' . e($u['email']) : '' ?></span>
                  </div>
                </div>
                <div>
                  <?php if (strtolower($u['status']) === 'aktif'): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                  <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Nonaktif</span>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Kolom Kanan: Matriks Otorisasi Menu Lengkap -->
  <div class="col-12 col-lg-7">
    <div class="panel">
      <div class="panel-header d-flex justify-content-between align-items-center">
        <div>
          <h2 class="h5 mb-1 section-title">
            <i class="bi bi-list-check text-primary me-2"></i>Matriks Hak Akses Menu
          </h2>
          <p class="text-muted small mb-0">Rincian status akses setiap modul menu navigasi untuk role ini.</p>
        </div>
        <a href="<?= base_url('modules/utilitas/role/edit.php?id=' . (int)$role['id_role']) ?>" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-gear me-1"></i> Ubah Otorisasi
        </a>
      </div>

      <div class="p-3 p-lg-4">
        <?php if ($is_admin): ?>
          <div class="alert alert-success py-2 small mb-4">
            <i class="bi bi-shield-check me-1"></i> Role ini memiliki hak akses penuh secara absolut ke seluruh fitur dan menu sistem.
          </div>
        <?php endif; ?>

        <?php foreach ($menus_grouped as $kategori => $menuList): ?>
          <div class="mb-4">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
              <h6 class="fw-bold text-uppercase text-secondary small mb-0 tracking-wider">
                <i class="bi bi-folder2-open me-1"></i>Kategori: <?= e($kategori) ?>
              </h6>
              <span class="badge bg-light text-muted border small">
                <?= count($menuList) ?> Menu
              </span>
            </div>

            <div class="list-group list-group-flush border rounded-3 overflow-hidden">
              <?php foreach ($menuList as $menu): 
                $is_dashboard = ($menu['slug'] === 'dashboard');
                $allowed = $is_admin || $is_dashboard || in_array((int)$menu['id_menu'], $allowed_menu_ids, true);
              ?>
                <div class="list-group-item d-flex align-items-center justify-content-between p-3 <?= $allowed ? 'bg-white' : 'bg-light-subtle opacity-75' ?>">
                  <div class="d-flex align-items-center gap-3">
                    <span class="d-inline-flex p-2 rounded-2 <?= $allowed ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-muted' ?>">
                      <i class="bi <?= e($menu['icon'] ?: 'bi-circle') ?> fs-5"></i>
                    </span>
                    <div>
                      <strong class="d-block <?= $allowed ? 'text-dark' : 'text-muted' ?>">
                        <?= e($menu['nama_menu']) ?>
                      </strong>
                      <span class="badge bg-secondary-subtle text-secondary small py-0 px-1 font-monospace" style="font-size: 0.7rem;">
                        slug: <?= e($menu['slug']) ?>
                      </span>
                    </div>
                  </div>

                  <div>
                    <?php if ($allowed): ?>
                      <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                        <i class="bi bi-check-circle-fill me-1"></i>Diizinkan
                      </span>
                    <?php else: ?>
                      <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                        <i class="bi bi-slash-circle me-1"></i>Ditolak
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php
require_once ROOT_PATH . '/includes/footer.php';
?>
