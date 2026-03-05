<?php
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
        ['3. Isi nilai pada kolom mapel dengan rentang 70-100.'],
        ['4. Biarkan kosong jika nilai mapel belum tersedia.'],
        ['5. Nilai akan dipetakan otomatis berdasarkan header mapel (QH, AA, FIK, SKI, BAR, PP, BINDO, MTK, IPA, IPS, BING, PJOK, INFO, SBP, BSD).'],
        ['6. NISN harus sesuai data siswa aktif di aplikasi.'],
        ['7. Simpan file dalam format .xlsx sebelum upload.'],
        [''],
        ['Catatan Semester:'],
        ['- Import Rapor: otomatis mengikuti current semester siswa pada semester aktif.'],
        ['- Import UAM: diproses untuk siswa semester 5 saat semester aktif GENAP.'],
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
foreach ($mapelRows as $m) {
    $mapelByName[normalize_header($m['nama_mapel'])] = (int) $m['id'];
}

$aliasToMapelName = [
    'QH' => "AL QUR AN HADIS",
    'QURAN HADIS' => "AL QUR AN HADIS",
    'ALQURAN HADIS' => "AL QUR AN HADIS",
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('nilai-import');

    $action = $_POST['action'] ?? '';

    if ($action === 'download_template_rapor' || $action === 'download_template_uam') {
        if (!class_exists(Spreadsheet::class)) {
            set_flash('error', 'PhpSpreadsheet belum terpasang. Jalankan composer install.');
            redirect('index.php?page=nilai-import');
        }

        $headers = ['No', 'NIS', 'NISN', 'Nama', 'JK', 'QH', 'AA', 'FIK', 'SKI', 'BAR', 'PP', 'BINDO', 'MTK', 'IPA', 'IPS', 'BING', 'PJOK', 'INFO', 'SBP', 'BSD'];

        if ($action === 'download_template_rapor') {
            download_template_excel('template_import_rapor.xlsx', $headers);
        }

        download_template_excel('template_import_uam.xlsx', $headers);
    }

    if (!class_exists(IOFactory::class)) {
        set_flash('error', 'PhpSpreadsheet belum terpasang. Jalankan composer install.');
        redirect('index.php?page=nilai-import');
    }

    $tmp = $_FILES['file_excel']['tmp_name'] ?? '';

    if (!$tmp) {
        set_flash('error', 'File wajib diisi.');
        redirect('index.php?page=nilai-import');
    }

    $spreadsheet = IOFactory::load($tmp);
    $rows = $spreadsheet->getActiveSheet()->toArray();

    if (count($rows) < 2) {
        set_flash('error', 'Format file tidak valid atau tidak ada data.');
        redirect('index.php?page=nilai-import');
    }

    $headerRow = $rows[0];
    $nisnIndex = null;
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

        if (isset($aliasToMapelId[$headerKey])) {
            $mapelColumns[(int) $index] = (int) $aliasToMapelId[$headerKey];
            continue;
        }

        if (isset($mapelByName[$headerKey])) {
            $mapelColumns[(int) $index] = (int) $mapelByName[$headerKey];
        }
    }

    if ($nisnIndex === null) {
        set_flash('error', 'Kolom NISN tidak ditemukan di header file Excel.');
        redirect('index.php?page=nilai-import');
    }

    if (count($mapelColumns) === 0) {
        set_flash('error', 'Kolom mapel tidak dikenali. Pastikan header mapel sesuai format leger.');
        redirect('index.php?page=nilai-import');
    }

    db()->beginTransaction();
    try {
        $count = 0;
        $skipRange = 0;
        foreach ($rows as $i => $row) {
            if ($i === 0) {
                continue;
            }

            $nisn = trim((string) ($row[$nisnIndex] ?? ''));

            if ($nisn === '') {
                continue;
            }

            $stSiswa = db()->prepare('SELECT nisn, current_semester, status_siswa FROM siswa WHERE nisn=:nisn LIMIT 1');
            $stSiswa->execute(['nisn' => $nisn]);
            $siswa = $stSiswa->fetch();
            if (!$siswa || $siswa['status_siswa'] !== 'Aktif') {
                continue;
            }

            $semesterSiswa = (int) $siswa['current_semester'];
            $isRaporTarget = in_array($semesterSiswa, $targetRapor, true);
            $isUamTarget = $semesterAktif === 'GENAP' && $semesterSiswa === 5;

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
                if ($nilai < 70 || $nilai > 100) {
                    $skipRange++;
                    continue;
                }

                if ($action === 'import_rapor') {
                    $stmt = db()->prepare('INSERT INTO nilai_rapor (nisn, mapel_id, semester, tahun_ajaran, nilai_angka, is_finalized) VALUES (:nisn,:mapel,:semester,:ta,:nilai,0)
                        ON DUPLICATE KEY UPDATE nilai_angka=VALUES(nilai_angka), is_finalized=0');
                    $stmt->execute([
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
                    $stmt = db()->prepare('INSERT INTO nilai_uam (nisn, mapel_id, nilai_angka) VALUES (:nisn,:mapel,:nilai)
                        ON DUPLICATE KEY UPDATE nilai_angka=VALUES(nilai_angka)');
                    $stmt->execute([
                        'nisn' => $nisn,
                        'mapel' => $mapelId,
                        'nilai' => $nilai,
                    ]);
                    $count++;
                }
            }
        }

        db()->commit();
        set_flash('success', "Import selesai. Diproses: {$count}, dilewati (nilai di luar 70-100): {$skipRange}.");
    } catch (Throwable $e) {
        db()->rollBack();
        set_flash('error', 'Import gagal: ' . $e->getMessage());
    }

    redirect('index.php?page=nilai-import');
}

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-1">Template Excel</h3>
        <p class="text-secondary mb-0">Download template, isi nilai per kolom mapel, lalu upload di bawah.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
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
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-1">Import Excel Nilai Rapor</h3>
        <p class="text-secondary mb-0">Upload 1 file leger lengkap (banyak kolom mapel) seperti contoh: No, NIS, NISN, Nama, JK, QH, AA, FIK, SKI, BAR, PP, BINDO, MTK, IPA, IPS, BING, PJOK, INFO, SBP, BSD.</p>
    </div>
    <div class="card-body">
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
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-0">Import Excel Nilai UAM (Semester 5)</h3>
    </div>
    <div class="card-body">
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
<?php require dirname(__DIR__) . '/partials/footer.php';
