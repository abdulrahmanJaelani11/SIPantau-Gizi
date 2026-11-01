<?php
/**
 * Modul Utilitas — Edit Role & Otorisasi
 * ========================================
 * Halaman form untuk mengubah informasi peran dan konfigurasi hak akses menu navigasinya.
 * Disesuaikan dengan arsitektur database db_sipantau_gizi.
 */

$current_slug = 'role';
require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan hanya role yang berhak yang bisa mengakses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'role')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengedit Role.');
    redirect(base_url('modules/dashboard/index.php'));
}

// 1. Ambil dan validasi ID Role dari URL
$role_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$role_id) {
    set_flash('danger', 'ID Role tidak valid.');
    redirect(base_url('modules/utilitas/role/index.php'));
}

// 2. Ambil data role dari database
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
    set_flash('danger', 'Role dengan ID tersebut tidak ditemukan.');
    redirect(base_url('modules/utilitas/role/index.php'));
}

$is_admin = ((int)$role['id_role'] === Otorisasi::ADMIN_ROLE_ID || strcasecmp($role['nama_role'], 'Admin') === 0);
$is_system = Otorisasi::isSystemRole((int)$role['id_role']);

// Ambil semua menu dan hak akses saat ini
$menus_grouped = Otorisasi::getAllMenusGrouped();
$current_menu_ids = Otorisasi::getMenuIdsForRole($role_id);
$user_count = Otorisasi::getUserCount($role_id);

$errors = [];

// ============================================================
// Proses Update Data (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Cek CSRF
    if (!verify_csrf_token()) {
        $errors[] = 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba lagi.';
    }

    $nama_role = trim($_POST['nama_role'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $menu_ids  = isset($_POST['menus']) && is_array($_POST['menus']) ? array_map('intval', $_POST['menus']) : [];

    // Jika Admin, selalu pertahankan semua menu
    if ($is_admin) {
        $all_menus_stmt = $pdo->query("SELECT id_menu FROM menu");
        $menu_ids = array_map('intval', $all_menus_stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // 2. Validasi Nama Role
    if ($nama_role === '') {
        $errors['nama_role'] = 'Nama role wajib diisi.';
    } elseif (mb_strlen($nama_role) < 3) {
        $errors['nama_role'] = 'Nama role minimal 3 karakter.';
    } elseif (mb_strlen($nama_role) > 50) {
        $errors['nama_role'] = 'Nama role maksimal 50 karakter.';
    } else {
        // Cek keunikan nama role terhadap role lain
        try {
            $stmt = $pdo->prepare("SELECT id_role FROM role WHERE LOWER(nama_role) = LOWER(?) AND id_role != ? LIMIT 1");
            $stmt->execute([$nama_role, $role_id]);
            if ($stmt->fetch()) {
                $errors['nama_role'] = 'Nama role "' . e($nama_role) . '" sudah digunakan oleh role lain.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Gagal memeriksa ketersediaan nama role: ' . $e->getMessage();
        }
    }

    // 3. Eksekusi Update jika Valid
    if (empty($errors) && $pdo !== null) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE role 
                SET nama_role = ?, deskripsi = ?, updated_at = NOW() 
                WHERE id_role = ?
            ");
            $stmt->execute([$nama_role, $deskripsi ?: null, $role_id]);

            $pdo->commit();

            // Sinkronkan otorisasi menu
            Otorisasi::syncRoleMenus($role_id, $menu_ids);

            // Jika mengedit role yang sedang digunakan oleh user yang login, perbarui session nama_role
            if ((int)$_SESSION['role_id'] === $role_id) {
                $_SESSION['user_role_nama'] = $nama_role;
            }

            clear_old_input();
            set_flash('success', "Perubahan pada role <strong>" . e($nama_role) . "</strong> dan otorisasi menu berhasil disimpan!");
            redirect(base_url('modules/utilitas/role/index.php'));

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Gagal menyimpan perubahan role: ' . $e->getMessage();
        }
    }
}

// Nilai field aktif (old input jika ada error submit, jika tidak pakai data DB)
$val_nama_role = old('nama_role', $role['nama_role']);
$val_deskripsi = old('deskripsi', $role['deskripsi'] ?? '');
$active_menu_ids = !empty($_POST) ? (isset($_POST['menus']) && is_array($_POST['menus']) ? array_map('intval', $_POST['menus']) : []) : $current_menu_ids;

// Variabel layout
$page_title  = 'Edit Role & Otorisasi';
$breadcrumbs = [
    'Utilitas'          => '#',
    'Role & Otorisasi'  => base_url('modules/utilitas/role/index.php'),
    'Edit Role'         => '',
];

// Include header layout
require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-pencil-square" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Utilitas Sistem &rsaquo; Role & Otorisasi</p>
      <h1 class="h3 mb-1">Edit Role: <?= e($role['nama_role']) ?></h1>
      <p class="text-muted mb-0">Sesuaikan nama peran, catatan wewenang, dan matriks hak akses menu navigasi.</p>
    </div>
  </div>
  <div class="heading-actions d-flex gap-2">
    <a class="btn btn-outline-info btn-sm" href="<?= base_url('modules/utilitas/role/detail.php?id=' . (int)$role['id_role']) ?>">
      <i class="bi bi-eye me-1" aria-hidden="true"></i> Lihat Detail
    </a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('modules/utilitas/role/index.php') ?>">
      <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali ke Daftar
    </a>
  </div>
</div>

<!-- Alert Error Validasi -->
<?php if (!empty($errors)): ?>
  <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
    <div class="d-flex align-items-center mb-1">
      <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
      <strong>Terdapat beberapa kesalahan:</strong>
    </div>
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $field => $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
  </div>
<?php endif; ?>

<form method="POST" action="" class="mt-3">
  <?= csrf_field() ?>

  <div class="row g-4">
    <!-- Kolom Kiri: Informasi Role -->
    <div class="col-12 col-lg-5">
      <div class="panel h-100">
        <div class="panel-header d-flex justify-content-between align-items-center">
          <h2 class="h5 mb-0 section-title">
            <i class="bi bi-info-circle text-primary me-2"></i>Informasi Peran
          </h2>
          <span class="badge <?= Otorisasi::getBadgeClass($role['nama_role']) ?>">
            ID #<?= (int)$role['id_role'] ?>
          </span>
        </div>
        <div class="p-3 p-lg-4">
          <?php if ($is_system): ?>
            <div class="alert alert-warning py-2 small mb-3">
              <i class="bi bi-lock-fill me-1"></i>
              <strong>Role Bawaan Sistem:</strong> Role ini dilindungi oleh arsitektur aplikasi. Hati-hati saat mengubah nama role agar tidak memengaruhi referensi fungsional kode.
            </div>
          <?php endif; ?>

          <div class="mb-3">
            <label for="nama_role" class="form-label fw-semibold">
              Nama Role <span class="text-danger">*</span>
            </label>
            <input type="text"
                   name="nama_role"
                   id="nama_role"
                   class="form-control <?= isset($errors['nama_role']) ? 'is-invalid' : '' ?>"
                   placeholder="Contoh: Admin / Kader / Petugas"
                   value="<?= e($val_nama_role) ?>"
                   required
                   maxlength="50"
                   <?= $is_admin ? 'readonly title="Nama Administrator tidak dapat diubah."' : '' ?>>
            <div class="form-text">
              <?= $is_admin ? 'Nama role Administrator bersifat permanen.' : 'Nama unik untuk peran ini (3-50 karakter).' ?>
            </div>
            <?php if (isset($errors['nama_role'])): ?>
              <div class="invalid-feedback"><?= e($errors['nama_role']) ?></div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label for="deskripsi" class="form-label fw-semibold">Deskripsi & Catatan Wewenang</label>
            <textarea name="deskripsi"
                      id="deskripsi"
                      rows="4"
                      class="form-control"
                      placeholder="Jelaskan fungsi peran ini di sistem..."><?= e($val_deskripsi) ?></textarea>
            <div class="form-text">
              Opsional. Menjelaskan lingkup tanggung jawab pengguna role ini.
            </div>
          </div>

          <div class="p-3 bg-light rounded-3 border">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="small text-muted">Pengguna Aktif:</span>
              <a href="<?= base_url('modules/utilitas/pengguna/index.php?role=' . (int)$role['id_role']) ?>"
                 class="badge bg-primary text-decoration-none">
                <i class="bi bi-people me-1"></i><?= $user_count ?> Akun
              </a>
            </div>
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="small text-muted">Status Proteksi:</span>
              <span class="small fw-semibold"><?= $is_system ? 'Terproteksi Sistem' : 'Kustom' ?></span>
            </div>
            <div class="d-flex align-items-center justify-content-between">
              <span class="small text-muted">Hak Akses Menu:</span>
              <span class="small fw-semibold"><?= $is_admin ? 'Semua (Bypass)' : count($current_menu_ids) . ' Menu' ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Kolom Kanan: Matriks Otorisasi Menu -->
    <div class="col-12 col-lg-7">
      <div class="panel">
        <div class="panel-header d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div>
            <h2 class="h5 mb-1 section-title">
              <i class="bi bi-ui-checks-grid text-primary me-2"></i>Otorisasi Menu Navigasi
            </h2>
            <p class="text-muted small mb-0">Atur menu navigasi mana saja yang dapat dibuka oleh role ini.</p>
          </div>
          <?php if (!$is_admin): ?>
            <div class="btn-group btn-group-sm">
              <button type="button" class="btn btn-outline-primary" id="btnCheckAll">
                <i class="bi bi-check-all me-1"></i>Pilih Semua
              </button>
              <button type="button" class="btn btn-outline-secondary" id="btnUncheckAll">
                <i class="bi bi-dash me-1"></i>Batal Semua
              </button>
            </div>
          <?php endif; ?>
        </div>

        <div class="p-3 p-lg-4">
          <?php if ($is_admin): ?>
            <div class="alert alert-success py-3 mb-4">
              <div class="d-flex align-items-center">
                <i class="bi bi-shield-fill-check fs-3 me-3 text-success"></i>
                <div>
                  <h6 class="alert-heading fw-bold mb-1">Akses Penuh Administrator</h6>
                  <p class="mb-0 small">
                    Role <strong>Administrator</strong> memiliki wewenang super-admin terhadap seluruh menu di sistem SiPantau Gizi secara otomatis. Konfigurasi per-menu tidak dibatasi untuk mencegah administrator terkunci dari sistem.
                  </p>
                </div>
              </div>
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

              <div class="row g-2">
                <?php foreach ($menuList as $menu): 
                  $is_dashboard = ($menu['slug'] === 'dashboard');
                  $is_checked = $is_admin || $is_dashboard || in_array((int)$menu['id_menu'], $active_menu_ids, true);
                ?>
                  <div class="col-12 col-sm-6">
                    <div class="card h-100 border p-2 rounded-3 hover-shadow-sm transition-all <?= ($is_admin || $is_dashboard) ? 'bg-light-subtle' : '' ?>">
                      <div class="form-check d-flex align-items-start gap-2 mb-0">
                        <input class="form-check-input mt-1 menu-checkbox"
                               type="checkbox"
                               name="menus[]"
                               value="<?= (int)$menu['id_menu'] ?>"
                               id="menu_<?= (int)$menu['id_menu'] ?>"
                               <?= $is_checked ? 'checked' : '' ?>
                               <?= ($is_admin || $is_dashboard) ? 'disabled' : '' ?>>
                        
                        <?php if ($is_admin || $is_dashboard): ?>
                          <input type="hidden" name="menus[]" value="<?= (int)$menu['id_menu'] ?>">
                        <?php endif; ?>

                        <label class="form-check-label w-100 cursor-pointer" for="menu_<?= (int)$menu['id_menu'] ?>">
                          <div class="d-flex align-items-center gap-2">
                            <i class="bi <?= e($menu['icon'] ?: 'bi-circle') ?> text-primary fs-5"></i>
                            <div>
                              <strong class="d-block text-dark small"><?= e($menu['nama_menu']) ?></strong>
                              <span class="badge bg-secondary-subtle text-secondary small py-0 px-1 font-monospace" style="font-size: 0.7rem;">
                                <?= e($menu['slug']) ?>
                              </span>
                            </div>
                          </div>
                          <?php if ($is_dashboard): ?>
                            <small class="text-muted d-block mt-1 fst-italic" style="font-size: 0.75rem;">
                              Wajib aktif untuk beranda sistem.
                            </small>
                          <?php endif; ?>
                        </label>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="panel-footer bg-light border-top p-3 d-flex justify-content-end gap-2">
          <a href="<?= base_url('modules/utilitas/role/index.php') ?>" class="btn btn-secondary btn-sm">
            Batal
          </a>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-save me-1"></i> Simpan Perubahan Role
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<?php
$extra_scripts = <<<JS
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const btnCheckAll = document.getElementById('btnCheckAll');
    const btnUncheckAll = document.getElementById('btnUncheckAll');
    const checkboxes = document.querySelectorAll('.menu-checkbox:not([disabled])');

    if (btnCheckAll) {
      btnCheckAll.addEventListener('click', function () {
        checkboxes.forEach(cb => cb.checked = true);
      });
    }

    if (btnUncheckAll) {
      btnUncheckAll.addEventListener('click', function () {
        checkboxes.forEach(cb => cb.checked = false);
      });
    }
  });
</script>
JS;

require_once ROOT_PATH . '/includes/footer.php';
?>
