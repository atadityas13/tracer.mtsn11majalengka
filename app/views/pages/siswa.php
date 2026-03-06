<?php
/**
 * ========================================================
 * E-LEGER MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Manajemen Data Nilai Siswa
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Data Siswa Page
 * Deskripsi: Halaman manajemen data siswa - CRUD, import Excel, dan modal nilai
 * 
 * @package    E-Leger-MTSN11
 * @author     MTsN 11 Majalengka Development Team
 * @copyright  2026 MTsN 11 Majalengka. All rights reserved.
 * @license    Proprietary License
 * @version    1.0.0
 * @since      2026-01-01
 * @created    2026-03-06
 * @modified   2026-03-06
 * 
 * Features:
 * - CRUD operations (Create, Read, Update, Delete)
 * - Import dari file Excel (.xlsx)
 * - Search realtime (Nama/NIS/NISN)
 * - Pagination (20/30/50/100/semua)
 * - Sorting clickable headers (Nama/Semester/Status)
 * - Modal view nilai per siswa (Rapor + UAM)
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
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!function_exists('siswa_excel_date_to_mysql')) {
    function siswa_excel_date_to_mysql($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable $e) {
                return null;
            }
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'];
        $value = trim((string) $value);
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date instanceof DateTime) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }
}

if (!function_exists('normalize_siswa_header')) {
    function normalize_siswa_header(string $header): string {
        $header = strtoupper(trim($header));
        $header = preg_replace('/\s+/', ' ', $header);
        $header = str_replace(['NO.', '-', '_'], ['NO', '', ''], $header);

        // Direct exact matches first
        $exactMap = [
            'NO ABSEN' => 'NO ABSEN',
            'NIS' => 'NIS',
            'NISN' => 'NISN',
            'NAMA' => 'NAMA',
            'TTL' => 'TTL',
            'KELAS' => 'KELAS',
            'L/P' => 'GENDER',
            'JENIS KELAMIN' => 'GENDER',
        ];

        if (isset($exactMap[$header])) {
            return $exactMap[$header];
        }

        // Fuzzy substring matches for variants
        $fuzzyMap = [
            'NOINDUK' => 'NIS',
            'NAMASANTRI' => 'NAMA',
            'TINGKATAN' => 'KELAS',
            'TEMPATLAHAIRTANGGALLAHIR' => 'TTL',
            'TTLTEMPATLAHAIRTANGGALLAHIR' => 'TTL',
        ];

        foreach ($fuzzyMap as $from => $to) {
            $headerNormalized = str_replace(' ', '', $header);
            if (strpos($headerNormalized, $from) !== false) {
                return $to;
            }
        }

        return '';
    }
}

if (!function_exists('siswa_parse_indonesian_date')) {
    function siswa_parse_indonesian_date(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})$/u', $value, $m)) {
            $day = (int) $m[1];
            $monthName = strtolower(trim($m[2]));
            $year = (int) $m[3];

            $months = [
                'januari' => 1,
                'februari' => 2,
                'pebruari' => 2,
                'maret' => 3,
                'april' => 4,
                'mei' => 5,
                'juni' => 6,
                'juli' => 7,
                'agustus' => 8,
                'september' => 9,
                'oktober' => 10,
                'november' => 11,
                'desember' => 12,
            ];

            if (!isset($months[$monthName])) {
                return null;
            }

            $month = (int) $months[$monthName];
            if (!checkdate($month, $day, $year)) {
                return null;
            }

            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        return siswa_excel_date_to_mysql($value);
    }
}

if (!function_exists('siswa_parse_ttl_rdm')) {
    function siswa_parse_ttl_rdm(string $ttl): array
    {
        $ttl = trim($ttl);
        if ($ttl === '') {
            return ['tempat' => '', 'tgl' => null];
        }

        $parts = explode(',', $ttl, 2);
        if (count($parts) === 2) {
            $tempat = trim($parts[0]);
            $tanggal = siswa_parse_indonesian_date(trim($parts[1]));
            return ['tempat' => $tempat, 'tgl' => $tanggal];
        }

        return ['tempat' => '', 'tgl' => siswa_parse_indonesian_date($ttl)];
    }
}

if (!function_exists('download_template_siswa')) {
    function download_template_siswa(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Siswa');

        $headers = ['No', 'NISN', 'NIS', 'Nama', 'Tempat Lahir', 'Tanggal Lahir', 'Kelas', 'Nomor Absen', 'Current Semester', 'Status Siswa'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray(['1', '0112345678', '240001', 'NAMA SISWA', 'MAJALENGKA', '2012-07-10', 'VIII-A', '5', '1', 'Aktif'], null, 'A2');

        for ($index = 1; $index <= count($headers); $index++) {
            $sheet->getColumnDimensionByColumn($index)->setAutoSize(true);
        }

        $guideSheet = $spreadsheet->createSheet();
        $guideSheet->setTitle('Petunjuk');
        $guideSheet->fromArray([
            ['PETUNJUK TEMPLATE SISWA'],
            ['1. Jangan ubah nama header pada baris pertama.'],
            ['2. Kolom No: nomor urut (opsional - untuk referensi).'],
            ['3. Kolom wajib: NISN, NIS, Nama, Tempat Lahir, Tanggal Lahir.'],
            ['4. Kolom Kelas dan Nomor Absen opsional (format: VIII-A, nomor 1-50).'],
            ['5. Format Tanggal Lahir disarankan YYYY-MM-DD (contoh: 2012-07-10).'],
            ['6. Current Semester boleh 1-5 atau Akhir. Jika kosong/invalid, otomatis jadi 1.'],
            ['7. Status Siswa: Aktif / Tidak Melanjutkan / Lulus (default Aktif).'],
            ['8. NISN dan NIS harus unik. Data duplikat akan dilewati saat impor.'],
        ], null, 'A1');
        $guideSheet->getColumnDimension('A')->setWidth(120);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="template_import_siswa.xlsx"');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'download_template_siswa_rdm') {
    enforce_csrf('siswa');
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Data Siswa RDM');
    
    $headers = ['NO ABSEN', 'NIS', 'NISN', 'NAMA', 'L/P', 'TTL', 'KELAS'];
    foreach ($headers as $index => $header) {
        $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        $sheet->getColumnDimensionByColumn($index)->setAutoSize(true);
    }
    
    $sheet->getStyle('1:1')->getFont()->setBold(true);
    $sheet->getStyle('1:1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFD3D3D3');
    
    $examples = [
        ['1', '250001', '3134412140', 'AJENG SRI PUTRI', 'L', 'BANDUNG, 28 April 2013', 'VII-1'],
        ['2', '250002', '3126316180', 'HAURA LATIFA', 'P', 'JAKARTA, 29 Agustus 2012', 'VII-1'],
        ['3', '250003', '3129026954', 'CHIKA NUIA PUTRI', 'P', 'MAJALENGKA, 25 September 2012', 'VII-1'],
    ];
    
    foreach ($examples as $rowIndex => $rowData) {
        foreach ($rowData as $colIndex => $value) {
            $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 2, $value);
        }
    }
    
    $sheet->getStyle('A2:G4')->getAlignment()->setWrapText(true);
    
    $guideSheet = $spreadsheet->createSheet();
    $guideSheet->setTitle('Panduan');
    $guideSheet->setCellValue('A1', 'Panduan Format Import Siswa RDM');
    $guideSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $guideSheet->setCellValue('A3', 'Kolom Wajib:');
    $guideSheet->getStyle('A3')->getFont()->setBold(true);
    $guideSheet->setCellValue('A4', '• NO ABSEN: Nomor urut siswa di kelas (1, 2, 3, ...) atau bisa dikosongkan');
    $guideSheet->setCellValue('A5', '• NIS: Nomor Induk Sekolah (wajib, unik, tidak boleh duplikat)');
    $guideSheet->setCellValue('A6', '• NISN: Nomor Induk Siswa Nasional (wajib, unik, digunakan sebagai anchor update)');
    $guideSheet->setCellValue('A7', '• NAMA: Nama lengkap siswa (wajib)');
    $guideSheet->setCellValue('A8', '• L/P: Jenis Kelamin - L untuk Laki-laki, P untuk Perempuan (opsional)');
    $guideSheet->setCellValue('A9', '• TTL: Tempat Lahir, Tanggal Lahir - format "KOTA, DD Bulan YYYY" misalnya "BANDUNG, 28 April 2013" (wajib)');
    $guideSheet->setCellValue('A10', '• KELAS: Kelas siswa misalnya VII-1, VIII-2, IX-1 (wajib)');
    $guideSheet->setCellValue('A12', 'Catatan Penting:');
    $guideSheet->getStyle('A12')->getFont()->setBold(true);
    $guideSheet->setCellValue('A13', '• Data baru akan di-INSERT jika NISN belum ada di database');
    $guideSheet->setCellValue('A14', '• Data lama akan di-UPDATE jika NISN sudah terdaftar');
    $guideSheet->setCellValue('A15', '• Baris dengan data kosong atau tidak lengkap akan di-SKIP');
    $guideSheet->setCellValue('A16', '• Format tanggal harus tepat: DD Bulan YYYY (misalnya 28 April 2013)');
    $guideSheet->getColumnDimension('A')->setWidth(150);
    $guideSheet->getStyle('A4:A16')->getAlignment()->setWrapText(true);
    
    $filename = 'Template-Siswa-RDM-' . date('YmdHis') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

$siswaRdmPreviewSessionKey = 'siswa_rdm_import_preview';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('siswa');

    $action = $_POST['action'] ?? '';

    if ($action === 'cancel_preview_siswa_rdm') {
        unset($_SESSION[$siswaRdmPreviewSessionKey]);
        set_flash('success', 'Preview import siswa RDM dibatalkan. Tidak ada data yang diubah.');
        redirect('index.php?page=siswa');
    }

    if ($action === 'confirm_import_siswa_rdm') {
        $preview = $_SESSION[$siswaRdmPreviewSessionKey] ?? null;
        if (!is_array($preview) || empty($preview['entries'])) {
            set_flash('error', 'Preview import tidak ditemukan. Silakan upload ulang file RDM dan lakukan preview terlebih dahulu.');
            redirect('index.php?page=siswa');
        }

        db()->beginTransaction();
        try {
            $insertStmt = db()->prepare('INSERT INTO siswa (nisn, nis, nama, tempat_lahir, tgl_lahir, kelas, nomor_absen, current_semester, status_siswa) VALUES (:nisn,:nis,:nama,:tempat,:tgl,:kelas,:nomor_absen,:semester,:status)');
            $updateStmt = db()->prepare('UPDATE siswa SET nis=:nis, nama=:nama, tempat_lahir=:tempat, tgl_lahir=:tgl, kelas=:kelas, nomor_absen=:nomor_absen WHERE nisn=:nisn');

            foreach ($preview['entries'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                if (($entry['aksi'] ?? '') === 'INSERT') {
                    $insertStmt->execute([
                        'nisn' => (string) ($entry['nisn'] ?? ''),
                        'nis' => (string) ($entry['nis'] ?? ''),
                        'nama' => (string) ($entry['nama'] ?? ''),
                        'tempat' => (string) ($entry['tempat'] ?? ''),
                        'tgl' => (string) ($entry['tgl'] ?? null),
                        'kelas' => ($entry['kelas'] ?? '') !== '' ? $entry['kelas'] : null,
                        'nomor_absen' => $entry['nomor_absen'] ?? null,
                        'semester' => 1,
                        'status' => 'Aktif',
                    ]);
                } elseif (($entry['aksi'] ?? '') === 'UPDATE') {
                    $updateStmt->execute([
                        'nis' => (string) ($entry['nis'] ?? ''),
                        'nama' => (string) ($entry['nama'] ?? ''),
                        'tempat' => (string) ($entry['tempat'] ?? ''),
                        'tgl' => (string) ($entry['tgl'] ?? null),
                        'kelas' => ($entry['kelas'] ?? '') !== '' ? $entry['kelas'] : null,
                        'nomor_absen' => $entry['nomor_absen'] ?? null,
                        'nisn' => (string) ($entry['nisn'] ?? ''),
                    ]);
                }
            }

            db()->commit();
            unset($_SESSION[$siswaRdmPreviewSessionKey]);

            $insertCount = (int) ($preview['meta']['insert_count'] ?? 0);
            $updateCount = (int) ($preview['meta']['update_count'] ?? 0);
            set_flash('success', "Import siswa RDM berhasil dikonfirmasi. Insert: {$insertCount}, update: {$updateCount}.");
        } catch (Throwable $e) {
            db()->rollBack();
            set_flash('error', 'Konfirmasi import siswa RDM gagal: ' . $e->getMessage());
        }

        redirect('index.php?page=siswa');
    }

    if ($action === 'download_template_siswa') {
        if (!class_exists(Spreadsheet::class)) {
            set_flash('error', 'PhpSpreadsheet belum terpasang. Jalankan composer install.');
            redirect('index.php?page=siswa');
        }

        download_template_siswa();
    }

    if ($action === 'import_excel_siswa') {
        if (!class_exists(IOFactory::class)) {
            set_flash('error', 'PhpSpreadsheet belum terpasang. Jalankan composer install.');
            redirect('index.php?page=siswa');
        }

        $tmp = $_FILES['file_excel']['tmp_name'] ?? '';
        if ($tmp === '') {
            set_flash('error', 'File Excel wajib diisi.');
            redirect('index.php?page=siswa');
        }

        $spreadsheet = IOFactory::load($tmp);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        if (count($rows) < 2) {
            set_flash('error', 'File tidak berisi data siswa.');
            redirect('index.php?page=siswa');
        }

        $headerMap = [];
        foreach ($rows[0] as $index => $header) {
            $key = normalize_siswa_header((string) $header);
            if ($key !== '') {
                $headerMap[$key] = (int) $index;
            }
        }

        $required = ['NISN', 'NIS', 'NAMA', 'TEMPAT LAHIR', 'TANGGAL LAHIR'];
        foreach ($required as $req) {
            if (!array_key_exists($req, $headerMap)) {
                set_flash('error', 'Header wajib tidak ditemukan: ' . $req);
                redirect('index.php?page=siswa');
            }
        }

        $kelasIndex = $headerMap['KELAS'] ?? null;
        $nomorAbsenIndex = $headerMap['NOMOR ABSEN'] ?? null;
        $semesterIndex = $headerMap['CURRENT SEMESTER'] ?? null;
        $statusIndex = $headerMap['STATUS SISWA'] ?? null;

        db()->beginTransaction();
        try {
            $inserted = 0;
            $duplicate = 0;
            $invalid = 0;

            $checkStmt = db()->prepare('SELECT nisn, nis FROM siswa WHERE nisn=:nisn OR nis=:nis LIMIT 1');
            $insertStmt = db()->prepare('INSERT INTO siswa (nisn, nis, nama, tempat_lahir, tgl_lahir, kelas, nomor_absen, current_semester, status_siswa) VALUES (:nisn,:nis,:nama,:tempat,:tgl,:kelas,:nomor_absen,:semester,:status)');

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $nisn = trim((string) ($row[$headerMap['NISN']] ?? ''));
                $nis = trim((string) ($row[$headerMap['NIS']] ?? ''));
                $nama = trim((string) ($row[$headerMap['NAMA']] ?? ''));
                $tempat = trim((string) ($row[$headerMap['TEMPAT LAHIR']] ?? ''));
                $tgl = siswa_excel_date_to_mysql($row[$headerMap['TANGGAL LAHIR']] ?? null);
                $kelas = ($kelasIndex !== null) ? trim((string) ($row[$kelasIndex] ?? '')) : '';
                $nomorAbsen = ($nomorAbsenIndex !== null) ? (int) trim((string) ($row[$nomorAbsenIndex] ?? '')) : null;

                if ($nisn === '' && $nis === '' && $nama === '') {
                    continue;
                }

                if ($nisn === '' || $nis === '' || $nama === '' || $tempat === '' || $tgl === null) {
                    $invalid++;
                    continue;
                }

                $semesterRaw = ($semesterIndex !== null) ? (string) ($row[$semesterIndex] ?? '') : '';
                $semester = normalize_current_semester($semesterRaw);

                $statusRaw = ($statusIndex !== null) ? trim((string) ($row[$statusIndex] ?? '')) : '';
                $allowedStatus = ['Aktif', 'Tidak Melanjutkan', 'Lulus'];
                $status = in_array($statusRaw, $allowedStatus, true) ? $statusRaw : 'Aktif';

                $checkStmt->execute(['nisn' => $nisn, 'nis' => $nis]);
                if ($checkStmt->fetch()) {
                    $duplicate++;
                    continue;
                }

                $insertStmt->execute([
                    'nisn' => $nisn,
                    'nis' => $nis,
                    'nama' => $nama,
                    'tempat' => $tempat,
                    'tgl' => $tgl,
                    'kelas' => $kelas !== '' ? $kelas : null,
                    'nomor_absen' => $nomorAbsen ?? null,
                    'semester' => $semester,
                    'status' => $status,
                ]);
                $inserted++;
            }

            db()->commit();
            set_flash('success', "Import siswa selesai. Berhasil: {$inserted}, duplikat: {$duplicate}, invalid: {$invalid}.");
        } catch (Throwable $e) {
            db()->rollBack();
            set_flash('error', 'Import siswa gagal: ' . $e->getMessage());
        }

        redirect('index.php?page=siswa');
    }

    if ($action === 'preview_excel_siswa_rdm') {
        if (!class_exists(IOFactory::class)) {
            set_flash('error', 'PhpSpreadsheet belum terpasang. Jalankan composer install.');
            redirect('index.php?page=siswa');
        }

        $tmp = $_FILES['file_excel']['tmp_name'] ?? '';
        if ($tmp === '') {
            set_flash('error', 'File Excel RDM wajib diisi.');
            redirect('index.php?page=siswa');
        }

        $spreadsheet = IOFactory::load($tmp);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        if (count($rows) < 2) {
            set_flash('error', 'File RDM tidak berisi data siswa.');
            redirect('index.php?page=siswa');
        }

        $headerMap = [];
        foreach ($rows[0] as $index => $header) {
            $key = normalize_siswa_header((string) $header);
            if ($key !== '') {
                $headerMap[$key] = (int) $index;
            }
        }

        $required = ['NO ABSEN', 'NIS', 'NISN', 'NAMA', 'TTL', 'KELAS'];
        foreach ($required as $req) {
            if (!array_key_exists($req, $headerMap)) {
                set_flash('error', 'Header RDM wajib tidak ditemukan: ' . $req);
                redirect('index.php?page=siswa');
            }
        }

        $findByNisnStmt = db()->prepare('SELECT nisn, nis FROM siswa WHERE nisn=:nisn LIMIT 1');
        $findByNisStmt = db()->prepare('SELECT nisn FROM siswa WHERE nis=:nis LIMIT 1');

        $entries = [];
        $insertCount = 0;
        $updateCount = 0;
        $invalid = 0;
        $skipNisConflict = 0;

        for ($i = 1; $i < count($rows); $i++) {
            $excelRow = $i + 1;
            $row = $rows[$i];
            $nisn = trim((string) ($row[$headerMap['NISN']] ?? ''));
            $nis = trim((string) ($row[$headerMap['NIS']] ?? ''));
            $nama = trim((string) ($row[$headerMap['NAMA']] ?? ''));
            $ttlRaw = trim((string) ($row[$headerMap['TTL']] ?? ''));
            $kelas = trim((string) ($row[$headerMap['KELAS']] ?? ''));
            $nomorAbsenRaw = trim((string) ($row[$headerMap['NO ABSEN']] ?? ''));
            $nomorAbsen = $nomorAbsenRaw !== '' ? (int) $nomorAbsenRaw : null;

            if ($nisn === '' && $nis === '' && $nama === '' && $ttlRaw === '') {
                continue;
            }

            $ttlParsed = siswa_parse_ttl_rdm($ttlRaw);
            $tempat = trim((string) ($ttlParsed['tempat'] ?? ''));
            $tgl = $ttlParsed['tgl'] ?? null;

            if ($nisn === '' || $nis === '' || $nama === '' || $tempat === '' || $tgl === null) {
                $invalid++;
                continue;
            }

            $findByNisnStmt->execute(['nisn' => $nisn]);
            $existingByNisn = $findByNisnStmt->fetch();

            $findByNisStmt->execute(['nis' => $nis]);
            $existingByNis = $findByNisStmt->fetch();
            if ($existingByNis && (string) $existingByNis['nisn'] !== $nisn) {
                $skipNisConflict++;
                continue;
            }

            $aksi = $existingByNisn ? 'UPDATE' : 'INSERT';
            if ($aksi === 'INSERT') {
                $insertCount++;
            } else {
                $updateCount++;
            }

            $entries[] = [
                'excel_row' => $excelRow,
                'nisn' => $nisn,
                'nis' => $nis,
                'nama' => $nama,
                'tempat' => $tempat,
                'tgl' => $tgl,
                'kelas' => $kelas !== '' ? $kelas : '-',
                'nomor_absen' => $nomorAbsen ?? '-',
                'aksi' => $aksi,
            ];
        }

        if (count($entries) === 0) {
            set_flash('error', 'Tidak ada data siswa yang bisa diproses untuk preview. Periksa format file dan data wajib.');
            redirect('index.php?page=siswa');
        }

        $_SESSION[$siswaRdmPreviewSessionKey] = [
            'meta' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'insert_count' => $insertCount,
                'update_count' => $updateCount,
                'invalid_count' => $invalid,
                'conflict_nis_count' => $skipNisConflict,
            ],
            'entries' => $entries,
        ];

        set_flash('success', 'Preview siswa RDM berhasil dibuat. Periksa semua baris pada tabel preview sebelum klik Konfirmasi Import.');
        redirect('index.php?page=siswa');
    }

    if ($action === 'create') {
        $nisn = trim($_POST['nisn'] ?? '');
        $nis = trim($_POST['nis'] ?? '');

        $cek = db()->prepare('SELECT nisn, nis FROM siswa WHERE nisn=:nisn OR nis=:nis LIMIT 1');
        $cek->execute([
            'nisn' => $nisn,
            'nis' => $nis,
        ]);
        $exists = $cek->fetch();

        if ($exists) {
            if (($exists['nisn'] ?? '') === $nisn) {
                set_flash('error', 'NISN sudah terdaftar. Gunakan NISN lain.');
                redirect('index.php?page=siswa');
            }

            if (($exists['nis'] ?? '') === $nis) {
                set_flash('error', 'NIS sudah terdaftar. Gunakan NIS lain.');
                redirect('index.php?page=siswa');
            }
        }

        $kelas = trim($_POST['kelas'] ?? '');
        $nomorAbsen = $_POST['nomor_absen'] ?? null;
        
        $stmt = db()->prepare('INSERT INTO siswa (nisn, nis, nama, tempat_lahir, tgl_lahir, kelas, nomor_absen, current_semester, status_siswa) VALUES (:nisn,:nis,:nama,:tempat,:tgl,:kelas,:nomor_absen,:semester,:status)');
        $stmt->execute([
            'nisn' => $nisn,
            'nis' => $nis,
            'nama' => trim($_POST['nama'] ?? ''),
            'tempat' => trim($_POST['tempat_lahir'] ?? ''),
            'tgl' => $_POST['tgl_lahir'] ?? '',
            'kelas' => $kelas !== '' ? $kelas : null,
            'nomor_absen' => !empty($nomorAbsen) ? (int) $nomorAbsen : null,
            'semester' => normalize_current_semester($_POST['current_semester'] ?? 1),
            'status' => $_POST['status_siswa'] ?? 'Aktif',
        ]);
        set_flash('success', 'Data siswa ditambahkan.');
        redirect('index.php?page=siswa');
    }

    if ($action === 'update') {
        $nisn = trim($_POST['nisn'] ?? '');
        $nisBaru = trim($_POST['nis'] ?? '');
        
        // Check if NIS is being changed and if new NIS already exists
        $cek = db()->prepare('SELECT nis FROM siswa WHERE nis=:nis AND nisn!=:nisn LIMIT 1');
        $cek->execute(['nis' => $nisBaru, 'nisn' => $nisn]);
        if ($cek->fetch()) {
            set_flash('error', 'NIS sudah digunakan siswa lain.');
            redirect('index.php?page=siswa');
        }
        
        $stmt = db()->prepare('UPDATE siswa SET nis=:nis, nama=:nama, tempat_lahir=:tempat, tgl_lahir=:tgl, current_semester=:semester, status_siswa=:status WHERE nisn=:nisn');
        $stmt->execute([
            'nis' => $nisBaru,
            'nama' => trim($_POST['nama'] ?? ''),
            'tempat' => trim($_POST['tempat_lahir'] ?? ''),
            'tgl' => $_POST['tgl_lahir'] ?? '',
            'semester' => normalize_current_semester($_POST['current_semester'] ?? 1),
            'status' => $_POST['status_siswa'] ?? 'Aktif',
            'nisn' => $nisn,
        ]);
        set_flash('success', 'Data siswa berhasil diperbarui.');
        redirect('index.php?page=siswa');
    }

    if ($action === 'delete') {
        $nisn = $_POST['nisn'] ?? '';
        $stmt = db()->prepare('DELETE FROM siswa WHERE nisn=:nisn');
        $stmt->execute(['nisn' => $nisn]);
        set_flash('success', 'Data siswa berhasil dihapus.');
        redirect('index.php?page=siswa');
    }

    if ($action === 'update_nilai') {
        $nisn = trim($_POST['nisn'] ?? '');
        $semester = (int) ($_POST['semester'] ?? 1);
        $mapelId = (int) ($_POST['mapel_id'] ?? 0);
        $nilaiAngka = $_POST['nilai_angka'] !== '' ? (float) $_POST['nilai_angka'] : null;

        if ($nisn === '' || $mapelId <= 0) {
            set_flash('error', 'Data tidak valid.');
            redirect($_POST['redirect_url'] ?? 'index.php?page=siswa');
        }

        db()->beginTransaction();
        try {
            if ($semester === 99) {
                // UAM
                if ($nilaiAngka === null) {
                    $stmt = db()->prepare('DELETE FROM nilai_uam WHERE nisn=:nisn AND mapel_id=:mapel_id');
                    $stmt->execute(['nisn' => $nisn, 'mapel_id' => $mapelId]);
                } else {
                    $stmtCheck = db()->prepare('SELECT id FROM nilai_uam WHERE nisn=:nisn AND mapel_id=:mapel_id LIMIT 1');
                    $stmtCheck->execute(['nisn' => $nisn, 'mapel_id' => $mapelId]);
                    if ($stmtCheck->fetch()) {
                        $stmt = db()->prepare('UPDATE nilai_uam SET nilai_angka=:nilai WHERE nisn=:nisn AND mapel_id=:mapel_id');
                        $stmt->execute(['nilai' => $nilaiAngka, 'nisn' => $nisn, 'mapel_id' => $mapelId]);
                    } else {
                        $stmt = db()->prepare('INSERT INTO nilai_uam (nisn, mapel_id, nilai_angka) VALUES (:nisn,:mapel_id,:nilai)');
                        $stmt->execute(['nisn' => $nisn, 'mapel_id' => $mapelId, 'nilai' => $nilaiAngka]);
                    }
                }
            } else {
                // Rapor
                if ($nilaiAngka === null) {
                    $stmt = db()->prepare('DELETE FROM nilai_rapor WHERE nisn=:nisn AND semester=:semester AND mapel_id=:mapel_id AND tahun_ajaran=:ta');
                    $stmt->execute(['nisn' => $nisn, 'semester' => $semester, 'mapel_id' => $mapelId, 'ta' => $setting['tahun_ajaran']]);
                } else {
                    $stmtCheck = db()->prepare('SELECT id FROM nilai_rapor WHERE nisn=:nisn AND semester=:semester AND mapel_id=:mapel_id AND tahun_ajaran=:ta LIMIT 1');
                    $stmtCheck->execute(['nisn' => $nisn, 'semester' => $semester, 'mapel_id' => $mapelId, 'ta' => $setting['tahun_ajaran']]);
                    if ($stmtCheck->fetch()) {
                        $stmt = db()->prepare('UPDATE nilai_rapor SET nilai_angka=:nilai WHERE nisn=:nisn AND semester=:semester AND mapel_id=:mapel_id AND tahun_ajaran=:ta');
                        $stmt->execute(['nilai' => $nilaiAngka, 'nisn' => $nisn, 'semester' => $semester, 'mapel_id' => $mapelId, 'ta' => $setting['tahun_ajaran']]);
                    } else {
                        $stmt = db()->prepare('INSERT INTO nilai_rapor (nisn, semester, mapel_id, tahun_ajaran, nilai_angka) VALUES (:nisn,:semester,:mapel_id,:ta,:nilai)');
                        $stmt->execute(['nisn' => $nisn, 'semester' => $semester, 'mapel_id' => $mapelId, 'ta' => $setting['tahun_ajaran'], 'nilai' => $nilaiAngka]);
                    }
                }
            }

            db()->commit();
            set_flash('success', 'Nilai berhasil diperbarui.');
        } catch (Throwable $e) {
            db()->rollBack();
            set_flash('error', 'Gagal update nilai: ' . $e->getMessage());
        }

        redirect($_POST['redirect_url'] ?? 'index.php?page=siswa');
    }
}

$searchQuery = trim($_GET['search'] ?? '');
$kelasFilter = trim($_GET['kelas'] ?? '');
$perPage = (int) ($_GET['per_page'] ?? 20);
$perPage = in_array($perPage, [20, 30, 50, 100, 999999], true) ? $perPage : 20;
$page = max(1, (int) ($_GET['page'] ?? 1));
$sortBy = $_GET['sort_by'] ?? 'kelas,nomor_absen,nama';
$sortDir = strtoupper($_GET['sort_dir'] ?? 'ASC');
$sortDir = in_array($sortDir, ['ASC', 'DESC'], true) ? $sortDir : 'ASC';

// Helper function untuk toggle sort
$getSortLink = function($column, $label) use ($searchQuery, $kelasFilter, $sortBy, $sortDir, $perPage) {
    $newDir = ($sortBy === $column && $sortDir === 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($sortBy === $column) {
        $icon = $sortDir === 'ASC' ? ' ↑' : ' ↓';
    }
    $url = "index.php?page=siswa&search=" . urlencode($searchQuery) . "&kelas=" . urlencode($kelasFilter) . "&sort_by={$column}&sort_dir={$newDir}&per_page={$perPage}";
    return "<a href=\"$url\" style=\"text-decoration: none; color: inherit; cursor: pointer;\">{$label}{$icon}</a>";
};

$where = '';
$params = [];
if ($searchQuery !== '') {
    $where = 'WHERE nama LIKE :search1 OR nisn LIKE :search2 OR nis LIKE :search3';
    $searchTerm = '%' . $searchQuery . '%';
    $params['search1'] = $searchTerm;
    $params['search2'] = $searchTerm;
    $params['search3'] = $searchTerm;
}

if ($kelasFilter !== '') {
    $where = ($where === '') ? 'WHERE' : $where . ' AND';
    $where .= ' kelas = :kelas';
    $params['kelas'] = $kelasFilter;
}

$countStmt = db()->prepare("SELECT COUNT(*) as total FROM siswa {$where}");
$countStmt->execute($params);
$totalRecords = (int) $countStmt->fetch()['total'];
$totalPages = $perPage >= 999999 ? 1 : ceil($totalRecords / $perPage);
$page = min($page, max(1, $totalPages));
$offset = ($page - 1) * $perPage;

$sql = "SELECT * FROM siswa {$where} ORDER BY COALESCE(kelas, ''), COALESCE(nomor_absen, 999), nama ASC LIMIT {$offset}, {$perPage}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$siswa = $stmt->fetchAll();
$kelasOptions = db()->query('SELECT DISTINCT kelas FROM siswa WHERE kelas IS NOT NULL AND kelas != "" ORDER BY kelas')->fetchAll();
$mapelList = db()->query('SELECT id, nama_mapel FROM mapel ORDER BY id')->fetchAll();
$setting = setting_akademik();

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-1">Import Data Siswa dari Excel</h3>
        <p class="text-secondary mb-0">Unduh template dan upload data siswa</p>
    </div>
    <div class="card-body row g-2">
        <div class="col-auto">
            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalImportSiswaTemplate">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Import Template Siswa
            </button>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalImportSiswaRdm">
                <i class="bi bi-cloud-arrow-up me-1"></i>Import Siswa RDM
            </button>
        </div>
    </div>
</div>

<?php
$siswa_preview = $_SESSION['siswa_rdm_import_preview'] ?? null;
if (is_array($siswa_preview) && !empty($siswa_preview['entries'])):
    $meta = $siswa_preview['meta'] ?? [];
?>
<div class="card border-0 shadow-sm mb-3 border-warning">
    <div class="card-header bg-light border-0 pt-3">
        <h4 class="mb-1"><i class="bi bi-eye me-2 text-warning"></i>Preview Import Siswa RDM</h4>
        <small class="text-secondary">Dibuat: <?= e($meta['generated_at'] ?? 'N/A') ?></small>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="alert alert-info mb-0"><strong>Insert Baru:</strong> <?= e((int) ($meta['insert_count'] ?? 0)) ?></div>
            </div>
            <div class="col-md-3">
                <div class="alert alert-warning mb-0"><strong>Update:</strong> <?= e((int) ($meta['update_count'] ?? 0)) ?></div>
            </div>
            <div class="col-md-3">
                <div class="alert alert-danger mb-0"><strong>Invalid:</strong> <?= e((int) ($meta['invalid_count'] ?? 0)) ?></div>
            </div>
            <div class="col-md-3">
                <div class="alert alert-secondary mb-0"><strong>Konflik NIS:</strong> <?= e((int) ($meta['conflict_nis_count'] ?? 0)) ?></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">Baris</th>
                        <th>NISN</th>
                        <th>NIS</th>
                        <th>Nama</th>
                        <th>TTL</th>
                        <th>Kelas</th>
                        <th>No. Absen</th>
                        <th style="width: 80px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($siswa_preview['entries'] as $entry): ?>
                    <tr class="<?= ($entry['aksi'] ?? '') === 'INSERT' ? 'table-info' : (($entry['aksi'] ?? '') === 'UPDATE' ? 'table-warning' : '') ?>">
                        <td><?= e($entry['excel_row'] ?? '-') ?></td>
                        <td><strong><?= e($entry['nisn'] ?? '-') ?></strong></td>
                        <td><?= e($entry['nis'] ?? '-') ?></td>
                        <td><?= e($entry['nama'] ?? '-') ?></td>
                        <td><small><?= e($entry['tempat'] ?? '-') ?>, <?= e($entry['tgl'] ?? '-') ?></small></td>
                        <td><?= e($entry['kelas'] ?? '-') ?></td>
                        <td><?= e($entry['nomor_absen'] ?? '-') ?></td>
                        <td>
                            <span class="badge <?= ($entry['aksi'] ?? '') === 'INSERT' ? 'bg-success' : (($entry['aksi'] ?? '') === 'UPDATE' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                                <?= e($entry['aksi'] ?? 'SKIP') ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row mt-3">
            <div class="col-md-6">
                <form method="post" class="d-inline">
                    <?= csrf_input('siswa') ?>
                    <input type="hidden" name="action" value="confirm_import_siswa_rdm">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>Konfirmasi Import
                    </button>
                </form>
            </div>
            <div class="col-md-6 text-end">
                <form method="post" class="d-inline">
                    <?= csrf_input('siswa') ?>
                    <input type="hidden" name="action" value="cancel_preview_siswa_rdm">
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-x-circle me-1"></i>Batalkan Preview
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
        <h3 class="mb-0">Data Siswa</h3>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambahSiswa">
            <i class="bi bi-plus-circle me-1"></i>Tambah Siswa
        </button>
    </div>
    <div class="card-body">
        <form method="get" class="row g-2 mb-3">
            <input type="hidden" name="page" value="siswa">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari Nama/NIS/NISN..." value="<?= e($searchQuery) ?>">
            </div>
            <div class="col-md-2">
                <select name="kelas" class="form-select form-select-sm">
                    <option value="">-- Semua Kelas --</option>
                    <?php foreach ($kelasOptions as $k): ?>
                        <option value="<?= e($k['kelas']) ?>" <?= $kelasFilter === $k['kelas'] ? 'selected' : '' ?>><?= e($k['kelas']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success btn-sm w-100">Cari</button>
            </div>
            <div class="col-md-2">
                <a href="index.php?page=siswa" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
            </div>
        </form>

        <div class="row mb-2 align-items-center small">
            <div class="col-md-6 text-secondary">Total: <?= e(number_format($totalRecords)) ?> siswa <?php if ($totalPages > 1): ?>(Halaman <?= e((string) $page) ?> dari <?= e((string) $totalPages) ?>)<?php endif; ?></div>
            <div class="col-md-6 text-end">
                <select id="perPageSelect" class="form-select form-select-sm d-inline-block" style="width: auto;">
                    <option value="20" <?= $perPage === 20 ? 'selected' : '' ?>>20 per halaman</option>
                    <option value="30" <?= $perPage === 30 ? 'selected' : '' ?>>30 per halaman</option>
                    <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50 per halaman</option>
                    <option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100 per halaman</option>
                    <option value="999999" <?= $perPage === 999999 ? 'selected' : '' ?>>Semua</option>
                </select>
            </div>
        </div>
        <script>
            document.getElementById('perPageSelect').addEventListener('change', function() {
                const url = new URL(window.location);
                url.searchParams.set('per_page', this.value);
                url.searchParams.delete('page');
                window.location = url;
            });
        </script>

        <div class="table-wrap">
            <table>
                <thead><tr><th style="width: 50px;">No</th><th><?php echo $getSortLink('nisn', 'NISN'); ?></th><th><?php echo $getSortLink('nama', 'Nama'); ?></th><th>Kelas</th><th>No. Absen</th><th><?php echo $getSortLink('current_semester', 'Semester'); ?></th><th><?php echo $getSortLink('status_siswa', 'Status'); ?></th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                <?php $noCounter = $offset + 1; foreach ($siswa as $s): ?>
                    <tr>
                        <td><?= e((string) $noCounter++) ?></td>
                        <td><?= e($s['nisn']) ?></td>
                        <td><?= e($s['nama']) ?></td>
                        <td><?= e($s['kelas'] ?? '-') ?></td>
                        <td><?= e($s['nomor_absen'] !== null ? (string) $s['nomor_absen'] : '-') ?></td>
                        <td><?= e(current_semester_label($s['current_semester'])) ?></td>
                        <td><span class="badge text-bg-light border"><?= e($s['status_siswa']) ?></span></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalDetailSiswa<?= e($s['nisn']) ?>" title="Detail">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalNilaiSiswa<?= e($s['nisn']) ?>" title="Nilai">
                                    <i class="bi bi-card-list"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="if(confirm('Yakin hapus siswa <?= e($s['nama']) ?>?')) document.getElementById('formHapusSiswa<?= e($s['nisn']) ?>').submit();" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <form id="formHapusSiswa<?= e($s['nisn']) ?>" method="post" class="d-none">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="nisn" value="<?= e($s['nisn']) ?>">
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=siswa&search=<?= e($searchQuery) ?>&kelas=<?= e($kelasFilter) ?>&sort_by=<?= e($sortBy) ?>&sort_dir=<?= e($sortDir) ?>&per_page=<?= e((string) $perPage) ?>&page=1">Pertama</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=siswa&search=<?= e($searchQuery) ?>&kelas=<?= e($kelasFilter) ?>&sort_by=<?= e($sortBy) ?>&sort_dir=<?= e($sortDir) ?>&per_page=<?= e((string) $perPage) ?>&page=<?= e((string) ($page - 1)) ?>">Sebelumnya</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?page=siswa&search=<?= e($searchQuery) ?>&kelas=<?= e($kelasFilter) ?>&sort_by=<?= e($sortBy) ?>&sort_dir=<?= e($sortDir) ?>&per_page=<?= e((string) $perPage) ?>&page=<?= e((string) $p) ?>"><?= e((string) $p) ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=siswa&search=<?= e($searchQuery) ?>&kelas=<?= e($kelasFilter) ?>&sort_by=<?= e($sortBy) ?>&sort_dir=<?= e($sortDir) ?>&per_page=<?= e((string) $perPage) ?>&page=<?= e((string) ($page + 1)) ?>">Selanjutnya</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=siswa&search=<?= e($searchQuery) ?>&kelas=<?= e($kelasFilter) ?>&sort_by=<?= e($sortBy) ?>&sort_dir=<?= e($sortDir) ?>&per_page=<?= e((string) $perPage) ?>&page=<?= e((string) $totalPages) ?>">Terakhir</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>

<script>
document.getElementById('perPageSelect').addEventListener('change', function() {
    const search = new URLSearchParams(window.location.search).get('search') || '';
    const sortBy = new URLSearchParams(window.location.search).get('sort_by') || 'nama';
    const sortDir = new URLSearchParams(window.location.search).get('sort_dir') || 'ASC';
    const perPage = this.value;
    window.location.href = `index.php?page=siswa&search=${encodeURIComponent(search)}&sort_by=${encodeURIComponent(sortBy)}&sort_dir=${encodeURIComponent(sortDir)}&per_page=${perPage}`;
});
</script>

<div class="modal fade" id="modalTambahSiswa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Siswa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">NISN</label><input type="text" class="form-control" name="nisn" required></div>
                        <div class="col-md-4"><label class="form-label">NIS</label><input type="text" class="form-control" name="nis" required></div>
                        <div class="col-md-4"><label class="form-label">Nama</label><input type="text" class="form-control" name="nama" required></div>
                        <div class="col-md-4"><label class="form-label">Tempat Lahir</label><input type="text" class="form-control" name="tempat_lahir" required></div>
                    <div class="col-md-6"><label class="form-label">Kelas</label><input type="text" class="form-control" name="kelas" placeholder="contoh: VIII-A"></div>
                        <div class="col-md-6"><label class="form-label">Nomor Absen</label><input type="number" class="form-control" name="nomor_absen" min="1" max="50" placeholder="1-50"></div>
                        <div class="col-md-2">
                            <label class="form-label">Current Semester</label>
                            <select name="current_semester" class="form-select">
                                <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">Akhir</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status_siswa" class="form-select">
                                <option>Aktif</option>
                                <option>Tidak Melanjutkan</option>
                                <option>Lulus</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalImportSiswaTemplate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Import Template Siswa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary mb-3">Download template, isi data siswa secara manual, lalu upload file Excel untuk input massal.</p>
                <form method="post" class="mb-3">
                    <?= csrf_input('siswa') ?>
                    <input type="hidden" name="action" value="download_template_siswa">
                    <button type="submit" class="btn btn-outline-success w-100">
                        <i class="bi bi-download me-1"></i>Download Template Siswa
                    </button>
                </form>

                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <h6 class="mb-2">Upload File Template Siswa</h6>
                        <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
                            <?= csrf_input('siswa') ?>
                            <input type="hidden" name="action" value="import_excel_siswa">
                            <div class="col-md-8">
                                <label class="form-label">File Excel (.xlsx/.xls)</label>
                                <input type="file" name="file_excel" class="form-control" accept=".xlsx,.xls" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-upload me-1"></i>Import
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalImportSiswaRdm" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Import Siswa dari Ekspor RDM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary mb-3">Upload file ekspor data siswa dari Rapor Digital Madrasah (RDM) untuk di-preview terlebih dahulu sebelum disimpan ke database.</p>

                <div class="row g-2 mb-3">
                    <div class="col-md-12">
                        <small class="text-secondary d-block mb-2">
                            <strong>Format Kolom yang Didukung:</strong><br>
                            NO ABSEN, NIS, NISN, NAMA, L/P, TTL, KELAS
                        </small>
                        <small class="text-secondary d-block">
                            <strong>Catatan:</strong> NISN digunakan sebagai anchor untuk UPDATE otomatis jika siswa sudah ada di database. Baris dengan data wajib yang kosong akan diskip.
                        </small>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-12">
                        <form method="post" class="d-inline">
                            <?= csrf_input('siswa') ?>
                            <input type="hidden" name="action" value="download_template_siswa_rdm">
                            <button type="submit" class="btn btn-sm btn-outline-info w-100">
                                <i class="bi bi-download me-1"></i>Unduh Template Format RDM (Referensi)
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <h6 class="mb-2">Preview & Import File RDM</h6>
                        <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
                            <?= csrf_input('siswa') ?>
                            <input type="hidden" name="action" value="preview_excel_siswa_rdm">
                            <div class="col-md-8">
                                <label class="form-label">File Excel Ekspor RDM (.xlsx/.xls)<span class="text-danger">*</span></label>
                                <input type="file" name="file_excel" class="form-control" accept=".xlsx,.xls" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-warning w-100">
                                    <i class="bi bi-search me-1"></i>Preview RDM
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php foreach ($siswa as $s): ?>
<!-- Modal Detail Siswa -->
<div class="modal fade" id="modalDetailSiswa<?= e($s['nisn']) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Detail Siswa: <?= e($s['nama']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="nisn" value="<?= e($s['nisn']) ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">NISN</label>
                            <input type="text" class="form-control" value="<?= e($s['nisn']) ?>" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">NIS</label>
                            <input type="text" class="form-control" name="nis" value="<?= e($s['nis']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nama</label>
                            <input type="text" class="form-control" name="nama" value="<?= e($s['nama']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tempat Lahir</label>
                            <input type="text" class="form-control" name="tempat_lahir" value="<?= e($s['tempat_lahir']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Lahir</label>
                            <input type="date" class="form-control" name="tgl_lahir" value="<?= e($s['tgl_lahir']) ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Current Semester</label>
                            <select name="current_semester" class="form-select">
                                <option value="1" <?= normalize_current_semester($s['current_semester']) === 1 ? 'selected' : '' ?>>1</option>
                                <option value="2" <?= normalize_current_semester($s['current_semester']) === 2 ? 'selected' : '' ?>>2</option>
                                <option value="3" <?= normalize_current_semester($s['current_semester']) === 3 ? 'selected' : '' ?>>3</option>
                                <option value="4" <?= normalize_current_semester($s['current_semester']) === 4 ? 'selected' : '' ?>>4</option>
                                <option value="5" <?= normalize_current_semester($s['current_semester']) === 5 ? 'selected' : '' ?>>5</option>
                                <option value="6" <?= normalize_current_semester($s['current_semester']) === 6 ? 'selected' : '' ?>>Akhir</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status_siswa" class="form-select">
                                <option <?= $s['status_siswa'] === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                                <option <?= $s['status_siswa'] === 'Tidak Melanjutkan' ? 'selected' : '' ?>>Tidak Melanjutkan</option>
                                <option <?= $s['status_siswa'] === 'Lulus' ? 'selected' : '' ?>>Lulus</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nilai Siswa -->
<div class="modal fade" id="modalNilaiSiswa<?= e($s['nisn']) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Nilai Siswa: <?= e($s['nama']) ?> (<?= e($s['nisn']) ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="tabNilai<?= e($s['nisn']) ?>" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-sem1-<?= e($s['nisn']) ?>" data-bs-toggle="tab" data-bs-target="#sem1-<?= e($s['nisn']) ?>" type="button">Semester 1</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-sem2-<?= e($s['nisn']) ?>" data-bs-toggle="tab" data-bs-target="#sem2-<?= e($s['nisn']) ?>" type="button">Semester 2</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-sem3-<?= e($s['nisn']) ?>" data-bs-toggle="tab" data-bs-target="#sem3-<?= e($s['nisn']) ?>" type="button">Semester 3</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-sem4-<?= e($s['nisn']) ?>" data-bs-toggle="tab" data-bs-target="#sem4-<?= e($s['nisn']) ?>" type="button">Semester 4</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-sem5-<?= e($s['nisn']) ?>" data-bs-toggle="tab" data-bs-target="#sem5-<?= e($s['nisn']) ?>" type="button">Semester 5</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-uam-<?= e($s['nisn']) ?>" data-bs-toggle="tab" data-bs-target="#uam-<?= e($s['nisn']) ?>" type="button">UAM</button>
                    </li>
                </ul>
                <div class="tab-content" id="tabContent<?= e($s['nisn']) ?>">
                    <?php for ($sem = 1; $sem <= 5; $sem++): 
                        $stNilai = db()->prepare('SELECT nr.id, nr.nilai_angka, nr.mapel_id, m.nama_mapel FROM nilai_rapor nr JOIN mapel m ON nr.mapel_id=m.id WHERE nr.nisn=:nisn AND nr.semester=:sem AND nr.tahun_ajaran=:ta ORDER BY m.id');
                        $stNilai->execute(['nisn' => $s['nisn'], 'sem' => $sem, 'ta' => $setting['tahun_ajaran']]);
                        $nilaiRapor = $stNilai->fetchAll();
                        
                        // Hitung rata-rata
                        $totalNilai = 0;
                        $jumlahMapel = count($nilaiRapor);
                        foreach ($nilaiRapor as $n) {
                            $totalNilai += $n['nilai_angka'];
                        }
                        $rataRata = $jumlahMapel > 0 ? $totalNilai / $jumlahMapel : 0;
                    ?>
                    <div class="tab-pane fade <?= $sem === 1 ? 'show active' : '' ?>" id="sem<?= $sem ?>-<?= e($s['nisn']) ?>">
                        <h6 class="mb-3">Semester <?= $sem ?> - TA <?= e($setting['tahun_ajaran']) ?></h6>
                        <?php if (count($nilaiRapor) > 0): ?>
                            <div class="table-wrap">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th rowspan="2" class="align-middle">Mata Pelajaran</th>
                                            <th colspan="2" class="text-center">Nilai</th>
                                        </tr>
                                        <tr>
                                            <th class="text-center">Angka</th>
                                            <th class="text-center">Huruf</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($nilaiRapor as $n): ?>
                                            <tr>
                                                <td><?= e($n['nama_mapel']) ?></td>
                                                <td class="text-center">
                                                    <input type="number" step="1" min="0" max="100" class="form-control form-control-sm" style="width: 80px; margin: 0 auto;" value="<?= e((int)round($n['nilai_angka'])) ?>" id="nilai_<?= e($n['id']) ?>" data-nisn="<?= e($s['nisn']) ?>" data-mapel-id="<?= e($n['mapel_id']) ?>" data-semester="<?= $sem ?>">
                                                </td>
                                                <td class="text-center"><?= ucwords(terbilang_bulat((int)$n['nilai_angka'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                                        <tr class="table-secondary fw-bold">
                                                            <td>Rata-Rata</td>
                                                            <td class="text-center"><?= e((string)round($rataRata)) ?></td>
                                                            <td class="text-center"><?= ucwords(terbilang_nilai($rataRata)) ?></td>
                                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-secondary text-center">Belum ada nilai untuk semester ini.</p>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                    
                    <?php 
                        $stUam = db()->prepare('SELECT nu.id, nu.nilai_angka, nu.mapel_id, m.nama_mapel FROM nilai_uam nu JOIN mapel m ON nu.mapel_id=m.id WHERE nu.nisn=:nisn ORDER BY m.id');
                        $stUam->execute(['nisn' => $s['nisn']]);
                        $nilaiUam = $stUam->fetchAll();
                        
                        // Hitung rata-rata UAM
                        $totalNilaiUam = 0;
                        $jumlahMapelUam = count($nilaiUam);
                        foreach ($nilaiUam as $n) {
                            $totalNilaiUam += $n['nilai_angka'];
                        }
                        $rataRataUam = $jumlahMapelUam > 0 ? $totalNilaiUam / $jumlahMapelUam : 0;
                    ?>
                    <div class="tab-pane fade" id="uam-<?= e($s['nisn']) ?>">
                        <h6 class="mb-3">Ujian Akhir Madrasah (UAM)</h6>
                        <?php if (count($nilaiUam) > 0): ?>
                            <div class="table-wrap">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th rowspan="2" class="align-middle">Mata Pelajaran</th>
                                            <th colspan="2" class="text-center">Nilai</th>
                                        </tr>
                                        <tr>
                                            <th class="text-center">Angka</th>
                                            <th class="text-center">Huruf</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($nilaiUam as $n): ?>
                                            <tr>
                                                <td><?= e($n['nama_mapel']) ?></td>
                                                <td class="text-center">
                                                    <input type="number" step="1" min="0" max="100" class="form-control form-control-sm" style="width: 80px; margin: 0 auto;" value="<?= e((int)round($n['nilai_angka'])) ?>" id="nilai_uam_<?= e($n['mapel_id']) ?>" data-nisn="<?= e($s['nisn']) ?>" data-mapel-id="<?= e($n['mapel_id']) ?>" data-semester="99">
                                                </td>
                                                <td class="text-center"><?= ucwords(terbilang_bulat((int)$n['nilai_angka'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-secondary fw-bold">
                                            <td>Rata-Rata</td>
                                            <td class="text-center"><?= e((string)round($rataRataUam)) ?></td>
                                            <td class="text-center"><?= ucwords(terbilang_nilai($rataRataUam)) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-secondary text-center">Belum ada nilai UAM.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-outline-warning d-none" id="batalBtn<?= e($s['nisn']) ?>" data-nisn="<?= e($s['nisn']) ?>">
                    <i class="bi bi-x-circle me-1"></i>Batal
                </button>
                <button type="button" class="btn btn-success d-none" id="simpanBtn<?= e($s['nisn']) ?>" data-nisn="<?= e($s['nisn']) ?>">
                    <i class="bi bi-check-circle me-1"></i>Simpan Perubahan
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Track nilai awal dan perubahan pada input nilai
const nilaiChanges = {};

document.addEventListener('change', function(e) {
    // Track perubahan pada input nilai
    if (e.target.matches('input[id^="nilai_"][type="number"]') || 
        e.target.matches('input[id^="nilai_uam_"][type="number"]')) {
        
        const inputEl = e.target;
        const nisn = inputEl.dataset.nisn;
        
        // Simpan perubahan ke object
        if (!nilaiChanges[nisn]) {
            nilaiChanges[nisn] = [];
        }
        
        nilaiChanges[nisn].push({
            nisn: nisn,
            mapel_id: inputEl.dataset.mapelId,
            semester: inputEl.dataset.semester,
            nilai_angka: inputEl.value,
            element: inputEl
        });
        
        // Tampilkan tombol Simpan dan Batal
        const simpanBtn = document.getElementById(`simpanBtn${nisn}`);
        const batalBtn = document.getElementById(`batalBtn${nisn}`);
        if (simpanBtn) simpanBtn.classList.remove('d-none');
        if (batalBtn) batalBtn.classList.remove('d-none');
    }
});

// Handler tombol Simpan
document.addEventListener('click', function(e) {
    if (e.target.matches('[id^="simpanBtn"]')) {
        const nisn = e.target.dataset.nisn;
        const changes = nilaiChanges[nisn] || [];
        
        if (changes.length === 0) {
            alert('Tidak ada perubahan untuk disimpan.');
            return;
        }
        
        // Kirim semua perubahan
        const promises = changes.map(change => {
            const formData = new FormData();
            formData.append('action', 'update_nilai');
            formData.append('nisn', change.nisn);
            formData.append('mapel_id', change.mapel_id);
            formData.append('semester', change.semester);
            formData.append('nilai_angka', change.nilai_angka);
            formData.append('_csrf', document.querySelector('input[name="_csrf"]')?.value || '');
            formData.append('redirect_url', window.location.href);
            
            return fetch('index.php?page=siswa', {
                method: 'POST',
                body: formData
            });
        });
        
        Promise.all(promises)
            .then(() => {
                // Clear changes dan refresh
                nilaiChanges[nisn] = [];
                setTimeout(() => location.reload(), 500);
            })
            .catch(err => {
                alert('Gagal menyimpan perubahan: ' + err.message);
                console.error('Error:', err);
            });
    }
    
    // Handler tombol Batal
    if (e.target.matches('[id^="batalBtn"]')) {
        const nisn = e.target.dataset.nisn;
        
        // Reload index untuk kembali ke nilai awal
        nilaiChanges[nisn] = [];
        window.location.hash = `modal-nilai-${nisn}`;
        location.reload();
    }
});
</script>

<?php endforeach; ?>

<?php require dirname(__DIR__) . '/partials/footer.php';
