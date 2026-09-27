<?php
/**
 * Dashboard — SiPantau Gizi
 * ===========================
 * Halaman utama setelah login. Menampilkan ringkasan metrik.
 * 
 * CONTOH POLA HALAMAN MODUL:
 * Setiap halaman modul mengikuti pola yang sama:
 *   1. require config/app.php
 *   2. require auth-guard.php
 *   3. Set $page_title dan $current_slug
 *   4. include header.php
 *   5. Tulis konten halaman
 *   6. include footer.php
 */

// 1. Load konfigurasi (wajib di setiap halaman)
require_once __DIR__ . '/../../config/app.php';

// 2. Cek login (wajib di setiap halaman yang butuh autentikasi)
require_once ROOT_PATH . '/includes/auth-guard.php';

// 3. Set variabel untuk header & sidebar
$page_title   = 'Dashboard';
$current_slug = 'dashboard';

// 4. Tampilkan header + sidebar
include ROOT_PATH . '/includes/header.php';
?>

<!-- 5. KONTEN HALAMAN DIMULAI DI SINI -->

<div class="page-heading">
  <div class="page-heading-copy">
    <span class="page-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></span>
    <div>
      <p class="eyebrow mb-1">Ringkasan</p>
      <h1 class="h3 mb-1">Dashboard</h1>
      <p class="text-muted mb-0">Selamat datang di <?= e(APP_NAME) ?>. Pantau status gizi dari satu tampilan.</p>
    </div>
  </div>
</div>

<!-- Kartu Metrik Contoh -->
<section class="row g-3 mt-1" aria-label="Ringkasan metrik">
  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-primary">
      <div class="metric-top">
        <span class="metric-label">Total Balita</span>
        <span class="metric-icon"><i class="bi bi-person-hearts" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value">—</div>
      <div class="metric-meta">
        <span class="text-muted">Belum ada data</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-success">
      <div class="metric-top">
        <span class="metric-label">Gizi Baik</span>
        <span class="metric-icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value">—</div>
      <div class="metric-meta">
        <span class="text-muted">Belum ada data</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-warning">
      <div class="metric-top">
        <span class="metric-label">Gizi Kurang</span>
        <span class="metric-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value">—</div>
      <div class="metric-meta">
        <span class="text-muted">Belum ada data</span>
      </div>
    </article>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <article class="metric-card metric-danger">
      <div class="metric-top">
        <span class="metric-label">Stunting</span>
        <span class="metric-icon"><i class="bi bi-heartbreak" aria-hidden="true"></i></span>
      </div>
      <div class="metric-value">—</div>
      <div class="metric-meta">
        <span class="text-muted">Belum ada data</span>
      </div>
    </article>
  </div>
</section>

<!-- Panel Info Pengembangan -->
<section class="panel mt-3">
  <div class="panel-header">
    <div>
      <h2 class="h5 mb-1 section-title">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <span>Informasi Pengembangan</span>
      </h2>
      <p class="text-muted mb-0">Status pembangunan modul aplikasi.</p>
    </div>
  </div>
  <div class="p-3">
    <div class="alert alert-info mb-0">
      <i class="bi bi-gear-wide-connected me-1"></i>
      <strong>Fase 1 selesai.</strong>
      Arsitektur dasar dan layout sudah aktif. Data metrik di atas akan terisi otomatis
      setelah modul pengukuran gizi dari anggota tim lain diintegrasikan.
    </div>
  </div>
</section>

<!-- 6. Tampilkan footer -->
<?php include ROOT_PATH . '/includes/footer.php'; ?>
