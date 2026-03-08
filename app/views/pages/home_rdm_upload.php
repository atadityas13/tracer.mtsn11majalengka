<?php
/**
 * Landing publik upload nilai RDM untuk guru/wali kelas.
 */
declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;

$setting = setting_akademik();
$semesterAktif = strtoupper((string) ($setting['semester_aktif'] ?? 'GANJIL'));
$targetRapor = semester_upload_target($semesterAktif);
$targetSemesterLabel = implode(', ', array_map('strval', $targetRapor));
$flash = get_flash();
$homePreviewSessionKey = 'home_rdm_preview';
$tokenSetting = get_upload_token_setting();
$requireUploadToken = $tokenSetting['require_token'];
$tokenMode = $tokenSetting['token_mode'];
$currentUploadToken = $requireUploadToken && $tokenMode !== 'disabled' ? get_current_upload_token() : null;

if (!function_exists('rdm_normalize_header')) {
    function rdm_normalize_header(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = str_replace(['.', '-', '/', "'"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }
}

if (!function_exists('rdm_parse_import_header')) {
    function rdm_parse_import_header(array $headerRow, array $nisnHeaderCandidates, array $aliasToMapelId, array $mapelByName): array
    {
        $nisnIndex = null;
        $kelasIndex = null;
        $nomorAbsenIndex = null;
        $mapelColumns = [];

        foreach ($headerRow as $index => $header) {
            $headerKey = rdm_normalize_header((string) $header);
            if ($headerKey === '') {
                continue;
            }

            if ($nisnIndex === null && in_array($headerKey, $nisnHeaderCandidates, true)) {
                $nisnIndex = (int) $index;
                continue;
            }

            if ($kelasIndex === null && $headerKey === 'KELAS') {
                $kelasIndex = (int) $index;
                continue;
            }

            if ($nomorAbsenIndex === null && in_array($headerKey, ['NOMOR ABSEN', 'NO ABSEN', 'ABSEN'], true)) {
                $nomorAbsenIndex = (int) $index;
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
            'mapel_columns' => $mapelColumns,
        ];
    }
}

if (!function_exists('rdm_build_combined_header_row')) {
    function rdm_build_combined_header_row(array $topRow, array $bottomRow): array
    {
        $length = max(count($topRow), count($bottomRow));
        $combined = [];

        for ($i = 0; $i < $length; $i++) {
            $top = trim((string) ($topRow[$i] ?? ''));
            $bottom = trim((string) ($bottomRow[$i] ?? ''));

            $topKey = rdm_normalize_header($top);
            $bottomKey = rdm_normalize_header($bottom);

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

if (!function_exists('rdm_detect_import_layout')) {
    function rdm_detect_import_layout(array $rows, array $nisnHeaderCandidates, array $aliasToMapelId, array $mapelByName): array
    {
        $best = [
            'score' => -1,
            'data_start_row' => 1,
            'nisn_index' => null,
            'kelas_index' => null,
            'nomor_absen_index' => null,
            'mapel_columns' => [],
        ];

        $maxRows = min(30, count($rows));

        for ($i = 0; $i < $maxRows; $i++) {
            $single = rdm_parse_import_header($rows[$i] ?? [], $nisnHeaderCandidates, $aliasToMapelId, $mapelByName);
            if ($single['nisn_index'] !== null && count($single['mapel_columns']) > 0) {
                $singleScore = count($single['mapel_columns']) + ($single['kelas_index'] !== null ? 1 : 0);
                if ($singleScore > $best['score']) {
                    $best = [
                        'score' => $singleScore,
                        'data_start_row' => $i + 1,
                        'nisn_index' => $single['nisn_index'],
                        'kelas_index' => $single['kelas_index'],
                        'nomor_absen_index' => $single['nomor_absen_index'],
                        'mapel_columns' => $single['mapel_columns'],
                    ];
                }
            }

            if ($i + 1 >= $maxRows) {
                continue;
            }

            $combinedHeader = rdm_build_combined_header_row($rows[$i] ?? [], $rows[$i + 1] ?? []);
            $combined = rdm_parse_import_header($combinedHeader, $nisnHeaderCandidates, $aliasToMapelId, $mapelByName);

            if ($combined['nisn_index'] !== null && count($combined['mapel_columns']) > 0) {
                $combinedScore = count($combined['mapel_columns']) + 2 + ($combined['kelas_index'] !== null ? 1 : 0);
                if ($combinedScore > $best['score']) {
                    $best = [
                        'score' => $combinedScore,
                        'data_start_row' => $i + 2,
                        'nisn_index' => $combined['nisn_index'],
                        'kelas_index' => $combined['kelas_index'],
                        'nomor_absen_index' => $combined['nomor_absen_index'],
                        'mapel_columns' => $combined['mapel_columns'],
                    ];
                }
            }
        }

        return $best;
    }
}

if (!function_exists('rdm_normalize_tahun_ajaran')) {
    function rdm_normalize_tahun_ajaran(string $value): string
    {
        if (preg_match('/\b(20\d{2})\s*[\/\-]\s*(20\d{2})\b/', $value, $m)) {
            return $m[1] . '/' . $m[2];
        }

        return '';
    }
}

if (!function_exists('rdm_detect_tahun_ajaran')) {
    function rdm_detect_tahun_ajaran(array $rows): string
    {
        $maxRows = min(25, count($rows));

        for ($r = 0; $r < $maxRows; $r++) {
            foreach (($rows[$r] ?? []) as $cell) {
                $text = trim((string) $cell);
                if ($text === '') {
                    continue;
                }

                $detected = rdm_normalize_tahun_ajaran($text);
                if ($detected !== '') {
                    return $detected;
                }
            }
        }

        return '';
    }
}

if (!function_exists('rdm_detect_kelas')) {
    function rdm_detect_kelas(array $rows): string
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

if (!function_exists('rdm_validate_export_signature')) {
    function rdm_validate_export_signature(array $rows): array
    {
        $maxRows = min(25, count($rows));
        $tokens = [];

        for ($r = 0; $r < $maxRows; $r++) {
            foreach (($rows[$r] ?? []) as $cell) {
                $text = trim((string) $cell);
                if ($text === '') {
                    continue;
                }

                $tokens[] = rdm_normalize_header($text);
            }
        }

        $joined = ' ' . implode(' ', $tokens) . ' ';

        $hasMadrasah = strpos($joined, ' MADRASAH ') !== false || strpos($joined, ' MTS ') !== false;
        $hasSemester = strpos($joined, ' SEMESTER ') !== false;
        $hasNilai = strpos($joined, ' NILAI ') !== false || strpos($joined, ' RAPOR ') !== false || strpos($joined, ' LEGER ') !== false;
        $hasTahunAjaran = strpos($joined, ' TAHUN AJARAN ') !== false || strpos($joined, ' THN AJARAN ') !== false;

        if ($hasMadrasah && $hasSemester && $hasNilai && $hasTahunAjaran) {
            return ['valid' => true, 'reason' => ''];
        }

        $missing = [];
        if (!$hasMadrasah) {
            $missing[] = 'identitas madrasah';
        }
        if (!$hasSemester) {
            $missing[] = 'informasi semester';
        }
        if (!$hasNilai) {
            $missing[] = 'judul data nilai/rapor';
        }
        if (!$hasTahunAjaran) {
            $missing[] = 'informasi tahun ajaran';
        }

        return [
            'valid' => false,
            'reason' => 'Penanda file RDM tidak lengkap (' . implode(', ', $missing) . ').',
        ];
    }
}

$mapelRows = db()->query('SELECT id, nama_mapel FROM mapel')->fetchAll();
$mapelByName = [];
foreach ($mapelRows as $m) {
    $mapelByName[rdm_normalize_header((string) $m['nama_mapel'])] = (int) $m['id'];
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
    $aliasKey = rdm_normalize_header($alias);
    $targetKey = rdm_normalize_header($targetName);
    if (isset($mapelByName[$targetKey])) {
        $aliasToMapelId[$aliasKey] = (int) $mapelByName[$targetKey];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('home');

    $action = (string) ($_POST['action'] ?? 'preview_upload');

    if ($action === 'cancel_preview_upload') {
        unset($_SESSION[$homePreviewSessionKey]);
        set_flash('info', 'Preview upload dibatalkan. Tidak ada data yang diubah.');
        redirect('index.php?page=home');
    }

    if ($action === 'confirm_upload') {
        $preview = $_SESSION[$homePreviewSessionKey] ?? null;
        if (!is_array($preview) || !is_array($preview['entries'] ?? null) || !is_array($preview['student_updates'] ?? null)) {
            set_flash('error', 'Preview tidak ditemukan. Silakan upload dan preview ulang file RDM.');
            redirect('index.php?page=home');
        }

        if ((string) ($preview['tahun_ajaran'] ?? '') !== (string) $setting['tahun_ajaran']) {
            unset($_SESSION[$homePreviewSessionKey]);
            set_flash('error', 'Preview sudah kedaluwarsa karena tahun ajaran aktif berubah. Silakan preview ulang.');
            redirect('index.php?page=home');
        }

        // Validate internal verification token (double-submit protection)
        $tokenInput = strtoupper(trim((string) ($_POST['verification_token'] ?? '')));
        $tokenExpected = strtoupper(trim((string) ($preview['verification_token'] ?? '')));
        if ($tokenExpected === '' || $tokenInput === '' || !hash_equals($tokenExpected, $tokenInput)) {
            set_flash('error', 'Token verifikasi tidak valid. Proses simpan dibatalkan.');
            redirect('index.php?page=home');
        }

        // Validate admin upload token if required
        if ($requireUploadToken && $tokenMode !== 'disabled') {
            $adminTokenInput = strtoupper(trim((string) ($_POST['admin_upload_token'] ?? '')));
            if ($adminTokenInput === '') {
                set_flash('error', 'Token dari admin/kurikulum diperlukan. Silakan hubungi admin untuk mendapatkan token.');
                redirect('index.php?page=home');
            }
            
            if (!validate_upload_token($adminTokenInput)) {
                set_flash('error', 'Token dari admin tidak valid, sudah expired, atau sudah digunakan. Silakan minta token baru ke admin.');
                redirect('index.php?page=home');
            }
        }

        $nisnListPreview = array_values(array_unique(array_filter(array_map(static function ($row) {
            return (string) ($row['nisn'] ?? '');
        }, $preview['entries']), static function ($nisn) {
            return $nisn !== '';
        })));

        if (count($nisnListPreview) === 0) {
            set_flash('error', 'Data preview tidak valid. Silakan preview ulang.');
            redirect('index.php?page=home');
        }

        $placeholdersPreview = implode(',', array_fill(0, count($nisnListPreview), '?'));
        $sqlNilaiSudahAda = "SELECT DISTINCT nisn FROM nilai_rapor WHERE tahun_ajaran = ? AND nisn IN ({$placeholdersPreview})";
        $stNilaiSudahAda = db()->prepare($sqlNilaiSudahAda);
        $stNilaiSudahAda->execute(array_merge([(string) $setting['tahun_ajaran']], $nisnListPreview));
        $sudahAdaRows = $stNilaiSudahAda->fetchAll();
        if (count($sudahAdaRows) > 0) {
            $nisnSudahAda = array_map(static function ($row) {
                return (string) ($row['nisn'] ?? '');
            }, $sudahAdaRows);
            $previewNisn = implode(', ', array_slice($nisnSudahAda, 0, 10));
            $suffix = count($nisnSudahAda) > 10 ? ' dan lainnya' : '';
            unset($_SESSION[$homePreviewSessionKey]);
            set_flash('error', 'Simpan dibatalkan. Ditemukan data nilai tahun ajaran aktif untuk NISN: ' . $previewNisn . $suffix . '. Silakan hubungi admin.');
            redirect('index.php?page=home');
        }

        db()->beginTransaction();
        try {
            $updatedSiswaCount = 0;
            $stUpdateSiswa = db()->prepare('UPDATE siswa
                SET kelas = CASE WHEN :kelas_set = 1 THEN :kelas ELSE kelas END,
                    nomor_absen = CASE WHEN :absen_set = 1 THEN :nomor_absen ELSE nomor_absen END
                WHERE nisn = :nisn');
            $stInsertRapor = db()->prepare('INSERT INTO nilai_rapor (nisn, mapel_id, semester, tahun_ajaran, nilai_angka, is_finalized)
                VALUES (:nisn, :mapel, :semester, :ta, :nilai, 0)
                ON DUPLICATE KEY UPDATE nilai_angka = VALUES(nilai_angka)');

            foreach ($preview['student_updates'] as $nisn => $updateData) {
                if (!is_array($updateData)) {
                    continue;
                }

                $kelasSet = (array_key_exists('kelas', $updateData) && $updateData['kelas'] !== null && (string) $updateData['kelas'] !== '') ? 1 : 0;
                $absenSet = (array_key_exists('nomor_absen', $updateData) && $updateData['nomor_absen'] !== null && (string) $updateData['nomor_absen'] !== '') ? 1 : 0;
                if ($kelasSet === 0 && $absenSet === 0) {
                    continue;
                }

                $stUpdateSiswa->execute([
                    'nisn' => (string) $nisn,
                    'kelas_set' => $kelasSet,
                    'kelas' => $kelasSet === 1 ? (string) $updateData['kelas'] : '',
                    'absen_set' => $absenSet,
                    'nomor_absen' => $absenSet === 1 ? (int) $updateData['nomor_absen'] : 0,
                ]);
                $updatedSiswaCount++;
            }

            $countProcessed = 0;
            $countInserted = 0;
            $countUpdated = 0;
            foreach ($preview['entries'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $stInsertRapor->execute([
                    'nisn' => (string) ($entry['nisn'] ?? ''),
                    'mapel' => (int) ($entry['mapel_id'] ?? 0),
                    'semester' => (int) ($entry['semester'] ?? 0),
                    'ta' => (string) $setting['tahun_ajaran'],
                    'nilai' => (float) ($entry['nilai_baru'] ?? 0),
                ]);

                if ($stInsertRapor->rowCount() === 1) {
                    $countInserted++;
                } else {
                    $countUpdated++;
                }
                $countProcessed++;
            }

            db()->commit();
            
            // Mark admin token as used after successful upload
            if ($requireUploadToken && $tokenMode !== 'disabled') {
                $adminTokenInput = strtoupper(trim((string) ($_POST['admin_upload_token'] ?? '')));
                mark_upload_token_used($adminTokenInput, current_user()['username'] ?? 'unknown');
            }
            
            unset($_SESSION[$homePreviewSessionKey]);
            set_flash('success', 'Konfirmasi upload berhasil. Diproses: ' . $countProcessed . ' nilai (insert: ' . $countInserted . ', update: ' . $countUpdated . '), data siswa diperbarui: ' . $updatedSiswaCount . '.');
        } catch (Throwable $e) {
            db()->rollBack();
            set_flash('error', 'Konfirmasi upload gagal: ' . $e->getMessage());
        }

        redirect('index.php?page=home');
    }

    if ($action !== 'preview_upload') {
        set_flash('error', 'Aksi upload tidak dikenali.');
        redirect('index.php?page=home');
    }

    if (!class_exists(IOFactory::class)) {
        set_flash('error', 'PhpSpreadsheet belum terpasang. Silakan hubungi admin untuk menjalankan composer install.');
        redirect('index.php?page=home');
    }

    $upload = $_FILES['file'] ?? null;
    if (!$upload || (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
        set_flash('error', 'File RDM wajib dipilih terlebih dahulu.');
        redirect('index.php?page=home');
    }

    $ext = strtolower((string) pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls'], true)) {
        set_flash('error', 'Format file harus .xlsx atau .xls.');
        redirect('index.php?page=home');
    }

    try {
        $spreadsheet = IOFactory::load((string) $upload['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
    } catch (Throwable $e) {
        set_flash('error', 'File tidak dapat dibaca: ' . $e->getMessage());
        redirect('index.php?page=home');
    }

    if (count($rows) < 2) {
        set_flash('error', 'Format file tidak valid atau data kosong.');
        redirect('index.php?page=home');
    }

    $layout = rdm_detect_import_layout($rows, ['NISN', 'NISN SISWA', 'NISN S'], $aliasToMapelId, $mapelByName);
    if ($layout['nisn_index'] === null) {
        set_flash('error', 'Kolom NISN tidak ditemukan di template RDM.');
        redirect('index.php?page=home');
    }

    if (count($layout['mapel_columns']) === 0) {
        set_flash('error', 'Kolom mapel tidak dikenali. Pastikan header mapel sesuai template RDM.');
        redirect('index.php?page=home');
    }

    $signatureValidation = rdm_validate_export_signature($rows);
    if (!$signatureValidation['valid']) {
        set_flash('error', 'Upload ditolak. File tidak terverifikasi sebagai ekspor RDM. ' . $signatureValidation['reason']);
        redirect('index.php?page=home');
    }

    $kelasRdm = rdm_detect_kelas($rows);
    if ($kelasRdm === '') {
        set_flash('error', 'Upload ditolak. Kelas pada header file RDM tidak ditemukan. Gunakan file hasil ekspor RDM asli.');
        redirect('index.php?page=home');
    }

    $tahunAjaranFile = rdm_detect_tahun_ajaran($rows);
    if ($tahunAjaranFile === '') {
        set_flash('error', 'Upload ditolak. Tahun ajaran pada file RDM tidak terbaca. Gunakan file hasil ekspor RDM asli.');
        redirect('index.php?page=home');
    }

    if ($tahunAjaranFile !== (string) $setting['tahun_ajaran']) {
        set_flash('error', 'Tahun ajaran pada file (' . $tahunAjaranFile . ') tidak cocok dengan pengaturan aktif (' . $setting['tahun_ajaran'] . ').');
        redirect('index.php?page=home');
    }

    $nisnList = [];
    $parsedRows = [];

    for ($i = (int) $layout['data_start_row']; $i < count($rows); $i++) {
        $row = $rows[$i] ?? [];
        $nisnRaw = trim((string) ($row[(int) $layout['nisn_index']] ?? ''));
        $nisn = preg_replace('/\D+/', '', $nisnRaw);

        if ($nisn === '') {
            continue;
        }

        $nilaiByMapel = [];
        foreach ($layout['mapel_columns'] as $colIndex => $mapelId) {
            $rawNilai = trim((string) ($row[(int) $colIndex] ?? ''));
            if ($rawNilai === '') {
                continue;
            }

            $rawNilai = str_replace(',', '.', $rawNilai);
            if (!is_numeric($rawNilai)) {
                continue;
            }

            $nilai = (float) $rawNilai;
            if ($nilai < 7 || $nilai > 100) {
                continue;
            }

            $nilaiByMapel[(int) $mapelId] = round($nilai, 2);
        }

        if (count($nilaiByMapel) === 0) {
            continue;
        }

        $kelasBaris = null;
        $absenBaris = null;

        if ($layout['kelas_index'] !== null) {
            $kelasRaw = trim((string) ($row[(int) $layout['kelas_index']] ?? ''));
            if ($kelasRaw !== '') {
                $kelasBaris = $kelasRaw;
            }
        }

        if ($layout['nomor_absen_index'] !== null) {
            $absenRaw = trim((string) ($row[(int) $layout['nomor_absen_index']] ?? ''));
            if ($absenRaw !== '' && ctype_digit($absenRaw)) {
                $absen = (int) $absenRaw;
                if ($absen >= 1 && $absen <= 50) {
                    $absenBaris = $absen;
                }
            }
        }

        $nisnList[$nisn] = $nisn;
        $parsedRows[] = [
            'nisn' => $nisn,
            'kelas' => $kelasBaris,
            'nomor_absen' => $absenBaris,
            'nilai' => $nilaiByMapel,
        ];
    }

    if (count($parsedRows) === 0) {
        set_flash('error', 'Tidak ada data nilai valid yang dapat diproses dari file ini.');
        redirect('index.php?page=home');
    }

    $placeholders = implode(',', array_fill(0, count($nisnList), '?'));
    $sqlSiswa = "SELECT nisn, current_semester, status_siswa FROM siswa WHERE nisn IN ({$placeholders})";
    $stSiswa = db()->prepare($sqlSiswa);
    $stSiswa->execute(array_values($nisnList));
    $siswaRows = $stSiswa->fetchAll();

    $siswaByNisn = [];
    foreach ($siswaRows as $siswa) {
        $siswaByNisn[(string) $siswa['nisn']] = $siswa;
    }

    $missingNisn = [];
    foreach ($nisnList as $nisnFile) {
        if (!isset($siswaByNisn[$nisnFile])) {
            $missingNisn[] = $nisnFile;
        }
    }

    if (count($missingNisn) > 0) {
        $previewMissing = implode(', ', array_slice($missingNisn, 0, 10));
        $suffix = count($missingNisn) > 10 ? ' dan lainnya' : '';
        set_flash('error', 'Upload dibatalkan. Ditemukan ' . count($missingNisn) . ' NISN pada file yang tidak ada di aplikasi: ' . $previewMissing . $suffix . '. Silakan hubungi admin.');
        redirect('index.php?page=home');
    }

    $placeholdersNilaiCek = implode(',', array_fill(0, count($nisnList), '?'));
    $sqlNilaiSudahAda = "SELECT DISTINCT nisn FROM nilai_rapor WHERE tahun_ajaran = ? AND nisn IN ({$placeholdersNilaiCek})";
    $stNilaiSudahAda = db()->prepare($sqlNilaiSudahAda);
    $stNilaiSudahAda->execute(array_merge([(string) $setting['tahun_ajaran']], array_values($nisnList)));
    $nisnSudahAdaRows = $stNilaiSudahAda->fetchAll();
    if (count($nisnSudahAdaRows) > 0) {
        $nisnSudahAda = array_map(static function ($row) {
            return (string) ($row['nisn'] ?? '');
        }, $nisnSudahAdaRows);
        $previewSudahAda = implode(', ', array_slice($nisnSudahAda, 0, 10));
        $suffixSudahAda = count($nisnSudahAda) > 10 ? ' dan lainnya' : '';
        set_flash('error', 'Upload dibatalkan. Ditemukan siswa yang sudah memiliki nilai pada tahun ajaran aktif: ' . $previewSudahAda . $suffixSudahAda . '.');
        redirect('index.php?page=home');
    }

    $entries = [];
    $studentUpdates = [];
    $countSkipSemester = 0;
    foreach ($parsedRows as $item) {
        $nisn = (string) $item['nisn'];
        $siswa = $siswaByNisn[$nisn];
        $currentSemester = normalize_current_semester($siswa['current_semester']);
        if (($siswa['status_siswa'] ?? '') !== 'Aktif' || !in_array($currentSemester, $targetRapor, true)) {
            $countSkipSemester++;
            continue;
        }

        $kelasLama = trim((string) ($siswa['kelas'] ?? ''));
        $kelasBaru = $item['kelas'] !== null ? trim((string) $item['kelas']) : '';
        $kelasBerubah = $kelasBaru !== '' && rdm_normalize_header($kelasBaru) !== rdm_normalize_header($kelasLama);
        $absenLama = $siswa['nomor_absen'] !== null ? (int) $siswa['nomor_absen'] : null;
        $absenBaru = $item['nomor_absen'] !== null ? (int) $item['nomor_absen'] : null;
        $absenBerubah = $absenBaru !== null && $absenBaru !== $absenLama;

        if ($kelasBerubah || $absenBerubah) {
            if (!isset($studentUpdates[$nisn])) {
                $studentUpdates[$nisn] = [];
            }
            if ($kelasBerubah) {
                $studentUpdates[$nisn]['kelas'] = $kelasBaru;
            }
            if ($absenBerubah) {
                $studentUpdates[$nisn]['nomor_absen'] = $absenBaru;
            }
        }

        foreach ($item['nilai'] as $mapelId => $nilai) {
            $entries[] = [
                'nisn' => $nisn,
                'mapel_id' => (int) $mapelId,
                'semester' => (int) $currentSemester,
                'nilai_baru' => (float) $nilai,
            ];
        }
    }

    if (count($entries) === 0) {
        set_flash('error', 'Tidak ada data yang bisa dipreview. Pastikan siswa aktif berada pada semester target (' . $targetSemesterLabel . ') dengan nilai 7-100.');
        redirect('index.php?page=home');
    }

    $verificationToken = strtoupper(bin2hex(random_bytes(3)));
    $_SESSION[$homePreviewSessionKey] = [
        'tahun_ajaran' => (string) $setting['tahun_ajaran'],
        'generated_at' => date('Y-m-d H:i:s'),
        'verification_token' => $verificationToken,
        'meta' => [
            'entry_count' => count($entries),
            'siswa_update_count' => count($studentUpdates),
            'skip_semester' => $countSkipSemester,
        ],
        'entries' => $entries,
        'student_updates' => $studentUpdates,
    ];

    set_flash('success', 'Preview upload berhasil dibuat. Verifikasi token sebelum konfirmasi simpan. Token: ' . $verificationToken);

    redirect('index.php?page=home');
}

$homePreview = $_SESSION[$homePreviewSessionKey] ?? null;
if (!is_array($homePreview) || !is_array($homePreview['entries'] ?? null) || !is_array($homePreview['meta'] ?? null)) {
    $homePreview = null;
}

$isLoggedIn = current_user() !== null;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(app_config('name')) ?> - Upload Nilai RDM</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/logo-tracer-mtsn11majalengka.png" type="image/png">
</head>
<body>
<div class="landing-shell">
    <div class="landing-blur one"></div>
    <div class="landing-blur two"></div>

    <header class="landing-topbar container py-3 py-md-4">
        <a class="landing-brand" href="index.php?page=home">
            <img src="assets/logo-tracer-mtsn11majalengka.png" alt="TRACER Logo">
            <span>
                <strong>TRACER</strong>
                <small>MTsN 11 Majalengka</small>
            </span>
        </a>

        <a href="index.php?page=<?= $isLoggedIn ? 'dashboard' : 'login' ?>" class="btn btn-light landing-login-btn">
            <i class="bi bi-box-arrow-in-right me-1"></i>
            <?= $isLoggedIn ? 'Masuk Dashboard' : 'Login Admin/Kurikulum' ?>
        </a>
    </header>

    <main class="container pb-5">
        <section class="landing-hero mb-4 mb-lg-5">
            <div class="landing-hero-copy">
                <p class="landing-kicker mb-2">Portal Guru & Wali Kelas</p>
                <h1 class="landing-title">Upload Nilai RDM Cepat, Akurat, dan Selaras Semester Aktif</h1>
                <p class="landing-subtitle mb-4">
                    Halaman ini menjadi akses utama untuk unggah nilai berdasarkan template RDM.
                    Sistem akan memetakan mapel otomatis, memvalidasi semester aktif, dan langsung menyimpan ke data rapor TRACER.
                </p>
                <div class="landing-badges">
                    <span><i class="bi bi-shield-check"></i> Validasi format</span>
                    <span><i class="bi bi-calendar2-check"></i> TA aktif: <?= e((string) $setting['tahun_ajaran']) ?></span>
                    <span><i class="bi bi-layers"></i> Semester target: <?= e($targetSemesterLabel) ?></span>
                </div>
            </div>
            <div class="landing-hero-panel">
                <h2 class="h5 mb-3">Ketentuan Upload</h2>
                <ul class="landing-checklist mb-0">
                    <li>Gunakan file <strong>.xlsx</strong> atau <strong>.xls</strong>.</li>
                    <li>Kolom <strong>NISN</strong> wajib ada di header.</li>
                    <li>Mapel dikenali melalui kode: QH, AA, FIK, SKI, BAR, PP, BINDO, MTK, IPA, IPS, BING, PJOK, INFO, SBP, BSD.</li>
                    <li>Rentang nilai valid: 7 sampai 100.</li>
                    <li>File wajib hasil ekspor asli RDM (metadata kelas, semester, dan tahun ajaran harus valid).</li>
                    <li>Jika ada 1 NISN saja yang tidak ada di aplikasi, seluruh upload akan dibatalkan.</li>
                    <li>Jika ada 1 siswa saja yang sudah punya nilai di tahun ajaran aktif, seluruh upload akan dibatalkan.</li>
                    <li>Data yang sudah finalisasi tidak akan ditimpa.</li>
                </ul>
            </div>
        </section>

        <?php if ($flash && ($flash['type'] ?? '') !== 'success'): ?>
            <div class="alert alert-<?= e((string) ($flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'warning' ? 'warning' : 'info'))) ?> border-0 shadow-sm mb-4" role="alert">
                <i class="bi <?= $flash['type'] === 'error' ? 'bi-exclamation-triangle-fill' : ($flash['type'] === 'warning' ? 'bi-exclamation-circle-fill' : 'bi-info-circle-fill') ?> me-2"></i>
                <?= e((string) ($flash['message'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <section id="uploadProgressArea" class="upload-progress-area mb-4 <?= (($flash['type'] ?? '') === 'success') ? 'is-complete' : '' ?>" aria-live="polite">
            <div class="upload-progress-head">
                <div>
                    <p id="uploadProgressTitle" class="upload-progress-title mb-1">Menyiapkan unggah file...</p>
                    <p id="uploadProgressHint" class="upload-progress-hint mb-0">Silakan tunggu. Validasi template dan sinkronisasi nilai sedang berjalan.</p>
                </div>
                <span id="uploadProgressValue" class="upload-progress-value">0%</span>
            </div>
            <div class="progress upload-progress-bar-wrap" role="progressbar" aria-label="Progress upload" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <div id="uploadProgressBar" class="progress-bar upload-progress-bar" style="width: 0%"></div>
            </div>
            <div class="upload-progress-steps">
                <span id="stepRead"><i class="bi bi-file-earmark-check"></i> Membaca file</span>
                <span id="stepValidate"><i class="bi bi-shield-check"></i> Validasi data</span>
                <span id="stepSave"><i class="bi bi-database-check"></i> Simpan nilai</span>
            </div>
        </section>

        <!-- FORM UPLOAD UTAMA (Centered) -->
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8">
                <section class="landing-upload card border-0 shadow-lg">
                    <div class="card-body p-4 p-lg-5">
                        <div class="mb-4 text-center">
                            <h2 class="h4 fw-bold mb-2">📤 Upload File Nilai RDM</h2>
                            <p class="text-secondary small mb-0">Pilih file template RDM dan sistem akan memvalidasi serta menyimpan secara otomatis</p>
                        </div>

                        <!-- Token Status Alert -->
                        <?php if ($requireUploadToken && $tokenMode !== 'disabled'): ?>
                            <div class="alert alert-warning border-0 mb-4" role="alert">
                                <div class="fw-semibold mb-1"><i class="bi bi-key-fill"></i> Verifikasi Token Diperlukan</div>
                                <div class="small text-secondary">
                                    Anda harus memasukkan token dari admin/kurikulum untuk verifikasi upload.
                                    <?php if ($currentUploadToken): ?>
                                        <span class="d-block mt-1"><strong class="text-success">✓ Token tersedia</strong></span>
                                    <?php else: ?>
                                        <span class="d-block mt-1"><strong class="text-warning">⚠️ Hubungi admin untuk mendapatkan token</strong></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Upload Form -->
                        <form id="rdmUploadForm" method="post" enctype="multipart/form-data">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="preview_upload">
                            
                            <div class="mb-4">
                                <label for="file" class="form-label fw-semibold">Pilih File Template RDM (.xlsx / .xls)</label>
                                <div class="mb-2">
                                    <input type="file" class="form-control form-control-lg" id="file" name="file" accept=".xlsx,.xls" required>
                                </div>
                                <div class="form-text">Struktur header harus sesuai template asli agar mapel terdeteksi otomatis</div>
                            </div>

                            <div class="d-grid gap-2">
                                <button id="uploadSubmitBtn" type="submit" class="btn btn-lg landing-upload-btn">
                                    <i class="bi bi-cloud-arrow-up me-2"></i> Preview Upload
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <!-- Info Box -->
                <div class="mt-4 p-3 bg-light rounded border">
                    <div class="row g-3 small">
                        <div class="col-6 col-md-3 text-center">
                            <div class="fw-bold text-success">Tahun Ajaran</div>
                            <div class="text-secondary"><?= e((string) $setting['tahun_ajaran']) ?></div>
                        </div>
                        <div class="col-6 col-md-3 text-center">
                            <div class="fw-bold text-primary">Semester Target</div>
                            <div class="text-secondary"><?= e($targetSemesterLabel) ?></div>
                        </div>
                        <div class="col-6 col-md-3 text-center">
                            <div class="fw-bold text-info">Range Nilai</div>
                            <div class="text-secondary">7 - 100</div>
                        </div>
                        <div class="col-6 col-md-3 text-center">
                            <div class="fw-bold text-warning">Status</div>
                            <div class="text-secondary"><?= $requireUploadToken && $tokenMode !== 'disabled' ? 'Token ON' : 'Token OFF' ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PREVIEW & CONFIRMATION SECTION (Only show if preview exists) -->
        <?php if ($homePreview): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <section class="card border-2 border-info bg-info-light">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-4">
                                <div class="alert alert-info border-0 flex-grow-1 mb-0">
                                    <div class="fw-semibold mb-1"><i class="bi bi-info-circle-fill me-2"></i>Preview Siap Dikonfirmasi</div>
                                    <div class="small">
                                        <span class="badge bg-info me-2">Baris: <?= e((string) ((int) ($homePreview['meta']['entry_count'] ?? 0))) ?></span>
                                        <span class="badge bg-info me-2">Update Siswa: <?= e((string) ((int) ($homePreview['meta']['siswa_update_count'] ?? 0))) ?></span>
                                        <span class="badge bg-warning">Skip Semester: <?= e((string) ((int) ($homePreview['meta']['skip_semester'] ?? 0))) ?></span>
                                    </div>
                                    <div class="small mt-2 text-secondary">
                                        Token Verifikasi: <code class="text-dark"><?= e((string) ($homePreview['verification_token'] ?? '-')) ?></code>
                                    </div>
                                </div>
                            </div>

                            <form method="post" class="row g-2">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="confirm_upload">
                                
                                <!-- Internal Verification Token -->
                                <div class="col-12">
                                    <label for="verification_token" class="form-label small fw-semibold">Token Verifikasi Konfirmasi</label>
                                    <input type="text" class="form-control" id="verification_token" name="verification_token" placeholder="Masukkan token dari atas" maxlength="12" required>
                                </div>

                                <!-- Admin Upload Token (if required) -->
                                <?php if ($requireUploadToken && $tokenMode !== 'disabled'): ?>
                                    <div class="col-12">
                                        <label for="admin_upload_token" class="form-label small fw-semibold">Token dari Admin/Kurikulum</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="admin_upload_token" name="admin_upload_token" 
                                                   placeholder="<?= $currentUploadToken ? e($currentUploadToken) : 'Minta token ke admin' ?>" 
                                                   maxlength="12" required>
                                            <?php if ($currentUploadToken): ?>
                                                <button class="btn btn-outline-secondary btn-sm" type="button" onclick="copyAdminToken()">
                                                    <i class="bi bi-clipboard me-1"></i> Copy
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($currentUploadToken): ?>
                                            <div class="form-text">✓ <strong><?= e($currentUploadToken) ?></strong> tersedia</div>
                                        <?php else: ?>
                                            <div class="form-text text-warning">⚠️ Hubungi admin untuk token</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="col-12 d-grid gap-2 pt-2">
                                    <button type="submit" class="btn btn-success btn-lg fw-semibold">
                                        <i class="bi bi-check2-circle me-2"></i> Konfirmasi Simpan
                                    </button>
                                </div>
                            </form>

                            <!-- Batalkan Preview Button -->
                            <form method="post" class="d-grid mt-2">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="cancel_preview_upload">
                                <button type="submit" class="btn btn-outline-secondary">Batalkan Preview</button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php if ($flash && ($flash['type'] ?? '') === 'success'): ?>
    <div class="position-fixed top-0 end-0 p-3 upload-toast-wrap" style="z-index: 1080">
        <div id="uploadSuccessToast"
             class="toast align-items-center text-bg-success border-0 upload-success-toast"
             role="status"
             aria-live="polite"
             aria-atomic="true"
             data-bs-delay="5000">
            <div class="d-flex">
                <div class="toast-body">
                    <div class="fw-semibold mb-1"><i class="bi bi-check2-circle me-1"></i> Upload Berhasil</div>
                    <div class="small"><?= e((string) ($flash['message'] ?? 'Data nilai berhasil disimpan.')) ?></div>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('rdmUploadForm');
    var submitBtn = document.getElementById('uploadSubmitBtn');
    var progressArea = document.getElementById('uploadProgressArea');
    var progressBar = document.getElementById('uploadProgressBar');
    var progressWrap = progressArea ? progressArea.querySelector('.upload-progress-bar-wrap') : null;
    var progressValue = document.getElementById('uploadProgressValue');
    var progressTitle = document.getElementById('uploadProgressTitle');
    var progressHint = document.getElementById('uploadProgressHint');
    var stepRead = document.getElementById('stepRead');
    var stepValidate = document.getElementById('stepValidate');
    var stepSave = document.getElementById('stepSave');
    var successToastEl = document.getElementById('uploadSuccessToast');

    function setStepState(node, state) {
        if (!node) {
            return;
        }

        node.classList.remove('active', 'done');
        if (state) {
            node.classList.add(state);
        }
    }

    function applyProgress(percent, titleText, hintText) {
        if (!progressBar || !progressValue || !progressWrap) {
            return;
        }

        var bounded = Math.max(0, Math.min(100, percent));
        progressBar.style.width = bounded + '%';
        progressValue.textContent = bounded + '%';
        progressWrap.setAttribute('aria-valuenow', String(bounded));

        if (titleText && progressTitle) {
            progressTitle.textContent = titleText;
        }

        if (hintText && progressHint) {
            progressHint.textContent = hintText;
        }
    }

    if (progressArea && !progressArea.classList.contains('is-complete')) {
        progressArea.classList.remove('is-visible');
    }

    if (progressArea && progressArea.classList.contains('is-complete')) {
        progressArea.classList.add('is-visible');
        applyProgress(100, 'Upload selesai diproses.', 'Data berhasil disimpan. Anda dapat melanjutkan upload file berikutnya jika diperlukan.');
        setStepState(stepRead, 'done');
        setStepState(stepValidate, 'done');
        setStepState(stepSave, 'done');
    }

    if (form) {
        form.addEventListener('submit', function () {
            if (!progressArea || !submitBtn) {
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Memproses...';
            progressArea.classList.add('is-visible');

            applyProgress(12, 'Membaca file Excel...', 'Sistem sedang memuat template RDM dari perangkat Anda.');
            setStepState(stepRead, 'active');
            setStepState(stepValidate, null);
            setStepState(stepSave, null);

            window.setTimeout(function () {
                applyProgress(43, 'Memvalidasi struktur dan mapel...', 'Kolom NISN, mapel, dan rentang nilai sedang diperiksa.');
                setStepState(stepRead, 'done');
                setStepState(stepValidate, 'active');
            }, 480);

            window.setTimeout(function () {
                applyProgress(78, 'Sinkronisasi ke database...', 'Nilai rapor sedang disimpan sesuai semester aktif.');
                setStepState(stepValidate, 'done');
                setStepState(stepSave, 'active');
            }, 980);

            window.setTimeout(function () {
                applyProgress(92, 'Finalisasi proses...', 'Mohon tunggu sebentar, hampir selesai.');
            }, 1500);
        });
    }

    if (successToastEl && window.bootstrap && window.bootstrap.Toast) {
        var toast = new window.bootstrap.Toast(successToastEl);
        toast.show();
    }
});

function copyAdminToken() {
    var tokenInput = document.getElementById('admin_upload_token');
    if (tokenInput && tokenInput.placeholder) {
        var token = tokenInput.placeholder;
        navigator.clipboard.writeText(token).then(function() {
            alert('Token berhasil dicopy: ' + token);
            tokenInput.value = token;
            tokenInput.focus();
        }).catch(function() {
            // Fallback untuk browser lama
            tokenInput.value = token;
            tokenInput.select();
            document.execCommand('copy');
            alert('Token berhasil dicopy: ' + token);
        });
    }
}
</script>
</body>
</html>
