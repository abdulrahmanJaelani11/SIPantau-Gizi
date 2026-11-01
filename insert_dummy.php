<?php
include 'koneksi.php';

// 1. Insert Wilayah
mysqli_query($koneksi, "INSERT INTO wilayah (nama_wilayah, kecamatan, kabupaten) VALUES ('Desa Sejahtera', 'Sukamaju', 'Bandung')");
$id_wilayah = mysqli_insert_id($koneksi);

// 2. Insert Puskesmas
mysqli_query($koneksi, "INSERT INTO puskesmas (id_wilayah, nama_puskesmas, alamat, no_telepon) VALUES ($id_wilayah, 'Puskesmas Sukamaju', 'Jl. Raya Sukamaju No.1', '022123456')");
$id_puskesmas = mysqli_insert_id($koneksi);

// 3. Insert Posyandu
mysqli_query($koneksi, "INSERT INTO posyandu (id_puskesmas, id_wilayah, nama_posyandu, alamat) VALUES ($id_puskesmas, $id_wilayah, 'Posyandu Melati', 'Balai Desa Sejahtera')");
$id_posyandu = mysqli_insert_id($koneksi);

// 4. Insert User (Kader)
// id_role 1 = Kader
mysqli_query($koneksi, "INSERT INTO user (id_role, id_posyandu, nama, username, password_hash, email) VALUES (1, $id_posyandu, 'Siti Kader', 'kader1', 'hashpassword123', 'kader@contoh.com')");
$id_user = mysqli_insert_id($koneksi);

// 5. Insert Orang Tua
mysqli_query($koneksi, "INSERT INTO orangtua (nama_ayah, nama_ibu, no_telepon, alamat) VALUES ('Budi Santoso', 'Siti Aminah', '081234567890', 'RT 01 RW 02 Desa Sejahtera')");
$id_orangtua = mysqli_insert_id($koneksi);

mysqli_query($koneksi, "INSERT INTO orangtua (nama_ayah, nama_ibu, no_telepon, alamat) VALUES ('Andi Wijaya', 'Rini Wahyuni', '081987654321', 'RT 03 RW 02 Desa Sejahtera')");
$id_orangtua2 = mysqli_insert_id($koneksi);

// 6. Insert Anak
mysqli_query($koneksi, "INSERT INTO anak (id_posyandu, id_orangtua, nik, nama, tanggal_lahir, jenis_kelamin) VALUES ($id_posyandu, $id_orangtua, '3200010101240001', 'Arkan Santoso', '2024-05-10', 'L')");
$id_anak1 = mysqli_insert_id($koneksi);

mysqli_query($koneksi, "INSERT INTO anak (id_posyandu, id_orangtua, nik, nama, tanggal_lahir, jenis_kelamin) VALUES ($id_posyandu, $id_orangtua2, '3200010101250002', 'Aisyah Putri', '2025-01-15', 'P')");
$id_anak2 = mysqli_insert_id($koneksi);

// 7. Insert Pengukuran (Bulan ini)
$tgl_ukur = date('Y-m-d');
mysqli_query($koneksi, "INSERT INTO pengukuran (id_anak, id_user, tanggal_pengukuran, berat_badan, tinggi_badan, lingkar_kepala) VALUES ($id_anak1, $id_user, '$tgl_ukur', 8.5, 75.2, 45.0)");
$id_ukur1 = mysqli_insert_id($koneksi);

mysqli_query($koneksi, "INSERT INTO pengukuran (id_anak, id_user, tanggal_pengukuran, berat_badan, tinggi_badan, lingkar_kepala) VALUES ($id_anak2, $id_user, '$tgl_ukur', 7.1, 70.0, 42.0)");
$id_ukur2 = mysqli_insert_id($koneksi);

// 8. Insert Status Gizi (Risiko)
mysqli_query($koneksi, "INSERT INTO status_gizi (id_pengukuran, status_bb_u, status_tb_u, status_bb_tb, status_imt_u, status_risiko) VALUES ($id_ukur1, 'Gizi Kurang', 'Normal', 'Normal', 'Normal', 'Berat Tidak Naik')");

mysqli_query($koneksi, "INSERT INTO status_gizi (id_pengukuran, status_bb_u, status_tb_u, status_bb_tb, status_imt_u, status_risiko) VALUES ($id_ukur2, 'Gizi Buruk', 'Sangat Pendek', 'Kurus', 'Kurus', 'Stunting')");

// 9. Insert Pemantauan (Ini yang bikin masuk ke Daftar Pantau)
mysqli_query($koneksi, "INSERT INTO pemantauan (id_anak, id_pengukuran, tanggal_mulai, status_tindak_lanjut) VALUES ($id_anak1, $id_ukur1, '$tgl_ukur', 'Baru')");

mysqli_query($koneksi, "INSERT INTO pemantauan (id_anak, id_pengukuran, tanggal_mulai, status_tindak_lanjut) VALUES ($id_anak2, $id_ukur2, '$tgl_ukur', 'Ditindaklanjuti')");

echo "SUKSES: Data dummy berhasil disuntikkan!";
?>
