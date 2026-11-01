<?php
/**
 * Engine Perhitungan Antropometri & Status Gizi Balita
 * Standar Baku: WHO Child Growth Standards & Permenkes RI No. 2 Tahun 2020
 * =========================================================================
 * Mengkalkulasi Z-Score untuk 4 indikator utama:
 * 1. Berat Badan menurut Umur (BB/U)       -> Deteksi Underweight / Gizi Kurang
 * 2. Tinggi Badan menurut Umur (TB/U)      -> Deteksi Stunting / Perawakan Pendek
 * 3. Berat Badan menurut Tinggi (BB/TB)   -> Deteksi Wasting / Gizi Buruk Akut
 * 4. Indeks Massa Tubuh menurut Umur (IMT/U) -> Deteksi Overweight / Obesitas
 */
class Antropometri
{
    /**
     * Hitung usia dalam bulan desimal secara presisi dari tanggal lahir hingga tanggal ukur.
     *
     * @param string $tanggal_lahir Format Y-m-d
     * @param string $tanggal_ukur  Format Y-m-d
     * @return float Usia dalam bulan
     */
    public static function hitungUsiaBulan(string $tanggal_lahir, string $tanggal_ukur): float
    {
        $d1 = new DateTime($tanggal_lahir);
        $d2 = new DateTime($tanggal_ukur);
        
        if ($d1 > $d2) {
            return 0.0;
        }

        $diff = $d1->diff($d2);
        $bulan = ($diff->y * 12) + $diff->m + ($diff->d / 30.4375);
        return round($bulan, 1);
    }

    /**
     * Format usia menjadi string deskriptif: "X Tahun Y Bulan" atau "X Bulan"
     * Menerima float usia_bulan ATAU sepasang tanggal lahir & tanggal ukur.
     *
     * @param float|string $usia_atau_tgl_lahir
     * @param string|null $tanggal_ukur
     * @return string
     */
    public static function formatUsia($usia_atau_tgl_lahir, ?string $tanggal_ukur = null): string
    {
        if ($tanggal_ukur !== null) {
            $usia_bulan = self::hitungUsiaBulan((string)$usia_atau_tgl_lahir, $tanggal_ukur);
        } else {
            $usia_bulan = (float)$usia_atau_tgl_lahir;
        }

        $total_bulan = (int)round($usia_bulan);
        $tahun = (int)floor($total_bulan / 12);
        $sisa_bulan = $total_bulan % 12;

        if ($tahun > 0) {
            return $sisa_bulan > 0 ? "{$tahun} Thn {$sisa_bulan} Bln" : "{$tahun} Tahun";
        }
        return "{$total_bulan} Bulan";
    }

    /**
     * Hitung Indeks Massa Tubuh (IMT / BMI).
     * Rumus: BB (kg) / ((TB (cm) / 100) ^ 2)
     *
     * @param float $bb_kg
     * @param float $tb_cm
     * @return float
     */
    public static function hitungIMT(float $bb_kg, float $tb_cm): float
    {
        if ($tb_cm <= 0) return 0.0;
        $tb_m = $tb_cm / 100.0;
        return round($bb_kg / ($tb_m * $tb_m), 2);
    }

    /**
     * Helper interpolasi linier antar milestone
     */
    private static function interpolate(float $x, array $table): array
    {
        $x = max(0.0, min($x, 60.0));
        $keys = array_keys($table);
        $n = count($keys);

        if ($x <= $keys[0]) {
            return $table[$keys[0]];
        }
        if ($x >= $keys[$n - 1]) {
            return $table[$keys[$n - 1]];
        }

        for ($i = 0; $i < $n - 1; $i++) {
            $x0 = (float)$keys[$i];
            $x1 = (float)$keys[$i + 1];
            if ($x >= $x0 && $x <= $x1) {
                $factor = ($x1 - $x0) > 0 ? ($x - $x0) / ($x1 - $x0) : 0.0;
                $p0 = $table[$keys[$i]];
                $p1 = $table[$keys[$i + 1]];
                return [
                    $p0[0] + $factor * ($p1[0] - $p0[0]), // median
                    $p0[1] + $factor * ($p1[1] - $p0[1]), // sd
                ];
            }
        }

        return $table[$keys[0]];
    }

    /**
     * Tabel referensi median & estimasi SD baku WHO/Kemenkes RI No. 2/2020
     * Berdasarkan usia (bulan) dan jenis kelamin (L = Laki-laki, P = Perempuan)
     */
    private static function getReferensiAntropometri(float $usia_bulan, string $gender): array
    {
        $is_male = (strtoupper($gender) === 'L');

        // Milestone WHO Child Growth Standards 0-60 bulan [Median, SD]
        if ($is_male) {
            $tb_bb = [
                0  => [3.3, 0.48],
                3  => [6.4, 0.72],
                6  => [7.9, 0.84],
                9  => [8.9, 0.94],
                12 => [9.6, 1.04],
                18 => [10.9, 1.15],
                24 => [12.2, 1.25],
                30 => [13.3, 1.35],
                36 => [14.3, 1.45],
                42 => [15.3, 1.55],
                48 => [16.3, 1.65],
                54 => [17.3, 1.75],
                60 => [18.3, 1.85],
            ];

            $tb_tb = [
                0  => [49.9, 1.9],
                3  => [61.4, 2.0],
                6  => [67.6, 2.1],
                9  => [72.0, 2.2],
                12 => [75.7, 2.3],
                18 => [82.3, 2.5],
                24 => [87.8, 2.7],
                30 => [91.9, 2.9],
                36 => [96.1, 3.1],
                42 => [99.9, 3.3],
                48 => [103.3, 3.5],
                54 => [106.7, 3.7],
                60 => [110.0, 3.9],
            ];

            $tb_imt = [
                0  => [13.4, 1.2],
                6  => [17.2, 1.3],
                12 => [16.8, 1.2],
                24 => [16.0, 1.1],
                36 => [15.6, 1.1],
                48 => [15.3, 1.1],
                60 => [15.2, 1.2],
            ];
        } else {
            $tb_bb = [
                0  => [3.2, 0.44],
                3  => [5.8, 0.66],
                6  => [7.3, 0.78],
                9  => [8.2, 0.88],
                12 => [8.9, 0.98],
                18 => [10.2, 1.10],
                24 => [11.5, 1.20],
                30 => [12.7, 1.30],
                36 => [13.9, 1.40],
                42 => [15.0, 1.50],
                48 => [16.1, 1.60],
                54 => [17.2, 1.70],
                60 => [18.2, 1.80],
            ];

            $tb_tb = [
                0  => [49.1, 1.9],
                3  => [59.8, 2.0],
                6  => [65.7, 2.1],
                9  => [70.1, 2.2],
                12 => [74.0, 2.3],
                18 => [80.7, 2.5],
                24 => [86.4, 2.7],
                30 => [90.7, 2.9],
                36 => [95.1, 3.1],
                42 => [99.0, 3.3],
                48 => [102.7, 3.5],
                54 => [106.2, 3.7],
                60 => [109.4, 3.9],
            ];

            $tb_imt = [
                0  => [13.3, 1.2],
                6  => [16.8, 1.3],
                12 => [16.4, 1.2],
                24 => [15.7, 1.1],
                36 => [15.3, 1.1],
                48 => [15.1, 1.1],
                60 => [15.0, 1.2],
            ];
        }

        $res_bb  = self::interpolate($usia_bulan, $tb_bb);
        $res_tb  = self::interpolate($usia_bulan, $tb_tb);
        $res_imt = self::interpolate($usia_bulan, $tb_imt);

        return [
            'median_bb'  => $res_bb[0],
            'sd_bb'      => $res_bb[1],
            'median_tb'  => $res_tb[0],
            'sd_tb'      => $res_tb[1],
            'median_imt' => $res_imt[0],
            'sd_imt'     => $res_imt[1],
        ];
    }

    /**
     * Hitung seluruh Z-Score dan tentukan status gizi berdasarkan Permenkes No. 2 Tahun 2020.
     *
     * @param float $bb_kg
     * @param float $tb_cm
     * @param float $usia_bulan
     * @param string $gender 'L' atau 'P'
     * @return array
     */
    public static function kalkulasiStatusGizi(float $bb_kg, float $tb_cm, float $usia_bulan, string $gender): array
    {
        $ref = self::getReferensiAntropometri($usia_bulan, $gender);
        $imt = self::hitungIMT($bb_kg, $tb_cm);

        // 1. Z-Score BB/U (Berat Badan menurut Umur)
        $z_bb_u = round(($bb_kg - $ref['median_bb']) / $ref['sd_bb'], 2);
        if ($z_bb_u < -3.0) {
            $status_bb_u = 'Gizi Buruk';
        } elseif ($z_bb_u < -2.0) {
            $status_bb_u = 'Gizi Kurang';
        } elseif ($z_bb_u <= 1.0) {
            $status_bb_u = 'Gizi Baik';
        } else {
            $status_bb_u = 'Risiko Gizi Lebih';
        }

        // 2. Z-Score TB/U (Tinggi Badan menurut Umur) — INDIKATOR UTAMA STUNTING
        $z_tb_u = round(($tb_cm - $ref['median_tb']) / $ref['sd_tb'], 2);
        if ($z_tb_u < -3.0) {
            $status_tb_u = 'Sangat Pendek';
        } elseif ($z_tb_u < -2.0) {
            $status_tb_u = 'Pendek'; // Stunting
        } elseif ($z_tb_u <= 3.0) {
            $status_tb_u = 'Normal';
        } else {
            $status_tb_u = 'Tinggi';
        }

        // 3. Z-Score BB/TB (Berat Badan menurut Tinggi Badan) — INDIKATOR WASTING
        // Menggunakan rasio allometrik berat badan terhadap tinggi badan (BB ~ c * TB^2.3)
        $tb_ratio = $tb_cm / max($ref['median_tb'], 1.0);
        $expected_bb = $ref['median_bb'] * pow($tb_ratio, 2.3);
        $sd_bb_tb = max($ref['sd_bb'], 0.6);
        $z_bb_tb = round(($bb_kg - $expected_bb) / $sd_bb_tb, 2);

        if ($z_bb_tb < -3.0) {
            $status_bb_tb = 'Gizi Buruk';
        } elseif ($z_bb_tb < -2.0) {
            $status_bb_tb = 'Gizi Kurang';
        } elseif ($z_bb_tb <= 1.0) {
            $status_bb_tb = 'Gizi Baik';
        } elseif ($z_bb_tb <= 2.0) {
            $status_bb_tb = 'Berisiko Gizi Lebih';
        } elseif ($z_bb_tb <= 3.0) {
            $status_bb_tb = 'Gizi Lebih';
        } else {
            $status_bb_tb = 'Obesitas';
        }

        // 4. Z-Score IMT/U (Indeks Massa Tubuh menurut Umur)
        $z_imt_u = round(($imt - $ref['median_imt']) / $ref['sd_imt'], 2);
        if ($z_imt_u < -3.0) {
            $status_imt_u = 'Gizi Buruk';
        } elseif ($z_imt_u < -2.0) {
            $status_imt_u = 'Gizi Kurang';
        } elseif ($z_imt_u <= 1.0) {
            $status_imt_u = 'Gizi Baik';
        } elseif ($z_imt_u <= 2.0) {
            $status_imt_u = 'Berisiko Gizi Lebih';
        } elseif ($z_imt_u <= 3.0) {
            $status_imt_u = 'Gizi Lebih';
        } else {
            $status_imt_u = 'Obesitas';
        }

        // 5. Penilaian Status Risiko / Tindak Lanjut
        if (in_array($status_tb_u, ['Sangat Pendek', 'Pendek'], true) || 
            in_array($status_bb_u, ['Gizi Buruk'], true) || 
            in_array($status_bb_tb, ['Gizi Buruk'], true)) {
            $status_risiko = 'Butuh Rujukan Segera';
        } elseif (in_array($status_bb_u, ['Gizi Kurang', 'Risiko Gizi Lebih'], true) || 
                  in_array($status_bb_tb, ['Gizi Kurang', 'Berisiko Gizi Lebih', 'Gizi Lebih', 'Obesitas'], true)) {
            $status_risiko = 'Berisiko';
        } else {
            $status_risiko = 'Normal';
        }

        return [
            'zscore_bb_u'   => $z_bb_u,
            'zscore_tb_u'   => $z_tb_u,
            'zscore_bb_tb'  => $z_bb_tb,
            'zscore_imt_u'  => $z_imt_u,
            'status_bb_u'   => $status_bb_u,
            'status_tb_u'   => $status_tb_u,
            'status_bb_tb'  => $status_bb_tb,
            'status_imt_u'  => $status_imt_u,
            'status_risiko' => $status_risiko,
            'imt'           => $imt,
        ];
    }

    /**
     * Helper CSS Badge untuk TB/U (Stunting)
     */
    public static function getBadgeTBU(?string $status): string
    {
        switch ($status) {
            case 'Normal':
                return 'bg-success-subtle text-success border border-success-subtle';
            case 'Pendek':
                return 'bg-warning-subtle text-warning border border-warning-subtle';
            case 'Sangat Pendek':
                return 'bg-danger-subtle text-danger border border-danger-subtle';
            case 'Tinggi':
                return 'bg-info-subtle text-info border border-info-subtle';
            default:
                return 'bg-secondary-subtle text-secondary border border-secondary-subtle';
        }
    }

    /**
     * Helper CSS Badge untuk BB/U
     */
    public static function getBadgeBBU(?string $status): string
    {
        switch ($status) {
            case 'Gizi Baik':
                return 'bg-success-subtle text-success border border-success-subtle';
            case 'Gizi Kurang':
                return 'bg-warning-subtle text-warning border border-warning-subtle';
            case 'Gizi Buruk':
                return 'bg-danger-subtle text-danger border border-danger-subtle';
            case 'Risiko Gizi Lebih':
                return 'bg-info-subtle text-info border border-info-subtle';
            default:
                return 'bg-secondary-subtle text-secondary border border-secondary-subtle';
        }
    }

    /**
     * Helper CSS Badge untuk BB/TB (Wasting)
     */
    public static function getBadgeBBTB(?string $status): string
    {
        switch ($status) {
            case 'Gizi Baik':
                return 'bg-success-subtle text-success border border-success-subtle';
            case 'Berisiko Gizi Lebih':
            case 'Gizi Lebih':
                return 'bg-warning-subtle text-warning border border-warning-subtle';
            case 'Gizi Kurang':
                return 'bg-warning-subtle text-warning border border-warning-subtle';
            case 'Gizi Buruk':
            case 'Obesitas':
                return 'bg-danger-subtle text-danger border border-danger-subtle';
            default:
                return 'bg-secondary-subtle text-secondary border border-secondary-subtle';
        }
    }

    /**
     * Helper CSS Badge untuk Status Risiko
     */
    public static function getBadgeRisiko(?string $status): string
    {
        switch ($status) {
            case 'Normal':
                return 'bg-success text-white';
            case 'Berisiko':
                return 'bg-warning text-dark';
            case 'Butuh Rujukan Segera':
                return 'bg-danger text-white';
            default:
                return 'bg-secondary text-white';
        }
    }
}
