<?php
require_once __DIR__ . '/_common.php';
$current_slug = 'data-anak';
require_once ROOT_PATH . '/includes/auth-guard.php';

$page_title = 'Data Balita & Anak';
$search     = trim($_GET['search'] ?? '');
$pos        = (int)($_GET['posyandu'] ?? 0);

// Query Filter
$where  = [];
$params = [];
if ($search !== '') {
    $where[] = '(a.nama LIKE ? OR a.nik LIKE ? OR o.nama_ayah LIKE ? OR o.nama_ibu LIKE ?)';
    array_push($params, ...array_fill(0, 4, '%' . $search . '%'));
}
if ($pos) {
    $where[]  = 'a.id_posyandu = ?';
    $params[] = $pos;
}

$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT a.*, p.nama_posyandu, o.nama_ayah, o.nama_ibu, 
               (SELECT COUNT(*) FROM pengukuran pg WHERE pg.id_anak = a.id_anak) AS jumlah_pengukuran 
        FROM anak a 
        JOIN posyandu p ON p.id_posyandu = a.id_posyandu 
        JOIN orangtua o ON o.id_orangtua = a.id_orangtua
        {$where_sql} 
        ORDER BY a.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$anak_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$posyandu = $pdo->query('SELECT id_posyandu, nama_posyandu FROM posyandu ORDER BY nama_posyandu ASC')->fetchAll(PDO::FETCH_ASSOC);
$flash    = get_flash();

// ============================================================
// Query Metrik Ringkasan Data Balita & Anak
// ============================================================
$total_anak      = 0;
$total_laki      = 0;
$total_perempuan = 0;
$total_terpantau = 0;

try {
    if ($pdo !== null) {
        $statsAnak = $pdo->query("
            SELECT 
                COUNT(*) AS total,
                COUNT(CASE WHEN jenis_kelamin = 'L' THEN 1 END) AS laki_count,
                COUNT(CASE WHEN jenis_kelamin = 'P' THEN 1 END) AS perempuan_count
            FROM anak
        ")->fetch(PDO::FETCH_ASSOC);

        if ($statsAnak) {
            $total_anak      = (int)$statsAnak['total'];
            $total_laki      = (int)$statsAnak['laki_count'];
            $total_perempuan = (int)$statsAnak['perempuan_count'];
        }

        $statsTerpantau = $pdo->query("
            SELECT COUNT(DISTINCT id_anak) AS terpantau_count FROM pengukuran
        ")->fetch(PDO::FETCH_ASSOC);

        if ($statsTerpantau) {
            $total_terpantau = (int)$statsTerpantau['terpantau_count'];
        }
    }
} catch (PDOException $e) {
    // Abaikan jika database bermasalah
}

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-person-hearts" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Data &amp; Laporan</p>
      <h1 class="h3 mb-1">Data Balita &amp; Anak</h1>
      <p class="text-muted mb-0">Profil balita, identitas orang tua/wali, dan riwayat pemantauan antropometri.</p>
    </div>
  </div>

  <div class="heading-actions">
    <a class="btn btn-outline-secondary btn-sm" href="<?= data_anak_url('ekspor.php') ?>">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i>Ekspor Excel
    </a>
    <a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?= data_anak_url('ekspor.php', ['format' => 'print']) ?>">
      <i class="bi bi-file-earmark-pdf me-1"></i>Ekspor PDF
    </a>
    <a class="btn btn-primary btn-sm" href="<?= data_anak_url('tambah.php') ?>">
      <i class="bi bi-plus-lg me-1"></i>Tambah Anak
    </a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
    <?= e($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
  </div>
<?php endif; ?>

<!-- Kartu Metrik Ringkasan Data Anak -->
<section class="row g-3 mb-4" aria-label="Ringkasan Data Balita">
  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-primary">
      <div class="metric-top">
        <span class="metric-label">Total Anak Terdaftar</span>
        <span class="metric-icon"><i class="bi bi-person-hearts" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_anak) ?></div>
      <div class="metric-meta">
        <span class="text-muted">Profil balita tercatat</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-info">
      <div class="metric-top">
        <span class="metric-label">Anak Laki-Laki</span>
        <span class="metric-icon"><i class="bi bi-gender-male" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_laki) ?></div>
      <div class="metric-meta">
        <span class="text-primary fw-semibold"><?= $total_anak > 0 ? round(($total_laki / $total_anak) * 100) : 0 ?>%</span>
        <span class="text-muted">dari total balita</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-danger">
      <div class="metric-top">
        <span class="metric-label">Anak Perempuan</span>
        <span class="metric-icon"><i class="bi bi-gender-female" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_perempuan) ?></div>
      <div class="metric-meta">
        <span class="text-danger fw-semibold"><?= $total_anak > 0 ? round(($total_perempuan / $total_anak) * 100) : 0 ?>%</span>
        <span class="text-muted">dari total balita</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-success">
      <div class="metric-top">
        <span class="metric-label">Pernah Ditimbang</span>
        <span class="metric-icon"><i class="bi bi-clipboard2-check" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value"><?= number_format($total_terpantau) ?></div>
      <div class="metric-meta">
        <span class="text-success fw-semibold"><?= $total_anak > 0 ? round(($total_terpantau / $total_anak) * 100) : 0 ?>%</span>
        <span class="text-muted">cakupan penimbangan</span>
      </div>
    </article>
  </div>
</section>

<!-- Panel Tabel Data Balita & Anak -->
<section class="panel">
  <div class="panel-header flex-wrap gap-2">
    <div>
      <h2 class="h5 mb-1 section-title">
        <i class="bi bi-table text-primary me-2" aria-hidden="true"></i>
        <span>Daftar Balita &amp; Anak</span>
      </h2>
      <p class="text-muted mb-0">Total <?= count($anak_list) ?> data balita ditemukan.</p>
    </div>

    <!-- Form Filter & Pencarian -->
    <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-2">
      <div class="input-group input-group-sm" style="min-width: 240px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" name="search" class="form-control"
               placeholder="Cari nama, NIK, orang tua..."
               value="<?= e($search) ?>" aria-label="Cari balita">
      </div>

      <select name="posyandu" class="form-select form-select-sm" style="width: auto;" aria-label="Filter posyandu">
        <option value="">Semua Posyandu</option>
        <?php foreach ($posyandu as $p): ?>
          <option value="<?= (int)$p['id_posyandu'] ?>" <?= $pos === (int)$p['id_posyandu'] ? 'selected' : '' ?>>
            <?= e($p['nama_posyandu']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-funnel me-1"></i>Filter
      </button>

      <?php if ($search !== '' || $pos > 0): ?>
        <a href="<?= data_anak_url('index.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset filter">
          <i class="bi bi-arrow-counterclockwise"></i>
        </a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th scope="col" style="min-width: 220px;">Anak</th>
          <th scope="col" style="min-width: 140px;">NIK</th>
          <th scope="col" style="min-width: 180px;">Orang Tua / Wali</th>
          <th scope="col" style="min-width: 160px;">Posyandu</th>
          <th scope="col" style="min-width: 110px;" class="text-center">Pengukuran</th>
          <th scope="col" class="text-end" style="min-width: 120px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($anak_list)): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-5">
              <i class="bi bi-clipboard-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
              Belum ada data balita yang cocok dengan filter pencarian.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($anak_list as $a): 
            $is_l = ($a['jenis_kelamin'] === 'L');
          ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge <?= $is_l ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?> rounded-circle p-2"
                        title="<?= $is_l ? 'Laki-laki' : 'Perempuan' ?>">
                    <?= $is_l ? 'L' : 'P' ?>
                  </span>
                  <div>
                    <a class="fw-semibold text-dark text-decoration-none hover-primary d-block" href="<?= data_anak_url('detail.php', ['id' => $a['id_anak']]) ?>">
                      <?= e($a['nama']) ?>
                    </a>
                    <div class="small text-muted">
                      <?= $is_l ? 'Laki-laki' : 'Perempuan' ?> · <?= date('d/m/Y', strtotime($a['tanggal_lahir'])) ?>
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <span class="font-monospace small text-dark"><?= e($a['nik']) ?></span>
              </td>
              <td>
                <span class="small text-dark">
                  <?= e(implode(' / ', array_filter([$a['nama_ayah'], $a['nama_ibu']])) ?: '—') ?>
                </span>
              </td>
              <td>
                <span class="badge bg-light text-dark border">
                  <i class="bi bi-hospital text-primary me-1"></i><?= e($a['nama_posyandu']) ?>
                </span>
              </td>
              <td class="text-center">
                <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                  <?= (int)$a['jumlah_pengukuran'] ?> Sesi
                </span>
              </td>
              <td class="text-end text-nowrap">
                <div class="d-inline-flex gap-1 justify-content-end">
                  <a class="btn btn-sm btn-outline-secondary" title="Detail Balita" href="<?= data_anak_url('detail.php', ['id' => $a['id_anak']]) ?>">
                    <i class="bi bi-eye"></i>
                  </a>
                  <a class="btn btn-sm btn-outline-primary" title="Ubah Profil Balita" href="<?= data_anak_url('edit.php', ['id' => $a['id_anak']]) ?>">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a class="btn btn-sm btn-outline-danger" title="Hapus Balita" href="<?= data_anak_url('hapus.php', ['id' => $a['id_anak']]) ?>">
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
