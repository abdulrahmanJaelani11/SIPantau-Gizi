USE `db_sipantau_gizi`;

-- 1. Tambah kolom deskripsi dan timestamp di tabel role
ALTER TABLE `role` ADD COLUMN `deskripsi` varchar(255) DEFAULT NULL AFTER `nama_role`;
ALTER TABLE `role` ADD COLUMN `created_at` datetime DEFAULT current_timestamp();
ALTER TABLE `role` ADD COLUMN `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp();

UPDATE `role` SET `deskripsi` = 'Kader Posyandu — input data pengukuran dan pemantauan balita' WHERE `id_role` = 1 AND (`deskripsi` IS NULL OR `deskripsi` = '');
UPDATE `role` SET `deskripsi` = 'Petugas kesehatan Puskesmas — validasi data dan intervensi gizi' WHERE `id_role` = 2 AND (`deskripsi` IS NULL OR `deskripsi` = '');
UPDATE `role` SET `deskripsi` = 'Koordinator Desa — monitoring rekapitulasi gizi tingkat wilayah' WHERE `id_role` = 3 AND (`deskripsi` IS NULL OR `deskripsi` = '');
UPDATE `role` SET `deskripsi` = 'Administrator sistem — kendali penuh data, pengguna, dan otorisasi' WHERE `id_role` = 4 AND (`deskripsi` IS NULL OR `deskripsi` = '');

-- 2. Buat tabel menu jika belum ada
CREATE TABLE IF NOT EXISTS `menu` (
  `id_menu` int(11) NOT NULL AUTO_INCREMENT,
  `nama_menu` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL UNIQUE,
  `icon` varchar(50) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `kategori` varchar(50) DEFAULT 'Utama',
  `parent_id` int(11) DEFAULT NULL,
  `urutan` int(11) DEFAULT 0,
  PRIMARY KEY (`id_menu`),
  KEY `fk_menu_parent` (`parent_id`),
  CONSTRAINT `fk_menu_parent` FOREIGN KEY (`parent_id`) REFERENCES `menu` (`id_menu`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Isi data default tabel menu
INSERT INTO `menu` (`id_menu`, `nama_menu`, `slug`, `icon`, `url`, `kategori`, `urutan`) VALUES
  (1, 'Dashboard', 'dashboard', 'bi-speedometer2', 'modules/dashboard/index.php', 'Utama', 1),
  (2, 'Data Balita & Anak', 'data-anak', 'bi-person-hearts', '#', 'Data & Laporan', 2),
  (3, 'Pengukuran Gizi', 'pengukuran', 'bi-rulers', '#', 'Data & Laporan', 3),
  (4, 'Laporan & Grafik', 'laporan-gizi', 'bi-bar-chart-line', '#', 'Data & Laporan', 4),
  (5, 'Data Posyandu', 'posyandu', 'bi-hospital', '#', 'Master Data', 5),
  (6, 'Data Puskesmas', 'puskesmas', 'bi-building', '#', 'Master Data', 6),
  (7, 'Daftar Pengguna', 'pengguna', 'bi-people', 'modules/utilitas/pengguna/index.php', 'Utilitas', 7),
  (8, 'Role & Otorisasi', 'role', 'bi-shield-lock', 'modules/utilitas/role/index.php', 'Utilitas', 8)
ON DUPLICATE KEY UPDATE 
  nama_menu = VALUES(nama_menu),
  icon = VALUES(icon),
  url = VALUES(url),
  kategori = VALUES(kategori),
  urutan = VALUES(urutan);

-- 4. Buat tabel role_menu jika belum ada
CREATE TABLE IF NOT EXISTS `role_menu` (
  `id_role_menu` int(11) NOT NULL AUTO_INCREMENT,
  `id_role` int(11) NOT NULL,
  `id_menu` int(11) NOT NULL,
  PRIMARY KEY (`id_role_menu`),
  UNIQUE KEY `uk_role_menu` (`id_role`, `id_menu`),
  KEY `fk_role_menu_role` (`id_role`),
  KEY `fk_role_menu_menu` (`id_menu`),
  CONSTRAINT `fk_role_menu_role` FOREIGN KEY (`id_role`) REFERENCES `role` (`id_role`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_menu_menu` FOREIGN KEY (`id_menu`) REFERENCES `menu` (`id_menu`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Isi hak akses default
INSERT IGNORE INTO `role_menu` (`id_role`, `id_menu`) VALUES
  (4, 1), (4, 2), (4, 3), (4, 4), (4, 5), (4, 6), (4, 7), (4, 8),
  (1, 1), (1, 2), (1, 3), (1, 5),
  (2, 1), (2, 2), (2, 3), (2, 4), (2, 5), (2, 6),
  (3, 1), (3, 4), (3, 5), (3, 6);
