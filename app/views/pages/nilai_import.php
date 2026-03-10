<?php
/**
 * ========================================================
 * TRACER MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Manajemen Data Nilai Siswa
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Data Nilai & Import Nilai Page
 * Deskripsi: Halaman untuk import nilai rapor/UAM dan monitoring upload status
 * 
 * @package    TRACER-MTSN11
 * @author     MTsN 11 Majalengka Development Team
 * @copyright  2026 MTsN 11 Majalengka. All rights reserved.
 * @license    Proprietary License
 * @version    1.0.0
 * @since      2026-01-01
 * @created    2026-03-06
 * @modified   2026-03-06
 * 
 * Features:
 * - Download template Excel per semester
 * - Upload nilai rapor/UAM dari file Excel
 * - Monitoring status upload per siswa
 * - Filter by semester GANJIL/GENAP dan status upload
 * - Search, pagination, dan sorting kolom
 * 
 * DISCLAIMER:
 * Software ini dikembangkan khusus untuk MTsN 11 Majalengka.
 * Dilarang keras menyalin, memodifikasi, atau mendistribusikan
 * tanpa izin tertulis dari MTsN 11 Majalengka.
 * 
 * CONTACT:
 * Website: https://mtsn11majalengka.sch.id
 * Email: mtsn11majalengka@gmail.com
 * Phone: (0233) 8319182
 * 
 * ========================================================
 */
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$setting = setting_akademik();
$semesterAktif = strtoupper($setting['semester_aktif']);
$targetRapor = semester_upload_target($semesterAktif);

if (!function_exists('normalize_header')) {
    function normalize_header(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = str_replace(['.', '-', '/', "'"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }
}

if (!function_exists('parse_import_header')) {
    function parse_import_header(array $headerRow, array $nisnHeaderCandidates, array $aliasToMapelId, array $mapelByName): array
    {
        $nisnIndex = null;
        $kelasIndex = null;
        $nomorAbsenIndex = null;
        $namaIndex = null;
        $mapelColumns = [];

        foreach ($headerRow as $index => $header) {
            $headerKey = normalize_header((string) $header);
            if ($headerKey === '') {
                continue;
            }

            if ($nisnIndex === null && in_array($headerKey, $nisnHeaderCandidates, true)) {
                $nisnIndex = (int) $index;
                continue;
            }

            if ($kelasIndex === null && in_array($headerKey, ['KELAS'], true)) {
                $kelasIndex = (int) $index;
                continue;
            }

            if ($nomorAbsenIndex === null && in_array($headerKey, ['NOMOR ABSEN', 'NO ABSEN', 'ABSEN'], true)) {
                $nomorAbsenIndex = (int) $index;
                continue;
            }

            if ($namaIndex === null && in_array($headerKey, ['NAMA', 'NAMA LENGKAP', 'NAMA SISWA'], true)) {
                $namaIndex = (int) $index;
                continue;
            }

            if (isset($aliasToMapelId[$headerKey])) {
                $mapelColumns[(int) $index] = (int) $aliasToMapelId[$headerKey];
                continue;
            }

            if (isset($mapelByName[$headerKey])) {
                $mapelColumns[(int) $index] = (int) $mapelByName[$headerKey];
            }
        }

        return [
            'nisn_index' => $nisnIndex,
            'kelas_index' => $kelasIndex,
            'nomor_absen_index' => $nomorAbsenIndex,
            'nama_index' => $namaIndex,
            'mapel_columns' => $mapelColumns,
        ];
    }
}

if (!function_exists('build_combined_header_row')) {
    function build_combined_header_row(array $topRow, array $bottomRow): array
    {
        $length = max(count($topRow), count($bottomRow));
        $combined = [];

        for ($i = 0; $i < $length; $i++) {
            $top = trim((string) ($topRow[$i] ?? ''));
            $bottom = trim((string) ($bottomRow[$i] ?? ''));

            $topKey = normalize_header($top);
            $bottomKey = normalize_header($bottom);

            if ($topKey === 'PAI' || $topKey === 'MULOK') {
                $combined[$i] = $bottom !== '' ? $bottom : $top;
                continue;
            }

            if ($top !== '') {
                $combined[$i] = $top;
                continue;
            }

            $combined[$i] = $bottom;
            if ($bottomKey === '') {
                $combined[$i] = '';
            }
        }

        return $combined;
    }
}

if (!function_exists('detect_rdm_kelas')) {
    function detect_rdm_kelas(array $rows): string
    {
        $maxRows = min(20, count($rows));

        for ($r = 0; $r < $maxRows; $r++) {
            $row = $rows[$r] ?? [];
            $colCount = count($row);
            for ($c = 0; $c < $colCount; $c++) {
                $raw = trim((string) ($row[$c] ?? ''));
                if ($raw === '') {
                    continue;
                }

                if (preg_match('/kelas\s*[:\-]\s*(.+)/i', $raw, $m)) {
                    $kelasValue = trim((string) $m[1]);
                    if ($kelasValue !== '') {
                        return $kelasValue;
                    }
                }

                $rawUpper = strtoupper($raw);
                if ($rawUpper === 'KELAS' || strpos($rawUpper, 'KELAS') === 0) {
                    for ($next = $c + 1; $next <= min($c + 3, $colCount - 1); $next++) {
                        $candidate = trim((string) ($row[$next] ?? ''));
                        if ($candidate !== '' && !preg_match('/^(semester|tahun|madrasah)/i', $candidate)) {
                            return $candidate;
                        }
                    }
                }
            }
        }

        return '';
    }
}

if (!function_exists('normalize_tahun_ajaran')) {
    function normalize_tahun_ajaran(string $value): string
    {
        if (preg_match('/\b(20\d{2})\s*[\/\-]\s*(20\d{2})\b/', $value, $m)) {
            return $m[1] . '/' . $m[2];
        }

        return '';
    }
}

if (!function_exists('detect_rdm_tahun_ajaran')) {
    function detect_rdm_tahun_ajaran(array $rows): string
    {
        $maxRows = min(30, count($rows));

        for ($r = 0; $r < $maxRows; $r++) {
            $row = $rows[$r] ?? [];
            $colCount = count($row);

            for ($c = 0; $c < $colCount; $c++) {
                $raw = trim((string) ($row[$c] ?? ''));
                if ($raw === '') {
                    continue;
                }

                $normalized = normalize_header($raw);
                $fromCell = normalize_tahun_ajaran($raw);
                if ($fromCell !== '') {
                    return $fromCell;
                }

                if (strpos($normalized, 'TAHUN AJARAN') !== false || strpos($normalized, 'THN AJARAN') !== false) {
                    for ($next = $c + 1; $next <= $c + 3; $next++) {
                        $candidate = trim((string) ($row[$next] ?? ''));
                        $fromNeighbor = normalize_tahun_ajaran($candidate);
                        if ($fromNeighbor !== '') {
                            return $fromNeighbor;
                        }
                    }

                    $nextRowCandidate = trim((string) (($rows[$r + 1][$c] ?? '')));
                    $fromNextRow = normalize_tahun_ajaran($nextRowCandidate);
                    if ($fromNextRow !== '') {
                        return $fromNextRow;
                    }
                }
            }
        }

        return '';
    }
}

if (!function_exists('detect_import_layout')) {
    function detect_import_layout(array $rows, array $nisnHeaderCandidates, array $aliasToMapelId, array $mapelByName): array
    {
        $best = [
            'score' => -1,
            'data_start_row' => 1,
            'nisn_index' => null,
            'kelas_index' => null,
            'nomor_absen_index' => null,
            'nama_index' => null,
            'mapel_columns' => [],
        ];

        $maxRows = min(30, count($rows));

        for ($i = 0; $i < $maxRows; $i++) {
            $single = parse_import_header($rows[$i] ?? [], $nisnHeaderCandidates, $aliasToMapelId, $mapelByName);
            if ($single['nisn_index'] !== null && count($single['mapel_columns']) > 0) {
                $singleScore = count($single['mapel_columns']) + ($single['kelas_index'] !== null ? 1 : 0);
                if ($singleScore > $best['score']) {
                    $best = [
                        'score' => $singleScore,
                        'data_start_row' => $i + 1,
                        'nisn_index' => $single['nisn_index'],
                        'kelas_index' => $single['kelas_index'],
                        'nomor_absen_index' => $single['nomor_absen_index'],
                        'nama_index' => $single['nama_index'],
                        'mapel_columns' => $single['mapel_columns'],
                    ];
                }
            }

            if ($i + 1 >= $maxRows) {
                continue;
            }

            $combinedHeader = build_combined_header_row($rows[$i] ?? [], $rows[$i + 1] ?? []);
            $combined = parse_import_header($combinedHeader, $nisnHeaderCandidates, $aliasToMapelId, $mapelByName);

            if ($combined['nisn_index'] !== null && count($combined['mapel_columns']) > 0) {
                $combinedScore = count($combined['mapel_columns']) + 2 + ($combined['kelas_index'] !== null ? 1 : 0);
                if ($combinedScore > $best['score']) {
                    $best = [
                        'score' => $combinedScore,
                        'data_start_row' => $i + 2,
                        'nisn_index' => $combined['nisn_index'],
                        'kelas_index' => $combined['kelas_index'],
                        'nomor_absen_index' => $combined['nomor_absen_index'],
                        'nama_index' => $combined['nama_index'],
                        'mapel_columns' => $combined['mapel_columns'],
                    ];
                }
            }
        }

        return $best;
    }
}

if (!function_exists('download_template_excel')) {
    function download_template_excel(string $filename, array $headers): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Nilai');

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            '1',
            '240001',
            '0112345678',
            'NAMA SISWA',
            'L',
            'VIII-A',
            '5',
        ], null, 'A2');

        $columnCount = count($headers);
        for ($index = 1; $index <= $columnCount; $index++) {
            $sheet->getColumnDimensionByColumn($index)->setAutoSize(true);
        }

        $guideSheet = $spreadsheet->createSheet();
        $guideSheet->setTitle('Petunjuk');
        $guideSheet->fromArray([
            ['PETUNJUK PENGISIAN TEMPLATE NILAI'],
            ['1. Jangan ubah nama header di baris pertama.'],
            ['2. Kolom wajib minimal: NISN. Kolom No/NIS/Nama/JK opsional untuk referensi.'],
            ['3. Kolom Kelas dan Nomor Absen opsional - jika diisi akan update data siswa.'],
            ['4. Isi nilai pada kolom mapel dengan rentang 70-100.'],
            ['5. Biarkan kosong jika nilai mapel belum tersedia.'],
            ['6. Nilai akan dipetakan otomatis berdasarkan header mapel (QH, AA, FIK, SKI, BAR, PP, BINDO, MTK, IPA, IPS, BING, PJOK, INFO, SBP, BSD).'],
            ['7. NISN harus sesuai data siswa aktif di aplikasi.'],
            ['8. Simpan file dalam format .xlsx sebelum upload.'],
            [''],
            ['Format Kelas: VIII-A, IX-B, dll (opsional)'],
            ['Format Nomor Absen: angka 1-50 (opsional)'],
            [''],
            ['Catatan Semester:'],
            ['- Import Rapor: otomatis mengikuti current semester siswa pada semester aktif.'],
            ['- Import UAM: diproses untuk siswa semester Akhir saat semester aktif GENAP.'],
        ], null, 'A1');
        $guideSheet->getColumnDimension('A')->setWidth(140);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

$mapelRows = db()->query('SELECT id, nama_mapel FROM mapel')->fetchAll();
$mapelByName = [];
$mapelNameById = [];
foreach ($mapelRows as $m) {
    $mapelId = (int) $m['id'];
    $mapelByName[normalize_header($m['nama_mapel'])] = $mapelId;
    $mapelNameById[$mapelId] = (string) $m['nama_mapel'];
}

$aliasToMapelName = [
    'QH' => 'AL QUR AN HADIS',
    'QURAN HADIS' => 'AL QUR AN HADIS',
    'ALQURAN HADIS' => 'AL QUR AN HADIS',
    'AA' => 'AKIDAH AKHLAK',
    'AKIDAH' => 'AKIDAH AKHLAK',
    'AKHLAK' => 'AKIDAH AKHLAK',
    'FIK' => 'FIKIH',
    'SKI' => 'SKI',
    'BAR' => 'BAHASA ARAB',
    'B ARAB' => 'BAHASA ARAB',
    'PP' => 'PPKN',
    'PPKN' => 'PPKN',
    'BINDO' => 'BAHASA INDONESIA',
    'B INDO' => 'BAHASA INDONESIA',
    'MTK' => 'MATEMATIKA',
    'IPA' => 'IPA',
    'IPS' => 'IPS',
    'BING' => 'BAHASA INGGRIS',
    'B ING' => 'BAHASA INGGRIS',
    'PJOK' => 'PENJASORKES',
    'PENJAS' => 'PENJASORKES',
    'INFO' => 'PRAKARYA INFORMATIKA',
    'INFORMATIKA' => 'PRAKARYA INFORMATIKA',
    'SBP' => 'SENI BUDAYA',
    'SENBUD' => 'SENI BUDAYA',
    'BSD' => 'BAHASA DAERAH',
    'BAHASA DAERAH' => 'BAHASA DAERAH',
];

$aliasToMapelId = [];
foreach ($aliasToMapelName as $alias => $targetName) {
    $aliasKey = normalize_header($alias);
    $targetKey = normalize_header($targetName);
    if (isset($mapelByName[$targetKey])) {
        $aliasToMapelId[$aliasKey] = $mapelByName[$targetKey];
    }
}

$nisnHeaderCandidates = ['NISN', 'NISN SISWA', 'NISN S'];
$rdmPreviewSessionKey = 'rdm_import_preview';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('data-nilai');

    $action = $_POST['action'] ?? '';

    if ($action === 'nonaktifkan_siswa') {
        $nisn = $_POST['nisn'] ?? '';
        $stmt = db()->prepare("UPDATE siswa SET status_siswa='Tidak Melanjutkan' WHERE nisn=:nisn");
        $stmt->execute(['nisn' => $nisn]);
        set_flash('success', 'Status siswa berhasil diubah menjadi Tidak Melanjutkan.');
        redirect('index.php?page=data-nilai');
    }

    if ($action === 'download_template_rapor' || $action === 'download_template_uam') {
        if (!class_exists(Spreadsheet::class)) {
            set_flash('error', 'PhpSpreadsheet belum terpasang. Jalankan composer install.');
            redirect('index.php?page=data-nilai');
        }

        $headers = ['No', 'NIS', 'NISN', 'Nama', 'JK', 'Kelas', 'Nomor Absen', 'QH', 'AA', 'FIK', 'SKI', 'BAR', 'PP', 'BINDO', 'MTK', 'IPA', 'IPS', 'BING', 'PJOK', 'INFO', 'SBP', 'BSD'];

        if ($action === 'download_template_rapor') {
            download_template_excel('template_import_rapor.xlsx', $headers);
        }

        download_template_excel('template_import_uam.xlsx', $headers);
    }

    if ($action === 'cancel_preview_rapor_rdm') {
        unset($_SESSION[$rdmPreviewSessionKey]);
        set_flash('success', 'Preview import RDM dibatalkan. Tidak ada data yang diubah.');
        redirect('index.php?page=data-nilai');
    }

    if ($action === 'kosongkan_nilai_siswa') {
        $nisn = trim((string) ($_POST['nisn'] ?? ''));
        $semesterTarget = strtoupper(trim((string) ($_POST['semester_target'] ?? '')));

        if ($nisn === '') {
            set_flash('error', 'NISN tidak valid untuk proses kosongkan nilai.');
            redirect('index.php?page=data-nilai');
        }

        if ($semesterTarget === 'UAM') {
            $stDeleteUam = db()->prepare('DELETE FROM nilai_uam WHERE nisn=:nisn');
            $stDeleteUam->execute(['nisn' => $nisn]);
            $deleted = $stDeleteUam->rowCount();
            set_flash('success', 'Nilai UAM berhasil dikosongkan. Total data dihapus: ' . $deleted . '.');
            redirect('index.php?page=data-nilai&semester_view=UAM');
        }

        $semesterInt = (int) $semesterTarget;
        if ($semesterInt < 1 || $semesterInt > 6) {
            set_flash('error', 'Semester target tidak valid untuk proses kosongkan nilai.');
            redirect('index.php?page=data-nilai');
        }

        $stCountFinalized = db()->prepare('SELECT COUNT(*) c FROM nilai_rapor
            WHERE nisn=:nisn AND semester=:semester AND tahun_ajaran=:ta AND is_finalized=1');
        $stCountFinalized->execute([
            'nisn' => $nisn,
            'semester' => $semesterInt,
            'ta' => (string) $setting['tahun_ajaran'],
        ]);
        $finalizedCount = (int) ($stCountFinalized->fetch()['c'] ?? 0);

        $stDeleteRapor = db()->prepare('DELETE FROM nilai_rapor
            WHERE nisn=:nisn AND semester=:semester AND tahun_ajaran=:ta AND is_finalized=0');
        $stDeleteRapor->execute([
            'nisn' => $nisn,
            'semester' => $semesterInt,
            'ta' => (string) $setting['tahun_ajaran'],
        ]);
        $deleted = $stDeleteRapor->rowCount();

        if ($finalizedCount > 0) {
            set_flash('warning', 'Nilai semester ' . $semesterInt . ' dikosongkan: ' . $deleted . ' data. ' . $finalizedCount . ' data finalized tidak dihapus.');
        } else {
            set_flash('success', 'Nilai semester ' . $semesterInt . ' berhasil dikosongkan. Total data dihapus: ' . $deleted . '.');
        }

        redirect('index.php?page=data-nilai&semester_view=' . $semesterInt);
    }

    if ($action === 'confirm_import_rapor_rdm') {
        $preview = $_SESSION[$rdmPreviewSessionKey] ?? null;
        if (!is_array($preview) || empty($preview['entries']) || empty($preview['meta'])) {
            set_flash('error', 'Preview import tidak ditemukan. Silakan upload ulang file RDM dan lakukan preview terlebih dahulu.');
            redirect('index.php?page=data-nilai');
        }

        $previewTa = (string) ($preview['meta']['tahun_ajaran'] ?? '');
        if ($previewTa !== (string) $setting['tahun_ajaran']) {
            set_flash('error', 'Preview sudah kedaluwarsa karena tahun ajaran aktif berubah. Silakan upload ulang dan preview kembali.');
            unset($_SESSION[$rdmPreviewSessionKey]);
            redirect('index.php?page=data-nilai');
        }

        db()->beginTransaction();
        try {
            $stUpdateSiswa = db()->prepare('UPDATE siswa
                SET kelas = CASE WHEN :kelas_set = 1 THEN :kelas ELSE kelas END,
                    nomor_absen = CASE WHEN :absen_set = 1 THEN :nomor_absen ELSE nomor_absen END,
                    tahun_masuk = CASE WHEN :tahun_masuk_set = 1 THEN :tahun_masuk ELSE tahun_masuk END
                WHERE nisn=:nisn');
            $stCheckFinalizedRapor = db()->prepare('SELECT is_finalized FROM nilai_rapor
                WHERE nisn=:nisn AND mapel_id=:mapel AND semester=:semester AND tahun_ajaran=:ta LIMIT 1');
            $stInsertRapor = db()->prepare('INSERT INTO nilai_rapor (nisn, mapel_id, semester, tahun_ajaran, nilai_angka, is_finalized) VALUES (:nisn,:mapel,:semester,:ta,:nilai,0)
                ON DUPLICATE KEY UPDATE nilai_angka=VALUES(nilai_angka), is_finalized=is_finalized');

            $updatedSiswaCount = 0;
            $siswaUpdates = is_array($preview['student_updates'] ?? null) ? $preview['student_updates'] : [];
            foreach ($siswaUpdates as $nisn => $updateData) {
                if (!is_array($updateData)) {
                    continue;
                }

                $kelasSet = array_key_exists('kelas', $updateData) ? 1 : 0;
                $absenSet = (array_key_exists('nomor_absen', $updateData) && $updateData['nomor_absen'] !== null && $updateData['nomor_absen'] !== '') ? 1 : 0;
                $tahunMasukSet = array_key_exists('tahun_masuk', $updateData) && !empty($updateData['tahun_masuk']) ? 1 : 0;
                
                if ($kelasSet === 0 && $absenSet === 0 && $tahunMasukSet === 0) {
                    continue;
                }

                $stUpdateSiswa->execute([
                    'nisn' => (string) $nisn,
                    'kelas_set' => $kelasSet,
                    'kelas' => (string) ($updateData['kelas'] ?? ''),
                    'absen_set' => $absenSet,
                    'nomor_absen' => $absenSet ? (int) $updateData['nomor_absen'] : 0,
                    'tahun_masuk_set' => $tahunMasukSet,
                    'tahun_masuk' => $tahunMasukSet ? (string) $updateData['tahun_masuk'] : '',
                ]);
                $updatedSiswaCount++;
            }

            $processedCount = 0;
            $skipFinalized = 0;
            foreach ($preview['entries'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $nisnEntry = (string) ($entry['nisn'] ?? '');
                $mapelEntry = (int) ($entry['mapel_id'] ?? 0);
                $semesterEntry = (int) ($entry['semester'] ?? 0);

                $stCheckFinalizedRapor->execute([
                    'nisn' => $nisnEntry,
                    'mapel' => $mapelEntry,
                    'semester' => $semesterEntry,
                    'ta' => (string) $setting['tahun_ajaran'],
                ]);
                $existing = $stCheckFinalizedRapor->fetch();
                if ($existing && (int) ($existing['is_finalized'] ?? 0) === 1) {
                    $skipFinalized++;
                    continue;
                }

                $stInsertRapor->execute([
                    'nisn' => $nisnEntry,
                    'mapel' => $mapelEntry,
                    'semester' => $semesterEntry,
                    'ta' => (string) $setting['tahun_ajaran'],
                    'nilai' => (float) ($entry['nilai_baru'] ?? 0),
                ]);
                $processedCount++;
            }

            db()->commit();
            unset($_SESSION[$rdmPreviewSessionKey]);

            $insertCount = (int) ($preview['meta']['insert_count'] ?? 0);
            $updateCount = (int) ($preview['meta']['update_count'] ?? 0);
            set_flash('success', "Import RDM berhasil dikonfirmasi. Diproses: {$processedCount} nilai (insert: {$insertCount}, update: {$updateCount}), dilewati finalized: {$skipFinalized}, update biodata siswa: {$updatedSiswaCount}.");
        } catch (Throwable $e) {
            db()->rollBack();
            set_flash('error', 'Konfirmasi import gagal: ' . $e->getMessage());
        }

        redirect('index.php?page=data-nilai');
    }

    $fileRequiredActions = ['import_rapor', 'import_uam', 'preview_rapor_rdm'];
    if (!in_array($action, $fileRequiredActions, true)) {
        set_flash('error', 'Aksi import tidak dikenali.');
        redirect('index.php?page=data-nilai');
    }

    if (!class_exists(IOFactory::class)) {
        set_flash('error', 'PhpSpreadsheet belum terpasang. Jalankan composer install.');
        redirect('index.php?page=data-nilai');
    }

    $tmp = $_FILES['file_excel']['tmp_name'] ?? '';
    if (!$tmp) {
        set_flash('error', 'File wajib diisi.');
        redirect('index.php?page=data-nilai');
    }

    $spreadsheet = IOFactory::load($tmp);
    $rows = $spreadsheet->getActiveSheet()->toArray();

    if (count($rows) < 2) {
        set_flash('error', 'Format file tidak valid atau tidak ada data.');
        redirect('index.php?page=data-nilai');
    }

    $layout = detect_import_layout($rows, $nisnHeaderCandidates, $aliasToMapelId, $mapelByName);
    $nisnIndex = $layout['nisn_index'];
    $kelasIndex = $layout['kelas_index'];
    $nomorAbsenIndex = $layout['nomor_absen_index'];
    $namaIndex = $layout['nama_index'];
    $mapelColumns = $layout['mapel_columns'];
    $dataStartRow = (int) $layout['data_start_row'];
    $kelasRdmDetected = detect_rdm_kelas($rows);
    $tahunAjaranRdmDetected = detect_rdm_tahun_ajaran($rows);

    if ($nisnIndex === null) {
        set_flash('error', 'Kolom NISN tidak ditemukan di header file Excel.');
        redirect('index.php?page=data-nilai');
    }

    if (count($mapelColumns) === 0) {
        set_flash('error', 'Kolom mapel tidak dikenali. Pastikan header mapel sesuai format leger.');
        redirect('index.php?page=data-nilai');
    }

    if ($action === 'preview_rapor_rdm' && $tahunAjaranRdmDetected !== '' && $tahunAjaranRdmDetected !== (string) $setting['tahun_ajaran']) {
        set_flash('error', 'Tahun ajaran file RDM (' . $tahunAjaranRdmDetected . ') tidak sama dengan tahun ajaran aktif aplikasi (' . $setting['tahun_ajaran'] . '). Import dibatalkan.');
        redirect('index.php?page=data-nilai');
    }

    if ($action === 'preview_rapor_rdm' && $kelasRdmDetected === '') {
        set_flash('error', 'Kelas pada header file RDM tidak ditemukan. Untuk import RDM, format wajib menyertakan kelas di header (contoh: KELAS: VII-1).');
        redirect('index.php?page=data-nilai');
    }

    if ($action === 'preview_rapor_rdm') {
        $nisnFromFile = [];
        $namaByNisn = [];
        foreach ($rows as $i => $row) {
            if ($i < $dataStartRow) {
                continue;
            }
            $nisnCandidate = trim((string) ($row[$nisnIndex] ?? ''));
            if ($nisnCandidate !== '') {
                $nisnFromFile[$nisnCandidate] = true;
                if ($namaIndex !== null) {
                    $namaCandidate = trim((string) ($row[$namaIndex] ?? ''));
                    if ($namaCandidate !== '') {
                        $namaByNisn[$nisnCandidate] = $namaCandidate;
                    }
                }
            }
        }

        $siswaByNisn = [];
        $nisnList = array_keys($nisnFromFile);
        if (count($nisnList) > 0) {
            $chunkSize = 500;
            for ($offset = 0; $offset < count($nisnList); $offset += $chunkSize) {
                $chunk = array_slice($nisnList, $offset, $chunkSize);
                if (count($chunk) === 0) {
                    continue;
                }

                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $sqlSiswa = "SELECT nisn, nama, current_semester, status_siswa, kelas, nomor_absen, tahun_masuk FROM siswa WHERE status_siswa='Aktif' AND nisn IN ($placeholders)";
                $stSiswaBatch = db()->prepare($sqlSiswa);
                $stSiswaBatch->execute($chunk);
                foreach ($stSiswaBatch->fetchAll() as $rowSiswa) {
                    $siswaByNisn[(string) $rowSiswa['nisn']] = $rowSiswa;
                }
            }
        }

        $missingStudents = [];
        foreach ($nisnList as $nisnFile) {
            if (!isset($siswaByNisn[$nisnFile])) {
                $displayName = isset($namaByNisn[$nisnFile]) ? $namaByNisn[$nisnFile] . ' (' . $nisnFile . ')' : $nisnFile;
                $missingStudents[] = $displayName;
            }
        }

        if (count($missingStudents) > 0) {
            $previewMissing = implode(', ', array_slice($missingStudents, 0, 10));
            $suffix = count($missingStudents) > 10 ? ' dan lainnya' : '';
            set_flash('error', 'Preview dibatalkan. Ditemukan ' . count($missingStudents) . ' siswa pada file yang tidak terdaftar di aplikasi: ' . $previewMissing . $suffix . '.');
            redirect('index.php?page=data-nilai');
        }

        $stNilaiBySemester = db()->prepare('SELECT mapel_id, nilai_angka FROM nilai_rapor WHERE nisn=:nisn AND semester=:semester AND tahun_ajaran=:ta');
        $nilaiCache = [];

        $entries = [];
        $studentUpdates = [];
        $insertCount = 0;
        $updateCount = 0;

        $skipNoNisn = 0;
        $skipNoSiswa = 0;
        $skipSemester = 0;
        $skipRange = 0;
        $skipUnchanged = 0;

        foreach ($rows as $i => $row) {
            if ($i < $dataStartRow) {
                continue;
            }

            $hasDataSignal = false;
            foreach ($mapelColumns as $colIndex => $mapelId) {
                $signal = trim((string) ($row[$colIndex] ?? ''));
                if ($signal !== '') {
                    $hasDataSignal = true;
                    break;
                }
            }
            if (!$hasDataSignal) {
                continue;
            }

            $excelRow = $i + 1;
            $nisn = trim((string) ($row[$nisnIndex] ?? ''));
            if ($nisn === '') {
                $skipNoNisn++;
                continue;
            }

            $siswa = $siswaByNisn[$nisn] ?? null;
            if (!$siswa) {
                $skipNoSiswa++;
                continue;
            }

            $kelasFinal = $kelasRdmDetected;

            $semesterSiswa = normalize_current_semester($siswa['current_semester']);
            $isRaporTarget = in_array($semesterSiswa, $targetRapor, true);
            if (!$isRaporTarget) {
                $skipSemester++;
                continue;
            }

            $nomorAbsenRaw = $nomorAbsenIndex !== null ? trim((string) ($row[$nomorAbsenIndex] ?? '')) : '';
            $nomorAbsenFinal = $nomorAbsenRaw !== '' ? (int) $nomorAbsenRaw : null;

            // Auto-set tahun_masuk jika belum ada
            $tahunMasukSiswa = $siswa['tahun_masuk'] ?? null;
            $tahunMasukBaru = null;
            if (empty($tahunMasukSiswa)) {
                $tahunMasukBaru = hitung_tahun_masuk_dari_semester($setting['tahun_ajaran'], $semesterSiswa);
            }

            $kelasLama = (string) ($siswa['kelas'] ?? '');
            $kelasBerubah = $kelasFinal !== '' && normalize_header($kelasFinal) !== normalize_header($kelasLama);
            $absenLama = $siswa['nomor_absen'] !== null ? (int) $siswa['nomor_absen'] : null;
            $absenBerubah = $nomorAbsenFinal !== null && $nomorAbsenFinal !== $absenLama;

            if ($kelasBerubah || $absenBerubah || $tahunMasukBaru !== null) {
                if (!isset($studentUpdates[$nisn])) {
                    $studentUpdates[$nisn] = [];
                }
                if ($kelasBerubah) {
                    $studentUpdates[$nisn]['kelas'] = $kelasFinal;
                }
                if ($absenBerubah) {
                    $studentUpdates[$nisn]['nomor_absen'] = $nomorAbsenFinal;
                }
                if ($tahunMasukBaru !== null) {
                    $studentUpdates[$nisn]['tahun_masuk'] = $tahunMasukBaru;
                }
            }

            foreach ($mapelColumns as $colIndex => $mapelId) {
                $rawNilai = $row[$colIndex] ?? null;
                if ($rawNilai === null || trim((string) $rawNilai) === '') {
                    continue;
                }

                $nilai = (float) $rawNilai;
                if ($nilai < 7 || $nilai > 100) {
                    $skipRange++;
                    continue;
                }

                $cacheKey = $nisn . '|' . $semesterSiswa;
                if (!isset($nilaiCache[$cacheKey])) {
                    $stNilaiBySemester->execute([
                        'nisn' => $nisn,
                        'semester' => $semesterSiswa,
                        'ta' => $setting['tahun_ajaran'],
                    ]);
                    $nilaiByMapel = [];
                    foreach ($stNilaiBySemester->fetchAll() as $nilaiRow) {
                        $nilaiByMapel[(int) $nilaiRow['mapel_id']] = (float) $nilaiRow['nilai_angka'];
                    }
                    $nilaiCache[$cacheKey] = $nilaiByMapel;
                }

                $nilaiLama = $nilaiCache[$cacheKey][$mapelId] ?? null;

                if ($nilaiLama !== null && abs($nilaiLama - $nilai) < 0.0001) {
                    $skipUnchanged++;
                    continue;
                }

                $aksi = $nilaiLama === null ? 'INSERT' : 'UPDATE';
                if ($aksi === 'INSERT') {
                    $insertCount++;
                } else {
                    $updateCount++;
                }

                $entries[] = [
                    'excel_row' => $excelRow,
                    'nisn' => $nisn,
                    'nama' => (string) ($siswa['nama'] ?? ''),
                    'kelas_lama' => $kelasLama,
                    'kelas_baru' => $kelasFinal,
                    'mapel_id' => (int) $mapelId,
                    'mapel_nama' => $mapelNameById[(int) $mapelId] ?? ('Mapel #' . (int) $mapelId),
                    'semester' => $semesterSiswa,
                    'nilai_lama' => $nilaiLama,
                    'nilai_baru' => $nilai,
                    'aksi' => $aksi,
                ];
            }
        }

        if (count($entries) === 0) {
            set_flash('error', 'Tidak ada data nilai yang bisa diproses untuk preview. Periksa kesesuaian kelas, semester siswa, dan rentang nilai 70-100.');
            redirect('index.php?page=data-nilai');
        }

        $_SESSION[$rdmPreviewSessionKey] = [
            'meta' => [
                'tahun_ajaran' => (string) $setting['tahun_ajaran'],
                'tahun_ajaran_file' => $tahunAjaranRdmDetected,
                'kelas_detected' => $kelasRdmDetected,
                'kelas_source' => 'header',
                'generated_at' => date('Y-m-d H:i:s'),
                'insert_count' => $insertCount,
                'update_count' => $updateCount,
                'skip_no_nisn' => $skipNoNisn,
                'skip_no_siswa' => $skipNoSiswa,
                'skip_semester' => $skipSemester,
                'skip_range' => $skipRange,
                'skip_unchanged' => $skipUnchanged,
            ],
            'entries' => $entries,
            'student_updates' => $studentUpdates,
        ];

        set_flash('success', 'Preview RDM berhasil dibuat. Periksa semua baris pada tabel preview sebelum klik Konfirmasi Import.');
        redirect('index.php?page=data-nilai');
    }

    db()->beginTransaction();
    try {
        $count = 0;
        $skipRange = 0;
        $skipFinalized = 0;

        $stSiswa = db()->prepare('SELECT nisn, current_semester, status_siswa, kelas, tahun_masuk FROM siswa WHERE nisn=:nisn LIMIT 1');
        $stUpdateSiswa = db()->prepare('UPDATE siswa
            SET kelas = CASE WHEN :kelas_set = 1 THEN :kelas ELSE kelas END,
                nomor_absen = CASE WHEN :absen_set = 1 THEN :nomor_absen ELSE nomor_absen END,
                tahun_masuk = CASE WHEN :tahun_masuk_set = 1 THEN :tahun_masuk ELSE tahun_masuk END
            WHERE nisn=:nisn');
        $stCheckFinalizedRapor = db()->prepare('SELECT is_finalized FROM nilai_rapor
            WHERE nisn=:nisn AND mapel_id=:mapel AND semester=:semester AND tahun_ajaran=:ta LIMIT 1');
        $stInsertRapor = db()->prepare('INSERT INTO nilai_rapor (nisn, mapel_id, semester, tahun_ajaran, nilai_angka, is_finalized) VALUES (:nisn,:mapel,:semester,:ta,:nilai,0)
            ON DUPLICATE KEY UPDATE nilai_angka=VALUES(nilai_angka), is_finalized=is_finalized');
        $stInsertUam = db()->prepare('INSERT INTO nilai_uam (nisn, mapel_id, nilai_angka) VALUES (:nisn,:mapel,:nilai)
            ON DUPLICATE KEY UPDATE nilai_angka=VALUES(nilai_angka)');

        foreach ($rows as $i => $row) {
            if ($i < $dataStartRow) {
                continue;
            }

            $nisn = trim((string) ($row[$nisnIndex] ?? ''));
            if ($nisn === '') {
                continue;
            }

            $stSiswa->execute(['nisn' => $nisn]);
            $siswa = $stSiswa->fetch();
            if (!$siswa || $siswa['status_siswa'] !== 'Aktif') {
                continue;
            }

            $semesterSiswa = normalize_current_semester($siswa['current_semester']);
            
            // Auto-set atau validasi tahun_masuk
            $tahunMasukSiswa = $siswa['tahun_masuk'] ?? null;
            $tahunMasukBaru = null;
            
            if (empty($tahunMasukSiswa)) {
                // Jika belum ada tahun_masuk, hitung mundur dari tahun ajaran aktif
                $tahunMasukBaru = hitung_tahun_masuk_dari_semester($setting['tahun_ajaran'], $semesterSiswa);
            } else {
                // Jika sudah ada tahun_masuk, validasi apakah tahun_ajaran sesuai
                $tahunAjaranSeharusnya = hitung_tahun_ajaran_dari_angkatan($tahunMasukSiswa, $semesterSiswa);
                
                // Jika tidak sesuai, skip import dan catat warning (optional: bisa dibuat strict)
                // Untuk sekarang kita toleransi, tapi bisa diubah jadi error jika perlu
            }

            // Update kelas, nomor_absen, dan tahun_masuk jika kolom ada dan terisi
            $kelas = $kelasIndex !== null ? trim((string) ($row[$kelasIndex] ?? '')) : '';
            $nomorAbsen = $nomorAbsenIndex !== null ? trim((string) ($row[$nomorAbsenIndex] ?? '')) : '';
            
            if ($kelas !== '' || $nomorAbsen !== '' || $tahunMasukBaru !== null) {
                $stUpdateSiswa->execute([
                    'nisn' => $nisn,
                    'kelas_set' => $kelas !== '' ? 1 : 0,
                    'kelas' => $kelas,
                    'absen_set' => $nomorAbsen !== '' ? 1 : 0,
                    'nomor_absen' => $nomorAbsen !== '' ? (int) $nomorAbsen : 0,
                    'tahun_masuk_set' => $tahunMasukBaru !== null ? 1 : 0,
                    'tahun_masuk' => $tahunMasukBaru ?? '',
                ]);
            }
            $isRaporTarget = in_array($semesterSiswa, $targetRapor, true);
            $isUamTarget = $semesterAktif === 'GENAP' && $semesterSiswa === 6;

            if ($action === 'import_rapor' && !$isRaporTarget) {
                continue;
            }

            if ($action === 'import_uam' && !$isUamTarget) {
                continue;
            }

            foreach ($mapelColumns as $colIndex => $mapelId) {
                $rawNilai = $row[$colIndex] ?? null;
                if ($rawNilai === null || trim((string) $rawNilai) === '') {
                    continue;
                }

                $nilai = (float) $rawNilai;
                if ($nilai < 7 || $nilai > 100) {
                    $skipRange++;
                    continue;
                }

                if ($action === 'import_rapor') {
                    $stCheckFinalizedRapor->execute([
                        'nisn' => $nisn,
                        'mapel' => $mapelId,
                        'semester' => $semesterSiswa,
                        'ta' => (string) $setting['tahun_ajaran'],
                    ]);
                    $existing = $stCheckFinalizedRapor->fetch();
                    if ($existing && (int) ($existing['is_finalized'] ?? 0) === 1) {
                        $skipFinalized++;
                        continue;
                    }

                    $stInsertRapor->execute([
                        'nisn' => $nisn,
                        'mapel' => $mapelId,
                        'semester' => $semesterSiswa,
                        'ta' => $setting['tahun_ajaran'],
                        'nilai' => $nilai,
                    ]);
                    $count++;
                    continue;
                }

                if ($action === 'import_uam') {
                    $stInsertUam->execute([
                        'nisn' => $nisn,
                        'mapel' => $mapelId,
                        'nilai' => $nilai,
                    ]);
                    $count++;
                }
            }
        }

        db()->commit();
        set_flash('success', "Import selesai. Diproses: {$count}, dilewati (nilai di luar 70-100): {$skipRange}, dilewati finalized: {$skipFinalized}.");
    } catch (Throwable $e) {
        db()->rollBack();
        set_flash('error', 'Import gagal: ' . $e->getMessage());
    }

    redirect('index.php?page=data-nilai');
}

$rdmPreview = $_SESSION[$rdmPreviewSessionKey] ?? null;
if (!is_array($rdmPreview) || !is_array($rdmPreview['meta'] ?? null) || !is_array($rdmPreview['entries'] ?? null)) {
    $rdmPreview = null;
}

$monitorSemesterOptions = array_map('strval', semester_upload_target($semesterAktif));
if ($semesterAktif === 'GENAP') {
    $monitorSemesterOptions[] = 'UAM';
}
$defaultMonitorSemester = $monitorSemesterOptions[0] ?? '1';

$monitorSemester = strtoupper(trim($_GET['semester_view'] ?? $defaultMonitorSemester));
$monitorStatus = trim($_GET['status_upload'] ?? 'all');
$monitorStatus = in_array($monitorStatus, ['all', 'uploaded', 'not_uploaded'], true) ? $monitorStatus : 'all';
$monitorKelas = trim((string) ($_GET['kelas_filter_monitoring'] ?? ''));

if (!in_array($monitorSemester, $monitorSemesterOptions, true)) {
    $monitorSemester = $defaultMonitorSemester;
}

$mapelCount = (int) (db()->query('SELECT COUNT(*) c FROM mapel')->fetch()['c'] ?? 0);

// Filter students by current_semester matching the selected semester filter
// Dan filter berdasarkan tahun_masuk untuk memastikan hanya tampilkan siswa yang angkatannya sesuai
if ($monitorSemester === 'UAM') {
    $sql = "SELECT nisn, nis, nama, current_semester, kelas, nomor_absen, tahun_masuk FROM siswa WHERE status_siswa='Aktif' AND current_semester = 6";
    
    // Filter berdasarkan tahun_masuk - semester 6 seharusnya tahun masuk 2 tahun lalu
    $expectedTahunMasuk = hitung_tahun_masuk_dari_semester($setting['tahun_ajaran'], 6);
    if (!empty($expectedTahunMasuk)) {
        $sql .= " AND (tahun_masuk = " . db()->quote($expectedTahunMasuk) . " OR tahun_masuk IS NULL)";
    }
    
    $sql .= " ORDER BY COALESCE(kelas, ''), COALESCE(nomor_absen, 999), nama";
    $students = db()->query($sql)->fetchAll();
} else {
    $semesterInt = (int) $monitorSemester;
    $expectedTahunMasuk = hitung_tahun_masuk_dari_semester($setting['tahun_ajaran'], $semesterInt);
    
    $sql = "SELECT nisn, nis, nama, current_semester, kelas, nomor_absen, tahun_masuk FROM siswa WHERE status_siswa='Aktif' AND current_semester=:sem";
    
    // Filter berdasarkan tahun_masuk untuk angkatan yang sesuai
    if (!empty($expectedTahunMasuk)) {
        $sql .= " AND (tahun_masuk = :tahun_masuk OR tahun_masuk IS NULL)";
    }
    
    $sql .= " ORDER BY COALESCE(kelas, ''), COALESCE(nomor_absen, 999), nama";
    
    $stStudents = db()->prepare($sql);
    $params = ['sem' => $semesterInt];
    if (!empty($expectedTahunMasuk)) {
        $params['tahun_masuk'] = $expectedTahunMasuk;
    }
    $stStudents->execute($params);
    $students = $stStudents->fetchAll();
}

$entryCountByNisn = [];
$nisnListMonitor = array_values(array_unique(array_filter(array_map(static function ($row) {
    return (string) ($row['nisn'] ?? '');
}, $students), static function ($nisn) {
    return $nisn !== '';
})));

if (count($nisnListMonitor) > 0) {
    $chunkSize = 500;
    for ($offset = 0; $offset < count($nisnListMonitor); $offset += $chunkSize) {
        $chunk = array_slice($nisnListMonitor, $offset, $chunkSize);
        if (count($chunk) === 0) {
            continue;
        }

        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        if ($monitorSemester === 'UAM') {
            $sqlCount = "SELECT nisn, COUNT(*) c FROM nilai_uam WHERE nisn IN ($placeholders) GROUP BY nisn";
            $stCount = db()->prepare($sqlCount);
            $stCount->execute($chunk);
        } else {
            $sqlCount = "SELECT nisn, COUNT(*) c FROM nilai_rapor WHERE semester = ? AND tahun_ajaran = ? AND nisn IN ($placeholders) GROUP BY nisn";
            $stCount = db()->prepare($sqlCount);
            $params = array_merge([(int) $monitorSemester, (string) $setting['tahun_ajaran']], $chunk);
            $stCount->execute($params);
        }

        foreach ($stCount->fetchAll() as $countRow) {
            $entryCountByNisn[(string) $countRow['nisn']] = (int) ($countRow['c'] ?? 0);
        }
    }
}

$rowsMonitor = [];
$kelasMonitorOptions = [];
foreach ($students as $student) {
    $entryCount = (int) ($entryCountByNisn[(string) ($student['nisn'] ?? '')] ?? 0);
    $statusLabel = 'Belum Terupload';
    $kelasValue = trim((string) ($student['kelas'] ?? ''));

    if ($kelasValue !== '') {
        $kelasMonitorOptions[$kelasValue] = $kelasValue;
    }

    if ($monitorKelas !== '' && $kelasValue !== $monitorKelas) {
        continue;
    }

    if ($entryCount > 0) {
        $statusLabel = $entryCount >= $mapelCount ? 'Sudah Terupload (Lengkap)' : 'Sudah Terupload (Sebagian)';
    }

    $isUploaded = $entryCount > 0;
    if ($monitorStatus === 'uploaded' && !$isUploaded) {
        continue;
    }
    if ($monitorStatus === 'not_uploaded' && $isUploaded) {
        continue;
    }

    $rowsMonitor[] = [
        'nisn' => $student['nisn'],
        'nis' => $student['nis'],
        'nama' => $student['nama'],
        'kelas' => $student['kelas'] ?? '-',
        'nomor_absen' => $student['nomor_absen'] !== null ? (string) $student['nomor_absen'] : '-',
        'current_semester' => current_semester_label($student['current_semester']),
        'entry_count' => $entryCount,
        'status_label' => $statusLabel,
        'uploaded' => $isUploaded,
    ];
}

// Pencarian monitoring
$monitorSearch = trim($_GET['search_monitoring'] ?? '');
if ($monitorSearch !== '') {
    $rowsMonitor = array_filter($rowsMonitor, function($row) use ($monitorSearch) {
        $search = strtolower($monitorSearch);
        return strpos(strtolower($row['nama']), $search) !== false ||
               strpos(strtolower($row['nisn']), $search) !== false ||
               strpos(strtolower($row['nis']), $search) !== false;
    });
}

ksort($kelasMonitorOptions, SORT_NATURAL | SORT_FLAG_CASE);

// Sorting monitoring
$monitorSortBy = $_GET['sort_by_monitoring'] ?? 'nama';
$monitorSortBy = in_array($monitorSortBy, ['nama', 'current_semester', 'status_label'], true) ? $monitorSortBy : 'nama';
$monitorSortDir = strtoupper($_GET['sort_dir_monitoring'] ?? 'ASC');
$monitorSortDir = in_array($monitorSortDir, ['ASC', 'DESC'], true) ? $monitorSortDir : 'ASC';
uasort($rowsMonitor, function($a, $b) use ($monitorSortBy, $monitorSortDir) {
    $aVal = $a[$monitorSortBy] ?? '';
    $bVal = $b[$monitorSortBy] ?? '';
    $result = strnatcmp((string)$aVal, (string)$bVal);
    return $monitorSortDir === 'DESC' ? -$result : $result;
});

// Pagination monitoring
$monitorPerPage = (int) ($_GET['per_page_monitoring'] ?? 20);
$monitorPerPage = in_array($monitorPerPage, [20, 30, 50, 100, 999999], true) ? $monitorPerPage : 20;
$monitorPage = max(1, (int) ($_GET['page_monitoring'] ?? 1));
$totalMonitorRecords = count($rowsMonitor);
$totalMonitorPages = $monitorPerPage >= 999999 ? 1 : ceil($totalMonitorRecords / $monitorPerPage);
$monitorPage = min($monitorPage, max(1, $totalMonitorPages));
$monitorOffset = ($monitorPage - 1) * $monitorPerPage;
$rowsMonitorPaginated = array_slice($rowsMonitor, $monitorOffset, $monitorPerPage);

$monitorNilaiByNisn = [];
$nisnListPaginated = array_values(array_unique(array_filter(array_map(static function ($row) {
    return (string) ($row['nisn'] ?? '');
}, $rowsMonitorPaginated), static function ($nisn) {
    return $nisn !== '';
})));

if (count($nisnListPaginated) > 0) {
    $placeholders = implode(',', array_fill(0, count($nisnListPaginated), '?'));
    if ($monitorSemester === 'UAM') {
        $sqlNilaiDetail = "SELECT nu.nisn, m.nama_mapel, nu.nilai_angka
            FROM nilai_uam nu
            JOIN mapel m ON nu.mapel_id = m.id
            WHERE nu.nisn IN ({$placeholders})
            ORDER BY m.id";
        $stNilaiDetail = db()->prepare($sqlNilaiDetail);
        $stNilaiDetail->execute($nisnListPaginated);
    } else {
        $sqlNilaiDetail = "SELECT nr.nisn, m.nama_mapel, nr.nilai_angka, nr.is_finalized
            FROM nilai_rapor nr
            JOIN mapel m ON nr.mapel_id = m.id
            WHERE nr.semester = ? AND nr.tahun_ajaran = ? AND nr.nisn IN ({$placeholders})
            ORDER BY m.id";
        $stNilaiDetail = db()->prepare($sqlNilaiDetail);
        $stNilaiDetail->execute(array_merge([(int) $monitorSemester, (string) $setting['tahun_ajaran']], $nisnListPaginated));
    }

    foreach ($stNilaiDetail->fetchAll() as $nilaiRow) {
        $nisnKey = (string) ($nilaiRow['nisn'] ?? '');
        if ($nisnKey === '') {
            continue;
        }
        if (!isset($monitorNilaiByNisn[$nisnKey])) {
            $monitorNilaiByNisn[$nisnKey] = [];
        }
        $monitorNilaiByNisn[$nisnKey][] = [
            'mapel' => (string) ($nilaiRow['nama_mapel'] ?? '-'),
            'nilai' => (float) ($nilaiRow['nilai_angka'] ?? 0),
            'is_finalized' => (int) ($nilaiRow['is_finalized'] ?? 0),
        ];
    }
}

// Helper function for clickable monitoring sort links
$getMonitorSortLink = function($column, $label) use ($monitorSortBy, $monitorSortDir, $monitorSearch, $monitorSemester, $monitorStatus, $monitorPerPage, $monitorKelas) {
    $newDir = ($monitorSortBy === $column && $monitorSortDir === 'ASC') ? 'DESC' : 'ASC';
    $indicator = ($monitorSortBy === $column) ? ($monitorSortDir === 'ASC' ? ' ↑' : ' ↓') : '';
    $url = "index.php?page=data-nilai" .
        "&search_monitoring=" . urlencode($monitorSearch) .
        "&sort_by_monitoring=" . urlencode($column) .
        "&sort_dir_monitoring=" . urlencode($newDir) .
        "&per_page_monitoring=" . urlencode((string) $monitorPerPage) .
        "&semester_view=" . urlencode($monitorSemester) .
        "&status_upload=" . urlencode($monitorStatus) .
        "&kelas_filter_monitoring=" . urlencode($monitorKelas);
    return '<a href="' . e($url) . '" class="text-dark text-decoration-none">' . e($label) . $indicator . '</a>';
};

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-1">Import Data Nilai</h3>
        <p class="text-secondary mb-0">Download template dan upload nilai.</p>
    </div>
    <div class="card-body d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalImportTemplate">
            <i class="bi bi-file-earmark-arrow-up me-1"></i>Import Nilai Template
        </button>
        <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalImportRdm">
            <i class="bi bi-table me-1"></i>Import Leger RDM
        </button>
    </div>
</div>

<?php if ($rdmPreview): ?>
    <?php
    $previewMeta = $rdmPreview['meta'];
    $previewEntries = $rdmPreview['entries'];
    $previewStudentUpdates = is_array($rdmPreview['student_updates'] ?? null) ? $rdmPreview['student_updates'] : [];
    $previewByStudent = [];
    foreach ($previewEntries as $entry) {
        $nisnKey = (string) ($entry['nisn'] ?? '');
        if ($nisnKey === '') {
            continue;
        }

        if (!isset($previewByStudent[$nisnKey])) {
            $previewByStudent[$nisnKey] = [
                'excel_row' => (int) ($entry['excel_row'] ?? 0),
                'nisn' => $nisnKey,
                'nama' => (string) ($entry['nama'] ?? '-'),
                'semester' => (string) ($entry['semester'] ?? '-'),
                'kelas_lama' => (string) ($entry['kelas_lama'] ?? ''),
                'kelas_baru' => (string) ($entry['kelas_baru'] ?? ''),
                'insert_count' => 0,
                'update_count' => 0,
                'details' => [],
            ];
        }

        if (($entry['aksi'] ?? '') === 'INSERT') {
            $previewByStudent[$nisnKey]['insert_count']++;
        } else {
            $previewByStudent[$nisnKey]['update_count']++;
        }

        $previewByStudent[$nisnKey]['details'][] = [
            'mapel_nama' => (string) ($entry['mapel_nama'] ?? '-'),
            'nilai_lama' => $entry['nilai_lama'] ?? null,
            'nilai_baru' => (float) ($entry['nilai_baru'] ?? 0),
            'aksi' => (string) ($entry['aksi'] ?? 'UPDATE'),
        ];
    }
    $previewStudentList = array_values($previewByStudent);
    ?>
    <div class="card border-warning shadow-sm mb-3">
        <div class="card-header bg-warning-subtle border-0 pt-3">
            <h3 class="mb-1">Preview Import RDM</h3>
            <p class="text-secondary mb-0">Periksa semua baris yang akan diproses. Data belum masuk database sebelum tombol konfirmasi ditekan.</p>
        </div>
        <div class="card-body">
            <div class="row g-2 small mb-3">
                <div class="col-md-3"><strong>Tahun Ajaran:</strong> <?= e((string) ($previewMeta['tahun_ajaran'] ?? '-')) ?></div>
                <div class="col-md-3"><strong>Tahun Ajaran File RDM:</strong> <?= e((string) (($previewMeta['tahun_ajaran_file'] ?? '') !== '' ? $previewMeta['tahun_ajaran_file'] : '-')) ?></div>
                <div class="col-md-3"><strong>Kelas Terdeteksi (Header):</strong> <?= e((string) (($previewMeta['kelas_detected'] ?? '') !== '' ? $previewMeta['kelas_detected'] : '-')) ?></div>
                <div class="col-md-3"><strong>Insert:</strong> <?= e((string) ((int) ($previewMeta['insert_count'] ?? 0))) ?></div>
                <div class="col-md-3"><strong>Update:</strong> <?= e((string) ((int) ($previewMeta['update_count'] ?? 0))) ?></div>
                <div class="col-md-3"><strong>Update Biodata Siswa:</strong> <?= e((string) count($previewStudentUpdates)) ?></div>
                <div class="col-md-3"><strong>Dilewati tanpa NISN:</strong> <?= e((string) ((int) ($previewMeta['skip_no_nisn'] ?? 0))) ?></div>
                <div class="col-md-3"><strong>Dilewati siswa nonaktif/tidak ditemukan:</strong> <?= e((string) ((int) ($previewMeta['skip_no_siswa'] ?? 0))) ?></div>
                <div class="col-md-3"><strong>Dilewati semester tidak sesuai:</strong> <?= e((string) ((int) ($previewMeta['skip_semester'] ?? 0))) ?></div>
                <div class="col-md-3"><strong>Dilewati nilai di luar 70-100:</strong> <?= e((string) ((int) ($previewMeta['skip_range'] ?? 0))) ?></div>
                <div class="col-md-3"><strong>Dilewati karena nilai sama:</strong> <?= e((string) ((int) ($previewMeta['skip_unchanged'] ?? 0))) ?></div>
            </div>

            <div class="d-flex gap-2 mb-3">
                <form method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="confirm_import_rapor_rdm">
                    <button type="submit" class="btn btn-success">Konfirmasi Import RDM</button>
                </form>
                <form method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="cancel_preview_rapor_rdm">
                    <button type="submit" class="btn btn-outline-secondary">Batalkan Preview</button>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Baris Excel</th>
                        <th>NISN</th>
                        <th>Nama</th>
                        <th>Semester</th>
                        <th>Kelas Lama</th>
                        <th>Kelas Baru</th>
                        <th>Total Mapel</th>
                        <th>Insert</th>
                        <th>Update</th>
                        <th>Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $previewNo = 1; foreach ($previewStudentList as $studentPreview): ?>
                        <?php $modalId = 'modalPreviewNilai' . md5((string) ($studentPreview['nisn'] ?? '')); ?>
                        <tr>
                            <td><?= e((string) $previewNo++) ?></td>
                            <td><?= e((string) ($studentPreview['excel_row'] > 0 ? $studentPreview['excel_row'] : '-')) ?></td>
                            <td><?= e((string) ($studentPreview['nisn'] ?? '-')) ?></td>
                            <td><?= e((string) ($studentPreview['nama'] ?? '-')) ?></td>
                            <td><?= e((string) ($studentPreview['semester'] ?? '-')) ?></td>
                            <td><?= e((string) (($studentPreview['kelas_lama'] ?? '') !== '' ? $studentPreview['kelas_lama'] : '-')) ?></td>
                            <td><?= e((string) (($studentPreview['kelas_baru'] ?? '') !== '' ? $studentPreview['kelas_baru'] : '-')) ?></td>
                            <td><?= e((string) count($studentPreview['details'])) ?></td>
                            <td><?= e((string) ((int) ($studentPreview['insert_count'] ?? 0))) ?></td>
                            <td><?= e((string) ((int) ($studentPreview['update_count'] ?? 0))) ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?= e($modalId) ?>">Lihat Nilai</button>
                            </td>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($previewStudentList as $studentPreview): ?>
                <?php $modalId = 'modalPreviewNilai' . md5((string) ($studentPreview['nisn'] ?? '')); ?>
                <div class="modal fade" id="<?= e($modalId) ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header">
                                <h5 class="modal-title">Detail Nilai - <?= e((string) ($studentPreview['nama'] ?? '-')) ?> (<?= e((string) ($studentPreview['nisn'] ?? '-')) ?>)</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th style="width: 50px;">No</th>
                                            <th>Mapel</th>
                                            <th>Nilai Lama</th>
                                            <th>Nilai Baru</th>
                                            <th>Aksi</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php $detailNo = 1; foreach ($studentPreview['details'] as $detail): ?>
                                            <tr>
                                                <td><?= e((string) $detailNo++) ?></td>
                                                <td><?= e((string) ($detail['mapel_nama'] ?? '-')) ?></td>
                                                <td><?= e((string) (($detail['nilai_lama'] ?? null) !== null ? number_format((float) $detail['nilai_lama'], 2) : '-')) ?></td>
                                                <td><?= e((string) number_format((float) ($detail['nilai_baru'] ?? 0), 2)) ?></td>
                                                <td>
                                                    <span class="badge <?= (($detail['aksi'] ?? '') === 'INSERT') ? 'text-bg-primary' : 'text-bg-warning' ?>">
                                                        <?= e((string) ($detail['aksi'] ?? '-')) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-1">Monitoring Upload Nilai (TA <?= e($setting['tahun_ajaran']) ?>)</h3>
        <p class="text-secondary mb-0">Filter berdasarkan semester dan status upload, serta cari berdasarkan nama/NIS/NISN siswa.</p>
    </div>
    <div class="card-body">
        <form method="get" class="row g-2 mb-3">
            <input type="hidden" name="page" value="data-nilai">
            <div class="col-md-2">
                <select name="semester_view" class="form-select form-select-sm">
                    <?php foreach ($monitorSemesterOptions as $semOpt): ?>
                        <option value="<?= e($semOpt) ?>" <?= $monitorSemester === $semOpt ? 'selected' : '' ?>>Semester <?= e($semOpt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status_upload" class="form-select form-select-sm">
                    <option value="all" <?= $monitorStatus === 'all' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="uploaded" <?= $monitorStatus === 'uploaded' ? 'selected' : '' ?>>Sudah Upload</option>
                    <option value="not_uploaded" <?= $monitorStatus === 'not_uploaded' ? 'selected' : '' ?>>Belum Upload</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="kelas_filter_monitoring" class="form-select form-select-sm">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($kelasMonitorOptions as $kelasOpt): ?>
                        <option value="<?= e($kelasOpt) ?>" <?= $monitorKelas === $kelasOpt ? 'selected' : '' ?>><?= e($kelasOpt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="search_monitoring" class="form-control form-control-sm" placeholder="Cari Nama/NIS/NISN..." value="<?= e($monitorSearch) ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-success btn-sm w-100">Cari</button>
            </div>
            <div class="col-md-2">
                <a href="index.php?page=data-nilai" class="btn btn-outline-secondary btn-sm w-100">Reset Filter</a>
            </div>
        </form>

        <div class="row mb-2 align-items-center small">
            <div class="col-md-6 text-secondary">Total: <?= e(number_format($totalMonitorRecords)) ?> siswa <?php if ($totalMonitorPages > 1): ?>(Halaman <?= e((string) $monitorPage) ?> dari <?= e((string) $totalMonitorPages) ?>)<?php endif; ?></div>
            <div class="col-md-6 text-end">
                <select id="perPageMonitorSelect" class="form-select form-select-sm d-inline-block" style="width: auto;">
                    <option value="20" <?= $monitorPerPage === 20 ? 'selected' : '' ?>>20 per halaman</option>
                    <option value="30" <?= $monitorPerPage === 30 ? 'selected' : '' ?>>30 per halaman</option>
                    <option value="50" <?= $monitorPerPage === 50 ? 'selected' : '' ?>>50 per halaman</option>
                    <option value="100" <?= $monitorPerPage === 100 ? 'selected' : '' ?>>100 per halaman</option>
                    <option value="999999" <?= $monitorPerPage === 999999 ? 'selected' : '' ?>>Semua</option>
                </select>
            </div>
        </div>
        <script>
            document.getElementById('perPageMonitorSelect').addEventListener('change', function() {
                const url = new URL(window.location);
                url.searchParams.set('per_page_monitoring', this.value);
                url.searchParams.set('page_monitoring', '1');
                window.location = url;
            });
        </script>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th><?php echo $getMonitorSortLink('nisn', 'NISN'); ?></th>
                    <th><?php echo $getMonitorSortLink('nis', 'NIS'); ?></th>
                    <th><?php echo $getMonitorSortLink('nama', 'Nama'); ?></th>
                    <th>Kelas</th>
                    <th>No. Absen</th>
                    <th><?php echo $getMonitorSortLink('current_semester', 'Semester Aktif'); ?></th>
                    <th>Jumlah Entri</th>
                    <th><?php echo $getMonitorSortLink('status_label', 'Status'); ?></th>
                    <th class="text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($rowsMonitorPaginated) === 0): ?>
                    <tr>
                        <td colspan="10" class="text-center text-secondary">Tidak ada data untuk filter ini.</td>
                    </tr>
                <?php else: ?>
                    <?php $noCounter = $monitorOffset + 1; foreach ($rowsMonitorPaginated as $row): ?>
                        <tr>
                            <td><?= e((string) $noCounter++) ?></td>
                            <td><?= e($row['nisn']) ?></td>
                            <td><?= e($row['nis']) ?></td>
                            <td><?= e($row['nama']) ?></td>
                            <td><?= e($row['kelas']) ?></td>
                            <td><?= e($row['nomor_absen']) ?></td>
                            <td><?= e($row['current_semester']) ?></td>
                            <td><?= e((string) $row['entry_count']) ?></td>
                            <td>
                                <span class="badge <?= $row['uploaded'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= e($row['status_label']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php $modalId = 'modalNilaiMonitor' . md5((string) ($row['nisn'] ?? '') . '|' . (string) $monitorSemester); ?>
                                <?php if ($row['uploaded']): ?>
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?= e($modalId) ?>" title="Lihat Nilai">
                                            <i class="bi bi-card-list"></i>
                                        </button>
                                        <form method="post" class="d-inline" data-confirm="Yakin ingin mengosongkan semua nilai siswa ini pada semester terpilih?" data-confirm-title="Konfirmasi Kosongkan Nilai">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="kosongkan_nilai_siswa">
                                            <input type="hidden" name="nisn" value="<?= e($row['nisn']) ?>">
                                            <input type="hidden" name="semester_target" value="<?= e($monitorSemester) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Kosongkan Nilai">
                                                <i class="bi bi-eraser"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="if(confirm('Nonaktifkan siswa <?= e($row['nama']) ?>? Status akan diubah menjadi Tidak Melanjutkan.')) document.getElementById('formNonaktif<?= e($row['nisn']) ?>').submit();" title="Nonaktifkan">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                    <form id="formNonaktif<?= e($row['nisn']) ?>" method="post" class="d-none">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="nonaktifkan_siswa">
                                        <input type="hidden" name="nisn" value="<?= e($row['nisn']) ?>">
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php foreach ($rowsMonitorPaginated as $row): ?>
            <?php if (!$row['uploaded']) { continue; } ?>
            <?php $modalId = 'modalNilaiMonitor' . md5((string) ($row['nisn'] ?? '') . '|' . (string) $monitorSemester); ?>
            <?php $nilaiList = $monitorNilaiByNisn[(string) ($row['nisn'] ?? '')] ?? []; ?>
            <?php
                $totalNilaiSiswa = 0.0;
                $jumlahMapelSiswa = count($nilaiList);
                $finalizedYa = 0;
                $finalizedBelum = 0;
                foreach ($nilaiList as $nilaiRowCalc) {
                    $totalNilaiSiswa += (float) ($nilaiRowCalc['nilai'] ?? 0);
                    if ($monitorSemester !== 'UAM') {
                        if ((int) ($nilaiRowCalc['is_finalized'] ?? 0) === 1) {
                            $finalizedYa++;
                        } else {
                            $finalizedBelum++;
                        }
                    }
                }
                $rataRataNilaiSiswa = $jumlahMapelSiswa > 0 ? ($totalNilaiSiswa / $jumlahMapelSiswa) : 0.0;
                $statusFinalisasiSiswa = ($monitorSemester !== 'UAM' && $jumlahMapelSiswa > 0 && $finalizedBelum === 0) ? 'Sudah' : 'Belum';
            ?>
            <div class="modal fade" id="<?= e($modalId) ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header">
                            <h5 class="modal-title">Nilai Siswa: <?= e((string) ($row['nama'] ?? '-')) ?> (<?= e((string) ($row['nisn'] ?? '-')) ?>)</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="small text-secondary mb-2">
                                Semester: <strong><?= e((string) $monitorSemester) ?></strong>
                                <?php if ($monitorSemester !== 'UAM'): ?>
                                    | Tahun Ajaran: <strong><?= e((string) $setting['tahun_ajaran']) ?></strong>
                                <?php endif; ?>
                            </div>
                            <?php if ($monitorSemester !== 'UAM'): ?>
                                <div class="small text-secondary mb-2">
                                    Status Finalisasi: <strong><?= e($statusFinalisasiSiswa) ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if (count($nilaiList) > 0): ?>
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th style="width: 50px;">No</th>
                                            <th>Mata Pelajaran</th>
                                            <th style="width: 140px;">Nilai</th>
                                            <th>Terbilang</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php $noNilai = 1; foreach ($nilaiList as $nilaiRow): ?>
                                            <tr>
                                                <td><?= e((string) $noNilai++) ?></td>
                                                <td><?= e((string) ($nilaiRow['mapel'] ?? '-')) ?></td>
                                                <td><?= e((string) round((float) ($nilaiRow['nilai'] ?? 0))) ?></td>
                                                <td><?= e(ucwords(terbilang_bulat((int) round((float) ($nilaiRow['nilai'] ?? 0))))) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-secondary fw-semibold">
                                            <td colspan="2">Jumlah Nilai</td>
                                            <td><?= e((string) round($totalNilaiSiswa)) ?></td>
                                            <td><?= e(ucwords(terbilang_bulat((int) round($totalNilaiSiswa)))) ?></td>
                                        </tr>
                                        <tr class="table-secondary fw-semibold">
                                            <td colspan="2">Rata-Rata Nilai</td>
                                            <td><?= e(number_format($rataRataNilaiSiswa, 2, ',', '')) ?></td>
                                            <td><?= e(ucwords(terbilang_nilai($rataRataNilaiSiswa))) ?></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-secondary text-center mb-0">Belum ada nilai untuk semester ini.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($totalMonitorPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                    <?php if ($monitorPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=data-nilai&semester_view=<?= e($monitorSemester) ?>&status_upload=<?= e($monitorStatus) ?>&kelas_filter_monitoring=<?= e($monitorKelas) ?>&search_monitoring=<?= e($monitorSearch) ?>&sort_by_monitoring=<?= e($monitorSortBy) ?>&sort_dir_monitoring=<?= e($monitorSortDir) ?>&per_page_monitoring=<?= e((string) $monitorPerPage) ?>&page_monitoring=1">Pertama</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=data-nilai&semester_view=<?= e($monitorSemester) ?>&status_upload=<?= e($monitorStatus) ?>&kelas_filter_monitoring=<?= e($monitorKelas) ?>&search_monitoring=<?= e($monitorSearch) ?>&sort_by_monitoring=<?= e($monitorSortBy) ?>&sort_dir_monitoring=<?= e($monitorSortDir) ?>&per_page_monitoring=<?= e((string) $monitorPerPage) ?>&page_monitoring=<?= e((string) ($monitorPage - 1)) ?>">Sebelumnya</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($p = max(1, $monitorPage - 2); $p <= min($totalMonitorPages, $monitorPage + 2); $p++): ?>
                        <li class="page-item <?= $p === $monitorPage ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?page=data-nilai&semester_view=<?= e($monitorSemester) ?>&status_upload=<?= e($monitorStatus) ?>&kelas_filter_monitoring=<?= e($monitorKelas) ?>&search_monitoring=<?= e($monitorSearch) ?>&sort_by_monitoring=<?= e($monitorSortBy) ?>&sort_dir_monitoring=<?= e($monitorSortDir) ?>&per_page_monitoring=<?= e((string) $monitorPerPage) ?>&page_monitoring=<?= e((string) $p) ?>"><?= e((string) $p) ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($monitorPage < $totalMonitorPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=data-nilai&semester_view=<?= e($monitorSemester) ?>&status_upload=<?= e($monitorStatus) ?>&kelas_filter_monitoring=<?= e($monitorKelas) ?>&search_monitoring=<?= e($monitorSearch) ?>&sort_by_monitoring=<?= e($monitorSortBy) ?>&sort_dir_monitoring=<?= e($monitorSortDir) ?>&per_page_monitoring=<?= e((string) $monitorPerPage) ?>&page_monitoring=<?= e((string) ($monitorPage + 1)) ?>">Selanjutnya</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=data-nilai&semester_view=<?= e($monitorSemester) ?>&status_upload=<?= e($monitorStatus) ?>&kelas_filter_monitoring=<?= e($monitorKelas) ?>&search_monitoring=<?= e($monitorSearch) ?>&sort_by_monitoring=<?= e($monitorSortBy) ?>&sort_dir_monitoring=<?= e($monitorSortDir) ?>&per_page_monitoring=<?= e((string) $monitorPerPage) ?>&page_monitoring=<?= e((string) $totalMonitorPages) ?>">Terakhir</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalImportTemplate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Import Nilai Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary mb-3">Download template lalu upload file nilai rapor/UAM dari form berikut.</p>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <form method="post">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="download_template_rapor">
                            <button type="submit" class="btn btn-outline-success w-100">Download Template Rapor</button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="post">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="download_template_uam">
                            <button type="submit" class="btn btn-outline-primary w-100">Download Template UAM</button>
                        </form>
                    </div>
                </div>

                <div class="card border-0 bg-light mb-3">
                    <div class="card-body">
                        <h6 class="mb-2">Import Excel Nilai Rapor</h6>
                        <form method="post" enctype="multipart/form-data" class="row g-3">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="import_rapor">
                            <div class="col-md-8">
                                <label class="form-label">File Excel (.xlsx/.xls)</label>
                                <input type="file" class="form-control" name="file_excel" accept=".xlsx,.xls" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-success w-100">Import Rapor</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($semesterAktif === 'GENAP'): ?>
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h6 class="mb-2">Import Excel Nilai UAM (Semester Akhir)</h6>
                            <form method="post" enctype="multipart/form-data" class="row g-3">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="import_uam">
                                <div class="col-md-8">
                                    <label class="form-label">File Excel (.xlsx/.xls)</label>
                                    <input type="file" class="form-control" name="file_excel" accept=".xlsx,.xls" required>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">Import UAM</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalImportRdm" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Import Leger RDM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary mb-3">Upload file ekspor leger RDM untuk dipreview terlebih dahulu sebelum data disimpan.</p>

                <div class="card border-0 bg-light mb-3">
                    <div class="card-body">
                        <h6 class="mb-2">Import Nilai RDM (Leger per Kelas)</h6>
                        <p class="text-secondary small mb-2">Format ekspor RDM dengan metadata kelas + header bertingkat bisa langsung diproses. Sistem tetap berpatokan NISN, lalu kelas siswa di aplikasi akan disesuaikan mengikuti kelas di file (kolom kelas pada baris, atau metadata/header jika kolom kelas kosong).</p>
                        <form method="post" enctype="multipart/form-data" class="row g-3">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="preview_rapor_rdm">
                            <div class="col-md-8">
                                <label class="form-label">File Excel RDM (.xlsx/.xls)</label>
                                <input type="file" class="form-control" name="file_excel" accept=".xlsx,.xls" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-warning w-100">Preview RDM Per Kelas</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require dirname(__DIR__) . '/partials/footer.php';
