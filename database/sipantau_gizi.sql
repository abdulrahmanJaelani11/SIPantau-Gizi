-- ============================================================
-- SiPantau Gizi — Skema Database
-- ============================================================
-- Capstone Project STSI4440
-- Stack: PHP Native + MySQL + Bootstrap 5
--
-- Cara pakai:
--   Opsi A: Import file ini via phpMyAdmin
--   Opsi B: Jalankan script database/install.php dari browser
--
-- Nama database: sipantau_gizi (sesuaikan di config/database.php jika berbeda)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `sipantau_gizi`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `sipantau_gizi`;

-- ============================================================
-- 1. Tabel ROLES — Peran pengguna
-- ============================================================
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_role` VARCHAR(50) NOT NULL UNIQUE,
  `deskripsi` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. Tabel MENUS — Daftar menu navigasi
-- ============================================================
-- slug  : identifier unik, dipakai untuk cek akses & highlight sidebar
-- icon  : class Bootstrap Icon (misal 'bi-people')
-- parent_id : NULL = menu utama, berisi ID parent = sub-menu
-- urutan : untuk mengurutkan tampilan di sidebar
CREATE TABLE IF NOT EXISTS `menus` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_menu` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `icon` VARCHAR(50) NULL,
  `parent_id` INT NULL,
  `urutan` INT DEFAULT 0,
  FOREIGN KEY (`parent_id`) REFERENCES `menus`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 3. Tabel ROLE_MENU — Hak akses role terhadap menu
-- ============================================================
-- Jika role_id + menu_id ada di tabel ini, maka role tersebut BOLEH akses menu tsb.
CREATE TABLE IF NOT EXISTS `role_menu` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT NOT NULL,
  `menu_id` INT NOT NULL,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`menu_id`) REFERENCES `menus`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_role_menu` (`role_id`, `menu_id`)
) ENGINE=InnoDB;

-- ============================================================
-- 4. Tabel USERS — Akun pengguna
-- ============================================================
-- posyandu_id  : TODO: koordinasi tim — FK ke tabel posyandu milik modul lain
-- puskesmas_id : TODO: koordinasi tim — FK ke tabel puskesmas milik modul lain
-- Kedua kolom sengaja nullable dan BELUM diberi FK constraint karena tabel
-- posyandu/puskesmas kemungkinan dibuat anggota tim lain.
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL COMMENT 'Hasil password_hash()',
  `role_id` INT NOT NULL,
  `posyandu_id` INT NULL COMMENT 'TODO: koordinasi tim — FK ke tabel posyandu',
  `puskesmas_id` INT NULL COMMENT 'TODO: koordinasi tim — FK ke tabel puskesmas',
  `status` ENUM('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;


-- ============================================================
-- DATA AWAL (SEED)
-- ============================================================

-- Roles
INSERT INTO `roles` (`id`, `nama_role`, `deskripsi`) VALUES
  (1, 'Admin',              'Administrator sistem — akses penuh ke semua menu'),
  (2, 'Kader',              'Kader Posyandu — input data pengukuran balita'),
  (3, 'Petugas Puskesmas',  'Petugas kesehatan — validasi dan laporan'),
  (4, 'Koordinator Desa',   'Koordinator tingkat desa — monitoring dan rekapitulasi');

-- Menu navigasi (sesuai sidebar.php)
-- ID 1-6: menu utama (top-level)
-- ID 7: parent menu "Utilitas"
-- ID 8-9: sub-menu di bawah Utilitas
INSERT INTO `menus` (`id`, `nama_menu`, `slug`, `icon`, `parent_id`, `urutan`) VALUES
  (1, 'Dashboard',          'dashboard',    'bi-speedometer2',   NULL, 1),
  (2, 'Data Balita & Anak', 'data-anak',    'bi-person-hearts',  NULL, 2),
  (3, 'Pengukuran Gizi',    'pengukuran',   'bi-rulers',         NULL, 3),
  (4, 'Laporan & Grafik',   'laporan-gizi', 'bi-bar-chart-line', NULL, 4),
  (5, 'Data Posyandu',      'posyandu',     'bi-hospital',       NULL, 5),
  (6, 'Data Puskesmas',     'puskesmas',    'bi-building',       NULL, 6),
  (7, 'Utilitas',           'utilitas',     'bi-gear',           NULL, 10),
  (8, 'Daftar Pengguna',    'pengguna',     'bi-people',         7,   11),
  (9, 'Role & Otorisasi',   'role',         'bi-shield-lock',    7,   12);

-- Hak akses: Admin dapat akses SEMUA menu
INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
  (1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8), (1, 9);

-- Hak akses: Kader — Dashboard, Data Balita, Pengukuran, Posyandu
INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
  (2, 1), (2, 2), (2, 3), (2, 5);

-- Hak akses: Petugas Puskesmas — Dashboard, Data Balita, Pengukuran, Laporan, Posyandu, Puskesmas
INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
  (3, 1), (3, 2), (3, 3), (3, 4), (3, 5), (3, 6);

-- Hak akses: Koordinator Desa — Dashboard, Laporan, Posyandu, Puskesmas
INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
  (4, 1), (4, 4), (4, 5), (4, 6);

-- User admin default
-- Email: admin@sipantau.test | Password: admin123
-- CATATAN: Hash di bawah dihasilkan oleh password_hash('admin123', PASSWORD_DEFAULT)
-- Jika Anda ingin password lain, gunakan script database/install.php
INSERT INTO `users` (`nama`, `email`, `password`, `role_id`, `status`) VALUES
  ('Administrator', 'admin@sipantau.test', '$2y$10$MDgQoB2wfWwcLHQBwI9yWus0vmt20if74B7nWfWXkmEAqOZlgoOMm', 1, 'aktif');
