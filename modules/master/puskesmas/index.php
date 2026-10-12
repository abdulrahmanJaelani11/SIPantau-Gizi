<?php
/**
 * Master Data — Data Puskesmas
 * ===============================
 * Halaman utama pengelolaan data Pusat Kesehatan Masyarakat (Puskesmas) Pembina.
 * Berdasarkan database db_sipantau_gizi (tabel puskesmas terelasi dengan wilayah, posyandu, user).
 */

$current_slug = 'puskesmas';
require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan role memiliki izin akses ke menu puskesmas
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'puskesmas')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengakses Data Puskesmas.');
    redirect(base_url('modules/dashboard/index.php'));
}

// Cek apakah role ini memiliki izin edit/tambah (Admin & Petugas)
$is_admin = ($user_role_id === Otorisasi::ADMIN_ROLE_ID || strcasecmp($_SESSION['user_role_nama'] ?? '', 'Admin') === 0);

// Variabel layout
$page_title  = 'Data Puskesmas';
$breadcrumbs = [
    'Master Data'    => '#',
    'Data Puskesmas' => '',
];

// ============================================================
// Filter & Pencarian
// ============================================================
$search         = trim($_GET['search'] ?? '');
$filter_wilayah = trim($_GET['wilayah'] ?? '');

// Ambil daftar wilayah untuk dropdown filter
$wilayah_list = [];
try {
    if ($pdo !== null) {
        $wilayah_list = $pdo->query("SELECT id_wilayah, nama_wilayah, kecamatan, kabupaten FROM wilayah ORDER BY nama_wilayah ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $wilayah_list = [];
}

// ============================================================
// Query Metrik Ringkasan
// ============================================================
$total_puskesmas = 0;
$total_posyandu  = 0;
$total_petugas   = 0;
$total_wilayah   = count($wilayah_list);

try {
    if ($pdo !== null) {
        $total_puskesmas = (int)$pdo->query("SELECT COUNT(*) FROM puskesmas")->fetchColumn();
        $total_posyandu  = (int)$pdo->query("SELECT COUNT(*) FROM posyandu")->fetchColumn();
        $total_petugas   = (int)$pdo->query("SELECT COUNT(*) FROM user WHERE id_puskesmas IS NOT NULL")->fetchColumn();
    }
} catch (PDOException $e) {
    // Abaikan jika database bermasalah
}

// ============================================================
// Query Daftar Puskesmas beserta Statistik Binaan
// ============================================================
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
$puskesmas_data = [];

try {
    if ($pdo !== null) {
        $sql = "
            SELECT 
                p.id_puskesmas,
                p.nama_puskesmas,
                p.alamat,
                p.no_telepon,
                w.id_wilayah,
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
            GROUP BY p.id_puskesmas, p.nama_puskesmas, p.alamat, p.no_telepon, w.id_wilayah, w.nama_wilayah, w.kecamatan, w.kabupaten
            ORDER BY p.id_puskesmas ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $puskesmas_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat data Puskesmas: ' . $e->getMessage());
}

// Parameter query string untuk ekspor
$export_query = http_build_query(array_filter([
    'search'  => $search,
    'wilayah' => $filter_wilayah,
]));

// Include header layout
require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-building" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Master Data</p>
      <h1 class="h3 mb-1">Data Puskesmas</h1>
      <p class="text-muted mb-0">Kelola master Pusat Kesehatan Masyarakat pembina wilayah dan jejaring Posyandu.</p>
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
          <a class="dropdown-item small" href="<?= base_url('modules/master/puskesmas/ekspor.php?format=csv' . (!empty($export_query) ? '&' . $export_query : '')) ?>">
            <i class="bi bi-file-earmark-spreadsheet text-success me-2"></i> Ekspor ke CSV / Excel (.csv)
          </a>
        </li>
        <li>
          <a class="dropdown-item small" target="_blank" href="<?= base_url('modules/master/puskesmas/ekspor.php?format=print' . (!empty($export_query) ? '&' . $export_query : '')) ?>">
            <i class="bi bi-printer text-primary me-2"></i> Cetak Dokumen / PDF
          </a>
        </li>
      </ul>
    </div>

    <?php if ($is_admin): ?>
      <a class="btn btn-primary btn-sm" href="<?= base_url('modules/master/puskesmas/tambah.php') ?>">
        <i class="bi bi-plus-circle me-1" aria-hidden="true"></i> Tambah Puskesmas
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Kartu Metrik Ringkasan -->
<section class="row g-3 mt-1" aria-label="Ringkasan Puskesmas">
  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-primary">
      <div class="metric-top">
        <span class="metric-label">Total Puskesmas</span>
        <span class="metric-icon"><i class="bi bi-building-check" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_puskesmas) ?></div>
      <div class="metric-meta">
        <span class="text-muted">Faskes pembina aktif</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-success">
      <div class="metric-top">
        <span class="metric-label">Posyandu Binaan</span>
        <span class="metric-icon"><i class="bi bi-hospital" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_posyandu) ?></div>
      <div class="metric-meta">
        <span class="text-success">Jejaring layanan</span>
        <span class="text-muted">terhubung</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-warning">
      <div class="metric-top">
        <span class="metric-label">Petugas & Bidan</span>
        <span class="metric-icon"><i class="bi bi-person-badge" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_petugas) ?></div>
      <div class="metric-meta">
        <span class="text-warning">Tenaga kesehatan</span>
        <span class="text-muted">terdaftar</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-danger">
      <div class="metric-top">
        <span class="metric-label">Wilayah Kerja</span>
        <span class="metric-icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_wilayah) ?></div>
      <div class="metric-meta">
        <span class="text-muted">Kelurahan binaan terdaftar</span>
      </div>
    </article>
  </div>
</section>

<!-- Panel Tabel Puskesmas -->
<section class="panel mt-3">
  <div class="panel-header flex-wrap gap-2">
    <div>
      <h2 class="h5 mb-1 section-title">
        <i class="bi bi-hospital text-primary me-2" aria-hidden="true"></i>
        <span>Daftar Unit Puskesmas</span>
      </h2>
      <p class="text-muted mb-0">Total <?= count($puskesmas_data) ?> unit Puskesmas ditemukan.</p>
    </div>

    <!-- Form Filter & Pencarian -->
    <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2">
      <div class="input-group input-group-sm" style="min-width: 240px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" name="search" class="form-control"
               placeholder="Cari nama, alamat, telepon..."
               value="<?= e($search) ?>" aria-label="Cari puskesmas">
      </div>

      <select name="wilayah" class="form-select form-select-sm" style="width: auto;" aria-label="Filter wilayah">
        <option value="">Semua Wilayah</option>
        <?php foreach ($wilayah_list as $w): ?>
          <option value="<?= $w['id_wilayah'] ?>" <?= (string)$filter_wilayah === (string)$w['id_wilayah'] ? 'selected' : '' ?>>
            <?= e($w['nama_wilayah']) ?> (<?= e($w['kecamatan']) ?>)
          </option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-funnel me-1"></i> Filter
      </button>

      <?php if ($search !== '' || $filter_wilayah !== ''): ?>
        <a href="<?= base_url('modules/master/puskesmas/index.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset filter">
          <i class="bi bi-x-circle me-1"></i> Reset
        </a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0" id="puskesmasTable">
      <thead class="table-light">
        <tr>
          <th scope="col" style="width: 50px;">#</th>
          <th scope="col" style="min-width: 220px;">Nama Puskesmas</th>
          <th scope="col" style="min-width: 180px;">Wilayah Kerja</th>
          <th scope="col">Alamat Lengkap</th>
          <th scope="col" style="min-width: 140px;">Kontak Telepon</th>
          <th scope="col" style="width: 110px;" class="text-center">Posyandu</th>
          <th scope="col" style="width: 110px;" class="text-center">Petugas</th>
          <th scope="col" class="text-end" style="min-width: 140px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($puskesmas_data)): ?>
          <tr>
            <td colspan="8" class="text-center py-5 text-muted">
              <i class="bi bi-building-slash fs-1 d-block mb-2 text-secondary opacity-50"></i>
              Tidak ada data Puskesmas yang sesuai dengan filter pencarian.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($puskesmas_data as $idx => $p): 
            $pos_count = (int)$p['posyandu_count'];
            $pet_count = (int)$p['petugas_count'];
          ?>
            <tr>
              <!-- 1. Nomor -->
              <td class="text-muted small"><?= $idx + 1 ?></td>

              <!-- 2. Nama Puskesmas -->
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="d-inline-flex p-2 rounded-2 bg-primary-subtle text-primary">
                    <i class="bi bi-building fs-5"></i>
                  </span>
                  <div>
                    <a href="<?= base_url('modules/master/puskesmas/detail.php?id=' . (int)$p['id_puskesmas']) ?>"
                       class="fw-bold text-dark text-decoration-none hover-primary d-block">
                      <?= e($p['nama_puskesmas']) ?>
                    </a>
                    <small class="text-muted">ID: #<?= (int)$p['id_puskesmas'] ?></small>
                  </div>
                </div>
              </td>

              <!-- 3. Wilayah Kerja -->
              <td>
                <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1">
                  <i class="bi bi-geo-alt text-danger"></i>
                  <?= e($p['nama_wilayah'] ?: 'Wilayah #' . $p['id_wilayah']) ?>
                </span>
                <small class="text-muted d-block mt-1">
                  <?= e($p['kecamatan'] ?: '-') ?>, <?= e($p['kabupaten'] ?: '-') ?>
                </small>
              </td>

              <!-- 4. Alamat -->
              <td>
                <span class="text-body-secondary small">
                  <?= !empty($p['alamat']) ? e($p['alamat']) : '<em class="text-muted">-</em>' ?>
                </span>
              </td>

              <!-- 5. Kontak Telepon -->
              <td>
                <?php if (!empty($p['no_telepon'])): ?>
                  <a href="tel:<?= e($p['no_telepon']) ?>" class="badge bg-secondary-subtle text-secondary border text-decoration-none">
                    <i class="bi bi-telephone me-1"></i><?= e($p['no_telepon']) ?>
                  </a>
                <?php else: ?>
                  <span class="text-muted small">-</span>
                <?php endif; ?>
              </td>

              <!-- 6. Jumlah Posyandu -->
              <td class="text-center">
                <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1" title="<?= $pos_count ?> Posyandu binaan">
                  <?= $pos_count ?> Posyandu
                </span>
              </td>

              <!-- 7. Jumlah Petugas -->
              <td class="text-center">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" title="<?= $pet_count ?> Tenaga medis">
                  <?= $pet_count ?> Petugas
                </span>
              </td>

              <!-- 8. Aksi -->
              <td class="text-end">
                <div class="btn-group btn-group-sm" role="group" aria-label="Aksi Puskesmas">
                  <a href="<?= base_url('modules/master/puskesmas/detail.php?id=' . (int)$p['id_puskesmas']) ?>"
                     class="btn btn-outline-secondary" title="Detail Puskesmas">
                    <i class="bi bi-eye"></i>
                  </a>

                  <?php if ($is_admin): ?>
                    <a href="<?= base_url('modules/master/puskesmas/edit.php?id=' . (int)$p['id_puskesmas']) ?>"
                       class="btn btn-outline-primary" title="Edit Puskesmas">
                      <i class="bi bi-pencil-square"></i>
                    </a>

                    <?php if ($pos_count > 0 || $pet_count > 0): ?>
                      <button type="button" class="btn btn-outline-secondary disabled"
                              title="Puskesmas ini tidak dapat dihapus karena masih membina <?= $pos_count ?> Posyandu atau <?= $pet_count ?> Petugas."
                              aria-disabled="true">
                        <i class="bi bi-trash"></i>
                      </button>
                    <?php else: ?>
                      <button type="button" class="btn btn-outline-danger"
                              data-bs-toggle="modal"
                              data-bs-target="#deletePuskesmasModal"
                              data-puskesmas-id="<?= (int)$p['id_puskesmas'] ?>"
                              data-puskesmas-name="<?= e($p['nama_puskesmas']) ?>"
                              title="Hapus Puskesmas">
                        <i class="bi bi-trash"></i>
                      </button>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- Modal Konfirmasi Hapus Puskesmas -->
<div class="modal fade" id="deletePuskesmasModal" tabindex="-1" aria-labelledby="deletePuskesmasModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title text-danger" id="deletePuskesmasModalLabel">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Puskesmas
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body py-3">
        <p class="mb-2">
          Apakah Anda yakin ingin menghapus data Puskesmas <strong id="deletePuskesmasName"></strong>?
        </p>
        <div class="alert alert-warning py-2 small mb-0">
          <i class="bi bi-info-circle me-1"></i> Tindakan ini tidak dapat dibatalkan.
        </div>
      </div>
      <div class="modal-footer border-top-0 pt-0">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <form method="POST" action="<?= base_url('modules/master/puskesmas/hapus.php') ?>" class="d-inline">
          <?= csrf_field() ?>
          <input type="hidden" name="id" id="deletePuskesmasId" value="">
          <button type="submit" class="btn btn-danger btn-sm">
            <i class="bi bi-trash me-1"></i> Ya, Hapus Puskesmas
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
    const deleteModal = document.getElementById('deletePuskesmasModal');
    if (deleteModal) {
      deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const puskesmasId = button.getAttribute('data-puskesmas-id');
        const puskesmasName = button.getAttribute('data-puskesmas-name');

        document.getElementById('deletePuskesmasId').value = puskesmasId;
        document.getElementById('deletePuskesmasName').textContent = puskesmasName;
      });
    }
  });
</script>
JS;

require_once ROOT_PATH . '/includes/footer.php';
?>
