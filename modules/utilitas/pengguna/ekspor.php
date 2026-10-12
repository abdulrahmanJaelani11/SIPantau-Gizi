<?php
/**
 * Modul Utilitas — Ekspor Data Pengguna
 * =======================================
 * Mengekspor data pengguna ke format CSV (kompatibel Excel) atau tampilan Cetak / Print PDF.
 * Menghormati filter pencarian, role, dan status yang sedang aktif.
 */

require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan hanya role Admin atau yang memiliki hak akses menu pengguna yang dapat mengekspor
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
$user_role_nama = $_SESSION['user_role_nama'] ?? '';
if (!bolehAkses($user_role_id, 'pengguna') && strcasecmp($user_role_nama, 'Admin') !== 0 && $user_role_id !== 4) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengekspor data pengguna.');
    redirect(base_url('modules/dashboard/index.php'));
}

// ============================================================
// Parameter Filter & Format
// ============================================================
$search        = trim($_GET['search'] ?? '');
$filter_role   = trim($_GET['role'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$format        = strtolower(trim($_GET['format'] ?? 'csv'));

// ============================================================
// Query Data Pengguna Sesuai Filter
// ============================================================
$where_clauses = ['1=1'];
$params        = [];

if ($search !== '') {
    $where_clauses[] = '(u.nama LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($filter_role !== '') {
    $where_clauses[] = 'u.id_role = ?';
    $params[] = $filter_role;
}

if ($filter_status !== '') {
    $where_clauses[] = 'LOWER(u.status) = ?';
    $params[] = strtolower($filter_status);
}

$where_sql = implode(' AND ', $where_clauses);
$users = [];

try {
    $sql = "
        SELECT u.id_user, u.nama, u.username, u.email, u.no_telepon, u.status, u.created_at,
               r.nama_role,
               p.nama_posyandu, k.nama_puskesmas, w.nama_wilayah
        FROM user u
        LEFT JOIN role r ON u.id_role = r.id_role
        LEFT JOIN posyandu p ON u.id_posyandu = p.id_posyandu
        LEFT JOIN puskesmas k ON u.id_puskesmas = k.id_puskesmas
        LEFT JOIN wilayah w ON u.id_wilayah = w.id_wilayah
        WHERE {$where_sql}
        ORDER BY u.id_user ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat data pengguna untuk ekspor: ' . $e->getMessage());
    redirect(base_url('modules/utilitas/pengguna/index.php'));
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
      <title>Cetak Data Pengguna — <?= e(APP_NAME) ?></title>
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
        <!-- Toolbar Tidak Tercetak -->
        <div class="no-print d-flex justify-content-between align-items-center mb-4 p-3 bg-light border rounded-3">
          <div>
            <strong>Pratinjau Cetak Data Pengguna</strong>
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
            <p class="text-muted mb-0 small">Sistem Informasi Pemantauan Gizi — Laporan Pengguna Sistem</p>
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
          <span>Role: <strong><?= $filter_role !== '' ? 'ID ' . e($filter_role) : 'Semua Role' ?></strong> | </span>
          <span>Status: <strong><?= $filter_status !== '' ? ucfirst(e($filter_status)) : 'Semua Status' ?></strong> | </span>
          <span>Total Data: <strong><?= count($users) ?> Pengguna</strong></span>
        </div>

        <!-- Tabel Data -->
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-sm align-middle small">
            <thead>
              <tr>
                <th style="width: 40px;" class="text-center">#</th>
                <th>Nama Lengkap</th>
                <th>Username</th>
                <th>Email</th>
                <th>No. Telepon</th>
                <th>Role</th>
                <th>Penugasan</th>
                <th class="text-center">Status</th>
                <th>Terdaftar</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($users)): ?>
                <tr>
                  <td colspan="9" class="text-center py-3 text-muted">Tidak ada data pengguna ditemukan.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($users as $i => $u): 
                  $penugasan = [];
                  if (!empty($u['nama_posyandu'])) $penugasan[] = $u['nama_posyandu'];
                  if (!empty($u['nama_puskesmas'])) $penugasan[] = $u['nama_puskesmas'];
                  if (!empty($u['nama_wilayah']))   $penugasan[] = $u['nama_wilayah'];
                  $penugasan_text = !empty($penugasan) ? implode(', ', $penugasan) : '-';
                ?>
                  <tr>
                    <td class="text-center"><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= e($u['nama']) ?></td>
                    <td>@<?= e($u['username']) ?></td>
                    <td><?= e($u['email'] ?: '-') ?></td>
                    <td><?= e($u['no_telepon'] ?: '-') ?></td>
                    <td><?= e($u['nama_role'] ?: '-') ?></td>
                    <td><?= e($penugasan_text) ?></td>
                    <td class="text-center">
                      <span class="badge <?= strtolower($u['status']) === 'aktif' ? 'bg-success' : 'bg-danger' ?>">
                        <?= ucfirst(e($u['status'])) ?>
                      </span>
                    </td>
                    <td><?= !empty($u['created_at']) ? date('d/m/Y', strtotime($u['created_at'])) : '-' ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Tanda Tangan / Footer Cetak -->
        <div class="row mt-5 pt-3">
          <div class="col-8">
            <small class="text-muted">Dokumen ini digenerate secara otomatis oleh sistem <?= e(APP_NAME) ?>.</small>
          </div>
          <div class="col-4 text-center small">
            <p class="mb-5">Petugas Administrator,</p>
            <p class="fw-bold mb-0 text-decoration-underline"><?= e($_SESSION['user_nama'] ?? 'Administrator') ?></p>
            <p class="text-muted mb-0">NIP / ID: <?= (int)($_SESSION['user_id'] ?? 1) ?></p>
          </div>
        </div>
      </div>

      <script>
        // Jalankan print dialog otomatis saat halaman dimuat jika diminta
        window.addEventListener('load', function () {
          // window.print();
        });
      </script>
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

$filename = 'Data_Pengguna_SiPantau_Gizi_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Tulis UTF-8 Byte Order Mark (BOM) agar karakter Unicode terbaca sempurna di Microsoft Excel
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Header Kolom CSV
$headers = [
    'No',
    'ID Pengguna',
    'Nama Lengkap',
    'Username',
    'Email',
    'No. Telepon',
    'Role / Peran',
    'Posyandu',
    'Puskesmas',
    'Wilayah / Kelurahan',
    'Status Akun',
    'Tanggal Terdaftar',
];
fputcsv($output, $headers);

// Baris Data
$no = 1;
foreach ($users as $u) {
    $row = [
        $no++,
        $u['id_user'],
        $u['nama'],
        $u['username'],
        $u['email'] ?: '',
        $u['no_telepon'] ?: '',
        $u['nama_role'] ?: '',
        $u['nama_posyandu'] ?: '',
        $u['nama_puskesmas'] ?: '',
        $u['nama_wilayah'] ?: '',
        ucfirst($u['status']),
        !empty($u['created_at']) ? date('d/m/Y H:i', strtotime($u['created_at'])) : '',
    ];
    fputcsv($output, $row);
}

fclose($output);
exit;
