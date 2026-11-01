<?php
/**
 * Modul Pengukuran Gizi — Riwayat Penimbangan & Status Antropometri
 * ====================================================================
 * Menampilkan data log penimbangan balita, hasil z-score antropometri,
 * dan deteksi dini status gizi (Normal, Stunting, Gizi Buruk).
 */

$current_slug = 'pengukuran';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';
require_once ROOT_PATH . '/src/Antropometri.php';

// Pastikan role memiliki hak akses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'pengukuran')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengakses Pengukuran Gizi.');
    redirect(base_url('modules/dashboard/index.php'));
}

// Variabel layout
$page_title  = 'Pengukuran Gizi';
$breadcrumbs = [
    'Data & Laporan'  => '#',
    'Pengukuran Gizi' => '',
];

// ============================================================
// Filter & Pencarian
// ============================================================
$search          = trim($_GET['search'] ?? '');
$filter_posyandu = trim($_GET['posyandu'] ?? '');
$filter_stunting = trim($_GET['stunting'] ?? '');
$filter_risiko   = trim($_GET['risiko'] ?? '');
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

// ============================================================
// Query Metrik Ringkasan
// ============================================================
$total_ukur      = 0;
$total_stunting  = 0;
$total_gizi_buruk = 0;
$total_normal    = 0;

try {
    if ($pdo !== null) {
        $stats = $pdo->query("
            SELECT 
                COUNT(*) AS total,
                COUNT(CASE WHEN sg.status_tb_u IN ('Pendek', 'Sangat Pendek') THEN 1 END) AS stunting_count,
                COUNT(CASE WHEN sg.status_bb_u = 'Gizi Buruk' OR sg.status_bb_tb = 'Gizi Buruk' THEN 1 END) AS buruk_count,
                COUNT(CASE WHEN sg.status_tb_u = 'Normal' AND sg.status_bb_u = 'Gizi Baik' THEN 1 END) AS normal_count
            FROM pengukuran p
            LEFT JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
        ")->fetch(PDO::FETCH_ASSOC);

        if ($stats) {
            $total_ukur       = (int)$stats['total'];
            $total_stunting   = (int)$stats['stunting_count'];
            $total_gizi_buruk = (int)$stats['buruk_count'];
            $total_normal     = (int)$stats['normal_count'];
        }
    }
} catch (PDOException $e) {
    // Abaikan jika database bermasalah
}

// ============================================================
// Query Daftar Pengukuran (dengan Filter)
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
        $where_clauses[] = "sg.status_tb_u = ?";
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
$pengukuran_list = [];

try {
    if ($pdo !== null) {
        $sql = "
            SELECT 
                p.id_pengukuran,
                p.tanggal_pengukuran,
                p.berat_badan,
                p.tinggi_badan,
                p.lingkar_kepala,
                a.id_anak,
                a.nama AS nama_anak,
                a.nik,
                a.tanggal_lahir,
                a.jenis_kelamin,
                pos.nama_posyandu,
                ot.nama_ibu,
                ot.nama_ayah,
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
        $pengukuran_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat data pengukuran: ' . $e->getMessage());
}

// Query parameter untuk tautan ekspor
$export_query = http_build_query(array_filter([
    'search'   => $search,
    'posyandu' => $filter_posyandu,
    'stunting' => $filter_stunting,
    'risiko'   => $filter_risiko,
    'bulan'    => $filter_bulan,
]));

// Include header layout
require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-rulers" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Data & Laporan</p>
      <h1 class="h3 mb-1">Pengukuran Gizi Balita</h1>
      <p class="text-muted mb-0">Pencatatan antropometri bulanan (BB, TB, LiLA) dan pemantauan status gizi standar WHO.</p>
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
          <a class="dropdown-item small" href="<?= base_url('modules/pengukuran/ekspor.php?format=csv' . (!empty($export_query) ? '&' . $export_query : '')) ?>">
            <i class="bi bi-file-earmark-spreadsheet text-success me-2"></i> Ekspor ke CSV / Excel (.csv)
          </a>
        </li>
        <li>
          <a class="dropdown-item small" target="_blank" href="<?= base_url('modules/pengukuran/ekspor.php?format=print' . (!empty($export_query) ? '&' . $export_query : '')) ?>">
            <i class="bi bi-printer text-primary me-2"></i> Cetak Dokumen / PDF
          </a>
        </li>
      </ul>
    </div>

    <a class="btn btn-primary btn-sm" href="<?= base_url('modules/pengukuran/tambah.php') ?>">
      <i class="bi bi-plus-circle me-1" aria-hidden="true"></i> Input Pengukuran Baru
    </a>
  </div>
</div>

<!-- Kartu Metrik Ringkasan -->
<section class="row g-3 mt-1" aria-label="Ringkasan Pengukuran Gizi">
  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-primary">
      <div class="metric-top">
        <span class="metric-label">Total Penimbangan</span>
        <span class="metric-icon"><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_ukur) ?></div>
      <div class="metric-meta">
        <span class="text-muted">Log pengukuran tercatat</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-success">
      <div class="metric-top">
        <span class="metric-label">Gizi Baik & Normal</span>
        <span class="metric-icon"><i class="bi bi-check-circle" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_normal) ?></div>
      <div class="metric-meta">
        <span class="text-success"><?= $total_ukur > 0 ? round(($total_normal / $total_ukur) * 100) : 0 ?>%</span>
        <span class="text-muted">tumbuh optimal</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-warning">
      <div class="metric-top">
        <span class="metric-label">Terindikasi Stunting</span>
        <span class="metric-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_stunting) ?></div>
      <div class="metric-meta">
        <span class="text-warning">Status TB/U</span>
        <span class="text-muted">pendek/sangat pendek</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-danger">
      <div class="metric-top">
        <span class="metric-label">Gizi Buruk / Wasting</span>
        <span class="metric-icon"><i class="bi bi-heart-pulse-fill" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_gizi_buruk) ?></div>
      <div class="metric-meta">
        <span class="text-danger">Prioritas Intervensi</span>
      </div>
    </article>
  </div>
</section>

<!-- Panel Tabel Riwayat Pengukuran -->
<section class="panel mt-3">
  <div class="panel-header flex-wrap gap-2">
    <div>
      <h2 class="h5 mb-1 section-title">
        <i class="bi bi-table text-primary me-2" aria-hidden="true"></i>
        <span>Riwayat Penimbangan & Antropometri</span>
      </h2>
      <p class="text-muted mb-0">Total <?= count($pengukuran_list) ?> data pengukuran ditemukan.</p>
    </div>

    <!-- Form Filter & Pencarian -->
    <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2">
      <div class="input-group input-group-sm" style="min-width: 220px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" name="search" class="form-control"
               placeholder="Cari balita, NIK, orang tua..."
               value="<?= e($search) ?>" aria-label="Cari pengukuran">
      </div>

      <select name="posyandu" class="form-select form-select-sm" style="width: auto;" aria-label="Filter posyandu">
        <option value="">Semua Posyandu</option>
        <?php foreach ($posyandu_list as $pos): ?>
          <option value="<?= (int)$pos['id_posyandu'] ?>" <?= (string)$filter_posyandu === (string)$pos['id_posyandu'] ? 'selected' : '' ?>>
            <?= e($pos['nama_posyandu']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="stunting" class="form-select form-select-sm" style="width: auto;" aria-label="Filter status stunting">
        <option value="">Status TB/U (Semua)</option>
        <option value="stunting" <?= $filter_stunting === 'stunting' ? 'selected' : '' ?>>Semua Stunting (Pendek/Sangat Pendek)</option>
        <option value="Normal" <?= $filter_stunting === 'Normal' ? 'selected' : '' ?>>Normal</option>
        <option value="Pendek" <?= $filter_stunting === 'Pendek' ? 'selected' : '' ?>>Pendek (Stunted)</option>
        <option value="Sangat Pendek" <?= $filter_stunting === 'Sangat Pendek' ? 'selected' : '' ?>>Sangat Pendek</option>
        <option value="Tinggi" <?= $filter_stunting === 'Tinggi' ? 'selected' : '' ?>>Tinggi</option>
      </select>

      <input type="month" name="bulan" class="form-control form-control-sm" style="width: auto;"
             value="<?= e($filter_bulan) ?>" title="Filter Bulan Pengukuran">

      <button type="submit" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-funnel me-1"></i> Filter
      </button>

      <?php if ($search !== '' || $filter_posyandu !== '' || $filter_stunting !== '' || $filter_risiko !== '' || $filter_bulan !== ''): ?>
        <a href="<?= base_url('modules/pengukuran/index.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset filter">
          <i class="bi bi-x-circle me-1"></i> Reset
        </a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0" id="pengukuranTable">
      <thead class="table-light">
        <tr>
          <th scope="col" style="width: 50px;">#</th>
          <th scope="col" style="min-width: 220px;">Data Balita</th>
          <th scope="col" style="min-width: 130px;">Tgl Ukur</th>
          <th scope="col" style="min-width: 170px;">Antropometri</th>
          <th scope="col" style="min-width: 150px;">TB/U (Stunting)</th>
          <th scope="col" style="min-width: 150px;">BB/U (Berat)</th>
          <th scope="col" style="min-width: 150px;">BB/TB (Wasting)</th>
          <th scope="col" style="min-width: 130px;" class="text-center">Risiko</th>
          <th scope="col" class="text-end" style="min-width: 130px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pengukuran_list)): ?>
          <tr>
            <td colspan="9" class="text-center py-5 text-muted">
              <i class="bi bi-clipboard-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
              Belum ada data pengukuran yang sesuai dengan kriteria pencarian.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($pengukuran_list as $idx => $p): 
            $usia_bln  = Antropometri::hitungUsiaBulan($p['tanggal_lahir'], $p['tanggal_pengukuran']);
            $usia_teks = Antropometri::formatUsia($usia_bln);
            $is_l      = (strtoupper($p['jenis_kelamin']) === 'L');
          ?>
            <tr>
              <!-- 1. Nomor -->
              <td class="text-muted small"><?= $idx + 1 ?></td>

              <!-- 2. Data Balita -->
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge <?= $is_l ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?> rounded-circle p-2"
                        title="<?= $is_l ? 'Laki-laki' : 'Perempuan' ?>">
                    <?= $is_l ? 'L' : 'P' ?>
                  </span>
                  <div>
                    <a href="<?= base_url('modules/pengukuran/detail.php?id=' . (int)$p['id_pengukuran']) ?>"
                       class="fw-bold text-dark text-decoration-none hover-primary d-block">
                      <?= e($p['nama_anak']) ?>
                    </a>
                    <small class="text-muted d-block" style="font-size: 0.75rem;">
                      NIK: <?= e($p['nik']) ?> · Usia: <?= e($usia_teks) ?>
                    </small>
                    <small class="text-muted d-block" style="font-size: 0.75rem;">
                      <i class="bi bi-hospital me-1"></i><?= e($p['nama_posyandu'] ?: 'Posyandu') ?>
                      <?php if (!empty($p['nama_ibu'])): ?>
                        · Ibu: <?= e($p['nama_ibu']) ?>
                      <?php endif; ?>
                    </small>
                  </div>
                </div>
              </td>

              <!-- 3. Tanggal Ukur -->
              <td>
                <span class="fw-semibold text-dark small d-block">
                  <?= date('d/m/Y', strtotime($p['tanggal_pengukuran'])) ?>
                </span>
                <small class="text-muted d-block" style="font-size: 0.75rem;">
                  Oleh: <?= e($p['nama_petugas'] ?: 'Kader') ?>
                </small>
              </td>

              <!-- 4. Antropometri -->
              <td>
                <div class="small">
                  <div><strong>BB:</strong> <?= number_format($p['berat_badan'], 2) ?> kg</div>
                  <div><strong>TB:</strong> <?= number_format($p['tinggi_badan'], 1) ?> cm</div>
                  <?php if (!empty($p['lingkar_kepala'])): ?>
                    <div class="text-muted"><strong>LK:</strong> <?= number_format($p['lingkar_kepala'], 1) ?> cm</div>
                  <?php endif; ?>
                </div>
              </td>

              <!-- 5. TB/U (Stunting) -->
              <td>
                <span class="badge <?= Antropometri::getBadgeTBU($p['status_tb_u']) ?> px-2 py-1 d-inline-block">
                  <?= e($p['status_tb_u'] ?: 'Belum dihitung') ?>
                </span>
                <?php if ($p['zscore_tb_u'] !== null): ?>
                  <small class="text-muted d-block mt-1 font-monospace" style="font-size: 0.75rem;">
                    Z: <?= $p['zscore_tb_u'] > 0 ? '+' : '' ?><?= number_format($p['zscore_tb_u'], 2) ?> SD
                  </small>
                <?php endif; ?>
              </td>

              <!-- 6. BB/U (Gizi) -->
              <td>
                <span class="badge <?= Antropometri::getBadgeBBU($p['status_bb_u']) ?> px-2 py-1 d-inline-block">
                  <?= e($p['status_bb_u'] ?: 'Belum dihitung') ?>
                </span>
                <?php if ($p['zscore_bb_u'] !== null): ?>
                  <small class="text-muted d-block mt-1 font-monospace" style="font-size: 0.75rem;">
                    Z: <?= $p['zscore_bb_u'] > 0 ? '+' : '' ?><?= number_format($p['zscore_bb_u'], 2) ?> SD
                  </small>
                <?php endif; ?>
              </td>

              <!-- 7. BB/TB (Wasting) -->
              <td>
                <span class="badge <?= Antropometri::getBadgeBBTB($p['status_bb_tb']) ?> px-2 py-1 d-inline-block">
                  <?= e($p['status_bb_tb'] ?: 'Belum dihitung') ?>
                </span>
                <?php if ($p['zscore_bb_tb'] !== null): ?>
                  <small class="text-muted d-block mt-1 font-monospace" style="font-size: 0.75rem;">
                    Z: <?= $p['zscore_bb_tb'] > 0 ? '+' : '' ?><?= number_format($p['zscore_bb_tb'], 2) ?> SD
                  </small>
                <?php endif; ?>
              </td>

              <!-- 8. Status Risiko -->
              <td class="text-center">
                <span class="badge <?= Antropometri::getBadgeRisiko($p['status_risiko']) ?> px-2 py-1">
                  <?= e($p['status_risiko'] ?: 'Normal') ?>
                </span>
              </td>

              <!-- 9. Aksi -->
              <td class="text-end">
                <div class="btn-group btn-group-sm" role="group" aria-label="Aksi Pengukuran">
                  <a href="<?= base_url('modules/pengukuran/detail.php?id=' . (int)$p['id_pengukuran']) ?>"
                     class="btn btn-outline-secondary" title="Detail & Lembar KMS Digital">
                    <i class="bi bi-eye"></i>
                  </a>

                  <a href="<?= base_url('modules/pengukuran/edit.php?id=' . (int)$p['id_pengukuran']) ?>"
                     class="btn btn-outline-primary" title="Edit Hasil Pengukuran">
                    <i class="bi bi-pencil-square"></i>
                  </a>

                  <button type="button" class="btn btn-outline-danger"
                          data-bs-toggle="modal"
                          data-bs-target="#deletePengukuranModal"
                          data-pengukuran-id="<?= (int)$p['id_pengukuran'] ?>"
                          data-anak-nama="<?= e($p['nama_anak']) ?>"
                          data-tgl="<?= date('d/m/Y', strtotime($p['tanggal_pengukuran'])) ?>"
                          title="Hapus Pengukuran">
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

<!-- Modal Konfirmasi Hapus Pengukuran -->
<div class="modal fade" id="deletePengukuranModal" tabindex="-1" aria-labelledby="deletePengukuranModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title text-danger" id="deletePengukuranModalLabel">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Pengukuran
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body py-3">
        <p class="mb-2">
          Apakah Anda yakin ingin menghapus data pengukuran untuk balita <strong id="deleteAnakNama"></strong> pada tanggal <span id="deleteTgl" class="fw-semibold"></span>?
        </p>
        <div class="alert alert-warning py-2 small mb-0">
          <i class="bi bi-info-circle me-1"></i> Riwayat z-score dan status gizi terkait penimbangan ini juga akan ikut dihapus.
        </div>
      </div>
      <div class="modal-footer border-top-0 pt-0">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <form method="POST" action="<?= base_url('modules/pengukuran/hapus.php') ?>" class="d-inline">
          <?= csrf_field() ?>
          <input type="hidden" name="id" id="deletePengukuranId" value="">
          <button type="submit" class="btn btn-danger btn-sm">
            <i class="bi bi-trash me-1"></i> Ya, Hapus Pengukuran
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
    const deleteModal = document.getElementById('deletePengukuranModal');
    if (deleteModal) {
      deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const pengukuranId = button.getAttribute('data-pengukuran-id');
        const anakNama = button.getAttribute('data-anak-nama');
        const tgl = button.getAttribute('data-tgl');

        document.getElementById('deletePengukuranId').value = pengukuranId;
        document.getElementById('deleteAnakNama').textContent = anakNama;
        document.getElementById('deleteTgl').textContent = tgl;
      });
    }
  });
</script>
JS;

require_once ROOT_PATH . '/includes/footer.php';
?>
