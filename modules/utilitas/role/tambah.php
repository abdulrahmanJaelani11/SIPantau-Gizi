<?php
/**
 * Modul Utilitas — Tambah Role Baru
 * ===================================
 * Halaman form untuk menambahkan peran baru beserta konfigurasi hak akses menu awalnya.
 * Disesuaikan dengan arsitektur database db_sipantau_gizi.
 */

$current_slug = 'role';
require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan hanya role yang berhak yang bisa mengakses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'role')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk menambah Role.');
    redirect(base_url('modules/dashboard/index.php'));
}

// Ambil semua menu dikelompokkan berdasarkan kategori
$menus_grouped = Otorisasi::getAllMenusGrouped();

$errors = [];

// ============================================================
// Proses Simpan Data Role Baru (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Cek CSRF
    if (!verify_csrf_token()) {
        $errors[] = 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba lagi.';
    }

    $nama_role   = trim($_POST['nama_role'] ?? '');
    $deskripsi   = trim($_POST['deskripsi'] ?? '');
    $menu_ids    = isset($_POST['menus']) && is_array($_POST['menus']) ? array_map('intval', $_POST['menus']) : [];

    // Simpan input lama agar user tidak perlu ketik ulang
    set_old_input([
        'nama_role' => $nama_role,
        'deskripsi' => $deskripsi,
        'menus'     => $menu_ids,
    ]);

    // 2. Validasi Nama Role
    if ($nama_role === '') {
        $errors['nama_role'] = 'Nama role wajib diisi.';
    } elseif (mb_strlen($nama_role) < 3) {
        $errors['nama_role'] = 'Nama role minimal 3 karakter.';
    } elseif (mb_strlen($nama_role) > 50) {
        $errors['nama_role'] = 'Nama role maksimal 50 karakter.';
    } else {
        // Cek keunikan nama role
        try {
            $stmt = $pdo->prepare("SELECT id_role FROM role WHERE LOWER(nama_role) = LOWER(?) LIMIT 1");
            $stmt->execute([$nama_role]);
            if ($stmt->fetch()) {
                $errors['nama_role'] = 'Nama role "' . e($nama_role) . '" sudah ada di sistem. Gunakan nama lain.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Gagal memeriksa ketersediaan nama role: ' . $e->getMessage();
        }
    }

    // 3. Eksekusi Simpan jika Valid
    if (empty($errors) && $pdo !== null) {
        try {
            $pdo->beginTransaction();

            // Insert ke tabel role
            $stmt = $pdo->prepare("INSERT INTO role (nama_role, deskripsi) VALUES (?, ?)");
            $stmt->execute([$nama_role, $deskripsi ?: null]);
            $new_role_id = (int)$pdo->lastInsertId();

            $pdo->commit();

            // Sinkronkan otorisasi menu
            Otorisasi::syncRoleMenus($new_role_id, $menu_ids);

            clear_old_input();
            set_flash('success', "Role baru <strong>" . e($nama_role) . "</strong> berhasil ditambahkan beserta hak aksesnya!");
            redirect(base_url('modules/utilitas/role/index.php'));

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Gagal menyimpan role baru: ' . $e->getMessage();
        }
    }
}

// Variabel layout
$page_title  = 'Tambah Role Baru';
$breadcrumbs = [
    'Utilitas'          => '#',
    'Role & Otorisasi'  => base_url('modules/utilitas/role/index.php'),
    'Tambah Role'       => '',
];

$old_menus = old('menus', []);
if (!is_array($old_menus)) {
    $old_menus = [];
}

// Include header layout
require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-shield-plus" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Utilitas Sistem &rsaquo; Role & Otorisasi</p>
      <h1 class="h3 mb-1">Tambah Role Baru</h1>
      <p class="text-muted mb-0">Daftarkan tingkatan hak akses baru dan tentukan wewenang menu navigasinya.</p>
    </div>
  </div>
  <div class="heading-actions">
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
      <strong>Terdapat beberapa kesalahan pada formulir:</strong>
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
        <div class="panel-header">
          <h2 class="h5 mb-0 section-title">
            <i class="bi bi-info-circle text-primary me-2"></i>Informasi Peran
          </h2>
        </div>
        <div class="p-3 p-lg-4">
          <div class="mb-3">
            <label for="nama_role" class="form-label fw-semibold">
              Nama Role <span class="text-danger">*</span>
            </label>
            <input type="text"
                   name="nama_role"
                   id="nama_role"
                   class="form-control <?= isset($errors['nama_role']) ? 'is-invalid' : '' ?>"
                   placeholder="Contoh: Ahli Gizi / Supervisor"
                   value="<?= e(old('nama_role')) ?>"
                   required
                   maxlength="50"
                   autofocus>
            <div class="form-text">
              Nama unik untuk peran ini di seluruh sistem (3-50 karakter).
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
                      placeholder="Jelaskan cakupan tanggung jawab dan kewenangan pengguna dengan role ini..."><?= e(old('deskripsi')) ?></textarea>
            <div class="form-text">
              Opsional. Membantu administrator lain memahami fungsi peran ini.
            </div>
          </div>

          <div class="alert alert-info py-2 small mb-0">
            <i class="bi bi-shield-check me-1"></i>
            <strong>Catatan Akses:</strong> Menu <em>Dashboard</em> akan otomatis diizinkan untuk semua peran agar pengguna memiliki halaman beranda setelah masuk.
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
            <p class="text-muted small mb-0">Pilih menu mana saja yang dapat dilihat & diakses oleh role ini.</p>
          </div>
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-primary" id="btnCheckAll">
              <i class="bi bi-check-all me-1"></i>Pilih Semua
            </button>
            <button type="button" class="btn btn-outline-secondary" id="btnUncheckAll">
              <i class="bi bi-dash me-1"></i>Batal Semua
            </button>
          </div>
        </div>

        <div class="p-3 p-lg-4">
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
                  $is_checked = $is_dashboard || in_array((int)$menu['id_menu'], $old_menus, true);
                ?>
                  <div class="col-12 col-sm-6">
                    <div class="card h-100 border p-2 rounded-3 hover-shadow-sm transition-all <?= $is_dashboard ? 'bg-light-subtle' : '' ?>">
                      <div class="form-check d-flex align-items-start gap-2 mb-0">
                        <input class="form-check-input mt-1 menu-checkbox"
                               type="checkbox"
                               name="menus[]"
                               value="<?= (int)$menu['id_menu'] ?>"
                               id="menu_<?= (int)$menu['id_menu'] ?>"
                               <?= $is_checked ? 'checked' : '' ?>
                               <?= $is_dashboard ? 'checked disabled' : '' ?>>
                        
                        <?php if ($is_dashboard): ?>
                          <!-- Kirim hidden input karena disabled checkbox tidak ikut di submit -->
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
            <i class="bi bi-save me-1"></i> Simpan Role Baru
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
