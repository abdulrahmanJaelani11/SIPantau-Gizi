<?php
/**
 * Master Data — Ekspor Data Puskesmas
 * =====================================
 * Mengekspor data Puskesmas ke file CSV (Excel) atau format Cetak / Simpan PDF.
 */

$current_slug = 'puskesmas';
require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan memiliki hak akses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'puskesmas')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengekspor data Puskesmas.');
    redirect(base_url('modules/dashboard/index.php'));
}

// Parameter Filter
$search         = trim($_GET['search'] ?? '');
$filter_wilayah = trim($_GET['wilayah'] ?? '');
$format         = strtolower(trim($_GET['format'] ?? 'csv'));

// Query data
$where_clauses = ['1=1'];
$params        = [];

if ($search !== '') {
    $where_clauses[] = '(p.nama_puskesmas LIKE ? OR p.alamat LIKE ? OR p.no_telepon LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($filter_wilayah !== '') {
    $where_clauses[] = 'p.id_wilayah = ?';
    $params[] = $filter_wilayah;
}

$where_sql = implode(' AND ', $where_clauses);
$puskesmas_list = [];

try {
    $sql = "
        SELECT 
            p.id_puskesmas,
            p.nama_puskesmas,
            p.alamat,
            p.no_telepon,
            w.nama_wilayah,
            w.kecamatan,
            w.kabupaten,
            COUNT(DISTINCT pos.id_posyandu) AS posyandu_count,
            COUNT(DISTINCT u.id_user) AS petugas_count
        FROM puskesmas p
        LEFT JOIN wilayah w ON p.id_wilayah = w.id_wilayah
        LEFT JOIN posyandu pos ON p.id_puskesmas = pos.id_puskesmas
        LEFT JOIN user u ON p.id_puskesmas = u.id_puskesmas
        WHERE {$where_sql}
        GROUP BY p.id_puskesmas, p.nama_puskesmas, p.alamat, p.no_telepon, w.nama_wilayah, w.kecamatan, w.kabupaten
        ORDER BY p.id_puskesmas ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $puskesmas_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat data untuk ekspor: ' . $e->getMessage());
    redirect(base_url('modules/master/puskesmas/index.php'));
}

// ============================================================
// Format 1: Cetak / Print PDF View
// ============================================================
if ($format === 'print') {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Laporan Data Puskesmas — <?= e(APP_NAME) ?></title>
      <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
      <link rel="stylesheet" href="<?= asset('vendors/bootstrap-icons/bootstrap-icons.css') ?>">
      <style>
        body { font-family: "Segoe UI", Arial, sans-serif; color: #1f2937; background: #fff; }
        .print-header { border-bottom: 2px solid #2563eb; padding-bottom: 12px; margin-bottom: 20px; }
        .table th { background-color: #f8fafc !important; }
        @media print {
          .no-print { display: none !important; }
          body { padding: 0; }
        }
      </style>
    </head>
    <body class="p-4">
      <div class="container-fluid">
        <!-- Toolbar Cetak -->
        <div class="no-print d-flex justify-content-between align-items-center mb-4 p-3 bg-light border rounded-3">
          <div>
            <strong>Pratinjau Cetak Data Puskesmas</strong>
            <span class="text-muted d-block small">Gunakan tombol di kanan untuk mencetak ke kertas atau simpan sebagai PDF.</span>
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
            <h3 class="fw-bold text-primary mb-0"><?= e(APP_NAME) ?></h3>
            <p class="text-muted mb-0 small">Sistem Informasi Pemantauan Gizi — Master Data Puskesmas</p>
          </div>
          <div class="text-end small text-muted">
            <div>Tanggal Cetak: <strong><?= date('d/m/Y H:i') ?> WIB</strong></div>
            <div>Dicetak oleh: <strong><?= e($_SESSION['user_nama'] ?? 'Admin') ?></strong></div>
          </div>
        </div>

        <!-- Ringkasan Filter -->
        <div class="mb-3 small text-muted">
          <span>Filter: </span>
          <span>Pencarian: <strong><?= $search !== '' ? e($search) : 'Semua' ?></strong> | </span>
          <span>Wilayah: <strong><?= $filter_wilayah !== '' ? 'ID ' . e($filter_wilayah) : 'Semua Wilayah' ?></strong> | </span>
          <span>Total: <strong><?= count($puskesmas_list) ?> Puskesmas</strong></span>
        </div>

        <!-- Tabel Data -->
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-sm align-middle small">
            <thead>
              <tr>
                <th style="width: 40px;" class="text-center">#</th>
                <th>Nama Puskesmas</th>
                <th>Wilayah Kerja</th>
                <th>Kecamatan</th>
                <th>Kabupaten/Kota</th>
                <th>Alamat Lengkap</th>
                <th>No. Telepon</th>
                <th class="text-center">Posyandu</th>
                <th class="text-center">Petugas</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($puskesmas_list)): ?>
                <tr>
                  <td colspan="9" class="text-center py-3 text-muted">Tidak ada data Puskesmas ditemukan.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($puskesmas_list as $i => $p): ?>
                  <tr>
                    <td class="text-center"><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= e($p['nama_puskesmas']) ?></td>
                    <td><?= e($p['nama_wilayah'] ?: '-') ?></td>
                    <td><?= e($p['kecamatan'] ?: '-') ?></td>
                    <td><?= e($p['kabupaten'] ?: '-') ?></td>
                    <td><?= e($p['alamat'] ?: '-') ?></td>
                    <td><?= e($p['no_telepon'] ?: '-') ?></td>
                    <td class="text-center"><?= (int)$p['posyandu_count'] ?></td>
                    <td class="text-center"><?= (int)$p['petugas_count'] ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Footer Dokumen -->
        <div class="row mt-5 pt-3">
          <div class="col-8">
            <small class="text-muted">Dokumen resmi master faskes pembina sistem SiPantau Gizi.</small>
          </div>
          <div class="col-4 text-center small">
            <p class="mb-5">Petugas Administrator,</p>
            <p class="fw-bold mb-0 text-decoration-underline"><?= e($_SESSION['user_nama'] ?? 'Administrator') ?></p>
            <p class="text-muted mb-0">NIP / ID: <?= (int)($_SESSION['user_id'] ?? 1) ?></p>
          </div>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================
// Format 2: Ekspor CSV / Microsoft Excel
// ============================================================
if (ob_get_level()) {
    ob_end_clean();
}

$filename = 'Data_Puskesmas_SiPantau_Gizi_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Header Kolom
$headers = [
    'No',
    'ID Puskesmas',
    'Nama Puskesmas',
    'Wilayah Kerja',
    'Kecamatan',
    'Kabupaten / Kota',
    'Alamat Lengkap',
    'No. Telepon',
    'Jumlah Posyandu Binaan',
    'Jumlah Petugas Terdaftar',
];
fputcsv($output, $headers);

// Baris Data
$no = 1;
foreach ($puskesmas_list as $p) {
    $row = [
        $no++,
        $p['id_puskesmas'],
        $p['nama_puskesmas'],
        $p['nama_wilayah'] ?: '',
        $p['kecamatan'] ?: '',
        $p['kabupaten'] ?: '',
        $p['alamat'] ?: '',
        $p['no_telepon'] ?: '',
        (int)$p['posyandu_count'],
        (int)$p['petugas_count'],
    ];
    fputcsv($output, $row);
}

fclose($output);
exit;
