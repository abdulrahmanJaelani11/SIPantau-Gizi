<?php
/**
 * Modul Utilitas — Matriks Hak Akses & Otorisasi Global
 * =======================================================
 * Halaman grid matriks lintas-peran untuk meninjau dan mengonfigurasi
 * hak akses menu untuk seluruh role sistem dalam satu tampilan terpadu.
 */

$current_slug = 'role';
require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan hanya role yang berhak yang bisa mengakses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'role')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengelola Matriks Otorisasi.');
    redirect(base_url('modules/dashboard/index.php'));
}

// Ambil semua role
$roles = [];
try {
    $roles = $pdo->query("SELECT id_role, nama_role, deskripsi FROM role ORDER BY id_role ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $roles = [];
}

// Ambil semua menu
$menus = [];
try {
    $stmtMenu = $pdo->query("
        SELECT id_menu, nama_menu, slug, icon, kategori, urutan 
        FROM menu 
        ORDER BY 
            CASE kategori
                WHEN 'Utama' THEN 1
                WHEN 'Data & Laporan' THEN 2
                WHEN 'Master Data' THEN 3
                WHEN 'Utilitas' THEN 4
                ELSE 5
            END,
            urutan ASC, id_menu ASC
    ");
    $menus = $stmtMenu->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $menus = [];
}

// Ambil pemetaan hak akses saat ini [role_id => [menu_id1, menu_id2, ...]]
$matrix_map = [];
try {
    $rm_stmt = $pdo->query("SELECT id_role, id_menu FROM role_menu");
    while ($row = $rm_stmt->fetch(PDO::FETCH_ASSOC)) {
        $matrix_map[(int)$row['id_role']][] = (int)$row['id_menu'];
    }
} catch (PDOException $e) {
    $matrix_map = [];
}

$errors = [];

// ============================================================
// Proses Simpan Matriks Hak Akses Batch (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $posted_matrix = $_POST['matrix'] ?? []; // format: [role_id => [menu_id1, menu_id2, ...]]

        try {
            $pdo->beginTransaction();

            foreach ($roles as $r) {
                $rId = (int)$r['id_role'];
                $isAdmin = ($rId === Otorisasi::ADMIN_ROLE_ID || strcasecmp($r['nama_role'], 'Admin') === 0);

                if ($isAdmin) {
                    // Admin selalu memiliki akses ke seluruh menu
                    $allMenuIds = array_column($menus, 'id_menu');
                    Otorisasi::syncRoleMenus($rId, $allMenuIds);
                } else {
                    $selectedMenuIds = isset($posted_matrix[$rId]) && is_array($posted_matrix[$rId])
                        ? array_map('intval', $posted_matrix[$rId])
                        : [];
                    Otorisasi::syncRoleMenus($rId, $selectedMenuIds);
                }
            }

            $pdo->commit();
            set_flash('success', 'Matriks otorisasi seluruh role berhasil diperbarui!');
            redirect(base_url('modules/utilitas/role/otorisasi.php'));

        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Gagal menyimpan perubahan matriks: ' . $e->getMessage();
        }
    }
}

// Variabel layout
$page_title  = 'Matriks Hak Akses & Otorisasi';
$breadcrumbs = [
    'Utilitas'          => '#',
    'Role & Otorisasi'  => base_url('modules/utilitas/role/index.php'),
    'Matriks Otorisasi' => '',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Utilitas Sistem &rsaquo; Role & Otorisasi</p>
      <h1 class="h3 mb-1">Matriks Hak Akses Menu</h1>
      <p class="text-muted mb-0">Tinjau dan sesuaikan perizinan seluruh menu aplikasi per role dalam satu tabel matriks.</p>
    </div>
  </div>
  <div class="heading-actions d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('modules/utilitas/role/index.php') ?>">
      <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali ke Daftar Role
    </a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <?= implode('<br>', array_map('e', $errors)) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
  </div>
<?php endif; ?>

<div class="alert alert-info py-2 small mt-3">
  <i class="bi bi-info-circle me-1"></i>
  <strong>Petunjuk:</strong> Centang kotak untuk memberikan izin akses ke modul terkait. Perubahan pada menu <em>Dashboard</em> (wajib aktif) dan hak akses <em>Administrator</em> (akses penuh) diproteksi secara otomatis.
</div>

<form method="POST" action="" class="mt-3">
  <?= csrf_field() ?>

  <div class="panel">
    <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h2 class="h5 mb-0 section-title">
        <i class="bi bi-table text-primary me-2"></i>Tabel Matriks RBAC
      </h2>
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="bi bi-check2-circle me-1"></i> Simpan Seluruh Perubahan Matriks
      </button>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered table-hover align-middle mb-0 text-center" id="matrixTable">
        <thead class="table-light">
          <tr>
            <th scope="col" class="text-start" style="min-width: 260px;">Modul & Menu Sistem</th>
            <th scope="col" style="width: 110px;">Kategori</th>
            <?php foreach ($roles as $r): 
              $is_admin = ((int)$r['id_role'] === Otorisasi::ADMIN_ROLE_ID || strcasecmp($r['nama_role'], 'Admin') === 0);
            ?>
              <th scope="col" style="min-width: 140px;">
                <span class="badge <?= Otorisasi::getBadgeClass($r['nama_role']) ?> d-block mb-1">
                  <?= e($r['nama_role']) ?>
                </span>
                <a href="<?= base_url('modules/utilitas/role/edit.php?id=' . (int)$r['id_role']) ?>"
                   class="small text-muted text-decoration-none" title="Ubah role ini">
                  <i class="bi bi-pencil-square"></i> Edit
                </a>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($menus as $m): 
            $is_dashboard = ($m['slug'] === 'dashboard');
          ?>
            <tr>
              <!-- Kolom Info Menu -->
              <td class="text-start">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi <?= e($m['icon'] ?: 'bi-circle') ?> text-primary fs-5"></i>
                  <div>
                    <strong class="d-block text-dark small"><?= e($m['nama_menu']) ?></strong>
                    <span class="badge bg-secondary-subtle text-secondary small py-0 px-1 font-monospace" style="font-size: 0.7rem;">
                      <?= e($m['slug']) ?>
                    </span>
                  </div>
                </div>
              </td>

              <!-- Kategori -->
              <td>
                <span class="badge bg-light text-dark border small fw-normal">
                  <?= e($m['kategori'] ?: 'Utama') ?>
                </span>
              </td>

              <!-- Kolom Checkbox per Role -->
              <?php foreach ($roles as $r): 
                $rId = (int)$r['id_role'];
                $isAdmin = ($rId === Otorisasi::ADMIN_ROLE_ID || strcasecmp($r['nama_role'], 'Admin') === 0);
                $isAllowed = $isAdmin || $is_dashboard || (isset($matrix_map[$rId]) && in_array((int)$m['id_menu'], $matrix_map[$rId], true));
              ?>
                <td class="<?= $isAllowed ? 'bg-success-subtle bg-opacity-25' : '' ?>">
                  <?php if ($isAdmin): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle" title="Admin memiliki akses penuh otomatis">
                      <i class="bi bi-check-all"></i> Aktif
                    </span>
                  <?php elseif ($is_dashboard): ?>
                    <input type="hidden" name="matrix[<?= $rId ?>][]" value="<?= (int)$m['id_menu'] ?>">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" title="Wajib untuk semua role">
                      <i class="bi bi-lock-fill"></i> Wajib
                    </span>
                  <?php else: ?>
                    <div class="form-check d-flex justify-content-center mb-0">
                      <input class="form-check-input matrix-checkbox"
                             type="checkbox"
                             name="matrix[<?= $rId ?>][]"
                             value="<?= (int)$m['id_menu'] ?>"
                             <?= $isAllowed ? 'checked' : '' ?>
                             aria-label="Izin <?= e($m['nama_menu']) ?> untuk <?= e($r['nama_role']) ?>">
                    </div>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="panel-footer bg-light border-top p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
      <small class="text-muted">
        <i class="bi bi-shield-check me-1"></i> Data otorisasi disimpan ke dalam tabel <code>role_menu</code> terelasi dengan <code>role</code> & <code>menu</code>.
      </small>
      <div class="d-flex gap-2">
        <a href="<?= base_url('modules/utilitas/role/index.php') ?>" class="btn btn-secondary btn-sm">
          Batal
        </a>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-check2-circle me-1"></i> Simpan Matriks Hak Akses
        </button>
      </div>
    </div>
  </div>
</form>

<?php
require_once ROOT_PATH . '/includes/footer.php';
?>
