<?php
/**
 * Modul Pengukuran Gizi — Input Pengukuran & Penimbangan Baru
 * ==============================================================
 * Formulir pencatatan hasil pengukuran antropometri balita.
 * Terintegrasi dengan engine kalkulasi Z-Score otomatis dan penilaian status gizi real-time.
 */

$current_slug = 'pengukuran';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';
require_once ROOT_PATH . '/src/Antropometri.php';

// Pastikan memiliki hak akses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'pengukuran')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk menginput pengukuran.');
    redirect(base_url('modules/dashboard/index.php'));
}

// Ambil daftar balita
$anak_list = [];
try {
    if ($pdo !== null) {
        $stmtAnak = $pdo->query("
            SELECT a.id_anak, a.nama, a.nik, a.tanggal_lahir, a.jenis_kelamin,
                   p.nama_posyandu, ot.nama_ibu
            FROM anak a
            LEFT JOIN posyandu p ON a.id_posyandu = p.id_posyandu
            LEFT JOIN orangtua ot ON a.id_orangtua = ot.id_orangtua
            ORDER BY a.nama ASC
        ");
        $anak_list = $stmtAnak->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $anak_list = [];
}

$errors = [];

// ============================================================
// Proses Simpan Data Pengukuran (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Cek CSRF
    if (!verify_csrf_token()) {
        $errors[] = 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba lagi.';
    }

    $id_anak            = filter_input(INPUT_POST, 'id_anak', FILTER_VALIDATE_INT);
    $tanggal_pengukuran = trim($_POST['tanggal_pengukuran'] ?? date('Y-m-d'));
    $berat_badan        = filter_input(INPUT_POST, 'berat_badan', FILTER_VALIDATE_FLOAT);
    $tinggi_badan       = filter_input(INPUT_POST, 'tinggi_badan', FILTER_VALIDATE_FLOAT);
    $lingkar_kepala     = !empty($_POST['lingkar_kepala']) ? filter_input(INPUT_POST, 'lingkar_kepala', FILTER_VALIDATE_FLOAT) : null;
    $id_user            = (int)($_SESSION['user_id'] ?? 1);

    // Simpan input lama
    set_old_input([
        'id_anak'            => $id_anak,
        'tanggal_pengukuran' => $tanggal_pengukuran,
        'berat_badan'        => $berat_badan,
        'tinggi_badan'       => $tinggi_badan,
        'lingkar_kepala'     => $lingkar_kepala,
    ]);

    // 2. Validasi Pilihan Balita
    $anak_data = null;
    if (!$id_anak) {
        $errors['id_anak'] = 'Silakan pilih balita yang akan diukur.';
    } else {
        try {
            $stmtCek = $pdo->prepare("SELECT id_anak, nama, tanggal_lahir, jenis_kelamin FROM anak WHERE id_anak = ? LIMIT 1");
            $stmtCek->execute([$id_anak]);
            $anak_data = $stmtCek->fetch(PDO::FETCH_ASSOC);
            if (!$anak_data) {
                $errors['id_anak'] = 'Data balita tidak ditemukan di database.';
            }
        } catch (PDOException $e) {
            $errors['id_anak'] = 'Gagal memverifikasi balita: ' . $e->getMessage();
        }
    }

    // 3. Validasi Tanggal
    if ($tanggal_pengukuran === '') {
        $errors['tanggal_pengukuran'] = 'Tanggal pengukuran wajib diisi.';
    } elseif ($anak_data && strtotime($tanggal_pengukuran) < strtotime($anak_data['tanggal_lahir'])) {
        $errors['tanggal_pengukuran'] = 'Tanggal pengukuran tidak boleh mendahului tanggal lahir balita (' . date('d/m/Y', strtotime($anak_data['tanggal_lahir'])) . ').';
    }

    // 4. Validasi Berat Badan
    if ($berat_badan === false || $berat_badan === null || $berat_badan <= 0) {
        $errors['berat_badan'] = 'Berat badan wajib diisi angka positif (kg).';
    } elseif ($berat_badan < 1.0 || $berat_badan > 50.0) {
        $errors['berat_badan'] = 'Berat badan di luar rentang normal balita (1.0 - 50.0 kg).';
    }

    // 5. Validasi Tinggi Badan
    if ($tinggi_badan === false || $tinggi_badan === null || $tinggi_badan <= 0) {
        $errors['tinggi_badan'] = 'Tinggi / panjang badan wajib diisi angka positif (cm).';
    } elseif ($tinggi_badan < 30.0 || $tinggi_badan > 150.0) {
        $errors['tinggi_badan'] = 'Tinggi / panjang badan di luar rentang normal balita (30.0 - 150.0 cm).';
    }

    // 6. Validasi Lingkar Kepala (Opsional)
    if ($lingkar_kepala !== null && ($lingkar_kepala < 20.0 || $lingkar_kepala > 65.0)) {
        $errors['lingkar_kepala'] = 'Lingkar kepala di luar rentang wajar (20.0 - 65.0 cm).';
    }

    // 7. Eksekusi Simpan ke Tabel pengukuran & status_gizi (Atomic Transaction)
    if (empty($errors) && $anak_data && $pdo !== null) {
        try {
            $pdo->beginTransaction();

            // Hitung Z-Score & Status Gizi menggunakan Engine Antropometri
            $usia_bulan = Antropometri::hitungUsiaBulan($anak_data['tanggal_lahir'], $tanggal_pengukuran);
            $hasilGizi  = Antropometri::kalkulasiStatusGizi((float)$berat_badan, (float)$tinggi_badan, $usia_bulan, $anak_data['jenis_kelamin']);

            // Insert ke tabel pengukuran
            $stmtPengukuran = $pdo->prepare("
                INSERT INTO pengukuran (id_anak, id_user, tanggal_pengukuran, berat_badan, tinggi_badan, lingkar_kepala)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtPengukuran->execute([
                $id_anak,
                $id_user,
                $tanggal_pengukuran,
                $berat_badan,
                $tinggi_badan,
                $lingkar_kepala ?: null
            ]);
            $new_pengukuran_id = (int)$pdo->lastInsertId();

            // Insert ke tabel status_gizi
            $stmtStatusGizi = $pdo->prepare("
                INSERT INTO status_gizi (
                    id_pengukuran, zscore_bb_u, zscore_tb_u, zscore_bb_tb, zscore_imt_u,
                    status_bb_u, status_tb_u, status_bb_tb, status_imt_u, status_risiko
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtStatusGizi->execute([
                $new_pengukuran_id,
                $hasilGizi['zscore_bb_u'],
                $hasilGizi['zscore_tb_u'],
                $hasilGizi['zscore_bb_tb'],
                $hasilGizi['zscore_imt_u'],
                $hasilGizi['status_bb_u'],
                $hasilGizi['status_tb_u'],
                $hasilGizi['status_bb_tb'],
                $hasilGizi['status_imt_u'],
                $hasilGizi['status_risiko']
            ]);

            $pdo->commit();

            clear_old_input();
            set_flash('success', "Pengukuran untuk <strong>" . e($anak_data['nama']) . "</strong> berhasil disimpan! Status TB/U: <strong>" . e($hasilGizi['status_tb_u']) . "</strong>, BB/U: <strong>" . e($hasilGizi['status_bb_u']) . "</strong>.");
            redirect(base_url('modules/pengukuran/index.php'));

        } catch (Exception $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Gagal menyimpan hasil pengukuran: ' . $e->getMessage();
        }
    }
}

// Variabel layout
$page_title  = 'Input Pengukuran Balita';
$breadcrumbs = [
    'Data & Laporan'   => '#',
    'Pengukuran Gizi'  => base_url('modules/pengukuran/index.php'),
    'Input Pengukuran' => '',
];

$default_tanggal = old('tanggal_pengukuran', date('Y-m-d'));

require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Heading Halaman -->
<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-clipboard2-plus" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Data & Laporan &rsaquo; Pengukuran Gizi</p>
      <h1 class="h3 mb-1">Input Penimbangan & Pengukuran</h1>
      <p class="text-muted mb-0">Catat parameter antropometri balita dan pantau kalkulasi Z-Score otomatis secara langsung.</p>
    </div>
  </div>
  <div class="heading-actions">
    <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('modules/pengukuran/index.php') ?>">
      <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali ke Riwayat
    </a>
  </div>
</div>

<!-- Alert Error Validasi -->
<?php if (!empty($errors)): ?>
  <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
    <div class="d-flex align-items-center mb-1">
      <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
      <strong>Terdapat beberapa kesalahan input:</strong>
    </div>
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $field => $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
  </div>
<?php endif; ?>

<form method="POST" action="" class="mt-3" id="formPengukuran">
  <?= csrf_field() ?>

  <div class="row g-4">
    <!-- Kolom Kiri: Form Input Data Antropometri -->
    <div class="col-12 col-lg-7">
      <div class="panel">
        <div class="panel-header">
          <h2 class="h5 mb-0 section-title">
            <i class="bi bi-person-heart text-primary me-2"></i>Parameter Antropometri
          </h2>
        </div>

        <div class="p-3 p-lg-4">
          <!-- 1. Pilihan Balita -->
          <div class="mb-3">
            <label for="id_anak" class="form-label fw-semibold">
              Pilih Balita <span class="text-danger">*</span>
            </label>
            <select name="id_anak"
                    id="id_anak"
                    class="form-select <?= isset($errors['id_anak']) ? 'is-invalid' : '' ?>"
                    required
                    autofocus>
              <option value="">-- Cari / Pilih Balita --</option>
              <?php foreach ($anak_list as $a): 
                $selected = ((string)old('id_anak') === (string)$a['id_anak']);
              ?>
                <option value="<?= (int)$a['id_anak'] ?>"
                        data-lahir="<?= e($a['tanggal_lahir']) ?>"
                        data-gender="<?= e($a['jenis_kelamin']) ?>"
                        data-nik="<?= e($a['nik']) ?>"
                        data-ibu="<?= e($a['nama_ibu'] ?: '-') ?>"
                        data-posyandu="<?= e($a['nama_posyandu'] ?: '-') ?>"
                        <?= $selected ? 'selected' : '' ?>>
                  <?= e($a['nama']) ?> (<?= strtoupper($a['jenis_kelamin']) ?>) — NIK: <?= e($a['nik']) ?> [<?= e($a['nama_posyandu']) ?>]
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Pilih balita yang terdaftar di posyandu untuk ditimbang.</div>
            <?php if (isset($errors['id_anak'])): ?>
              <div class="invalid-feedback"><?= e($errors['id_anak']) ?></div>
            <?php endif; ?>
          </div>

          <!-- 2. Tanggal Pengukuran -->
          <div class="mb-3">
            <label for="tanggal_pengukuran" class="form-label fw-semibold">
              Tanggal Penimbangan <span class="text-danger">*</span>
            </label>
            <input type="date"
                   name="tanggal_pengukuran"
                   id="tanggal_pengukuran"
                   class="form-control <?= isset($errors['tanggal_pengukuran']) ? 'is-invalid' : '' ?>"
                   value="<?= e($default_tanggal) ?>"
                   max="<?= date('Y-m-d') ?>"
                   required>
            <div class="form-text">Tanggal saat kegiatan penimbangan posyandu dilaksanakan.</div>
            <?php if (isset($errors['tanggal_pengukuran'])): ?>
              <div class="invalid-feedback"><?= e($errors['tanggal_pengukuran']) ?></div>
            <?php endif; ?>
          </div>

          <!-- 3. Parameter BB & TB -->
          <div class="row g-3 mb-3">
            <div class="col-12 col-md-6">
              <label for="berat_badan" class="form-label fw-semibold">
                Berat Badan (BB) <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <input type="number"
                       step="0.01"
                       min="1.0"
                       max="50.0"
                       name="berat_badan"
                       id="berat_badan"
                       class="form-control <?= isset($errors['berat_badan']) ? 'is-invalid' : '' ?>"
                       placeholder="Contoh: 9.45"
                       value="<?= e(old('berat_badan')) ?>"
                       required>
                <span class="input-group-text fw-bold">kg</span>
              </div>
              <div class="form-text">Gunakan timbangan digital bayi (akurasi 2 desimal).</div>
              <?php if (isset($errors['berat_badan'])): ?>
                <div class="invalid-feedback d-block"><?= e($errors['berat_badan']) ?></div>
              <?php endif; ?>
            </div>

            <div class="col-12 col-md-6">
              <label for="tinggi_badan" class="form-label fw-semibold">
                Panjang / Tinggi Badan (PB/TB) <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <input type="number"
                       step="0.1"
                       min="30.0"
                       max="150.0"
                       name="tinggi_badan"
                       id="tinggi_badan"
                       class="form-control <?= isset($errors['tinggi_badan']) ? 'is-invalid' : '' ?>"
                       placeholder="Contoh: 76.5"
                       value="<?= e(old('tinggi_badan')) ?>"
                       required>
                <span class="input-group-text fw-bold">cm</span>
              </div>
              <div class="form-text">Panjang badan terlentang (<2 thn) / berdiri (>=2 thn).</div>
              <?php if (isset($errors['tinggi_badan'])): ?>
                <div class="invalid-feedback d-block"><?= e($errors['tinggi_badan']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <!-- 4. Lingkar Kepala (Opsional) -->
          <div class="mb-3">
            <label for="lingkar_kepala" class="form-label fw-semibold">
              Lingkar Kepala (LiLA/LK) <span class="text-muted small">(Opsional)</span>
            </label>
            <div class="input-group" style="max-width: 320px;">
              <input type="number"
                     step="0.1"
                     min="20.0"
                     max="65.0"
                     name="lingkar_kepala"
                     id="lingkar_kepala"
                     class="form-control <?= isset($errors['lingkar_kepala']) ? 'is-invalid' : '' ?>"
                     placeholder="Contoh: 44.5"
                     value="<?= e(old('lingkar_kepala')) ?>">
              <span class="input-group-text fw-bold">cm</span>
            </div>
            <div class="form-text">Pemantauan perkembangan lingkar kepala untuk deteksi mikrosefali/makrosefali.</div>
            <?php if (isset($errors['lingkar_kepala'])): ?>
              <div class="invalid-feedback d-block"><?= e($errors['lingkar_kepala']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="panel-footer bg-light border-top p-3 d-flex justify-content-end gap-2">
          <a href="<?= base_url('modules/pengukuran/index.php') ?>" class="btn btn-secondary btn-sm">
            Batal
          </a>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-save me-1"></i> Simpan Hasil Pengukuran
          </button>
        </div>
      </div>
    </div>

    <!-- Kolom Kanan: Pratinjau Status Gizi Otomatis (Live Calculation) -->
    <div class="col-12 col-lg-5">
      <div class="panel h-100">
        <div class="panel-header d-flex justify-content-between align-items-center">
          <h2 class="h5 mb-0 section-title">
            <i class="bi bi-heart-pulse-fill text-danger me-2"></i>Analisis Status Gizi Otomatis
          </h2>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="badgeEngine">
            WHO / Kemenkes
          </span>
        </div>

        <div class="p-3 p-lg-4">
          <!-- Info Balita Terpilih -->
          <div class="p-3 bg-light rounded-3 border mb-3" id="boxBalitaInfo">
            <span class="small text-muted d-block mb-1">Identitas Balita Terpilih:</span>
            <h6 class="mb-1 fw-bold text-dark" id="txtNamaBalita">Silakan pilih balita</h6>
            <div class="small text-muted" id="txtMetaBalita">
              Pilih nama balita di formulir sebelah kiri untuk melihat informasi usia dan pratinjau status gizi.
            </div>
          </div>

          <!-- Indikator Live Z-Score -->
          <div id="boxHasilGizi" class="d-none">
            <div class="alert alert-info py-2 small mb-3">
              <i class="bi bi-calculator me-1"></i>
              Usia saat penimbangan: <strong id="lblUsiaTeks">-</strong>
            </div>

            <!-- Kartu Status TB/U (Stunting) -->
            <div class="card p-3 border rounded-3 mb-2 bg-light-subtle">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <span class="text-muted small d-block">TB/U (Indikator Stunting)</span>
                  <span class="badge fs-6 mt-1" id="badgeTBU">-</span>
                </div>
                <div class="text-end">
                  <span class="small text-muted d-block">Z-Score</span>
                  <strong class="fs-6 font-monospace" id="lblZscoreTBU">-</strong>
                </div>
              </div>
            </div>

            <!-- Kartu Status BB/U (Gizi) -->
            <div class="card p-3 border rounded-3 mb-2 bg-light-subtle">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <span class="text-muted small d-block">BB/U (Berat per Umur)</span>
                  <span class="badge fs-6 mt-1" id="badgeBBU">-</span>
                </div>
                <div class="text-end">
                  <span class="small text-muted d-block">Z-Score</span>
                  <strong class="fs-6 font-monospace" id="lblZscoreBBU">-</strong>
                </div>
              </div>
            </div>

            <!-- Kartu Status BB/TB (Wasting) -->
            <div class="card p-3 border rounded-3 mb-3 bg-light-subtle">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <span class="text-muted small d-block">BB/TB (Indikator Wasting)</span>
                  <span class="badge fs-6 mt-1" id="badgeBBTB">-</span>
                </div>
                <div class="text-end">
                  <span class="small text-muted d-block">Z-Score</span>
                  <strong class="fs-6 font-monospace" id="lblZscoreBBTB">-</strong>
                </div>
              </div>
            </div>

            <!-- Peringatan Dini Status Risiko -->
            <div class="p-3 rounded-3 border" id="alertRisikoBox">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-exclamation fs-4" id="iconRisiko"></i>
                <div>
                  <strong class="d-block" id="titleRisiko">Status Risiko</strong>
                  <span class="small text-muted" id="descRisiko">Penilaian otomatis berdasarkan regulasi Kemenkes RI.</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Placeholder saat belum mengisi BB & TB -->
          <div id="boxPlaceholder" class="text-center py-5 text-muted">
            <i class="bi bi-activity fs-1 d-block mb-2 text-secondary opacity-50"></i>
            <p class="small mb-0">Isi Berat Badan (BB) dan Tinggi Badan (TB) untuk memicu kalkulasi Z-score real-time.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>

<?php
$api_url = base_url('modules/pengukuran/api_hitung.php');
$extra_scripts = <<<JS
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const selAnak     = document.getElementById('id_anak');
    const inputTgl    = document.getElementById('tanggal_pengukuran');
    const inputBB     = document.getElementById('berat_badan');
    const inputTB     = document.getElementById('tinggi_badan');

    const txtNama     = document.getElementById('txtNamaBalita');
    const txtMeta     = document.getElementById('txtMetaBalita');

    const boxHasil    = document.getElementById('boxHasilGizi');
    const boxPlace    = document.getElementById('boxPlaceholder');

    const lblUsia     = document.getElementById('lblUsiaTeks');
    const badgeTBU    = document.getElementById('badgeTBU');
    const lblZTBU     = document.getElementById('lblZscoreTBU');
    const badgeBBU    = document.getElementById('badgeBBU');
    const lblZBBU     = document.getElementById('lblZscoreBBU');
    const badgeBBTB   = document.getElementById('badgeBBTB');
    const lblZBBTB    = document.getElementById('lblZscoreBBTB');

    const alertBox    = document.getElementById('alertRisikoBox');
    const titleRisiko = document.getElementById('titleRisiko');
    const descRisiko  = document.getElementById('descRisiko');
    const iconRisiko  = document.getElementById('iconRisiko');

    function updateBalitaInfo() {
      const opt = selAnak.options[selAnak.selectedIndex];
      if (opt && opt.value) {
        txtNama.textContent = opt.textContent.split('—')[0].trim();
        txtMeta.innerHTML = 'NIK: <strong>' + opt.getAttribute('data-nik') + '</strong> · Ibu: ' + opt.getAttribute('data-ibu') + ' · ' + opt.getAttribute('data-posyandu');
      } else {
        txtNama.textContent = 'Silakan pilih balita';
        txtMeta.textContent = 'Pilih nama balita di formulir sebelah kiri untuk melihat informasi usia dan pratinjau status gizi.';
      }
    }

    let debounceTimer = null;
    function triggerHitung() {
      updateBalitaInfo();

      const idAnak = selAnak.value;
      const tgl    = inputTgl.value;
      const bb     = parseFloat(inputBB.value);
      const tb     = parseFloat(inputTB.value);

      if (!idAnak || isNaN(bb) || isNaN(tb) || bb <= 0 || tb <= 0) {
        boxHasil.classList.add('d-none');
        boxPlace.classList.remove('d-none');
        return;
      }

      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        fetch('{$api_url}?id_anak=' + idAnak + '&tanggal_pengukuran=' + encodeURIComponent(tgl) + '&bb=' + bb + '&tb=' + tb)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              lblUsia.textContent = data.usia_teks;

              badgeTBU.textContent = data.status_tb_u;
              badgeTBU.className = 'badge fs-6 mt-1 ' + data.badge_tb_u;
              lblZTBU.textContent = (data.zscore_tb_u > 0 ? '+' : '') + data.zscore_tb_u + ' SD';

              badgeBBU.textContent = data.status_bb_u;
              badgeBBU.className = 'badge fs-6 mt-1 ' + data.badge_bb_u;
              lblZBBU.textContent = (data.zscore_bb_u > 0 ? '+' : '') + data.zscore_bb_u + ' SD';

              badgeBBTB.textContent = data.status_bb_tb;
              badgeBBTB.className = 'badge fs-6 mt-1 ' + data.badge_bb_tb;
              lblZBBTB.textContent = (data.zscore_bb_tb > 0 ? '+' : '') + data.zscore_bb_tb + ' SD';

              titleRisiko.textContent = 'Status Risiko: ' + data.status_risiko;
              if (data.status_risiko === 'Butuh Rujukan Segera') {
                alertBox.className = 'p-3 rounded-3 border bg-danger-subtle text-danger border-danger-subtle';
                descRisiko.textContent = 'PERHATIAN: Balita terindikasi ' + data.status_tb_u + ' / ' + data.status_bb_u + '. Perlu koordinasi rujukan ke Puskesmas.';
              } else if (data.status_risiko === 'Berisiko') {
                alertBox.className = 'p-3 rounded-3 border bg-warning-subtle text-warning border-warning-subtle';
                descRisiko.textContent = 'WASPADA: Balita berisiko mengalami gangguan gizi. Pantau ketat penimbangan bulan depan.';
              } else {
                alertBox.className = 'p-3 rounded-3 border bg-success-subtle text-success border-success-subtle';
                descRisiko.textContent = 'Tumbuh kembang optimal. Pertahankan pola asuh dan asupan gizi seimbang.';
              }

              boxPlace.classList.add('d-none');
              boxHasil.classList.remove('d-none');
            }
          })
          .catch(err => {
            console.error('Error kalkulasi:', err);
          });
      }, 250);
    }

    selAnak.addEventListener('change', triggerHitung);
    inputTgl.addEventListener('change', triggerHitung);
    inputBB.addEventListener('input', triggerHitung);
    inputTB.addEventListener('input', triggerHitung);

    // Initial check jika old input terisi
    triggerHitung();
  });
</script>
JS;

require_once ROOT_PATH . '/includes/footer.php';
?>
