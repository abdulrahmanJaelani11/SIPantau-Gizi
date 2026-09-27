<?php
/**
 * Modul Utilitas — Daftar Pengguna
 * =================================
 * Halaman utama manajemen akun pengguna di SiPantau Gizi.
 * Menampilkan daftar pengguna, metrik ringkasan, filter, serta aksi tambah/edit/hapus.
 */

require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan hanya role Admin yang bisa mengelola akun pengguna
if (($_SESSION['user_role_nama'] ?? '') !== 'Admin' && ($_SESSION['role_id'] ?? 0) != 1) {
    set_flash('danger', 'Akses ditolak. Halaman ini hanya untuk Administrator.');
    redirect(base_url('modules/dashboard/index.php'));
}

// Variabel layout
$page_title   = 'Daftar Pengguna';
$current_slug = 'pengguna';
$breadcrumbs  = [
    'Utilitas'        => '#',
    'Daftar Pengguna' => '',
];

// ============================================================
// Filter & Pencarian
// ============================================================
$search        = trim($_GET['search'] ?? '');
$filter_role   = trim($_GET['role'] ?? '');
$filter_status = trim($_GET['status'] ?? '');

// Ambil daftar role untuk dropdown filter
$roles_list = [];
try {
    $roles_stmt = $pdo->query("SELECT id, nama_role FROM roles ORDER BY id ASC");
    $roles_list = $roles_stmt->fetchAll();
} catch (PDOException $e) {
    $roles_list = [];
}

// ============================================================
// Query Metrik Ringkasan
// ============================================================
$total_users    = 0;
$active_users   = 0;
$inactive_users = 0;
$total_roles    = count($roles_list);

try {
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(*) AS total,
            COUNT(CASE WHEN status = 'aktif' THEN 1 END) AS active_count,
            COUNT(CASE WHEN status = 'nonaktif' THEN 1 END) AS inactive_count
        FROM users
    ");
    $stats = $stats_stmt->fetch();
    if ($stats) {
        $total_users    = (int) $stats['total'];
        $active_users   = (int) $stats['active_count'];
        $inactive_users = (int) $stats['inactive_count'];
    }
} catch (PDOException $e) {
    // Graceful error jika database bermasalah
}

// ============================================================
// Query Daftar Pengguna (dengan Filter)
// ============================================================
$where_clauses = ['1=1'];
$params        = [];

if ($search !== '') {
    $where_clauses[] = '(u.nama LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($filter_role !== '') {
    $where_clauses[] = 'u.role_id = ?';
    $params[] = $filter_role;
}

if ($filter_status !== '') {
    $where_clauses[] = 'u.status = ?';
    $params[] = $filter_status;
}

$where_sql = implode(' AND ', $where_clauses);
$users = [];

try {
    $sql = "
        SELECT u.id, u.nama, u.email, u.status, u.created_at,
               r.id AS role_id, r.nama_role
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE {$where_sql}
        ORDER BY u.id ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat data pengguna: ' . $e->getMessage());
}

// Helper badge role
function get_role_badge_class(?string $role_name): string {
    return match ($role_name) {
        'Admin'              => 'text-bg-primary',
        'Kader'              => 'text-bg-info',
        'Petugas Puskesmas'  => 'text-bg-success',
        'Koordinator Desa'   => 'text-bg-warning',
        default              => 'text-bg-secondary',
    };
}

// Helper inisial nama untuk avatar
function get_initials(string $name): string {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $w) {
        $initials .= mb_substr($w, 0, 1);
    }
    return strtoupper($initials ?: 'U');
}

// Include header layout
require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Utilitas Sistem</p>
      <h1 class="h3 mb-1">Daftar Pengguna</h1>
      <p class="text-muted mb-0">Kelola akun pengguna, penugasan role, dan status aktif akun.</p>
    </div>
  </div>
  <div class="heading-actions">
    <a class="btn btn-primary btn-sm" href="<?= base_url('modules/utilitas/pengguna/tambah.php') ?>">
      <i class="bi bi-person-plus me-1" aria-hidden="true"></i> Tambah Pengguna
    </a>
  </div>
</div>

<!-- Kartu Metrik Ringkasan -->
<section class="row g-3 mt-1" aria-label="Ringkasan Pengguna">
  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-primary">
      <div class="metric-top">
        <span class="metric-label">Total Pengguna</span>
        <span class="metric-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_users) ?></div>
      <div class="metric-meta">
        <span class="text-muted">Akun terdaftar di sistem</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-success">
      <div class="metric-top">
        <span class="metric-label">Pengguna Aktif</span>
        <span class="metric-icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($active_users) ?></div>
      <div class="metric-meta">
        <span class="text-success"><?= $total_users > 0 ? round(($active_users / $total_users) * 100) : 0 ?>%</span>
        <span>dapat mengakses aplikasi</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-danger">
      <div class="metric-top">
        <span class="metric-label">Nonaktif</span>
        <span class="metric-icon"><i class="bi bi-slash-circle" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($inactive_users) ?></div>
      <div class="metric-meta">
        <span class="text-danger">Akses dibekukan</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-warning">
      <div class="metric-top">
        <span class="metric-label">Total Role</span>
        <span class="metric-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_roles) ?></div>
      <div class="metric-meta">
        <span class="text-muted">Tingkatan hak akses</span>
      </div>
    </article>
  </div>
</section>

<!-- Panel Tabel Pengguna -->
<section class="panel mt-3">
  <div class="panel-header flex-wrap gap-2">
    <div>
      <h2 class="h5 mb-1 section-title">
        <i class="bi bi-table" aria-hidden="true"></i>
        <span>Data Akun Pengguna</span>
      </h2>
      <p class="text-muted mb-0">Total <?= count($users) ?> pengguna ditemukan.</p>
    </div>

    <!-- Form Filter & Pencarian -->
    <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2">
      <div class="input-group input-group-sm" style="min-width: 220px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" name="search" class="form-control"
               placeholder="Cari nama atau email..."
               value="<?= e($search) ?>" aria-label="Cari pengguna">
      </div>

      <select name="role" class="form-select form-select-sm" style="width: auto;" aria-label="Filter role">
        <option value="">Semua Role</option>
        <?php foreach ($roles_list as $r): ?>
          <option value="<?= $r['id'] ?>" <?= (string)$filter_role === (string)$r['id'] ? 'selected' : '' ?>>
            <?= e($r['nama_role']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="status" class="form-select form-select-sm" style="width: auto;" aria-label="Filter status">
        <option value="">Semua Status</option>
        <option value="aktif" <?= $filter_status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
        <option value="nonaktif" <?= $filter_status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
      </select>

      <button type="submit" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-funnel me-1"></i> Filter
      </button>

      <?php if ($search !== '' || $filter_role !== '' || $filter_status !== ''): ?>
        <a href="<?= base_url('modules/utilitas/pengguna/index.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset filter">
          <i class="bi bi-x-circle me-1"></i> Reset
        </a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0" id="usersTable">
      <thead class="table-light">
        <tr>
          <th scope="col" style="width: 50px;">#</th>
          <th scope="col">Pengguna</th>
          <th scope="col">Role</th>
          <th scope="col">Status</th>
          <th scope="col">Terdaftar</th>
          <th scope="col" class="text-end" style="min-width: 140px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
              <p class="mb-1 fw-semibold">Tidak ada pengguna yang ditemukan</p>
              <small>Coba sesuaikan kata kunci pencarian atau filter yang Anda pilih.</small>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($users as $index => $u): ?>
            <?php 
              $is_current_user = ((int)$u['id'] === (int)$_SESSION['user_id']);
            ?>
            <tr>
              <td class="text-muted small"><?= $index + 1 ?></td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar-sm rounded-circle d-flex align-items-center justify-content-center text-white fw-bold bg-primary bg-gradient"
                       style="width: 36px; height: 36px; font-size: 0.85rem; flex-shrink: 0;">
                    <?= get_initials($u['nama']) ?>
                  </div>
                  <div>
                    <div class="fw-semibold">
                      <?= e($u['nama']) ?>
                      <?php if ($is_current_user): ?>
                        <span class="badge text-bg-light border text-primary ms-1" style="font-size: 0.7rem;">Anda</span>
                      <?php endif; ?>
                    </div>
                    <div class="text-muted small"><?= e($u['email']) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge <?= get_role_badge_class($u['nama_role']) ?>">
                  <i class="bi bi-shield-check me-1"></i><?= e($u['nama_role'] ?? 'Tanpa Role') ?>
                </span>
              </td>
              <td>
                <?php if ($u['status'] === 'aktif'): ?>
                  <span class="badge text-bg-success">
                    <i class="bi bi-check-circle-fill me-1"></i>Aktif
                  </span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">
                    <i class="bi bi-dash-circle-fill me-1"></i>Nonaktif
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <span class="small text-muted" title="<?= e($u['created_at']) ?>">
                  <?= date('d M Y, H:i', strtotime($u['created_at'])) ?>
                </span>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm" role="group" aria-label="Aksi Pengguna">
                  <a href="<?= base_url('modules/utilitas/pengguna/edit.php?id=' . $u['id']) ?>"
                     class="btn btn-outline-primary"
                     title="Edit Pengguna">
                    <i class="bi bi-pencil-square"></i>
                    <span class="d-none d-md-inline ms-1">Edit</span>
                  </a>

                  <?php if (!$is_current_user): ?>
                    <button type="button"
                            class="btn btn-outline-danger btn-delete-user"
                            data-bs-toggle="modal"
                            data-bs-target="#deleteModal"
                            data-user-id="<?= $u['id'] ?>"
                            data-user-nama="<?= e($u['nama']) ?>"
                            data-user-email="<?= e($u['email']) ?>"
                            title="Hapus Pengguna">
                      <i class="bi bi-trash"></i>
                      <span class="d-none d-md-inline ms-1">Hapus</span>
                    </button>
                  <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary disabled" title="Tidak dapat menghapus akun Anda sendiri">
                      <i class="bi bi-lock"></i>
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

<!-- Modal Konfirmasi Hapus Pengguna -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title text-danger" id="deleteModalLabel">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Pengguna
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <form method="POST" action="<?= base_url('modules/utilitas/pengguna/hapus.php') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="deleteUserId" value="">
        <div class="modal-body py-3">
          <p class="mb-2">Apakah Anda yakin ingin menghapus akun pengguna berikut?</p>
          <div class="p-3 bg-light rounded border mb-2">
            <div class="fw-bold" id="deleteUserNama">—</div>
            <div class="text-muted small" id="deleteUserEmail">—</div>
          </div>
          <p class="text-danger small mb-0">
            <i class="bi bi-info-circle me-1"></i> Tindakan ini tidak dapat dibatalkan. Riwayat data pengguna ini akan dihapus dari sistem.
          </p>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger btn-sm">
            <i class="bi bi-trash me-1"></i> Ya, Hapus Pengguna
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
// Script modal delete handler
$extra_scripts = '
<script>
document.addEventListener("DOMContentLoaded", function () {
  var deleteModal = document.getElementById("deleteModal");
  if (deleteModal) {
    deleteModal.addEventListener("show.bs.modal", function (event) {
      var button = event.relatedTarget;
      var userId = button.getAttribute("data-user-id");
      var userNama = button.getAttribute("data-user-nama");
      var userEmail = button.getAttribute("data-user-email");

      document.getElementById("deleteUserId").value = userId;
      document.getElementById("deleteUserNama").textContent = userNama;
      document.getElementById("deleteUserEmail").textContent = userEmail;
    });
  }
});
</script>
';

// Include footer layout
require_once ROOT_PATH . '/includes/footer.php';
