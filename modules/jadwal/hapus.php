<?php
/**
 * Modul Jadwal & Kunjungan — Hapus Data Jadwal Posyandu
 * =======================================================
 * Menghapus agenda jadwal kegiatan beserta riwayat presensi terkait.
 * Menerima request via POST dengan proteksi CSRF.
 */

$current_slug = 'jadwal';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';

// Pastikan memiliki hak akses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'jadwal')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk menghapus jadwal posyandu.');
    redirect(base_url('modules/jadwal/index.php'));
}

// Hanya menerima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Metode request tidak diizinkan.');
    redirect(base_url('modules/jadwal/index.php'));
}

// Verifikasi token CSRF
if (!verify_csrf_token()) {
    set_flash('danger', 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan coba kembali.');
    redirect(base_url('modules/jadwal/index.php'));
}

$id_jadwal = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id_jadwal) {
    set_flash('danger', 'ID Jadwal tidak valid.');
    redirect(base_url('modules/jadwal/index.php'));
}

try {
    if ($pdo === null) {
        throw new Exception('Koneksi database tidak tersedia.');
    }

    // Ambil info jadwal untuk pesan flash
    $stmtCek = $pdo->prepare("
        SELECT j.id_jadwal, j.tanggal, pos.nama_posyandu
        FROM jadwal_posyandu j
        JOIN posyandu pos ON j.id_posyandu = pos.id_posyandu
        WHERE j.id_jadwal = ?
        LIMIT 1
    ");
    $stmtCek->execute([$id_jadwal]);
    $data = $stmtCek->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        set_flash('warning', 'Data jadwal tidak ditemukan atau sudah dihapus sebelumnya.');
        redirect(base_url('modules/jadwal/index.php'));
    }

    // Eksekusi penghapusan dalam transaksi
    $pdo->beginTransaction();

    // 1. Hapus catatan kehadiran terkait
    $stmtDelHadir = $pdo->prepare("DELETE FROM kehadiran WHERE id_jadwal = ?");
    $stmtDelHadir->execute([$id_jadwal]);

    // 2. Hapus data jadwal
    $stmtDelJadwal = $pdo->prepare("DELETE FROM jadwal_posyandu WHERE id_jadwal = ?");
    $stmtDelJadwal->execute([$id_jadwal]);

    $pdo->commit();

    $tgl_fmt = date('d/m/Y', strtotime($data['tanggal']));
    set_flash('success', "Jadwal kegiatan untuk <strong>" . e($data['nama_posyandu']) . "</strong> pada tanggal <strong>{$tgl_fmt}</strong> berhasil dihapus.");

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('danger', 'Gagal menghapus jadwal posyandu: ' . $e->getMessage());
}

redirect(base_url('modules/jadwal/index.php'));
