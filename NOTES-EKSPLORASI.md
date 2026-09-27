# Catatan Hasil Eksplorasi Template adminHMD untuk SiPantau Gizi
**Mata Kuliah:** Capstone Project STSI4440  
**Stack:** PHP Native + MySQL + Bootstrap 5  
**Fokus Tanggung Jawab:** Arsitektur Awal + Modul Utilitas (Pengguna, Role & Otorisasi)

---

## 1. Daftar Halaman HTML dan Fungsinya

Template yang ada saat ini (`/html/`) memiliki 19 file HTML statis:

| No | File HTML | Kategori | Fungsi & Deskripsi |
|---|---|---|---|
| 1 | `index.html` | Dashboard | Halaman beranda utama dengan kartu metrik KPI, grafik pendapatan mini, dan tabel aktivitas terbaru. |
| 2 | `users.html` | User Management | Daftar tabel pengguna lengkap dengan filter pencarian instan (*client-side search*), status badge, dan metrik akun. |
| 3 | `add-user.html` | User Management | Form penambahan pengguna baru dengan field nama, email, no. telp, pilihan role, tim, serta validasi form Bootstrap. |
| 4 | `user-details.html` | User Management | Halaman profil detail dari seorang pengguna spesifik beserta riwayat aktivitasnya. |
| 5 | `charts.html` | Visualisasi | Komponen grafik batang (*revenue trend*) dan grafik donat (*channel mix*) berbasis CSS murni. |
| 6 | `tables.html` | UI Component | Showcase variasi tabel Bootstrap (striped, bordered, responsive, search filter). |
| 7 | `forms.html` | UI Component | Showcase berbagai elemen input (text, select, switch, checkbox, upload file, ukuran input). |
| 8 | `components.html` | UI Component | Showcase komponen UI kit (button, card, accordion, tooltip, progress bar, badge). |
| 9 | `alerts.html` | UI Component | Variasi notifikasi alert standar dan dismissible. |
| 10 | `modals.html` | UI Component | Variasi dialog modal (standard, static backdrop, scrollable, konfirmasi). |
| 11 | `blank.html` | Skeleton | Halaman kanvas kosong standar yang menjadi acuan struktur layout utama admin shell. |
| 12 | `create-agent.html`| Modul Form | Template form pendaftaran agen/petugas dengan parameter skill & deskripsi. |
| 13 | `profile.html` | Pengguna | Halaman pengelolaan profil akun personal (ganti avatar, update kontak, log aktivitas). |
| 14 | `settings.html` | Pengaturan | Pengaturan akun dan preferensi aplikasi (notifikasi, privasi, tema). |
| 15 | `login.html` | Autentikasi | Halaman sign-in mandiri dengan form email & password (*layout auth* khusus). |
| 16 | `register.html` | Autentikasi | Halaman pendaftaran akun baru (*layout auth* khusus). |
| 17 | `forgot-password.html` | Autentikasi | Halaman pemulihan kata sandi / reset password (*layout auth* khusus). |
| 18 | `404.html` | Error Page | Halaman penanganan 404 (Halaman Tidak Ditemukan) dengan ilustrasi SVG. |
| 19 | `500.html` | Error Page | Halaman penanganan 500 (Internal Server Error) dengan ilustrasi SVG maintenance. |

---

## 2. Rencana Ekstraksi Layout Bersama (Include)

Semua halaman bertipe admin dashboard (seperti `index.html`, `users.html`, `blank.html`) berbagi struktur DOM yang seragam. Struktur ini akan dipecah menjadi 3 komponen utama di dalam folder `includes/`:

### A. `includes/header.php`
* **Mencakup:**
  * Deklarasi `<!DOCTYPE html>`, tag `<head>`, meta tags (charset, viewport, description).
  * Tag `<title>` dinamis (misal: `<?= $page_title ?? 'Dashboard' ?> | SiPantau Gizi`).
  * Link CSS utama (`bootstrap.min.css`, `bootstrap-icons.css`, `style.css`).
  * Buka tag `<body>` dan pembungkus layout `.admin-shell`.
  * Backdrop sidebar mobile: `<div class="sidebar-backdrop" data-sidebar-close></div>`.
  * Pemanggilan `include __DIR__ . '/sidebar.php';`.
  * Buka pembungkus konten `.admin-main`.
  * Topbar / Navbar (`<nav class="navbar admin-navbar ...">`): tombol toggle sidebar mobile, form search global, tombol dark/light mode toggle, dropdown notifikasi, dan dropdown profil pengguna (nama & role dinamis dari session, link ke profil & logout).
  * Buka kontainer utama: `<main class="dashboard-content"><div class="container-fluid px-3 px-lg-4 py-4">`.

### B. `includes/sidebar.php`
* **Mencakup:**
  * Tag `<aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">`.
  * Header brand/logo SiPantau Gizi (`brand-icon`, `brand-title`, `brand-subtitle`).
  * Navigasi sidebar (`<nav class="sidebar-nav">`): **dirender dinamis** berdasarkan query tabel `menus` dan `role_menu` sesuai role pengguna yang sedang login.
  * Menandai link aktif (`class="nav-link active"` dan `aria-current="page"`) berdasarkan variabel `$current_slug`.
  * Bagian profil pengguna di sidebar (`.sidebar-user`): nama dan role dinamis dari sesi.
  * Status sistem di sidebar (`.sidebar-footer`).
  * Penutup `</aside>`.

### C. `includes/footer.php`
* **Mencakup:**
  * Penutup tag konten: `</div></main>`.
  * Footer aplikasi (`<footer class="admin-footer">`): copyright SiPantau Gizi & info tahun dinamis.
  * Penutup pembungkus layout: `</div></div>` (menutup `.admin-main` dan `.admin-shell`).
  * Script JavaScript bawaan: `bootstrap.bundle.min.js` dan `main.js`.
  * Slot opsional untuk script khusus halaman (misal `$extra_scripts`).
  * Penutup `</body></html>`.

---

## 3. Struktur Folder Asset

Lokasi aset saat ini berada di `/assets/` dengan struktur:
* `assets/css/`
  * `bootstrap.min.css` (Bootstrap v5.3)
  * `style.css` (Design system template: warna variabel `--admin-primary`, layout shell, sidebar mini, css charts)
* `assets/js/`
  * `bootstrap.bundle.min.js` (Komponen JS Bootstrap: dropdown, modal, collapse, tooltip)
  * `main.js` (Logika UI: dark/light theme toggle via localStorage, sidebar expand/collapse persistensi desktop & mobile, auto form validation, client-side table search `data-table-search`)
* `assets/vendors/`
  * `bootstrap-icons/` (Font icons `bootstrap-icons.css` serta file webfont `.woff` dan `.woff2`)
* `assets/images/`
  * `avatar/` (Foto profil pengguna contoh)
  * `brand/` (Logo brand SVG)
  * `svg/` (Ilustrasi 404 & maintenance)
  * `favicon/`, `ecommerce/`, `png/`

> **Catatan Teknis Path Aset:**  
> Karena file PHP akan tersebar di beberapa tingkat subdirektori (misal `modules/utilitas/pengguna/index.php` berjarak 3 tingkat dari root), kita akan mendefinisikan konstanta `BASE_URL` di `config/app.php` dan fungsi helper `asset($path)` agar pemanggilan file CSS, JS, dan gambar tidak pernah mengalami *broken path*.

---

## 4. Pemetaan Menu Navigasi ke Tabel `menus`

Berdasarkan analisa menu di template serta kebutuhan fungsional aplikasi **SiPantau Gizi**, berikut rancangan awal data menu untuk tabel `menus` yang akan digunakan pada sistem otorisasi:

| ID | Nama Menu | Slug | Icon | Parent ID | Urutan | Keterangan & Rencana Modul |
|---|---|---|---|---|---|---|
| 1 | Dashboard | `dashboard` | `bi-speedometer2` | NULL | 1 | Modul Utama / Ringkasan Metrik Gizi |
| 2 | Data Balita & Anak | `data-anak` | `bi-person-hearts` | NULL | 2 | *(TODO: modul anggota tim)* |
| 3 | Pengukuran Gizi | `pengukuran` | `bi-rulers` | NULL | 3 | *(TODO: modul anggota tim)* |
| 4 | Laporan & Grafik Gizi | `laporan-gizi` | `bi-bar-chart-line` | NULL | 4 | *(TODO: modul anggota tim)* |
| 5 | Data Posyandu | `posyandu` | `bi-hospital` | NULL | 5 | *(TODO: modul anggota tim)* |
| 6 | Data Puskesmas | `puskesmas` | `bi-building` | NULL | 6 | *(TODO: modul anggota tim)* |
| 7 | Utilitas | `utilitas` | `bi-gear` | NULL | 10 | Header / Parent Modul Utilitas |
| 8 | Daftar Pengguna | `pengguna` | `bi-people` | 7 | 11 | **Tugas Kita:** `modules/utilitas/pengguna/` |
| 9 | Role & Otorisasi | `role` | `bi-shield-lock` | 7 | 12 | **Tugas Kita:** `modules/utilitas/role/` |

---

## 5. Rencana Langkah Selanjutnya (Fase 1)
1. Membuat struktur folder PHP native sesuai rencana: `config/`, `includes/`, `src/`, `modules/`.
2. Menyusun file konfigurasi `config/database.php` (koneksi PDO) dan `config/app.php` (konstanta `BASE_URL`, `APP_NAME`, session init).
3. Mengekstrak layout bersama ke `includes/header.php`, `includes/sidebar.php`, `includes/footer.php`, dan `includes/auth-guard.php`.
4. Membuat halaman autentikasi dasar: `modules/auth/login.php` dan `modules/auth/logout.php`.
5. Menyiapkan `index.php` di root untuk me-redirect ke dashboard atau halaman login.
