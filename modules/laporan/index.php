<?php
/**
 * Modul Laporan — Rekap Laporan Bulanan Pemantauan Gizi
 * ==============================================================
 * Fitur:
 * 1. Tampilkan rekap laporan bulanan (Kehadiran & SKDN, Distribusi Status Gizi, Daftar Anak Berisiko).
 * 2. Filter laporan berdasarkan posyandu dan periode bulan.
 * 3. Export laporan ke format PDF (Cetak Dokumen Resmi).
 * 4. Export laporan ke format Excel (Spreadsheet CSV UTF-8 BOM).
 */

$current_slug = 'laporan-gizi';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';
require_once ROOT_PATH . '/src/Otorisasi.php';

// Cek hak akses menu laporan-gizi
$user_role_id      = (int)($_SESSION['role_id'] ?? 0);
$user_role_nama    = $_SESSION['user_role_nama'] ?? '';
$user_posyandu_id  = (int)($_SESSION['user_posyandu'] ?? 0);
$user_puskesmas_id = (int)($_SESSION['user_puskesmas'] ?? 0);
$user_wilayah_id   = (int)($_SESSION['user_wilayah'] ?? 0);

if (!bolehAkses($user_role_id, 'laporan-gizi') && $user_role_id !== Otorisasi::ADMIN_ROLE_ID && strcasecmp($user_role_nama, 'Admin') !== 0) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk melihat modul Laporan.');
    redirect(base_url('modules/dashboard/index.php'));
}

$page_title = 'Laporan & Rekapitulasi Gizi';

// ============================================================
// 1. Parameter Filter (Posyandu & Periode Bulan)
// ============================================================
$filter_posyandu = trim($_GET['posyandu'] ?? '');
$filter_bulan    = trim($_GET['bulan'] ?? '');

// Jika role adalah Kader, batasi ke posyandu tempat kader bertugas
if ($user_role_id === 1 && $user_posyandu_id > 0) {
    $filter_posyandu = (string)$user_posyandu_id;
}

// Ambil daftar pilihan Posyandu sesuai otorisasi wilayah pengguna
$posyandu_options = [];
if ($pdo !== null) {
    try {
        if ($user_role_id === 1 && $user_posyandu_id > 0) {
            $stmtPos = $pdo->prepare("SELECT p.id_posyandu, p.nama_posyandu, p.alamat, w.nama_wilayah FROM posyandu p LEFT JOIN wilayah w ON p.id_wilayah = w.id_wilayah WHERE p.id_posyandu = ?");
            $stmtPos->execute([$user_posyandu_id]);
            $posyandu_options = $stmtPos->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($user_role_id === 2 && $user_puskesmas_id > 0) {
            $stmtPos = $pdo->prepare("SELECT p.id_posyandu, p.nama_posyandu, p.alamat, w.nama_wilayah FROM posyandu p LEFT JOIN wilayah w ON p.id_wilayah = w.id_wilayah WHERE p.id_puskesmas = ? ORDER BY p.nama_posyandu ASC");
            $stmtPos->execute([$user_puskesmas_id]);
            $posyandu_options = $stmtPos->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($user_role_id === 3 && $user_wilayah_id > 0) {
            $stmtPos = $pdo->prepare("SELECT p.id_posyandu, p.nama_posyandu, p.alamat, w.nama_wilayah FROM posyandu p LEFT JOIN wilayah w ON p.id_wilayah = w.id_wilayah WHERE p.id_wilayah = ? ORDER BY p.nama_posyandu ASC");
            $stmtPos->execute([$user_wilayah_id]);
            $posyandu_options = $stmtPos->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmtPos = $pdo->query("SELECT p.id_posyandu, p.nama_posyandu, p.alamat, w.nama_wilayah FROM posyandu p LEFT JOIN wilayah w ON p.id_wilayah = w.id_wilayah ORDER BY p.nama_posyandu ASC");
            $posyandu_options = $stmtPos->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $posyandu_options = [];
    }
}

// Ambil daftar bulan yang tersedia dari database
$available_months = [];
if ($pdo !== null) {
    try {
        $stmtM = $pdo->query("SELECT DISTINCT DATE_FORMAT(tanggal_pengukuran, '%Y-%m') as bln FROM pengukuran ORDER BY bln DESC");
        $available_months = $stmtM->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {}
}

$current_month = date('Y-m');
if (!in_array($current_month, $available_months, true)) {
    array_unshift($available_months, $current_month);
}

// Default filter bulan ke bulan aktif atau bulan terbaru dengan data
if ($filter_bulan === '' || !preg_match('/^\d{4}-\d{2}$/', $filter_bulan)) {
    $filter_bulan = in_array($current_month, $available_months, true) ? $current_month : ($available_months[0] ?? $current_month);
}

// Helper formatting tanggal & bulan
if (!function_exists('formatBulanIndo')) {
    function formatBulanIndo(?string $ym_str): string {
        if (empty($ym_str)) return '-';
        $parts = explode('-', $ym_str);
        if (count($parts) !== 2) return e($ym_str);
        $bulanIndo = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        $m = (int)$parts[1];
        return ($bulanIndo[$m] ?? $parts[1]) . ' ' . $parts[0];
    }
}

if (!function_exists('hitungUsiaBulan')) {
    function hitungUsiaBulan(?string $tgl_lahir, ?string $tgl_hitung = null): int {
        if (empty($tgl_lahir)) return 0;
        try {
            $birth = new DateTime($tgl_lahir);
            $target = new DateTime($tgl_hitung ?: 'now');
            $diff = $birth->diff($target);
            return ($diff->y * 12) + $diff->m;
        } catch (Exception $e) {
            return 0;
        }
    }
}

// Info Posyandu terpilih
$selected_posyandu_info = null;
if ($filter_posyandu !== '') {
    foreach ($posyandu_options as $opt) {
        if ((string)$opt['id_posyandu'] === (string)$filter_posyandu) {
            $selected_posyandu_info = $opt;
            break;
        }
    }
}

// ============================================================
// 2. Query Rekap Kehadiran & Indikator SKDN
// ============================================================
$S = 0; // Sasaran Balita
$K = 0; // Memiliki KMS / Buku KIA
$D = 0; // Ditimbang pada bulan ini
$N = 0; // Naik berat badannya dibanding bulan sebelumnya
$T = 0; // Tidak naik / tetap / turun

$kehadiran_stats = [
    'hadir' => 0,
    'izin'  => 0,
    'sakit' => 0,
    'alpha' => 0
];

if ($pdo !== null) {
    try {
        // Query S (Total Sasaran Balita)
        $sqlS = "SELECT COUNT(*) FROM anak WHERE 1=1";
        $paramsS = [];
        if ($filter_posyandu !== '') {
            $sqlS .= " AND id_posyandu = ?";
            $paramsS[] = $filter_posyandu;
        }
        $stmtS = $pdo->prepare($sqlS);
        $stmtS->execute($paramsS);
        $S = (int)$stmtS->fetchColumn();
        $K = $S; // Setiap balita sasaran tercatat memiliki buku KIA/KMS

        // Query D (Balita Ditimbang pada bulan berjalan)
        $sqlD = "
            SELECT COUNT(DISTINCT a.id_anak) 
            FROM anak a 
            JOIN pengukuran p ON a.id_anak = p.id_anak 
            WHERE DATE_FORMAT(p.tanggal_pengukuran, '%Y-%m') = ?
        ";
        $paramsD = [$filter_bulan];
        if ($filter_posyandu !== '') {
            $sqlD .= " AND a.id_posyandu = ?";
            $paramsD[] = $filter_posyandu;
        }
        $stmtD = $pdo->prepare($sqlD);
        $stmtD->execute($paramsD);
        $D = (int)$stmtD->fetchColumn();

        // Query N (Balita yang Naik Berat Badannya)
        $sqlN = "
            SELECT COUNT(DISTINCT curr.id_anak)
            FROM pengukuran curr
            JOIN anak a ON curr.id_anak = a.id_anak
            LEFT JOIN pengukuran prev ON prev.id_anak = curr.id_anak 
                 AND prev.id_pengukuran = (
                     SELECT p_sub.id_pengukuran 
                     FROM pengukuran p_sub 
                     WHERE p_sub.id_anak = curr.id_anak 
                       AND p_sub.tanggal_pengukuran < curr.tanggal_pengukuran 
                     ORDER BY p_sub.tanggal_pengukuran DESC 
                     LIMIT 1
                 )
            WHERE DATE_FORMAT(curr.tanggal_pengukuran, '%Y-%m') = ?
              AND curr.berat_badan > IFNULL(prev.berat_badan, 0)
        ";
        $paramsN = [$filter_bulan];
        if ($filter_posyandu !== '') {
            $sqlN .= " AND a.id_posyandu = ?";
            $paramsN[] = $filter_posyandu;
        }
        $stmtN = $pdo->prepare($sqlN);
        $stmtN->execute($paramsN);
        $N = (int)$stmtN->fetchColumn();

        $T = max(0, $D - $N);

        // Rekap Kehadiran dari Jadwal Posyandu
        $sqlK = "
            SELECT 
                SUM(CASE WHEN k.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) as total_hadir,
                SUM(CASE WHEN k.status_kehadiran = 'Izin' THEN 1 ELSE 0 END) as total_izin,
                SUM(CASE WHEN k.status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) as total_sakit,
                SUM(CASE WHEN k.status_kehadiran = 'Alpha' THEN 1 ELSE 0 END) as total_alpha
            FROM kehadiran k
            JOIN jadwal_posyandu j ON k.id_jadwal = j.id_jadwal
            WHERE DATE_FORMAT(j.tanggal, '%Y-%m') = ?
        ";
        $paramsK = [$filter_bulan];
        if ($filter_posyandu !== '') {
            $sqlK .= " AND j.id_posyandu = ?";
            $paramsK[] = $filter_posyandu;
        }
        $stmtK = $pdo->prepare($sqlK);
        $stmtK->execute($paramsK);
        $resK = $stmtK->fetch(PDO::FETCH_ASSOC);
        if ($resK) {
            $kehadiran_stats = [
                'hadir' => (int)$resK['total_hadir'],
                'izin'  => (int)$resK['total_izin'],
                'sakit' => (int)$resK['total_sakit'],
                'alpha' => (int)$resK['total_alpha']
            ];
        }
    } catch (PDOException $e) {}
}

$persen_DS = $S > 0 ? round(($D / $S) * 100, 1) : 0;
$persen_ND = $D > 0 ? round(($N / $D) * 100, 1) : 0;

// ============================================================
// 3. Query Distribusi Status Gizi (BB/U, TB/U, BB/TB)
// ============================================================
$distribusi_gizi = [
    'total_pengukuran'  => 0,
    // BB/U
    'bbu_baik'          => 0,
    'bbu_kurang'        => 0,
    'bbu_buruk'         => 0,
    'bbu_lebih'         => 0,
    // TB/U (Stunting)
    'tbu_normal'        => 0,
    'tbu_pendek'        => 0,
    'tbu_sangat_pendek' => 0,
    'tbu_tinggi'        => 0,
    // BB/TB (Wasting)
    'bbtb_normal'       => 0,
    'bbtb_kurang'       => 0,
    'bbtb_buruk'        => 0,
    'bbtb_lebih'        => 0
];

if ($pdo !== null) {
    try {
        $sqlG = "
            SELECT 
                COUNT(*) as total_pengukuran,
                SUM(CASE WHEN sg.status_bb_u = 'Gizi Baik' THEN 1 ELSE 0 END) as bbu_baik,
                SUM(CASE WHEN sg.status_bb_u = 'Gizi Kurang' THEN 1 ELSE 0 END) as bbu_kurang,
                SUM(CASE WHEN sg.status_bb_u = 'Gizi Buruk' THEN 1 ELSE 0 END) as bbu_buruk,
                SUM(CASE WHEN sg.status_bb_u LIKE '%Lebih%' THEN 1 ELSE 0 END) as bbu_lebih,
                
                SUM(CASE WHEN sg.status_tb_u = 'Normal' THEN 1 ELSE 0 END) as tbu_normal,
                SUM(CASE WHEN sg.status_tb_u IN ('Pendek', 'Stunting') THEN 1 ELSE 0 END) as tbu_pendek,
                SUM(CASE WHEN sg.status_tb_u = 'Sangat Pendek' THEN 1 ELSE 0 END) as tbu_sangat_pendek,
                SUM(CASE WHEN sg.status_tb_u = 'Tinggi' THEN 1 ELSE 0 END) as tbu_tinggi,
                
                SUM(CASE WHEN sg.status_bb_tb = 'Gizi Baik' OR sg.status_bb_tb = 'Normal' THEN 1 ELSE 0 END) as bbtb_normal,
                SUM(CASE WHEN sg.status_bb_tb = 'Gizi Kurang' OR sg.status_bb_tb = 'Kurus' THEN 1 ELSE 0 END) as bbtb_kurang,
                SUM(CASE WHEN sg.status_bb_tb = 'Gizi Buruk' OR sg.status_bb_tb = 'Sangat Kurus' THEN 1 ELSE 0 END) as bbtb_buruk,
                SUM(CASE WHEN sg.status_bb_tb LIKE '%Lebih%' OR sg.status_bb_tb LIKE '%Obesitas%' THEN 1 ELSE 0 END) as bbtb_lebih
            FROM pengukuran p
            JOIN anak a ON p.id_anak = a.id_anak
            JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
            WHERE DATE_FORMAT(p.tanggal_pengukuran, '%Y-%m') = ?
        ";
        $paramsG = [$filter_bulan];
        if ($filter_posyandu !== '') {
            $sqlG .= " AND a.id_posyandu = ?";
            $paramsG[] = $filter_posyandu;
        }
        $stmtG = $pdo->prepare($sqlG);
        $stmtG->execute($paramsG);
        $resG = $stmtG->fetch(PDO::FETCH_ASSOC);
        if ($resG) {
            $distribusi_gizi = array_map('intval', $resG);
        }
    } catch (PDOException $e) {}
}

$total_stunting = $distribusi_gizi['tbu_pendek'] + $distribusi_gizi['tbu_sangat_pendek'];
$total_wasting  = $distribusi_gizi['bbtb_kurang'] + $distribusi_gizi['bbtb_buruk'];
$total_underweight = $distribusi_gizi['bbu_kurang'] + $distribusi_gizi['bbu_buruk'];

// ============================================================
// 4. Query Daftar Anak Berisiko per Posyandu
// ============================================================
$anak_berisiko_list = [];

if ($pdo !== null) {
    try {
        $sqlR = "
            SELECT 
                a.id_anak, a.nama as nama_anak, a.nik, a.tanggal_lahir, a.jenis_kelamin, a.alamat as alamat_anak,
                pos.nama_posyandu,
                ot.nama_ibu, ot.no_telepon, ot.alamat as alamat_ot,
                p.id_pengukuran, p.tanggal_pengukuran, p.berat_badan, p.tinggi_badan, p.lingkar_kepala,
                sg.status_bb_u, sg.status_tb_u, sg.status_bb_tb, sg.status_imt_u, sg.status_risiko
            FROM pengukuran p
            JOIN anak a ON p.id_anak = a.id_anak
            JOIN posyandu pos ON a.id_posyandu = pos.id_posyandu
            LEFT JOIN orangtua ot ON a.id_orangtua = ot.id_orangtua
            JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
            WHERE DATE_FORMAT(p.tanggal_pengukuran, '%Y-%m') = ?
              AND (
                  sg.status_tb_u IN ('Pendek', 'Sangat Pendek', 'Stunting')
                  OR sg.status_bb_u IN ('Gizi Kurang', 'Gizi Buruk')
                  OR sg.status_bb_tb IN ('Gizi Kurang', 'Gizi Buruk', 'Kurus', 'Sangat Kurus')
                  OR sg.status_risiko != 'Normal'
              )
        ";
        $paramsR = [$filter_bulan];
        if ($filter_posyandu !== '') {
            $sqlR .= " AND a.id_posyandu = ?";
            $paramsR[] = $filter_posyandu;
        }
        $sqlR .= " ORDER BY (sg.status_tb_u IN ('Sangat Pendek', 'Pendek')) DESC, p.tanggal_pengukuran DESC";

        $stmtR = $pdo->prepare($sqlR);
        $stmtR->execute($paramsR);
        $anak_berisiko_list = $stmtR->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

// URL Parameter untuk Tombol Ekspor
$export_query = http_build_query([
    'posyandu' => $filter_posyandu,
    'bulan'    => $filter_bulan
]);

include ROOT_PATH . '/includes/header.php';
?>

<!-- ============================================================ -->
<!-- HEADER HALAMAN & TOMBOL EKSPOR                               -->
<!-- ============================================================ -->
<div class="page-heading mb-3">
  <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
    <div class="page-heading-copy">
      <span class="page-icon"><i class="bi bi-bar-chart-line-fill text-primary" aria-hidden="true"></i></span>
      <div>
        <p class="eyebrow mb-1">Modul Pelaporan</p>
        <h1 class="h3 mb-1">Laporan Rekapitulasi Gizi &amp; Kehadiran</h1>
        <p class="text-muted mb-0">Rekap kehadiran penimbangan, distribusi status gizi balita, dan daftar anak berisiko.</p>
      </div>
    </div>

    <!-- Tombol Ekspor PDF & Excel -->
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <a href="<?= base_url('modules/laporan/ekspor.php?format=print&' . $export_query) ?>"
         target="_blank" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
        <i class="bi bi-file-earmark-pdf-fill"></i> Cetak / Export PDF
      </a>
      <a href="<?= base_url('modules/laporan/ekspor.php?format=excel&' . $export_query) ?>"
         class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
        <i class="bi bi-file-earmark-spreadsheet-fill"></i> Export Excel
      </a>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- FORM FILTER LAPORAN (POSYANDU & PERIODE BULAN)               -->
<!-- ============================================================ -->
<section class="panel mb-3 p-3 bg-white border rounded-3 shadow-sm">
  <form method="GET" action="" class="row g-2 align-items-end">
    <div class="col-12 col-md-5 col-lg-4">
      <label for="filterPosyandu" class="form-label small fw-semibold text-muted mb-1">
        <i class="bi bi-hospital me-1"></i>Posyandu:
      </label>
      <select name="posyandu" id="filterPosyandu" class="form-select form-select-sm" <?= ($user_role_id === 1 && $user_posyandu_id > 0) ? 'disabled' : '' ?>>
        <?php if ($user_role_id !== 1): ?>
          <option value="">Semua Posyandu (Wilayah Terbina)</option>
        <?php endif; ?>
        <?php foreach ($posyandu_options as $pos): ?>
          <option value="<?= (int)$pos['id_posyandu'] ?>" <?= ((string)$pos['id_posyandu'] === (string)$filter_posyandu) ? 'selected' : '' ?>>
            <?= e($pos['nama_posyandu']) ?> (<?= e($pos['nama_wilayah'] ?? '-') ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <?php if ($user_role_id === 1 && $user_posyandu_id > 0): ?>
        <input type="hidden" name="posyandu" value="<?= (int)$user_posyandu_id ?>">
      <?php endif; ?>
    </div>

    <div class="col-12 col-md-4 col-lg-3">
      <label for="filterBulan" class="form-label small fw-semibold text-muted mb-1">
        <i class="bi bi-calendar3 me-1"></i>Periode Bulan:
      </label>
      <select name="bulan" id="filterBulan" class="form-select form-select-sm">
        <?php foreach ($available_months as $bln): ?>
          <option value="<?= e($bln) ?>" <?= ($filter_bulan === $bln) ? 'selected' : '' ?>>
            <?= formatBulanIndo($bln) ?><?= ($bln === $current_month) ? ' (Bulan Ini)' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-md-3 col-lg-3 d-flex gap-2">
      <button type="submit" class="btn btn-primary btn-sm flex-fill">
        <i class="bi bi-funnel me-1"></i>Terapkan Filter
      </button>
      <?php if ($filter_posyandu !== '' || $filter_bulan !== $current_month): ?>
        <a href="<?= base_url('modules/laporan/index.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset filter">
          <i class="bi bi-arrow-counterclockwise"></i>
        </a>
      <?php endif; ?>
    </div>
  </form>

  <div class="mt-2 pt-2 border-top d-flex align-items-center justify-content-between flex-wrap gap-2 text-muted small">
    <div>
      <i class="bi bi-info-circle me-1 text-primary"></i>
      Filter Aktif: <strong><?= formatBulanIndo($filter_bulan) ?></strong> &middot; 
      Posyandu: <strong><?= $selected_posyandu_info ? e($selected_posyandu_info['nama_posyandu']) : 'Seluruh Posyandu Binaan' ?></strong>
    </div>
    <div>
      Total Data Terukur: <strong><?= number_format($distribusi_gizi['total_pengukuran']) ?> Balita</strong>
    </div>
  </div>
</section>

<!-- ============================================================ -->
<!-- 1. REKAP KEHADIRAN & INDIKATOR SKDN                          -->
<!-- ============================================================ -->
<div class="row g-3 mb-3">
  <!-- Kartu S (Sasaran) -->
  <div class="col-6 col-md-3">
    <div class="p-3 border rounded-3 bg-white h-100 shadow-sm border-start border-primary border-3">
      <span class="small text-muted text-uppercase fw-bold d-block">Sasaran Balita (S)</span>
      <h2 class="h3 fw-bold text-dark mb-0 mt-1"><?= number_format($S) ?></h2>
      <small class="text-muted">Total anak terdata</small>
    </div>
  </div>

  <!-- Kartu D (Ditimbang) -->
  <div class="col-6 col-md-3">
    <div class="p-3 border rounded-3 bg-white h-100 shadow-sm border-start border-success border-3">
      <span class="small text-muted text-uppercase fw-bold d-block">Ditimbang (D)</span>
      <h2 class="h3 fw-bold text-success mb-0 mt-1"><?= number_format($D) ?></h2>
      <small class="text-success fw-semibold">Partisipasi (D/S): <?= $persen_DS ?>%</small>
    </div>
  </div>

  <!-- Kartu N (Naik BB) -->
  <div class="col-6 col-md-3">
    <div class="p-3 border rounded-3 bg-white h-100 shadow-sm border-start border-info border-3">
      <span class="small text-muted text-uppercase fw-bold d-block">Naik BB (N)</span>
      <h2 class="h3 fw-bold text-info mb-0 mt-1"><?= number_format($N) ?></h2>
      <small class="text-muted">Kinerja (N/D): <?= $persen_ND ?>%</small>
    </div>
  </div>

  <!-- Kartu T (Tidak Naik) -->
  <div class="col-6 col-md-3">
    <div class="p-3 border rounded-3 bg-white h-100 shadow-sm border-start border-warning border-3">
      <span class="small text-muted text-uppercase fw-bold d-block">Tidak Naik / Tetap (T)</span>
      <h2 class="h3 fw-bold text-warning mb-0 mt-1"><?= number_format($T) ?></h2>
      <small class="text-muted">Perlu edukasi gizi</small>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- 2. REKAPITULASI DISTRIBUSI STATUS GIZI                       -->
<!-- ============================================================ -->
<div class="row g-3 mb-3">
  <!-- Indeks TB/U (Stunting) -->
  <div class="col-12 col-md-4">
    <div class="panel h-100 p-3 bg-white border rounded-3 shadow-sm">
      <div class="panel-header mb-2 pb-2 border-bottom d-flex justify-content-between align-items-center">
        <h3 class="h6 fw-bold mb-0 text-dark">
          <i class="bi bi-rulers text-danger me-1"></i>Indeks TB/U (Stunting)
        </h3>
        <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?= $total_stunting ?> Kasus</span>
      </div>
      <ul class="list-group list-group-flush small">
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
          <span>Normal</span>
          <span class="badge bg-success-subtle text-success border fw-bold"><?= $distribusi_gizi['tbu_normal'] ?> Anak</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
          <span>Pendek (Stunted)</span>
          <span class="badge bg-warning-subtle text-warning-emphasis border fw-bold"><?= $distribusi_gizi['tbu_pendek'] ?> Anak</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
          <span>Sangat Pendek (Severely Stunted)</span>
          <span class="badge bg-danger text-white fw-bold"><?= $distribusi_gizi['tbu_sangat_pendek'] ?> Anak</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
          <span>Tinggi</span>
          <span class="badge bg-light text-secondary border"><?= $distribusi_gizi['tbu_tinggi'] ?> Anak</span>
        </li>
      </ul>
      <div class="mt-2 pt-2 border-top small text-muted d-flex justify-content-between">
        <span>Prevalensi Stunting:</span>
        <strong class="text-danger"><?= $D > 0 ? round(($total_stunting / $D) * 100, 1) : 0 ?>%</strong>
      </div>
    </div>
  </div>

  <!-- Indeks BB/U (Underweight) -->
  <div class="col-12 col-md-4">
    <div class="panel h-100 p-3 bg-white border rounded-3 shadow-sm">
      <div class="panel-header mb-2 pb-2 border-bottom d-flex justify-content-between align-items-center">
        <h3 class="h6 fw-bold mb-0 text-dark">
          <i class="bi bi-speedometer text-warning me-1"></i>Indeks BB/U (Underweight)
        </h3>
        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><?= $total_underweight ?> Kasus</span>
      </div>
      <ul class="list-group list-group-flush small">
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
          <span>Gizi Baik (Normal)</span>
          <span class="badge bg-success-subtle text-success border fw-bold"><?= $distribusi_gizi['bbu_baik'] ?> Anak</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
          <span>Gizi Kurang</span>
          <span class="badge bg-warning-subtle text-warning-emphasis border fw-bold"><?= $distribusi_gizi['bbu_kurang'] ?> Anak</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
          <span>Gizi Buruk</span>
          <span class="badge bg-danger text-white fw-bold"><?= $distribusi_gizi['bbu_buruk'] ?> Anak</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
          <span>Risiko Gizi Lebih</span>
          <span class="badge bg-info-subtle text-info border"><?= $distribusi_gizi['bbu_lebih'] ?> Anak</span>
        </li>
      </ul>
      <div class="mt-2 pt-2 border-top small text-muted d-flex justify-content-between">
        <span>Kurang / Buruk:</span>
        <strong class="text-warning"><?= $D > 0 ? round(($total_underweight / $D) * 100, 1) : 0 ?>%</strong>
      </div>
    </div>
  </div>

  <!-- Indeks BB/TB (Wasting) -->
  <div class="col-12 col-md-4">
    <div class="panel h-100 p-3 bg-white border rounded-3 shadow-sm">
      <div class="panel-header mb-2 pb-2 border-bottom d-flex justify-content-between align-items-center">
        <h3 class="h6 fw-bold mb-0 text-dark">
          <i class="bi bi-person-badge text-primary me-1"></i>Indeks BB/TB (Wasting)
        </h3>
        <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?= $total_wasting ?> Kasus</span>
      </div>
      <ul class="list-group list-group-flush small">
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
          <span>Gizi Baik (Normal)</span>
          <span class="badge bg-success-subtle text-success border fw-bold"><?= $distribusi_gizi['bbtb_normal'] ?> Anak</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
          <span>Gizi Kurang (Kurus / Wasted)</span>
          <span class="badge bg-warning-subtle text-warning-emphasis border fw-bold"><?= $distribusi_gizi['bbtb_kurang'] ?> Anak</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
          <span>Gizi Buruk (Sangat Kurus)</span>
          <span class="badge bg-danger text-white fw-bold"><?= $distribusi_gizi['bbtb_buruk'] ?> Anak</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
          <span>Berisiko Lebih / Obesitas</span>
          <span class="badge bg-info-subtle text-info border"><?= $distribusi_gizi['bbtb_lebih'] ?> Anak</span>
        </li>
      </ul>
      <div class="mt-2 pt-2 border-top small text-muted d-flex justify-content-between">
        <span>Prevalensi Wasting:</span>
        <strong class="text-danger"><?= $D > 0 ? round(($total_wasting / $D) * 100, 1) : 0 ?>%</strong>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- 3. DAFTAR ANAK BERISIKO PER POSYANDU                         -->
<!-- ============================================================ -->
<section class="panel bg-white border rounded-3 shadow-sm mb-4">
  <div class="panel-header p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h2 class="h5 mb-0 section-title text-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>Daftar Balita Berisiko Gizi &amp; Stunting
      </h2>
      <p class="text-muted small mb-0">Daftar anak yang memerlukan pemantauan intensif, intervensi PMT, atau rujukan medis (Periode: <?= formatBulanIndo($filter_bulan) ?>)</p>
    </div>
    <span class="badge bg-danger px-2 py-1 fs-6"><?= count($anak_berisiko_list) ?> Anak Berisiko</span>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle small mb-0">
      <thead class="table-light">
        <tr>
          <th>Nama Balita</th>
          <th>Usia</th>
          <th>Posyandu</th>
          <th>Orang Tua &amp; Kontak</th>
          <th>BB / TB</th>
          <th>Status TB/U (Stunting)</th>
          <th>Status BB/U</th>
          <th>Status BB/TB</th>
          <th>Kategori Risiko</th>
          <th class="text-center">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($anak_berisiko_list)): ?>
          <tr>
            <td colspan="10" class="text-center py-5 text-muted">
              <i class="bi bi-shield-check text-success fs-1 d-block mb-2"></i>
              Tidak ada balita yang teridentifikasi berisiko gizi pada periode dan filter posyandu yang dipilih.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($anak_berisiko_list as $anak): 
            $usia = hitungUsiaBulan($anak['tanggal_lahir'], $anak['tanggal_pengukuran']);
            $is_stunting = in_array($anak['status_tb_u'], ['Pendek', 'Sangat Pendek', 'Stunting']);
          ?>
            <tr class="<?= $is_stunting ? 'table-danger-subtle' : '' ?>">
              <td>
                <div class="fw-bold text-dark"><?= e($anak['nama_anak']) ?></div>
                <small class="text-muted">NIK: <?= e($anak['nik'] ?: '-') ?></small>
              </td>
              <td>
                <span class="badge bg-light text-dark border"><?= $usia ?> Bln</span>
                <small class="text-muted d-block"><?= ($anak['jenis_kelamin'] === 'L') ? 'Laki-laki' : 'Perempuan' ?></small>
              </td>
              <td>
                <span class="badge bg-light text-primary border"><?= e($anak['nama_posyandu']) ?></span>
              </td>
              <td>
                <div>Ibu: <strong><?= e($anak['nama_ibu'] ?: '-') ?></strong></div>
                <?php if (!empty($anak['no_telepon'])): ?>
                  <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $anak['no_telepon']) ?>" target="_blank" class="text-success small text-decoration-none">
                    <i class="bi bi-whatsapp me-1"></i><?= e($anak['no_telepon']) ?>
                  </a>
                <?php else: ?>
                  <small class="text-muted">-</small>
                <?php endif; ?>
              </td>
              <td>
                <div><strong><?= number_format((float)$anak['berat_badan'], 1) ?> kg</strong></div>
                <div><?= number_format((float)$anak['tinggi_badan'], 1) ?> cm</div>
              </td>
              <td>
                <span class="badge <?= $is_stunting ? 'bg-danger text-white' : 'bg-success-subtle text-success border' ?>">
                  <?= e($anak['status_tb_u']) ?>
                </span>
              </td>
              <td>
                <span class="badge <?= in_array($anak['status_bb_u'], ['Gizi Kurang', 'Gizi Buruk']) ? 'bg-warning text-dark' : 'bg-light text-dark border' ?>">
                  <?= e($anak['status_bb_u']) ?>
                </span>
              </td>
              <td>
                <span class="badge <?= in_array($anak['status_bb_tb'], ['Gizi Kurang', 'Gizi Buruk', 'Kurus', 'Sangat Kurus']) ? 'bg-danger-subtle text-danger border' : 'bg-light text-dark border' ?>">
                  <?= e($anak['status_bb_tb']) ?>
                </span>
              </td>
              <td>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                  <i class="bi bi-exclamation-circle me-1"></i><?= e($anak['status_risiko'] ?: 'Perlu Perhatian') ?>
                </span>
              </td>
              <td class="text-center text-nowrap">
                <a href="<?= base_url('modules/pengukuran/detail.php?id=' . (int)$anak['id_pengukuran']) ?>"
                   class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.78rem;" title="Lihat Detail Pengukuran">
                  <i class="bi bi-eye me-1"></i>Detail
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
