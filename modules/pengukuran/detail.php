<?php
/**
 * Modul Pengukuran Gizi — Detail Hasil Pengukuran & KMS Digital
 * ==============================================================
 * Menampilkan rincian hasil pengukuran antropometri balita,
 * evaluasi Z-Score WHO/Permenkes No. 2/2020, rekomendasi intervensi,
 * dan log riwayat pertumbuhan balita secara komprehensif.
 */

$current_slug = 'pengukuran';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';
require_once ROOT_PATH . '/src/Antropometri.php';

// Pastikan memiliki hak akses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'pengukuran')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk melihat detail pengukuran.');
    redirect(base_url('modules/pengukuran/index.php'));
}

$id_pengukuran = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_pengukuran) {
    set_flash('danger', 'ID Pengukuran tidak valid.');
    redirect(base_url('modules/pengukuran/index.php'));
}

// Ambil data pengukuran utama
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
                pos.alamat AS alamat_posyandu,
                ot.nama_ibu,
                ot.nama_ayah,
                ot.no_telepon AS telepon,
                ot.alamat AS alamat_orangtua,
                u.nama AS nama_petugas,
                u.email AS email_petugas,
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
    set_flash('danger', 'Gagal memuat detail data pengukuran: ' . $e->getMessage());
    redirect(base_url('modules/pengukuran/index.php'));
}

if (!$pengukuran) {
    set_flash('warning', 'Data pengukuran tidak ditemukan.');
    redirect(base_url('modules/pengukuran/index.php'));
}

// Ambil semua riwayat penimbangan balita ini untuk Lembar KMS Digital
$riwayat_pengukuran = [];
try {
    if ($pdo !== null) {
        $stmtRiwayat = $pdo->prepare("
            SELECT 
                p.id_pengukuran,
                p.tanggal_pengukuran,
                p.berat_badan,
                p.tinggi_badan,
                p.lingkar_kepala,
                sg.zscore_bb_u,
                sg.zscore_tb_u,
                sg.zscore_bb_tb,
                sg.status_bb_u,
                sg.status_tb_u,
                sg.status_bb_tb,
                sg.status_risiko
            FROM pengukuran p
            LEFT JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
            WHERE p.id_anak = ?
            ORDER BY p.tanggal_pengukuran ASC
        ");
        $stmtRiwayat->execute([$pengukuran['id_anak']]);
        $riwayat_pengukuran = $stmtRiwayat->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $riwayat_pengukuran = [];
}

// Hitung usia pada saat pengukuran
$usia_bulan_ukur = Antropometri::hitungUsiaBulan($pengukuran['tanggal_lahir'], $pengukuran['tanggal_pengukuran']);
$usia_teks_ukur  = Antropometri::formatUsia($usia_bulan_ukur);

// Hitung usia saat ini
$usia_bulan_kini = Antropometri::hitungUsiaBulan($pengukuran['tanggal_lahir'], date('Y-m-d'));
$usia_teks_kini  = Antropometri::formatUsia($usia_bulan_kini);

// Hitung IMT (Indeks Massa Tubuh)
$tb_m = (float)$pengukuran['tinggi_badan'] / 100;
$imt  = $tb_m > 0 ? (float)$pengukuran['berat_badan'] / ($tb_m * $tb_m) : 0;

$page_title  = 'Detail Pengukuran: ' . $pengukuran['nama_anak'];
$breadcrumbs = [
    'Data & Laporan'  => '#',
    'Pengukuran Gizi' => base_url('modules/pengukuran/index.php'),
    'Detail & KMS'    => '',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-file-earmark-medical" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Kartu Menuju Sehat (KMS) & Antropometri</p>
      <h1 class="h3 mb-1"><?= e($pengukuran['nama_anak']) ?></h1>
      <p class="text-muted mb-0">
        NIK: <?= e($pengukuran['nik']) ?> · Penimbangan tanggal <?= date('d F Y', strtotime($pengukuran['tanggal_pengukuran'])) ?> (Usia: <?= e($usia_teks_ukur) ?>)
      </p>
    </div>
  </div>
  <div class="heading-actions d-flex flex-wrap gap-2">
    <a href="<?= base_url('modules/pengukuran/ekspor.php?format=print&id=' . (int)$pengukuran['id_pengukuran']) ?>"
       target="_blank"
       class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-printer me-1" aria-hidden="true"></i> Cetak Lembar Pengukuran
    </a>
    <a href="<?= base_url('modules/pengukuran/edit.php?id=' . (int)$pengukuran['id_pengukuran']) ?>"
       class="btn btn-outline-primary btn-sm">
      <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Edit Data
    </a>
    <a href="<?= base_url('modules/pengukuran/index.php') ?>" class="btn btn-secondary btn-sm">
      <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali ke Daftar
    </a>
  </div>
</div>

<div class="row g-4">
  <!-- Kolom Kiri: Ringkasan Pengukuran & Profil Balita -->
  <div class="col-lg-7">
    <!-- Kartu Hasil Pengukuran Terpilih -->
    <div class="panel mb-4">
      <div class="panel-header border-bottom pb-3 mb-3 d-flex align-items-center justify-content-between">
        <div>
          <h5 class="panel-title mb-0">Hasil Penimbangan & Pengukuran Fisik</h5>
          <small class="text-muted">Tanggal Ukur: <?= date('d/m/Y', strtotime($pengukuran['tanggal_pengukuran'])) ?></small>
        </div>
        <span class="badge bg-light text-dark border">
          Oleh: <?= e($pengukuran['nama_petugas'] ?: 'Kader Posyandu') ?>
        </span>
      </div>

      <!-- Grid 4 Metrik Fisik -->
      <div class="row g-3 text-center mb-4">
        <div class="col-sm-3 col-6">
          <div class="p-3 rounded-3 bg-light border">
            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Berat Badan</small>
            <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($pengukuran['berat_badan'], 2) ?> <span class="fs-6 fw-normal text-muted">kg</span></div>
          </div>
        </div>
        <div class="col-sm-3 col-6">
          <div class="p-3 rounded-3 bg-light border">
            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Tinggi Badan</small>
            <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($pengukuran['tinggi_badan'], 1) ?> <span class="fs-6 fw-normal text-muted">cm</span></div>
          </div>
        </div>
        <div class="col-sm-3 col-6">
          <div class="p-3 rounded-3 bg-light border">
            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">IMT / BMI</small>
            <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($imt, 2) ?> <span class="fs-6 fw-normal text-muted">kg/m²</span></div>
          </div>
        </div>
        <div class="col-sm-3 col-6">
          <div class="p-3 rounded-3 bg-light border">
            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Lingkar Kepala</small>
            <div class="fs-4 fw-bold text-dark mt-1">
              <?= $pengukuran['lingkar_kepala'] ? number_format($pengukuran['lingkar_kepala'], 1) . ' <span class="fs-6 fw-normal text-muted">cm</span>' : '<span class="text-muted fs-6">—</span>' ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Detail Balita & Keluarga -->
      <h6 class="fw-bold text-dark mb-3"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Profil Balita & Keluarga</h6>
      <div class="table-responsive mb-0">
        <table class="table table-sm table-borderless small mb-0">
          <tbody>
            <tr>
              <td class="text-muted" style="width: 32%;">Nama Lengkap Balita</td>
              <td class="fw-bold text-dark">: <?= e($pengukuran['nama_anak']) ?></td>
            </tr>
            <tr>
              <td class="text-muted">Nomor Induk Kependudukan (NIK)</td>
              <td>: <?= e($pengukuran['nik']) ?></td>
            </tr>
            <tr>
              <td class="text-muted">Jenis Kelamin</td>
              <td>:
                <span class="badge <?= $pengukuran['jenis_kelamin'] === 'L' ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?>">
                  <?= $pengukuran['jenis_kelamin'] === 'L' ? 'Laki-laki (L)' : 'Perempuan (P)' ?>
                </span>
              </td>
            </tr>
            <tr>
              <td class="text-muted">Tanggal Lahir / Usia Kini</td>
              <td>: <?= date('d F Y', strtotime($pengukuran['tanggal_lahir'])) ?> (<?= e($usia_teks_kini) ?>)</td>
            </tr>
            <tr>
              <td class="text-muted">Posyandu Binaan</td>
              <td>: <span class="fw-semibold"><?= e($pengukuran['nama_posyandu'] ?: '—') ?></span></td>
            </tr>
            <tr>
              <td class="text-muted">Nama Ibu / Ayah</td>
              <td>: <?= e($pengukuran['nama_ibu'] ?: '—') ?> / <?= e($pengukuran['nama_ayah'] ?: '—') ?></td>
            </tr>
            <?php if (!empty($pengukuran['telepon'])): ?>
              <tr>
                <td class="text-muted">Nomor Telepon Orang Tua</td>
                <td>: <?= e($pengukuran['telepon']) ?></td>
              </tr>
            <?php endif; ?>
            <?php if (!empty($pengukuran['alamat_orangtua'])): ?>
              <tr>
                <td class="text-muted">Alamat Rumah</td>
                <td>: <?= e($pengukuran['alamat_orangtua']) ?></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Riwayat Pertumbuhan & Penimbangan (Tabel KMS Digital) -->
    <div class="panel">
      <div class="panel-header border-bottom pb-3 mb-3 d-flex align-items-center justify-content-between">
        <div>
          <h5 class="panel-title mb-0">Lembar Pemantauan Pertumbuhan (KMS Digital)</h5>
          <small class="text-muted">Riwayat penimbangan berkala balita di Posyandu</small>
        </div>
        <span class="badge bg-secondary-subtle text-secondary border">
          Total <?= count($riwayat_pengukuran) ?> Kunjungan
        </span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th scope="col" style="width: 5%;">No</th>
              <th scope="col">Tanggal Ukur</th>
              <th scope="col">Usia</th>
              <th scope="col">BB (kg)</th>
              <th scope="col">TB (cm)</th>
              <th scope="col">TB/U (Stunting)</th>
              <th scope="col">BB/U (Gizi)</th>
              <th scope="col">BB/TB (Wasting)</th>
              <th scope="col" class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($riwayat_pengukuran)): ?>
              <tr>
                <td colspan="9" class="text-center py-4 text-muted">Belum ada riwayat penimbangan lainnya.</td>
              </tr>
            <?php else: ?>
              <?php
              $prev_bb = null;
              foreach ($riwayat_pengukuran as $idx => $r):
                $is_current = (int)$r['id_pengukuran'] === (int)$pengukuran['id_pengukuran'];
                $r_usia_bln = Antropometri::hitungUsiaBulan($pengukuran['tanggal_lahir'], $r['tanggal_pengukuran']);

                // Trend BB Naik / Turun
                $trend_badge = '';
                if ($prev_bb !== null) {
                    $diff = (float)$r['berat_badan'] - $prev_bb;
                    if ($diff > 0) {
                        $trend_badge = '<span class="badge bg-success-subtle text-success p-1 ms-1" title="BB Naik +' . number_format($diff, 2) . ' kg"><i class="bi bi-arrow-up-right"></i> N</span>';
                    } elseif ($diff < 0) {
                        $trend_badge = '<span class="badge bg-danger-subtle text-danger p-1 ms-1" title="BB Turun ' . number_format($diff, 2) . ' kg"><i class="bi bi-arrow-down-right"></i> T</span>';
                    } else {
                        $trend_badge = '<span class="badge bg-secondary-subtle text-secondary p-1 ms-1" title="BB Tetap"><i class="bi bi-dash"></i> Tetap</span>';
                    }
                }
                $prev_bb = (float)$r['berat_badan'];
              ?>
                <tr class="<?= $is_current ? 'table-primary border-primary' : '' ?>">
                  <td><?= $idx + 1 ?></td>
                  <td>
                    <span class="fw-semibold"><?= date('d/m/Y', strtotime($r['tanggal_pengukuran'])) ?></span>
                    <?php if ($is_current): ?>
                      <span class="badge bg-primary ms-1" style="font-size: 0.65rem;">Saat Ini</span>
                    <?php endif; ?>
                  </td>
                  <td><?= (int)$r_usia_bln ?> bln</td>
                  <td>
                    <strong><?= number_format($r['berat_badan'], 2) ?></strong>
                    <?= $trend_badge ?>
                  </td>
                  <td><?= number_format($r['tinggi_badan'], 1) ?></td>
                  <td>
                    <span class="badge <?= Antropometri::getBadgeTBU($r['status_tb_u']) ?>">
                      <?= e($r['status_tb_u'] ?: '—') ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge <?= Antropometri::getBadgeBBU($r['status_bb_u']) ?>">
                      <?= e($r['status_bb_u'] ?: '—') ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge <?= Antropometri::getBadgeBBTB($r['status_bb_tb']) ?>">
                      <?= e($r['status_bb_tb'] ?: '—') ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <?php if (!$is_current): ?>
                      <a href="<?= base_url('modules/pengukuran/detail.php?id=' . (int)$r['id_pengukuran']) ?>"
                         class="btn btn-outline-secondary btn-sm py-0 px-2" title="Buka Detail">
                        <i class="bi bi-eye"></i>
                      </a>
                    <?php else: ?>
                      <span class="text-muted small">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="p-3 bg-light rounded-bottom border-top text-muted small d-flex align-items-center justify-content-between">
        <div>
          <strong>Keterangan KMS:</strong>
          <span class="badge bg-success-subtle text-success ms-1">N</span> = Naik (Sesuai grafik tumbuh kembang) |
          <span class="badge bg-danger-subtle text-danger ms-1">T</span> = Turun (Perlu konfirmasi asupan & riwayat sakit)
        </div>
      </div>
    </div>
  </div>

  <!-- Kolom Kanan: Evaluasi Status Gizi Standar WHO & Rekomendasi Klinis -->
  <div class="col-lg-5">
    <div class="panel sticky-top" style="top: 80px;">
      <div class="panel-header border-bottom pb-3 mb-3 d-flex align-items-center justify-content-between">
        <div>
          <h5 class="panel-title mb-0">Evaluasi Z-Score & Antropometri</h5>
          <small class="text-muted">Standar Antropometri Permenkes No. 2/2020</small>
        </div>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">WHO Standard</span>
      </div>

      <!-- 4 Evaluasi Z-Score Card -->
      <div class="d-flex flex-column gap-3 mb-4">
        <!-- 1. TB/U (Stunting) -->
        <div class="border rounded-3 p-3 bg-light">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">Indikator Utama Stunting</div>
              <h6 class="fw-bold mb-0">Tinggi Badan / Usia (TB/U)</h6>
            </div>
            <span class="badge fs-6 <?= Antropometri::getBadgeTBU($pengukuran['status_tb_u']) ?>">
              <?= e($pengukuran['status_tb_u'] ?: 'Normal') ?>
            </span>
          </div>
          <div class="d-flex justify-content-between align-items-center small">
            <span class="text-muted">Skor Deviasi Standar:</span>
            <span class="font-monospace fw-bold <?= $pengukuran['zscore_tb_u'] < -2 ? 'text-danger' : 'text-dark' ?>">
              Z: <?= ($pengukuran['zscore_tb_u'] > 0 ? '+' : '') . number_format($pengukuran['zscore_tb_u'], 2) ?> SD
            </span>
          </div>
          <div class="small text-muted mt-2 pt-2 border-top">
            <?php if (in_array($pengukuran['status_tb_u'], ['Pendek', 'Sangat Pendek'])): ?>
              <span class="text-danger fw-semibold"><i class="bi bi-exclamation-circle me-1"></i> Terindikasi Stunting:</span> Memerlukan intervensi gizi terintegrasi dan pemeriksaan dokter spesialis anak/puskesmas.
            <?php else: ?>
              <span class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i> Pertumbuhan Linear Normal:</span> Tinggi badan sesuai dengan kurva pertumbuhan anak seusianya.
            <?php endif; ?>
          </div>
        </div>

        <!-- 2. BB/U (Underweight) -->
        <div class="border rounded-3 p-3 bg-light">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">Indikator Berat Badan Kurang</div>
              <h6 class="fw-bold mb-0">Berat Badan / Usia (BB/U)</h6>
            </div>
            <span class="badge fs-6 <?= Antropometri::getBadgeBBU($pengukuran['status_bb_u']) ?>">
              <?= e($pengukuran['status_bb_u'] ?: 'Gizi Baik') ?>
            </span>
          </div>
          <div class="d-flex justify-content-between align-items-center small">
            <span class="text-muted">Skor Deviasi Standar:</span>
            <span class="font-monospace fw-bold <?= in_array($pengukuran['status_bb_u'], ['Berat Badan Sangat Kurang', 'Berat Badan Kurang']) ? 'text-warning' : 'text-dark' ?>">
              Z: <?= ($pengukuran['zscore_bb_u'] > 0 ? '+' : '') . number_format($pengukuran['zscore_bb_u'], 2) ?> SD
            </span>
          </div>
        </div>

        <!-- 3. BB/TB (Wasting) -->
        <div class="border rounded-3 p-3 bg-light">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">Indikator Gizi Akut / Wasting</div>
              <h6 class="fw-bold mb-0">BB / TB (BB/TB)</h6>
            </div>
            <span class="badge fs-6 <?= Antropometri::getBadgeBBTB($pengukuran['status_bb_tb']) ?>">
              <?= e($pengukuran['status_bb_tb'] ?: 'Gizi Baik') ?>
            </span>
          </div>
          <div class="d-flex justify-content-between align-items-center small">
            <span class="text-muted">Skor Deviasi Standar:</span>
            <span class="font-monospace fw-bold <?= in_array($pengukuran['status_bb_tb'], ['Gizi Buruk', 'Gizi Kurang']) ? 'text-danger' : 'text-dark' ?>">
              Z: <?= ($pengukuran['zscore_bb_tb'] > 0 ? '+' : '') . number_format($pengukuran['zscore_bb_tb'], 2) ?> SD
            </span>
          </div>
        </div>

        <!-- 4. IMT/U -->
        <div class="border rounded-3 p-3 bg-light">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">Indeks Massa Tubuh / Usia</div>
              <h6 class="fw-bold mb-0">IMT / Usia (IMT/U)</h6>
            </div>
            <span class="badge fs-6 <?= Antropometri::getBadgeBBTB($pengukuran['status_imt_u']) ?>">
              <?= e($pengukuran['status_imt_u'] ?: 'Gizi Baik') ?>
            </span>
          </div>
          <div class="d-flex justify-content-between align-items-center small">
            <span class="text-muted">Skor Deviasi Standar:</span>
            <span class="font-monospace fw-bold text-dark">
              Z: <?= ($pengukuran['zscore_imt_u'] > 0 ? '+' : '') . number_format($pengukuran['zscore_imt_u'], 2) ?> SD
            </span>
          </div>
        </div>
      </div>

      <!-- Kotak Rekomendasi & Tindakan Petugas -->
      <?php
      $status_risiko = $pengukuran['status_risiko'] ?? 'Normal';
      $box_theme     = 'bg-success-subtle text-success border-success-subtle';
      $badge_theme   = 'bg-success text-white';

      if ($status_risiko === 'Butuh Rujukan Segera') {
          $box_theme   = 'bg-danger-subtle text-danger border-danger-subtle';
          $badge_theme = 'bg-danger text-white';
      } elseif ($status_risiko === 'Berisiko') {
          $box_theme   = 'bg-warning-subtle text-warning border-warning-subtle';
          $badge_theme = 'bg-warning text-dark';
      }
      ?>

      <div class="border rounded-3 p-3 <?= $box_theme ?> mb-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="fw-bold mb-0"><i class="bi bi-shield-check me-2"></i>Status Risiko: <?= e($status_risiko) ?></h6>
          <span class="badge <?= $badge_theme ?> px-2 py-1"><?= e($status_risiko) ?></span>
        </div>
        <p class="small mb-2">
          <?php if ($status_risiko === 'Butuh Rujukan Segera'): ?>
            <strong>PERHATIAN NAKES / KADER:</strong> Balita ini menunjukkan indikasi gangguan gizi berat (Stunting / Gizi Buruk). Sangat direkomendasikan untuk segera dibuatkan surat pengantar rujukan ke Puskesmas.
          <?php elseif ($status_risiko === 'Berisiko'): ?>
            <strong>WASPADA:</strong> Balita mengalami risiko gizi kurang atau perawakan pendek. Perlu pemantauan intensif di Posyandu dan konseling pola asuh gizi kepada orang tua.
          <?php else: ?>
            <strong>KONDISI BAIK:</strong> Tumbuh kembang balita terpantau optimal sesuai standar kurva WHO. Pertahankan asupan gizi seimbang dan ASI/MPASI adekuat.
          <?php endif; ?>
        </p>

        <hr class="my-2 border-opacity-25">

        <h6 class="fw-bold small mb-1">Rekomendasi Tindak Lanjut:</h6>
        <ul class="mb-0 ps-3 small">
          <?php if ($status_risiko === 'Butuh Rujukan Segera'): ?>
            <li>Segera jadwalkan konsultasi dengan Tim Asuhan Gizi Puskesmas.</li>
            <li>Berikan PMT (Pemberian Makanan Tambahan) pemulihan kaya protein hewani.</li>
            <li>Periksa kemungkinan adanya penyakit infeksi penyerta (TBC balita, ISPA, diare berulang).</li>
          <?php elseif ($status_risiko === 'Berisiko'): ?>
            <li>Edukasi orang tua mengenai variasi MP-ASI dan kecukupan kalori.</li>
            <li>Lakukan penimbangan ulang 2 minggu hingga 1 bulan ke depan.</li>
            <li>Pastikan kepatuhan imunisasi dasar lengkap dan vitamin A.</li>
          <?php else: ?>
            <li>Pertahankan pola makan bergizi seimbang (Isi Piringku balita).</li>
            <li>Lanjutkan pemantauan rutin bulanan di Posyandu Binaan.</li>
            <li>Cukupi kebutuhan stimulasi psikomotorik dan kebersihan lingkungan.</li>
          <?php endif; ?>
        </ul>
      </div>

      <div class="d-grid">
        <a href="<?= base_url('modules/pengukuran/ekspor.php?format=print&id=' . (int)$pengukuran['id_pengukuran']) ?>"
           target="_blank"
           class="btn btn-outline-primary btn-sm">
          <i class="bi bi-printer me-1" aria-hidden="true"></i> Cetak Kartu Pemeriksaan
        </a>
      </div>
    </div>
  </div>
</div>

<?php
require_once ROOT_PATH . '/includes/footer.php';
?>
