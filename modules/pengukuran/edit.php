<?php
/**
 * Modul Pengukuran Gizi — Edit & Perbarui Data Pengukuran
 * ==============================================================
 * Formulir koreksi data antropometri balita.
 * Otomatis menghitung ulang Z-Score WHO & Permenkes RI No. 2/2020.
 */

$current_slug = 'pengukuran';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';
require_once ROOT_PATH . '/src/Antropometri.php';

// Pastikan memiliki hak akses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'pengukuran')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengedit data pengukuran.');
    redirect(base_url('modules/pengukuran/index.php'));
}

$id_pengukuran = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_pengukuran) {
    set_flash('danger', 'ID Pengukuran tidak valid.');
    redirect(base_url('modules/pengukuran/index.php'));
}

// Ambil data pengukuran beserta relasinya
$pengukuran = null;
try {
    if ($pdo !== null) {
        $stmt = $pdo->prepare("
            SELECT 
                p.id_pengukuran,
                p.id_anak,
                p.id_user,
                p.tanggal_pengukuran,
                p.berat_badan,
                p.tinggi_badan,
                p.lingkar_kepala,
                a.nama AS nama_anak,
                a.nik,
                a.tanggal_lahir,
                a.jenis_kelamin,
                pos.nama_posyandu,
                ot.nama_ibu,
                ot.nama_ayah,
                u.nama AS nama_petugas,
                sg.id_status_gizi,
                sg.zscore_bb_u,
                sg.zscore_tb_u,
                sg.zscore_bb_tb,
                sg.zscore_imt_u,
                sg.status_bb_u,
                sg.status_tb_u,
                sg.status_bb_tb,
                sg.status_imt_u,
                sg.status_risiko
            FROM pengukuran p
            JOIN anak a ON p.id_anak = a.id_anak
            LEFT JOIN posyandu pos ON a.id_posyandu = pos.id_posyandu
            LEFT JOIN orangtua ot ON a.id_orangtua = ot.id_orangtua
            LEFT JOIN user u ON p.id_user = u.id_user
            LEFT JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
            WHERE p.id_pengukuran = ?
            LIMIT 1
        ");
        $stmt->execute([$id_pengukuran]);
        $pengukuran = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat data pengukuran: ' . $e->getMessage());
    redirect(base_url('modules/pengukuran/index.php'));
}

if (!$pengukuran) {
    set_flash('warning', 'Data pengukuran tidak ditemukan.');
    redirect(base_url('modules/pengukuran/index.php'));
}

$errors = [];

// ============================================================
// Proses Perbarui Data Pengukuran (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Cek CSRF
    if (!verify_csrf_token()) {
        $errors[] = 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba lagi.';
    }

    $tanggal_pengukuran = trim($_POST['tanggal_pengukuran'] ?? '');
    $berat_badan        = filter_input(INPUT_POST, 'berat_badan', FILTER_VALIDATE_FLOAT);
    $tinggi_badan       = filter_input(INPUT_POST, 'tinggi_badan', FILTER_VALIDATE_FLOAT);
    $lingkar_kepala     = !empty($_POST['lingkar_kepala']) ? filter_input(INPUT_POST, 'lingkar_kepala', FILTER_VALIDATE_FLOAT) : null;

    // 2. Validasi Tanggal
    if ($tanggal_pengukuran === '') {
        $errors['tanggal_pengukuran'] = 'Tanggal pengukuran wajib diisi.';
    } elseif (strtotime($tanggal_pengukuran) < strtotime($pengukuran['tanggal_lahir'])) {
        $errors['tanggal_pengukuran'] = 'Tanggal pengukuran tidak boleh mendahului tanggal lahir balita (' . date('d/m/Y', strtotime($pengukuran['tanggal_lahir'])) . ').';
    }

    // 3. Validasi Berat Badan
    if ($berat_badan === false || $berat_badan === null || $berat_badan <= 0) {
        $errors['berat_badan'] = 'Berat badan wajib diisi angka positif (kg).';
    } elseif ($berat_badan < 1.0 || $berat_badan > 50.0) {
        $errors['berat_badan'] = 'Berat badan di luar rentang normal balita (1.0 - 50.0 kg).';
    }

    // 4. Validasi Tinggi Badan
    if ($tinggi_badan === false || $tinggi_badan === null || $tinggi_badan <= 0) {
        $errors['tinggi_badan'] = 'Tinggi / panjang badan wajib diisi angka positif (cm).';
    } elseif ($tinggi_badan < 30.0 || $tinggi_badan > 150.0) {
        $errors['tinggi_badan'] = 'Tinggi / panjang badan di luar rentang normal balita (30.0 - 150.0 cm).';
    }

    // 5. Validasi Lingkar Kepala (Opsional)
    if ($lingkar_kepala !== null && ($lingkar_kepala < 20.0 || $lingkar_kepala > 65.0)) {
        $errors['lingkar_kepala'] = 'Lingkar kepala di luar rentang wajar (20.0 - 65.0 cm).';
    }

    // 6. Eksekusi Update ke Tabel pengukuran & status_gizi (Atomic Transaction)
    if (empty($errors) && $pdo !== null) {
        try {
            $pdo->beginTransaction();

            // Hitung ulang Z-Score & Status Gizi menggunakan Engine Antropometri
            $usia_bulan = Antropometri::hitungUsiaBulan($pengukuran['tanggal_lahir'], $tanggal_pengukuran);
            $hasilGizi  = Antropometri::kalkulasiStatusGizi((float)$berat_badan, (float)$tinggi_badan, $usia_bulan, $pengukuran['jenis_kelamin']);

            // Update tabel pengukuran
            $stmtUpdateP = $pdo->prepare("
                UPDATE pengukuran 
                SET tanggal_pengukuran = ?, berat_badan = ?, tinggi_badan = ?, lingkar_kepala = ?
                WHERE id_pengukuran = ?
            ");
            $stmtUpdateP->execute([
                $tanggal_pengukuran,
                $berat_badan,
                $tinggi_badan,
                $lingkar_kepala ?: null,
                $id_pengukuran
            ]);

            // Cek apakah status_gizi sudah ada
            $stmtCekGizi = $pdo->prepare("SELECT id_status_gizi FROM status_gizi WHERE id_pengukuran = ? LIMIT 1");
            $stmtCekGizi->execute([$id_pengukuran]);
            $existsGizi = $stmtCekGizi->fetchColumn();

            if ($existsGizi) {
                // Update tabel status_gizi
                $stmtUpdateSG = $pdo->prepare("
                    UPDATE status_gizi SET
                        zscore_bb_u = ?, zscore_tb_u = ?, zscore_bb_tb = ?, zscore_imt_u = ?,
                        status_bb_u = ?, status_tb_u = ?, status_bb_tb = ?, status_imt_u = ?, status_risiko = ?
                    WHERE id_pengukuran = ?
                ");
                $stmtUpdateSG->execute([
                    $hasilGizi['zscore_bb_u'],
                    $hasilGizi['zscore_tb_u'],
                    $hasilGizi['zscore_bb_tb'],
                    $hasilGizi['zscore_imt_u'],
                    $hasilGizi['status_bb_u'],
                    $hasilGizi['status_tb_u'],
                    $hasilGizi['status_bb_tb'],
                    $hasilGizi['status_imt_u'],
                    $hasilGizi['status_risiko'],
                    $id_pengukuran
                ]);
            } else {
                // Insert status_gizi jika sebelumnya belum ada
                $stmtInsertSG = $pdo->prepare("
                    INSERT INTO status_gizi (
                        id_pengukuran, zscore_bb_u, zscore_tb_u, zscore_bb_tb, zscore_imt_u,
                        status_bb_u, status_tb_u, status_bb_tb, status_imt_u, status_risiko
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtInsertSG->execute([
                    $id_pengukuran,
                    $hasilGizi['zscore_bb_u'],
                    $hasilGizi['zscore_tb_u'],
                    $hasilGizi['zscore_bb_tb'],
                    $hasilGizi['zscore_imt_u'],
                    $hasilGizi['status_bb_u'],
                    $hasilGizi['status_tb_u'],
                    $hasilGizi['status_bb_tb'],
                    $hasilGizi['status_imt_u'],
                    $hasilGizi['status_risiko']
                ]);
            }

            $pdo->commit();

            set_flash('success', "Data pengukuran <strong>" . e($pengukuran['nama_anak']) . "</strong> berhasil diperbarui! Status gizi telah dikalkulasi ulang secara otomatis.");
            redirect(base_url('modules/pengukuran/detail.php?id=' . $id_pengukuran));

        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Gagal menyimpan perubahan: ' . $e->getMessage();
        }
    }
}

// Data awal formulir
$val_tgl_pengukuran = $_POST['tanggal_pengukuran'] ?? $pengukuran['tanggal_pengukuran'];
$val_berat_badan    = $_POST['berat_badan'] ?? $pengukuran['berat_badan'];
$val_tinggi_badan   = $_POST['tinggi_badan'] ?? $pengukuran['tinggi_badan'];
$val_lingkar_kepala = $_POST['lingkar_kepala'] ?? $pengukuran['lingkar_kepala'];

// Hitung usia pada saat pengukuran
$usia_saat_ukur = Antropometri::formatUsia(Antropometri::hitungUsiaBulan($pengukuran['tanggal_lahir'], $val_tgl_pengukuran));

// Variabel layout
$page_title  = 'Edit Pengukuran: ' . $pengukuran['nama_anak'];
$breadcrumbs = [
    'Data & Laporan'  => '#',
    'Pengukuran Gizi' => base_url('modules/pengukuran/index.php'),
    'Edit Pengukuran' => '',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-pencil-square" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Koreksi Data Antropometri</p>
      <h1 class="h3 mb-1">Edit Pengukuran Balita</h1>
      <p class="text-muted mb-0">Perbarui data penimbangan balita. Z-Score WHO akan dihitung ulang secara otomatis.</p>
    </div>
  </div>
  <div class="heading-actions d-flex gap-2">
    <a href="<?= base_url('modules/pengukuran/detail.php?id=' . (int)$pengukuran['id_pengukuran']) ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-eye me-1" aria-hidden="true"></i> Lihat Detail
    </a>
    <a href="<?= base_url('modules/pengukuran/index.php') ?>" class="btn btn-outline-secondary btn-sm">
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

<!-- Formulir Edit Pengukuran -->
<form method="POST" action="<?= base_url('modules/pengukuran/edit.php?id=' . (int)$pengukuran['id_pengukuran']) ?>" id="formEditPengukuran">
  <?= csrf_field() ?>

  <div class="row g-4">
    <!-- Kolom Kiri: Input Pengukuran -->
    <div class="col-lg-7">
      <div class="panel h-100">
        <div class="panel-header d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
          <div>
            <h5 class="panel-title mb-0">Formulir Koreksi Data</h5>
            <small class="text-muted">Periksa kembali data penimbangan balita</small>
          </div>
          <span class="badge bg-light text-dark border">
            ID: #<?= (int)$pengukuran['id_pengukuran'] ?>
          </span>
        </div>

        <!-- Kartu Identitas Balita (Read-Only) -->
        <div class="card border bg-light mb-4 shadow-none">
          <div class="card-body p-3">
            <div class="d-flex align-items-center gap-3">
              <span class="badge <?= $pengukuran['jenis_kelamin'] === 'L' ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?> rounded-circle p-3 fs-5">
                <?= $pengukuran['jenis_kelamin'] === 'L' ? 'L' : 'P' ?>
              </span>
              <div class="flex-grow-1">
                <h6 class="fw-bold text-dark mb-1"><?= e($pengukuran['nama_anak']) ?></h6>
                <div class="text-muted small">
                  <span><strong>NIK:</strong> <?= e($pengukuran['nik']) ?></span> ·
                  <span><strong>Lahir:</strong> <?= date('d/m/Y', strtotime($pengukuran['tanggal_lahir'])) ?></span>
                </div>
                <div class="text-muted small mt-1">
                  <i class="bi bi-hospital me-1"></i><?= e($pengukuran['nama_posyandu'] ?: 'Posyandu') ?>
                  <?php if (!empty($pengukuran['nama_ibu'])): ?>
                    · Ibu: <?= e($pengukuran['nama_ibu']) ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <input type="hidden" id="id_anak" name="id_anak" value="<?= (int)$pengukuran['id_anak'] ?>">
        <input type="hidden" id="tanggal_lahir" value="<?= e($pengukuran['tanggal_lahir']) ?>">
        <input type="hidden" id="jenis_kelamin" value="<?= e($pengukuran['jenis_kelamin']) ?>">

        <!-- Tanggal Pengukuran -->
        <div class="mb-3">
          <label for="tanggal_pengukuran" class="form-label fw-semibold">
            Tanggal Pengukuran <span class="text-danger">*</span>
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
            <input type="date"
                   class="form-control <?= isset($errors['tanggal_pengukuran']) ? 'is-invalid' : '' ?>"
                   id="tanggal_pengukuran"
                   name="tanggal_pengukuran"
                   value="<?= e($val_tgl_pengukuran) ?>"
                   max="<?= date('Y-m-d') ?>"
                   required>
            <?php if (isset($errors['tanggal_pengukuran'])): ?>
              <div class="invalid-feedback"><?= e($errors['tanggal_pengukuran']) ?></div>
            <?php endif; ?>
          </div>
          <div class="form-text small">Sesuaikan tanggal saat kader / nakes melakukan penimbangan.</div>
        </div>

        <div class="row g-3 mb-3">
          <!-- Berat Badan -->
          <div class="col-md-6">
            <label for="berat_badan" class="form-label fw-semibold">
              Berat Badan (BB) <span class="text-danger">*</span>
            </label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-speedometer2"></i></span>
              <input type="number"
                     step="0.01"
                     min="1.0"
                     max="50.0"
                     class="form-control <?= isset($errors['berat_badan']) ? 'is-invalid' : '' ?>"
                     id="berat_badan"
                     name="berat_badan"
                     value="<?= e($val_berat_badan) ?>"
                     placeholder="contoh: 10.45"
                     required>
              <span class="input-group-text">kg</span>
              <?php if (isset($errors['berat_badan'])): ?>
                <div class="invalid-feedback"><?= e($errors['berat_badan']) ?></div>
              <?php endif; ?>
            </div>
            <div class="form-text small">Ketelitian timbangan digital (misal 9.50 kg).</div>
          </div>

          <!-- Tinggi / Panjang Badan -->
          <div class="col-md-6">
            <label for="tinggi_badan" class="form-label fw-semibold">
              Tinggi / Panjang Badan (TB) <span class="text-danger">*</span>
            </label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-rulers"></i></span>
              <input type="number"
                     step="0.1"
                     min="30.0"
                     max="150.0"
                     class="form-control <?= isset($errors['tinggi_badan']) ? 'is-invalid' : '' ?>"
                     id="tinggi_badan"
                     name="tinggi_badan"
                     value="<?= e($val_tinggi_badan) ?>"
                     placeholder="contoh: 82.5"
                     required>
              <span class="input-group-text">cm</span>
              <?php if (isset($errors['tinggi_badan'])): ?>
                <div class="invalid-feedback"><?= e($errors['tinggi_badan']) ?></div>
              <?php endif; ?>
            </div>
            <div class="form-text small">Panjang badan (< 2 th) atau tinggi badan (≥ 2 th).</div>
          </div>
        </div>

        <!-- Lingkar Kepala (Opsional) -->
        <div class="mb-4">
          <label for="lingkar_kepala" class="form-label fw-semibold">
            Lingkar Kepala (LK) <span class="text-muted fw-normal">(Opsional)</span>
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-circle"></i></span>
            <input type="number"
                   step="0.1"
                   min="20.0"
                   max="65.0"
                   class="form-control <?= isset($errors['lingkar_kepala']) ? 'is-invalid' : '' ?>"
                   id="lingkar_kepala"
                   name="lingkar_kepala"
                   value="<?= e($val_lingkar_kepala) ?>"
                   placeholder="contoh: 46.0">
            <span class="input-group-text">cm</span>
            <?php if (isset($errors['lingkar_kepala'])): ?>
              <div class="invalid-feedback"><?= e($errors['lingkar_kepala']) ?></div>
            <?php endif; ?>
          </div>
          <div class="form-text small">Ukuran lingkar kepala untuk pemantauan mikrosefali/makrosefali.</div>
        </div>

        <!-- Petugas Pencatat Info -->
        <div class="bg-light p-3 rounded-2 border mb-4">
          <div class="d-flex align-items-center justify-content-between small text-muted">
            <span><i class="bi bi-person-badge me-1"></i> Dicatat oleh: <strong><?= e($pengukuran['nama_petugas'] ?: 'Kader Posyandu') ?></strong></span>
            <span><i class="bi bi-clock-history me-1"></i> Terakhir diubah: <?= date('d/m/Y') ?></span>
          </div>
        </div>

        <div class="d-flex gap-2 justify-content-end pt-3 border-top">
          <a href="<?= base_url('modules/pengukuran/detail.php?id=' . (int)$pengukuran['id_pengukuran']) ?>" class="btn btn-secondary">
            Batal
          </a>
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Simpan Perubahan
          </button>
        </div>
      </div>
    </div>

    <!-- Kolom Kanan: Pratinjau Real-Time Kalkulasi Status Gizi -->
    <div class="col-lg-5">
      <div class="panel sticky-top" style="top: 80px;">
        <div class="panel-header border-bottom pb-3 mb-3 d-flex align-items-center justify-content-between">
          <div>
            <h5 class="panel-title mb-0">Evaluasi Status Gizi (WHO)</h5>
            <small class="text-muted">Kalkulasi real-time sesuai Permenkes No. 2/2020</small>
          </div>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
            <i class="bi bi-cpu me-1"></i> Live Auto
          </span>
        </div>

        <div id="boxHasilGizi">
          <!-- Usia Balita saat Penimbangan -->
          <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded-3 border">
            <div>
              <div class="text-muted small">Usia saat Pengukuran</div>
              <div class="fw-bold text-dark fs-6" id="lblUsiaTeks"><?= e($usia_saat_ukur) ?></div>
            </div>
            <span class="badge bg-primary text-white rounded-pill px-3 py-2">
              <i class="bi bi-calendar-check me-1"></i> Terkalkulasi
            </span>
          </div>

          <!-- 3 Indikator Utama Antropometri -->
          <div class="d-flex flex-column gap-3 mb-4">
            <!-- 1. TB/U (Stunting Indikator) -->
            <div class="border rounded-3 p-3">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h6 class="mb-0 fw-bold">Tinggi Badan menurut Usia (TB/U)</h6>
                  <small class="text-muted">Indikator Stunting / Perawakan</small>
                </div>
                <div class="text-end">
                  <span id="badgeTBU" class="badge fs-6 mt-1 <?= Antropometri::getBadgeTBU($pengukuran['status_tb_u']) ?>">
                    <?= e($pengukuran['status_tb_u'] ?: 'Normal') ?>
                  </span>
                  <div class="small font-monospace text-muted mt-1" id="lblZscoreTBU">
                    Z: <?= ($pengukuran['zscore_tb_u'] > 0 ? '+' : '') . number_format($pengukuran['zscore_tb_u'], 2) ?> SD
                  </div>
                </div>
              </div>
            </div>

            <!-- 2. BB/U (Status Berat Badan) -->
            <div class="border rounded-3 p-3">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h6 class="mb-0 fw-bold">Berat Badan menurut Usia (BB/U)</h6>
                  <small class="text-muted">Indikator Berat Badan Kurang</small>
                </div>
                <div class="text-end">
                  <span id="badgeBBU" class="badge fs-6 mt-1 <?= Antropometri::getBadgeBBU($pengukuran['status_bb_u']) ?>">
                    <?= e($pengukuran['status_bb_u'] ?: 'Gizi Baik') ?>
                  </span>
                  <div class="small font-monospace text-muted mt-1" id="lblZscoreBBU">
                    Z: <?= ($pengukuran['zscore_bb_u'] > 0 ? '+' : '') . number_format($pengukuran['zscore_bb_u'], 2) ?> SD
                  </div>
                </div>
              </div>
            </div>

            <!-- 3. BB/TB (Wasting Indikator) -->
            <div class="border rounded-3 p-3">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h6 class="mb-0 fw-bold">BB menurut TB (BB/TB)</h6>
                  <small class="text-muted">Indikator Gizi Buruk / Akut (Wasting)</small>
                </div>
                <div class="text-end">
                  <span id="badgeBBTB" class="badge fs-6 mt-1 <?= Antropometri::getBadgeBBTB($pengukuran['status_bb_tb']) ?>">
                    <?= e($pengukuran['status_bb_tb'] ?: 'Gizi Baik') ?>
                  </span>
                  <div class="small font-monospace text-muted mt-1" id="lblZscoreBBTB">
                    Z: <?= ($pengukuran['zscore_bb_tb'] > 0 ? '+' : '') . number_format($pengukuran['zscore_bb_tb'], 2) ?> SD
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Status Risiko & Rekomendasi -->
          <?php
          $bg_risiko = 'bg-success-subtle text-success border-success-subtle';
          if ($pengukuran['status_risiko'] === 'Butuh Rujukan Segera') {
              $bg_risiko = 'bg-danger-subtle text-danger border-danger-subtle';
          } elseif ($pengukuran['status_risiko'] === 'Berisiko') {
              $bg_risiko = 'bg-warning-subtle text-warning border-warning-subtle';
          }
          ?>
          <div id="alertRisikoBox" class="p-3 rounded-3 border <?= $bg_risiko ?>">
            <div class="d-flex align-items-start gap-2">
              <i class="bi bi-shield-exclamation fs-4 mt-n1" id="iconRisiko"></i>
              <div>
                <h6 class="fw-bold mb-1" id="titleRisiko">Status Risiko: <?= e($pengukuran['status_risiko'] ?: 'Normal') ?></h6>
                <p class="small mb-0" id="descRisiko">
                  <?php if ($pengukuran['status_risiko'] === 'Butuh Rujukan Segera'): ?>
                    PERHATIAN: Balita terindikasi <?= e($pengukuran['status_tb_u']) ?> / <?= e($pengukuran['status_bb_u']) ?>. Perlu koordinasi rujukan ke Puskesmas.
                  <?php elseif ($pengukuran['status_risiko'] === 'Berisiko'): ?>
                    WASPADA: Balita berisiko mengalami gangguan gizi. Pantau ketat penimbangan bulan depan.
                  <?php else: ?>
                    Tumbuh kembang optimal. Pertahankan pola asuh dan asupan gizi seimbang.
                  <?php endif; ?>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>

<?php
$api_url = base_url('modules/pengukuran/api_hitung.php');
$extra_scripts = <<<JS
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const idAnakVal   = document.getElementById('id_anak').value;
    const inputTgl    = document.getElementById('tanggal_pengukuran');
    const inputBB     = document.getElementById('berat_badan');
    const inputTB     = document.getElementById('tinggi_badan');

    const lblUsia     = document.getElementById('lblUsiaTeks');
    const badgeTBU    = document.getElementById('badgeTBU');
    const lblZTBU     = document.getElementById('lblZscoreTBU');
    const badgeBBU    = document.getElementById('badgeBBU');
    const lblZBBU     = document.getElementById('lblZscoreBBU');
    const badgeBBTB   = document.getElementById('badgeBBTB');
    const lblZBBTB    = document.getElementById('lblZscoreBBTB');

    const alertBox    = document.getElementById('alertRisikoBox');
    const titleRisiko = document.getElementById('titleRisiko');
    const descRisiko  = document.getElementById('descRisiko');

    let debounceTimer = null;
    function triggerHitung() {
      const tgl = inputTgl.value;
      const bb  = parseFloat(inputBB.value);
      const tb  = parseFloat(inputTB.value);

      if (!idAnakVal || isNaN(bb) || isNaN(tb) || bb <= 0 || tb <= 0) {
        return;
      }

      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        fetch('{$api_url}?id_anak=' + idAnakVal + '&tanggal_pengukuran=' + encodeURIComponent(tgl) + '&bb=' + bb + '&tb=' + tb)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              lblUsia.textContent = data.usia_teks;

              badgeTBU.textContent = data.status_tb_u;
              badgeTBU.className = 'badge fs-6 mt-1 ' + data.badge_tb_u;
              lblZTBU.textContent = (data.zscore_tb_u > 0 ? '+' : '') + data.zscore_tb_u + ' SD';

              badgeBBU.textContent = data.status_bb_u;
              badgeBBU.className = 'badge fs-6 mt-1 ' + data.badge_bb_u;
              lblZBBU.textContent = (data.zscore_bb_u > 0 ? '+' : '') + data.zscore_bb_u + ' SD';

              badgeBBTB.textContent = data.status_bb_tb;
              badgeBBTB.className = 'badge fs-6 mt-1 ' + data.badge_bb_tb;
              lblZBBTB.textContent = (data.zscore_bb_tb > 0 ? '+' : '') + data.zscore_bb_tb + ' SD';

              titleRisiko.textContent = 'Status Risiko: ' + data.status_risiko;
              if (data.status_risiko === 'Butuh Rujukan Segera') {
                alertBox.className = 'p-3 rounded-3 border bg-danger-subtle text-danger border-danger-subtle';
                descRisiko.textContent = 'PERHATIAN: Balita terindikasi ' + data.status_tb_u + ' / ' + data.status_bb_u + '. Perlu koordinasi rujukan ke Puskesmas.';
              } else if (data.status_risiko === 'Berisiko') {
                alertBox.className = 'p-3 rounded-3 border bg-warning-subtle text-warning border-warning-subtle';
                descRisiko.textContent = 'WASPADA: Balita berisiko mengalami gangguan gizi. Pantau ketat penimbangan bulan depan.';
              } else {
                alertBox.className = 'p-3 rounded-3 border bg-success-subtle text-success border-success-subtle';
                descRisiko.textContent = 'Tumbuh kembang optimal. Pertahankan pola asuh dan asupan gizi seimbang.';
              }
            }
          })
          .catch(err => {
            console.error('Error kalkulasi:', err);
          });
      }, 250);
    }

    inputTgl.addEventListener('change', triggerHitung);
    inputBB.addEventListener('input', triggerHitung);
    inputTB.addEventListener('input', triggerHitung);
  });
</script>
JS;

require_once ROOT_PATH . '/includes/footer.php';
?>
