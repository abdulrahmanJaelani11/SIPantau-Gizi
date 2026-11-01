<?php 
include 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_intervensi'])) {
    $id_pemantauan = $_POST['id_pemantauan'];
    $tanggal = $_POST['tanggal_intervensi'];
    $jenis = $_POST['jenis_intervensi'];
    $catatan = $_POST['catatan'];
    $status_update = $_POST['status_update'];
    
    // Asumsi ID user yang sedang login adalah 1 (sementara)
    $id_user = 1; 

    // Insert ke tabel catatan_intervensi
    $sql_insert = "INSERT INTO catatan_intervensi (id_pemantauan, id_user, tanggal, jenis_intervensi, catatan) 
                   VALUES ('$id_pemantauan', '$id_user', '$tanggal', '$jenis', '$catatan')";
    mysqli_query($koneksi, $sql_insert);

    // Update status di tabel pemantauan
    $sql_update = "UPDATE pemantauan SET status_tindak_lanjut = '$status_update', tanggal_tindak_lanjut = '$tanggal' WHERE id_pemantauan = '$id_pemantauan'";
    mysqli_query($koneksi, $sql_update);
    
    // Refresh halaman agar form tidak tersubmit ulang
    header("Location: daftar_pantau.php");
    exit;
}

include 'header.php'; 
?>

<main class="dashboard-content">
  <div class="container-fluid px-3 px-lg-4 py-4">
    
    <!-- Page Heading -->
    <div class="page-heading mb-4">
      <div class="page-heading-copy">
        <span class="page-icon"><i class="bi bi-exclamation-triangle-fill text-warning" aria-hidden="true"></i></span>
        <div>
          <p class="eyebrow mb-1">SiPantau Gizi</p>
          <h1 class="h3 mb-1">Daftar Pantau</h1>
          <p class="text-muted mb-0">Daftar anak yang berisiko stunting atau gizi buruk dan membutuhkan tindak lanjut segera.</p>
        </div>
      </div>
    </div>

    <!-- Data Table Card -->
    <section class="panel">
      <div class="panel-header">
        <div>
          <h2 class="h5 mb-1 section-title"><i class="bi bi-card-list" aria-hidden="true"></i><span>Data Anak Berisiko</span></h2>
        </div>
      </div>
      
      <div class="panel-body p-3">
        <div class="table-responsive">
          <table id="tableDaftarPantau" class="table table-striped table-hover align-middle mb-0 w-100">
            <thead class="table-light">
              <tr>
                <th>No</th>
                <th>Nama Anak</th>
                <th>Usia (Bulan)</th>
                <th>Tanggal Pengukuran</th>
                <th>Jenis Risiko</th>
                <th>Status Tindak Lanjut</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php
              // include 'koneksi.php'; sudah dipanggil di atas
              
              // Query untuk mengambil anak yang memiliki riwayat "pemantauan"
              $query = "
                SELECT 
                    a.id_anak, 
                    a.nama AS nama_anak, 
                    a.tanggal_lahir, 
                    o.nama_ibu, 
                    p.tanggal_pengukuran, 
                    sg.status_risiko, 
                    pm.status_tindak_lanjut, 
                    pm.id_pemantauan 
                FROM pemantauan pm
                JOIN anak a ON pm.id_anak = a.id_anak
                JOIN orangtua o ON a.id_orangtua = o.id_orangtua
                JOIN pengukuran p ON pm.id_pengukuran = p.id_pengukuran
                JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
                ORDER BY pm.id_pemantauan DESC
              ";
              
              $result = mysqli_query($koneksi, $query);
              $no = 1;

              if(mysqli_num_rows($result) > 0) {
                  while($row = mysqli_fetch_assoc($result)) {
                      // Hitung usia dalam bulan
                      $tanggal_lahir = new DateTime($row['tanggal_lahir']);
                      $sekarang = new DateTime();
                      $diff = $sekarang->diff($tanggal_lahir);
                      $usia_bulan = ($diff->format('%y') * 12) + $diff->format('%m');
                      
                      // Penentuan warna badge status tindak lanjut
                      $badgeStatus = 'bg-secondary';
                      if($row['status_tindak_lanjut'] == 'Baru') {
                          $badgeStatus = 'border border-danger text-danger bg-transparent';
                      } elseif($row['status_tindak_lanjut'] == 'Ditindaklanjuti') {
                          $badgeStatus = 'bg-warning text-dark';
                      } elseif($row['status_tindak_lanjut'] == 'Selesai') {
                          $badgeStatus = 'bg-success';
                      }
              ?>
              <tr>
                <td><?= $no++; ?></td>
                <td>
                  <p class="fw-semibold mb-0"><?= htmlspecialchars($row['nama_anak']); ?></p>
                  <p class="text-muted small mb-0">Ibu: <?= htmlspecialchars($row['nama_ibu']); ?></p>
                </td>
                <td><?= $usia_bulan; ?></td>
                <td><?= date('d M Y', strtotime($row['tanggal_pengukuran'])); ?></td>
                <td><span class="badge bg-danger"><?= htmlspecialchars($row['status_risiko'] ?: 'Berisiko'); ?></span></td>
                <td><span class="badge <?= $badgeStatus; ?>"><?= htmlspecialchars($row['status_tindak_lanjut'] ?: 'Baru'); ?></span></td>
                <td class="text-center">
                  <?php if($row['status_tindak_lanjut'] == 'Selesai'): ?>
                    <button class="btn btn-outline-secondary btn-sm" disabled>
                      <i class="bi bi-check2-all"></i> Selesai
                    </button>
                  <?php else: ?>
                    <button class="btn <?= $row['status_tindak_lanjut'] == 'Baru' ? 'btn-primary' : 'btn-outline-primary' ?> btn-sm" data-bs-toggle="modal" data-bs-target="#modalIntervensi<?= $row['id_pemantauan'] ?>">
                      <i class="bi <?= $row['status_tindak_lanjut'] == 'Baru' ? 'bi-heart-pulse' : 'bi-pencil-square' ?>"></i> 
                      <?= $row['status_tindak_lanjut'] == 'Baru' ? 'Tindak Lanjut' : 'Update' ?>
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
              
              <!-- Modal Input Catatan Intervensi per Anak -->
              <div class="modal fade" id="modalIntervensi<?= $row['id_pemantauan'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Catatan Tindak Lanjut</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="" method="POST">
                      <div class="modal-body text-start">
                        <input type="hidden" name="id_pemantauan" value="<?= $row['id_pemantauan'] ?>">
                        <div class="alert alert-info mb-3">
                          Menambahkan intervensi untuk anak: <strong><?= htmlspecialchars($row['nama_anak']) ?></strong>
                        </div>
                        
                        <div class="mb-3">
                          <label class="form-label">Tanggal Intervensi</label>
                          <input type="date" class="form-control" name="tanggal_intervensi" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                          <label class="form-label">Jenis Intervensi</label>
                          <select class="form-select" name="jenis_intervensi" required>
                            <option value="">-- Pilih Intervensi --</option>
                            <option value="Konseling Gizi / Penyuluhan">Konseling Gizi / Penyuluhan</option>
                            <option value="Pemberian Makanan Tambahan (PMT)">Pemberian Makanan Tambahan (PMT)</option>
                            <option value="Rujukan ke Puskesmas / RS">Rujukan ke Puskesmas / RS</option>
                          </select>
                        </div>
                        
                        <div class="mb-3">
                          <label class="form-label">Keterangan / Catatan</label>
                          <textarea class="form-control" name="catatan" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                          <label class="form-label">Update Status</label>
                          <select class="form-select" name="status_update">
                            <option value="Ditindaklanjuti">Ditindaklanjuti (Sedang berjalan)</option>
                            <option value="Selesai">Selesai (Kondisi sudah membaik/normal)</option>
                          </select>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="submit_intervensi" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Catatan</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>

              <?php 
                  } 
              } else {
              ?>
              <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                    Belum ada data anak di daftar pantau.
                </td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

  </div>
</main>



<?php include 'footer.php'; ?>
