-- ============================================================
-- Database: db_sipantau_gizi
-- Sistem Informasi Pemantauan Gizi (SiPantau Gizi)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `db_sipantau_gizi` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `db_sipantau_gizi`;

DROP TABLE IF EXISTS `wilayah`;
CREATE TABLE `wilayah` (
  `id_wilayah` int(11) NOT NULL AUTO_INCREMENT,
  `nama_wilayah` varchar(100) NOT NULL,
  `kecamatan` varchar(100) NOT NULL,
  `kabupaten` varchar(100) NOT NULL,
  PRIMARY KEY (`id_wilayah`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `wilayah` (`id_wilayah`, `nama_wilayah`, `kecamatan`, `kabupaten`) VALUES
  ('1', 'Kelurahan Sukamaju', 'Cilodong', 'Kota Depok'),
  ('2', 'Kelurahan Kalimulya', 'Cilodong', 'Kota Depok');

DROP TABLE IF EXISTS `puskesmas`;
CREATE TABLE `puskesmas` (
  `id_puskesmas` int(11) NOT NULL AUTO_INCREMENT,
  `id_wilayah` int(11) NOT NULL,
  `nama_puskesmas` varchar(100) NOT NULL,
  `alamat` text NOT NULL,
  `no_telepon` varchar(20) NOT NULL,
  PRIMARY KEY (`id_puskesmas`),
  KEY `fk_puskesmas_wilayah` (`id_wilayah`),
  CONSTRAINT `fk_puskesmas_wilayah` FOREIGN KEY (`id_wilayah`) REFERENCES `wilayah` (`id_wilayah`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `puskesmas` (`id_puskesmas`, `id_wilayah`, `nama_puskesmas`, `alamat`, `no_telepon`) VALUES
  ('1', '1', 'Puskesmas Cilodong', 'Jl. Cilodong Raya No. 12', '021-87654321');

DROP TABLE IF EXISTS `posyandu`;
CREATE TABLE `posyandu` (
  `id_posyandu` int(11) NOT NULL AUTO_INCREMENT,
  `id_puskesmas` int(11) NOT NULL,
  `id_wilayah` int(11) NOT NULL,
  `nama_posyandu` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  PRIMARY KEY (`id_posyandu`),
  KEY `fk_posyandu_puskesmas` (`id_puskesmas`),
  KEY `fk_posyandu_wilayah` (`id_wilayah`),
  CONSTRAINT `fk_posyandu_puskesmas` FOREIGN KEY (`id_puskesmas`) REFERENCES `puskesmas` (`id_puskesmas`),
  CONSTRAINT `fk_posyandu_wilayah` FOREIGN KEY (`id_wilayah`) REFERENCES `wilayah` (`id_wilayah`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `posyandu` (`id_posyandu`, `id_puskesmas`, `id_wilayah`, `nama_posyandu`, `alamat`) VALUES
  ('1', '1', '1', 'Posyandu Melati 01', 'RW 01 Sukamaju'),
  ('2', '1', '1', 'Posyandu Mawar 02', 'RW 02 Sukamaju');

DROP TABLE IF EXISTS `role`;
CREATE TABLE `role` (
  `id_role` int(11) NOT NULL AUTO_INCREMENT,
  `nama_role` varchar(50) NOT NULL,
  PRIMARY KEY (`id_role`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `role` (`id_role`, `nama_role`) VALUES
  ('1', 'Kader'),
  ('2', 'Bidan/Petugas Puskesmas'),
  ('3', 'Koordinator Desa'),
  ('4', 'Admin');

DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `id_role` int(11) NOT NULL,
  `id_posyandu` int(11) DEFAULT NULL,
  `id_puskesmas` int(11) DEFAULT NULL,
  `id_wilayah` int(11) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Aktif',
  `created_at` datetime DEFAULT current_timestamp(),
  `update_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_user_role` (`id_role`),
  KEY `fk_user_posyandu` (`id_posyandu`),
  KEY `fk_user_puskesmas` (`id_puskesmas`),
  KEY `fk_user_wilayah` (`id_wilayah`),
  CONSTRAINT `fk_user_posyandu` FOREIGN KEY (`id_posyandu`) REFERENCES `posyandu` (`id_posyandu`),
  CONSTRAINT `fk_user_puskesmas` FOREIGN KEY (`id_puskesmas`) REFERENCES `puskesmas` (`id_puskesmas`),
  CONSTRAINT `fk_user_role` FOREIGN KEY (`id_role`) REFERENCES `role` (`id_role`),
  CONSTRAINT `fk_user_wilayah` FOREIGN KEY (`id_wilayah`) REFERENCES `wilayah` (`id_wilayah`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `user` (`id_user`, `id_role`, `id_posyandu`, `id_puskesmas`, `id_wilayah`, `nama`, `username`, `password_hash`, `email`, `no_telepon`, `status`, `created_at`, `update_at`) VALUES
  ('1', '4', NULL, NULL, NULL, 'Administrator', 'admin', '$2y$10$0QquSoboPQvssiZvpU.LLewhllj3zAMQj4aZzMn9pfGrmM5YtDK2S', 'admin@sipantau.test', '081234567890', 'Aktif', '2026-10-06 09:33:23', '2026-10-06 09:33:23'),
  ('2', '1', '1', '1', '1', 'Siti Rahmawati', 'kader', '$2y$10$5jO031uF0O16fK2HJUzqged6FdPCMYKHLJ0xuQ1GbpXGnOguA9t/i', 'kader@sipantau.test', '081234567891', 'Aktif', '2026-10-06 09:33:23', '2026-10-06 09:33:23'),
  ('3', '2', NULL, '1', '1', 'Dr. Budi Santoso', 'petugas', '$2y$10$KvnbtnZOOUi4Zq3dGLG64.8RXmRIvPYdXBJkQgh7qOC/HK/EgiJp6', 'puskesmas@sipantau.test', '081234567892', 'Aktif', '2026-10-06 09:33:23', '2026-10-06 09:33:23'),
  ('4', '3', NULL, NULL, '1', 'Ahmad Fauzi', 'kordes', '$2y$10$FAQw3.c6qx2TDDy7V8M7VeuxRQmQR6d7/1.1/3AB.tHRyoFJt.weG', 'kordes@sipantau.test', '081234567893', 'Aktif', '2026-10-06 09:33:23', '2026-10-06 09:33:23');

DROP TABLE IF EXISTS `orangtua`;
CREATE TABLE `orangtua` (
  `id_orangtua` int(11) NOT NULL AUTO_INCREMENT,
  `nama_ayah` varchar(100) DEFAULT NULL,
  `nama_ibu` varchar(100) DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  PRIMARY KEY (`id_orangtua`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `anak`;
CREATE TABLE `anak` (
  `id_anak` int(11) NOT NULL AUTO_INCREMENT,
  `id_posyandu` int(11) NOT NULL,
  `id_orangtua` int(11) NOT NULL,
  `nik` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `tanggal_lahir` date NOT NULL,
  `jenis_kelamin` char(1) NOT NULL,
  `alamat` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_anak`),
  UNIQUE KEY `nik` (`nik`),
  KEY `fk_anak_posyandu` (`id_posyandu`),
  KEY `fk_anak_orangtua` (`id_orangtua`),
  CONSTRAINT `fk_anak_orangtua` FOREIGN KEY (`id_orangtua`) REFERENCES `orangtua` (`id_orangtua`),
  CONSTRAINT `fk_anak_posyandu` FOREIGN KEY (`id_posyandu`) REFERENCES `posyandu` (`id_posyandu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `jadwal_posyandu`;
CREATE TABLE `jadwal_posyandu` (
  `id_jadwal` int(11) NOT NULL AUTO_INCREMENT,
  `id_posyandu` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `waktu` time NOT NULL,
  `lokasi` varchar(200) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`id_jadwal`),
  KEY `fk_jadwal_posyandu` (`id_posyandu`),
  CONSTRAINT `fk_jadwal_posyandu` FOREIGN KEY (`id_posyandu`) REFERENCES `posyandu` (`id_posyandu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `kehadiran`;
CREATE TABLE `kehadiran` (
  `id_kehadiran` int(11) NOT NULL AUTO_INCREMENT,
  `id_jadwal` int(11) NOT NULL,
  `id_anak` int(11) NOT NULL,
  `status_kehadiran` varchar(30) NOT NULL,
  `waktu_kehadiran` datetime DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`id_kehadiran`),
  KEY `fk_kehadiran_jadwal` (`id_jadwal`),
  KEY `fk_kehadiran_anak` (`id_anak`),
  CONSTRAINT `fk_kehadiran_anak` FOREIGN KEY (`id_anak`) REFERENCES `anak` (`id_anak`),
  CONSTRAINT `fk_kehadiran_jadwal` FOREIGN KEY (`id_jadwal`) REFERENCES `jadwal_posyandu` (`id_jadwal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `pengukuran`;
CREATE TABLE `pengukuran` (
  `id_pengukuran` int(11) NOT NULL AUTO_INCREMENT,
  `id_anak` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `tanggal_pengukuran` date NOT NULL,
  `berat_badan` decimal(5,2) DEFAULT NULL,
  `tinggi_badan` decimal(5,2) DEFAULT NULL,
  `lingkar_kepala` decimal(5,2) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_pengukuran`),
  KEY `fk_pengukuran_anak` (`id_anak`),
  KEY `fk_pengukuran_user` (`id_user`),
  CONSTRAINT `fk_pengukuran_anak` FOREIGN KEY (`id_anak`) REFERENCES `anak` (`id_anak`),
  CONSTRAINT `fk_pengukuran_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `status_gizi`;
CREATE TABLE `status_gizi` (
  `id_status_gizi` int(11) NOT NULL AUTO_INCREMENT,
  `id_pengukuran` int(11) NOT NULL,
  `zscore_bb_u` decimal(5,2) DEFAULT NULL,
  `zscore_tb_u` decimal(5,2) DEFAULT NULL,
  `zscore_bb_tb` decimal(5,2) DEFAULT NULL,
  `zscore_imt_u` decimal(5,2) DEFAULT NULL,
  `status_bb_u` varchar(50) DEFAULT NULL,
  `status_tb_u` varchar(50) DEFAULT NULL,
  `status_bb_tb` varchar(50) DEFAULT NULL,
  `status_imt_u` varchar(50) DEFAULT NULL,
  `status_risiko` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_status_gizi`),
  UNIQUE KEY `id_pengukuran` (`id_pengukuran`),
  CONSTRAINT `fk_status_gizi_pengukuran` FOREIGN KEY (`id_pengukuran`) REFERENCES `pengukuran` (`id_pengukuran`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `pemantauan`;
CREATE TABLE `pemantauan` (
  `id_pemantauan` int(11) NOT NULL AUTO_INCREMENT,
  `id_anak` int(11) NOT NULL,
  `id_pengukuran` int(11) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `status_tindak_lanjut` varchar(50) DEFAULT NULL,
  `tanggal_tindak_lanjut` date DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`id_pemantauan`),
  KEY `fk_pemantauan_anak` (`id_anak`),
  KEY `fk_pemantauan_pengukuran` (`id_pengukuran`),
  CONSTRAINT `fk_pemantauan_anak` FOREIGN KEY (`id_anak`) REFERENCES `anak` (`id_anak`),
  CONSTRAINT `fk_pemantauan_pengukuran` FOREIGN KEY (`id_pengukuran`) REFERENCES `pengukuran` (`id_pengukuran`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `catatan_intervensi`;
CREATE TABLE `catatan_intervensi` (
  `id_intervensi` int(11) NOT NULL AUTO_INCREMENT,
  `id_pemantauan` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jenis_intervensi` varchar(100) NOT NULL,
  `catatan` text DEFAULT NULL,
  PRIMARY KEY (`id_intervensi`),
  KEY `fk_intervensi_pemantauan` (`id_pemantauan`),
  KEY `fk_intervensi_user` (`id_user`),
  CONSTRAINT `fk_intervensi_pemantauan` FOREIGN KEY (`id_pemantauan`) REFERENCES `pemantauan` (`id_pemantauan`),
  CONSTRAINT `fk_intervensi_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

