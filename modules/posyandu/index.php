<?php
require_once __DIR__ . '/_common.php';
$current_slug = 'posyandu';
require_once ROOT_PATH . '/includes/auth-guard.php';

$page_title = 'Data Posyandu';
$search     = trim($_GET['search'] ?? '');
$params     = [];
$where      = '';

if ($search !== '') {
    $where   = ' WHERE p.nama_posyandu LIKE ? OR p.alamat LIKE ? OR w.nama_wilayah LIKE ? OR pk.nama_puskesmas LIKE ?';
    $pattern = '%' . $search . '%';
    $params  = [$pattern, $pattern, $pattern, $pattern];
}

$sql = 'SELECT p.*, w.nama_wilayah, w.kecamatan, w.kabupaten, pk.nama_puskesmas,
               (SELECT COUNT(*) FROM anak a WHERE a.id_posyandu = p.id_posyandu) AS jumlah_anak 
        FROM posyandu p 
        JOIN wilayah w ON w.id_wilayah = p.id_wilayah 
        JOIN puskesmas pk ON pk.id_puskesmas = p.id_puskesmas' . $where . ' 
        ORDER BY p.nama_posyandu ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
$flash   = get_flash();

// ============================================================
// Query Metrik Ringkasan Data Posyandu
// ============================================================
$total_posyandu  = 0;
$total_anak_pos  = 0;
$total_wilayah   = 0;
$total_puskesmas = 0;

try {
    if ($pdo !== null) {
        $total_posyandu  = (int)$pdo->query("SELECT COUNT(*) FROM posyandu")->fetchColumn();
        $total_anak_pos  = (int)$pdo->query("SELECT COUNT(*) FROM anak")->fetchColumn();
        $total_wilayah   = (int)$pdo->query("SELECT COUNT(DISTINCT id_wilayah) FROM posyandu")->fetchColumn();
        $total_puskesmas = (int)$pdo->query("SELECT COUNT(DISTINCT id_puskesmas) FROM posyandu")->fetchColumn();
    }
} catch (PDOException $e) {
    // Abaikan jika database bermasalah
}

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-hospital" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Master Data</p>
      <h1 class="h3 mb-1">Data Posyandu &amp; Wilayah</h1>
      <p class="text-muted mb-0">Kelola titik posyandu, sasaran balita terdaftar, wilayah binaan, dan puskesmas induk.</p>
    </div>
  </div>

  <div class="heading-actions">
    <a class="btn btn-outline-secondary btn-sm" href="<?= posyandu_url('ekspor.php') ?>">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i>Ekspor Excel
    </a>
    <a class="btn btn-primary btn-sm" href="<?= posyandu_url('tambah.php') ?>">
      <i class="bi bi-plus-lg me-1"></i>Tambah Posyandu
    </a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
    <?= e($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
  </div>
<?php endif; ?>

<!-- Kartu Metrik Ringkasan Data Posyandu -->
<section class="row g-3 mb-4" aria-label="Ringkasan Data Posyandu">
  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-primary">
      <div class="metric-top">
        <span class="metric-label">Total Posyandu</span>
        <span class="metric-icon"><i class="bi bi-hospital" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_posyandu) ?></div>
      <div class="metric-meta">
        <span class="text-muted">Titik layanan aktif</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-success">
      <div class="metric-top">
        <span class="metric-label">Balita Terdaftar</span>
        <span class="metric-icon"><i class="bi bi-person-hearts" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_anak_pos) ?></div>
      <div class="metric-meta">
        <span class="text-success fw-semibold">Sasaran pemantauan</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-warning">
      <div class="metric-top">
        <span class="metric-label">Wilayah Binaan</span>
        <span class="metric-icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_wilayah) ?></div>
      <div class="metric-meta">
        <span class="text-warning fw-semibold">Kelurahan / Desa</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-danger">
      <div class="metric-top">
        <span class="metric-label">Puskesmas Pembina</span>
        <span class="metric-icon"><i class="bi bi-building" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_puskesmas) ?></div>
      <div class="metric-meta">
        <span class="text-danger fw-semibold">Puskesmas induk</span>
      </div>
    </article>
  </div>
</section>

<!-- Panel Tabel Data Posyandu -->
<section class="panel">
  <div class="panel-header flex-wrap gap-2">
    <div>
      <h2 class="h5 mb-1 section-title">
        <i class="bi bi-table text-primary me-2" aria-hidden="true"></i>
        <span>Daftar Posyandu</span>
      </h2>
      <p class="text-muted mb-0">Total <?= count($records) ?> posyandu terdaftar.</p>
    </div>

    <!-- Form Filter & Pencarian -->
    <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2">
      <div class="input-group input-group-sm" style="min-width: 260px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" name="search" class="form-control"
               placeholder="Cari posyandu, wilayah, puskesmas..."
               value="<?= e($search) ?>" aria-label="Cari posyandu">
      </div>

      <button type="submit" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-funnel me-1"></i>Cari
      </button>

      <?php if ($search !== ''): ?>
        <a href="<?= posyandu_url('index.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset pencarian">
          <i class="bi bi-arrow-counterclockwise"></i>
        </a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th scope="col" style="min-width: 200px;">Posyandu</th>
          <th scope="col" style="min-width: 180px;">Wilayah Kerja</th>
          <th scope="col" style="min-width: 180px;">Puskesmas Induk</th>
          <th scope="col" style="min-width: 120px;" class="text-center">Anak Terdaftar</th>
          <th scope="col" class="text-end" style="min-width: 100px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($records)): ?>
          <tr>
            <td colspan="5" class="text-center text-muted py-5">
              <i class="bi bi-hospital fs-1 d-block mb-2 text-secondary opacity-50"></i>
              Belum ada data posyandu yang cocok dengan kriteria pencarian.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($records as $r): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="d-inline-flex p-2 rounded-2 bg-primary-subtle text-primary">
                    <i class="bi bi-hospital fs-6"></i>
                  </span>
                  <div>
                    <span class="fw-semibold text-dark d-block"><?= e($r['nama_posyandu']) ?></span>
                    <small class="text-muted"><?= e($r['alamat'] ?: 'Alamat belum diisi') ?></small>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1">
                  <i class="bi bi-geo-alt text-danger"></i><?= e($r['nama_wilayah']) ?>
                </span>
                <div class="small text-muted mt-1"><?= e($r['kecamatan'] . ', ' . $r['kabupaten']) ?></div>
              </td>
              <td>
                <span class="badge bg-secondary-subtle text-secondary border">
                  <i class="bi bi-building me-1"></i><?= e($r['nama_puskesmas']) ?>
                </span>
              </td>
              <td class="text-center">
                <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                  <?= (int)$r['jumlah_anak'] ?> Balita
                </span>
              </td>
              <td class="text-end text-nowrap">
                <div class="d-inline-flex gap-1 justify-content-end">
                  <a class="btn btn-sm btn-outline-secondary" href="<?= posyandu_url('edit.php', ['id' => $r['id_posyandu']]) ?>" title="Ubah Posyandu">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a class="btn btn-sm btn-outline-danger" href="<?= posyandu_url('hapus.php', ['id' => $r['id_posyandu']]) ?>" title="Hapus Posyandu">
                    <i class="bi bi-trash"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
