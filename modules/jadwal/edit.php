<?php
/**
 * Modul Jadwal & Kunjungan — Edit Jadwal Kegiatan Posyandu
 * ==========================================================
 * Formulir koreksi data agenda sesi Posyandu, lokasi, dan tanggal pelaksanaan.
 */

$current_slug = 'jadwal';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan memiliki hak akses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'jadwal')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengedit jadwal kegiatan.');
    redirect(base_url('modules/jadwal/index.php'));
}

$id_jadwal = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_jadwal) {
    set_flash('danger', 'ID Jadwal tidak valid.');
    redirect(base_url('modules/jadwal/index.php'));
}

// Ambil data jadwal
$jadwal = null;
try {
    if ($pdo !== null) {
        $stmt = $pdo->prepare("
            SELECT j.*, pos.nama_posyandu
            FROM jadwal_posyandu j
            JOIN posyandu pos ON j.id_posyandu = pos.id_posyandu
            WHERE j.id_jadwal = ?
            LIMIT 1
        ");
        $stmt->execute([$id_jadwal]);
        $jadwal = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat data jadwal: ' . $e->getMessage());
    redirect(base_url('modules/jadwal/index.php'));
}

if (!$jadwal) {
    set_flash('warning', 'Data jadwal posyandu tidak ditemukan.');
    redirect(base_url('modules/jadwal/index.php'));
}

// Ambil daftar posyandu
$posyandu_list = [];
try {
    if ($pdo !== null) {
        $stmtPos = $pdo->query("SELECT id_posyandu, nama_posyandu, alamat FROM posyandu ORDER BY nama_posyandu ASC");
        $posyandu_list = $stmtPos->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $posyandu_list = [];
}

$errors = [];

// ============================================================
// Proses Simpan Perubahan Jadwal (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Cek CSRF
    if (!verify_csrf_token()) {
        $errors[] = 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba lagi.';
    }

    $id_posyandu  = filter_input(INPUT_POST, 'id_posyandu', FILTER_VALIDATE_INT);
    $tanggal      = trim($_POST['tanggal'] ?? '');
    $waktu        = trim($_POST['waktu'] ?? '');
    $lokasi       = trim($_POST['lokasi'] ?? '');
    $keterangan   = trim($_POST['keterangan'] ?? '');
    $sync_balita  = isset($_POST['sync_balita']);

    // 2. Validasi
    if (!$id_posyandu) {
        $errors['id_posyandu'] = 'Posyandu wajib dipilih.';
    }
    if ($tanggal === '') {
        $errors['tanggal'] = 'Tanggal kegiatan wajib diisi.';
    }
    if ($waktu === '') {
        $errors['waktu'] = 'Waktu pelaksanaan wajib diisi.';
    }
    if ($lokasi === '') {
        $errors['lokasi'] = 'Lokasi kegiatan wajib diisi.';
    }

    // 3. Eksekusi Update
    if (empty($errors) && $pdo !== null) {
        try {
            $pdo->beginTransaction();

            $stmtUpdate = $pdo->prepare("
                UPDATE jadwal_posyandu
                SET id_posyandu = ?, tanggal = ?, waktu = ?, lokasi = ?, keterangan = ?
                WHERE id_jadwal = ?
            ");
            $stmtUpdate->execute([
                $id_posyandu,
                $tanggal,
                $waktu,
                $lokasi,
                $keterangan ?: null,
                $id_jadwal
            ]);

            // Jika sinkronisasi balita dipilih, tambahkan balita yang belum masuk daftar presensi
            $added_count = 0;
            if ($sync_balita) {
                $stmtBalita = $pdo->prepare("
                    SELECT a.id_anak 
                    FROM anak a 
                    WHERE a.id_posyandu = ? 
                      AND a.id_anak NOT IN (SELECT k.id_anak FROM kehadiran k WHERE k.id_jadwal = ?)
                ");
                $stmtBalita->execute([$id_posyandu, $id_jadwal]);
                $missing_children = $stmtBalita->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($missing_children)) {
                    $stmtAddHadir = $pdo->prepare("
                        INSERT INTO kehadiran (id_jadwal, id_anak, status_kehadiran, waktu_kehadiran, keterangan)
                        VALUES (?, ?, 'Belum Hadir', NULL, NULL)
                    ");
                    foreach ($missing_children as $id_anak) {
                        $stmtAddHadir->execute([$id_jadwal, $id_anak]);
                        $added_count++;
                    }
                }
            }

            $pdo->commit();

            $msg = "Jadwal kegiatan berhasil diperbarui!";
            if ($added_count > 0) {
                $msg .= " Menambahkan {$added_count} balita baru ke daftar sasaran presensi.";
            }
            set_flash('success', $msg);
            redirect(base_url('modules/jadwal/detail.php?id=' . $id_jadwal));

        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Gagal menyimpan perubahan: ' . $e->getMessage();
        }
    }
}

$val_posyandu   = $_POST['id_posyandu'] ?? $jadwal['id_posyandu'];
$val_tanggal    = $_POST['tanggal'] ?? $jadwal['tanggal'];
$val_waktu      = $_POST['waktu'] ?? $jadwal['waktu'];
$val_lokasi     = $_POST['lokasi'] ?? $jadwal['lokasi'];
$val_keterangan = $_POST['keterangan'] ?? $jadwal['keterangan'];

$page_title  = 'Edit Jadwal: ' . $jadwal['nama_posyandu'];
$breadcrumbs = [
    'Data & Laporan'     => '#',
    'Jadwal & Kunjungan' => base_url('modules/jadwal/index.php'),
    'Edit Jadwal'        => '',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-pencil-square" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Koreksi Agenda</p>
      <h1 class="h3 mb-1">Edit Jadwal Kegiatan Posyandu</h1>
      <p class="text-muted mb-0">Ubah rincian waktu, tanggal pelaksanaan, lokasi, atau agenda sesi.</p>
    </div>
  </div>
  <div class="heading-actions d-flex gap-2">
    <a href="<?= base_url('modules/jadwal/detail.php?id=' . (int)$jadwal['id_jadwal']) ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-clipboard-check me-1" aria-hidden="true"></i> Lihat Presensi
    </a>
    <a href="<?= base_url('modules/jadwal/index.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali ke Daftar
    </a>
  </div>
</div>

<!-- Pesan Error / Validasi Form -->
<?php if (!empty($errors)): ?>
  <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
    <div class="d-flex align-items-center mb-1">
      <i class="bi bi-exclamation-octagon-fill fs-5 me-2" aria-hidden="true"></i>
      <h6 class="mb-0 fw-bold">Terdapat kesalahan pengisian data:</h6>
    </div>
    <ul class="mb-0 ps-4 small">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
  </div>
<?php endif; ?>

<form method="POST" action="<?= base_url('modules/jadwal/edit.php?id=' . (int)$jadwal['id_jadwal']) ?>">
  <?= csrf_field() ?>

  <div class="row g-4">
    <!-- Kolom Kiri: Form Input -->
    <div class="col-lg-7">
      <div class="panel">
        <div class="panel-header border-bottom pb-3 mb-3 d-flex align-items-center justify-content-between">
          <div>
            <h5 class="panel-title mb-0">Formulir Perubahan Jadwal</h5>
            <small class="text-muted">Perbarui data pelaksanaan kegiatan Posyandu</small>
          </div>
          <span class="badge bg-light text-dark border">ID: #<?= (int)$jadwal['id_jadwal'] ?></span>
        </div>

        <!-- Pilihan Posyandu -->
        <div class="mb-3">
          <label for="id_posyandu" class="form-label fw-semibold">
            Posyandu Penyelenggara <span class="text-danger">*</span>
          </label>
          <select class="form-select <?= isset($errors['id_posyandu']) ? 'is-invalid' : '' ?>"
                  id="id_posyandu"
                  name="id_posyandu"
                  required>
            <option value="">-- Pilih Posyandu --</option>
            <?php foreach ($posyandu_list as $pos): ?>
              <option value="<?= (int)$pos['id_posyandu'] ?>"
                      <?= (int)$val_posyandu === (int)$pos['id_posyandu'] ? 'selected' : '' ?>>
                <?= e($pos['nama_posyandu']) ?> (<?= e($pos['alamat'] ?: 'Wilayah Kerja') ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['id_posyandu'])): ?>
            <div class="invalid-feedback"><?= e($errors['id_posyandu']) ?></div>
          <?php endif; ?>
        </div>

        <div class="row g-3 mb-3">
          <!-- Tanggal Pelaksanaan -->
          <div class="col-md-6">
            <label for="tanggal" class="form-label fw-semibold">
              Tanggal Pelaksanaan <span class="text-danger">*</span>
            </label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
              <input type="date"
                     class="form-control <?= isset($errors['tanggal']) ? 'is-invalid' : '' ?>"
                     id="tanggal"
                     name="tanggal"
                     value="<?= e($val_tanggal) ?>"
                     required>
              <?php if (isset($errors['tanggal'])): ?>
                <div class="invalid-feedback"><?= e($errors['tanggal']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Waktu Pelaksanaan -->
          <div class="col-md-6">
            <label for="waktu" class="form-label fw-semibold">
              Waktu / Jam Mulai <span class="text-danger">*</span>
            </label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-clock"></i></span>
              <input type="time"
                     class="form-control <?= isset($errors['waktu']) ? 'is-invalid' : '' ?>"
                     id="waktu"
                     name="waktu"
                     value="<?= e(substr($val_waktu, 0, 5)) ?>"
                     required>
              <?php if (isset($errors['waktu'])): ?>
                <div class="invalid-feedback"><?= e($errors['waktu']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Lokasi Pelaksanaan -->
        <div class="mb-3">
          <label for="lokasi" class="form-label fw-semibold">
            Lokasi Kegiatan <span class="text-danger">*</span>
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
            <input type="text"
                   class="form-control <?= isset($errors['lokasi']) ? 'is-invalid' : '' ?>"
                   id="lokasi"
                   name="lokasi"
                   value="<?= e($val_lokasi) ?>"
                   placeholder="Lokasi pelaksanaan sesi Posyandu"
                   required>
            <?php if (isset($errors['lokasi'])): ?>
              <div class="invalid-feedback"><?= e($errors['lokasi']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Keterangan / Agenda -->
        <div class="mb-4">
          <label for="keterangan" class="form-label fw-semibold">
            Agenda / Keterangan Khusus <span class="text-muted fw-normal">(Opsional)</span>
          </label>
          <textarea class="form-control"
                    id="keterangan"
                    name="keterangan"
                    rows="3"><?= e($val_keterangan) ?></textarea>
        </div>

        <!-- Opsi Sinkronisasi Balita Baru -->
        <div class="p-3 bg-light rounded-3 border mb-4">
          <div class="form-check form-switch mb-0">
            <input class="form-check-input"
                   type="checkbox"
                   role="switch"
                   id="sync_balita"
                   name="sync_balita"
                   value="1">
            <label class="form-check-label fw-semibold text-dark" for="sync_balita">
              Sinkronisasikan Balita Baru ke Daftar Presensi
            </label>
            <div class="text-muted small mt-1">
              Centang opsi ini jika terdapat balita baru yang belum terdaftar di presensi jadwal ini.
            </div>
          </div>
        </div>

        <div class="d-flex gap-2 justify-content-end pt-3 border-top">
          <a href="<?= base_url('modules/jadwal/detail.php?id=' . (int)$jadwal['id_jadwal']) ?>" class="btn btn-secondary">
            Batal
          </a>
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Simpan Perubahan
          </button>
        </div>
      </div>
    </div>

    <!-- Kolom Kanan: Info Ringkas -->
    <div class="col-lg-5">
      <div class="panel">
        <div class="panel-header border-bottom pb-3 mb-3">
          <h5 class="panel-title mb-0">Status Jadwal Saat Ini</h5>
          <small class="text-muted">Informasi ringkasan kegiatan</small>
        </div>

        <?php
        $today = date('Y-m-d');
        $is_today = ($jadwal['tanggal'] === $today);
        $is_past  = ($jadwal['tanggal'] < $today);
        ?>

        <div class="p-3 rounded-3 mb-3 border <?= $is_today ? 'bg-success-subtle text-success border-success-subtle' : ($is_past ? 'bg-secondary-subtle text-secondary border-secondary-subtle' : 'bg-primary-subtle text-primary border-primary-subtle') ?>">
          <div class="d-flex align-items-center gap-2">
            <i class="bi <?= $is_today ? 'bi-bell-fill' : ($is_past ? 'bi-check-all' : 'bi-calendar-event') ?> fs-4"></i>
            <div>
              <h6 class="fw-bold mb-0">
                <?= $is_today ? 'Kegiatan Berlangsung Hari Ini!' : ($is_past ? 'Kegiatan Telah Selesai' : 'Kegiatan Mendatang') ?>
              </h6>
              <small>Tanggal: <?= date('d F Y', strtotime($jadwal['tanggal'])) ?></small>
            </div>
          </div>
        </div>

        <div class="d-grid gap-2">
          <a href="<?= base_url('modules/jadwal/detail.php?id=' . (int)$jadwal['id_jadwal']) ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-clipboard-check me-1"></i> Buka Lembar Presensi & Sweeping Balita
          </a>
        </div>
      </div>
    </div>
  </div>
</form>

<?php
require_once ROOT_PATH . '/includes/footer.php';
?>
