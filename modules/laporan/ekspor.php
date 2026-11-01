<?php
/**
 * Modul Laporan — Ekspor & Cetak Rekapitulasi Gizi Bulanan
 * ==============================================================
 * Fitur:
 * 1. Export laporan ke format PDF (Tampilan Dokumen Cetak Resmi A4 dengan Kop & Tanda Tangan).
 * 2. Export laporan ke format Excel (Spreadsheet CSV dengan UTF-8 BOM).
 * ==============================================================
 */

$current_slug = 'laporan-gizi';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';
require_once ROOT_PATH . '/src/Otorisasi.php';
require_once ROOT_PATH . '/src/Antropometri.php';

// Cek hak akses modul laporan-gizi
$user_role_id      = (int)($_SESSION['role_id'] ?? 0);
$user_role_nama    = $_SESSION['user_role_nama'] ?? '';
$user_posyandu_id  = (int)($_SESSION['user_posyandu'] ?? 0);
$user_puskesmas_id = (int)($_SESSION['user_puskesmas'] ?? 0);
$user_wilayah_id   = (int)($_SESSION['user_wilayah'] ?? 0);
$nama_petugas      = $_SESSION['user_nama'] ?? 'Petugas SiPantau Gizi';

if (!bolehAkses($user_role_id, 'laporan-gizi') && $user_role_id !== Otorisasi::ADMIN_ROLE_ID && strcasecmp($user_role_nama, 'Admin') !== 0) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengekspor data laporan.');
    redirect(base_url('modules/laporan/index.php'));
}

// ============================================================
// Parameter Ekspor
// ============================================================
$format          = strtolower(trim($_GET['format'] ?? 'excel'));
$filter_posyandu = trim($_GET['posyandu'] ?? '');
$filter_bulan    = trim($_GET['bulan'] ?? '');

// Jika role adalah Kader, batasi ke posyandu tempat tugas
if ($user_role_id === 1 && $user_posyandu_id > 0) {
    $filter_posyandu = (string)$user_posyandu_id;
}

// Normalisasi periode bulan (default: bulan berjalan)
if ($filter_bulan === '' || !preg_match('/^\d{4}-\d{2}$/', $filter_bulan)) {
    $filter_bulan = date('Y-m');
}

// Helper formatting bulan Indonesia
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

// Ambil Informasi Detail Posyandu & Wilayah
$posyandu_info = null;
if ($filter_posyandu !== '' && $pdo !== null) {
    try {
        $stmtPos = $pdo->prepare("
            SELECT p.id_posyandu, p.nama_posyandu, p.alamat as alamat_posyandu,
                   w.nama_wilayah, pus.nama_puskesmas
            FROM posyandu p
            LEFT JOIN wilayah w ON p.id_wilayah = w.id_wilayah
            LEFT JOIN puskesmas pus ON p.id_puskesmas = pus.id_puskesmas
            WHERE p.id_posyandu = ?
        ");
        $stmtPos->execute([$filter_posyandu]);
        $posyandu_info = $stmtPos->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

$nama_posyandu_label = $posyandu_info ? $posyandu_info['nama_posyandu'] : 'Seluruh Posyandu Wilayah Binaan';
$wilayah_label       = $posyandu_info ? ($posyandu_info['nama_wilayah'] ?? '-') : 'Wilayah Kerja Puskesmas';
$puskesmas_label     = $posyandu_info ? ($posyandu_info['nama_puskesmas'] ?? APP_NAME) : APP_NAME;

// ============================================================
// 1. Query Rekap Kehadiran & Indikator SKDN
// ============================================================
$S = 0; // Sasaran Balita
$K = 0; // Memiliki KMS / Buku KIA
$D = 0; // Ditimbang pada bulan ini
$N = 0; // Naik berat badan
$T = 0; // Tidak naik / tetap

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
        $K = $S; // Setiap sasaran balita tercatat memiliki buku KIA/KMS

        // Query D (Balita Ditimbang)
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

        // Rekap Kehadiran
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
// 2. Query Distribusi Status Gizi (BB/U, TB/U, BB/TB)
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

$total_stunting    = $distribusi_gizi['tbu_pendek'] + $distribusi_gizi['tbu_sangat_pendek'];
$total_wasting     = $distribusi_gizi['bbtb_kurang'] + $distribusi_gizi['bbtb_buruk'];
$total_underweight = $distribusi_gizi['bbu_kurang'] + $distribusi_gizi['bbu_buruk'];

// ============================================================
// 3. Query Daftar Anak Berisiko per Posyandu
// ============================================================
$anak_berisiko_list = [];

if ($pdo !== null) {
    try {
        $sqlR = "
            SELECT 
                a.id_anak, a.nama as nama_anak, a.nik, a.tanggal_lahir, a.jenis_kelamin, a.alamat as alamat_anak,
                pos.nama_posyandu,
                ot.nama_ibu, ot.nama_ayah, ot.no_telepon, ot.alamat as alamat_ot,
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
            ORDER BY (sg.status_tb_u IN ('Sangat Pendek', 'Pendek')) DESC, p.tanggal_pengukuran DESC
        ";
        $paramsR = [$filter_bulan];
        if ($filter_posyandu !== '') {
            $sqlR = str_replace("WHERE DATE_FORMAT(p.tanggal_pengukuran, '%Y-%m') = ?", "WHERE DATE_FORMAT(p.tanggal_pengukuran, '%Y-%m') = ? AND a.id_posyandu = ?", $sqlR);
            $paramsR[] = $filter_posyandu;
        }

        $stmtR = $pdo->prepare($sqlR);
        $stmtR->execute($paramsR);
        $anak_berisiko_list = $stmtR->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}


// ============================================================
// MODE A: CETAK / EXPORT KE PDF (Print Preview Dokumen Resmi)
// ============================================================
if ($format === 'print') {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Laporan Rekapitulasi Gizi &amp; Kehadiran — <?= e(formatBulanIndo($filter_bulan)) ?></title>
      <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
      <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        * {
          box-sizing: border-box;
        }

        body {
          font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
          color: #1e293b;
          background-color: #f8fafc;
          margin: 0;
          padding: 24px;
          font-size: 12px;
        }

        .print-container {
          max-width: 1080px;
          margin: 0 auto;
          background: #fff;
          padding: 32px 40px;
          border-radius: 8px;
          box-shadow: 0 4px 14px rgba(0,0,0,0.06);
        }

        /* Kop Surat Resmi */
        .kop-surat {
          border-bottom: 3px double #0f172a;
          padding-bottom: 12px;
          margin-bottom: 20px;
          display: flex;
          align-items: center;
          gap: 20px;
        }

        .kop-logo {
          width: 72px;
          height: 72px;
          border-radius: 12px;
          background: #eff6ff;
          display: flex;
          align-items: center;
          justify-content: center;
          color: #2563eb;
          font-size: 36px;
          flex-shrink: 0;
          border: 1px solid #bfdbfe;
        }

        .kop-text {
          flex: 1;
          text-align: center;
        }

        .kop-text h2 {
          font-size: 15px;
          font-weight: 700;
          letter-spacing: 0.5px;
          margin: 0;
          text-transform: uppercase;
          color: #0f172a;
        }

        .kop-text h1 {
          font-size: 18px;
          font-weight: 800;
          letter-spacing: 0.5px;
          margin: 2px 0;
          text-transform: uppercase;
          color: #1d4ed8;
        }

        .kop-text p {
          font-size: 11px;
          margin: 0;
          color: #475569;
        }

        /* Metadata Kartu */
        .meta-box {
          background-color: #f8fafc;
          border: 1px solid #e2e8f0;
          border-radius: 6px;
          padding: 12px 16px;
          margin-bottom: 20px;
        }

        .section-header {
          font-size: 13px;
          font-weight: 700;
          color: #0f172a;
          border-left: 4px solid #2563eb;
          padding-left: 8px;
          margin-bottom: 10px;
          margin-top: 18px;
          text-transform: uppercase;
          letter-spacing: 0.5px;
        }

        .table-custom {
          width: 100%;
          border-collapse: collapse;
          margin-bottom: 16px;
          font-size: 11.5px;
        }

        .table-custom th, .table-custom td {
          border: 1px solid #cbd5e1;
          padding: 6px 10px;
        }

        .table-custom th {
          background-color: #f1f5f9;
          font-weight: 600;
          color: #0f172a;
          text-align: center;
        }

        .badge-status {
          display: inline-block;
          padding: 2px 8px;
          border-radius: 4px;
          font-size: 10.5px;
          font-weight: 600;
        }

        .badge-stunting { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-normal   { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-warning  { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

        .ttd-box {
          margin-top: 36px;
          display: flex;
          justify-content: space-between;
          page-break-inside: avoid;
        }

        .ttd-col {
          width: 280px;
          text-align: center;
        }

        .ttd-space {
          height: 64px;
        }

        /* Toolbar Layar / No-Print */
        .print-toolbar {
          position: sticky;
          top: 0;
          z-index: 999;
          background: #ffffff;
          padding: 12px 20px;
          border-radius: 8px;
          box-shadow: 0 2px 10px rgba(0,0,0,0.08);
          max-width: 1080px;
          margin: 0 auto 20px auto;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }

        @media print {
          .no-print {
            display: none !important;
          }

          body {
            background-color: #fff !important;
            padding: 0 !important;
          }

          .print-container {
            box-shadow: none !important;
            padding: 0 !important;
            max-width: 100% !important;
            width: 100% !important;
          }

          @page {
            size: A4 landscape;
            margin: 12mm 10mm;
          }

          .table-custom th {
            background-color: #f1f5f9 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }

          .badge-stunting, .badge-normal, .badge-warning {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
        }
      </style>
    </head>
    <body>

      <!-- Toolbar Aksi Layar (Disembunyikan Saat Dicetak) -->
      <div class="print-toolbar no-print">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-file-earmark-pdf text-danger fs-4"></i>
          <div>
            <strong class="d-block text-dark">Pratinjau Dokumen Cetak / Simpan PDF</strong>
            <small class="text-muted">Siap dicetak atau disimpan sebagai PDF (Kertas A4 Lanskap)</small>
          </div>
        </div>
        <div class="d-flex gap-2">
          <button onclick="window.print()" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-printer-fill"></i> Cetak Dokumen / Simpan PDF
          </button>
          <a href="<?= base_url('modules/laporan/index.php?' . http_build_query(['posyandu' => $filter_posyandu, 'bulan' => $filter_bulan])) ?>"
             class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Kembali ke Menu Laporan
          </a>
        </div>
      </div>

      <div class="print-container">
        <!-- Kop Surat -->
        <div class="kop-surat">
          <div class="kop-logo">
            <i class="bi bi-hospital"></i>
          </div>
          <div class="kop-text">
            <h2>PEMERINTAH KABUPATEN / KOTA &bull; DINAS KESEHATAN</h2>
            <h1>UPTD PUSKESMAS &bull; SIPANTAU GIZI</h1>
            <p>Sistem Pemantauan Status Gizi Balita &amp; Deteksi Dini Stunting Terpadu</p>
            <p>Wilayah: <?= e($wilayah_label) ?> &bull; Unit Layanan: <?= e($puskesmas_label) ?></p>
          </div>
        </div>

        <!-- Judul Dokumen -->
        <div class="text-center mb-3">
          <h3 style="font-size: 15px; font-weight: 700; margin: 0; text-transform: uppercase; color: #0f172a;">
            LAPORAN REKAPITULASI PEMANTAUAN GIZI &amp; KEHADIRAN BULANAN
          </h3>
          <p class="text-muted mb-0" style="font-size: 12px;">
            Periode Penimbangan: <strong><?= e(formatBulanIndo($filter_bulan)) ?></strong> &bull; Posyandu: <strong><?= e($nama_posyandu_label) ?></strong>
          </p>
        </div>

        <!-- Metadata Ringkas -->
        <div class="meta-box">
          <div class="row g-2">
            <div class="col-6 col-md-3">
              <span class="text-muted d-block" style="font-size: 10.5px;">PERIODE BULAN:</span>
              <strong><?= e(formatBulanIndo($filter_bulan)) ?></strong>
            </div>
            <div class="col-6 col-md-3">
              <span class="text-muted d-block" style="font-size: 10.5px;">TITIK LAYANAN:</span>
              <strong><?= e($nama_posyandu_label) ?></strong>
            </div>
            <div class="col-6 col-md-3">
              <span class="text-muted d-block" style="font-size: 10.5px;">TANGGAL CETAK:</span>
              <strong><?= date('d/m/Y H:i') ?> WIB</strong>
            </div>
            <div class="col-6 col-md-3">
              <span class="text-muted d-block" style="font-size: 10.5px;">DICETAK OLEH:</span>
              <strong><?= e($nama_petugas) ?> (<?= e($user_role_nama) ?>)</strong>
            </div>
          </div>
        </div>

        <!-- ============================================================ -->
        <!-- 1. REKAP KEHADIRAN & INDIKATOR SKDN                          -->
        <!-- ============================================================ -->
        <div class="section-header">1. Rekapitulasi Indikator SKDN &amp; Kehadiran Balita</div>
        <table class="table-custom text-center">
          <thead>
            <tr>
              <th>Sasaran Balita (S)</th>
              <th>Memiliki KMS/KIA (K)</th>
              <th>Ditimbang (D)</th>
              <th>Cakupan Penimbangan (D/S)</th>
              <th>Naik Berat Badan (N)</th>
              <th>Efektivitas Edukasi (N/D)</th>
              <th>Tidak Naik / Tetap (T)</th>
              <th>Hadir Posyandu</th>
              <th>Izin / Sakit</th>
              <th>Alpha (Tanpa Kabar)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="fw-bold"><?= number_format($S) ?></td>
              <td><?= number_format($K) ?></td>
              <td class="fw-bold text-success"><?= number_format($D) ?></td>
              <td class="fw-bold text-primary"><?= $persen_DS ?>%</td>
              <td class="fw-bold text-info"><?= number_format($N) ?></td>
              <td class="fw-bold"><?= $persen_ND ?>%</td>
              <td class="fw-bold text-warning"><?= number_format($T) ?></td>
              <td><?= number_format($kehadiran_stats['hadir']) ?></td>
              <td><?= number_format($kehadiran_stats['izin'] + $kehadiran_stats['sakit']) ?></td>
              <td class="text-danger fw-semibold"><?= number_format($kehadiran_stats['alpha']) ?></td>
            </tr>
          </tbody>
        </table>

        <!-- ============================================================ -->
        <!-- 2. REKAPITULASI DISTRIBUSI STATUS GIZI                       -->
        <!-- ============================================================ -->
        <div class="section-header">2. Distribusi Status Gizi Balita</div>
        <table class="table-custom">
          <thead>
            <tr>
              <th style="text-align: left;">Indikator Antropometri</th>
              <th>Normal / Baik</th>
              <th>Kurang / Pendek</th>
              <th>Buruk / Sangat Pendek</th>
              <th>Lebih / Tinggi</th>
              <th>Total Terukur</th>
              <th>Prevalensi Masalah</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="fw-bold">Tinggi Badan / Umur (TB/U - Stunting)</td>
              <td class="text-center"><?= $distribusi_gizi['tbu_normal'] ?> Anak</td>
              <td class="text-center text-warning fw-semibold"><?= $distribusi_gizi['tbu_pendek'] ?> Anak</td>
              <td class="text-center text-danger fw-bold"><?= $distribusi_gizi['tbu_sangat_pendek'] ?> Anak</td>
              <td class="text-center"><?= $distribusi_gizi['tbu_tinggi'] ?> Anak</td>
              <td class="text-center fw-bold"><?= $D ?> Anak</td>
              <td class="text-center text-danger fw-bold">
                <?= $D > 0 ? round(($total_stunting / $D) * 100, 1) : 0 ?>% (<?= $total_stunting ?> Kasus)
              </td>
            </tr>
            <tr>
              <td class="fw-bold">Berat Badan / Umur (BB/U - Underweight)</td>
              <td class="text-center"><?= $distribusi_gizi['bbu_baik'] ?> Anak</td>
              <td class="text-center text-warning fw-semibold"><?= $distribusi_gizi['bbu_kurang'] ?> Anak</td>
              <td class="text-center text-danger fw-bold"><?= $distribusi_gizi['bbu_buruk'] ?> Anak</td>
              <td class="text-center"><?= $distribusi_gizi['bbu_lebih'] ?> Anak</td>
              <td class="text-center fw-bold"><?= $D ?> Anak</td>
              <td class="text-center text-warning fw-bold">
                <?= $D > 0 ? round(($total_underweight / $D) * 100, 1) : 0 ?>% (<?= $total_underweight ?> Kasus)
              </td>
            </tr>
            <tr>
              <td class="fw-bold">Berat Badan / Tinggi Badan (BB/TB - Wasting)</td>
              <td class="text-center"><?= $distribusi_gizi['bbtb_normal'] ?> Anak</td>
              <td class="text-center text-warning fw-semibold"><?= $distribusi_gizi['bbtb_kurang'] ?> Anak</td>
              <td class="text-center text-danger fw-bold"><?= $distribusi_gizi['bbtb_buruk'] ?> Anak</td>
              <td class="text-center"><?= $distribusi_gizi['bbtb_lebih'] ?> Anak</td>
              <td class="text-center fw-bold"><?= $D ?> Anak</td>
              <td class="text-center text-danger fw-bold">
                <?= $D > 0 ? round(($total_wasting / $D) * 100, 1) : 0 ?>% (<?= $total_wasting ?> Kasus)
              </td>
            </tr>
          </tbody>
        </table>

        <!-- ============================================================ -->
        <!-- 3. DAFTAR BALITA BERISIKO GIZI & STUNTING                    -->
        <!-- ============================================================ -->
        <div class="section-header">3. Daftar Balita Berisiko Gizi &amp; Stunting (Perlu Intervensi)</div>
        <table class="table-custom">
          <thead>
            <tr>
              <th style="text-align: left;">Nama Balita &amp; NIK</th>
              <th>JK</th>
              <th>Usia</th>
              <th>Posyandu</th>
              <th style="text-align: left;">Orang Tua / Kontak</th>
              <th>BB (kg)</th>
              <th>TB (cm)</th>
              <th>Status TB/U (Stunting)</th>
              <th>Status BB/U</th>
              <th>Status BB/TB</th>
              <th>Tindak Lanjut / Intervensi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($anak_berisiko_list)): ?>
              <tr>
                <td colspan="11" class="text-center text-muted py-3">
                  <em>Tidak ada data balita berisiko gizi pada periode penimbangan ini.</em>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($anak_berisiko_list as $anak): 
                $usia_bln = Antropometri::hitungUsiaBulan($anak['tanggal_lahir'], $anak['tanggal_pengukuran']);
                $is_stunting = in_array($anak['status_tb_u'], ['Pendek', 'Sangat Pendek', 'Stunting']);
              ?>
                <tr>
                  <td>
                    <strong><?= e($anak['nama_anak']) ?></strong>
                    <div style="font-size: 10px; color: #64748b;"><?= e($anak['nik'] ?: '-') ?></div>
                  </td>
                  <td class="text-center"><?= ($anak['jenis_kelamin'] === 'L') ? 'L' : 'P' ?></td>
                  <td class="text-center"><?= (int)round($usia_bln) ?> Bln</td>
                  <td class="text-center"><?= e($anak['nama_posyandu']) ?></td>
                  <td>
                    <?= e($anak['nama_ibu'] ?: '-') ?>
                    <?php if (!empty($anak['no_telepon'])): ?>
                      <div style="font-size: 10px; color: #475569;"><?= e($anak['no_telepon']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-center"><?= number_format((float)$anak['berat_badan'], 1) ?></td>
                  <td class="text-center"><?= number_format((float)$anak['tinggi_badan'], 1) ?></td>
                  <td class="text-center">
                    <span class="badge-status <?= $is_stunting ? 'badge-stunting' : 'badge-normal' ?>">
                      <?= e($anak['status_tb_u']) ?>
                    </span>
                  </td>
                  <td class="text-center">
                    <span class="badge-status <?= in_array($anak['status_bb_u'], ['Gizi Kurang', 'Gizi Buruk']) ? 'badge-warning' : 'badge-normal' ?>">
                      <?= e($anak['status_bb_u']) ?>
                    </span>
                  </td>
                  <td class="text-center">
                    <span class="badge-status <?= in_array($anak['status_bb_tb'], ['Gizi Kurang', 'Gizi Buruk', 'Kurus', 'Sangat Kurus']) ? 'badge-stunting' : 'badge-normal' ?>">
                      <?= e($anak['status_bb_tb']) ?>
                    </span>
                  </td>
                  <td class="text-center">
                    <span style="font-size: 10.5px; font-weight: 600; color: #dc2626;">
                      <?= e($anak['status_risiko'] ?: 'Pemberian Makanan Tambahan (PMT)') ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <!-- ============================================================ -->
        <!-- LEMBAR PENGESAHAN / TANDA TANGAN                             -->
        <!-- ============================================================ -->
        <div class="ttd-box">
          <div class="ttd-col">
            <p class="mb-1">Mengetahui,</p>
            <p class="fw-bold mb-0">Petugas Gizi / Bidan Pembina</p>
            <div class="ttd-space"></div>
            <p class="fw-bold text-decoration-underline mb-0"><?= e($nama_petugas) ?></p>
            <p class="text-muted small mb-0">NIP / ID: <?= sprintf('PST-%04d', $user_posyandu_id ?: 1001) ?></p>
          </div>

          <div class="ttd-col">
            <p class="mb-1"><?= e($wilayah_label) ?>, <?= date('d') ?> <?= formatBulanIndo(date('Y-m')) ?></p>
            <p class="fw-bold mb-0">Koordinator Posyandu / Penanggung Jawab</p>
            <div class="ttd-space"></div>
            <p class="fw-bold text-decoration-underline mb-0">___________________________</p>
            <p class="text-muted small mb-0">Ketua / Penanggung Jawab Wilayah</p>
          </div>
        </div>
      </div>

      <script>
        // Otomatis buka dialog cetak browser saat halaman selesai dimuat
        window.addEventListener('load', function() {
          // Beri jeda 300ms agar font & styling ter-render sempurna
          setTimeout(function() {
            window.print();
          }, 300);
        });
      </script>
    </body>
    </html>
    <?php
    exit;
}


// ============================================================
// MODE B: EXPORT KE EXCEL (Spreadsheet CSV UTF-8 BOM)
// ============================================================
$posyandu_filename_part = preg_replace('/[^A-Za-z0-9_]/', '_', $nama_posyandu_label);
$filename = 'Laporan_Rekap_Gizi_' . $posyandu_filename_part . '_' . $filter_bulan . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Tulis UTF-8 Byte Order Mark (BOM) agar Microsoft Excel membaca karakter Indonesia dengan sempurna
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// 1. Header Laporan & Metadata
fputcsv($output, ['LAPORAN REKAPITULASI PEMANTAUAN GIZI DAN KEHADIRAN POSYANDU']);
fputcsv($output, ['Sistem Informasi SiPantau Gizi']);
fputcsv($output, ['Periode Bulan', formatBulanIndo($filter_bulan)]);
fputcsv($output, ['Posyandu / Titik Layanan', $nama_posyandu_label]);
fputcsv($output, ['Wilayah Kerja', $wilayah_label]);
fputcsv($output, ['Tanggal Unduh', date('d/m/Y H:i:s') . ' WIB']);
fputcsv($output, ['Petugas Pengunduh', $nama_petugas . ' (' . $user_role_nama . ')']);
fputcsv($output, []); // Baris kosong pemisah

// 2. Rekap Indikator SKDN & Kehadiran
fputcsv($output, ['--- REKAPITULASI INDIKATOR SKDN & KEHADIRAN BALITA ---']);
fputcsv($output, [
    'Sasaran Balita (S)',
    'Memiliki KMS/KIA (K)',
    'Ditimbang (D)',
    'Cakupan Penimbangan (D/S %)',
    'Naik Berat Badan (N)',
    'Efektivitas Edukasi (N/D %)',
    'Tidak Naik/Tetap (T)',
    'Kehadiran: Hadir',
    'Kehadiran: Izin',
    'Kehadiran: Sakit',
    'Kehadiran: Alpha'
]);
fputcsv($output, [
    $S,
    $K,
    $D,
    $persen_DS . '%',
    $N,
    $persen_ND . '%',
    $T,
    $kehadiran_stats['hadir'],
    $kehadiran_stats['izin'],
    $kehadiran_stats['sakit'],
    $kehadiran_stats['alpha']
]);
fputcsv($output, []); // Baris kosong pemisah

// 3. Rekapitulasi Distribusi Status Gizi
fputcsv($output, ['--- DISTRIBUSI STATUS GIZI BALITA ---']);
fputcsv($output, [
    'Indikator Antropometri',
    'Normal / Baik',
    'Kurang / Pendek',
    'Buruk / Sangat Pendek',
    'Lebih / Tinggi',
    'Total Terukur',
    'Prevalensi Masalah (%)'
]);
fputcsv($output, [
    'Tinggi Badan / Umur (TB/U - Stunting)',
    $distribusi_gizi['tbu_normal'],
    $distribusi_gizi['tbu_pendek'],
    $distribusi_gizi['tbu_sangat_pendek'],
    $distribusi_gizi['tbu_tinggi'],
    $D,
    ($D > 0 ? round(($total_stunting / $D) * 100, 1) : 0) . '% (' . $total_stunting . ' Kasus)'
]);
fputcsv($output, [
    'Berat Badan / Umur (BB/U - Underweight)',
    $distribusi_gizi['bbu_baik'],
    $distribusi_gizi['bbu_kurang'],
    $distribusi_gizi['bbu_buruk'],
    $distribusi_gizi['bbu_lebih'],
    $D,
    ($D > 0 ? round(($total_underweight / $D) * 100, 1) : 0) . '% (' . $total_underweight . ' Kasus)'
]);
fputcsv($output, [
    'Berat Badan / Tinggi Badan (BB/TB - Wasting)',
    $distribusi_gizi['bbtb_normal'],
    $distribusi_gizi['bbtb_kurang'],
    $distribusi_gizi['bbtb_buruk'],
    $distribusi_gizi['bbtb_lebih'],
    $D,
    ($D > 0 ? round(($total_wasting / $D) * 100, 1) : 0) . '% (' . $total_wasting . ' Kasus)'
]);
fputcsv($output, []); // Baris kosong pemisah

// 4. Daftar Balita Berisiko Gizi & Stunting
fputcsv($output, ['--- DAFTAR BALITA BERISIKO GIZI & STUNTING (PERLU INTERVENSI) ---']);
fputcsv($output, [
    'NIK Balita',
    'Nama Lengkap Balita',
    'Jenis Kelamin',
    'Tanggal Lahir',
    'Usia (Bulan)',
    'Posyandu',
    'Nama Ibu',
    'Nama Ayah',
    'No Telepon',
    'Alamat Balita',
    'Tanggal Pengukuran',
    'Berat Badan (kg)',
    'Tinggi Badan (cm)',
    'Lingkar Kepala (cm)',
    'Status TB/U (Stunting)',
    'Status BB/U (Underweight)',
    'Status BB/TB (Wasting)',
    'Status IMT/U',
    'Kategori Risiko / Intervensi'
]);

foreach ($anak_berisiko_list as $anak) {
    $usia_bln = Antropometri::hitungUsiaBulan($anak['tanggal_lahir'], $anak['tanggal_pengukuran']);
    fputcsv($output, [
        "'" . ($anak['nik'] ?: '-'), // Prefix ' agar NIK tidak diformat scientific number oleh Excel
        $anak['nama_anak'],
        $anak['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan',
        $anak['tanggal_lahir'],
        (int)round($usia_bln),
        $anak['nama_posyandu'],
        $anak['nama_ibu'] ?: '-',
        $anak['nama_ayah'] ?: '-',
        $anak['no_telepon'] ? ("'" . $anak['no_telepon']) : '-',
        $anak['alamat_anak'] ?: ($anak['alamat_ot'] ?: '-'),
        $anak['tanggal_pengukuran'],
        number_format((float)$anak['berat_badan'], 1),
        number_format((float)$anak['tinggi_badan'], 1),
        $anak['lingkar_kepala'] ? number_format((float)$anak['lingkar_kepala'], 1) : '-',
        $anak['status_tb_u'],
        $anak['status_bb_u'],
        $anak['status_bb_tb'],
        $anak['status_imt_u'] ?: '-',
        $anak['status_risiko'] ?: 'Pemberian Makanan Tambahan (PMT)'
    ]);
}

fclose($output);
exit;
