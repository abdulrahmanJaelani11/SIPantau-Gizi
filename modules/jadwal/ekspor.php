<?php
/**
 * Modul Jadwal & Kunjungan — Ekspor Data & Cetak Dokumen
 * ========================================================
 * Mengekspor jadwal dan presensi ke format CSV (Excel) serta
 * menyediakan dokumen cetak / PDF (Lembar Daftar Hadir, Sweeping Kunjungan Rumah, dan Rekapitulasi).
 */

$current_slug = 'jadwal';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';
require_once ROOT_PATH . '/src/Antropometri.php';

// Pastikan memiliki hak akses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'jadwal')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengekspor jadwal posyandu.');
    redirect(base_url('modules/jadwal/index.php'));
}

$format          = strtolower(trim($_GET['format'] ?? 'csv'));
$type            = strtolower(trim($_GET['type'] ?? ''));
$id_jadwal       = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$search          = trim($_GET['search'] ?? '');
$filter_posyandu = trim($_GET['posyandu'] ?? '');
$filter_status   = trim($_GET['status'] ?? '');
$filter_bulan    = trim($_GET['bulan'] ?? '');

// ============================================================
// MODE 1: Cetak Dokumen Sesi Jadwal Tertentu (Single Print)
// ============================================================
if ($format === 'print' && $id_jadwal) {
    try {
        $stmtJ = $pdo->prepare("
            SELECT j.*, pos.nama_posyandu, pos.alamat AS alamat_posyandu
            FROM jadwal_posyandu j
            JOIN posyandu pos ON j.id_posyandu = pos.id_posyandu
            WHERE j.id_jadwal = ?
            LIMIT 1
        ");
        $stmtJ->execute([$id_jadwal]);
        $jadwal = $stmtJ->fetch(PDO::FETCH_ASSOC);

        if (!$jadwal) {
            die('Data jadwal tidak ditemukan.');
        }

        // Ambil daftar kehadiran
        $sqlK = "
            SELECT 
                k.*,
                a.nama AS nama_anak, a.nik, a.tanggal_lahir, a.jenis_kelamin, a.alamat AS alamat_anak,
                ot.nama_ibu, ot.nama_ayah, ot.no_telepon, ot.alamat AS alamat_orangtua,
                p.berat_badan, p.tinggi_badan, sg.status_tb_u, sg.status_bb_u
            FROM kehadiran k
            JOIN anak a ON k.id_anak = a.id_anak
            LEFT JOIN orangtua ot ON a.id_orangtua = ot.id_orangtua
            LEFT JOIN pengukuran p ON (p.id_anak = a.id_anak AND p.tanggal_pengukuran = '{$jadwal['tanggal']}')
            LEFT JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
            WHERE k.id_jadwal = ?
        ";

        if ($type === 'sweeping') {
            $sqlK .= " AND k.status_kehadiran = 'Tidak Hadir' ";
        }

        $sqlK .= " ORDER BY a.nama ASC ";

        $stmtK = $pdo->prepare($sqlK);
        $stmtK->execute([$id_jadwal]);
        $rows = $stmtK->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        die('Gagal memuat data: ' . $e->getMessage());
    }

    $is_sweeping = ($type === 'sweeping');
    $title_doc   = $is_sweeping ? 'Lembar Sweeping Kunjungan Rumah' : 'Daftar Hadir Posyandu';
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title><?= e($title_doc) ?> — <?= e($jadwal['nama_posyandu']) ?></title>
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
        <!-- Toolbar Kontrol -->
        <div class="no-print d-flex justify-content-between align-items-center mb-4 p-3 bg-light border rounded-3">
          <div>
            <strong>Pratinjau Cetak: <?= e($title_doc) ?></strong>
            <span class="text-muted d-block small">Dokumen siap dicetak langsung atau disimpan sebagai PDF.</span>
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

        <!-- Kop Dokumen -->
        <div class="print-header d-flex justify-content-between align-items-end">
          <div>
            <h4 class="fw-bold text-success mb-1"><?= e(APP_NAME) ?></h4>
            <p class="text-muted mb-0 small">Sistem Informasi Pemantauan Tumbuh Kembang & Gizi Balita</p>
            <p class="text-muted mb-0 small">Pelayanan Posyandu & Surveilans Gizi Berkelanjutan</p>
          </div>
          <div class="text-end small text-muted">
            <div><strong><?= e($jadwal['nama_posyandu']) ?></strong></div>
            <div>Dicetak: <?= date('d/m/Y H:i') ?></div>
          </div>
        </div>

        <!-- Judul Dokumen -->
        <div class="text-center mb-4">
          <h5 class="fw-bold text-uppercase mb-1">
            <?= $is_sweeping ? 'FORMULIR SWEEPING KUNJUNGAN RUMAH BALITA' : 'LEMBAR PRESENSI & HASIL PENIMBANGAN POSYANDU' ?>
          </h5>
          <p class="text-muted small mb-0">
            Tanggal: <?= date('d F Y', strtotime($jadwal['tanggal'])) ?> · Lokasi: <?= e($jadwal['lokasi']) ?> · Agenda: <?= e($jadwal['keterangan'] ?: 'Penimbangan Rutin') ?>
          </p>
        </div>

        <!-- Tabel Data -->
        <table class="table table-bordered table-striped table-sm align-middle mb-4">
          <thead>
            <tr class="text-center align-middle">
              <th style="width: 3%;">No</th>
              <th style="width: 20%;">Nama Balita</th>
              <th style="width: 14%;">NIK</th>
              <th style="width: 5%;">JK</th>
              <th style="width: 10%;">Usia</th>
              <th style="width: 14%;">Nama Orang Tua</th>
              <?php if ($is_sweeping): ?>
                <th style="width: 18%;">Alamat Lengkap Rumah</th>
                <th style="width: 16%;">Hasil Sweeping / BB / TB</th>
              <?php else: ?>
                <th style="width: 10%;">Status Hadir</th>
                <th style="width: 12%;">Hasil Ukur (BB/TB)</th>
                <th style="width: 12%;">Tanda Tangan / Ket</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="<?= $is_sweeping ? 8 : 9 ?>" class="text-center py-3 text-muted">
                  <?= $is_sweeping ? 'Alhamdulillah, tidak ada balita yang absen. Semua sasaran hadir di Posyandu!' : 'Belum ada sasaran balita terdaftar.' ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $i => $r): 
                $u_bln = Antropometri::hitungUsiaBulan($r['tanggal_lahir'], $jadwal['tanggal']);
              ?>
                <tr>
                  <td class="text-center"><?= $i + 1 ?></td>
                  <td><strong><?= e($r['nama_anak']) ?></strong></td>
                  <td class="font-monospace small"><?= e($r['nik']) ?></td>
                  <td class="text-center"><?= $r['jenis_kelamin'] ?></td>
                  <td class="text-center"><?= (int)$u_bln ?> bln</td>
                  <td><?= e($r['nama_ibu'] ?: $r['nama_ayah'] ?: '—') ?></td>
                  <?php if ($is_sweeping): ?>
                    <td>
                      <small><?= e($r['alamat_orangtua'] ?: $r['alamat_anak'] ?: '—') ?></small>
                      <?php if (!empty($r['no_telepon'])): ?>
                        <div class="small text-muted">Telp: <?= e($r['no_telepon']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <small class="text-muted d-block">Tgl Sweeping: ...../...../2026</small>
                      <small class="text-muted d-block">BB: ......... kg · TB: ......... cm</small>
                    </td>
                  <?php else: ?>
                    <td class="text-center fw-semibold"><?= e($r['status_kehadiran'] ?: 'Belum') ?></td>
                    <td>
                      <?php if (!empty($r['berat_badan'])): ?>
                        <?= number_format($r['berat_badan'], 2) ?> kg / <?= number_format($r['tinggi_badan'], 1) ?> cm
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= e($r['keterangan']) ?></td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <!-- Tanda Tangan Pengesahan -->
        <div class="row text-center small mt-5">
          <div class="col-6">
            <p class="mb-5">Mengetahui,<br>Bidan Pembina Wilayah / Puskesmas</p>
            <p class="fw-bold mb-0">( .................................................... )</p>
          </div>
          <div class="col-6">
            <p class="mb-5">Pelaksana Kegiatan,<br>Ketua Kader Posyandu</p>
            <p class="fw-bold mb-0">( <?= e($_SESSION['user_nama'] ?? '....................................................') ?> )</p>
          </div>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================
// MODE 2: Ekspor Format CSV (Microsoft Excel UTF-8 BOM)
// ============================================================
if (ob_get_level()) {
    ob_end_clean();
}

if ($id_jadwal) {
    // Ekspor detail presensi jadwal tunggal
    $stmtJ = $pdo->prepare("SELECT j.*, pos.nama_posyandu FROM jadwal_posyandu j JOIN posyandu pos ON j.id_posyandu = pos.id_posyandu WHERE j.id_jadwal = ?");
    $stmtJ->execute([$id_jadwal]);
    $jadwal = $stmtJ->fetch(PDO::FETCH_ASSOC);

    $sqlK = "
        SELECT 
            k.*,
            a.nama AS nama_anak, a.nik, a.tanggal_lahir, a.jenis_kelamin,
            ot.nama_ibu, ot.no_telepon, ot.alamat AS alamat_orangtua,
            p.berat_badan, p.tinggi_badan, sg.status_tb_u, sg.status_bb_u, sg.status_risiko
        FROM kehadiran k
        JOIN anak a ON k.id_anak = a.id_anak
        LEFT JOIN orangtua ot ON a.id_orangtua = ot.id_orangtua
        LEFT JOIN pengukuran p ON (p.id_anak = a.id_anak AND p.tanggal_pengukuran = '{$jadwal['tanggal']}')
        LEFT JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
        WHERE k.id_jadwal = ?
        ORDER BY a.nama ASC
    ";
    $stmtK = $pdo->prepare($sqlK);
    $stmtK->execute([$id_jadwal]);
    $records = $stmtK->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'Presensi_Posyandu_' . preg_replace('/[^a-zA-Z0-9]/', '_', $jadwal['nama_posyandu']) . '_' . date('Ymd', strtotime($jadwal['tanggal'])) . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    $headers = [
        'No',
        'NIK Balita',
        'Nama Lengkap Balita',
        'Jenis Kelamin',
        'Tanggal Lahir',
        'Usia (Bulan)',
        'Nama Ibu',
        'No. Telepon',
        'Alamat Rumah',
        'Status Kehadiran',
        'Waktu Check-In',
        'Berat Badan (kg)',
        'Tinggi Badan (cm)',
        'Status TB/U (Stunting)',
        'Status BB/U',
        'Status Risiko',
        'Catatan Presensi',
    ];
    fputcsv($output, $headers);

    $no = 1;
    foreach ($records as $r) {
        $u_bln = Antropometri::hitungUsiaBulan($r['tanggal_lahir'], $jadwal['tanggal']);
        $row = [
            $no++,
            "'" . $r['nik'],
            $r['nama_anak'],
            $r['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan',
            date('d/m/Y', strtotime($r['tanggal_lahir'])),
            number_format($u_bln, 1),
            $r['nama_ibu'] ?: '',
            $r['no_telepon'] ?: '',
            $r['alamat_orangtua'] ?: '',
            $r['status_kehadiran'] ?: 'Belum Hadir',
            $r['waktu_kehadiran'] ? date('H:i', strtotime($r['waktu_kehadiran'])) : '',
            $r['berat_badan'] ? number_format($r['berat_badan'], 2) : '',
            $r['tinggi_badan'] ? number_format($r['tinggi_badan'], 1) : '',
            $r['status_tb_u'] ?: '',
            $r['status_bb_u'] ?: '',
            $r['status_risiko'] ?: '',
            $r['keterangan'] ?: '',
        ];
        fputcsv($output, $row);
    }
    fclose($output);
    exit;

} else {
    // Ekspor rekapitulasi daftar jadwal
    $where_clauses = ['1=1'];
    $params        = [];

    if ($search !== '') {
        $where_clauses[] = '(j.lokasi LIKE ? OR j.keterangan LIKE ? OR pos.nama_posyandu LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if ($filter_posyandu !== '') {
        $where_clauses[] = 'j.id_posyandu = ?';
        $params[] = $filter_posyandu;
    }

    $today = date('Y-m-d');
    if ($filter_status === 'upcoming') {
        $where_clauses[] = 'j.tanggal > ?';
        $params[] = $today;
    } elseif ($filter_status === 'today') {
        $where_clauses[] = 'j.tanggal = ?';
        $params[] = $today;
    } elseif ($filter_status === 'past') {
        $where_clauses[] = 'j.tanggal < ?';
        $params[] = $today;
    }

    if ($filter_bulan !== '') {
        $where_clauses[] = "DATE_FORMAT(j.tanggal, '%Y-%m') = ?";
        $params[] = $filter_bulan;
    }

    $where_sql = implode(' AND ', $where_clauses);

    $sql = "
        SELECT 
            j.*,
            pos.nama_posyandu,
            COUNT(k.id_kehadiran) AS total_sasaran,
            COUNT(CASE WHEN k.status_kehadiran = 'Hadir' THEN 1 END) AS total_hadir,
            COUNT(CASE WHEN k.status_kehadiran = 'Tidak Hadir' THEN 1 END) AS total_absen
        FROM jadwal_posyandu j
        JOIN posyandu pos ON j.id_posyandu = pos.id_posyandu
        LEFT JOIN kehadiran k ON j.id_jadwal = k.id_jadwal
        WHERE {$where_sql}
        GROUP BY j.id_jadwal
        ORDER BY j.tanggal DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jadwal_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // MODE 2.1: Print Bulk
    if ($format === 'print') {
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
          <meta charset="UTF-8">
          <title>Rekapitulasi Jadwal Kegiatan Posyandu — <?= e(APP_NAME) ?></title>
          <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
          <style>
            body { font-family: "Segoe UI", Arial, sans-serif; color: #1e293b; background: #fff; }
            .print-header { border-bottom: 2px solid #0f766e; padding-bottom: 12px; margin-bottom: 20px; }
            .table th { background-color: #f1f5f9 !important; font-size: 0.8rem; }
            .table td { font-size: 0.8rem; }
            @media print { .no-print { display: none !important; } body { padding: 0; } }
          </style>
        </head>
        <body class="p-4">
          <div class="container-fluid">
            <div class="no-print d-flex justify-content-between align-items-center mb-4 p-3 bg-light border rounded-3">
              <div>
                <strong>Pratinjau Rekapitulasi Jadwal Kegiatan Posyandu</strong>
                <span class="text-muted d-block small">Total <?= count($jadwal_list) ?> jadwal kegiatan.</span>
              </div>
              <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-primary btn-sm">Cetak / Simpan PDF</button>
                <button onclick="window.close()" class="btn btn-secondary btn-sm">Tutup</button>
              </div>
            </div>

            <div class="print-header d-flex justify-content-between align-items-end">
              <div>
                <h4 class="fw-bold text-success mb-1"><?= e(APP_NAME) ?></h4>
                <p class="text-muted mb-0 small">Laporan Rekapitulasi Pelaksanaan Kegiatan & Presensi Posyandu</p>
              </div>
              <div class="text-end small text-muted">
                <div>Tanggal: <?= date('d/m/Y H:i') ?></div>
              </div>
            </div>

            <table class="table table-bordered table-striped table-sm align-middle">
              <thead>
                <tr class="text-center align-middle">
                  <th>No</th>
                  <th>Tanggal</th>
                  <th>Waktu</th>
                  <th>Posyandu</th>
                  <th>Lokasi Kegiatan</th>
                  <th>Agenda / Keterangan</th>
                  <th>Sasaran</th>
                  <th>Hadir</th>
                  <th>Sweeping</th>
                  <th>Kehadiran (%)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($jadwal_list as $i => $j): 
                  $pct = $j['total_sasaran'] > 0 ? round(($j['total_hadir'] / $j['total_sasaran']) * 100) : 0;
                ?>
                  <tr>
                    <td class="text-center"><?= $i + 1 ?></td>
                    <td class="text-nowrap"><?= date('d/m/Y', strtotime($j['tanggal'])) ?></td>
                    <td class="text-center"><?= date('H:i', strtotime($j['waktu'])) ?></td>
                    <td><strong><?= e($j['nama_posyandu']) ?></strong></td>
                    <td><?= e($j['lokasi']) ?></td>
                    <td><?= e($j['keterangan']) ?></td>
                    <td class="text-center"><?= $j['total_sasaran'] ?></td>
                    <td class="text-center"><?= $j['total_hadir'] ?></td>
                    <td class="text-center"><?= $j['total_absen'] ?></td>
                    <td class="text-center fw-bold"><?= $pct ?>%</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </body>
        </html>
        <?php
        exit;
    }

    // MODE 2.2: CSV Bulk
    $filename = 'Rekapitulasi_Jadwal_Posyandu_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    $headers = [
        'No',
        'ID Jadwal',
        'Tanggal Kegiatan',
        'Waktu',
        'Nama Posyandu',
        'Lokasi Kegiatan',
        'Agenda / Keterangan',
        'Total Sasaran Balita',
        'Jumlah Hadir',
        'Jumlah Tidak Hadir',
        'Persentase Kehadiran (%)',
    ];
    fputcsv($output, $headers);

    $no = 1;
    foreach ($jadwal_list as $j) {
        $pct = $j['total_sasaran'] > 0 ? round(($j['total_hadir'] / $j['total_sasaran']) * 100) : 0;
        $row = [
            $no++,
            $j['id_jadwal'],
            date('d/m/Y', strtotime($j['tanggal'])),
            date('H:i', strtotime($j['waktu'])),
            $j['nama_posyandu'],
            $j['lokasi'] ?: '',
            $j['keterangan'] ?: '',
            $j['total_sasaran'],
            $j['total_hadir'],
            $j['total_absen'],
            $pct . '%',
        ];
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
