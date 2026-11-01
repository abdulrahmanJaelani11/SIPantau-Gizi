-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: db_sipantau_gizi
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `anak`
--

DROP TABLE IF EXISTS `anak`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `anak` (
  `id_anak` int NOT NULL AUTO_INCREMENT,
  `id_posyandu` int NOT NULL,
  `id_orangtua` int NOT NULL,
  `nik` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal_lahir` date NOT NULL,
  `jenis_kelamin` char(1) COLLATE utf8mb4_general_ci NOT NULL,
  `alamat` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_anak`),
  UNIQUE KEY `nik` (`nik`),
  KEY `fk_anak_posyandu` (`id_posyandu`),
  KEY `fk_anak_orangtua` (`id_orangtua`),
  CONSTRAINT `fk_anak_orangtua` FOREIGN KEY (`id_orangtua`) REFERENCES `orangtua` (`id_orangtua`),
  CONSTRAINT `fk_anak_posyandu` FOREIGN KEY (`id_posyandu`) REFERENCES `posyandu` (`id_posyandu`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `anak`
--

LOCK TABLES `anak` WRITE;
/*!40000 ALTER TABLE `anak` DISABLE KEYS */;
INSERT INTO `anak` VALUES (1,1,1,'3276010101250001','Muhammad Rayyan Al-Ghifari','2025-04-15','L','RW 01 Sukamaju','2026-10-13 01:08:36','2026-10-13 01:08:36'),(2,1,2,'3276010202250002','Aisyah Humaira Putri','2025-08-10','P','RW 01 Sukamaju','2026-10-13 01:08:36','2026-10-13 01:08:36'),(3,2,3,'3276010303240003','Arkan Al-Farizi','2024-11-20','L','RW 02 Sukamaju','2026-10-13 01:08:36','2026-10-13 01:08:36'),(4,2,4,'3276010404240004','Kirana Putri Maharani','2024-05-12','P','RW 02 Sukamaju','2026-10-13 01:08:36','2026-10-13 01:08:36'),(5,1,5,'3276010505250005','Kenzo Pratama','2025-10-01','L','RW 01 Sukamaju','2026-10-13 01:08:36','2026-10-13 01:08:36'),(6,2,6,'197211302005021001','Randika Angga','2026-11-01','L','Haruman\r\n20','2026-11-01 03:37:14','2026-11-01 03:37:14');
/*!40000 ALTER TABLE `anak` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `catatan_intervensi`
--

DROP TABLE IF EXISTS `catatan_intervensi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `catatan_intervensi` (
  `id_intervensi` int NOT NULL AUTO_INCREMENT,
  `id_pemantauan` int NOT NULL,
  `id_user` int NOT NULL,
  `tanggal` date NOT NULL,
  `jenis_intervensi` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `catatan` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id_intervensi`),
  KEY `fk_intervensi_pemantauan` (`id_pemantauan`),
  KEY `fk_intervensi_user` (`id_user`),
  CONSTRAINT `fk_intervensi_pemantauan` FOREIGN KEY (`id_pemantauan`) REFERENCES `pemantauan` (`id_pemantauan`),
  CONSTRAINT `fk_intervensi_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `catatan_intervensi`
--

LOCK TABLES `catatan_intervensi` WRITE;
/*!40000 ALTER TABLE `catatan_intervensi` DISABLE KEYS */;
/*!40000 ALTER TABLE `catatan_intervensi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jadwal_posyandu`
--

DROP TABLE IF EXISTS `jadwal_posyandu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jadwal_posyandu` (
  `id_jadwal` int NOT NULL AUTO_INCREMENT,
  `id_posyandu` int NOT NULL,
  `tanggal` date NOT NULL,
  `waktu` time NOT NULL,
  `lokasi` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id_jadwal`),
  KEY `fk_jadwal_posyandu` (`id_posyandu`),
  CONSTRAINT `fk_jadwal_posyandu` FOREIGN KEY (`id_posyandu`) REFERENCES `posyandu` (`id_posyandu`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jadwal_posyandu`
--

LOCK TABLES `jadwal_posyandu` WRITE;
/*!40000 ALTER TABLE `jadwal_posyandu` DISABLE KEYS */;
INSERT INTO `jadwal_posyandu` VALUES (1,1,'2026-09-15','08:30:00','Balai Warga RW 01 Sukamaju','Penimbangan Rutin & Pemberian Vitamin A Dosis Tinggi'),(2,1,'2026-10-10','08:00:00','Pos Posyandu Melati 01, RT 03/RW 01','Penimbangan Bulanan & Edukasi MP-ASI Kaya Protein Hewani'),(3,2,'2026-10-25','09:00:00','Balai Pertemuan RW 02 Sukamaju','Pemeriksaan Tumbuh Kembang, Imunisasi Rutin & Konseling Bidan Desa');
/*!40000 ALTER TABLE `jadwal_posyandu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kehadiran`
--

DROP TABLE IF EXISTS `kehadiran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kehadiran` (
  `id_kehadiran` int NOT NULL AUTO_INCREMENT,
  `id_jadwal` int NOT NULL,
  `id_anak` int NOT NULL,
  `status_kehadiran` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `waktu_kehadiran` datetime DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id_kehadiran`),
  KEY `fk_kehadiran_jadwal` (`id_jadwal`),
  KEY `fk_kehadiran_anak` (`id_anak`),
  CONSTRAINT `fk_kehadiran_anak` FOREIGN KEY (`id_anak`) REFERENCES `anak` (`id_anak`),
  CONSTRAINT `fk_kehadiran_jadwal` FOREIGN KEY (`id_jadwal`) REFERENCES `jadwal_posyandu` (`id_jadwal`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kehadiran`
--

LOCK TABLES `kehadiran` WRITE;
/*!40000 ALTER TABLE `kehadiran` DISABLE KEYS */;
INSERT INTO `kehadiran` VALUES (1,1,1,'Hadir','2026-09-15 08:45:00','Ibu membawa buku KIA lengkap'),(2,1,2,'Hadir','2026-09-15 03:01:06','Hadir tepat waktu dan aktif'),(3,1,3,'Tidak Hadir',NULL,'Sedang demam di rumah, butuh kunjungan rumah'),(4,2,1,'Hadir','2026-10-10 08:30:00','Penimbangan selesai, BB naik'),(5,2,2,'Hadir','2026-10-10 08:50:00','Tinggi badan bertambah'),(6,2,3,'Hadir','2026-10-10 09:15:00','Sudah sehat kembali pasca demam'),(7,3,4,'Belum Hadir',NULL,'Jadwal sesi mendatang'),(8,3,5,'Belum Hadir',NULL,'Jadwal sesi mendatang');
/*!40000 ALTER TABLE `kehadiran` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu`
--

DROP TABLE IF EXISTS `menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu` (
  `id_menu` int NOT NULL AUTO_INCREMENT,
  `nama_menu` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `icon` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kategori` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Utama',
  `parent_id` int DEFAULT NULL,
  `urutan` int DEFAULT '0',
  PRIMARY KEY (`id_menu`),
  UNIQUE KEY `slug` (`slug`),
  KEY `fk_menu_parent` (`parent_id`),
  CONSTRAINT `fk_menu_parent` FOREIGN KEY (`parent_id`) REFERENCES `menu` (`id_menu`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu`
--

LOCK TABLES `menu` WRITE;
/*!40000 ALTER TABLE `menu` DISABLE KEYS */;
INSERT INTO `menu` VALUES (1,'Dashboard','dashboard','bi-speedometer2','modules/dashboard/index.php','Utama',NULL,1),(2,'Data Balita & Anak','data-anak','bi-person-hearts','modules/data-anak/index.php','Data & Laporan',NULL,2),(3,'Pengukuran Gizi','pengukuran','bi-rulers','modules/pengukuran/index.php','Data & Laporan',NULL,3),(10,'Daftar Pantau','daftar-pantau','bi-clipboard2-pulse','modules/daftar-pantau/index.php','Data & Laporan',NULL,4),(9,'Jadwal & Kunjungan','jadwal','bi-calendar-check','modules/jadwal/index.php','Data & Laporan',NULL,5),(4,'Laporan & Grafik','laporan-gizi','bi-bar-chart-line','modules/laporan/index.php','Data & Laporan',NULL,6),(5,'Data Posyandu','posyandu','bi-hospital','modules/posyandu/index.php','Master Data',NULL,5),(6,'Data Puskesmas','puskesmas','bi-building','modules/master/puskesmas/index.php','Master Data',NULL,6),(7,'Daftar Pengguna','pengguna','bi-people','modules/utilitas/pengguna/index.php','Utilitas',NULL,7),(8,'Role & Otorisasi','role','bi-shield-lock','modules/utilitas/role/index.php','Utilitas',NULL,8),(11,'Profil Saya','profil','bi-person-circle','modules/profil/index.php','Utilitas',NULL,9);
/*!40000 ALTER TABLE `menu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orangtua`
--

DROP TABLE IF EXISTS `orangtua`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orangtua` (
  `id_orangtua` int NOT NULL AUTO_INCREMENT,
  `nama_ayah` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nama_ibu` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `no_telepon` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alamat` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id_orangtua`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orangtua`
--

LOCK TABLES `orangtua` WRITE;
/*!40000 ALTER TABLE `orangtua` DISABLE KEYS */;
INSERT INTO `orangtua` VALUES (1,'Hendra Wijaya','Dewi Lestari','081234567801','RW 01 Sukamaju'),(2,'Bambang Suryono','Nur Aini','081234567802','RW 01 Sukamaju'),(3,'Rizky Maulana','Fitri Handayani','081234567803','RW 02 Sukamaju'),(4,'Dian Kurniawan','Rina Marlina','081234567804','RW 02 Sukamaju'),(5,'Eko Prasetyo','Maya Safitri','081234567805','RW 01 Sukamaju'),(6,'Tes','TEs','083874809704','tes');
/*!40000 ALTER TABLE `orangtua` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pemantauan`
--

DROP TABLE IF EXISTS `pemantauan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pemantauan` (
  `id_pemantauan` int NOT NULL AUTO_INCREMENT,
  `id_anak` int NOT NULL,
  `id_pengukuran` int NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `status_tindak_lanjut` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tanggal_tindak_lanjut` date DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id_pemantauan`),
  KEY `fk_pemantauan_anak` (`id_anak`),
  KEY `fk_pemantauan_pengukuran` (`id_pengukuran`),
  CONSTRAINT `fk_pemantauan_anak` FOREIGN KEY (`id_anak`) REFERENCES `anak` (`id_anak`),
  CONSTRAINT `fk_pemantauan_pengukuran` FOREIGN KEY (`id_pengukuran`) REFERENCES `pengukuran` (`id_pengukuran`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pemantauan`
--

LOCK TABLES `pemantauan` WRITE;
/*!40000 ALTER TABLE `pemantauan` DISABLE KEYS */;
/*!40000 ALTER TABLE `pemantauan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengukuran`
--

DROP TABLE IF EXISTS `pengukuran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengukuran` (
  `id_pengukuran` int NOT NULL AUTO_INCREMENT,
  `id_anak` int NOT NULL,
  `id_user` int NOT NULL,
  `tanggal_pengukuran` date NOT NULL,
  `berat_badan` decimal(5,2) DEFAULT NULL,
  `tinggi_badan` decimal(5,2) DEFAULT NULL,
  `lingkar_kepala` decimal(5,2) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pengukuran`),
  KEY `fk_pengukuran_anak` (`id_anak`),
  KEY `fk_pengukuran_user` (`id_user`),
  CONSTRAINT `fk_pengukuran_anak` FOREIGN KEY (`id_anak`) REFERENCES `anak` (`id_anak`),
  CONSTRAINT `fk_pengukuran_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengukuran`
--

LOCK TABLES `pengukuran` WRITE;
/*!40000 ALTER TABLE `pengukuran` DISABLE KEYS */;
INSERT INTO `pengukuran` VALUES (1,1,1,'2026-08-15',10.50,80.50,46.50,'2026-10-13 01:32:48','2026-10-13 01:32:48'),(2,1,1,'2026-09-15',10.90,81.80,46.80,'2026-10-13 01:32:48','2026-10-13 01:32:48'),(3,1,1,'2026-10-10',11.20,82.80,47.00,'2026-10-13 01:32:48','2026-10-13 01:32:48'),(4,2,1,'2026-08-20',10.80,83.00,46.00,'2026-10-13 01:32:48','2026-10-13 01:32:48'),(5,2,1,'2026-09-20',11.20,84.20,46.20,'2026-10-13 01:32:48','2026-10-13 01:32:48'),(7,3,1,'2026-08-10',10.20,80.00,45.50,'2026-10-13 01:32:48','2026-10-13 01:32:48'),(8,3,1,'2026-09-10',10.40,80.50,45.60,'2026-10-13 01:32:48','2026-10-13 01:32:48'),(9,3,1,'2026-10-10',10.50,82.00,45.80,'2026-10-13 01:32:48','2026-10-13 02:11:35'),(10,4,1,'2026-09-05',9.50,76.00,45.00,'2026-10-13 01:32:48','2026-10-13 01:32:48'),(11,4,1,'2026-10-05',9.90,77.20,45.20,'2026-10-13 01:32:48','2026-10-13 01:32:48'),(12,5,1,'2026-09-12',11.50,87.00,47.00,'2026-10-13 01:32:48','2026-10-13 01:32:48'),(13,5,1,'2026-10-12',10.60,81.20,47.10,'2026-10-13 01:32:48','2026-10-13 01:47:27'),(15,2,1,'2026-10-13',10.00,81.00,45.00,'2026-10-13 02:13:49','2026-10-13 02:13:49');
/*!40000 ALTER TABLE `pengukuran` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `posyandu`
--

DROP TABLE IF EXISTS `posyandu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `posyandu` (
  `id_posyandu` int NOT NULL AUTO_INCREMENT,
  `id_puskesmas` int NOT NULL,
  `id_wilayah` int NOT NULL,
  `nama_posyandu` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `dusun_rw` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alamat` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id_posyandu`),
  KEY `fk_posyandu_puskesmas` (`id_puskesmas`),
  KEY `fk_posyandu_wilayah` (`id_wilayah`),
  CONSTRAINT `fk_posyandu_puskesmas` FOREIGN KEY (`id_puskesmas`) REFERENCES `puskesmas` (`id_puskesmas`),
  CONSTRAINT `fk_posyandu_wilayah` FOREIGN KEY (`id_wilayah`) REFERENCES `wilayah` (`id_wilayah`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `posyandu`
--

LOCK TABLES `posyandu` WRITE;
/*!40000 ALTER TABLE `posyandu` DISABLE KEYS */;
INSERT INTO `posyandu` VALUES (1,1,1,'Posyandu Melati 01',NULL,'RW 01 Sukamaju'),(2,1,1,'Posyandu Mawar 02',NULL,'RW 02 Sukamaju');
/*!40000 ALTER TABLE `posyandu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `puskesmas`
--

DROP TABLE IF EXISTS `puskesmas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `puskesmas` (
  `id_puskesmas` int NOT NULL AUTO_INCREMENT,
  `id_wilayah` int NOT NULL,
  `nama_puskesmas` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `alamat` text COLLATE utf8mb4_general_ci NOT NULL,
  `no_telepon` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id_puskesmas`),
  KEY `fk_puskesmas_wilayah` (`id_wilayah`),
  CONSTRAINT `fk_puskesmas_wilayah` FOREIGN KEY (`id_wilayah`) REFERENCES `wilayah` (`id_wilayah`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `puskesmas`
--

LOCK TABLES `puskesmas` WRITE;
/*!40000 ALTER TABLE `puskesmas` DISABLE KEYS */;
INSERT INTO `puskesmas` VALUES (1,1,'Puskesmas Cilodong','Jl. Cilodong Raya No. 12','021-87654321');
/*!40000 ALTER TABLE `puskesmas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role`
--

DROP TABLE IF EXISTS `role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role` (
  `id_role` int NOT NULL AUTO_INCREMENT,
  `nama_role` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `deskripsi` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_role`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role`
--

LOCK TABLES `role` WRITE;
/*!40000 ALTER TABLE `role` DISABLE KEYS */;
INSERT INTO `role` VALUES (1,'Kader','Kader Posyandu - Input data pengukuran dan pemantauan balita','2026-10-12 12:30:04','2026-10-12 12:32:15'),(2,'Bidan/Petugas Puskesmas','Petugas Puskesmas - Validasi data dan intervensi gizi','2026-10-12 12:30:04','2026-10-12 12:32:15'),(3,'Koordinator Desa','Koordinator Desa - Monitoring rekapitulasi gizi tingkat wilayah','2026-10-12 12:30:04','2026-10-12 12:32:15'),(4,'Admin','Administrator sistem - Kendali penuh data, pengguna, dan otorisasi','2026-10-12 12:30:04','2026-10-12 12:32:15');
/*!40000 ALTER TABLE `role` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_menu`
--

DROP TABLE IF EXISTS `role_menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_menu` (
  `id_role_menu` int NOT NULL AUTO_INCREMENT,
  `id_role` int NOT NULL,
  `id_menu` int NOT NULL,
  PRIMARY KEY (`id_role_menu`),
  UNIQUE KEY `uk_role_menu` (`id_role`,`id_menu`),
  KEY `fk_role_menu_role` (`id_role`),
  KEY `fk_role_menu_menu` (`id_menu`),
  CONSTRAINT `fk_role_menu_menu` FOREIGN KEY (`id_menu`) REFERENCES `menu` (`id_menu`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_menu_role` FOREIGN KEY (`id_role`) REFERENCES `role` (`id_role`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_menu`
--

LOCK TABLES `role_menu` WRITE;
/*!40000 ALTER TABLE `role_menu` DISABLE KEYS */;
INSERT INTO `role_menu` VALUES (9,1,1),(10,1,2),(11,1,3),(12,1,5),(24,1,9),(27,1,10),(31,1,11),(13,2,1),(14,2,2),(15,2,3),(16,2,4),(17,2,5),(18,2,6),(25,2,9),(28,2,10),(32,2,11),(19,3,1),(20,3,4),(21,3,5),(22,3,6),(26,3,9),(29,3,10),(33,3,11),(1,4,1),(2,4,2),(3,4,3),(4,4,4),(5,4,5),(6,4,6),(7,4,7),(8,4,8),(23,4,9),(30,4,10),(34,4,11);
/*!40000 ALTER TABLE `role_menu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `status_gizi`
--

DROP TABLE IF EXISTS `status_gizi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `status_gizi` (
  `id_status_gizi` int NOT NULL AUTO_INCREMENT,
  `id_pengukuran` int NOT NULL,
  `zscore_bb_u` decimal(5,2) DEFAULT NULL,
  `zscore_tb_u` decimal(5,2) DEFAULT NULL,
  `zscore_bb_tb` decimal(5,2) DEFAULT NULL,
  `zscore_imt_u` decimal(5,2) DEFAULT NULL,
  `status_bb_u` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_tb_u` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_bb_tb` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_imt_u` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_risiko` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_status_gizi`),
  UNIQUE KEY `id_pengukuran` (`id_pengukuran`),
  CONSTRAINT `fk_status_gizi_pengukuran` FOREIGN KEY (`id_pengukuran`) REFERENCES `pengukuran` (`id_pengukuran`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `status_gizi`
--

LOCK TABLES `status_gizi` WRITE;
/*!40000 ALTER TABLE `status_gizi` DISABLE KEYS */;
INSERT INTO `status_gizi` VALUES (1,1,0.03,0.16,-0.08,-0.29,'Gizi Baik','Normal','Gizi Baik','Gizi Baik','Normal','2026-10-13 01:32:48'),(2,2,0.19,0.24,0.03,-0.15,'Gizi Baik','Normal','Gizi Baik','Gizi Baik','Normal','2026-10-13 01:32:48'),(3,3,0.30,0.29,0.11,-0.06,'Gizi Baik','Normal','Gizi Baik','Gizi Baik','Normal','2026-10-13 01:32:48'),(4,4,1.86,3.75,-0.76,-0.59,'Risiko Gizi Lebih','Tinggi','Gizi Baik','Gizi Baik','Berisiko','2026-10-13 01:32:48'),(5,5,2.01,3.73,-0.61,-0.44,'Risiko Gizi Lebih','Tinggi','Gizi Baik','Gizi Baik','Berisiko','2026-10-13 01:32:48'),(7,7,-1.08,-1.84,0.12,-0.25,'Gizi Baik','Normal','Gizi Baik','Gizi Baik','Normal','2026-10-13 01:32:48'),(8,8,-1.07,-1.98,0.22,-0.09,'Gizi Baik','Normal','Gizi Baik','Gizi Baik','Normal','2026-10-13 01:32:48'),(9,9,-1.15,-1.73,-0.01,-0.42,'Gizi Baik','Normal','Gizi Baik','Gizi Baik','Normal','2026-10-13 01:32:48'),(10,10,-2.18,-4.64,0.79,0.80,'Gizi Kurang','Sangat Pendek','Gizi Baik','Gizi Baik','Butuh Rujukan Segera','2026-10-13 01:32:48'),(11,11,-2.00,-4.42,0.87,0.97,'Gizi Baik','Sangat Pendek','Gizi Baik','Gizi Baik','Butuh Rujukan Segera','2026-10-13 01:32:48'),(12,12,2.00,5.28,-1.79,-1.36,'Risiko Gizi Lebih','Tinggi','Gizi Baik','Gizi Baik','Berisiko','2026-10-13 01:32:48'),(13,13,0.87,2.19,-0.60,-0.58,'Gizi Baik','Normal','Gizi Baik','Gizi Baik','Normal','2026-10-13 01:32:48'),(15,15,0.63,1.96,-0.70,-0.88,'Gizi Baik','Normal','Gizi Baik','Gizi Baik','Normal','2026-10-13 02:13:49');
/*!40000 ALTER TABLE `status_gizi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id_user` int NOT NULL AUTO_INCREMENT,
  `id_role` int NOT NULL,
  `id_posyandu` int DEFAULT NULL,
  `id_puskesmas` int DEFAULT NULL,
  `id_wilayah` int DEFAULT NULL,
  `nama` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `no_telepon` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Aktif',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `update_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (1,4,NULL,NULL,NULL,'Administrator','admin','$2y$10$0QquSoboPQvssiZvpU.LLewhllj3zAMQj4aZzMn9pfGrmM5YtDK2S','admin@sipantau.test','081234567890','Aktif','2026-10-06 09:33:23','2026-10-06 09:33:23'),(2,1,1,1,1,'Siti Rahmawati','kader','$2y$10$SlKob9sSSb.jS3/pVmS7N.Z3zXRhAOiwYxz5A82wuOk4MEWEgwZeG','kader@sipantau.test','081234567891','Aktif','2026-10-06 09:33:23','2026-10-13 02:26:06'),(3,2,NULL,1,1,'Dr. Budi Santoso','petugas','$2y$10$Oyr3pTnTscOfAXeGJX.niuW6B/5lskLDN8pLNQIzHz7eatku/blUq','puskesmas@sipantau.test','081234567892','Aktif','2026-10-06 09:33:23','2026-10-12 13:08:53'),(4,3,NULL,NULL,1,'Ahmad Fauzi','kordes','$2y$10$frGGSfuzLzg5.pLH89qFxuROu0/0qEY3KTselAlG9M60VQPaXApwC','kordes@sipantau.test','081234567893','Aktif','2026-10-06 09:33:23','2026-10-14 06:53:42');
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wilayah`
--

DROP TABLE IF EXISTS `wilayah`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wilayah` (
  `id_wilayah` int NOT NULL AUTO_INCREMENT,
  `nama_wilayah` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `kecamatan` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `kabupaten` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id_wilayah`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wilayah`
--

LOCK TABLES `wilayah` WRITE;
/*!40000 ALTER TABLE `wilayah` DISABLE KEYS */;
INSERT INTO `wilayah` VALUES (1,'Kelurahan Sukamaju','Cilodong','Kota Depok'),(2,'Kelurahan Kalimulya','Cilodong','Kota Depok');
/*!40000 ALTER TABLE `wilayah` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-01  4:51:58
