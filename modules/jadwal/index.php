<?php
/**
 * Modul Jadwal & Kunjungan — Daftar Agenda Posyandu & Presensi
 * ==============================================================
 * Menampilkan jadwal penimbangan rutin Posyandu, rekapitulasi kehadiran balita,
 * dan manajemen sweeping kunjungan rumah.
 */

$current_slug = 'jadwal';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan role memiliki hak akses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'jadwal')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengakses Jadwal & Kunjungan.');
    redirect(base_url('modules/dashboard/index.php'));
}

// Variabel layout
$page_title  = 'Jadwal & Kunjungan';
$breadcrumbs = [
    'Data & Laporan'     => '#',
    'Jadwal & Kunjungan' => '',
];

// ============================================================
// Filter & Pencarian
// ============================================================
$search          = trim($_GET['search'] ?? '');
$filter_posyandu = trim($_GET['posyandu'] ?? '');
$filter_status   = trim($_GET['status'] ?? '');
$filter_bulan    = trim($_GET['bulan'] ?? '');

// Ambil daftar posyandu untuk filter
$posyandu_list = [];
try {
    if ($pdo !== null) {
        $posyandu_list = $pdo->query("SELECT id_posyandu, nama_posyandu FROM posyandu ORDER BY nama_posyandu ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $posyandu_list = [];
}

$today = date('Y-m-d');

// ============================================================
// Query Metrik Ringkasan
// ============================================================
$total_jadwal   = 0;
$total_upcoming = 0;
$total_selesai  = 0;
$total_kehadiran_hadir = 0;
$total_kehadiran_all   = 0;

try {
    if ($pdo !== null) {
        // Metrik jadwal
        $statsJadwal = $pdo->query("
            SELECT 
                COUNT(*) AS total,
                COUNT(CASE WHEN tanggal >= '{$today}' THEN 1 END) AS upcoming_count,
                COUNT(CASE WHEN tanggal < '{$today}' THEN 1 END) AS selesai_count
            FROM jadwal_posyandu
        ")->fetch(PDO::FETCH_ASSOC);

        if ($statsJadwal) {
            $total_jadwal   = (int)$statsJadwal['total'];
            $total_upcoming = (int)$statsJadwal['upcoming_count'];
            $total_selesai  = (int)$statsJadwal['selesai_count'];
        }

        // Metrik kehadiran
        $statsHadir = $pdo->query("
            SELECT 
                COUNT(*) AS total,
                COUNT(CASE WHEN status_kehadiran = 'Hadir' THEN 1 END) AS hadir_count
            FROM kehadiran
        ")->fetch(PDO::FETCH_ASSOC);

        if ($statsHadir) {
            $total_kehadiran_all   = (int)$statsHadir['total'];
            $total_kehadiran_hadir = (int)$statsHadir['hadir_count'];
        }
    }
} catch (PDOException $e) {
    // Abaikan jika database bermasalah
}

$persen_kehadiran = $total_kehadiran_all > 0 ? round(($total_kehadiran_hadir / $total_kehadiran_all) * 100, 1) : 0;

// ============================================================
// Query Daftar Jadwal (dengan Filter)
// ============================================================
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

$where_sql   = implode(' AND ', $where_clauses);
$jadwal_list = [];

try {
    if ($pdo !== null) {
        $sql = "
            SELECT 
                j.id_jadwal,
                j.id_posyandu,
                j.tanggal,
                j.waktu,
                j.lokasi,
                j.keterangan,
                pos.nama_posyandu,
                pos.alamat AS alamat_posyandu,
                COUNT(k.id_kehadiran) AS total_sasaran,
                COUNT(CASE WHEN k.status_kehadiran = 'Hadir' THEN 1 END) AS total_hadir,
                COUNT(CASE WHEN k.status_kehadiran = 'Tidak Hadir' THEN 1 END) AS total_absen
            FROM jadwal_posyandu j
            JOIN posyandu pos ON j.id_posyandu = pos.id_posyandu
            LEFT JOIN kehadiran k ON j.id_jadwal = k.id_jadwal
            WHERE {$where_sql}
            GROUP BY j.id_jadwal
            ORDER BY j.tanggal DESC, j.waktu ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $jadwal_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat data jadwal posyandu: ' . $e->getMessage());
}

// Query parameter untuk tautan ekspor
$export_query = http_build_query(array_filter([
    'search'   => $search,
    'posyandu' => $filter_posyandu,
    'status'   => $filter_status,
    'bulan'    => $filter_bulan,
]));

// Include header layout
require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Data & Laporan</p>
      <h1 class="h3 mb-1">Jadwal & Kunjungan Posyandu</h1>
      <p class="text-muted mb-0">Pengaturan sesi penimbangan rutin, absensi kehadiran balita, dan rencana sweeping kunjungan rumah.</p>
    </div>
  </div>
  <div class="heading-actions d-flex gap-2">
    <!-- Dropdown Ekspor Data -->
    <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-download me-1" aria-hidden="true"></i> Ekspor Data
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li><h6 class="dropdown-header small">Format Ekspor</h6></li>
        <li>
          <a class="dropdown-item small" href="<?= base_url('modules/jadwal/ekspor.php?format=csv' . (!empty($export_query) ? '&' . $export_query : '')) ?>">
            <i class="bi bi-file-earmark-spreadsheet text-success me-2"></i> Ekspor ke CSV / Excel (.csv)
          </a>
        </li>
        <li>
          <a class="dropdown-item small" target="_blank" href="<?= base_url('modules/jadwal/ekspor.php?format=print' . (!empty($export_query) ? '&' . $export_query : '')) ?>">
            <i class="bi bi-printer text-primary me-2"></i> Cetak Rekapitulasi Jadwal
          </a>
        </li>
      </ul>
    </div>

    <!-- Tombol Tambah Jadwal Baru -->
    <a href="<?= base_url('modules/jadwal/tambah.php') ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-calendar-plus me-1" aria-hidden="true"></i> Tambah Jadwal Baru
    </a>
  </div>
</div>

<!-- Kartu Metrik Ringkasan -->
<div class="row g-3 mb-4">
  <!-- 1. Total Jadwal -->
  <div class="col-sm-6 col-xl-3">
    <div class="metric-card p-3 rounded-3 border bg-white shadow-sm h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-semibold text-uppercase">Total Sesi Kegiatan</span>
        <span class="badge bg-primary-subtle text-primary p-2 rounded-circle">
          <i class="bi bi-calendar-range fs-6"></i>
        </span>
      </div>
      <div class="fs-3 fw-bold text-dark"><?= number_format($total_jadwal) ?></div>
      <small class="text-muted">Semua jadwal terdaftar</small>
    </div>
  </div>

  <!-- 2. Jadwal Mendatang & Hari Ini -->
  <div class="col-sm-6 col-xl-3">
    <div class="metric-card p-3 rounded-3 border bg-white shadow-sm h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-semibold text-uppercase">Mendatang / Aktif</span>
        <span class="badge bg-info-subtle text-info p-2 rounded-circle">
          <i class="bi bi-clock-history fs-6"></i>
        </span>
      </div>
      <div class="fs-3 fw-bold text-info"><?= number_format($total_upcoming) ?></div>
      <small class="text-muted">Sesi penimbangan bulan ini</small>
    </div>
  </div>

  <!-- 3. Jadwal Terlaksana / Selesai -->
  <div class="col-sm-6 col-xl-3">
    <div class="metric-card p-3 rounded-3 border bg-white shadow-sm h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-semibold text-uppercase">Telah Terlaksana</span>
        <span class="badge bg-success-subtle text-success p-2 rounded-circle">
          <i class="bi bi-check2-circle fs-6"></i>
        </span>
      </div>
      <div class="fs-3 fw-bold text-success"><?= number_format($total_selesai) ?></div>
      <small class="text-muted">Riwayat penimbangan lampau</small>
    </div>
  </div>

  <!-- 4. Rata-rata Kehadiran -->
  <div class="col-sm-6 col-xl-3">
    <div class="metric-card p-3 rounded-3 border bg-white shadow-sm h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-semibold text-uppercase">Tingkat Kehadiran</span>
        <span class="badge bg-warning-subtle text-warning p-2 rounded-circle">
          <i class="bi bi-people fs-6"></i>
        </span>
      </div>
      <div class="fs-3 fw-bold text-dark"><?= $persen_kehadiran ?>%</div>
      <small class="text-muted"><?= number_format($total_kehadiran_hadir) ?> dari <?= number_format($total_kehadiran_all) ?> sasaran balita</small>
    </div>
  </div>
</div>

<!-- Panel Filter & Data Jadwal -->
<section class="panel">
  <!-- Baris Filter & Pencarian -->
  <div class="panel-header border-bottom pb-3 mb-3">
    <form method="GET" action="<?= base_url('modules/jadwal/index.php') ?>" class="row g-2 align-items-center">
      <!-- Pencarian -->
      <div class="col-md-4 col-12">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-light border-end-0">
            <i class="bi bi-search text-muted"></i>
          </span>
          <input type="text"
                 name="search"
                 class="form-control border-start-0"
                 placeholder="Cari lokasi, agenda, posyandu..."
                 value="<?= e($search) ?>">
        </div>
      </div>

      <!-- Filter Posyandu -->
      <div class="col-md-3 col-sm-6">
        <select name="posyandu" class="form-select form-select-sm">
          <option value="">Semua Posyandu</option>
          <?php foreach ($posyandu_list as $pos): ?>
            <option value="<?= (int)$pos['id_posyandu'] ?>" <?= (string)$filter_posyandu === (string)$pos['id_posyandu'] ? 'selected' : '' ?>>
              <?= e($pos['nama_posyandu']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Filter Status Jadwal -->
      <div class="col-md-2 col-sm-6">
        <select name="status" class="form-select form-select-sm">
          <option value="">Semua Status</option>
          <option value="upcoming" <?= $filter_status === 'upcoming' ? 'selected' : '' ?>>Mendatang</option>
          <option value="today" <?= $filter_status === 'today' ? 'selected' : '' ?>>Hari Ini</option>
          <option value="past" <?= $filter_status === 'past' ? 'selected' : '' ?>>Telah Selesai</option>
        </select>
      </div>

      <!-- Filter Bulan -->
      <div class="col-md-2 col-sm-6">
        <input type="month"
               name="bulan"
               class="form-control form-control-sm"
               value="<?= e($filter_bulan) ?>"
               title="Filter berdasarkan bulan pelaksanaan">
      </div>

      <!-- Tombol Aksi Filter -->
      <div class="col-md-1 col-sm-6 d-flex gap-1">
        <button type="submit" class="btn btn-primary btn-sm w-100" title="Terapkan Filter">
          <i class="bi bi-funnel"></i>
        </button>
        <?php if (!empty($search) || !empty($filter_posyandu) || !empty($filter_status) || !empty($filter_bulan)): ?>
          <a href="<?= base_url('modules/jadwal/index.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
            <i class="bi bi-x-lg"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Tabel Data Jadwal Posyandu -->
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th scope="col" style="width: 4%;">#</th>
          <th scope="col" style="width: 16%;">Tanggal & Jam</th>
          <th scope="col" style="width: 18%;">Posyandu Binaan</th>
          <th scope="col" style="width: 20%;">Lokasi Kegiatan</th>
          <th scope="col" style="width: 20%;">Agenda / Catatan</th>
          <th scope="col" style="width: 12%;">Presensi Balita</th>
          <th scope="col" class="text-end" style="width: 10%;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($jadwal_list)): ?>
          <tr>
            <td colspan="7" class="text-center py-5 text-muted">
              <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
              Belum ada jadwal kegiatan posyandu yang cocok dengan filter pencarian.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($jadwal_list as $idx => $j): 
            $tgl_db = $j['tanggal'];
            $is_today = ($tgl_db === $today);
            $is_past  = ($tgl_db < $today);
            $status_badge = '';
            if ($is_today) {
                $status_badge = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Hari Ini</span>';
            } elseif ($is_past) {
                $status_badge = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">Selesai</span>';
            } else {
                $status_badge = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">Mendatang</span>';
            }

            $sasaran = (int)$j['total_sasaran'];
            $hadir   = (int)$j['total_hadir'];
            $absen   = (int)$j['total_absen'];
            $pct     = $sasaran > 0 ? round(($hadir / $sasaran) * 100) : 0;
          ?>
            <tr>
              <!-- 1. Nomor -->
              <td class="text-muted small"><?= $idx + 1 ?></td>

              <!-- 2. Tanggal & Jam -->
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div>
                    <span class="fw-bold text-dark d-block">
                      <?= date('d/m/Y', strtotime($j['tanggal'])) ?>
                    </span>
                    <small class="text-muted d-block">
                      <i class="bi bi-clock me-1"></i><?= date('H:i', strtotime($j['waktu'])) ?> WIB
                    </small>
                    <div class="mt-1"><?= $status_badge ?></div>
                  </div>
                </div>
              </td>

              <!-- 3. Posyandu -->
              <td>
                <span class="fw-semibold text-dark d-block">
                  <i class="bi bi-hospital text-primary me-1"></i><?= e($j['nama_posyandu']) ?>
                </span>
                <small class="text-muted d-block" style="font-size: 0.75rem;">
                  <?= e($j['alamat_posyandu'] ?: '—') ?>
                </small>
              </td>

              <!-- 4. Lokasi -->
              <td>
                <span class="text-dark small d-block">
                  <i class="bi bi-geo-alt text-danger me-1"></i><?= e($j['lokasi'] ?: 'Posyandu') ?>
                </span>
              </td>

              <!-- 5. Keterangan / Agenda -->
              <td>
                <span class="small text-muted d-block">
                  <?= e($j['keterangan'] ?: 'Penimbangan Rutin Bulanan') ?>
                </span>
              </td>

              <!-- 6. Presensi Kehadiran -->
              <td>
                <?php if ($sasaran > 0): ?>
                  <div class="small fw-semibold text-dark mb-1">
                    <?= $hadir ?> / <?= $sasaran ?> Balita
                    <span class="text-muted fw-normal">(<?= $pct ?>%)</span>
                  </div>
                  <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                  <?php if ($absen > 0): ?>
                    <small class="text-danger mt-1 d-block" style="font-size: 0.72rem;">
                      <i class="bi bi-person-x me-1"></i><?= $absen ?> butuh sweeping
                    </small>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge bg-light text-muted border">Belum ada sasaran</span>
                <?php endif; ?>
              </td>

              <!-- 7. Aksi -->
              <td class="text-end">
                <div class="btn-group btn-group-sm" role="group" aria-label="Aksi Jadwal">
                  <a href="<?= base_url('modules/jadwal/detail.php?id=' . (int)$j['id_jadwal']) ?>"
                     class="btn btn-outline-secondary" title="Lembar Presensi & Sweeping Balita">
                    <i class="bi bi-clipboard-check"></i>
                  </a>

                  <a href="<?= base_url('modules/jadwal/edit.php?id=' . (int)$j['id_jadwal']) ?>"
                     class="btn btn-outline-primary" title="Edit Jadwal">
                    <i class="bi bi-pencil-square"></i>
                  </a>

                  <button type="button" class="btn btn-outline-danger"
                          data-bs-toggle="modal"
                          data-bs-target="#deleteJadwalModal"
                          data-jadwal-id="<?= (int)$j['id_jadwal'] ?>"
                          data-posyandu="<?= e($j['nama_posyandu']) ?>"
                          data-tgl="<?= date('d/m/Y', strtotime($j['tanggal'])) ?>"
                          title="Hapus Jadwal">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- Modal Konfirmasi Hapus Jadwal -->
<div class="modal fade" id="deleteJadwalModal" tabindex="-1" aria-labelledby="deleteJadwalModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title text-danger" id="deleteJadwalModalLabel">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Jadwal
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body py-3">
        <p class="mb-2">
          Apakah Anda yakin ingin menghapus jadwal posyandu <strong id="deletePosyanduNama"></strong> pada tanggal <span id="deleteTgl" class="fw-semibold"></span>?
        </p>
        <div class="alert alert-warning py-2 small mb-0">
          <i class="bi bi-info-circle me-1"></i> Seluruh riwayat presensi kehadiran yang terkait dengan jadwal ini akan ikut dihapus.
        </div>
      </div>
      <div class="modal-footer border-top-0 pt-0">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <form method="POST" action="<?= base_url('modules/jadwal/hapus.php') ?>" class="d-inline">
          <?= csrf_field() ?>
          <input type="hidden" name="id" id="deleteJadwalId" value="">
          <button type="submit" class="btn btn-danger btn-sm">
            <i class="bi bi-trash me-1"></i> Ya, Hapus Jadwal
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
$extra_scripts = <<<JS
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const deleteModal = document.getElementById('deleteJadwalModal');
    if (deleteModal) {
      deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const jadwalId = button.getAttribute('data-jadwal-id');
        const posyandu = button.getAttribute('data-posyandu');
        const tgl = button.getAttribute('data-tgl');

        document.getElementById('deleteJadwalId').value = jadwalId;
        document.getElementById('deletePosyanduNama').textContent = posyandu;
        document.getElementById('deleteTgl').textContent = tgl;
      });
    }
  });
</script>
JS;

require_once ROOT_PATH . '/includes/footer.php';
?>
