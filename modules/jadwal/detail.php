<?php
/**
 * Modul Jadwal & Kunjungan — Lembar Presensi & Sweeping Balita
 * =============================================================
 * Menampilkan detail sesi posyandu, absensi kehadiran balita secara real-time,
 * tautan langsung ke pencatatan penimbangan (Pengukuran Gizi),
 * dan daftar target kunjungan rumah (sweeping) balita yang tidak hadir.
 */

$current_slug = 'jadwal';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';
require_once ROOT_PATH . '/src/Antropometri.php';

// Pastikan memiliki hak akses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'jadwal')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk melihat detail jadwal.');
    redirect(base_url('modules/jadwal/index.php'));
}

$id_jadwal = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_jadwal) {
    set_flash('danger', 'ID Jadwal tidak valid.');
    redirect(base_url('modules/jadwal/index.php'));
}

// Ambil data jadwal utama
$jadwal = null;
try {
    if ($pdo !== null) {
        $stmt = $pdo->prepare("
            SELECT 
                j.id_jadwal,
                j.id_posyandu,
                j.tanggal,
                j.waktu,
                j.lokasi,
                j.keterangan,
                pos.nama_posyandu,
                pos.alamat AS alamat_posyandu
            FROM jadwal_posyandu j
            JOIN posyandu pos ON j.id_posyandu = pos.id_posyandu
            WHERE j.id_jadwal = ?
            LIMIT 1
        ");
        $stmt->execute([$id_jadwal]);
        $jadwal = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat detail jadwal: ' . $e->getMessage());
    redirect(base_url('modules/jadwal/index.php'));
}

if (!$jadwal) {
    set_flash('warning', 'Data jadwal posyandu tidak ditemukan.');
    redirect(base_url('modules/jadwal/index.php'));
}

// ============================================================
// Proses Simpan / Update Presensi Balita (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_presensi'])) {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Token keamanan tidak valid atau telah kedaluwarsa.');
    } else {
        $status_arr = $_POST['status_kehadiran'] ?? [];
        $waktu_arr  = $_POST['waktu_kehadiran'] ?? [];
        $ket_arr    = $_POST['keterangan_kehadiran'] ?? [];

        try {
            $pdo->beginTransaction();

            $stmtUpdateHadir = $pdo->prepare("
                UPDATE kehadiran 
                SET status_kehadiran = ?, waktu_kehadiran = ?, keterangan = ?
                WHERE id_kehadiran = ? AND id_jadwal = ?
            ");

            $updated_count = 0;
            foreach ($status_arr as $id_kehadiran => $status) {
                $id_k   = (int)$id_kehadiran;
                $st     = in_array($status, ['Hadir', 'Tidak Hadir', 'Izin', 'Sakit', 'Belum Hadir']) ? $status : 'Belum Hadir';
                
                // Set waktu_kehadiran otomatis jika Hadir dan sebelumnya kosong
                $waktu_input = !empty($waktu_arr[$id_k]) ? trim($waktu_arr[$id_k]) : null;
                if ($st === 'Hadir' && empty($waktu_input)) {
                    $waktu_input = $jadwal['tanggal'] . ' ' . date('H:i:s');
                } elseif ($st !== 'Hadir') {
                    $waktu_input = null;
                }

                $ket = !empty($ket_arr[$id_k]) ? trim($ket_arr[$id_k]) : null;

                $stmtUpdateHadir->execute([$st, $waktu_input, $ket, $id_k, $id_jadwal]);
                $updated_count++;
            }

            $pdo->commit();
            set_flash('success', "Presensi kehadiran berhasil diperbarui untuk {$updated_count} balita!");
            redirect(base_url('modules/jadwal/detail.php?id=' . $id_jadwal));

        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('danger', 'Gagal memperbarui presensi: ' . $e->getMessage());
        }
    }
}

// ============================================================
// Query Daftar Kehadiran & Riwayat Pengukuran Hari Ini
// ============================================================
$kehadiran_list = [];
try {
    if ($pdo !== null) {
        $sql = "
            SELECT 
                k.id_kehadiran,
                k.id_anak,
                k.status_kehadiran,
                k.waktu_kehadiran,
                k.keterangan AS catatan_kehadiran,
                a.nama AS nama_anak,
                a.nik,
                a.tanggal_lahir,
                a.jenis_kelamin,
                a.alamat AS alamat_anak,
                ot.nama_ibu,
                ot.nama_ayah,
                ot.no_telepon AS telepon_orangtua,
                ot.alamat AS alamat_orangtua,
                p.id_pengukuran,
                p.berat_badan,
                p.tinggi_badan,
                p.lingkar_kepala,
                sg.status_tb_u,
                sg.status_bb_u,
                sg.status_bb_tb,
                sg.status_risiko
            FROM kehadiran k
            JOIN anak a ON k.id_anak = a.id_anak
            LEFT JOIN orangtua ot ON a.id_orangtua = ot.id_orangtua
            LEFT JOIN pengukuran p ON (p.id_anak = a.id_anak AND p.tanggal_pengukuran = '{$jadwal['tanggal']}')
            LEFT JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
            WHERE k.id_jadwal = ?
            ORDER BY a.nama ASC
        ";
        $stmtK = $pdo->prepare($sql);
        $stmtK->execute([$id_jadwal]);
        $kehadiran_list = $stmtK->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    set_flash('danger', 'Gagal memuat daftar kehadiran: ' . $e->getMessage());
}

// Hitung Rekapitulasi Presensi
$total_sasaran = count($kehadiran_list);
$count_hadir   = 0;
$count_absen   = 0;
$count_izin    = 0;
$count_ukur    = 0;

foreach ($kehadiran_list as $row) {
    if ($row['status_kehadiran'] === 'Hadir') {
        $count_hadir++;
    } elseif ($row['status_kehadiran'] === 'Tidak Hadir') {
        $count_absen++;
    } elseif (in_array($row['status_kehadiran'], ['Izin', 'Sakit'])) {
        $count_izin++;
    }
    if (!empty($row['id_pengukuran'])) {
        $count_ukur++;
    }
}

$persen_hadir = $total_sasaran > 0 ? round(($count_hadir / $total_sasaran) * 100) : 0;
$today        = date('Y-m-d');
$is_today     = ($jadwal['tanggal'] === $today);
$is_past      = ($jadwal['tanggal'] < $today);

$page_title  = 'Presensi Posyandu: ' . $jadwal['nama_posyandu'];
$breadcrumbs = [
    'Data & Laporan'     => '#',
    'Jadwal & Kunjungan' => base_url('modules/jadwal/index.php'),
    'Presensi Kegiatan'  => '',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-clipboard-check" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Presensi & Sweeping Balita</p>
      <h1 class="h3 mb-1"><?= e($jadwal['nama_posyandu']) ?></h1>
      <p class="text-muted mb-0">
        Pelaksanaan: <?= date('d F Y', strtotime($jadwal['tanggal'])) ?> · Pukul <?= date('H:i', strtotime($jadwal['waktu'])) ?> WIB di <?= e($jadwal['lokasi']) ?>
      </p>
    </div>
  </div>
  <div class="heading-actions d-flex flex-wrap gap-2">
    <!-- Dropdown Cetak Dokumen -->
    <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-printer me-1" aria-hidden="true"></i> Cetak Dokumen
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li><h6 class="dropdown-header small">Pilihan Cetak</h6></li>
        <li>
          <a class="dropdown-item small" target="_blank" href="<?= base_url('modules/jadwal/ekspor.php?format=print&id=' . (int)$jadwal['id_jadwal']) ?>">
            <i class="bi bi-file-earmark-text text-primary me-2"></i> Lembar Daftar Hadir Posyandu
          </a>
        </li>
        <li>
          <a class="dropdown-item small" target="_blank" href="<?= base_url('modules/jadwal/ekspor.php?format=print&id=' . (int)$jadwal['id_jadwal'] . '&type=sweeping') ?>">
            <i class="bi bi-house-door text-danger me-2"></i> Form Sweeping / Kunjungan Rumah
          </a>
        </li>
        <li>
          <a class="dropdown-item small" href="<?= base_url('modules/jadwal/ekspor.php?format=csv&id=' . (int)$jadwal['id_jadwal']) ?>">
            <i class="bi bi-file-earmark-spreadsheet text-success me-2"></i> Unduh Data (.csv)
          </a>
        </li>
      </ul>
    </div>

    <a href="<?= base_url('modules/jadwal/edit.php?id=' . (int)$jadwal['id_jadwal']) ?>" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Edit Jadwal
    </a>

    <a href="<?= base_url('modules/jadwal/index.php') ?>" class="btn btn-secondary btn-sm">
      <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
  </div>
</div>

<!-- Kartu Ringkasan Informasi Sesi & Metrik Presensi -->
<div class="row g-3 mb-4">
  <!-- 1. Sasaran Terdaftar -->
  <div class="col-sm-6 col-xl-3">
    <div class="metric-card p-3 rounded-3 border bg-white shadow-sm h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-semibold text-uppercase">Sasaran Balita</span>
        <span class="badge bg-primary-subtle text-primary p-2 rounded-circle">
          <i class="bi bi-people fs-6"></i>
        </span>
      </div>
      <div class="fs-3 fw-bold text-dark"><?= number_format($total_sasaran) ?></div>
      <small class="text-muted">Balita terdaftar di Posyandu</small>
    </div>
  </div>

  <!-- 2. Balita Hadir -->
  <div class="col-sm-6 col-xl-3">
    <div class="metric-card p-3 rounded-3 border bg-white shadow-sm h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-semibold text-uppercase">Balita Hadir</span>
        <span class="badge bg-success-subtle text-success p-2 rounded-circle">
          <i class="bi bi-person-check fs-6"></i>
        </span>
      </div>
      <div class="fs-3 fw-bold text-success"><?= number_format($count_hadir) ?> <span class="fs-6 fw-normal text-muted">(<?= $persen_hadir ?>%)</span></div>
      <small class="text-muted"><?= number_format($count_ukur) ?> balita telah ditimbang</small>
    </div>
  </div>

  <!-- 3. Target Sweeping (Tidak Hadir) -->
  <div class="col-sm-6 col-xl-3">
    <div class="metric-card p-3 rounded-3 border bg-white shadow-sm h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-semibold text-uppercase">Target Sweeping</span>
        <span class="badge bg-danger-subtle text-danger p-2 rounded-circle">
          <i class="bi bi-house-exclamation fs-6"></i>
        </span>
      </div>
      <div class="fs-3 fw-bold text-danger"><?= number_format($count_absen) ?></div>
      <small class="text-muted">Butuh kunjungan rumah</small>
    </div>
  </div>

  <!-- 4. Izin / Sakit / Belum -->
  <div class="col-sm-6 col-xl-3">
    <div class="metric-card p-3 rounded-3 border bg-white shadow-sm h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-semibold text-uppercase">Izin / Sakit / Belum</span>
        <span class="badge bg-warning-subtle text-warning p-2 rounded-circle">
          <i class="bi bi-clock-history fs-6"></i>
        </span>
      </div>
      <div class="fs-3 fw-bold text-dark"><?= number_format($count_izin + ($total_sasaran - $count_hadir - $count_absen - $count_izin)) ?></div>
      <small class="text-muted">Dalam konfirmasi kader</small>
    </div>
  </div>
</div>

<!-- Informasi Agenda & Lokasi Posyandu -->
<div class="panel mb-4">
  <div class="panel-body p-3">
    <div class="row align-items-center">
      <div class="col-md-8">
        <h6 class="fw-bold text-dark mb-1">
          <i class="bi bi-card-checklist text-primary me-2"></i>Agenda: <?= e($jadwal['keterangan'] ?: 'Penimbangan Rutin & Pemberian Vitamin A') ?>
        </h6>
        <div class="text-muted small">
          <span class="me-3"><i class="bi bi-geo-alt me-1 text-danger"></i>Lokasi: <?= e($jadwal['lokasi']) ?></span>
          <span><i class="bi bi-hospital me-1 text-primary"></i>Posyandu: <?= e($jadwal['nama_posyandu']) ?> (<?= e($jadwal['alamat_posyandu'] ?: '—') ?>)</span>
        </div>
      </div>
      <div class="col-md-4 text-md-end mt-2 mt-md-0">
        <?php if ($is_today): ?>
          <span class="badge bg-success px-3 py-2 fs-6"><i class="bi bi-broadcast me-1"></i> Sesi Berlangsung Hari Ini</span>
        <?php elseif ($is_past): ?>
          <span class="badge bg-secondary px-3 py-2"><i class="bi bi-check2-all me-1"></i> Sesi Telah Selesai</span>
        <?php else: ?>
          <span class="badge bg-primary px-3 py-2"><i class="bi bi-calendar-event me-1"></i> Sesi Mendatang</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Formulir Presensi Kehadiran & Status Penimbangan Balita -->
<section class="panel">
  <form method="POST" action="<?= base_url('modules/jadwal/detail.php?id=' . (int)$jadwal['id_jadwal']) ?>" id="formPresensi">
    <?= csrf_field() ?>
    <input type="hidden" name="simpan_presensi" value="1">

    <div class="panel-header border-bottom pb-3 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div>
        <h5 class="panel-title mb-0">Daftar Kehadiran & Status Penimbangan Balita</h5>
        <small class="text-muted">Kelola status hadir balita dan catat antropometri langsung di Posyandu</small>
      </div>

      <div class="d-flex gap-2">
        <!-- Quick Fill Helpers -->
        <button type="button" class="btn btn-outline-success btn-sm" id="btnAllHadir" title="Set semua status menjadi Hadir">
          <i class="bi bi-check-all me-1"></i> Semua Hadir
        </button>
        <button type="submit" class="btn btn-primary btn-sm px-3 shadow-sm">
          <i class="bi bi-save me-1"></i> Simpan Presensi
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light small">
          <tr>
            <th scope="col" style="width: 3%;">#</th>
            <th scope="col" style="width: 25%;">Identitas Balita & Orang Tua</th>
            <th scope="col" style="width: 17%;">Status Kehadiran</th>
            <th scope="col" style="width: 25%;">Hasil Penimbangan Hari Ini</th>
            <th scope="col" style="width: 20%;">Catatan / Alasan</th>
            <th scope="col" class="text-end" style="width: 10%;">Tindakan</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($kehadiran_list)): ?>
            <tr>
              <td colspan="6" class="text-center py-5 text-muted">
                <i class="bi bi-people fs-1 d-block mb-2 text-secondary opacity-50"></i>
                Belum ada balita yang terdaftar di posyandu ini.
                <div class="mt-2">
                  <a href="<?= base_url('modules/jadwal/edit.php?id=' . (int)$jadwal['id_jadwal']) ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-repeat me-1"></i> Sinkronisasi Balita Posyandu
                  </a>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($kehadiran_list as $idx => $k): 
              $usia_bln  = Antropometri::hitungUsiaBulan($k['tanggal_lahir'], $jadwal['tanggal']);
              $usia_teks = Antropometri::formatUsia($usia_bln);
              $is_l      = ($k['jenis_kelamin'] === 'L');
              $st_hadir  = $k['status_kehadiran'] ?: 'Belum Hadir';
            ?>
              <tr class="<?= $st_hadir === 'Tidak Hadir' ? 'table-danger-subtle' : '' ?>">
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
                      <strong class="text-dark d-block"><?= e($k['nama_anak']) ?></strong>
                      <small class="text-muted d-block" style="font-size: 0.75rem;">
                        NIK: <?= e($k['nik']) ?> · Usia: <?= e($usia_teks) ?>
                      </small>
                      <small class="text-muted d-block" style="font-size: 0.75rem;">
                        Ibu: <?= e($k['nama_ibu'] ?: '—') ?>
                        <?php if (!empty($k['telepon_orangtua'])): ?>
                          · <i class="bi bi-telephone me-1"></i><?= e($k['telepon_orangtua']) ?>
                        <?php endif; ?>
                      </small>
                    </div>
                  </div>
                </td>

                <!-- 3. Status Kehadiran -->
                <td>
                  <select name="status_kehadiran[<?= (int)$k['id_kehadiran'] ?>]"
                          class="form-select form-select-sm select-status-hadir <?= $st_hadir === 'Hadir' ? 'border-success' : ($st_hadir === 'Tidak Hadir' ? 'border-danger text-danger fw-semibold' : '') ?>">
                    <option value="Hadir" <?= $st_hadir === 'Hadir' ? 'selected' : '' ?>>Hadir</option>
                    <option value="Tidak Hadir" <?= $st_hadir === 'Tidak Hadir' ? 'selected' : '' ?>>Tidak Hadir</option>
                    <option value="Izin" <?= $st_hadir === 'Izin' ? 'selected' : '' ?>>Izin</option>
                    <option value="Sakit" <?= $st_hadir === 'Sakit' ? 'selected' : '' ?>>Sakit</option>
                    <option value="Belum Hadir" <?= $st_hadir === 'Belum Hadir' ? 'selected' : '' ?>>Belum Hadir</option>
                  </select>

                  <?php if (!empty($k['waktu_kehadiran'])): ?>
                    <small class="text-muted d-block mt-1 font-monospace" style="font-size: 0.7rem;">
                      <i class="bi bi-clock me-1"></i><?= date('H:i', strtotime($k['waktu_kehadiran'])) ?> WIB
                    </small>
                  <?php endif; ?>
                </td>

                <!-- 4. Status Penimbangan Hari Ini -->
                <td>
                  <?php if (!empty($k['id_pengukuran'])): ?>
                    <div class="small">
                      <span class="badge bg-success-subtle text-success border border-success-subtle mb-1">
                        <i class="bi bi-check-circle me-1"></i> Sudah Ditimbang
                      </span>
                      <div class="fw-semibold">
                        BB: <?= number_format($k['berat_badan'], 2) ?> kg · TB: <?= number_format($k['tinggi_badan'], 1) ?> cm
                      </div>
                      <div class="mt-1">
                        <span class="badge <?= Antropometri::getBadgeTBU($k['status_tb_u']) ?> py-0 px-1" style="font-size: 0.7rem;">
                          <?= e($k['status_tb_u']) ?>
                        </span>
                        <span class="badge <?= Antropometri::getBadgeBBU($k['status_bb_u']) ?> py-0 px-1" style="font-size: 0.7rem;">
                          <?= e($k['status_bb_u']) ?>
                        </span>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="small">
                      <span class="badge bg-light text-muted border mb-1">Belum Ada Pengukuran</span>
                      <a href="<?= base_url('modules/pengukuran/tambah.php?id_anak=' . (int)$k['id_anak'] . '&tanggal=' . urlencode($jadwal['tanggal'])) ?>"
                         class="btn btn-outline-primary btn-sm py-0 px-2 d-inline-block" style="font-size: 0.75rem;">
                        <i class="bi bi-plus-circle me-1"></i> Input Antropometri
                      </a>
                    </div>
                  <?php endif; ?>
                </td>

                <!-- 5. Catatan / Alasan -->
                <td>
                  <input type="text"
                         name="keterangan_kehadiran[<?= (int)$k['id_kehadiran'] ?>]"
                         class="form-control form-control-sm"
                         value="<?= e($k['catatan_kehadiran']) ?>"
                         placeholder="<?= $st_hadir === 'Tidak Hadir' ? 'Alasan sweeping / demam...' : 'Catatan buku KIA...' ?>">
                  <?php if ($st_hadir === 'Tidak Hadir'): ?>
                    <small class="text-danger d-block mt-1" style="font-size: 0.7rem;">
                      <i class="bi bi-exclamation-triangle me-1"></i> Rencana Sweeping Kunjungan Rumah
                    </small>
                  <?php endif; ?>
                </td>

                <!-- 6. Tindakan Cepat -->
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <?php if (!empty($k['id_pengukuran'])): ?>
                      <a href="<?= base_url('modules/pengukuran/detail.php?id=' . (int)$k['id_pengukuran']) ?>"
                         class="btn btn-outline-secondary" title="Buka Detail KMS">
                        <i class="bi bi-eye"></i>
                      </a>
                    <?php else: ?>
                      <a href="<?= base_url('modules/pengukuran/tambah.php?id_anak=' . (int)$k['id_anak'] . '&tanggal=' . urlencode($jadwal['tanggal'])) ?>"
                         class="btn btn-outline-primary" title="Timbang Sekarang">
                        <i class="bi bi-rulers"></i>
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if (!empty($kehadiran_list)): ?>
      <div class="panel-footer border-top pt-3 d-flex justify-content-between align-items-center">
        <span class="text-muted small">
          Menampilkan total <strong><?= count($kehadiran_list) ?></strong> balita sasaran.
        </span>
        <button type="submit" class="btn btn-primary px-4">
          <i class="bi bi-save me-1"></i> Simpan Perubahan Presensi
        </button>
      </div>
    <?php endif; ?>
  </form>
</section>

<?php
$extra_scripts = <<<JS
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const btnAllHadir = document.getElementById('btnAllHadir');
    if (btnAllHadir) {
      btnAllHadir.addEventListener('click', function () {
        const selects = document.querySelectorAll('.select-status-hadir');
        selects.forEach(sel => {
          sel.value = 'Hadir';
          sel.className = 'form-select form-select-sm select-status-hadir border-success';
        });
      });
    }

    // Dynamic style update saat status diganti
    document.querySelectorAll('.select-status-hadir').forEach(sel => {
      sel.addEventListener('change', function () {
        if (this.value === 'Hadir') {
          this.className = 'form-select form-select-sm select-status-hadir border-success';
        } else if (this.value === 'Tidak Hadir') {
          this.className = 'form-select form-select-sm select-status-hadir border-danger text-danger fw-semibold';
        } else {
          this.className = 'form-select form-select-sm select-status-hadir';
        }
      });
    });
  });
</script>
JS;

require_once ROOT_PATH . '/includes/footer.php';
?>
