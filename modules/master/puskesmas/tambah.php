<?php
/**
 * Master Data — Tambah Puskesmas Baru
 * =====================================
 * Halaman form registrasi unit Puskesmas baru terelasi dengan tabel wilayah.
 */

$current_slug = 'puskesmas';
require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan hanya role Admin yang bisa menambah Puskesmas
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
$is_admin = ($user_role_id === Otorisasi::ADMIN_ROLE_ID || strcasecmp($_SESSION['user_role_nama'] ?? '', 'Admin') === 0);
if (!$is_admin) {
    set_flash('danger', 'Akses ditolak. Hanya Administrator yang dapat mendaftarkan unit Puskesmas baru.');
    redirect(base_url('modules/master/puskesmas/index.php'));
}

// Ambil daftar wilayah untuk dropdown
$wilayah_list = [];
try {
    if ($pdo !== null) {
        $wilayah_list = $pdo->query("SELECT id_wilayah, nama_wilayah, kecamatan, kabupaten FROM wilayah ORDER BY nama_wilayah ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $wilayah_list = [];
}

$errors = [];

// ============================================================
// Proses Simpan Data (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Cek CSRF
    if (!verify_csrf_token()) {
        $errors[] = 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba lagi.';
    }

    $nama_puskesmas = trim($_POST['nama_puskesmas'] ?? '');
    $id_wilayah     = filter_input(INPUT_POST, 'id_wilayah', FILTER_VALIDATE_INT);
    $no_telepon     = trim($_POST['no_telepon'] ?? '');
    $alamat         = trim($_POST['alamat'] ?? '');

    // Simpan input lama agar form tidak ter-reset
    set_old_input([
        'nama_puskesmas' => $nama_puskesmas,
        'id_wilayah'     => $id_wilayah,
        'no_telepon'     => $no_telepon,
        'alamat'         => $alamat,
    ]);

    // 2. Validasi Nama Puskesmas
    if ($nama_puskesmas === '') {
        $errors['nama_puskesmas'] = 'Nama Puskesmas wajib diisi.';
    } elseif (mb_strlen($nama_puskesmas) < 3) {
        $errors['nama_puskesmas'] = 'Nama Puskesmas minimal 3 karakter.';
    } elseif (mb_strlen($nama_puskesmas) > 100) {
        $errors['nama_puskesmas'] = 'Nama Puskesmas maksimal 100 karakter.';
    } else {
        // Cek keunikan nama puskesmas
        try {
            $stmt = $pdo->prepare("SELECT id_puskesmas FROM puskesmas WHERE LOWER(nama_puskesmas) = LOWER(?) LIMIT 1");
            $stmt->execute([$nama_puskesmas]);
            if ($stmt->fetch()) {
                $errors['nama_puskesmas'] = 'Nama Puskesmas "' . e($nama_puskesmas) . '" sudah terdaftar sebelumnya.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Gagal memeriksa ketersediaan nama: ' . $e->getMessage();
        }
    }

    // 3. Validasi Wilayah
    if (!$id_wilayah) {
        $errors['id_wilayah'] = 'Wilayah kerja Puskesmas wajib dipilih.';
    } else {
        $valid_wilayah_ids = array_column($wilayah_list, 'id_wilayah');
        if (!in_array($id_wilayah, $valid_wilayah_ids)) {
            $errors['id_wilayah'] = 'Wilayah yang dipilih tidak valid.';
        }
    }

    // 4. Validasi Telepon
    if ($no_telepon === '') {
        $errors['no_telepon'] = 'Nomor telepon kontak wajib diisi.';
    } elseif (mb_strlen($no_telepon) > 20) {
        $errors['no_telepon'] = 'Nomor telepon maksimal 20 karakter.';
    }

    // 5. Validasi Alamat
    if ($alamat === '') {
        $errors['alamat'] = 'Alamat lengkap fasilitas wajib diisi.';
    }

    // 6. Eksekusi Simpan
    if (empty($errors) && $pdo !== null) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO puskesmas (id_wilayah, nama_puskesmas, alamat, no_telepon)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$id_wilayah, $nama_puskesmas, $alamat, $no_telepon]);

            clear_old_input();
            set_flash('success', "Puskesmas <strong>" . e($nama_puskesmas) . "</strong> berhasil ditambahkan ke sistem!");
            redirect(base_url('modules/master/puskesmas/index.php'));

        } catch (PDOException $e) {
            $errors[] = 'Gagal menyimpan data Puskesmas: ' . $e->getMessage();
        }
    }
}

// Variabel layout
$page_title  = 'Tambah Puskesmas Baru';
$breadcrumbs = [
    'Master Data'       => '#',
    'Data Puskesmas'    => base_url('modules/master/puskesmas/index.php'),
    'Tambah Puskesmas'  => '',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-building-add" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Master Data &rsaquo; Data Puskesmas</p>
      <h1 class="h3 mb-1">Tambah Puskesmas Baru</h1>
      <p class="text-muted mb-0">Daftarkan fasilitas kesehatan pembina wilayah dan jejaring Posyandu.</p>
    </div>
  </div>
  <div class="heading-actions">
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
        <div class="panel-header">
          <h2 class="h5 mb-0 section-title">
            <i class="bi bi-info-circle text-primary me-2"></i>Formulir Data Puskesmas
          </h2>
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
                   value="<?= e(old('nama_puskesmas')) ?>"
                   required
                   maxlength="100"
                   autofocus>
            <div class="form-text">Nama resmi unit fasilitas pelayanan kesehatan masyarakat.</div>
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
                  <option value="<?= (int)$w['id_wilayah'] ?>" <?= (string)old('id_wilayah') === (string)$w['id_wilayah'] ? 'selected' : '' ?>>
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
                     value="<?= e(old('no_telepon')) ?>"
                     required
                     maxlength="20">
              <div class="form-text">Nomor resmi untuk koordinasi rujukan & konsultasi gizi.</div>
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
                      placeholder="Contoh: Jl. Cilodong Raya No. 12, RT 01/RW 02"
                      required><?= e(old('alamat')) ?></textarea>
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
            <i class="bi bi-save me-1"></i> Simpan Puskesmas
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<?php
require_once ROOT_PATH . '/includes/footer.php';
?>
