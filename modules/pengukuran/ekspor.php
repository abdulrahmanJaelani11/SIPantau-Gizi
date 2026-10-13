<?php
/**
 * Modul Pengukuran Gizi — Ekspor Data & Cetak Laporan
 * ==============================================================
 * Mengekspor data pengukuran ke format CSV (kompatibel Excel dengan UTF-8 BOM)
 * atau tampilan Cetak / Print PDF (Lembar Pengukuran Balita / Rekapitulasi).
 */

$current_slug = 'pengukuran';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';
require_once ROOT_PATH . '/src/Antropometri.php';

// Pastikan memiliki hak akses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'pengukuran')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengekspor data pengukuran.');
    redirect(base_url('modules/pengukuran/index.php'));
}

// Parameter Filter & Format
$format          = strtolower(trim($_GET['format'] ?? 'csv'));
$id_pengukuran   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$search          = trim($_GET['search'] ?? '');
$filter_posyandu = trim($_GET['posyandu'] ?? '');
$filter_stunting = trim($_GET['stunting'] ?? '');
$filter_risiko   = trim($_GET['risiko'] ?? '');
$filter_bulan    = trim($_GET['bulan'] ?? '');

// ============================================================
// MODE 1: Cetak Lembar Pemeriksaan Balita Tunggal (Single Print)
// ============================================================
if ($format === 'print' && $id_pengukuran) {
    try {
        $stmtSingle = $pdo->prepare("
            SELECT 
                p.id_pengukuran,
                p.id_anak,
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
        $stmtSingle->execute([$id_pengukuran]);
        $row = $stmtSingle->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            die('Data pengukuran tidak ditemukan.');
        }

        // Ambil riwayat penimbangan
        $stmtRiwayat = $pdo->prepare("
            SELECT p.tanggal_pengukuran, p.berat_badan, p.tinggi_badan,
                   sg.status_tb_u, sg.status_bb_u, sg.status_bb_tb, sg.status_risiko
            FROM pengukuran p
            LEFT JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
            WHERE p.id_anak = ?
            ORDER BY p.tanggal_pengukuran ASC
        ");
        $stmtRiwayat->execute([$row['id_anak']]);
        $history = $stmtRiwayat->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        die('Gagal memuat data: ' . $e->getMessage());
    }

    $usia_bln = Antropometri::hitungUsiaBulan($row['tanggal_lahir'], $row['tanggal_pengukuran']);
    $usia_str = Antropometri::formatUsia($usia_bln);
    $tb_m     = (float)$row['tinggi_badan'] / 100;
    $imt      = $tb_m > 0 ? (float)$row['berat_badan'] / ($tb_m * $tb_m) : 0;
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Lembar KMS & Status Gizi — <?= e($row['nama_anak']) ?></title>
      <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
      <link rel="stylesheet" href="<?= asset('vendors/bootstrap-icons/bootstrap-icons.css') ?>">
      <style>
        body { font-family: "Segoe UI", Arial, sans-serif; color: #1e293b; background: #fff; }
        .print-header { border-bottom: 2px solid #0f766e; padding-bottom: 12px; margin-bottom: 20px; }
        .table th { background-color: #f1f5f9 !important; font-weight: 600; }
        .metric-box { border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; background: #f8fafc; }
        @media print {
          .no-print { display: none !important; }
          body { padding: 0; }
        }
      </style>
    </head>
    <body class="p-4">
      <div class="container-fluid">
        <!-- Toolbar Kontrol Cetak -->
        <div class="no-print d-flex justify-content-between align-items-center mb-4 p-3 bg-light border rounded-3">
          <div>
            <strong>Pratinjau Lembar Pemeriksaan Balita</strong>
            <span class="text-muted d-block small">Dokumen siap cetak atau disimpan dalam format PDF.</span>
          </div>
          <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-sm">
              <i class="bi bi-printer me-1"></i> Cetak / Simpan PDF
            </button>
            <button onclick="window.close()" class="btn btn-secondary btn-sm">
              <i class="bi bi-x-lg me-1"></i> Tutup
            </button>
          </div>
        </div>

        <!-- Kop Surat Dokumen -->
        <div class="print-header d-flex justify-content-between align-items-end">
          <div>
            <h4 class="fw-bold text-success mb-1"><?= e(APP_NAME) ?></h4>
            <p class="text-muted mb-0 small">Sistem Informasi Pemantauan Tumbuh Kembang & Gizi Balita Terintegrasi</p>
            <p class="text-muted mb-0 small">Standar Baku Antropometri WHO & Permenkes RI No. 2 Tahun 2020</p>
          </div>
          <div class="text-end small text-muted">
            <div><strong><?= e($row['nama_posyandu'] ?: 'Posyandu') ?></strong></div>
            <div>Tanggal Cetak: <?= date('d/m/Y H:i') ?></div>
          </div>
        </div>

        <h5 class="text-center fw-bold text-uppercase mb-4">LEMBAR HASIL PENIMBANGAN & EVALUASI GIZI BALITA</h5>

        <!-- Identitas Balita -->
        <div class="row g-3 mb-4">
          <div class="col-6">
            <table class="table table-sm table-borderless small mb-0">
              <tr><td class="text-muted" style="width: 35%;">Nama Balita</td><td class="fw-bold">: <?= e($row['nama_anak']) ?></td></tr>
              <tr><td class="text-muted">NIK</td><td>: <?= e($row['nik']) ?></td></tr>
              <tr><td class="text-muted">Jenis Kelamin</td><td>: <?= $row['jenis_kelamin'] === 'L' ? 'Laki-laki (L)' : 'Perempuan (P)' ?></td></tr>
              <tr><td class="text-muted">Tanggal Lahir</td><td>: <?= date('d/m/Y', strtotime($row['tanggal_lahir'])) ?></td></tr>
            </table>
          </div>
          <div class="col-6">
            <table class="table table-sm table-borderless small mb-0">
              <tr><td class="text-muted" style="width: 35%;">Posyandu Binaan</td><td>: <?= e($row['nama_posyandu'] ?: '—') ?></td></tr>
              <tr><td class="text-muted">Nama Ibu / Ayah</td><td>: <?= e($row['nama_ibu'] ?: '—') ?> / <?= e($row['nama_ayah'] ?: '—') ?></td></tr>
              <tr><td class="text-muted">Tanggal Ukur</td><td class="fw-bold">: <?= date('d/m/Y', strtotime($row['tanggal_pengukuran'])) ?></td></tr>
              <tr><td class="text-muted">Usia saat Ukur</td><td>: <strong><?= e($usia_str) ?></strong></td></tr>
            </table>
          </div>
        </div>

        <!-- Hasil Pengukuran Fisik -->
        <h6 class="fw-bold mb-2">1. Parameter Fisik Antropometri</h6>
        <div class="row g-2 mb-4 text-center">
          <div class="col-3">
            <div class="metric-box">
              <small class="text-muted d-block">Berat Badan</small>
              <strong class="fs-5"><?= number_format($row['berat_badan'], 2) ?> kg</strong>
            </div>
          </div>
          <div class="col-3">
            <div class="metric-box">
              <small class="text-muted d-block">Tinggi / Panjang</small>
              <strong class="fs-5"><?= number_format($row['tinggi_badan'], 1) ?> cm</strong>
            </div>
          </div>
          <div class="col-3">
            <div class="metric-box">
              <small class="text-muted d-block">Indeks Massa Tubuh</small>
              <strong class="fs-5"><?= number_format($imt, 2) ?> kg/m²</strong>
            </div>
          </div>
          <div class="col-3">
            <div class="metric-box">
              <small class="text-muted d-block">Lingkar Kepala</small>
              <strong class="fs-5"><?= $row['lingkar_kepala'] ? number_format($row['lingkar_kepala'], 1) . ' cm' : '—' ?></strong>
            </div>
          </div>
        </div>

        <!-- Tabel Z-Score WHO -->
        <h6 class="fw-bold mb-2">2. Evaluasi Indikator Standar Pertumbuhan Anak (WHO)</h6>
        <table class="table table-bordered table-sm small mb-4">
          <thead>
            <tr>
              <th>Indikator Pertumbuhan</th>
              <th class="text-center">Z-Score (SD)</th>
              <th class="text-center">Kategori / Status Gizi</th>
              <th>Ambang Batas Standar</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Tinggi Badan menurut Usia (TB/U)</strong><br><small class="text-muted">Deteksi Dini Stunting / Kerdil</small></td>
              <td class="text-center font-monospace fw-bold"><?= ($row['zscore_tb_u'] > 0 ? '+' : '') . number_format($row['zscore_tb_u'], 2) ?> SD</td>
              <td class="text-center fw-bold"><?= e($row['status_tb_u']) ?></td>
              <td>Normal: -2 SD s/d +3 SD</td>
            </tr>
            <tr>
              <td><strong>Berat Badan menurut Usia (BB/U)</strong><br><small class="text-muted">Deteksi Underweight / Berat Badan Kurang</small></td>
              <td class="text-center font-monospace fw-bold"><?= ($row['zscore_bb_u'] > 0 ? '+' : '') . number_format($row['zscore_bb_u'], 2) ?> SD</td>
              <td class="text-center fw-bold"><?= e($row['status_bb_u']) ?></td>
              <td>Gizi Baik: -2 SD s/d +1 SD</td>
            </tr>
            <tr>
              <td><strong>BB menurut TB (BB/TB)</strong><br><small class="text-muted">Deteksi Wasting / Gizi Buruk Akut</small></td>
              <td class="text-center font-monospace fw-bold"><?= ($row['zscore_bb_tb'] > 0 ? '+' : '') . number_format($row['zscore_bb_tb'], 2) ?> SD</td>
              <td class="text-center fw-bold"><?= e($row['status_bb_tb']) ?></td>
              <td>Gizi Baik: -2 SD s/d +1 SD</td>
            </tr>
            <tr>
              <td><strong>Indeks Massa Tubuh menurut Usia (IMT/U)</strong></td>
              <td class="text-center font-monospace fw-bold"><?= ($row['zscore_imt_u'] > 0 ? '+' : '') . number_format($row['zscore_imt_u'], 2) ?> SD</td>
              <td class="text-center fw-bold"><?= e($row['status_imt_u']) ?></td>
              <td>Gizi Baik: -2 SD s/d +1 SD</td>
            </tr>
          </tbody>
        </table>

        <!-- Kesimpulan Risiko & Catatan -->
        <div class="border p-3 rounded-2 bg-light mb-4 small">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <strong>Kesimpulan Status Risiko Balita:</strong>
            <span class="badge border <?= $row['status_risiko'] === 'Butuh Rujukan Segera' ? 'bg-danger text-white' : ($row['status_risiko'] === 'Berisiko' ? 'bg-warning text-dark' : 'bg-success text-white') ?> px-2 py-1">
              <?= e($row['status_risiko'] ?: 'Normal') ?>
            </span>
          </div>
          <div>
            <?php if ($row['status_risiko'] === 'Butuh Rujukan Segera'): ?>
              Balita terindikasi mengalami gangguan pertumbuhan signifikan (Stunting / Gizi Buruk). Diharuskan mendapatkan pemeriksaan komprehensif oleh dokter/nutrisionis di Puskesmas.
            <?php elseif ($row['status_risiko'] === 'Berisiko'): ?>
              Balita memerlukan pemantauan intensif di Posyandu dan edukasi peningkatan kualitas gizi keluarga.
            <?php else: ?>
              Pertumbuhan balita dalam batas normal kurva standar WHO. Lanjutkan pemberian makanan bergizi seimbang dan stimulasi perkembangan.
            <?php endif; ?>
          </div>
        </div>

        <!-- Riwayat Singkat KMS -->
        <h6 class="fw-bold mb-2">3. Rekam Riwayat Penimbangan Terakhir (KMS)</h6>
        <table class="table table-bordered table-sm small mb-5">
          <thead>
            <tr>
              <th>No</th>
              <th>Tanggal Ukur</th>
              <th>BB (kg)</th>
              <th>TB (cm)</th>
              <th>Status TB/U</th>
              <th>Status BB/U</th>
              <th>Status BB/TB</th>
              <th>Tingkat Risiko</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $idx => $h): ?>
              <tr>
                <td><?= $idx + 1 ?></td>
                <td><?= date('d/m/Y', strtotime($h['tanggal_pengukuran'])) ?></td>
                <td><?= number_format($h['berat_badan'], 2) ?></td>
                <td><?= number_format($h['tinggi_badan'], 1) ?></td>
                <td><?= e($h['status_tb_u']) ?></td>
                <td><?= e($h['status_bb_u']) ?></td>
                <td><?= e($h['status_bb_tb']) ?></td>
                <td><?= e($h['status_risiko']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <!-- Tanda Tangan -->
        <div class="row text-center small mt-4">
          <div class="col-6">
            <p class="mb-5">Mengetahui,<br>Orang Tua / Wali Balita</p>
            <p class="fw-bold mb-0">( <?= e($row['nama_ibu'] ?: '..........................................') ?> )</p>
          </div>
          <div class="col-6">
            <p class="mb-5">Petugas Pemeriksa / Kader Posyandu</p>
            <p class="fw-bold mb-0">( <?= e($row['nama_petugas'] ?: '..........................................') ?> )</p>
          </div>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================
// Query Data Pengukuran (Berdasarkan Filter Aktif)
// ============================================================
$where_clauses = ['1=1'];
$params        = [];

if ($search !== '') {
    $where_clauses[] = '(a.nama LIKE ? OR a.nik LIKE ? OR ot.nama_ibu LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($filter_posyandu !== '') {
    $where_clauses[] = 'a.id_posyandu = ?';
    $params[] = $filter_posyandu;
}

if ($filter_stunting !== '') {
    if ($filter_stunting === 'stunting') {
        $where_clauses[] = "sg.status_tb_u IN ('Pendek', 'Sangat Pendek')";
    } else {
        $where_clauses[] = 'sg.status_tb_u = ?';
        $params[] = $filter_stunting;
    }
}

if ($filter_risiko !== '') {
    $where_clauses[] = 'sg.status_risiko = ?';
    $params[] = $filter_risiko;
}

if ($filter_bulan !== '') {
    $where_clauses[] = "DATE_FORMAT(p.tanggal_pengukuran, '%Y-%m') = ?";
    $params[] = $filter_bulan;
}

$where_sql = implode(' AND ', $where_clauses);
$records   = [];

try {
    $sql = "
        SELECT 
            p.id_pengukuran,
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
            u.nama AS nama_petugas,
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
        WHERE {$where_sql}
        ORDER BY p.tanggal_pengukuran DESC, p.id_pengukuran DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat data pengukuran untuk ekspor: ' . $e->getMessage());
    redirect(base_url('modules/pengukuran/index.php'));
}

// ============================================================
// MODE 2: Cetak Rekapitulasi Daftar Pengukuran (Bulk Print)
// ============================================================
if ($format === 'print') {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Laporan Rekapitulasi Pengukuran Gizi — <?= e(APP_NAME) ?></title>
      <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
      <link rel="stylesheet" href="<?= asset('vendors/bootstrap-icons/bootstrap-icons.css') ?>">
      <style>
        body { font-family: "Segoe UI", Arial, sans-serif; color: #1e293b; background: #fff; }
        .print-header { border-bottom: 2px solid #0f766e; padding-bottom: 12px; margin-bottom: 20px; }
        .table th { background-color: #f1f5f9 !important; font-size: 0.8rem; }
        .table td { font-size: 0.8rem; }
        @media print {
          .no-print { display: none !important; }
          body { padding: 0; }
        }
      </style>
    </head>
    <body class="p-4">
      <div class="container-fluid">
        <!-- Toolbar Tidak Tercetak -->
        <div class="no-print d-flex justify-content-between align-items-center mb-4 p-3 bg-light border rounded-3">
          <div>
            <strong>Pratinjau Rekapitulasi Pengukuran Gizi</strong>
            <span class="text-muted d-block small">Total <?= count($records) ?> data ditemukan berdasarkan filter aktif.</span>
          </div>
          <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-sm">
              <i class="bi bi-printer me-1"></i> Cetak / Simpan PDF
            </button>
            <button onclick="window.close()" class="btn btn-secondary btn-sm">
              <i class="bi bi-x-lg me-1"></i> Tutup
            </button>
          </div>
        </div>

        <!-- Kop Surat Dokumen -->
        <div class="print-header d-flex justify-content-between align-items-end">
          <div>
            <h4 class="fw-bold text-success mb-1"><?= e(APP_NAME) ?></h4>
            <p class="text-muted mb-0 small">Sistem Informasi Pemantauan Tumbuh Kembang & Gizi Balita Terintegrasi</p>
            <p class="text-muted mb-0 small">Laporan Rekapitulasi Penimbangan & Status Antropometri WHO</p>
          </div>
          <div class="text-end small text-muted">
            <div>Tanggal Cetak: <?= date('d/m/Y H:i') ?></div>
            <div>Oleh: <?= e($_SESSION['user_nama'] ?? 'Petugas') ?></div>
          </div>
        </div>

        <!-- Tabel Rekapitulasi -->
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-sm align-middle">
            <thead>
              <tr class="text-center align-middle">
                <th>No</th>
                <th>Tgl Ukur</th>
                <th>Nama Balita</th>
                <th>NIK</th>
                <th>JK</th>
                <th>Usia</th>
                <th>Posyandu</th>
                <th>BB (kg)</th>
                <th>TB (cm)</th>
                <th>TB/U (Stunting)</th>
                <th>BB/U (Gizi)</th>
                <th>BB/TB (Wasting)</th>
                <th>Status Risiko</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($records)): ?>
                <tr>
                  <td colspan="13" class="text-center py-3 text-muted">Tidak ada data pengukuran yang cocok dengan kriteria filter.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($records as $i => $r): 
                  $usia_bln = Antropometri::hitungUsiaBulan($r['tanggal_lahir'], $r['tanggal_pengukuran']);
                ?>
                  <tr>
                    <td class="text-center"><?= $i + 1 ?></td>
                    <td class="text-nowrap"><?= date('d/m/Y', strtotime($r['tanggal_pengukuran'])) ?></td>
                    <td><strong><?= e($r['nama_anak']) ?></strong></td>
                    <td class="font-monospace small"><?= e($r['nik']) ?></td>
                    <td class="text-center"><?= $r['jenis_kelamin'] ?></td>
                    <td class="text-center"><?= (int)$usia_bln ?> bln</td>
                    <td><?= e($r['nama_posyandu'] ?: '—') ?></td>
                    <td class="text-end"><?= number_format($r['berat_badan'], 2) ?></td>
                    <td class="text-end"><?= number_format($r['tinggi_badan'], 1) ?></td>
                    <td><?= e($r['status_tb_u']) ?></td>
                    <td><?= e($r['status_bb_u']) ?></td>
                    <td><?= e($r['status_bb_tb']) ?></td>
                    <td class="text-center"><?= e($r['status_risiko']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Tanda Tangan -->
        <div class="row text-center small mt-5">
          <div class="col-6"></div>
          <div class="col-6">
            <p class="mb-5">Petugas Penanggung Jawab Laporan,</p>
            <p class="fw-bold mb-0">( <?= e($_SESSION['user_nama'] ?? '..........................................') ?> )</p>
          </div>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================
// MODE 3: Ekspor Format CSV (Microsoft Excel UTF-8 BOM)
// ============================================================
if (ob_get_level()) {
    ob_end_clean();
}

$filename = 'Laporan_Pengukuran_Gizi_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Tulis UTF-8 Byte Order Mark (BOM) agar Excel tidak rusak membaca simbol & angka
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Header Kolom CSV
$headers = [
    'No',
    'ID Pengukuran',
    'Tanggal Pengukuran',
    'NIK Balita',
    'Nama Lengkap Balita',
    'Jenis Kelamin',
    'Tanggal Lahir',
    'Usia (Bulan)',
    'Usia (Teks)',
    'Posyandu',
    'Nama Ibu',
    'Berat Badan (kg)',
    'Tinggi Badan (cm)',
    'Lingkar Kepala (cm)',
    'Z-Score TB/U',
    'Status TB/U (Stunting)',
    'Z-Score BB/U',
    'Status BB/U',
    'Z-Score BB/TB',
    'Status BB/TB (Wasting)',
    'Z-Score IMT/U',
    'Status IMT/U',
    'Status Risiko',
    'Petugas Pencatat',
];
fputcsv($output, $headers);

// Tulis Baris Data
$no = 1;
foreach ($records as $r) {
    $usia_bln  = Antropometri::hitungUsiaBulan($r['tanggal_lahir'], $r['tanggal_pengukuran']);
    $usia_teks = Antropometri::formatUsia($usia_bln);

    $row = [
        $no++,
        $r['id_pengukuran'],
        date('d/m/Y', strtotime($r['tanggal_pengukuran'])),
        "'" . $r['nik'], // Prefix tanda petik agar NIK tidak diubah format scientific oleh Excel
        $r['nama_anak'],
        $r['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan',
        date('d/m/Y', strtotime($r['tanggal_lahir'])),
        number_format($usia_bln, 1),
        $usia_teks,
        $r['nama_posyandu'] ?: '',
        $r['nama_ibu'] ?: '',
        number_format($r['berat_badan'], 2),
        number_format($r['tinggi_badan'], 1),
        $r['lingkar_kepala'] ? number_format($r['lingkar_kepala'], 1) : '',
        $r['zscore_tb_u'] !== null ? number_format($r['zscore_tb_u'], 2) : '',
        $r['status_tb_u'] ?: '',
        $r['zscore_bb_u'] !== null ? number_format($r['zscore_bb_u'], 2) : '',
        $r['status_bb_u'] ?: '',
        $r['zscore_bb_tb'] !== null ? number_format($r['zscore_bb_tb'], 2) : '',
        $r['status_bb_tb'] ?: '',
        $r['zscore_imt_u'] !== null ? number_format($r['zscore_imt_u'], 2) : '',
        $r['status_imt_u'] ?: '',
        $r['status_risiko'] ?: '',
        $r['nama_petugas'] ?: '',
    ];
    fputcsv($output, $row);
}

fclose($output);
exit;
