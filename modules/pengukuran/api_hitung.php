<?php
/**
 * API Internal — Kalkulasi Status Antropometri Real-Time (JSON)
 * ===============================================================
 * Endpoint AJAX untuk menghitung status gizi dan Z-score secara interaktif saat form diisi.
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';
require_once ROOT_PATH . '/src/Antropometri.php';

$id_anak      = filter_input(INPUT_GET, 'id_anak', FILTER_VALIDATE_INT);
$tanggal_ukur = trim($_GET['tanggal_pengukuran'] ?? date('Y-m-d'));
$bb           = filter_input(INPUT_GET, 'bb', FILTER_VALIDATE_FLOAT);
$tb           = filter_input(INPUT_GET, 'tb', FILTER_VALIDATE_FLOAT);

if (!$id_anak || !$bb || !$tb || $bb <= 0 || $tb <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id_anak, nama, tanggal_lahir, jenis_kelamin FROM anak WHERE id_anak = ? LIMIT 1");
    $stmt->execute([$id_anak]);
    $anak = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$anak) {
        echo json_encode(['success' => false, 'message' => 'Data balita tidak ditemukan.']);
        exit;
    }

    $usia_bulan = Antropometri::hitungUsiaBulan($anak['tanggal_lahir'], $tanggal_ukur);
    $usia_teks  = Antropometri::formatUsia($usia_bulan);

    $hasil = Antropometri::kalkulasiStatusGizi((float)$bb, (float)$tb, $usia_bulan, $anak['jenis_kelamin']);

    echo json_encode([
        'success'        => true,
        'usia_bulan'     => $usia_bulan,
        'usia_teks'      => $usia_teks,
        'imt'            => $hasil['imt'],
        'zscore_bb_u'    => $hasil['zscore_bb_u'],
        'status_bb_u'    => $hasil['status_bb_u'],
        'badge_bb_u'     => Antropometri::getBadgeBBU($hasil['status_bb_u']),
        'zscore_tb_u'    => $hasil['zscore_tb_u'],
        'status_tb_u'    => $hasil['status_tb_u'],
        'badge_tb_u'     => Antropometri::getBadgeTBU($hasil['status_tb_u']),
        'zscore_bb_tb'   => $hasil['zscore_bb_tb'],
        'status_bb_tb'   => $hasil['status_bb_tb'],
        'badge_bb_tb'    => Antropometri::getBadgeBBTB($hasil['status_bb_tb']),
        'zscore_imt_u'   => $hasil['zscore_imt_u'],
        'status_imt_u'   => $hasil['status_imt_u'],
        'status_risiko'  => $hasil['status_risiko'],
        'badge_risiko'   => Antropometri::getBadgeRisiko($hasil['status_risiko']),
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
