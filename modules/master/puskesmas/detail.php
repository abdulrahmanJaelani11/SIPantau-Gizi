<?php
/**
 * Master Data — Detail Puskesmas
 * =================================
 * Halaman profil lengkap Puskesmas, daftar Posyandu binaan, dan akun tenaga medis tertaut.
 */

$current_slug = 'puskesmas';
require_once __DIR__ . '/../../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan memiliki hak akses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'puskesmas')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk melihat detail Puskesmas.');
    redirect(base_url('modules/dashboard/index.php'));
}

$is_admin = ($user_role_id === Otorisasi::ADMIN_ROLE_ID || strcasecmp($_SESSION['user_role_nama'] ?? '', 'Admin') === 0);

// Validasi ID
$puskesmas_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$puskesmas_id) {
    set_flash('danger', 'ID Puskesmas tidak valid.');
    redirect(base_url('modules/master/puskesmas/index.php'));
}

// Ambil profil puskesmas beserta wilayah
$puskesmas = null;
try {
    $stmt = $pdo->prepare("
        SELECT p.*, w.nama_wilayah, w.kecamatan, w.kabupaten
        FROM puskesmas p
        LEFT JOIN wilayah w ON p.id_wilayah = w.id_wilayah
        WHERE p.id_puskesmas = ?
        LIMIT 1
    ");
    $stmt->execute([$puskesmas_id]);
    $puskesmas = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat data Puskesmas: ' . $e->getMessage());
    redirect(base_url('modules/master/puskesmas/index.php'));
}

if (!$puskesmas) {
    set_flash('danger', 'Data Puskesmas tidak ditemukan.');
    redirect(base_url('modules/master/puskesmas/index.php'));
}

// Ambil Posyandu binaan
$posyandu_list = [];
try {
    $stmtPos = $pdo->prepare("
        SELECT pos.id_posyandu, pos.nama_posyandu, pos.alamat, w.nama_wilayah,
               COUNT(a.id_anak) AS balita_count
        FROM posyandu pos
        LEFT JOIN wilayah w ON pos.id_wilayah = w.id_wilayah
        LEFT JOIN anak a ON pos.id_posyandu = a.id_posyandu
        WHERE pos.id_puskesmas = ?
        GROUP BY pos.id_posyandu, pos.nama_posyandu, pos.alamat, w.nama_wilayah
        ORDER BY pos.nama_posyandu ASC
    ");
    $stmtPos->execute([$puskesmas_id]);
    $posyandu_list = $stmtPos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $posyandu_list = [];
}

// Ambil Tenaga Kesehatan / Petugas tertaut
$petugas_list = [];
try {
    $stmtPet = $pdo->prepare("
        SELECT u.id_user, u.nama, u.username, u.email, u.no_telepon, u.status, r.nama_role
        FROM user u
        LEFT JOIN role r ON u.id_role = r.id_role
        WHERE u.id_puskesmas = ?
        ORDER BY u.nama ASC
    ");
    $stmtPet->execute([$puskesmas_id]);
    $petugas_list = $stmtPet->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $petugas_list = [];
}

// Hitung total balita di bawah seluruh posyandu binaan
$total_balita = array_sum(array_column($posyandu_list, 'balita_count'));

// Variabel layout
$page_title  = 'Detail Puskesmas — ' . $puskesmas['nama_puskesmas'];
$breadcrumbs = [
    'Master Data'       => '#',
    'Data Puskesmas'    => base_url('modules/master/puskesmas/index.php'),
    'Detail Puskesmas'  => '',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-hospital" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Master Data &rsaquo; Data Puskesmas</p>
      <h1 class="h3 mb-1">Detail: <?= e($puskesmas['nama_puskesmas']) ?></h1>
      <p class="text-muted mb-0">Profil unit Puskesmas pembina, jejaring Posyandu, dan tenaga medis yang bertugas.</p>
    </div>
  </div>
  <div class="heading-actions d-flex gap-2">
    <?php if ($is_admin): ?>
      <a class="btn btn-primary btn-sm" href="<?= base_url('modules/master/puskesmas/edit.php?id=' . (int)$puskesmas['id_puskesmas']) ?>">
        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Edit Puskesmas
      </a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('modules/master/puskesmas/index.php') ?>">
      <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
  </div>
</div>

<div class="row g-4 mt-1">
  <!-- Kolom Kiri: Profil Puskesmas -->
  <div class="col-12 col-lg-5">
    <div class="panel mb-4">
      <div class="panel-header d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0 section-title">
          <i class="bi bi-building text-primary me-2"></i>Informasi Fasilitas
        </h2>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
          ID #<?= (int)$puskesmas['id_puskesmas'] ?>
        </span>
      </div>
      <div class="p-3 p-lg-4">
        <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
          <div class="brand-icon" style="width: 52px; height: 52px; font-size: 1.5rem;">
            <i class="bi bi-hospital"></i>
          </div>
          <div>
            <h4 class="mb-0 fw-bold"><?= e($puskesmas['nama_puskesmas']) ?></h4>
            <span class="badge bg-light text-muted border mt-1">
              Fasilitas Pelayanan Kesehatan
            </span>
          </div>
        </div>

        <ul class="list-unstyled mb-0 small">
          <li class="mb-2 pb-2 border-bottom">
            <span class="text-muted d-block">Wilayah Kerja:</span>
            <strong><?= e($puskesmas['nama_wilayah'] ?: '-') ?></strong>
            <span class="text-muted">(Kec. <?= e($puskesmas['kecamatan'] ?: '-') ?>, <?= e($puskesmas['kabupaten'] ?: '-') ?>)</span>
          </li>
          <li class="mb-2 pb-2 border-bottom">
            <span class="text-muted d-block">Alamat Lengkap:</span>
            <div class="text-dark mt-1">
              <i class="bi bi-geo-alt-fill text-danger me-1"></i>
              <?= nl2br(e($puskesmas['alamat'])) ?>
            </div>
          </li>
          <li class="mb-3">
            <span class="text-muted d-block">Kontak Telepon:</span>
            <div class="mt-1">
              <a href="tel:<?= e($puskesmas['no_telepon']) ?>" class="badge bg-primary-subtle text-primary border text-decoration-none fs-6 px-2 py-1">
                <i class="bi bi-telephone-fill me-1"></i><?= e($puskesmas['no_telepon']) ?>
              </a>
            </div>
          </li>
        </ul>

        <div class="row g-2 text-center pt-2">
          <div class="col-4">
            <div class="p-2 border rounded-3 bg-light-subtle">
              <span class="small text-muted d-block" style="font-size: 0.75rem;">Posyandu</span>
              <strong class="fs-5 text-primary"><?= count($posyandu_list) ?></strong>
            </div>
          </div>
          <div class="col-4">
            <div class="p-2 border rounded-3 bg-light-subtle">
              <span class="small text-muted d-block" style="font-size: 0.75rem;">Petugas</span>
              <strong class="fs-5 text-success"><?= count($petugas_list) ?></strong>
            </div>
          </div>
          <div class="col-4">
            <div class="p-2 border rounded-3 bg-light-subtle">
              <span class="small text-muted d-block" style="font-size: 0.75rem;">Balita Binaan</span>
              <strong class="fs-5 text-warning"><?= number_format($total_balita) ?></strong>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Kolom Kanan: Jejaring Posyandu & Petugas -->
  <div class="col-12 col-lg-7">
    <!-- Panel Posyandu Binaan -->
    <div class="panel mb-4">
      <div class="panel-header d-flex justify-content-between align-items-center">
        <div>
          <h2 class="h5 mb-0 section-title">
            <i class="bi bi-diagram-3 text-primary me-2"></i>Jejaring Posyandu Binaan
          </h2>
          <small class="text-muted"><?= count($posyandu_list) ?> unit Posyandu di bawah koordinasi Puskesmas ini</small>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th style="width: 40px;">#</th>
              <th>Nama Posyandu</th>
              <th>Alamat / RW</th>
              <th>Kelurahan</th>
              <th class="text-center">Balita</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($posyandu_list)): ?>
              <tr>
                <td colspan="5" class="text-center py-4 text-muted">
                  Belum ada data Posyandu yang dibina oleh Puskesmas ini.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($posyandu_list as $i => $pos): ?>
                <tr>
                  <td class="text-muted"><?= $i + 1 ?></td>
                  <td class="fw-semibold text-dark">
                    <i class="bi bi-hospital text-primary me-1"></i>
                    <?= e($pos['nama_posyandu']) ?>
                  </td>
                  <td><?= e($pos['alamat'] ?: '-') ?></td>
                  <td><?= e($pos['nama_wilayah'] ?: '-') ?></td>
                  <td class="text-center">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                      <?= (int)$pos['balita_count'] ?> Anak
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Panel Petugas Kesehatan Tertaut -->
    <div class="panel">
      <div class="panel-header d-flex justify-content-between align-items-center">
        <div>
          <h2 class="h5 mb-0 section-title">
            <i class="bi bi-people text-primary me-2"></i>Tenaga Kesehatan & Petugas
          </h2>
          <small class="text-muted"><?= count($petugas_list) ?> akun petugas tertaut ke Puskesmas ini</small>
        </div>
      </div>

      <div class="p-0">
        <?php if (empty($petugas_list)): ?>
          <div class="p-4 text-center text-muted small">
            Belum ada akun petugas kesehatan yang ditugaskan di Puskesmas ini.
          </div>
        <?php else: ?>
          <ul class="list-group list-group-flush small mb-0">
            <?php foreach ($petugas_list as $pet): ?>
              <li class="list-group-item d-flex align-items-center justify-content-between px-3 py-2">
                <div class="d-flex align-items-center gap-2">
                  <div class="profile-avatar bg-success text-white rounded-circle d-flex align-items-center justify-content-center"
                       style="width: 32px; height: 32px; font-size: 0.75rem; font-weight: bold;">
                    <?= strtoupper(substr($pet['nama'], 0, 1)) ?>
                  </div>
                  <div>
                    <strong class="d-block text-dark"><?= e($pet['nama']) ?></strong>
                    <span class="text-muted" style="font-size: 0.75rem;">
                      @<?= e($pet['username']) ?> <?= !empty($pet['email']) ? '· ' . e($pet['email']) : '' ?>
                    </span>
                  </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-secondary-subtle text-secondary border">
                    <?= e($pet['nama_role'] ?: 'Petugas') ?>
                  </span>
                  <?php if (strtolower($pet['status']) === 'aktif'): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                  <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Nonaktif</span>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
require_once ROOT_PATH . '/includes/footer.php';
?>
