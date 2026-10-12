<?php
/**
 * Modul Utilitas — Role & Otorisasi
 * ====================================
 * Halaman utama pengelolaan Role dan Otorisasi Hak Akses Menu.
 * Disesuaikan dengan arsitektur database db_sipantau_gizi (tabel role, menu, role_menu, user).
 */

$current_slug = 'role';
require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan hanya role Admin atau pengguna berhak yang dapat mengelola role
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'role')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengelola Role & Otorisasi.');
    redirect(base_url('modules/dashboard/index.php'));
}

// Variabel layout
$page_title  = 'Role & Otorisasi';
$breadcrumbs = [
    'Utilitas'          => '#',
    'Role & Otorisasi'  => '',
];

// ============================================================
// Filter & Pencarian
// ============================================================
$search = trim($_GET['search'] ?? '');

// ============================================================
// Query Metrik Ringkasan
// ============================================================
$total_roles       = 0;
$total_users       = 0;
$total_menus       = 0;
$total_system_roles = count(Otorisasi::SYSTEM_ROLE_IDS);

try {
    if ($pdo !== null) {
        $total_roles = (int)$pdo->query("SELECT COUNT(*) FROM role")->fetchColumn();
        $total_users = (int)$pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
        $total_menus = (int)$pdo->query("SELECT COUNT(*) FROM menu")->fetchColumn();
    }
} catch (PDOException $e) {
    // Abaikan jika query gagal
}

// ============================================================
// Query Daftar Role beserta Statistik User & Menu
// ============================================================
$roles = [];
try {
    if ($pdo !== null) {
        $where_clauses = ['1=1'];
        $params        = [];

        if ($search !== '') {
            $where_clauses[] = '(r.nama_role LIKE ? OR r.deskripsi LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $where_sql = implode(' AND ', $where_clauses);

        $sql = "
            SELECT 
                r.id_role,
                r.nama_role,
                r.deskripsi,
                r.created_at,
                r.updated_at,
                COUNT(DISTINCT u.id_user) AS user_count,
                COUNT(DISTINCT rm.id_menu) AS menu_count,
                GROUP_CONCAT(DISTINCT m.nama_menu ORDER BY m.urutan SEPARATOR '||') AS accessible_menus,
                GROUP_CONCAT(DISTINCT m.icon ORDER BY m.urutan SEPARATOR '||') AS menu_icons
            FROM role r
            LEFT JOIN user u ON r.id_role = u.id_role
            LEFT JOIN role_menu rm ON r.id_role = rm.id_role
            LEFT JOIN menu m ON rm.id_menu = m.id_menu
            WHERE {$where_sql}
            GROUP BY r.id_role, r.nama_role, r.deskripsi, r.created_at, r.updated_at
            ORDER BY r.id_role ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat data role: ' . $e->getMessage());
}

// Include header layout
require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Utilitas Sistem</p>
      <h1 class="h3 mb-1">Role & Otorisasi</h1>
      <p class="text-muted mb-0">Kelola hierarki peran pengguna dan konfigurasi matriks hak akses menu navigasi.</p>
    </div>
  </div>
  <div class="heading-actions d-flex gap-2">
    <a class="btn btn-outline-primary btn-sm" href="<?= base_url('modules/utilitas/role/otorisasi.php') ?>">
      <i class="bi bi-grid-3x3-gap me-1" aria-hidden="true"></i> Matriks Hak Akses
    </a>
    <a class="btn btn-primary btn-sm" href="<?= base_url('modules/utilitas/role/tambah.php') ?>">
      <i class="bi bi-shield-plus me-1" aria-hidden="true"></i> Tambah Role Baru
    </a>
  </div>
</div>

<!-- Kartu Metrik Ringkasan -->
<section class="row g-3 mt-1" aria-label="Ringkasan Role dan Otorisasi">
  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-primary">
      <div class="metric-top">
        <span class="metric-label">Total Role</span>
        <span class="metric-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_roles) ?></div>
      <div class="metric-meta">
        <span class="text-muted">Peran aktif terdaftar di sistem</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-success">
      <div class="metric-top">
        <span class="metric-label">Total Pengguna</span>
        <span class="metric-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_users) ?></div>
      <div class="metric-meta">
        <span class="text-success">Semua akun</span>
        <span class="text-muted">terhubung ke role</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-warning">
      <div class="metric-top">
        <span class="metric-label">Role Bawaan</span>
        <span class="metric-icon"><i class="bi bi-award" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_system_roles) ?></div>
      <div class="metric-meta">
        <span class="text-warning">Terproteksi</span>
        <span class="text-muted">oleh arsitektur sistem</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-danger">
      <div class="metric-top">
        <span class="metric-label">Modul Menu</span>
        <span class="metric-icon"><i class="bi bi-menu-button-wide" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_menus) ?></div>
      <div class="metric-meta">
        <span class="text-muted">Fitur dapat diatur aksesnya</span>
      </div>
    </article>
  </div>
</section>

<!-- Panel Tabel Role & Otorisasi -->
<section class="panel mt-3">
  <div class="panel-header flex-wrap gap-2">
    <div>
      <h2 class="h5 mb-1 section-title">
        <i class="bi bi-shield-shaded" aria-hidden="true"></i>
        <span>Daftar Peran & Hak Akses</span>
      </h2>
      <p class="text-muted mb-0">Total <?= count($roles) ?> role ditemukan dalam database.</p>
    </div>

    <!-- Form Filter & Pencarian -->
    <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2">
      <div class="input-group input-group-sm" style="min-width: 260px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" name="search" class="form-control"
               placeholder="Cari nama role atau deskripsi..."
               value="<?= e($search) ?>" aria-label="Cari role">
      </div>

      <button type="submit" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-funnel me-1"></i> Filter
      </button>

      <?php if ($search !== ''): ?>
        <a href="<?= base_url('modules/utilitas/role/index.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset pencarian">
          <i class="bi bi-x-circle me-1"></i> Reset
        </a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0" id="rolesTable">
      <thead class="table-light">
        <tr>
          <th scope="col" style="width: 50px;">#</th>
          <th scope="col" style="min-width: 180px;">Nama Role</th>
          <th scope="col" style="min-width: 240px;">Deskripsi Peran</th>
          <th scope="col" style="width: 130px;" class="text-center">Pengguna</th>
          <th scope="col">Cakupan Otorisasi Menu</th>
          <th scope="col" class="text-end" style="min-width: 160px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($roles)): ?>
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="bi bi-shield-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
              Tidak ada data role yang sesuai dengan filter pencarian.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($roles as $idx => $role): 
            $is_admin = ((int)$role['id_role'] === Otorisasi::ADMIN_ROLE_ID || strcasecmp($role['nama_role'], 'Admin') === 0);
            $is_system = Otorisasi::isSystemRole((int)$role['id_role']);
            $user_count = (int)$role['user_count'];
            $menu_count = $is_admin ? $total_menus : (int)$role['menu_count'];
            
            // Format list menu
            $menu_list = [];
            if (!empty($role['accessible_menus'])) {
                $menu_list = explode('||', $role['accessible_menus']);
            }
          ?>
            <tr>
              <!-- 1. Nomor urut -->
              <td class="text-muted small"><?= $idx + 1 ?></td>

              <!-- 2. Nama Role & Badge -->
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge <?= Otorisasi::getBadgeClass($role['nama_role']) ?> px-2 py-1 fs-6">
                    <i class="bi bi-shield me-1"></i><?= e($role['nama_role']) ?>
                  </span>
                  <?php if ($is_system): ?>
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" title="Role bawaan sistem yang terproteksi">
                      <i class="bi bi-lock-fill me-1"></i>Sistem
                    </span>
                  <?php else: ?>
                    <span class="badge bg-info-subtle text-info border border-info-subtle" title="Role kustom tambahan">
                      Kustom
                    </span>
                  <?php endif; ?>
                </div>
                <small class="text-muted d-block mt-1">ID Role: #<?= (int)$role['id_role'] ?></small>
              </td>

              <!-- 3. Deskripsi -->
              <td>
                <span class="text-body-secondary">
                  <?= !empty($role['deskripsi']) ? e($role['deskripsi']) : '<em class="text-muted">Belum ada deskripsi</em>' ?>
                </span>
              </td>

              <!-- 4. Jumlah Pengguna -->
              <td class="text-center">
                <?php if ($user_count > 0): ?>
                  <a href="<?= base_url('modules/utilitas/pengguna/index.php?role=' . (int)$role['id_role']) ?>"
                     class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none px-2 py-1"
                     title="Lihat <?= $user_count ?> pengguna dengan role ini">
                    <i class="bi bi-person-fill me-1"></i><?= $user_count ?> Akun
                  </a>
                <?php else: ?>
                  <span class="badge bg-light text-muted border px-2 py-1">
                    0 Akun
                  </span>
                <?php endif; ?>
              </td>

              <!-- 5. Cakupan Otorisasi Menu -->
              <td>
                <div class="d-flex flex-wrap align-items-center gap-1">
                  <?php if ($is_admin): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                      <i class="bi bi-check-all me-1"></i>Akses Penuh (Semua <?= $total_menus ?> Menu)
                    </span>
                  <?php elseif ($menu_count === 0): ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                      <i class="bi bi-x-circle me-1"></i>Tidak ada izin menu
                    </span>
                  <?php else: ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1">
                      <?= $menu_count ?> Menu Diizinkan
                    </span>
                    <?php 
                    // Tampilkan maksimal 3 nama menu sebagai preview ringkas
                    $preview = array_slice($menu_list, 0, 3);
                    foreach ($preview as $mName): ?>
                      <span class="badge bg-light text-dark border small fw-normal">
                        <?= e($mName) ?>
                      </span>
                    <?php endforeach; ?>
                    <?php if (count($menu_list) > 3): ?>
                      <span class="badge bg-light text-muted border small fw-normal">
                        +<?= count($menu_list) - 3 ?> lainnya
                      </span>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </td>

              <!-- 6. Aksi -->
              <td class="text-end">
                <div class="btn-group btn-group-sm" role="group" aria-label="Aksi Role">
                  <!-- Tombol Detail & Matriks Akses -->
                  <a href="<?= base_url('modules/utilitas/role/detail.php?id=' . (int)$role['id_role']) ?>"
                     class="btn btn-outline-secondary" title="Detail Role & Daftar Hak Akses">
                    <i class="bi bi-eye"></i>
                  </a>

                  <!-- Tombol Edit -->
                  <a href="<?= base_url('modules/utilitas/role/edit.php?id=' . (int)$role['id_role']) ?>"
                     class="btn btn-outline-primary" title="Edit Role & Konfigurasi Otorisasi">
                    <i class="bi bi-pencil-square"></i>
                  </a>

                  <!-- Tombol Hapus -->
                  <?php if ($is_admin): ?>
                    <button type="button" class="btn btn-outline-secondary disabled"
                            title="Role Administrator tidak dapat dihapus." aria-disabled="true">
                      <i class="bi bi-trash"></i>
                    </button>
                  <?php elseif ($is_system): ?>
                    <button type="button" class="btn btn-outline-secondary disabled"
                            title="Role bawaan sistem tidak dapat dihapus." aria-disabled="true">
                      <i class="bi bi-shield-lock"></i>
                    </button>
                  <?php elseif ($user_count > 0): ?>
                    <button type="button" class="btn btn-outline-secondary disabled"
                            title="Role ini masih digunakan oleh <?= $user_count ?> akun aktif. Pindahkan akun terlebih dahulu." aria-disabled="true">
                      <i class="bi bi-trash"></i>
                    </button>
                  <?php else: ?>
                    <button type="button" class="btn btn-outline-danger"
                            data-bs-toggle="modal"
                            data-bs-target="#deleteRoleModal"
                            data-role-id="<?= (int)$role['id_role'] ?>"
                            data-role-name="<?= e($role['nama_role']) ?>"
                            title="Hapus Role">
                      <i class="bi bi-trash"></i>
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- Modal Konfirmasi Hapus Role -->
<div class="modal fade" id="deleteRoleModal" tabindex="-1" aria-labelledby="deleteRoleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title text-danger" id="deleteRoleModalLabel">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Role
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body py-3">
        <p class="mb-2">
          Apakah Anda yakin ingin menghapus role <strong id="deleteRoleName"></strong> dari sistem?
        </p>
        <div class="alert alert-warning py-2 small mb-0">
          <i class="bi bi-info-circle me-1"></i> Tindakan ini permanen dan akan menghapus semua konfigurasi otorisasi yang tertaut ke role ini.
        </div>
      </div>
      <div class="modal-footer border-top-0 pt-0">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <form method="POST" action="<?= base_url('modules/utilitas/role/hapus.php') ?>" class="d-inline">
          <?= csrf_field() ?>
          <input type="hidden" name="id" id="deleteRoleId" value="">
          <button type="submit" class="btn btn-danger btn-sm">
            <i class="bi bi-trash me-1"></i> Ya, Hapus Role
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
// Script khusus untuk passing data ke modal konfirmasi hapus
$extra_scripts = <<<JS
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const deleteModal = document.getElementById('deleteRoleModal');
    if (deleteModal) {
      deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const roleId = button.getAttribute('data-role-id');
        const roleName = button.getAttribute('data-role-name');

        document.getElementById('deleteRoleId').value = roleId;
        document.getElementById('deleteRoleName').textContent = roleName;
      });
    }
  });
</script>
JS;

require_once ROOT_PATH . '/includes/footer.php';
?>
