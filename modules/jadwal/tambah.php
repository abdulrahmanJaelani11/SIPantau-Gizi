<?php
/**
 * Modul Jadwal & Kunjungan — Tambah Jadwal Kegiatan Posyandu Baru
 * ================================================================
 * Formulir penambahan agenda sesi penimbangan balita dan pos pelayanan terpadu.
 */

$current_slug = 'jadwal';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan memiliki hak akses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'jadwal')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk menambah jadwal kegiatan.');
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
// Proses Simpan Jadwal Baru (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Cek CSRF
    if (!verify_csrf_token()) {
        $errors[] = 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba lagi.';
    }

    $id_posyandu   = filter_input(INPUT_POST, 'id_posyandu', FILTER_VALIDATE_INT);
    $tanggal       = trim($_POST['tanggal'] ?? '');
    $waktu         = trim($_POST['waktu'] ?? '');
    $lokasi        = trim($_POST['lokasi'] ?? '');
    $keterangan    = trim($_POST['keterangan'] ?? '');
    $auto_generate = isset($_POST['auto_generate']);

    set_old_input([
        'id_posyandu'   => $id_posyandu,
        'tanggal'       => $tanggal,
        'waktu'         => $waktu,
        'lokasi'        => $lokasi,
        'keterangan'    => $keterangan,
        'auto_generate' => $auto_generate,
    ]);

    // 2. Validasi Posyandu
    $posyandu_data = null;
    if (!$id_posyandu) {
        $errors['id_posyandu'] = 'Silakan pilih Posyandu penyelenggara.';
    } else {
        try {
            $stmtCek = $pdo->prepare("SELECT id_posyandu, nama_posyandu FROM posyandu WHERE id_posyandu = ? LIMIT 1");
            $stmtCek->execute([$id_posyandu]);
            $posyandu_data = $stmtCek->fetch(PDO::FETCH_ASSOC);
            if (!$posyandu_data) {
                $errors['id_posyandu'] = 'Posyandu terpilih tidak ditemukan di database.';
            }
        } catch (PDOException $e) {
            $errors['id_posyandu'] = 'Gagal memverifikasi data posyandu: ' . $e->getMessage();
        }
    }

    // 3. Validasi Tanggal
    if ($tanggal === '') {
        $errors['tanggal'] = 'Tanggal kegiatan wajib diisi.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $errors['tanggal'] = 'Format tanggal tidak valid.';
    }

    // 4. Validasi Waktu
    if ($waktu === '') {
        $errors['waktu'] = 'Waktu / jam kegiatan wajib diisi.';
    }

    // 5. Validasi Lokasi
    if ($lokasi === '') {
        $errors['lokasi'] = 'Lokasi kegiatan wajib diisi.';
    }

    // 6. Eksekusi Simpan ke Tabel jadwal_posyandu & kehadiran
    if (empty($errors) && $posyandu_data && $pdo !== null) {
        try {
            $pdo->beginTransaction();

            // Insert ke tabel jadwal_posyandu
            $stmtInsertJadwal = $pdo->prepare("
                INSERT INTO jadwal_posyandu (id_posyandu, tanggal, waktu, lokasi, keterangan)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtInsertJadwal->execute([
                $id_posyandu,
                $tanggal,
                $waktu,
                $lokasi,
                $keterangan ?: null
            ]);
            $new_jadwal_id = (int)$pdo->lastInsertId();

            // Jika opsi auto_generate aktif, buatkan daftar presensi untuk semua balita di Posyandu ini
            $total_sasaran = 0;
            if ($auto_generate) {
                $stmtBalita = $pdo->prepare("SELECT id_anak FROM anak WHERE id_posyandu = ? ORDER BY nama ASC");
                $stmtBalita->execute([$id_posyandu]);
                $balita_list = $stmtBalita->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($balita_list)) {
                    $stmtKehadiran = $pdo->prepare("
                        INSERT INTO kehadiran (id_jadwal, id_anak, status_kehadiran, waktu_kehadiran, keterangan)
                        VALUES (?, ?, 'Belum Hadir', NULL, NULL)
                    ");
                    foreach ($balita_list as $id_anak) {
                        $stmtKehadiran->execute([$new_jadwal_id, $id_anak]);
                        $total_sasaran++;
                    }
                }
            }

            $pdo->commit();

            clear_old_input();
            $msg_sukses = "Jadwal kegiatan untuk <strong>" . e($posyandu_data['nama_posyandu']) . "</strong> berhasil dibuat!";
            if ($total_sasaran > 0) {
                $msg_sukses .= " {$total_sasaran} balita terdaftar telah dimasukkan ke daftar sasaran presensi.";
            }
            set_flash('success', $msg_sukses);
            redirect(base_url('modules/jadwal/detail.php?id=' . $new_jadwal_id));

        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Gagal menyimpan jadwal: ' . $e->getMessage();
        }
    }
}

$page_title  = 'Tambah Jadwal Kegiatan Posyandu';
$breadcrumbs = [
    'Data & Laporan'     => '#',
    'Jadwal & Kunjungan' => base_url('modules/jadwal/index.php'),
    'Tambah Jadwal'      => '',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-calendar-plus" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Agenda Posyandu</p>
      <h1 class="h3 mb-1">Tambah Jadwal Kegiatan Baru</h1>
      <p class="text-muted mb-0">Atur jadwal sesi penimbangan bulanan balita, lokasi pelaksanaan, dan sasaran presensi.</p>
    </div>
  </div>
  <div class="heading-actions d-flex gap-2">
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

<form method="POST" action="<?= base_url('modules/jadwal/tambah.php') ?>" id="formTambahJadwal">
  <?= csrf_field() ?>

  <div class="row g-4">
    <!-- Kolom Kiri: Form Input -->
    <div class="col-lg-7">
      <div class="panel">
        <div class="panel-header border-bottom pb-3 mb-3">
          <h5 class="panel-title mb-0">Informasi Sesi Posyandu</h5>
          <small class="text-muted">Isi rincian waktu, lokasi, dan agenda penimbangan</small>
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
            <option value="">-- Pilih Posyandu Binaan --</option>
            <?php foreach ($posyandu_list as $pos): ?>
              <option value="<?= (int)$pos['id_posyandu'] ?>"
                      data-alamat="<?= e($pos['alamat']) ?>"
                      <?= (int)old('id_posyandu') === (int)$pos['id_posyandu'] ? 'selected' : '' ?>>
                <?= e($pos['nama_posyandu']) ?> (<?= e($pos['alamat'] ?: 'Wilayah Kerja') ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['id_posyandu'])): ?>
            <div class="invalid-feedback"><?= e($errors['id_posyandu']) ?></div>
          <?php endif; ?>
          <div class="form-text small">Pilih posyandu tempat kegiatan diselenggarakan.</div>
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
                     value="<?= e(old('tanggal', date('Y-m-d'))) ?>"
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
                     value="<?= e(old('waktu', '08:30')) ?>"
                     required>
              <?php if (isset($errors['waktu'])): ?>
                <div class="invalid-feedback"><?= e($errors['waktu']) ?></div>
              <?php endif; ?>
            </div>
            <div class="form-text small">Waktu mulai sesi Posyandu (WIB).</div>
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
                   value="<?= e(old('lokasi')) ?>"
                   placeholder="Contoh: Balai Warga RW 01, Halaman Rumah Ibu RT"
                   required>
            <?php if (isset($errors['lokasi'])): ?>
              <div class="invalid-feedback"><?= e($errors['lokasi']) ?></div>
            <?php endif; ?>
          </div>
          <div class="form-text small">Tempat fisik berkumpulnya kader dan para ibu balita.</div>
        </div>

        <!-- Keterangan / Agenda -->
        <div class="mb-4">
          <label for="keterangan" class="form-label fw-semibold">
            Agenda / Keterangan Khusus <span class="text-muted fw-normal">(Opsional)</span>
          </label>
          <textarea class="form-control"
                    id="keterangan"
                    name="keterangan"
                    rows="3"
                    placeholder="Contoh: Penimbangan rutin balita, pembagian vitamin A dosis tinggi, serta penyuluhan menu MP-ASI kaya telur & ikan."><?= e(old('keterangan')) ?></textarea>
          <div class="form-text small">Informasi tambahan agenda yang akan dilaksanakan pada hari kegiatan.</div>
        </div>

        <!-- Opsi Otomatisasi Sasaran Kehadiran -->
        <div class="p-3 bg-light rounded-3 border mb-4">
          <div class="form-check form-switch mb-0">
            <input class="form-check-input"
                   type="checkbox"
                   role="switch"
                   id="auto_generate"
                   name="auto_generate"
                   value="1"
                   <?= old('auto_generate', true) ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold text-dark" for="auto_generate">
              Generate Daftar Sasaran Balita Otomatis
            </label>
            <div class="text-muted small mt-1">
              Sistem akan otomatis memasukkan seluruh balita binaan Posyandu ini ke daftar presensi dengan status awal <em>Belum Hadir</em>, sehingga kader dapat langsung melakukan check-in saat hari kegiatan.
            </div>
          </div>
        </div>

        <div class="d-flex gap-2 justify-content-end pt-3 border-top">
          <a href="<?= base_url('modules/jadwal/index.php') ?>" class="btn btn-secondary">
            Batal
          </a>
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-calendar-check me-1" aria-hidden="true"></i> Simpan & Buat Jadwal
          </button>
        </div>
      </div>
    </div>

    <!-- Kolom Kanan: Panduan Operasional Posyandu -->
    <div class="col-lg-5">
      <div class="panel">
        <div class="panel-header border-bottom pb-3 mb-3">
          <h5 class="panel-title mb-0">Panduan Sesi 5 Meja Posyandu</h5>
          <small class="text-muted">Prosedur operasional standar pelayanan balita</small>
        </div>

        <div class="d-flex flex-column gap-3 small">
          <div class="d-flex align-items-start gap-3 p-2 rounded border bg-light">
            <span class="badge bg-primary rounded-circle p-2 fs-6">1</span>
            <div>
              <strong>Meja 1: Pendaftaran</strong>
              <div class="text-muted">Pencatatan data balita & verifikasi kehadiran di sistem SiPantau Gizi.</div>
            </div>
          </div>

          <div class="d-flex align-items-start gap-3 p-2 rounded border bg-light">
            <span class="badge bg-info text-white rounded-circle p-2 fs-6">2</span>
            <div>
              <strong>Meja 2: Penimbangan & Pengukuran</strong>
              <div class="text-muted">Penimbangan BB, pengukuran TB/panjang badan, dan lingkar kepala (LK).</div>
            </div>
          </div>

          <div class="d-flex align-items-start gap-3 p-2 rounded border bg-light">
            <span class="badge bg-success rounded-circle p-2 fs-6">3</span>
            <div>
              <strong>Meja 3: Pencatatan (KMS Digital)</strong>
              <div class="text-muted">Input hasil pengukuran ke menu <em>Pengukuran Gizi</em> untuk kalkulasi otomatis Z-score WHO.</div>
            </div>
          </div>

          <div class="d-flex align-items-start gap-3 p-2 rounded border bg-light">
            <span class="badge bg-warning text-dark rounded-circle p-2 fs-6">4</span>
            <div>
              <strong>Meja 4: Penyuluhan & PMT</strong>
              <div class="text-muted">Konseling gizi berdasarkan status Z-score dan pemberian PMT pemulihan/penyuluhan.</div>
            </div>
          </div>

          <div class="d-flex align-items-start gap-3 p-2 rounded border bg-light">
            <span class="badge bg-danger rounded-circle p-2 fs-6">5</span>
            <div>
              <strong>Meja 5: Pelayanan Kesehatan</strong>
              <div class="text-muted">Pemberian imunisasi dasar, vitamin A, oralit, dan rujukan nakes/Puskesmas.</div>
            </div>
          </div>
        </div>

        <div class="alert alert-info py-2 small mt-4 mb-0">
          <i class="bi bi-info-circle me-1"></i> Setelah jadwal dibuat, kader dapat mencetak Lembar Presensi Kosong atau memantau kehadiran secara digital.
        </div>
      </div>
    </div>
  </div>
</form>

<?php
$extra_scripts = <<<JS
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const selPosyandu = document.getElementById('id_posyandu');
    const inputLokasi = document.getElementById('lokasi');

    selPosyandu.addEventListener('change', function () {
      const opt = this.options[this.selectedIndex];
      if (opt && opt.value && !inputLokasi.value) {
        const alamat = opt.getAttribute('data-alamat');
        if (alamat) {
          inputLokasi.value = 'Pos ' + opt.textContent.split('(')[0].trim() + ', ' + alamat;
        }
      }
    });
  });
</script>
JS;

require_once ROOT_PATH . '/includes/footer.php';
?>
