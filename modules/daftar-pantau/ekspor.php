<?php
/**
 * Modul Daftar Pantau — Ekspor Data & Cetak Laporan PDF
 * =========================================================================
 * 1. Ekspor data anak berisiko ke format standar Microsoft Excel (.xlsx murni)
 *    dengan styling profesional: banner judul, warna header elegan, zebra striping,
 *    pewarnaan status otomatis, auto-fit lebar kolom, dan freeze header pane.
 * 2. Ekspor ke format CSV (UTF-8 BOM).
 * 3. Tampilan cetak resmi (Print View / Save to PDF) dengan kop surat dan kolom pengesahan.
 */

$current_slug = 'daftar-pantau';
require_once __DIR__ . '/../../config/app.php';
require_once ROOT_PATH . '/includes/auth-guard.php';
require_once ROOT_PATH . '/src/Antropometri.php';

// Pastikan memiliki hak akses
$user_role_id = (int)($_SESSION['role_id'] ?? 0);
if (!bolehAkses($user_role_id, 'daftar-pantau')) {
    set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk mengekspor data daftar pantau.');
    redirect(base_url('modules/daftar-pantau/index.php'));
}

// Parameter Filter & Format
$format          = strtolower(trim($_GET['format'] ?? 'xlsx'));
$search          = trim($_GET['search'] ?? '');
$filter_posyandu = trim($_GET['posyandu'] ?? '');
$filter_status   = trim($_GET['status'] ?? '');
$filter_risiko   = trim($_GET['risiko'] ?? '');

// Susun Query Filter
$where_clauses = ['1=1'];
$params        = [];

if ($search !== '') {
    $where_clauses[] = '(a.nama LIKE ? OR a.nik LIKE ? OR ot.nama_ibu LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($filter_posyandu !== '') {
    $where_clauses[] = 'a.id_posyandu = ?';
    $params[] = $filter_posyandu;
}

if ($filter_status !== '') {
    if ($filter_status === 'Baru') {
        $where_clauses[] = "(pm.status_tindak_lanjut = 'Baru' OR pm.status_tindak_lanjut IS NULL)";
    } else {
        $where_clauses[] = 'pm.status_tindak_lanjut = ?';
        $params[] = $filter_status;
    }
}

if ($filter_risiko !== '') {
    if ($filter_risiko === 'stunting') {
        $where_clauses[] = "sg.status_tb_u IN ('Sangat Pendek', 'Pendek')";
    } elseif ($filter_risiko === 'wasting') {
        $where_clauses[] = "sg.status_bb_tb IN ('Gizi Buruk', 'Gizi Kurang')";
    } elseif ($filter_risiko === 'underweight') {
        $where_clauses[] = "sg.status_bb_u IN ('Gizi Buruk', 'Gizi Kurang')";
    } else {
        $where_clauses[] = 'sg.status_risiko = ?';
        $params[] = $filter_risiko;
    }
}

$where_sql = implode(' AND ', $where_clauses);
$data = [];

try {
    if ($pdo !== null) {
        $sql = "
            SELECT 
                pm.id_pemantauan,
                pm.id_anak,
                pm.id_pengukuran,
                pm.tanggal_mulai,
                COALESCE(pm.status_tindak_lanjut, 'Baru') AS status_tindak_lanjut,
                pm.tanggal_tindak_lanjut,
                pm.keterangan AS catatan_sistem,
                a.nama AS nama_anak,
                a.nik,
                a.tanggal_lahir,
                a.jenis_kelamin,
                ot.nama_ibu,
                ot.nama_ayah,
                ot.no_telepon,
                ot.alamat AS alamat_orangtua,
                pos.nama_posyandu,
                pos.dusun_rw,
                p.tanggal_pengukuran,
                p.berat_badan,
                p.tinggi_badan,
                p.lingkar_kepala,
                sg.zscore_bb_u,
                sg.zscore_tb_u,
                sg.zscore_bb_tb,
                sg.zscore_imt_u,
                sg.status_bb_u,
                sg.status_tb_u,
                sg.status_bb_tb,
                sg.status_imt_u,
                sg.status_risiko,
                (SELECT COUNT(*) FROM catatan_intervensi ci WHERE ci.id_pemantauan = pm.id_pemantauan) AS total_intervensi,
                (SELECT ci_sub.jenis_intervensi FROM catatan_intervensi ci_sub WHERE ci_sub.id_pemantauan = pm.id_pemantauan ORDER BY ci_sub.id_intervensi DESC LIMIT 1) AS jenis_terakhir,
                (SELECT ci_sub.tanggal FROM catatan_intervensi ci_sub WHERE ci_sub.id_pemantauan = pm.id_pemantauan ORDER BY ci_sub.id_intervensi DESC LIMIT 1) AS tanggal_terakhir,
                (SELECT ci_sub.catatan FROM catatan_intervensi ci_sub WHERE ci_sub.id_pemantauan = pm.id_pemantauan ORDER BY ci_sub.id_intervensi DESC LIMIT 1) AS catatan_terakhir,
                (SELECT u.nama FROM catatan_intervensi ci_sub JOIN user u ON ci_sub.id_user = u.id_user WHERE ci_sub.id_pemantauan = pm.id_pemantauan ORDER BY ci_sub.id_intervensi DESC LIMIT 1) AS petugas_terakhir
            FROM pemantauan pm
            JOIN anak a ON pm.id_anak = a.id_anak
            JOIN orangtua ot ON a.id_orangtua = ot.id_orangtua
            JOIN posyandu pos ON a.id_posyandu = pos.id_posyandu
            JOIN pengukuran p ON pm.id_pengukuran = p.id_pengukuran
            JOIN status_gizi sg ON p.id_pengukuran = sg.id_pengukuran
            WHERE {$where_sql}
            ORDER BY 
                CASE COALESCE(pm.status_tindak_lanjut, 'Baru')
                    WHEN 'Baru' THEN 1
                    WHEN 'Ditindaklanjuti' THEN 2
                    WHEN 'Selesai' THEN 3
                    ELSE 4
                END,
                pm.tanggal_mulai DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die('Terjadi kesalahan saat memuat data: ' . $e->getMessage());
}

// Ambil info nama posyandu jika difilter
$posyandu_label = 'Semua Posyandu';
if (!empty($filter_posyandu)) {
    foreach ($data as $d) {
        if (!empty($d['nama_posyandu'])) {
            $posyandu_label = $d['nama_posyandu'];
            break;
        }
    }
}

// Susun array kolom header
$export_headers = [
    'NO',
    'NIK ANAK',
    'NAMA BALITA',
    'JK',
    'TANGGAL LAHIR',
    'USIA',
    'NAMA IBU',
    'POSYANDU',
    'DUSUN / RW',
    'TGL UKUR',
    'BB (KG)',
    'TB (CM)',
    'Z-SCORE BB/U',
    'Z-SCORE TB/U',
    'Z-SCORE BB/TB',
    'STATUS BB/U',
    'STATUS STUNTING (TB/U)',
    'STATUS WASTING (BB/TB)',
    'TINGKAT RISIKO',
    'STATUS TINDAK LANJUT',
    'TGL UPDATE STATUS',
    'TOTAL INTERVENSI',
    'INTERVENSI TERAKHIR',
    'TGL INTERVENSI TERAKHIR',
    'PETUGAS PELAKSANA',
    'CATATAN TINDAK LANJUT',
];

// ============================================================
// FORMAT 1: EKSPOR EXCEL PROFESIONAL (.xlsx MURNI)
// ============================================================
if ($format === 'xlsx') {
    $filename = 'daftar-pantau-anak-berisiko-' . date('Y-m-d') . '.xlsx';

    $zip = new ZipArchive();
    $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_neat_');

    if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 4. xl/workbook.xml
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="Daftar Pantau Balita" sheetId="1" r:id="rId1"/></sheets>' .
            '</workbook>';
        $zip->addFromString('xl/workbook.xml', $wb);

        // 5. xl/styles.xml (Font, Warna, Border, Alignment Lengkap)
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<fonts count="8">' .
            '<font><name val="Calibri"/><sz val="10"/><color rgb="FF1E293B"/></font>' . // 0: Normal Dark Gray
            '<font><b/><name val="Calibri"/><sz val="10"/><color rgb="FFFFFFFF"/></font>' . // 1: Header White Bold
            '<font><b/><name val="Calibri"/><sz val="13"/><color rgb="FF1E40AF"/></font>' . // 2: Title Big Navy
            '<font><i/><name val="Calibri"/><sz val="9.5"/><color rgb="FF64748B"/></font>' . // 3: Subtitle Muted Gray
            '<font><b/><name val="Calibri"/><sz val="10"/><color rgb="FF0F172A"/></font>' . // 4: Summary Bold
            '<font><b/><name val="Calibri"/><sz val="9.5"/><color rgb="FF991B1B"/></font>' . // 5: Red Bold
            '<font><b/><name val="Calibri"/><sz val="9.5"/><color rgb="FF92400E"/></font>' . // 6: Yellow Bold
            '<font><b/><name val="Calibri"/><sz val="9.5"/><color rgb="FF166534"/></font>' . // 7: Green Bold
            '</fonts>' .
            '<fills count="8">' .
            '<fill><patternFill patternType="none"/></fill>' .
            '<fill><patternFill patternType="gray125"/></fill>' .
            '<fill><patternFill patternType="solid"><fgColor rgb="FF1E40AF"/></patternFill></fill>' . // 2: Header Navy Blue
            '<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>' . // 3: Zebra Light Slate
            '<fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>' . // 4: Soft Red (Rujukan/Baru)
            '<fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill>' . // 5: Soft Yellow (Berisiko/Tindak)
            '<fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/></patternFill></fill>' . // 6: Soft Green (Selesai)
            '<fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/></patternFill></fill>' . // 7: Summary Gray
            '</fills>' .
            '<borders count="3">' .
            '<border><left/><right/><top/><bottom/></border>' . // 0: None
            '<border>' . // 1: Thin Gray Borders
            '<left style="thin"><color rgb="FFCBD5E1"/></left>' .
            '<right style="thin"><color rgb="FFCBD5E1"/></right>' .
            '<top style="thin"><color rgb="FFCBD5E1"/></top>' .
            '<bottom style="thin"><color rgb="FFCBD5E1"/></bottom>' .
            '</border>' .
            '<border>' . // 2: Summary Row (Top thin, Bottom double)
            '<left style="thin"><color rgb="FFCBD5E1"/></left>' .
            '<right style="thin"><color rgb="FFCBD5E1"/></right>' .
            '<top style="thin"><color rgb="FF94A3B8"/></top>' .
            '<bottom style="double"><color rgb="FF334155"/></bottom>' .
            '</border>' .
            '</borders>' .
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
            '<cellXfs count="14">' .
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>' . // 0: Normal Left
            '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' . // 1: Header
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . // 2: Normal Center
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' . // 3: Normal Right
            '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>' . // 4: Zebra Left
            '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . // 5: Zebra Center
            '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' . // 6: Zebra Right
            '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>' . // 7: Title
            '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>' . // 8: Subtitle
            '<xf numFmtId="0" fontId="5" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . // 9: Soft Red Badge
            '<xf numFmtId="0" fontId="6" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . // 10: Soft Yellow Badge
            '<xf numFmtId="0" fontId="7" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . // 11: Soft Green Badge
            '<xf numFmtId="0" fontId="4" fillId="7" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' . // 12: Summary Label
            '<xf numFmtId="0" fontId="4" fillId="7" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . // 13: Summary Value
            '</cellXfs>' .
            '</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // 6. xl/worksheets/sheet1.xml
        $colLetter = function($n) {
            $r = '';
            while ($n >= 0) {
                $r = chr($n % 26 + 65) . $r;
                $n = intval($n / 26) - 1;
            }
            return $r;
        };

        // Lebar kolom rapi
        $colWidths = [
            1 => 6,   // NO
            2 => 20,  // NIK
            3 => 26,  // NAMA BALITA
            4 => 8,   // JK
            5 => 14,  // TGL LAHIR
            6 => 12,  // USIA
            7 => 22,  // IBU
            8 => 22,  // POSYANDU
            9 => 14,  // DUSUN
            10 => 14, // TGL UKUR
            11 => 12, // BB (KG)
            12 => 12, // TB (CM)
            13 => 14, // Z-SCORE BB/U
            14 => 14, // Z-SCORE TB/U
            15 => 14, // Z-SCORE BB/TB
            16 => 18, // STATUS BB/U
            17 => 22, // STATUS TB/U
            18 => 20, // STATUS BB/TB
            19 => 24, // TINGKAT RISIKO
            20 => 22, // STATUS TL
            21 => 16, // TGL UPDATE
            22 => 16, // TOTAL INTERVENSI
            23 => 32, // JENIS TERAKHIR
            24 => 16, // TGL TERAKHIR
            25 => 22, // PETUGAS
            26 => 45  // CATATAN
        ];

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' .
            '<cols>';
        foreach ($colWidths as $colIdx => $w) {
            $sheetXml .= '<col min="' . $colIdx . '" max="' . $colIdx . '" width="' . $w . '" customWidth="1"/>';
        }
        $sheetXml .= '</cols><sheetData>';

        // Row 1: Judul Utama Laporan
        $sheetXml .= '<row r="1" ht="26" customHeight="1">';
        $sheetXml .= '<c r="A1" t="inlineStr" s="7"><is><t>SISTEM INFORMASI PEMANTAUAN GIZI (SIPANTAU GIZI) — DAFTAR PANTAU ANAK BERISIKO</t></is></c>';
        $sheetXml .= '</row>';

        // Row 2: Subtitle Metadata Filter
        $subtitleText = 'Lokasi: ' . $posyandu_label . ' | Status: ' . ($filter_status ?: 'Semua Status') . ' | Dicetak: ' . date('d F Y, H:i') . ' WIB | Petugas: ' . ($_SESSION['user_nama'] ?? 'Petugas Gizi');
        $sheetXml .= '<row r="2" ht="18" customHeight="1">';
        $sheetXml .= '<c r="A2" t="inlineStr" s="8"><is><t>' . htmlspecialchars($subtitleText, ENT_XML1, 'UTF-8') . '</t></is></c>';
        $sheetXml .= '</row>';

        // Row 3: Baris Kosong Pemisah
        $sheetXml .= '<row r="3" ht="8" customHeight="1"></row>';

        // Row 4: Header Kolom Tabel (Navy Blue, White Text, Bold)
        $sheetXml .= '<row r="4" ht="30" customHeight="1">';
        foreach ($export_headers as $colIdx => $hText) {
            $cRef = $colLetter($colIdx) . '4';
            $sheetXml .= '<c r="' . $cRef . '" t="inlineStr" s="1"><is><t>' . htmlspecialchars($hText, ENT_XML1, 'UTF-8') . '</t></is></c>';
        }
        $sheetXml .= '</row>';

        // Baris Data (Row 5 dst)
        $rowNum = 5;
        $countBaru   = 0;
        $countTindak = 0;
        $countSelesai = 0;

        foreach ($data as $idx => $r) {
            $isZebra = ($idx % 2 === 1);
            $baseLeft   = $isZebra ? 4 : 0;
            $baseCenter = $isZebra ? 5 : 2;
            $baseRight  = $isZebra ? 6 : 3;

            $usia_bulan = Antropometri::hitungUsiaBulan($r['tanggal_lahir'], $r['tanggal_pengukuran']);
            $status_tl  = $r['status_tindak_lanjut'];
            $risiko     = $r['status_risiko'];

            if ($status_tl === 'Baru') $countBaru++;
            elseif ($status_tl === 'Ditindaklanjuti') $countTindak++;
            elseif ($status_tl === 'Selesai') $countSelesai++;

            // Style Badge Status Risiko
            $styleRisiko = $baseCenter;
            if ($risiko === 'Butuh Rujukan Segera') {
                $styleRisiko = 9; // Soft Red
            } elseif ($risiko === 'Berisiko') {
                $styleRisiko = 10; // Soft Yellow
            }

            // Style Badge Status Tindak Lanjut
            $styleTL = $baseCenter;
            if ($status_tl === 'Baru') {
                $styleTL = 9; // Soft Red
            } elseif ($status_tl === 'Ditindaklanjuti') {
                $styleTL = 10; // Soft Yellow
            } elseif ($status_tl === 'Selesai') {
                $styleTL = 11; // Soft Green
            }

            // Format tanggal Indonesia d/m/Y
            $tglLahir  = !empty($r['tanggal_lahir']) ? date('d/m/Y', strtotime($r['tanggal_lahir'])) : '-';
            $tglUkur   = !empty($r['tanggal_pengukuran']) ? date('d/m/Y', strtotime($r['tanggal_pengukuran'])) : '-';
            $tglUpdate = !empty($r['tanggal_tindak_lanjut']) ? date('d/m/Y', strtotime($r['tanggal_tindak_lanjut'])) : '-';
            $tglInterv = !empty($r['tanggal_terakhir']) ? date('d/m/Y', strtotime($r['tanggal_terakhir'])) : '-';

            // Z-score dengan format tanda + jika positif
            $formatZ = function($val) {
                if ($val === null || $val === '') return '-';
                $num = (float)$val;
                return ($num > 0 ? '+' : '') . number_format($num, 2);
            };

            $cells = [
                0  => ['v' => ($idx + 1), 's' => $baseCenter, 't' => 'n'],
                1  => ['v' => $r['nik'], 's' => $baseCenter, 't' => 's'],
                2  => ['v' => $r['nama_anak'], 's' => $baseLeft, 't' => 's'],
                3  => ['v' => ($r['jenis_kelamin'] === 'L' ? 'L' : 'P'), 's' => $baseCenter, 't' => 's'],
                4  => ['v' => $tglLahir, 's' => $baseCenter, 't' => 's'],
                5  => ['v' => $usia_bulan . ' bln', 's' => $baseCenter, 't' => 's'],
                6  => ['v' => $r['nama_ibu'] ?: '-', 's' => $baseLeft, 't' => 's'],
                7  => ['v' => $r['nama_posyandu'] ?: '-', 's' => $baseLeft, 't' => 's'],
                8  => ['v' => $r['dusun_rw'] ?: '-', 's' => $baseCenter, 't' => 's'],
                9  => ['v' => $tglUkur, 's' => $baseCenter, 't' => 's'],
                10 => ['v' => number_format((float)$r['berat_badan'], 1), 's' => $baseRight, 't' => 's'],
                11 => ['v' => number_format((float)$r['tinggi_badan'], 1), 's' => $baseRight, 't' => 's'],
                12 => ['v' => $formatZ($r['zscore_bb_u']), 's' => $baseCenter, 't' => 's'],
                13 => ['v' => $formatZ($r['zscore_tb_u']), 's' => $baseCenter, 't' => 's'],
                14 => ['v' => $formatZ($r['zscore_bb_tb']), 's' => $baseCenter, 't' => 's'],
                15 => ['v' => $r['status_bb_u'] ?: '-', 's' => $baseCenter, 't' => 's'],
                16 => ['v' => $r['status_tb_u'] ?: '-', 's' => $baseCenter, 't' => 's'],
                17 => ['v' => $r['status_bb_tb'] ?: '-', 's' => $baseCenter, 't' => 's'],
                18 => ['v' => $r['status_risiko'] ?: '-', 's' => $styleRisiko, 't' => 's'],
                19 => ['v' => $r['status_tindak_lanjut'] ?: '-', 's' => $styleTL, 't' => 's'],
                20 => ['v' => $tglUpdate, 's' => $baseCenter, 't' => 's'],
                21 => ['v' => (int)$r['total_intervensi'], 's' => $baseCenter, 't' => 'n'],
                22 => ['v' => $r['jenis_terakhir'] ?: '-', 's' => $baseLeft, 't' => 's'],
                23 => ['v' => $tglInterv, 's' => $baseCenter, 't' => 's'],
                24 => ['v' => $r['petugas_terakhir'] ?: '-', 's' => $baseLeft, 't' => 's'],
                25 => ['v' => str_replace(["\r", "\n"], ' ', $r['catatan_terakhir'] ?: '-'), 's' => $baseLeft, 't' => 's'],
            ];

            $sheetXml .= '<row r="' . $rowNum . '" ht="21" customHeight="1">';
            foreach ($cells as $colIdx => $c) {
                $cRef = $colLetter($colIdx) . $rowNum;
                if ($c['t'] === 'n') {
                    $sheetXml .= '<c r="' . $cRef . '" t="n" s="' . $c['s'] . '"><v>' . $c['v'] . '</v></c>';
                } else {
                    $sheetXml .= '<c r="' . $cRef . '" t="inlineStr" s="' . $c['s'] . '"><is><t>' . htmlspecialchars((string)$c['v'], ENT_XML1, 'UTF-8') . '</t></is></c>';
                }
            }
            $sheetXml .= '</row>';
            $rowNum++;
        }

        // Baris Ringkasan Footer (Summary Row)
        $sheetXml .= '<row r="' . $rowNum . '" ht="24" customHeight="1">';
        for ($colIdx = 0; $colIdx < 26; $colIdx++) {
            $cRef = $colLetter($colIdx) . $rowNum;
            if ($colIdx === 2) {
                $sheetXml .= '<c r="' . $cRef . '" t="inlineStr" s="12"><is><t>TOTAL: ' . count($data) . ' ANAK BERISIKO</t></is></c>';
            } elseif ($colIdx === 19) {
                $summaryStatus = $countBaru . ' Baru | ' . $countTindak . ' Ditindaklanjuti | ' . $countSelesai . ' Selesai';
                $sheetXml .= '<c r="' . $cRef . '" t="inlineStr" s="13"><is><t>' . $summaryStatus . '</t></is></c>';
            } else {
                $sheetXml .= '<c r="' . $cRef . '" s="12"/>';
            }
        }
        $sheetXml .= '</row>';

        $sheetXml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        // Download Response ke Browser
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tempFile));
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        readfile($tempFile);
        @unlink($tempFile);
        exit;
    }
}

// ============================================================
// FORMAT 2: EKSPOR CSV (KOMPATIBEL EXCEL UTF-8 BOM)
// ============================================================
if ($format === 'csv') {
    $filename = 'daftar-pantau-anak-berisiko-' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // Tulis UTF-8 BOM agar Excel membaca karakter khusus dengan benar
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, $export_headers);

    $no = 1;
    foreach ($data as $r) {
        $usia_bulan = Antropometri::hitungUsiaBulan($r['tanggal_lahir'], $r['tanggal_pengukuran']);
        fputcsv($output, [
            $no++,
            "'" . $r['nik'], // Prefix ' agar NIK tidak diformat scientific number oleh Excel
            $r['nama_anak'],
            $r['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan',
            $r['tanggal_lahir'],
            $usia_bulan . ' bln',
            $r['nama_ibu'] ?: '-',
            $r['nama_posyandu'] ?: '-',
            $r['dusun_rw'] ?: '-',
            $r['tanggal_pengukuran'],
            number_format((float)$r['berat_badan'], 1),
            number_format((float)$r['tinggi_badan'], 1),
            number_format((float)$r['zscore_bb_u'], 2),
            number_format((float)$r['zscore_tb_u'], 2),
            number_format((float)$r['zscore_bb_tb'], 2),
            $r['status_bb_u'] ?: '-',
            $r['status_tb_u'] ?: '-',
            $r['status_bb_tb'] ?: '-',
            $r['status_risiko'] ?: '-',
            $r['status_tindak_lanjut'],
            $r['tanggal_tindak_lanjut'] ?: '-',
            (int)$r['total_intervensi'],
            $r['jenis_terakhir'] ?: '-',
            $r['tanggal_terakhir'] ?: '-',
            $r['petugas_terakhir'] ?: '-',
            str_replace(["\r", "\n"], ' ', $r['catatan_terakhir'] ?: '-'),
        ]);
    }

    fclose($output);
    exit;
}

// ============================================================
// FORMAT 3: CETAK REKAPITULASI DOKUMEN RESMI / PDF
// ============================================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Daftar Pantau Balita Berisiko — SiPantau Gizi</title>
  <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= asset('vendors/bootstrap-icons/bootstrap-icons.css') ?>">
  <style>
    @media print {
      .no-print { display: none !important; }
      body { font-size: 10pt; color: #000; background: #fff !important; }
      .table th, .table td { padding: 4px 6px !important; font-size: 8.5pt !important; }
      @page { size: landscape; margin: 12mm 10mm; }
    }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; }
    .paper { background: #fff; max-width: 1200px; margin: 20px auto; padding: 30px 40px; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 4px; }
    .kop-header { border-bottom: 3px double #333; padding-bottom: 12px; margin-bottom: 20px; }
    .kop-logo { font-size: 2.2rem; color: #0d6efd; line-height: 1; }
    .kop-title { font-size: 1.25rem; font-weight: 700; text-transform: uppercase; margin-bottom: 2px; }
    .kop-sub { font-size: 0.9rem; color: #555; margin-bottom: 0; }
    .table-data th { background-color: #f1f3f5 !important; color: #333; font-weight: 600; text-align: center; vertical-align: middle; border-color: #dee2e6; }
    .table-data td { vertical-align: middle; border-color: #dee2e6; }
  </style>
</head>
<body>

  <!-- Floating Action Buttons -->
  <div class="no-print position-fixed top-0 end-0 p-3" style="z-index: 9999;">
    <div class="btn-group shadow">
      <button onclick="window.print()" class="btn btn-primary btn-sm">
        <i class="bi bi-printer me-1"></i> Cetak / Simpan PDF
      </button>
      <a href="<?= base_url('modules/daftar-pantau/index.php') ?>" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Kembali
      </a>
    </div>
  </div>

  <div class="paper">
    <!-- Kop Dokumen -->
    <div class="kop-header d-flex align-items-center gap-3">
      <div class="kop-logo"><i class="bi bi-heart-pulse-fill"></i></div>
      <div class="flex-grow-1 text-center">
        <h1 class="kop-title">PEMERINTAH KOTA / KABUPATEN</h1>
        <h2 class="kop-title" style="font-size: 1.1rem; font-weight: 600;">DINAS KESEHATAN — PUSKESMAS WILAYAH</h2>
        <p class="kop-sub">Sistem Informasi Pemantauan Gizi Balita (SiPantau Gizi)</p>
      </div>
      <div class="text-end text-muted small" style="min-width: 140px;">
        <div><strong>Dokumen Resmi</strong></div>
        <div>No: DPG/<?= date('Ym') ?>/<?= count($data) ?></div>
      </div>
    </div>

    <!-- Judul & Keterangan Laporan -->
    <div class="text-center mb-3">
      <h3 class="h5 fw-bold text-uppercase mb-1">DAFTAR PANTAU BALITA BERISIKO & CATATAN TINDAK LANJUT</h3>
      <p class="small text-muted mb-0">
        Lokasi: <strong><?= e($posyandu_label) ?></strong> | 
        Status: <strong><?= !empty($filter_status) ? e($filter_status) : 'Semua Status' ?></strong> | 
        Tanggal Cetak: <strong><?= date('d F Y') ?></strong>
      </p>
    </div>

    <!-- Ringkasan Statistik -->
    <div class="row g-2 mb-3 small text-center">
      <div class="col-3">
        <div class="border rounded p-2 bg-light">
          <span class="text-muted d-block">Total Balita Berisiko</span>
          <span class="fs-6 fw-bold"><?= count($data) ?> Anak</span>
        </div>
      </div>
      <div class="col-3">
        <div class="border rounded p-2 bg-light">
          <span class="text-danger d-block">Status Baru (Menunggu)</span>
          <span class="fs-6 fw-bold text-danger">
            <?= count(array_filter($data, fn($r) => $r['status_tindak_lanjut'] === 'Baru')) ?>
          </span>
        </div>
      </div>
      <div class="col-3">
        <div class="border rounded p-2 bg-light">
          <span class="text-warning-emphasis d-block">Sedang Ditindaklanjuti</span>
          <span class="fs-6 fw-bold text-warning-emphasis">
            <?= count(array_filter($data, fn($r) => $r['status_tindak_lanjut'] === 'Ditindaklanjuti')) ?>
          </span>
        </div>
      </div>
      <div class="col-3">
        <div class="border rounded p-2 bg-light">
          <span class="text-success d-block">Selesai (Teratasi)</span>
          <span class="fs-6 fw-bold text-success">
            <?= count(array_filter($data, fn($r) => $r['status_tindak_lanjut'] === 'Selesai')) ?>
          </span>
        </div>
      </div>
    </div>

    <!-- Tabel Rekapitulasi Cetak -->
    <table class="table table-bordered table-data table-sm w-100">
      <thead>
        <tr>
          <th style="width: 25px;">No</th>
          <th>Nama Balita / NIK</th>
          <th style="width: 50px;">JK</th>
          <th style="width: 70px;">Usia</th>
          <th>Ibu / Posyandu</th>
          <th style="width: 70px;">Tgl Ukur</th>
          <th style="width: 65px;">BB (kg)</th>
          <th style="width: 65px;">TB (cm)</th>
          <th>Status Gizi & Risiko</th>
          <th style="width: 85px;">Status TL</th>
          <th>Intervensi Terakhir</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($data)): ?>
          <tr>
            <td colspan="11" class="text-center py-4 text-muted">Tidak ada data anak berisiko pada periode ini.</td>
          </tr>
        <?php else: ?>
          <?php $no = 1; foreach ($data as $r): 
            $usia_bulan = Antropometri::hitungUsiaBulan($r['tanggal_lahir'], $r['tanggal_pengukuran']);
          ?>
            <tr>
              <td class="text-center"><?= $no++ ?></td>
              <td>
                <div class="fw-semibold"><?= e($r['nama_anak']) ?></div>
                <small class="text-muted">NIK: <?= e($r['nik']) ?></small>
              </td>
              <td class="text-center"><?= $r['jenis_kelamin'] ?></td>
              <td class="text-center"><?= $usia_bulan ?> bln</td>
              <td>
                <div><?= e($r['nama_ibu']) ?></div>
                <small class="text-muted"><?= e($r['nama_posyandu']) ?></small>
              </td>
              <td class="text-center"><?= date('d/m/y', strtotime($r['tanggal_pengukuran'])) ?></td>
              <td class="text-end"><?= number_format($r['berat_badan'], 1) ?></td>
              <td class="text-end"><?= number_format($r['tinggi_badan'], 1) ?></td>
              <td>
                <div class="fw-semibold text-danger small"><?= e($r['status_risiko']) ?></div>
                <small class="text-muted d-block">
                  TB/U: <?= e($r['status_tb_u']) ?> | BB/U: <?= e($r['status_bb_u']) ?>
                </small>
              </td>
              <td class="text-center">
                <strong><?= e($r['status_tindak_lanjut']) ?></strong>
                <?php if ($r['tanggal_tindak_lanjut']): ?>
                  <div style="font-size: 7.5pt;" class="text-muted"><?= date('d/m/y', strtotime($r['tanggal_tindak_lanjut'])) ?></div>
                <?php endif; ?>
              </td>
              <td style="font-size: 8pt;">
                <?php if ($r['total_intervensi'] > 0): ?>
                  <strong><?= e($r['jenis_terakhir']) ?></strong>
                  <div class="text-muted"><?= date('d/m/Y', strtotime($r['tanggal_terakhir'])) ?> - <?= e($r['petugas_terakhir']) ?></div>
                  <div><?= e($r['catatan_terakhir']) ?></div>
                <?php else: ?>
                  <span class="text-muted fst-italic">- Belum ada -</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- Bagian Tanda Tangan Pengesahan -->
    <div class="row mt-4 pt-3 small text-center" style="page-break-inside: avoid;">
      <div class="col-4">
        <div>Mengetahui,</div>
        <div class="fw-semibold">Koordinator Desa / Puskesmas</div>
        <div style="height: 60px;"></div>
        <div class="text-decoration-underline fw-semibold">( ............................................ )</div>
        <div class="text-muted">NIP. ........................................</div>
      </div>
      <div class="col-4"></div>
      <div class="col-4">
        <div>Dicetak pada: <?= date('d F Y') ?></div>
        <div class="fw-semibold">Petugas Gizi / Bidan Pembina</div>
        <div style="height: 60px;"></div>
        <div class="text-decoration-underline fw-semibold"><?= e($_SESSION['user_nama'] ?? 'Petugas Gizi') ?></div>
        <div class="text-muted">Role: <?= e($_SESSION['user_role_nama'] ?? 'Petugas') ?></div>
      </div>
    </div>
  </div>

</body>
</html>
