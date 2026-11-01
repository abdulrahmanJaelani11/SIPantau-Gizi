<?php
/**
 * Modul Daftar Pantau & Intervensi Gizi — SiPantau Gizi
 * =========================================================================
 * Desain dan layout diselaraskan 100% dengan Modul Pengukuran Gizi.
 * 1. Menampilkan daftar anak berisiko otomatis (deteksi stunting, gizi buruk/kurang).
 * 2. Form pencatatan intervensi tindak lanjut (konseling, PMT, rujukan).
 * 3. Update status tindak lanjut (Baru / Ditindaklanjuti / Selesai).
 * 4. Ekspor data ke format Excel (.xlsx), CSV, dan Cetak PDF.
 */

$current_slug = 'daftar-pantau';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';
require_once ROOT_PATH . '/src/Antropometri.php';

// Pastikan user memiliki hak akses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
$user_id      = (int)($_SESSION['user_id'] ?? 1);

if (!bolehAkses($user_role_id, 'daftar-pantau')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengakses Daftar Pantau.');
    redirect(base_url('modules/dashboard/index.php'));
}

// Konfigurasi Halaman & Breadcrumbs
$page_title  = 'Daftar Pantau Balita Berisiko';
$breadcrumbs = [
    'Data & Laporan' => '#',
    'Daftar Pantau'  => '',
];

// ============================================================
// 1. SINKRONISASI OTOMATIS ATURAN PERINGATAN DINI
// ============================================================
if ($pdo !== null) {
    try {
        $syncSql = "
            INSERT INTO pemantauan (id_anak, id_pengukuran, tanggal_mulai, status_tindak_lanjut, keterangan)
            SELECT 
                p.id_anak, 
                p.id_pengukuran, 
                p.tanggal_pengukuran, 
                'Baru', 
                CONCAT('Peringatan Dini: ', sg.status_risiko, ' [TB/U: ', sg.status_tb_u, ', BB/U: ', sg.status_bb_u, ', BB/TB: ', sg.status_bb_tb, ']')
            FROM pengukuran p
            JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
            LEFT JOIN pemantauan pm ON p.id_pengukuran = pm.id_pengukuran
            WHERE pm.id_pemantauan IS NULL
              AND (
                  sg.status_risiko IN ('Butuh Rujukan Segera', 'Berisiko')
                  OR sg.status_tb_u IN ('Sangat Pendek', 'Pendek')
                  OR sg.status_bb_u IN ('Gizi Buruk', 'Gizi Kurang')
                  OR sg.status_bb_tb IN ('Gizi Buruk', 'Gizi Kurang', 'Obesitas')
              )
        ";
        $pdo->exec($syncSql);
    } catch (PDOException $e) {
        // Log query error silent
    }
}

// ============================================================
// 2. PROSES FORM (POST ACTION)
// ============================================================
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    // A. Form Input Catatan Intervensi
    if ($action === 'simpan_intervensi') {
        if (!verify_csrf_token()) {
            set_flash('danger', 'Token keamanan CSRF tidak valid. Silakan coba kembali.');
            redirect(base_url('modules/daftar-pantau/index.php'));
        }

        $id_pemantauan = filter_input(INPUT_POST, 'id_pemantauan', FILTER_VALIDATE_INT);
        $tanggal       = trim($_POST['tanggal_intervensi'] ?? date('Y-m-d'));
        $jenis         = trim($_POST['jenis_intervensi'] ?? '');
        $catatan       = trim($_POST['catatan'] ?? '');
        $status_update = trim($_POST['status_update'] ?? 'Ditindaklanjuti');

        if (!$id_pemantauan || empty($jenis) || empty($catatan)) {
            set_flash('warning', 'Harap isi seluruh field intervensi yang wajib diisi.');
            redirect(base_url('modules/daftar-pantau/index.php'));
        }

        try {
            $pdo->beginTransaction();

            $stmtInsert = $pdo->prepare("
                INSERT INTO catatan_intervensi (id_pemantauan, id_user, tanggal, jenis_intervensi, catatan)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtInsert->execute([$id_pemantauan, $user_id, $tanggal, $jenis, $catatan]);

            $stmtUpdate = $pdo->prepare("
                UPDATE pemantauan 
                SET status_tindak_lanjut = ?,
                    tanggal_tindak_lanjut = ?
                WHERE id_pemantauan = ?
            ");
            $stmtUpdate->execute([$status_update, $tanggal, $id_pemantauan]);

            $pdo->commit();
            set_flash('success', "Catatan intervensi ({$jenis}) berhasil disimpan. Status pemantauan diperbarui menjadi <strong>{$status_update}</strong>.");
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('danger', 'Gagal menyimpan catatan intervensi: ' . $e->getMessage());
        }

        redirect(base_url('modules/daftar-pantau/index.php'));
    }

    // B. Form Cepat Update Status Tindak Lanjut
    if ($action === 'update_status') {
        if (!verify_csrf_token()) {
            set_flash('danger', 'Token keamanan CSRF tidak valid.');
            redirect(base_url('modules/daftar-pantau/index.php'));
        }

        $id_pemantauan = filter_input(INPUT_POST, 'id_pemantauan', FILTER_VALIDATE_INT);
        $status_baru   = trim($_POST['status_tindak_lanjut'] ?? '');
        $catatan_status = trim($_POST['catatan_status'] ?? '');
        $tanggal_now   = date('Y-m-d');

        if (!$id_pemantauan || !in_array($status_baru, ['Baru', 'Ditindaklanjuti', 'Selesai'], true)) {
            set_flash('warning', 'Status tindak lanjut yang dipilih tidak valid.');
            redirect(base_url('modules/daftar-pantau/index.php'));
        }

        try {
            $pdo->beginTransaction();

            $stmtStatus = $pdo->prepare("
                UPDATE pemantauan 
                SET status_tindak_lanjut = ?,
                    tanggal_tindak_lanjut = ?
                WHERE id_pemantauan = ?
            ");
            $stmtStatus->execute([$status_baru, $tanggal_now, $id_pemantauan]);

            if (!empty($catatan_status)) {
                $stmtLog = $pdo->prepare("
                    INSERT INTO catatan_intervensi (id_pemantauan, id_user, tanggal, jenis_intervensi, catatan)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmtLog->execute([
                    $id_pemantauan, 
                    $user_id, 
                    $tanggal_now, 
                    'Update Status: ' . $status_baru, 
                    $catatan_status
                ]);
            }

            $pdo->commit();
            set_flash('success', "Status tindak lanjut berhasil diperbarui menjadi <strong>{$status_baru}</strong>.");
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('danger', 'Gagal memperbarui status: ' . $e->getMessage());
        }

        redirect(base_url('modules/daftar-pantau/index.php'));
    }

    // C. Pemicu Sinkronisasi Deteksi Dini Manual
    if ($action === 'manual_sync') {
        set_flash('info', "Sinkronisasi selesai diproses. Sistem telah memverifikasi seluruh riwayat status gizi berisiko.");
        redirect(base_url('modules/daftar-pantau/index.php'));
    }
}

// ============================================================
// 3. FILTER & PARAMETER PENCARIAN
// ============================================================
$search          = trim($_GET['search'] ?? '');
$filter_posyandu = trim($_GET['posyandu'] ?? '');
$filter_status   = trim($_GET['status'] ?? '');
$filter_risiko   = trim($_GET['risiko'] ?? '');

$posyandu_list = [];
try {
    if ($pdo !== null) {
        $posyandu_list = $pdo->query("SELECT id_posyandu, nama_posyandu FROM posyandu ORDER BY nama_posyandu ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $posyandu_list = [];
}

// ============================================================
// 4. STATISTIK METRIK RINGKASAN (Sesuai Pengukuran Gizi)
// ============================================================
$total_kasus   = 0;
$count_baru    = 0;
$count_tindak  = 0;
$count_selesai = 0;

try {
    if ($pdo !== null) {
        $stats = $pdo->query("
            SELECT 
                COUNT(*) AS total,
                COUNT(CASE WHEN status_tindak_lanjut = 'Baru' OR status_tindak_lanjut IS NULL THEN 1 END) AS baru,
                COUNT(CASE WHEN status_tindak_lanjut = 'Ditindaklanjuti' THEN 1 END) AS tindak,
                COUNT(CASE WHEN status_tindak_lanjut = 'Selesai' THEN 1 END) AS selesai
            FROM pemantauan
        ")->fetch(PDO::FETCH_ASSOC);

        if ($stats) {
            $total_kasus   = (int)$stats['total'];
            $count_baru    = (int)$stats['baru'];
            $count_tindak  = (int)$stats['tindak'];
            $count_selesai = (int)$stats['selesai'];
        }
    }
} catch (PDOException $e) {
    // Ignore error
}

// ============================================================
// 5. QUERY DATA DAFTAR PANTAU
// ============================================================
$where_clauses = ['1=1'];
$params        = [];

if ($search !== '') {
    $where_clauses[] = '(a.nama LIKE ? OR a.nik LIKE ? OR ot.nama_ibu LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($filter_posyandu !== '') {
    $where_clauses[] = 'a.id_posyandu = ?';
    $params[] = $filter_posyandu;
}

if ($filter_status !== '') {
    if ($filter_status === 'Baru') {
        $where_clauses[] = "(pm.status_tindak_lanjut = 'Baru' OR pm.status_tindak_lanjut IS NULL)";
    } else {
        $where_clauses[] = 'pm.status_tindak_lanjut = ?';
        $params[] = $filter_status;
    }
}

if ($filter_risiko !== '') {
    if ($filter_risiko === 'stunting') {
        $where_clauses[] = "sg.status_tb_u IN ('Sangat Pendek', 'Pendek')";
    } elseif ($filter_risiko === 'wasting') {
        $where_clauses[] = "sg.status_bb_tb IN ('Gizi Buruk', 'Gizi Kurang')";
    } elseif ($filter_risiko === 'underweight') {
        $where_clauses[] = "sg.status_bb_u IN ('Gizi Buruk', 'Gizi Kurang')";
    } else {
        $where_clauses[] = 'sg.status_risiko = ?';
        $params[] = $filter_risiko;
    }
}

$where_sql = implode(' AND ', $where_clauses);
$daftar_pantau = [];
$riwayat_intervensi_map = [];

try {
    if ($pdo !== null) {
        $sql = "
            SELECT 
                pm.id_pemantauan,
                pm.id_anak,
                pm.id_pengukuran,
                pm.tanggal_mulai,
                COALESCE(pm.status_tindak_lanjut, 'Baru') AS status_tindak_lanjut,
                pm.tanggal_tindak_lanjut,
                pm.keterangan AS catatan_sistem,
                a.nama AS nama_anak,
                a.nik,
                a.tanggal_lahir,
                a.jenis_kelamin,
                ot.nama_ibu,
                ot.nama_ayah,
                ot.no_telepon,
                pos.nama_posyandu,
                pos.dusun_rw,
                p.tanggal_pengukuran,
                p.berat_badan,
                p.tinggi_badan,
                p.lingkar_kepala,
                sg.zscore_bb_u,
                sg.zscore_tb_u,
                sg.zscore_bb_tb,
                sg.zscore_imt_u,
                sg.status_bb_u,
                sg.status_tb_u,
                sg.status_bb_tb,
                sg.status_imt_u,
                sg.status_risiko,
                (SELECT COUNT(*) FROM catatan_intervensi ci WHERE ci.id_pemantauan = pm.id_pemantauan) AS total_intervensi,
                (SELECT ci_sub.jenis_intervensi FROM catatan_intervensi ci_sub WHERE ci_sub.id_pemantauan = pm.id_pemantauan ORDER BY ci_sub.id_intervensi DESC LIMIT 1) AS jenis_terakhir,
                (SELECT ci_sub.tanggal FROM catatan_intervensi ci_sub WHERE ci_sub.id_pemantauan = pm.id_pemantauan ORDER BY ci_sub.id_intervensi DESC LIMIT 1) AS tanggal_terakhir,
                (SELECT u.nama FROM catatan_intervensi ci_sub JOIN user u ON ci_sub.id_user = u.id_user WHERE ci_sub.id_pemantauan = pm.id_pemantauan ORDER BY ci_sub.id_intervensi DESC LIMIT 1) AS petugas_terakhir
            FROM pemantauan pm
            JOIN anak a ON pm.id_anak = a.id_anak
            JOIN orangtua ot ON a.id_orangtua = ot.id_orangtua
            JOIN posyandu pos ON a.id_posyandu = pos.id_posyandu
            JOIN pengukuran p ON pm.id_pengukuran = p.id_pengukuran
            JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
            WHERE {$where_sql}
            ORDER BY 
                CASE COALESCE(pm.status_tindak_lanjut, 'Baru')
                    WHEN 'Baru' THEN 1
                    WHEN 'Ditindaklanjuti' THEN 2
                    WHEN 'Selesai' THEN 3
                    ELSE 4
                END,
                pm.tanggal_mulai DESC,
                pm.id_pemantauan DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $daftar_pantau = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($daftar_pantau)) {
            $pemantauan_ids = array_column($daftar_pantau, 'id_pemantauan');
            $placeholders = implode(',', array_fill(0, count($pemantauan_ids), '?'));
            
            $stmtRiwayat = $pdo->prepare("
                SELECT ci.*, u.nama AS nama_petugas, r.nama_role
                FROM catatan_intervensi ci
                JOIN user u ON ci.id_user = u.id_user
                LEFT JOIN role r ON u.id_role = r.id_role
                WHERE ci.id_pemantauan IN ({$placeholders})
                ORDER BY ci.tanggal DESC, ci.id_intervensi DESC
            ");
            $stmtRiwayat->execute($pemantauan_ids);
            $all_intervensi = $stmtRiwayat->fetchAll(PDO::FETCH_ASSOC);

            foreach ($all_intervensi as $ci) {
                $riwayat_intervensi_map[$ci['id_pemantauan']][] = $ci;
            }
        }
    }
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat data daftar pantau: ' . $e->getMessage());
}

$export_query = http_build_query(array_filter([
    'search'   => $search,
    'posyandu' => $filter_posyandu,
    'status'   => $filter_status,
    'risiko'   => $filter_risiko,
]));

// CSS Khusus untuk menjamin tampilan rapi dan kontras warna sempurna di Light & Dark Mode
$extra_css = '
<style>
  .child-avatar-badge {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    flex-shrink: 0;
  }
  .child-name-link {
    color: var(--admin-text) !important;
    font-size: 0.92rem;
    font-weight: 600;
  }
  .child-name-link:hover {
    color: var(--admin-primary) !important;
  }
  /* Dark mode overrides khusus tabel pantau */
  html[data-theme="dark"] #pantauTable thead th {
    background-color: var(--admin-surface-soft, #1e293b) !important;
    color: #cbd5e1 !important;
    border-color: var(--admin-border, #334155) !important;
  }
  html[data-theme="dark"] .table-light {
    background-color: var(--admin-surface-soft, #1e293b) !important;
    color: #cbd5e1 !important;
  }
  html[data-theme="dark"] .child-name-link {
    color: #f8fafc !important;
  }
  html[data-theme="dark"] .child-name-link:hover {
    color: #60a5fa !important;
  }
  html[data-theme="dark"] .modal-content {
    background-color: var(--admin-surface, #0f172a) !important;
    color: var(--admin-text, #f1f5f9) !important;
    border-color: var(--admin-border, #334155) !important;
  }
  html[data-theme="dark"] .modal-header.bg-light {
    background-color: var(--admin-surface-soft, #1e293b) !important;
    color: var(--admin-text, #f1f5f9) !important;
    border-color: var(--admin-border, #334155) !important;
  }
</style>
';

// Include Header Layout
require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman (Sesuai Pengukuran Gizi) -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-clipboard2-pulse text-danger" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Peringatan Dini & Intervensi Gizi</p>
      <h1 class="h3 mb-1">Daftar Pantau Balita Berisiko</h1>
      <p class="text-muted mb-0">Deteksi otomatis balita berisiko stunting, wasting, dan gizi buruk serta pemantauan catatan intervensi.</p>
    </div>
  </div>
  <div class="heading-actions d-flex gap-2">
    <!-- Tombol Sinkronisasi Deteksi Dini -->
    <form method="POST" class="d-inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="manual_sync">
      <button type="submit" class="btn btn-outline-secondary btn-sm" title="Pindai ulang dan sinkronkan balita berisiko">
        <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i> Sinkronkan
      </button>
    </form>

    <!-- Dropdown Ekspor Data -->
    <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-download me-1" aria-hidden="true"></i> Ekspor Data
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li><h6 class="dropdown-header small">Pilihan Format</h6></li>
        <li>
          <a class="dropdown-item small" href="<?= base_url('modules/daftar-pantau/ekspor.php?format=xlsx' . (!empty($export_query) ? '&' . $export_query : '')) ?>">
            <i class="bi bi-file-earmark-excel text-success me-2"></i> Ekspor ke Excel (.xlsx)
          </a>
        </li>
        <li>
          <a class="dropdown-item small" href="<?= base_url('modules/daftar-pantau/ekspor.php?format=csv' . (!empty($export_query) ? '&' . $export_query : '')) ?>">
            <i class="bi bi-file-earmark-spreadsheet text-muted me-2"></i> Ekspor ke CSV (.csv)
          </a>
        </li>
        <li>
          <a class="dropdown-item small" target="_blank" href="<?= base_url('modules/daftar-pantau/ekspor.php?format=print' . (!empty($export_query) ? '&' . $export_query : '')) ?>">
            <i class="bi bi-printer text-primary me-2"></i> Cetak Rekapitulasi / PDF
          </a>
        </li>
      </ul>
    </div>
  </div>
</div>

<!-- Kartu Metrik Ringkasan (Desain Persis Pengukuran Gizi) -->
<section class="row g-3 mt-1" aria-label="Ringkasan Kasus Berisiko">
  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-primary">
      <div class="metric-top">
        <span class="metric-label">Total Balita Berisiko</span>
        <span class="metric-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_kasus) ?></div>
      <div class="metric-meta">
        <span class="text-muted">Teridentifikasi via aturan deteksi</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-danger">
      <div class="metric-top">
        <span class="metric-label">Kasus Baru</span>
        <span class="metric-icon"><i class="bi bi-bell-fill" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($count_baru) ?></div>
      <div class="metric-meta">
        <span class="text-danger fw-semibold">Menunggu Intervensi</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-warning">
      <div class="metric-top">
        <span class="metric-label">Sedang Ditindaklanjuti</span>
        <span class="metric-icon"><i class="bi bi-heart-pulse-fill" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($count_tindak) ?></div>
      <div class="metric-meta">
        <span class="text-warning">Dalam program PMT/Konseling</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-success">
      <div class="metric-top">
        <span class="metric-label">Selesai / Teratasi</span>
        <span class="metric-icon"><i class="bi bi-check-circle" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($count_selesai) ?></div>
      <div class="metric-meta">
        <span class="text-success">Kondisi gizi membaik/normal</span>
      </div>
    </article>
  </div>
</section>

<!-- Panel Tabel Pemantauan (Desain Persis Pengukuran Gizi) -->
<section class="panel mt-3">
  <div class="panel-header flex-wrap gap-2">
    <div>
      <h2 class="h5 mb-1 section-title">
        <i class="bi bi-table text-primary me-2" aria-hidden="true"></i>
        <span>Data Pemantauan & Tindak Lanjut Anak</span>
      </h2>
      <p class="text-muted mb-0">Total <?= count($daftar_pantau) ?> anak berisiko terdeteksi.</p>
    </div>

    <!-- Form Filter & Pencarian (Inline di Header seperti Pengukuran Gizi) -->
    <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2">
      <div class="input-group input-group-sm" style="min-width: 220px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" name="search" class="form-control"
               placeholder="Cari balita, NIK, orang tua..."
               value="<?= e($search) ?>" aria-label="Cari balita">
      </div>

      <select name="posyandu" class="form-select form-select-sm" style="width: auto;" aria-label="Filter posyandu">
        <option value="">Semua Posyandu</option>
        <?php foreach ($posyandu_list as $pos): ?>
          <option value="<?= (int)$pos['id_posyandu'] ?>" <?= (string)$filter_posyandu === (string)$pos['id_posyandu'] ? 'selected' : '' ?>>
            <?= e($pos['nama_posyandu']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="status" class="form-select form-select-sm" style="width: auto;" aria-label="Filter status tindak lanjut">
        <option value="">Status Tindak Lanjut (Semua)</option>
        <option value="Baru" <?= ($filter_status === 'Baru') ? 'selected' : '' ?>>Baru (Menunggu)</option>
        <option value="Ditindaklanjuti" <?= ($filter_status === 'Ditindaklanjuti') ? 'selected' : '' ?>>Ditindaklanjuti</option>
        <option value="Selesai" <?= ($filter_status === 'Selesai') ? 'selected' : '' ?>>Selesai</option>
      </select>

      <select name="risiko" class="form-select form-select-sm" style="width: auto;" aria-label="Filter risiko">
        <option value="">Status Risiko (Semua)</option>
        <option value="Butuh Rujukan Segera" <?= ($filter_risiko === 'Butuh Rujukan Segera') ? 'selected' : '' ?>>Butuh Rujukan Segera</option>
        <option value="Berisiko" <?= ($filter_risiko === 'Berisiko') ? 'selected' : '' ?>>Berisiko</option>
        <option value="stunting" <?= ($filter_risiko === 'stunting') ? 'selected' : '' ?>>Stunting (TB/U)</option>
        <option value="wasting" <?= ($filter_risiko === 'wasting') ? 'selected' : '' ?>>Wasting (BB/TB)</option>
        <option value="underweight" <?= ($filter_risiko === 'underweight') ? 'selected' : '' ?>>Underweight (BB/U)</option>
      </select>

      <button type="submit" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-funnel me-1"></i> Filter
      </button>

      <?php if ($search !== '' || $filter_posyandu !== '' || $filter_status !== '' || $filter_risiko !== ''): ?>
        <a href="<?= base_url('modules/daftar-pantau/index.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset filter">
          <i class="bi bi-x-circle me-1"></i> Reset
        </a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0" id="pantauTable">
      <thead class="table-light">
        <tr>
          <th scope="col" style="min-width: 220px;">Data Balita</th>
          <th scope="col" style="min-width: 120px;">Tgl Ukur</th>
          <th scope="col" style="min-width: 140px;">Antropometri</th>
          <th scope="col" style="min-width: 140px;">TB/U (Stunting)</th>
          <th scope="col" style="min-width: 140px;">BB/U (Berat)</th>
          <th scope="col" style="min-width: 140px;">BB/TB (Wasting)</th>
          <th scope="col" style="min-width: 150px;" class="text-center">Risiko & Tindak Lanjut</th>
          <th scope="col" style="min-width: 170px;">Intervensi Terakhir</th>
          <th scope="col" class="text-end" style="min-width: 140px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($daftar_pantau)): ?>
          <tr>
            <td colspan="9" class="text-center py-5 text-muted">
              <i class="bi bi-clipboard-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
              Tidak ada data anak berisiko yang sesuai dengan kriteria filter.
            </td>
          </tr>
        <?php else: ?>
          <?php 
          foreach ($daftar_pantau as $row): 
              $usia_bulan = Antropometri::hitungUsiaBulan($row['tanggal_lahir'], $row['tanggal_pengukuran']);
              $usia_teks  = Antropometri::formatUsia($usia_bulan);
              $is_l       = (strtoupper($row['jenis_kelamin']) === 'L');
              $status_tl  = $row['status_tindak_lanjut'];

              // Badge Status Tindak Lanjut
              $badge_tl = 'bg-secondary text-white';
              $icon_tl  = 'bi-clock';
              if ($status_tl === 'Baru') {
                  $badge_tl = 'bg-danger text-white';
                  $icon_tl  = 'bi-bell-fill';
              } elseif ($status_tl === 'Ditindaklanjuti') {
                  $badge_tl = 'bg-warning text-dark';
                  $icon_tl  = 'bi-arrow-repeat';
              } elseif ($status_tl === 'Selesai') {
                  $badge_tl = 'bg-success text-white';
                  $icon_tl  = 'bi-check-circle-fill';
              }
          ?>
            <tr>
              <!-- 1. Data Balita (Persis Pengukuran Gizi) -->
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge <?= $is_l ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?> rounded-circle child-avatar-badge"
                        title="<?= $is_l ? 'Laki-laki' : 'Perempuan' ?>">
                    <?= $is_l ? 'L' : 'P' ?>
                  </span>
                  <div>
                    <a href="<?= base_url('modules/data-anak/detail.php?id=' . (int)$row['id_anak']) ?>"
                       class="fw-bold text-decoration-none hover-primary d-block child-name-link">
                      <?= e($row['nama_anak']) ?>
                    </a>
                    <small class="text-muted d-block" style="font-size: 0.75rem;">
                      NIK: <?= e($row['nik']) ?> · Usia: <?= e($usia_teks) ?>
                    </small>
                    <small class="text-muted d-block" style="font-size: 0.75rem;">
                      <i class="bi bi-hospital me-1"></i><?= e($row['nama_posyandu'] ?: 'Posyandu') ?>
                      <?php if (!empty($row['nama_ibu'])): ?>
                        · Ibu: <?= e($row['nama_ibu']) ?>
                      <?php endif; ?>
                    </small>
                  </div>
                </div>
              </td>

              <!-- 2. Tanggal Ukur -->
              <td>
                <span class="fw-semibold small d-block" style="color: var(--admin-text);">
                  <?= date('d/m/Y', strtotime($row['tanggal_pengukuran'])) ?>
                </span>
                <small class="text-muted d-block" style="font-size: 0.75rem;">
                  Terdeteksi: <?= date('d/m/Y', strtotime($row['tanggal_mulai'])) ?>
                </small>
              </td>

              <!-- 3. Antropometri -->
              <td>
                <div class="small">
                  <div><strong>BB:</strong> <?= number_format($row['berat_badan'], 2) ?> kg</div>
                  <div><strong>TB:</strong> <?= number_format($row['tinggi_badan'], 1) ?> cm</div>
                </div>
              </td>

              <!-- 4. TB/U (Stunting) -->
              <td>
                <span class="badge <?= Antropometri::getBadgeTBU($row['status_tb_u']) ?> px-2 py-1 d-inline-block">
                  <?= e($row['status_tb_u'] ?: 'Belum dihitung') ?>
                </span>
                <?php if ($row['zscore_tb_u'] !== null): ?>
                  <small class="text-muted d-block mt-1 font-monospace" style="font-size: 0.75rem;">
                    Z: <?= $row['zscore_tb_u'] > 0 ? '+' : '' ?><?= number_format($row['zscore_tb_u'], 2) ?> SD
                  </small>
                <?php endif; ?>
              </td>

              <!-- 5. BB/U (Gizi) -->
              <td>
                <span class="badge <?= Antropometri::getBadgeBBU($row['status_bb_u']) ?> px-2 py-1 d-inline-block">
                  <?= e($row['status_bb_u'] ?: 'Belum dihitung') ?>
                </span>
                <?php if ($row['zscore_bb_u'] !== null): ?>
                  <small class="text-muted d-block mt-1 font-monospace" style="font-size: 0.75rem;">
                    Z: <?= $row['zscore_bb_u'] > 0 ? '+' : '' ?><?= number_format($row['zscore_bb_u'], 2) ?> SD
                  </small>
                <?php endif; ?>
              </td>

              <!-- 6. BB/TB (Wasting) -->
              <td>
                <span class="badge <?= Antropometri::getBadgeBBTB($row['status_bb_tb']) ?> px-2 py-1 d-inline-block">
                  <?= e($row['status_bb_tb'] ?: 'Belum dihitung') ?>
                </span>
                <?php if ($row['zscore_bb_tb'] !== null): ?>
                  <small class="text-muted d-block mt-1 font-monospace" style="font-size: 0.75rem;">
                    Z: <?= $row['zscore_bb_tb'] > 0 ? '+' : '' ?><?= number_format($row['zscore_bb_tb'], 2) ?> SD
                  </small>
                <?php endif; ?>
              </td>

              <!-- 6. Status Risiko & Tindak Lanjut -->
              <td class="text-center">
                <div class="d-flex flex-column gap-1 align-items-center">
                  <span class="badge <?= Antropometri::getBadgeRisiko($row['status_risiko']) ?> px-2 py-1">
                    <?= e($row['status_risiko']) ?>
                  </span>
                  <span class="badge <?= $badge_tl ?> px-2 py-1">
                    <i class="bi <?= $icon_tl ?> me-1"></i><?= e($status_tl) ?>
                  </span>
                </div>
              </td>

              <!-- 7. Intervensi Terakhir -->
              <td>
                <?php if ($row['total_intervensi'] > 0): ?>
                  <span class="fw-semibold text-primary small d-block">
                    <?= e($row['jenis_terakhir']) ?>
                  </span>
                  <small class="text-muted d-block" style="font-size: 0.75rem;">
                    <?= date('d/m/Y', strtotime($row['tanggal_terakhir'])) ?> · <?= e($row['petugas_terakhir'] ?: 'Petugas') ?>
                  </small>
                  <small class="text-muted" style="font-size: 0.7rem;">
                    Total: <?= $row['total_intervensi'] ?> intervensi
                  </small>
                <?php else: ?>
                  <span class="text-muted small fst-italic">Belum ada intervensi</span>
                <?php endif; ?>
              </td>

              <!-- 8. Aksi (Persis Pengukuran Gizi) -->
              <td class="text-end text-nowrap">
                <div class="d-inline-flex gap-1 justify-content-end">
                  <!-- Tombol Input Intervensi -->
                  <button type="button" 
                          class="btn btn-sm btn-outline-primary" 
                          data-bs-toggle="modal" 
                          data-bs-target="#modalIntervensi<?= $row['id_pemantauan'] ?>"
                          title="Input Catatan Intervensi">
                    <i class="bi bi-heart-pulse"></i>
                  </button>

                  <!-- Tombol Riwayat Intervensi -->
                  <button type="button" 
                          class="btn btn-sm btn-outline-info" 
                          data-bs-toggle="modal" 
                          data-bs-target="#modalRiwayat<?= $row['id_pemantauan'] ?>"
                          title="Lihat Riwayat Intervensi">
                    <i class="bi bi-clock-history"></i>
                  </button>

                  <!-- Tombol Ubah Status Cepat -->
                  <button type="button" 
                          class="btn btn-sm btn-outline-secondary" 
                          data-bs-toggle="modal" 
                          data-bs-target="#modalStatus<?= $row['id_pemantauan'] ?>"
                          title="Ubah Status Tindak Lanjut">
                    <i class="bi bi-tag"></i>
                  </button>

                  <!-- Detail Balita -->
                  <a href="<?= base_url('modules/data-anak/detail.php?id=' . (int)$row['id_anak']) ?>"
                     class="btn btn-sm btn-outline-secondary"
                     title="Detail Rekam Medis Balita">
                    <i class="bi bi-eye"></i>
                  </a>
                </div>
              </td>
            </tr>

            <!-- ============================================================== -->
            <!-- MODAL 1: INPUT CATATAN INTERVENSI PER ANAK -->
            <!-- ============================================================== -->
            <div class="modal fade" id="modalIntervensi<?= $row['id_pemantauan'] ?>" tabindex="-1" aria-labelledby="modalIntervensiLabel<?= $row['id_pemantauan'] ?>" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title h6" id="modalIntervensiLabel<?= $row['id_pemantauan'] ?>">
                      <i class="bi bi-heart-pulse me-2"></i>Catatan Intervensi & Tindak Lanjut
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                  </div>
                  <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="simpan_intervensi">
                    <input type="hidden" name="id_pemantauan" value="<?= $row['id_pemantauan'] ?>">

                    <div class="modal-body text-start">
                      <!-- Info Balita Singkat -->
                      <div class="alert border mb-3" style="background-color: var(--admin-surface-soft); border-color: var(--admin-border) !important;">
                        <div class="fw-semibold" style="color: var(--admin-text);"><?= e($row['nama_anak']) ?> (<?= e($usia_teks) ?>)</div>
                        <div class="small text-muted">
                          <span>Posyandu: <?= e($row['nama_posyandu']) ?></span> • 
                          <span class="text-danger fw-semibold">Risiko: <?= e($row['status_risiko']) ?></span>
                        </div>
                      </div>

                      <div class="mb-3">
                        <label class="form-label small fw-semibold">Tanggal Tindak Lanjut <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_intervensi" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                      </div>

                      <div class="mb-3">
                        <label class="form-label small fw-semibold">Jenis Intervensi <span class="text-danger">*</span></label>
                        <select name="jenis_intervensi" class="form-select form-select-sm" required>
                          <option value="">-- Pilih Jenis Tindak Lanjut --</option>
                          <option value="Konseling Gizi / Edukasi Ibu">Konseling Gizi & Pola Makan Seimbang</option>
                          <option value="Pemberian Makanan Tambahan (PMT)">Pemberian Makanan Tambahan (PMT Pemulihan)</option>
                          <option value="Rujukan ke Puskesmas / RS">Rujukan ke Puskesmas / Rumah Sakit</option>
                          <option value="Pemberian Vitamin & Suplemen">Pemberian Vitamin A / Tablet Besi / Taburia</option>
                          <option value="Kunjungan Rumah (Home Visit)">Kunjungan Rumah (Home Visit Sanitasi & Asupan)</option>
                          <option value="Evaluasi & Pendampingan Rutin">Evaluasi & Pendampingan Rutin Kader</option>
                        </select>
                      </div>

                      <div class="mb-3">
                        <label class="form-label small fw-semibold">Detail Catatan / Tindakan Medis <span class="text-danger">*</span></label>
                        <textarea name="catatan" rows="3" class="form-control form-control-sm" placeholder="Contoh: Telah diberikan biskuit PMT selama 30 hari dan edukasi frekuensi menyusui..." required></textarea>
                      </div>

                      <div class="mb-2">
                        <label class="form-label small fw-semibold">Update Status Peringatan</label>
                        <select name="status_update" class="form-select form-select-sm">
                          <option value="Ditindaklanjuti" <?= $status_tl === 'Ditindaklanjuti' ? 'selected' : '' ?>>
                            Ditindaklanjuti (Dalam Proses Pemantauan Aktif)
                          </option>
                          <option value="Selesai" <?= $status_tl === 'Selesai' ? 'selected' : '' ?>>
                            Selesai (Kondisi Membaik / Masalah Teratasi)
                          </option>
                          <option value="Baru" <?= $status_tl === 'Baru' ? 'selected' : '' ?>>
                            Tetap Berstatus Baru
                          </option>
                        </select>
                        <div class="form-text" style="font-size: 0.75rem;">Status akan otomatis diperbarui sesuai progres penanganan anak.</div>
                      </div>
                    </div>

                    <div class="modal-footer border-top-0 pt-0">
                      <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                      <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-save me-1"></i> Simpan Catatan Intervensi
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- ============================================================== -->
            <!-- MODAL 2: RIWAYAT CATATAN INTERVENSI LENGKAP -->
            <!-- ============================================================== -->
            <div class="modal fade" id="modalRiwayat<?= $row['id_pemantauan'] ?>" tabindex="-1" aria-labelledby="modalRiwayatLabel<?= $row['id_pemantauan'] ?>" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                  <div class="modal-header bg-light">
                    <h5 class="modal-title h6" id="modalRiwayatLabel<?= $row['id_pemantauan'] ?>">
                      <i class="bi bi-clock-history me-2 text-info"></i>Riwayat Intervensi — <?= e($row['nama_anak']) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                  </div>
                  <div class="modal-body text-start">
                    <div class="d-flex justify-content-between align-items-center p-2 mb-3 rounded border" style="background-color: var(--admin-surface-soft); border-color: var(--admin-border) !important;">
                      <div>
                        <span class="fw-semibold" style="color: var(--admin-text);"><?= e($row['nama_anak']) ?></span> (NIK: <?= e($row['nik']) ?>)
                        <div class="small text-muted">Ibu: <?= e($row['nama_ibu']) ?> • Posyandu: <?= e($row['nama_posyandu']) ?></div>
                      </div>
                      <span class="badge <?= $badge_tl ?>"><?= e($status_tl) ?></span>
                    </div>

                    <?php if (empty($riwayat_intervensi_map[$row['id_pemantauan']])): ?>
                      <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-3 d-block mb-1 text-secondary"></i>
                        <p class="small mb-0">Belum ada catatan intervensi yang terekam untuk kasus ini.</p>
                      </div>
                    <?php else: ?>
                      <div class="timeline position-relative ps-3 border-start ms-2">
                        <?php foreach ($riwayat_intervensi_map[$row['id_pemantauan']] as $hist): ?>
                          <div class="mb-3 position-relative">
                            <span class="position-absolute top-0 start-0 translate-middle-x p-1 bg-primary rounded-circle" style="width: 10px; height: 10px; left: -1px; margin-top: 6px;"></span>
                            <div class="ms-3 card border shadow-sm p-2" style="background-color: var(--admin-surface-soft); border-color: var(--admin-border) !important;">
                              <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="text-primary small"><?= e($hist['jenis_intervensi']) ?></strong>
                                <span class="badge border small" style="font-size: 0.7rem; background-color: var(--admin-surface); color: var(--admin-text); border-color: var(--admin-border) !important;">
                                  <?= date('d M Y', strtotime($hist['tanggal'])) ?>
                                </span>
                              </div>
                              <p class="mb-1 small" style="color: var(--admin-text);"><?= nl2br(e($hist['catatan'])) ?></p>
                              <div class="text-muted" style="font-size: 0.72rem;">
                                <i class="bi bi-person-badge me-1"></i>Dicatat oleh: <?= e($hist['nama_petugas']) ?> (<?= e($hist['nama_role'] ?? 'Petugas') ?>)
                              </div>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#modalIntervensi<?= $row['id_pemantauan'] ?>">
                      <i class="bi bi-plus-circle me-1"></i> Tambah Intervensi Baru
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- ============================================================== -->
            <!-- MODAL 3: UBAH STATUS TINDAK LANJUT CEPAT -->
            <!-- ============================================================== -->
            <div class="modal fade" id="modalStatus<?= $row['id_pemantauan'] ?>" tabindex="-1" aria-labelledby="modalStatusLabel<?= $row['id_pemantauan'] ?>" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title h6" id="modalStatusLabel<?= $row['id_pemantauan'] ?>">
                      <i class="bi bi-tag me-1 text-primary"></i>Ubah Status Peringatan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                  </div>
                  <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id_pemantauan" value="<?= $row['id_pemantauan'] ?>">

                    <div class="modal-body text-start">
                      <p class="small text-muted mb-2">Pilih status penanganan untuk balita <strong><?= e($row['nama_anak']) ?></strong>:</p>

                      <div class="mb-3">
                        <label class="form-label small fw-semibold">Status Tindak Lanjut</label>
                        <select name="status_tindak_lanjut" class="form-select form-select-sm" required>
                          <option value="Baru" <?= $status_tl === 'Baru' ? 'selected' : '' ?>>Baru (Menunggu)</option>
                          <option value="Ditindaklanjuti" <?= $status_tl === 'Ditindaklanjuti' ? 'selected' : '' ?>>Ditindaklanjuti (Berjalan)</option>
                          <option value="Selesai" <?= $status_tl === 'Selesai' ? 'selected' : '' ?>>Selesai (Teratasi)</option>
                        </select>
                      </div>

                      <div class="mb-2">
                        <label class="form-label small fw-semibold">Catatan Singkat (Opsional)</label>
                        <input type="text" name="catatan_status" class="form-control form-control-sm" placeholder="Contoh: Kondisi BB naik setelah 2 pekan...">
                      </div>
                    </div>

                    <div class="modal-footer border-top-0 pt-0">
                      <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                      <button type="submit" class="btn btn-primary btn-sm">Perbarui Status</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php
require_once ROOT_PATH . '/includes/footer.php';
?>
