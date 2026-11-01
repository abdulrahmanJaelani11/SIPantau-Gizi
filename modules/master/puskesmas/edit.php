<?php
/**
 * Master Data — Edit Puskesmas
 * ==============================
 * Halaman form pengubahan data Puskesmas.
 */

$current_slug = 'puskesmas';
require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan hanya role Admin yang bisa mengedit Puskesmas
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
$is_admin = ($user_role_id === Otorisasi::ADMIN_ROLE_ID || strcasecmp($_SESSION['user_role_nama'] ?? '', 'Admin') === 0);
if (!$is_admin) {
    set_flash('danger', 'Akses ditolak. Hanya Administrator yang dapat mengubah data Puskesmas.');
    redirect(base_url('modules/master/puskesmas/index.php'));
}

// Validasi ID
$puskesmas_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$puskesmas_id) {
    set_flash('danger', 'ID Puskesmas tidak valid.');
    redirect(base_url('modules/master/puskesmas/index.php'));
}

// Ambil data puskesmas saat ini
$puskesmas = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM puskesmas WHERE id_puskesmas = ? LIMIT 1");
    $stmt->execute([$puskesmas_id]);
    $puskesmas = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat data Puskesmas: ' . $e->getMessage());
    redirect(base_url('modules/master/puskesmas/index.php'));
}

if (!$puskesmas) {
    set_flash('danger', 'Data Puskesmas tidak ditemukan.');
    redirect(base_url('modules/master/puskesmas/index.php'));
}

// Ambil daftar wilayah
$wilayah_list = [];
try {
    $wilayah_list = $pdo->query("SELECT id_wilayah, nama_wilayah, kecamatan, kabupaten FROM wilayah ORDER BY nama_wilayah ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $wilayah_list = [];
}

$errors = [];

// ============================================================
// Proses Simpan Perubahan (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba lagi.';
    }

    $nama_puskesmas = trim($_POST['nama_puskesmas'] ?? '');
    $id_wilayah     = filter_input(INPUT_POST, 'id_wilayah', FILTER_VALIDATE_INT);
    $no_telepon     = trim($_POST['no_telepon'] ?? '');
    $alamat         = trim($_POST['alamat'] ?? '');

    // Validasi Nama Puskesmas
    if ($nama_puskesmas === '') {
        $errors['nama_puskesmas'] = 'Nama Puskesmas wajib diisi.';
    } elseif (mb_strlen($nama_puskesmas) < 3) {
        $errors['nama_puskesmas'] = 'Nama Puskesmas minimal 3 karakter.';
    } elseif (mb_strlen($nama_puskesmas) > 100) {
        $errors['nama_puskesmas'] = 'Nama Puskesmas maksimal 100 karakter.';
    } else {
        // Cek keunikan nama puskesmas terhadap data lain
        try {
            $stmt = $pdo->prepare("SELECT id_puskesmas FROM puskesmas WHERE LOWER(nama_puskesmas) = LOWER(?) AND id_puskesmas != ? LIMIT 1");
            $stmt->execute([$nama_puskesmas, $puskesmas_id]);
            if ($stmt->fetch()) {
                $errors['nama_puskesmas'] = 'Nama Puskesmas "' . e($nama_puskesmas) . '" sudah digunakan oleh Puskesmas lain.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Gagal memeriksa ketersediaan nama: ' . $e->getMessage();
        }
    }

    // Validasi Wilayah
    if (!$id_wilayah) {
        $errors['id_wilayah'] = 'Wilayah kerja Puskesmas wajib dipilih.';
    } else {
        $valid_wilayah_ids = array_column($wilayah_list, 'id_wilayah');
        if (!in_array($id_wilayah, $valid_wilayah_ids)) {
            $errors['id_wilayah'] = 'Wilayah yang dipilih tidak valid.';
        }
    }

    // Validasi Telepon
    if ($no_telepon === '') {
        $errors['no_telepon'] = 'Nomor telepon kontak wajib diisi.';
    } elseif (mb_strlen($no_telepon) > 20) {
        $errors['no_telepon'] = 'Nomor telepon maksimal 20 karakter.';
    }

    // Validasi Alamat
    if ($alamat === '') {
        $errors['alamat'] = 'Alamat lengkap fasilitas wajib diisi.';
    }

    // Eksekusi Update
    if (empty($errors) && $pdo !== null) {
        try {
            $stmt = $pdo->prepare("
                UPDATE puskesmas 
                SET id_wilayah = ?, nama_puskesmas = ?, alamat = ?, no_telepon = ?
                WHERE id_puskesmas = ?
            ");
            $stmt->execute([$id_wilayah, $nama_puskesmas, $alamat, $no_telepon, $puskesmas_id]);

            clear_old_input();
            set_flash('success', "Perubahan data Puskesmas <strong>" . e($nama_puskesmas) . "</strong> berhasil disimpan!");
            redirect(base_url('modules/master/puskesmas/index.php'));

        } catch (PDOException $e) {
            $errors[] = 'Gagal menyimpan perubahan Puskesmas: ' . $e->getMessage();
        }
    }
}

$val_nama_puskesmas = old('nama_puskesmas', $puskesmas['nama_puskesmas']);
$val_id_wilayah     = old('id_wilayah', (string)$puskesmas['id_wilayah']);
$val_no_telepon     = old('no_telepon', $puskesmas['no_telepon']);
$val_alamat         = old('alamat', $puskesmas['alamat']);

// Variabel layout
$page_title  = 'Edit Puskesmas: ' . $puskesmas['nama_puskesmas'];
$breadcrumbs = [
    'Master Data'       => '#',
    'Data Puskesmas'    => base_url('modules/master/puskesmas/index.php'),
    'Edit Puskesmas'    => '',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-pencil-square" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Master Data &rsaquo; Data Puskesmas</p>
      <h1 class="h3 mb-1">Edit Puskesmas: <?= e($puskesmas['nama_puskesmas']) ?></h1>
      <p class="text-muted mb-0">Perbarui profil unit Puskesmas pembina dan informasi kontak.</p>
    </div>
  </div>
  <div class="heading-actions d-flex gap-2">
    <a class="btn btn-outline-info btn-sm" href="<?= base_url('modules/master/puskesmas/detail.php?id=' . (int)$puskesmas['id_puskesmas']) ?>">
      <i class="bi bi-eye me-1" aria-hidden="true"></i> Lihat Detail
    </a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('modules/master/puskesmas/index.php') ?>">
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

  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">
      <div class="panel">
        <div class="panel-header d-flex justify-content-between align-items-center">
          <h2 class="h5 mb-0 section-title">
            <i class="bi bi-info-circle text-primary me-2"></i>Perbarui Data Puskesmas
          </h2>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
            ID #<?= (int)$puskesmas['id_puskesmas'] ?>
          </span>
        </div>

        <div class="p-3 p-lg-4">
          <div class="mb-3">
            <label for="nama_puskesmas" class="form-label fw-semibold">
              Nama Puskesmas <span class="text-danger">*</span>
            </label>
            <input type="text"
                   name="nama_puskesmas"
                   id="nama_puskesmas"
                   class="form-control <?= isset($errors['nama_puskesmas']) ? 'is-invalid' : '' ?>"
                   placeholder="Contoh: Puskesmas Cilodong"
                   value="<?= e($val_nama_puskesmas) ?>"
                   required
                   maxlength="100"
                   autofocus>
            <div class="form-text">Nama resmi fasilitas kesehatan.</div>
            <?php if (isset($errors['nama_puskesmas'])): ?>
              <div class="invalid-feedback"><?= e($errors['nama_puskesmas']) ?></div>
            <?php endif; ?>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-12 col-md-6">
              <label for="id_wilayah" class="form-label fw-semibold">
                Wilayah Kerja / Kecamatan <span class="text-danger">*</span>
              </label>
              <select name="id_wilayah"
                      id="id_wilayah"
                      class="form-select <?= isset($errors['id_wilayah']) ? 'is-invalid' : '' ?>"
                      required>
                <option value="">-- Pilih Wilayah Binaan --</option>
                <?php foreach ($wilayah_list as $w): ?>
                  <option value="<?= (int)$w['id_wilayah'] ?>" <?= (string)$val_id_wilayah === (string)$w['id_wilayah'] ? 'selected' : '' ?>>
                    <?= e($w['nama_wilayah']) ?> (Kec. <?= e($w['kecamatan']) ?>, <?= e($w['kabupaten']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['id_wilayah'])): ?>
                <div class="invalid-feedback"><?= e($errors['id_wilayah']) ?></div>
              <?php endif; ?>
            </div>

            <div class="col-12 col-md-6">
              <label for="no_telepon" class="form-label fw-semibold">
                No. Telepon Kontak <span class="text-danger">*</span>
              </label>
              <input type="text"
                     name="no_telepon"
                     id="no_telepon"
                     class="form-control <?= isset($errors['no_telepon']) ? 'is-invalid' : '' ?>"
                     placeholder="Contoh: 021-87654321 / 081234567890"
                     value="<?= e($val_no_telepon) ?>"
                     required
                     maxlength="20">
              <div class="form-text">Nomor resmi untuk koordinasi pelayanan gizi.</div>
              <?php if (isset($errors['no_telepon'])): ?>
                <div class="invalid-feedback"><?= e($errors['no_telepon']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="mb-3">
            <label for="alamat" class="form-label fw-semibold">
              Alamat Lengkap <span class="text-danger">*</span>
            </label>
            <textarea name="alamat"
                      id="alamat"
                      rows="3"
                      class="form-control <?= isset($errors['alamat']) ? 'is-invalid' : '' ?>"
                      required><?= e($val_alamat) ?></textarea>
            <?php if (isset($errors['alamat'])): ?>
              <div class="invalid-feedback"><?= e($errors['alamat']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="panel-footer bg-light border-top p-3 d-flex justify-content-end gap-2">
          <a href="<?= base_url('modules/master/puskesmas/index.php') ?>" class="btn btn-secondary btn-sm">
            Batal
          </a>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-save me-1"></i> Simpan Perubahan
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<?php
require_once ROOT_PATH . '/includes/footer.php';
?>
