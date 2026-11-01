<?php
/**
 * Dashboard — SiPantau Gizi
 * ===========================
 * Halaman utama setelah login yang secara dinamis menyesuaikan tampilan metrik,
 * grafik, dan informasi berdasarkan peran (role) pengguna:
 * 
 * 1. Kader: Jadwal posyandu & jumlah anak yang sudah diukur pada bulan berjalan
 * 2. Bidan / Petugas Puskesmas: Ringkasan status gizi & tren bulanan seluruh posyandu di wilayah petugas
 * 3. Koordinator Desa: Ringkasan status gizi per posyandu/dusun di desa
 * 4. Admin: Ringkasan jumlah pengguna, posyandu, dan status sistem secara umum
 *    (Plus Role Switcher untuk memudahkan pratinjau seluruh peran secara langsung)
 */

require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';
require_once ROOT_PATH . '/src/Otorisasi.php';

$page_title   = 'Dashboard';
$current_slug = 'dashboard';

// ============================================================
// Identitas Pengguna & Otorisasi Peran
// ============================================================
$user_id           = (int)($_SESSION['user_id'] ?? 0);
$user_nama         = $_SESSION['user_nama'] ?? 'Pengguna';
$user_role_id      = (int)($_SESSION['role_id'] ?? 4);
$user_role_nama    = $_SESSION['user_role_nama'] ?? 'Admin';
$user_posyandu_id  = (int)($_SESSION['user_posyandu'] ?? 0);
$user_puskesmas_id = (int)($_SESSION['user_puskesmas'] ?? 0);
$user_wilayah_id   = (int)($_SESSION['user_wilayah'] ?? 0);

$is_admin = ($user_role_id === Otorisasi::ADMIN_ROLE_ID || strcasecmp($user_role_nama, 'Admin') === 0);

// ============================================================
// Penentuan Peran Aktif (Dengan Pratinjau Khusus Admin)
// ============================================================
$allowed_roles = ['admin', 'kader', 'petugas', 'kordes'];
$requested_role = strtolower(trim($_GET['role'] ?? $_GET['peran'] ?? ''));

if ($is_admin && in_array($requested_role, $allowed_roles, true)) {
    $active_role = $requested_role;
} else {
    if ($user_role_id === 1 || stripos($user_role_nama, 'kader') !== false) {
        $active_role = 'kader';
    } elseif ($user_role_id === 2 || stripos($user_role_nama, 'petugas') !== false || stripos($user_role_nama, 'bidan') !== false) {
        $active_role = 'petugas';
    } elseif ($user_role_id === 3 || stripos($user_role_nama, 'desa') !== false || stripos($user_role_nama, 'koordinator') !== false) {
        $active_role = 'kordes';
    } else {
        $active_role = 'admin';
    }
}

// ============================================================
// Periode Bulan Berjalan
// ============================================================
$current_month = date('Y-m');
$available_months = [];

if ($pdo !== null) {
    try {
        $stmtM = $pdo->query("SELECT DISTINCT DATE_FORMAT(tanggal_pengukuran, '%Y-%m') as bln FROM pengukuran ORDER BY bln DESC");
        $available_months = $stmtM->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {}
}

if (!in_array($current_month, $available_months, true)) {
    array_unshift($available_months, $current_month);
}

// Pilihan bulan berjalan: default ke bulan terpilih dari GET, atau bulan ini jika ada pengukuran, atau bulan teraktif
$bulan_aktif = trim($_GET['bulan'] ?? '');
if ($bulan_aktif === '' || !preg_match('/^\d{4}-\d{2}$/', $bulan_aktif)) {
    $bulan_aktif = $current_month;
}

// ============================================================
// Helper Formatting
// ============================================================
if (!function_exists('formatTanggalIndo')) {
    function formatTanggalIndo(?string $date_str): string {
        if (empty($date_str)) return '-';
        $time = strtotime($date_str);
        if (!$time) return e($date_str);
        $bulanIndo = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        return date('j', $time) . ' ' . ($bulanIndo[(int)date('n', $time)] ?? '') . ' ' . date('Y', $time);
    }
}

if (!function_exists('formatBulanIndo')) {
    function formatBulanIndo(?string $ym_str): string {
        if (empty($ym_str)) return '-';
        $parts = explode('-', $ym_str);
        if (count($parts) !== 2) return e($ym_str);
        $bulanIndo = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        $m = (int)$parts[1];
        return ($bulanIndo[$m] ?? $parts[1]) . ' ' . $parts[0];
    }
}

if (!function_exists('hitungUsiaBulan')) {
    function hitungUsiaBulan(?string $tgl_lahir, ?string $tgl_hitung = null): int {
        if (empty($tgl_lahir)) return 0;
        try {
            $birth = new DateTime($tgl_lahir);
            $target = new DateTime($tgl_hitung ?: 'now');
            $diff = $birth->diff($target);
            return ($diff->y * 12) + $diff->m;
        } catch (Exception $e) {
            return 0;
        }
    }
}

// ============================================================
// DATA FETCHING SESUAI PERAN AKTIF
// ============================================================

// ------------------------------------------------------------
// 1. DATA KHUSUS KADER
// ------------------------------------------------------------
if ($active_role === 'kader' && $pdo !== null) {
    // Cari posyandu kader (jika belum ada di session atau jika admin preview, ambil posyandu pertama)
    $kader_posyandu_id = $user_posyandu_id > 0 ? $user_posyandu_id : 1;
    if (isset($_GET['id_posyandu']) && $is_admin) {
        $kader_posyandu_id = (int)$_GET['id_posyandu'];
    }

    $stmt = $pdo->prepare("
        SELECT p.*, w.nama_wilayah, w.kecamatan, w.kabupaten, pk.nama_puskesmas 
        FROM posyandu p 
        LEFT JOIN wilayah w ON p.id_wilayah = w.id_wilayah 
        LEFT JOIN puskesmas pk ON p.id_puskesmas = pk.id_puskesmas 
        WHERE p.id_posyandu = ?
    ");
    $stmt->execute([$kader_posyandu_id]);
    $kader_posyandu = $stmt->fetch() ?: [
        'id_posyandu' => 1,
        'nama_posyandu' => 'Posyandu Melati 01',
        'alamat' => 'RW 01',
        'nama_wilayah' => 'Kelurahan Sukamaju',
        'nama_puskesmas' => 'Puskesmas Cilodong'
    ];

    // Total balita di posyandu kader
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM anak WHERE id_posyandu = ?");
    $stmt->execute([$kader_posyandu_id]);
    $kader_total_balita = (int)$stmt->fetchColumn();

    // Anak yang SUDAH diukur pada bulan berjalan
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT a.id_anak) 
        FROM anak a 
        JOIN pengukuran p ON a.id_anak = p.id_anak 
        WHERE a.id_posyandu = ? AND DATE_FORMAT(p.tanggal_pengukuran, '%Y-%m') = ?
    ");
    $stmt->execute([$kader_posyandu_id, $bulan_aktif]);
    $kader_anak_diukur = (int)$stmt->fetchColumn();

    // Anak yang BELUM diukur pada bulan berjalan
    $kader_anak_belum = max(0, $kader_total_balita - $kader_anak_diukur);
    $kader_persen_diukur = $kader_total_balita > 0 ? round(($kader_anak_diukur / $kader_total_balita) * 100, 1) : 0;

    // Jadwal posyandu terdekat / berikutnya
    $stmt = $pdo->prepare("
        SELECT * FROM jadwal_posyandu 
        WHERE id_posyandu = ? AND tanggal >= CURDATE() 
        ORDER BY tanggal ASC 
        LIMIT 1
    ");
    $stmt->execute([$kader_posyandu_id]);
    $kader_jadwal_terdekat = $stmt->fetch();

    if (!$kader_jadwal_terdekat) {
        // Ambil jadwal terakhir sebagai referensi
        $stmt = $pdo->prepare("SELECT * FROM jadwal_posyandu WHERE id_posyandu = ? ORDER BY tanggal DESC LIMIT 1");
        $stmt->execute([$kader_posyandu_id]);
        $kader_jadwal_terdekat = $stmt->fetch();
    }

    // Riwayat jadwal posyandu kader
    $stmt = $pdo->prepare("
        SELECT j.*, 
               (SELECT COUNT(*) FROM kehadiran k WHERE k.id_jadwal = j.id_jadwal AND k.status_kehadiran = 'Hadir') as hadir_count
        FROM jadwal_posyandu j 
        WHERE j.id_posyandu = ? 
        ORDER BY j.tanggal DESC 
        LIMIT 5
    ");
    $stmt->execute([$kader_posyandu_id]);
    $kader_daftar_jadwal = $stmt->fetchAll();

    // Daftar balita & status pengukuran bulan berjalan
    $stmt = $pdo->prepare("
        SELECT a.id_anak, a.nama, a.nik, a.tanggal_lahir, a.jenis_kelamin, o.nama_ibu,
               (SELECT p.id_pengukuran FROM pengukuran p WHERE p.id_anak = a.id_anak AND DATE_FORMAT(p.tanggal_pengukuran, '%Y-%m') = ? ORDER BY p.tanggal_pengukuran DESC LIMIT 1) as id_pengukuran_bulan_ini,
               (SELECT p.tanggal_pengukuran FROM pengukuran p WHERE p.id_anak = a.id_anak AND DATE_FORMAT(p.tanggal_pengukuran, '%Y-%m') = ? ORDER BY p.tanggal_pengukuran DESC LIMIT 1) as tgl_ukur_bulan_ini,
               (SELECT p.berat_badan FROM pengukuran p WHERE p.id_anak = a.id_anak ORDER BY p.tanggal_pengukuran DESC LIMIT 1) as bb_terakhir,
               (SELECT p.tinggi_badan FROM pengukuran p WHERE p.id_anak = a.id_anak ORDER BY p.tanggal_pengukuran DESC LIMIT 1) as tb_terakhir,
               (SELECT sg.status_tb_u FROM pengukuran p JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran WHERE p.id_anak = a.id_anak ORDER BY p.tanggal_pengukuran DESC LIMIT 1) as status_tb_u,
               (SELECT sg.status_bb_u FROM pengukuran p JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran WHERE p.id_anak = a.id_anak ORDER BY p.tanggal_pengukuran DESC LIMIT 1) as status_bb_u,
               (SELECT sg.status_risiko FROM pengukuran p JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran WHERE p.id_anak = a.id_anak ORDER BY p.tanggal_pengukuran DESC LIMIT 1) as status_risiko
        FROM anak a 
        LEFT JOIN orangtua o ON a.id_orangtua = o.id_orangtua
        WHERE a.id_posyandu = ? 
        ORDER BY (tgl_ukur_bulan_ini IS NOT NULL) ASC, a.nama ASC
    ");
    $stmt->execute([$bulan_aktif, $bulan_aktif, $kader_posyandu_id]);
    $kader_anak_list = $stmt->fetchAll();
}

// ------------------------------------------------------------
// 2. DATA KHUSUS BIDAN / PETUGAS PUSKESMAS
// ------------------------------------------------------------
if ($active_role === 'petugas' && $pdo !== null) {
    $petugas_puskesmas_id = $user_puskesmas_id > 0 ? $user_puskesmas_id : 1;

    // Info Puskesmas
    $stmt = $pdo->prepare("
        SELECT pk.*, w.nama_wilayah 
        FROM puskesmas pk 
        LEFT JOIN wilayah w ON pk.id_wilayah = w.id_wilayah 
        WHERE pk.id_puskesmas = ?
    ");
    $stmt->execute([$petugas_puskesmas_id]);
    $petugas_puskesmas = $stmt->fetch() ?: [
        'id_puskesmas' => 1,
        'nama_puskesmas' => 'Puskesmas Cilodong',
        'alamat' => 'Kecamatan Cilodong',
        'nama_wilayah' => 'Kelurahan Sukamaju'
    ];

    // Total posyandu binaan di bawah puskesmas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM posyandu WHERE id_puskesmas = ?");
    $stmt->execute([$petugas_puskesmas_id]);
    $petugas_total_posyandu = (int)$stmt->fetchColumn();

    // Total balita di seluruh posyandu puskesmas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM anak a 
        JOIN posyandu p ON a.id_posyandu = p.id_posyandu 
        WHERE p.id_puskesmas = ?
    ");
    $stmt->execute([$petugas_puskesmas_id]);
    $petugas_total_balita = (int)$stmt->fetchColumn();

    // Kasus Stunting Aktif di seluruh posyandu puskesmas
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT a.id_anak) 
        FROM anak a 
        JOIN posyandu p ON a.id_posyandu = p.id_posyandu 
        JOIN pengukuran pg ON a.id_anak = pg.id_anak 
        JOIN status_gizi sg ON pg.id_pengukuran = sg.id_pengukuran 
        WHERE p.id_puskesmas = ? 
          AND sg.status_tb_u IN ('Pendek', 'Sangat Pendek', 'Stunting')
          AND pg.id_pengukuran = (SELECT p_sub.id_pengukuran FROM pengukuran p_sub WHERE p_sub.id_anak = a.id_anak ORDER BY p_sub.tanggal_pengukuran DESC LIMIT 1)
    ");
    $stmt->execute([$petugas_puskesmas_id]);
    $petugas_kasus_stunting = (int)$stmt->fetchColumn();

    // Kasus Gizi Kurang / Buruk Aktif
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT a.id_anak) 
        FROM anak a 
        JOIN posyandu p ON a.id_posyandu = p.id_posyandu 
        JOIN pengukuran pg ON a.id_anak = pg.id_anak 
        JOIN status_gizi sg ON pg.id_pengukuran = sg.id_pengukuran 
        WHERE p.id_puskesmas = ? 
          AND sg.status_bb_u IN ('Gizi Kurang', 'Gizi Buruk')
          AND pg.id_pengukuran = (SELECT p_sub.id_pengukuran FROM pengukuran p_sub WHERE p_sub.id_anak = a.id_anak ORDER BY p_sub.tanggal_pengukuran DESC LIMIT 1)
    ");
    $stmt->execute([$petugas_puskesmas_id]);
    $petugas_kasus_gizi_kurang = (int)$stmt->fetchColumn();

    // Tren Bulanan Seluruh Posyandu di Wilayah Petugas (6 Bulan Terakhir)
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(p.tanggal_pengukuran, '%Y-%m') as bulan,
               COUNT(*) as total_ukur,
               SUM(CASE WHEN sg.status_tb_u IN ('Pendek', 'Sangat Pendek', 'Stunting') THEN 1 ELSE 0 END) as stunting_count,
               SUM(CASE WHEN sg.status_tb_u = 'Normal' THEN 1 ELSE 0 END) as normal_count,
               SUM(CASE WHEN sg.status_bb_u IN ('Gizi Kurang', 'Gizi Buruk') THEN 1 ELSE 0 END) as gizi_kurang_count
        FROM pengukuran p
        JOIN anak a ON p.id_anak = a.id_anak
        JOIN posyandu pos ON a.id_posyandu = pos.id_posyandu
        LEFT JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
        WHERE pos.id_puskesmas = ?
        GROUP BY bulan
        ORDER BY bulan ASC
        LIMIT 6
    ");
    $stmt->execute([$petugas_puskesmas_id]);
    $petugas_tren_bulanan = $stmt->fetchAll();

    // Hitung max height untuk bar chart visual
    $max_ukur = 1;
    foreach ($petugas_tren_bulanan as $t) {
        if ((int)$t['total_ukur'] > $max_ukur) $max_ukur = (int)$t['total_ukur'];
    }

    // Rekapitulasi Status Gizi per Posyandu di Wilayah Petugas
    $stmt = $pdo->prepare("
        SELECT pos.id_posyandu, pos.nama_posyandu, pos.alamat,
               COUNT(DISTINCT a.id_anak) as total_anak,
               COUNT(DISTINCT CASE WHEN DATE_FORMAT(p.tanggal_pengukuran, '%Y-%m') = ? THEN a.id_anak END) as diukur_bulan_ini,
               COUNT(DISTINCT CASE WHEN sg.status_tb_u IN ('Pendek', 'Sangat Pendek', 'Stunting') THEN a.id_anak END) as kasus_stunting,
               COUNT(DISTINCT CASE WHEN sg.status_tb_u = 'Normal' THEN a.id_anak END) as kasus_normal,
               COUNT(DISTINCT CASE WHEN sg.status_bb_u IN ('Gizi Kurang', 'Gizi Buruk') THEN a.id_anak END) as kasus_gizi_kurang
        FROM posyandu pos
        LEFT JOIN anak a ON pos.id_posyandu = a.id_posyandu
        LEFT JOIN pengukuran p ON a.id_anak = p.id_anak
        LEFT JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
        WHERE pos.id_puskesmas = ?
        GROUP BY pos.id_posyandu
        ORDER BY kasus_stunting DESC, total_anak DESC
    ");
    $stmt->execute([$bulan_aktif, $petugas_puskesmas_id]);
    $petugas_rekap_posyandu = $stmt->fetchAll();

    // Balita Prioritas / Berisiko yang Perlu Intervensi
    $stmt = $pdo->prepare("
        SELECT a.id_anak, a.nama as nama_anak, a.tanggal_lahir, a.jenis_kelamin, pos.nama_posyandu,
               p.id_pengukuran, p.tanggal_pengukuran, p.berat_badan, p.tinggi_badan,
               sg.status_tb_u, sg.status_bb_u, sg.status_bb_tb, sg.status_risiko
        FROM anak a 
        JOIN posyandu pos ON a.id_posyandu = pos.id_posyandu
        JOIN pengukuran p ON a.id_anak = p.id_anak
        JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
        WHERE pos.id_puskesmas = ?
          AND (sg.status_tb_u IN ('Pendek', 'Sangat Pendek', 'Stunting') OR sg.status_bb_u IN ('Gizi Kurang', 'Gizi Buruk') OR sg.status_risiko != 'Normal')
          AND p.id_pengukuran = (SELECT p_sub.id_pengukuran FROM pengukuran p_sub WHERE p_sub.id_anak = a.id_anak ORDER BY p_sub.tanggal_pengukuran DESC LIMIT 1)
        ORDER BY (sg.status_tb_u IN ('Pendek', 'Sangat Pendek', 'Stunting')) DESC, p.tanggal_pengukuran DESC
        LIMIT 6
    ");
    $stmt->execute([$petugas_puskesmas_id]);
    $petugas_balita_intervensi = $stmt->fetchAll();
}

// ------------------------------------------------------------
// 3. DATA KHUSUS KOORDINATOR DESA
// ------------------------------------------------------------
if ($active_role === 'kordes' && $pdo !== null) {
    $kordes_wilayah_id = $user_wilayah_id > 0 ? $user_wilayah_id : 1;

    // Info Wilayah Desa
    $stmt = $pdo->prepare("SELECT * FROM wilayah WHERE id_wilayah = ?");
    $stmt->execute([$kordes_wilayah_id]);
    $kordes_wilayah = $stmt->fetch() ?: [
        'id_wilayah' => 1,
        'nama_wilayah' => 'Kelurahan Sukamaju',
        'kecamatan' => 'Cilodong',
        'kabupaten' => 'Kota Depok'
    ];

    // Rekapitulasi status gizi per posyandu/dusun untuk koordinator desa
    $stmt = $pdo->prepare("
        SELECT pos.id_posyandu, pos.nama_posyandu, pos.alamat,
               COUNT(DISTINCT a.id_anak) as total_anak,
               COUNT(DISTINCT CASE WHEN DATE_FORMAT(p.tanggal_pengukuran, '%Y-%m') = ? THEN a.id_anak END) as diukur_bulan_ini,
               COUNT(DISTINCT CASE WHEN sg.status_tb_u IN ('Pendek', 'Sangat Pendek', 'Stunting') THEN a.id_anak END) as kasus_stunting,
               COUNT(DISTINCT CASE WHEN sg.status_bb_u IN ('Gizi Kurang', 'Gizi Buruk') THEN a.id_anak END) as kasus_gizi_kurang,
               COUNT(DISTINCT CASE WHEN sg.status_tb_u = 'Normal' THEN a.id_anak END) as kasus_normal
        FROM posyandu pos
        LEFT JOIN anak a ON pos.id_posyandu = a.id_posyandu
        LEFT JOIN pengukuran p ON a.id_anak = p.id_anak
        LEFT JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
        WHERE pos.id_wilayah = ?
        GROUP BY pos.id_posyandu
        ORDER BY kasus_stunting DESC, total_anak DESC
    ");
    $stmt->execute([$bulan_aktif, $kordes_wilayah_id]);
    $kordes_rekap_posyandu = $stmt->fetchAll();

    // Metrik Agregat Desa
    $kordes_total_posyandu = count($kordes_rekap_posyandu);
    $kordes_total_balita = 0;
    $kordes_total_diukur = 0;
    $kordes_total_stunting = 0;
    $kordes_total_gizi_kurang = 0;

    foreach ($kordes_rekap_posyandu as $row) {
        $kordes_total_balita      += (int)$row['total_anak'];
        $kordes_total_diukur      += (int)$row['diukur_bulan_ini'];
        $kordes_total_stunting    += (int)$row['kasus_stunting'];
        $kordes_total_gizi_kurang += (int)$row['kasus_gizi_kurang'];
    }

    $kordes_persen_stunting = $kordes_total_balita > 0 ? round(($kordes_total_stunting / $kordes_total_balita) * 100, 1) : 0;
    $kordes_persen_diukur   = $kordes_total_balita > 0 ? round(($kordes_total_diukur / $kordes_total_balita) * 100, 1) : 0;
}

// ------------------------------------------------------------
// 4. DATA KHUSUS ADMINISTRATOR SISTEM
// ------------------------------------------------------------
if ($active_role === 'admin' && $pdo !== null) {
    // 1. Ringkasan Pengguna
    $admin_total_users = (int)$pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
    $admin_users_aktif = (int)$pdo->query("SELECT COUNT(*) FROM user WHERE LOWER(status) = 'aktif'")->fetchColumn();
    $admin_users_nonaktif = max(0, $admin_total_users - $admin_users_aktif);

    // Breakdown Pengguna per Role
    $admin_users_by_role = $pdo->query("
        SELECT r.id_role, r.nama_role, COUNT(u.id_user) as total_user 
        FROM role r 
        LEFT JOIN user u ON r.id_role = u.id_role 
        GROUP BY r.id_role 
        ORDER BY r.id_role ASC
    ")->fetchAll();

    // 2. Ringkasan Posyandu & Faskes
    $admin_total_posyandu   = (int)$pdo->query("SELECT COUNT(*) FROM posyandu")->fetchColumn();
    $admin_total_puskesmas  = (int)$pdo->query("SELECT COUNT(*) FROM puskesmas")->fetchColumn();
    $admin_total_wilayah    = (int)$pdo->query("SELECT COUNT(*) FROM wilayah")->fetchColumn();
    $admin_total_balita     = (int)$pdo->query("SELECT COUNT(*) FROM anak")->fetchColumn();
    $admin_total_orangtua   = (int)$pdo->query("SELECT COUNT(*) FROM orangtua")->fetchColumn();
    $admin_total_pengukuran = (int)$pdo->query("SELECT COUNT(*) FROM pengukuran")->fetchColumn();
    $admin_total_jadwal     = (int)$pdo->query("SELECT COUNT(*) FROM jadwal_posyandu")->fetchColumn();

    // 3. Aktivitas Transaksi Pengukuran Terakhir
    $admin_recent_measurements = $pdo->query("
        SELECT p.id_pengukuran, p.tanggal_pengukuran, p.created_at, p.berat_badan, p.tinggi_badan,
               a.nama as nama_anak, pos.nama_posyandu, u.nama as nama_petugas,
               sg.status_tb_u, sg.status_bb_u
        FROM pengukuran p
        JOIN anak a ON p.id_anak = a.id_anak
        JOIN posyandu pos ON a.id_posyandu = pos.id_posyandu
        LEFT JOIN user u ON p.id_user = u.id_user
        LEFT JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
        ORDER BY p.id_pengukuran DESC
        LIMIT 5
    ")->fetchAll();

    // Status Sistem Info
    $mysql_version = $pdo->query("SELECT VERSION()")->fetchColumn() ?: 'MySQL 8.x';
    $system_status = [
        'db_status' => 'Terhubung (Online)',
        'db_name'   => 'db_sipantau_gizi',
        'mysql_ver' => $mysql_version,
        'php_ver'   => PHP_VERSION,
        'rbac_mode' => 'Role-Based Access Control Aktif',
        'csrf_mode' => 'CSRF Token Protection Aktif',
        'auth_mode' => 'Bcrypt Hash Authentication'
    ];
}

// Include Header & Sidebar Layout
include ROOT_PATH . '/includes/header.php';
?>

<!-- ============================================================ -->
<!-- BANNER UTAMA DASHBOARD & ROLE SWITCHER (SUPER-ADMIN PREVIEW) -->
<!-- ============================================================ -->
<div class="dashboard-hero mb-4">
  <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3">
      <div class="profile-avatar bg-primary text-white rounded-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px; font-size: 1.35rem;">
        <?php if ($active_role === 'kader'): ?>
          <i class="bi bi-person-heart"></i>
        <?php elseif ($active_role === 'petugas'): ?>
          <i class="bi bi-hospital"></i>
        <?php elseif ($active_role === 'kordes'): ?>
          <i class="bi bi-geo-alt-fill"></i>
        <?php else: ?>
          <i class="bi bi-shield-check"></i>
        <?php endif; ?>
      </div>
      <div>
        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
          <h1 class="h4 mb-0 fw-bold">Selamat Datang, <?= e($user_nama) ?>!</h1>
          <?php if ($active_role === 'kader'): ?>
            <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">Dashboard Kader</span>
          <?php elseif ($active_role === 'petugas'): ?>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Dashboard Bidan / Petugas Puskesmas</span>
          <?php elseif ($active_role === 'kordes'): ?>
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">Dashboard Koordinator Desa</span>
          <?php else: ?>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">Dashboard Administrator</span>
          <?php endif; ?>
        </div>
        <p class="text-muted small mb-0">
          <?php if ($active_role === 'kader'): ?>
            <i class="bi bi-building me-1"></i><strong><?= e($kader_posyandu['nama_posyandu']) ?></strong> &middot; <?= e($kader_posyandu['nama_wilayah'] ?? 'Kelurahan') ?> &middot; Pembina: <?= e($kader_posyandu['nama_puskesmas'] ?? '-') ?>
          <?php elseif ($active_role === 'petugas'): ?>
            <i class="bi bi-hospital me-1"></i><strong><?= e($petugas_puskesmas['nama_puskesmas']) ?></strong> &middot; Wilayah Kerja: <?= e($petugas_puskesmas['nama_wilayah'] ?? 'Kecamatan') ?>
          <?php elseif ($active_role === 'kordes'): ?>
            <i class="bi bi-geo-alt me-1"></i><strong><?= e($kordes_wilayah['nama_wilayah']) ?></strong>, Kec. <?= e($kordes_wilayah['kecamatan']) ?>, <?= e($kordes_wilayah['kabupaten']) ?>
          <?php else: ?>
            <i class="bi bi-cpu me-1"></i>Pusat Kendali Sistem &middot; Ringkasan Pengguna, Faskes, dan Status Sistem Umum
          <?php endif; ?>
        </p>
      </div>
    </div>

    <!-- Pilihan Periode Bulan Berjalan -->
    <div class="d-flex align-items-center gap-2 align-self-stretch align-self-lg-auto justify-content-end">
      <form method="GET" action="" class="d-flex align-items-center gap-2">
        <?php if ($is_admin && $active_role !== 'admin'): ?>
          <input type="hidden" name="role" value="<?= e($active_role) ?>">
        <?php endif; ?>
        <label for="bulanSelect" class="small text-muted text-nowrap d-none d-sm-inline"><i class="bi bi-calendar3 me-1"></i>Bulan:</label>
        <select name="bulan" id="bulanSelect" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 160px;">
          <?php foreach ($available_months as $m_val): ?>
            <option value="<?= e($m_val) ?>" <?= ($bulan_aktif === $m_val) ? 'selected' : '' ?>>
              <?= formatBulanIndo($m_val) ?><?= ($m_val === $current_month) ? ' (Bulan Ini)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>

  <?php if ($is_admin): ?>
    <!-- ROLE SWITCHER TOOLBAR (HANYA UNTUK ADMINISTRATOR / PENGUJIAN) -->
    <div class="mt-3 pt-3 border-top d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-2">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="small fw-semibold text-secondary"><i class="bi bi-toggles me-1"></i>Pratinjau Dashboard Peran:</span>
        <div class="role-preview-bar">
          <a href="<?= base_url('modules/dashboard/index.php?role=admin&bulan=' . urlencode($bulan_aktif)) ?>"
             class="role-preview-btn <?= ($active_role === 'admin') ? 'active' : 'text-dark bg-white border' ?>">
            <i class="bi bi-shield-check"></i> Admin
          </a>
          <a href="<?= base_url('modules/dashboard/index.php?role=kader&bulan=' . urlencode($bulan_aktif)) ?>"
             class="role-preview-btn <?= ($active_role === 'kader') ? 'active' : 'text-dark bg-white border' ?>">
            <i class="bi bi-person-heart"></i> Kader
          </a>
          <a href="<?= base_url('modules/dashboard/index.php?role=petugas&bulan=' . urlencode($bulan_aktif)) ?>"
             class="role-preview-btn <?= ($active_role === 'petugas') ? 'active' : 'text-dark bg-white border' ?>">
            <i class="bi bi-hospital"></i> Bidan/Petugas
          </a>
          <a href="<?= base_url('modules/dashboard/index.php?role=kordes&bulan=' . urlencode($bulan_aktif)) ?>"
             class="role-preview-btn <?= ($active_role === 'kordes') ? 'active' : 'text-dark bg-white border' ?>">
            <i class="bi bi-geo-alt"></i> Koordinator Desa
          </a>
        </div>
      </div>
      <small class="text-muted fst-italic">
        <i class="bi bi-info-circle me-1"></i>Menampilkan pratinjau sesuai modul jobdesk masing-masing peran.
      </small>
    </div>
  <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- KONTEN DASHBOARD BERDASARKAN PERAN AKTIF                     -->
<!-- ============================================================ -->

<?php if ($active_role === 'kader'): ?>
  <!-- ============================================================ -->
  <!-- 1. DASHBOARD KADER                                           -->
  <!-- ============================================================ -->
  
  <!-- 4 Kartu Metrik Kader -->
  <section class="row g-3" aria-label="Metrik Kader">
    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-primary">
        <div class="metric-top">
          <span class="metric-label">Total Balita Posyandu</span>
          <span class="metric-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value"><?= number_format($kader_total_balita) ?></div>
        <div class="metric-meta">
          <span class="text-muted">Sasaran balita di <?= e($kader_posyandu['nama_posyandu']) ?></span>
        </div>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-success">
        <div class="metric-top">
          <span class="metric-label">Sudah Diukur (<?= formatBulanIndo($bulan_aktif) ?>)</span>
          <span class="metric-icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value text-success"><?= number_format($kader_anak_diukur) ?></div>
        <div class="metric-meta">
          <span class="text-success fw-bold"><?= $kader_persen_diukur ?>%</span>
          <span class="text-muted">ketercapaian bulan ini</span>
        </div>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card <?= ($kader_anak_belum > 0) ? 'metric-warning' : 'metric-success' ?>">
        <div class="metric-top">
          <span class="metric-label">Belum Diukur (Bulan Ini)</span>
          <span class="metric-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value <?= ($kader_anak_belum > 0) ? 'text-warning' : 'text-success' ?>">
          <?= number_format($kader_anak_belum) ?>
        </div>
        <div class="metric-meta">
          <span class="<?= ($kader_anak_belum > 0) ? 'text-warning' : 'text-success' ?>">
            <?= ($kader_anak_belum > 0) ? 'Perlu penimbangan rutin' : 'Semua anak telah diukur' ?>
          </span>
        </div>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-primary">
        <div class="metric-top">
          <span class="metric-label">Jadwal Posyandu Terdekat</span>
          <span class="metric-icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value fs-4 mt-2">
          <?= $kader_jadwal_terdekat ? date('d M Y', strtotime($kader_jadwal_terdekat['tanggal'])) : '-' ?>
        </div>
        <div class="metric-meta">
          <span class="text-muted"><?= $kader_jadwal_terdekat ? substr($kader_jadwal_terdekat['waktu'], 0, 5) . ' WIB' : 'Belum ada jadwal' ?></span>
        </div>
      </article>
    </div>
  </section>

  <!-- Progress Bar Ketercapaian Pengukuran Bulan Berjalan -->
  <div class="panel mt-3 p-3 bg-white border rounded-3 shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <strong class="small text-dark">
        <i class="bi bi-activity text-primary me-1"></i>Progres Ketercapaian Penimbangan Balita Bulan Ini
      </strong>
      <span class="badge bg-primary px-2 py-1"><?= $kader_anak_diukur ?> dari <?= $kader_total_balita ?> Anak (<?= $kader_persen_diukur ?>%)</span>
    </div>
    <div class="progress" style="height: 10px;">
      <div class="progress-bar bg-success" role="progressbar" style="width: <?= min(100, $kader_persen_diukur) ?>%;" aria-valuenow="<?= $kader_persen_diukur ?>" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <!-- Kolom Kiri: Jadwal Posyandu & Agenda Kegiatan -->
    <div class="col-12 col-lg-5">
      <div class="panel h-100">
        <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h2 class="h5 mb-0 section-title">
              <i class="bi bi-calendar-check text-primary me-2"></i>Agenda & Jadwal Posyandu
            </h2>
            <p class="text-muted small mb-0">Jadwal kegiatan penimbangan di <?= e($kader_posyandu['nama_posyandu']) ?></p>
          </div>
          <a href="<?= base_url('modules/jadwal/index.php') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-box-arrow-up-right me-1"></i>Semua Jadwal
          </a>
        </div>

        <?php if ($kader_jadwal_terdekat): 
          $is_upcoming = strtotime($kader_jadwal_terdekat['tanggal']) >= strtotime(date('Y-m-d'));
        ?>
          <div class="alert <?= $is_upcoming ? 'alert-primary' : 'alert-light border' ?> mb-3">
            <div class="d-flex align-items-center justify-content-between mb-1">
              <span class="badge <?= $is_upcoming ? 'bg-primary' : 'bg-secondary' ?> text-uppercase px-2 py-1">
                <?= $is_upcoming ? 'Jadwal Mendatang' : 'Jadwal Terakhir' ?>
              </span>
              <strong class="text-primary small"><?= formatTanggalIndo($kader_jadwal_terdekat['tanggal']) ?></strong>
            </div>
            <h3 class="h6 fw-bold mb-1 mt-2 text-dark">
              <i class="bi bi-clock me-1"></i><?= substr($kader_jadwal_terdekat['waktu'], 0, 5) ?> WIB
            </h3>
            <p class="small mb-1 text-muted">
              <i class="bi bi-geo-alt text-danger me-1"></i><?= e($kader_jadwal_terdekat['lokasi']) ?>
            </p>
            <p class="small mb-0 text-dark">
              <i class="bi bi-card-text me-1"></i><?= e($kader_jadwal_terdekat['keterangan'] ?: 'Pelayanan kesehatan & penimbangan balita') ?>
            </p>
          </div>
        <?php else: ?>
          <div class="p-3 text-center text-muted small border rounded-3 mb-3">
            Belum ada jadwal posyandu terdaftar.
          </div>
        <?php endif; ?>

        <h3 class="h6 fw-semibold text-muted mb-2 text-uppercase" style="font-size: 0.76rem; letter-spacing: 0.05em;">Riwayat Jadwal Terkini</h3>
        <div class="table-responsive">
          <table class="table table-sm align-middle small mb-0">
            <thead class="table-light">
              <tr>
                <th>Tanggal</th>
                <th>Lokasi</th>
                <th class="text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($kader_daftar_jadwal)): ?>
                <tr><td colspan="3" class="text-center py-3 text-muted">Belum ada riwayat jadwal.</td></tr>
              <?php else: ?>
                <?php foreach ($kader_daftar_jadwal as $j): 
                  $tgl_cek = strtotime($j['tanggal']);
                  $today = strtotime(date('Y-m-d'));
                ?>
                  <tr>
                    <td class="fw-semibold text-nowrap"><?= date('d/m/Y', $tgl_cek) ?></td>
                    <td class="text-truncate" style="max-width: 140px;" title="<?= e($j['lokasi']) ?>"><?= e($j['lokasi']) ?></td>
                    <td class="text-center">
                      <?php if ($tgl_cek > $today): ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Mendatang</span>
                      <?php elseif ($tgl_cek === $today): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Hari Ini</span>
                      <?php else: ?>
                        <span class="badge bg-light text-secondary border">Selesai</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="mt-3 pt-2 text-end">
          <a href="<?= base_url('modules/jadwal/tambah.php') ?>" class="btn btn-primary btn-sm w-100">
            <i class="bi bi-calendar-plus me-1"></i>Tambah Jadwal Baru
          </a>
        </div>
      </div>
    </div>

    <!-- Kolom Kanan: Daftar Anak & Status Pengukuran Bulan Berjalan -->
    <div class="col-12 col-lg-7">
      <div class="panel h-100">
        <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h2 class="h5 mb-0 section-title">
              <i class="bi bi-rulers text-primary me-2"></i>Status Pengukuran Anak (<?= formatBulanIndo($bulan_aktif) ?>)
            </h2>
            <p class="text-muted small mb-0">Daftar sasaran penimbangan balita pada bulan berjalan</p>
          </div>
          <a href="<?= base_url('modules/pengukuran/tambah.php') ?>" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Input Pengukuran
          </a>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle small mb-0">
            <thead class="table-light">
              <tr>
                <th>Nama Balita</th>
                <th>Usia</th>
                <th>Status Bulan Ini</th>
                <th>BB / TB Terakhir</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($kader_anak_list)): ?>
                <tr>
                  <td colspan="5" class="text-center py-4 text-muted">
                    Belum ada data anak balita terdaftar di posyandu ini.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($kader_anak_list as $anak): 
                  $sudah_ukur = !empty($anak['tgl_ukur_bulan_ini']);
                  $usia = hitungUsiaBulan($anak['tanggal_lahir']);
                ?>
                  <tr>
                    <td>
                      <div class="fw-bold text-dark"><?= e($anak['nama']) ?></div>
                      <span class="text-muted" style="font-size: 0.75rem;">
                        NIK: <?= e($anak['nik'] ?: '-') ?> &middot; Ibu: <?= e($anak['nama_ibu'] ?: '-') ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge bg-light text-dark border"><?= $usia ?> Bln</span>
                      <small class="text-muted d-block"><?= ($anak['jenis_kelamin'] === 'L') ? 'Laki-laki' : 'Perempuan' ?></small>
                    </td>
                    <td>
                      <?php if ($sudah_ukur): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1">
                          <i class="bi bi-check-circle-fill"></i> Sudah Diukur
                        </span>
                        <small class="text-muted d-block mt-1"><?= date('d/m/Y', strtotime($anak['tgl_ukur_bulan_ini'])) ?></small>
                      <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle d-inline-flex align-items-center gap-1">
                          <i class="bi bi-clock-history"></i> Belum Diukur
                        </span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (!empty($anak['bb_terakhir'])): ?>
                        <div><strong><?= number_format((float)$anak['bb_terakhir'], 1) ?> kg</strong> / <strong><?= number_format((float)$anak['tb_terakhir'], 1) ?> cm</strong></div>
                        <span class="badge <?= in_array($anak['status_tb_u'], ['Pendek', 'Sangat Pendek', 'Stunting']) ? 'bg-danger-subtle text-danger' : 'bg-light text-dark border' ?>" style="font-size: 0.72rem;">
                          <?= e($anak['status_tb_u'] ?: 'Normal') ?>
                        </span>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center text-nowrap">
                      <?php if ($sudah_ukur): ?>
                        <a href="<?= base_url('modules/pengukuran/detail.php?id=' . (int)$anak['id_pengukuran_bulan_ini']) ?>" class="btn btn-outline-primary btn-sm" title="Lihat Detail Hasil Pengukuran">
                          <i class="bi bi-eye"></i> Detail
                        </a>
                      <?php else: ?>
                        <a href="<?= base_url('modules/pengukuran/tambah.php?id_anak=' . (int)$anak['id_anak']) ?>" class="btn btn-success btn-sm" title="Input Penimbangan Bulan Ini">
                          <i class="bi bi-rulers me-1"></i> Ukur
                        </a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($active_role === 'petugas'): ?>
  <!-- ============================================================ -->
  <!-- 2. DASHBOARD BIDAN / PETUGAS PUSKESMAS                       -->
  <!-- ============================================================ -->

  <!-- 4 Kartu Metrik Bidan/Petugas -->
  <section class="row g-3" aria-label="Metrik Petugas Puskesmas">
    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-primary">
        <div class="metric-top">
          <span class="metric-label">Total Balita di Puskesmas</span>
          <span class="metric-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value"><?= number_format($petugas_total_balita) ?></div>
        <div class="metric-meta">
          <span class="text-muted">Total sasaran di <?= number_format($petugas_total_posyandu) ?> Posyandu binaan</span>
        </div>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-success">
        <div class="metric-top">
          <span class="metric-label">Posyandu Binaan Aktif</span>
          <span class="metric-icon"><i class="bi bi-hospital-fill" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value text-success"><?= number_format($petugas_total_posyandu) ?></div>
        <div class="metric-meta">
          <span class="text-muted">Unit posyandu di wilayah kerja</span>
        </div>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-danger">
        <div class="metric-top">
          <span class="metric-label">Kasus Stunting (TB/U)</span>
          <span class="metric-icon"><i class="bi bi-heartbreak-fill" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value text-danger"><?= number_format($petugas_kasus_stunting) ?></div>
        <div class="metric-meta">
          <span class="text-danger fw-bold">
            <?= $petugas_total_balita > 0 ? round(($petugas_kasus_stunting / $petugas_total_balita) * 100, 1) : 0 ?>%
          </span>
          <span class="text-muted">prevalensi stunting</span>
        </div>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-warning">
        <div class="metric-top">
          <span class="metric-label">Gizi Kurang / Buruk (BB/U)</span>
          <span class="metric-icon"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value text-warning"><?= number_format($petugas_kasus_gizi_kurang) ?></div>
        <div class="metric-meta">
          <span class="text-warning">Memerlukan intervensi PMT</span>
        </div>
      </article>
    </div>
  </section>

  <!-- Tren Bulanan Seluruh Posyandu di Wilayah Petugas -->
  <section class="panel mt-3">
    <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h2 class="h5 mb-0 section-title">
          <i class="bi bi-graph-up-arrow text-primary me-2"></i>Tren Bulanan Pengukuran & Status Gizi Seluruh Posyandu
        </h2>
        <p class="text-muted small mb-0">Tren pemantauan bulanan di wilayah kerja <?= e($petugas_puskesmas['nama_puskesmas']) ?></p>
      </div>
      <div class="d-flex align-items-center gap-3 small">
        <span class="d-inline-flex align-items-center gap-1"><span class="badge bg-primary p-1"></span> Total Diukur</span>
        <span class="d-inline-flex align-items-center gap-1"><span class="badge bg-danger p-1"></span> Stunting</span>
        <span class="d-inline-flex align-items-center gap-1"><span class="badge bg-success p-1"></span> Normal</span>
      </div>
    </div>

    <?php if (empty($petugas_tren_bulanan)): ?>
      <div class="p-4 text-center text-muted small">Belum ada data riwayat penimbangan bulanan.</div>
    <?php else: ?>
      <!-- Visual Bar Chart Tren Bulanan -->
      <div class="trend-bars-wrapper mb-3">
        <?php foreach ($petugas_tren_bulanan as $t): 
          $h_total = min(100, round(((int)$t['total_ukur'] / $max_ukur) * 100));
          $h_stunting = (int)$t['total_ukur'] > 0 ? min(100, round(((int)$t['stunting_count'] / $max_ukur) * 100)) : 0;
          $h_normal = (int)$t['total_ukur'] > 0 ? min(100, round(((int)$t['normal_count'] / $max_ukur) * 100)) : 0;
        ?>
          <div class="trend-col">
            <div class="trend-bar-group">
              <div class="trend-bar trend-bar-total" style="height: <?= max(8, $h_total) ?>%;" data-tooltip="Total: <?= (int)$t['total_ukur'] ?> Anak"></div>
              <div class="trend-bar trend-bar-stunting" style="height: <?= max(4, $h_stunting) ?>%;" data-tooltip="Stunting: <?= (int)$t['stunting_count'] ?>"></div>
              <div class="trend-bar trend-bar-normal" style="height: <?= max(4, $h_normal) ?>%;" data-tooltip="Normal: <?= (int)$t['normal_count'] ?>"></div>
            </div>
            <span class="small fw-semibold mt-2 text-muted" style="font-size: 0.75rem;"><?= date('M Y', strtotime($t['bulan'] . '-01')) ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Tabel Ringkasan Tren Bulanan -->
      <div class="table-responsive">
        <table class="table table-bordered table-sm text-center align-middle small mb-0">
          <thead class="table-light">
            <tr>
              <th class="text-start">Bulan</th>
              <th>Total Pengukuran</th>
              <th>Status Normal</th>
              <th>Kasus Stunting</th>
              <th>Gizi Kurang / Buruk</th>
              <th>Prevalensi Stunting</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($petugas_tren_bulanan as $t): 
              $p_stunt = (int)$t['total_ukur'] > 0 ? round(((int)$t['stunting_count'] / (int)$t['total_ukur']) * 100, 1) : 0;
            ?>
              <tr>
                <td class="text-start fw-semibold"><?= formatBulanIndo($t['bulan']) ?></td>
                <td><span class="badge bg-primary-subtle text-primary border"><?= (int)$t['total_ukur'] ?> anak</span></td>
                <td class="text-success fw-bold"><?= (int)$t['normal_count'] ?></td>
                <td class="text-danger fw-bold"><?= (int)$t['stunting_count'] ?></td>
                <td class="text-warning fw-bold"><?= (int)$t['gizi_kurang_count'] ?></td>
                <td>
                  <span class="badge <?= $p_stunt > 15 ? 'bg-danger-subtle text-danger' : ($p_stunt > 5 ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success') ?>">
                    <?= $p_stunt ?>%
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <!-- Tabel Ringkasan Status Gizi Seluruh Posyandu di Wilayah Petugas -->
  <section class="panel mt-3">
    <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h2 class="h5 mb-0 section-title">
          <i class="bi bi-hospital text-primary me-2"></i>Rekapitulasi Status Gizi Seluruh Posyandu Binaan
        </h2>
        <p class="text-muted small mb-0">Pemantauan distribusi gizi antar posyandu di wilayah kerja Puskesmas (Periode: <?= formatBulanIndo($bulan_aktif) ?>)</p>
      </div>
      <a href="<?= base_url('modules/posyandu/index.php') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-box-arrow-up-right me-1"></i>Data Posyandu
      </a>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle small mb-0">
        <thead class="table-light">
          <tr>
            <th>Nama Posyandu</th>
            <th>Lokasi / RW</th>
            <th class="text-center">Total Balita</th>
            <th class="text-center">Diukur Bulan Ini</th>
            <th class="text-center">Normal</th>
            <th class="text-center">Stunting</th>
            <th class="text-center">Gizi Kurang</th>
            <th class="text-center">Status Evaluasi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($petugas_rekap_posyandu)): ?>
            <tr><td colspan="8" class="text-center py-4 text-muted">Belum ada data posyandu binaan.</td></tr>
          <?php else: ?>
            <?php foreach ($petugas_rekap_posyandu as $p): 
              $tot = (int)$p['total_anak'];
              $diukur = (int)$p['diukur_bulan_ini'];
              $stunt = (int)$p['kasus_stunting'];
              $p_diukur = $tot > 0 ? round(($diukur / $tot) * 100, 1) : 0;
              $p_stunt = $tot > 0 ? round(($stunt / $tot) * 100, 1) : 0;
            ?>
              <tr>
                <td class="fw-bold text-dark">
                  <i class="bi bi-building text-primary me-1"></i><?= e($p['nama_posyandu']) ?>
                </td>
                <td class="text-muted"><?= e($p['alamat'] ?: '-') ?></td>
                <td class="text-center fw-semibold"><?= $tot ?></td>
                <td class="text-center">
                  <span class="badge bg-light text-dark border"><?= $diukur ?> (<?= $p_diukur ?>%)</span>
                </td>
                <td class="text-center text-success fw-bold"><?= (int)$p['kasus_normal'] ?></td>
                <td class="text-center text-danger fw-bold"><?= $stunt ?> (<?= $p_stunt ?>%)</td>
                <td class="text-center text-warning fw-bold"><?= (int)$p['kasus_gizi_kurang'] ?></td>
                <td class="text-center">
                  <?php if ($stunt === 0): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Terkendali</span>
                  <?php elseif ($p_stunt > 20): ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Prioritas Intervensi</span>
                  <?php else: ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Waspada</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Daftar Balita Berisiko Perlu Intervensi -->
  <?php if (!empty($petugas_balita_intervensi)): ?>
    <section class="panel mt-3">
      <div class="panel-header d-flex justify-content-between align-items-center">
        <div>
          <h2 class="h5 mb-0 section-title text-danger">
            <i class="bi bi-exclamation-octagon-fill me-2"></i>Daftar Balita Perlu Intervensi Khusus
          </h2>
          <p class="text-muted small mb-0">Balita dengan indikasi stunting atau gizi buruk untuk tindak lanjut rujukan / PMT Puskesmas</p>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle small mb-0">
          <thead class="table-light">
            <tr>
              <th>Nama Balita</th>
              <th>Posyandu</th>
              <th>Usia</th>
              <th>Status TB/U (Stunting)</th>
              <th>Status BB/U</th>
              <th>Tgl Pengukuran</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($petugas_balita_intervensi as $b): 
              $usia = hitungUsiaBulan($b['tanggal_lahir']);
            ?>
              <tr>
                <td class="fw-bold text-dark"><?= e($b['nama_anak']) ?></td>
                <td><span class="badge bg-light text-dark border"><?= e($b['nama_posyandu']) ?></span></td>
                <td><?= $usia ?> Bulan</td>
                <td>
                  <span class="badge <?= in_array($b['status_tb_u'], ['Pendek', 'Sangat Pendek', 'Stunting']) ? 'bg-danger text-white' : 'bg-light text-dark border' ?>">
                    <?= e($b['status_tb_u']) ?>
                  </span>
                </td>
                <td>
                  <span class="badge <?= in_array($b['status_bb_u'], ['Gizi Kurang', 'Gizi Buruk']) ? 'bg-warning text-dark' : 'bg-light text-dark border' ?>">
                    <?= e($b['status_bb_u']) ?>
                  </span>
                </td>
                <td class="text-muted"><?= date('d/m/Y', strtotime($b['tanggal_pengukuran'])) ?></td>
                <td class="text-center">
                  <a href="<?= base_url('modules/pengukuran/detail.php?id=' . (int)$b['id_pengukuran']) ?>" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.78rem;">
                    <i class="bi bi-clipboard2-pulse me-1"></i>Tinjau
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>

<?php elseif ($active_role === 'kordes'): ?>
  <!-- ============================================================ -->
  <!-- 3. DASHBOARD KOORDINATOR DESA                                -->
  <!-- ============================================================ -->

  <!-- 4 Kartu Metrik Koordinator Desa -->
  <section class="row g-3" aria-label="Metrik Koordinator Desa">
    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-primary">
        <div class="metric-top">
          <span class="metric-label">Posyandu / Dusun di Desa</span>
          <span class="metric-icon"><i class="bi bi-houses-fill" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value"><?= number_format($kordes_total_posyandu) ?></div>
        <div class="metric-meta">
          <span class="text-muted">Titik layanan di <?= e($kordes_wilayah['nama_wilayah']) ?></span>
        </div>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-success">
        <div class="metric-top">
          <span class="metric-label">Total Sasaran Balita Desa</span>
          <span class="metric-icon"><i class="bi bi-person-hearts" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value text-success"><?= number_format($kordes_total_balita) ?></div>
        <div class="metric-meta">
          <span class="text-muted">Balita terdata se-desa</span>
        </div>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-danger">
        <div class="metric-top">
          <span class="metric-label">Prevalensi Stunting Desa</span>
          <span class="metric-icon"><i class="bi bi-heartbreak-fill" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value text-danger"><?= $kordes_persen_stunting ?>%</div>
        <div class="metric-meta">
          <span class="text-danger fw-bold"><?= number_format($kordes_total_stunting) ?> anak</span>
          <span class="text-muted">dari total balita</span>
        </div>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-warning">
        <div class="metric-top">
          <span class="metric-label">Cakupan Pengukuran (<?= formatBulanIndo($bulan_aktif) ?>)</span>
          <span class="metric-icon"><i class="bi bi-pie-chart-fill" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value text-warning"><?= $kordes_persen_diukur ?>%</div>
        <div class="metric-meta">
          <span class="text-muted"><?= number_format($kordes_total_diukur) ?> balita ditimbang</span>
        </div>
      </article>
    </div>
  </section>

  <!-- Tabel Ringkasan Status Gizi per Posyandu / Dusun -->
  <section class="panel mt-3">
    <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h2 class="h5 mb-0 section-title">
          <i class="bi bi-table text-primary me-2"></i>Ringkasan Status Gizi per Posyandu / Dusun
        </h2>
        <p class="text-muted small mb-0">Komparasi capaian penimbangan dan prevalensi gizi antar posyandu di wilayah <?= e($kordes_wilayah['nama_wilayah']) ?></p>
      </div>
      <div class="d-flex align-items-center gap-2 small">
        <span class="badge zone-badge-green px-2 py-1"><i class="bi bi-shield-check me-1"></i>Hijau: Aman (&lt;10%)</span>
        <span class="badge zone-badge-yellow px-2 py-1"><i class="bi bi-exclamation-triangle me-1"></i>Kuning: Waspada (10-20%)</span>
        <span class="badge zone-badge-red px-2 py-1"><i class="bi bi-exclamation-octagon me-1"></i>Merah: Prioritas (&gt;20%)</span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle small mb-0">
        <thead class="table-light">
          <tr>
            <th>Posyandu / Dusun</th>
            <th>Alamat / RW</th>
            <th class="text-center">Sasaran Balita</th>
            <th class="text-center">Diukur Bulan Ini</th>
            <th class="text-center">Gizi Baik (Normal)</th>
            <th class="text-center">Kasus Stunting</th>
            <th class="text-center">Gizi Kurang</th>
            <th class="text-center">Zonasi Risiko Desa</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($kordes_rekap_posyandu)): ?>
            <tr><td colspan="8" class="text-center py-4 text-muted">Belum ada data posyandu terdaftar di desa ini.</td></tr>
          <?php else: ?>
            <?php foreach ($kordes_rekap_posyandu as $row): 
              $tot = (int)$row['total_anak'];
              $diukur = (int)$row['diukur_bulan_ini'];
              $stunt = (int)$row['kasus_stunting'];
              $p_diukur = $tot > 0 ? round(($diukur / $tot) * 100, 1) : 0;
              $p_stunt = $tot > 0 ? round(($stunt / $tot) * 100, 1) : 0;

              // Zonasi stunting desa
              if ($p_stunt > 20) {
                  $zone_class = 'zone-badge-red';
                  $zone_label = 'Merah (Prioritas Desa)';
                  $zone_icon = 'bi-exclamation-octagon-fill text-danger';
              } elseif ($p_stunt > 10) {
                  $zone_class = 'zone-badge-yellow';
                  $zone_label = 'Kuning (Waspada)';
                  $zone_icon = 'bi-exclamation-triangle-fill text-warning';
              } else {
                  $zone_class = 'zone-badge-green';
                  $zone_label = 'Hijau (Terkendali)';
                  $zone_icon = 'bi-check-circle-fill text-success';
              }
            ?>
              <tr>
                <td class="fw-bold text-dark">
                  <i class="bi bi-hospital text-primary me-1"></i><?= e($row['nama_posyandu']) ?>
                </td>
                <td class="text-muted"><?= e($row['alamat'] ?: '-') ?></td>
                <td class="text-center fw-semibold"><?= $tot ?></td>
                <td class="text-center">
                  <span class="badge bg-light text-dark border"><?= $diukur ?> (<?= $p_diukur ?>%)</span>
                </td>
                <td class="text-center text-success fw-bold"><?= (int)$row['kasus_normal'] ?></td>
                <td class="text-center text-danger fw-bold"><?= $stunt ?> (<?= $p_stunt ?>%)</td>
                <td class="text-center text-warning fw-bold"><?= (int)$row['kasus_gizi_kurang'] ?></td>
                <td class="text-center">
                  <span class="badge <?= $zone_class ?> px-2 py-1 d-inline-flex align-items-center gap-1">
                    <i class="bi <?= $zone_icon ?>"></i> <?= $zone_label ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Panel Rekomendasi Program & Anggaran Desa -->
  <section class="panel mt-3">
    <div class="panel-header">
      <h2 class="h5 mb-1 section-title">
        <i class="bi bi-lightbulb text-warning me-2"></i>Rekomendasi Rencana Program & Alokasi Desa
      </h2>
      <p class="text-muted small mb-0">Panduan koordinator desa untuk penyusunan prioritas intervensi gizi & dana desa</p>
    </div>
    <div class="row g-3">
      <div class="col-12 col-md-4">
        <div class="p-3 border rounded-3 bg-light-subtle h-100">
          <h3 class="h6 fw-bold text-primary mb-2"><i class="bi bi-egg-fried me-1"></i>Pemberian PMT Pemulihan</h3>
          <p class="small text-muted mb-0">Prioritaskan alokasi PMT berbasis pangan lokal kaya protein hewani untuk balita pada posyandu dengan zonasi merah.</p>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="p-3 border rounded-3 bg-light-subtle h-100">
          <h3 class="h6 fw-bold text-success mb-2"><i class="bi bi-droplet-half me-1"></i>Sanitasi & Air Bersih</h3>
          <p class="small text-muted mb-0">Pemeriksaan sarana jamban keluarga dan akses air minum aman pada keluarga balita dengan status stunting.</p>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="p-3 border rounded-3 bg-light-subtle h-100">
          <h3 class="h6 fw-bold text-warning mb-2"><i class="bi bi-megaphone me-1"></i>Penyuluhan & Sweeping Kader</h3>
          <p class="small text-muted mb-0">Koordinasikan kader posyandu untuk sweeping penimbangan ke rumah-rumah balita yang belum hadir bulan ini.</p>
        </div>
      </div>
    </div>
  </section>

<?php else: ?>
  <!-- ============================================================ -->
  <!-- 4. DASHBOARD ADMINISTRATOR SISTEM                            -->
  <!-- ============================================================ -->

  <!-- 4 Kartu Metrik Admin -->
  <section class="row g-3" aria-label="Metrik Administrator">
    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-primary">
        <div class="metric-top">
          <span class="metric-label">Total Pengguna Sistem</span>
          <span class="metric-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value"><?= number_format($admin_total_users) ?></div>
        <div class="metric-meta">
          <span class="text-success fw-bold"><?= number_format($admin_users_aktif) ?> Aktif</span>
          <span class="text-muted">&middot; <?= number_format($admin_users_nonaktif) ?> Nonaktif</span>
        </div>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-success">
        <div class="metric-top">
          <span class="metric-label">Unit Posyandu Terdaftar</span>
          <span class="metric-icon"><i class="bi bi-hospital-fill" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value text-success"><?= number_format($admin_total_posyandu) ?></div>
        <div class="metric-meta">
          <span class="text-muted">Titik layanan di <?= number_format($admin_total_wilayah) ?> Wilayah</span>
        </div>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-warning">
        <div class="metric-top">
          <span class="metric-label">Total Data Balita</span>
          <span class="metric-icon"><i class="bi bi-person-hearts" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value text-warning"><?= number_format($admin_total_balita) ?></div>
        <div class="metric-meta">
          <span class="text-muted">&amp; <?= number_format($admin_total_orangtua) ?> data orang tua</span>
        </div>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="metric-card metric-primary">
        <div class="metric-top">
          <span class="metric-label">Total Riwayat Pengukuran</span>
          <span class="metric-icon"><i class="bi bi-clipboard2-data-fill" aria-hidden="true"></i></span>
        </div>
        <div class="metric-value"><?= number_format($admin_total_pengukuran) ?></div>
        <div class="metric-meta">
          <span class="text-muted">Log data pemantauan gizi</span>
        </div>
      </article>
    </div>
  </section>

  <div class="row g-3 mt-1">
    <!-- Ringkasan Pengguna Berdasarkan Role -->
    <div class="col-12 col-lg-6">
      <div class="panel h-100">
        <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <h2 class="h5 mb-0 section-title">
              <i class="bi bi-shield-lock text-primary me-2"></i>Distribusi Pengguna per Peran (Role)
            </h2>
            <p class="text-muted small mb-0">Ringkasan hak akses pengguna aktif di sistem</p>
          </div>
          <a href="<?= base_url('modules/utilitas/pengguna/index.php') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-people me-1"></i>Kelola Pengguna
          </a>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle small mb-0">
            <thead class="table-light">
              <tr>
                <th>Peran / Role</th>
                <th class="text-center">Jumlah Akun</th>
                <th class="text-center">Akses Menu</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($admin_users_by_role as $r): 
                $badge_class = Otorisasi::getBadgeClass($r['nama_role']);
              ?>
                <tr>
                  <td>
                    <span class="badge <?= $badge_class ?> px-2 py-1"><?= e($r['nama_role']) ?></span>
                  </td>
                  <td class="text-center fw-bold fs-6"><?= (int)$r['total_user'] ?></td>
                  <td class="text-center">
                    <span class="badge bg-light text-secondary border">
                      <?= count(Otorisasi::getMenuIdsForRole((int)$r['id_role'])) ?> Menu
                    </span>
                  </td>
                  <td class="text-center">
                    <a href="<?= base_url('modules/utilitas/pengguna/index.php?role=' . (int)$r['id_role']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size: 0.76rem;">
                      Lihat Akun
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
          <span class="small text-muted">Total terdaftar: <strong><?= $admin_total_users ?> Pengguna</strong></span>
          <a href="<?= base_url('modules/utilitas/role/index.php') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-key me-1"></i>Pengaturan Otorisasi Role
          </a>
        </div>
      </div>
    </div>

    <!-- Status Sistem Secara Umum -->
    <div class="col-12 col-lg-6">
      <div class="panel h-100">
        <div class="panel-header">
          <h2 class="h5 mb-0 section-title">
            <i class="bi bi-hdd-network text-success me-2"></i>Status Sistem &amp; Server
          </h2>
          <p class="text-muted small mb-0">Informasi kesehatan infrastruktur aplikasi SiPantau Gizi</p>
        </div>

        <div class="list-group list-group-flush small">
          <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
            <span><i class="bi bi-database-check text-success me-2"></i>Status Database MySQL</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
              <i class="bi bi-check-circle-fill me-1"></i><?= e($system_status['db_status']) ?>
            </span>
          </div>
          <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
            <span><i class="bi bi-folder2-open text-primary me-2"></i>Nama Database Aktif</span>
            <span class="font-monospace text-dark fw-bold"><?= e($system_status['db_name']) ?></span>
          </div>
          <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
            <span><i class="bi bi-code-slash text-info me-2"></i>Versi PHP Runtime</span>
            <span class="badge bg-info-subtle text-info border"><?= e($system_status['php_ver']) ?></span>
          </div>
          <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
            <span><i class="bi bi-shield-lock text-primary me-2"></i>Modul Keamanan & Otorisasi</span>
            <span class="badge bg-primary-subtle text-primary border"><?= e($system_status['rbac_mode']) ?></span>
          </div>
          <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
            <span><i class="bi bi-hospital text-danger me-2"></i>Unit Puskesmas Pembina</span>
            <span class="fw-bold text-dark"><?= number_format($admin_total_puskesmas) ?> Puskesmas</span>
          </div>
          <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
            <span><i class="bi bi-geo text-secondary me-2"></i>Wilayah Cakupan</span>
            <span class="fw-bold text-dark"><?= number_format($admin_total_wilayah) ?> Kelurahan / Wilayah</span>
          </div>
        </div>

        <div class="alert alert-info py-2 px-3 small mt-3 mb-0">
          <i class="bi bi-shield-check me-1"></i>Sistem beroperasi optimal. Semua modul transaksi dan master data aktif.
        </div>
      </div>
    </div>
  </div>

  <!-- Transaksi Pengukuran Terkini (Activity Feed) -->
  <section class="panel mt-3">
    <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h2 class="h5 mb-0 section-title">
          <i class="bi bi-clock-history text-primary me-2"></i>Aktivitas Pengukuran Terbaru
        </h2>
        <p class="text-muted small mb-0">Log transaksi data penimbangan gizi yang baru masuk ke sistem</p>
      </div>
      <a href="<?= base_url('modules/pengukuran/index.php') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-box-arrow-up-right me-1"></i>Seluruh Pengukuran
      </a>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle small mb-0">
        <thead class="table-light">
          <tr>
            <th>Waktu &amp; Tanggal</th>
            <th>Nama Balita</th>
            <th>Posyandu</th>
            <th>Petugas Pencatat</th>
            <th>Hasil Penimbangan</th>
            <th>Status Gizi</th>
            <th class="text-center">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($admin_recent_measurements)): ?>
            <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada riwayat pengukuran tercatat.</td></tr>
          <?php else: ?>
            <?php foreach ($admin_recent_measurements as $m): ?>
              <tr>
                <td class="text-nowrap">
                  <div class="fw-semibold text-dark"><?= date('d/m/Y', strtotime($m['tanggal_pengukuran'])) ?></div>
                  <small class="text-muted"><?= !empty($m['created_at']) ? date('H:i', strtotime($m['created_at'])) . ' WIB' : '-' ?></small>
                </td>
                <td class="fw-bold text-dark"><?= e($m['nama_anak']) ?></td>
                <td><span class="badge bg-light text-dark border"><?= e($m['nama_posyandu']) ?></span></td>
                <td class="text-muted"><?= e($m['nama_petugas'] ?: 'Petugas / Kader') ?></td>
                <td>
                  <strong><?= number_format((float)$m['berat_badan'], 1) ?> kg</strong> &middot; 
                  <strong><?= number_format((float)$m['tinggi_badan'], 1) ?> cm</strong>
                </td>
                <td>
                  <span class="badge <?= in_array($m['status_tb_u'], ['Pendek', 'Sangat Pendek', 'Stunting']) ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-success-subtle text-success border border-success-subtle' ?>">
                    <?= e($m['status_tb_u'] ?: 'Normal') ?>
                  </span>
                </td>
                <td class="text-center">
                  <a href="<?= base_url('modules/pengukuran/detail.php?id=' . (int)$m['id_pengukuran']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size: 0.78rem;">
                    <i class="bi bi-eye"></i> Detail
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

<?php endif; ?>

<!-- Footer Layout -->
<?php include ROOT_PATH . '/includes/footer.php'; ?>
