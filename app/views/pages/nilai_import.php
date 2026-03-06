<?php
/**
 * ========================================================
 * E-LEGER MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Manajemen Data Nilai Siswa
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Data Nilai & Import Nilai Page
 * Deskripsi: Halaman untuk import nilai rapor/UAM dan monitoring upload status
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
foreach ($mapelRows as $m) {
    $mapelByName[normalize_header($m['nama_mapel'])] = (int) $m['id'];
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

    $headerRow = $rows[0];
    $nisnIndex = null;
    $kelasIndex = null;
    $nomorAbsenIndex = null;
    $mapelColumns = [];

    foreach ($headerRow as $index => $header) {
        $headerKey = normalize_header((string) $header);
        if ($headerKey === '') {
            continue;
        }

        // Deteksi kolom NISN
        if ($nisnIndex === null && in_array($headerKey, $nisnHeaderCandidates, true)) {
            $nisnIndex = (int) $index;
            continue;
        }

        // Deteksi kolom Kelas
        if ($kelasIndex === null && in_array($headerKey, ['KELAS'], true)) {
            $kelasIndex = (int) $index;
            continue;
        }

        // Deteksi kolom Nomor Absen
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

    if ($nisnIndex === null) {
        set_flash('error', 'Kolom NISN tidak ditemukan di header file Excel.');
        redirect('index.php?page=data-nilai');
    }

    if (count($mapelColumns) === 0) {
        set_flash('error', 'Kolom mapel tidak dikenali. Pastikan header mapel sesuai format leger.');
        redirect('index.php?page=data-nilai');
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

            // Update kelas dan nomor_absen jika kolom ada dan terisi
            $kelas = $kelasIndex !== null ? trim((string) ($row[$kelasIndex] ?? '')) : '';
            $nomorAbsen = $nomorAbsenIndex !== null ? trim((string) ($row[$nomorAbsenIndex] ?? '')) : '';
            
            if ($kelas !== '' || $nomorAbsen !== '') {
                $updateData = [];
                if ($kelas !== '') {
                    $updateData['kelas'] = $kelas;
                }
                if ($nomorAbsen !== '') {
                    $updateData['nomor_absen'] = (int) $nomorAbsen;
                }
                
                if (!empty($updateData)) {
                    $setClauses = [];
                    foreach ($updateData as $col => $val) {
                        $setClauses[] = "$col = :$col";
                    }
                    $updateQuery = "UPDATE siswa SET " . implode(', ', $setClauses) . " WHERE nisn=:nisn";
                    $stUpdate = db()->prepare($updateQuery);
                    $updateData['nisn'] = $nisn;
                    $stUpdate->execute($updateData);
                }
            }

            $semesterSiswa = normalize_current_semester($siswa['current_semester']);
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

    redirect('index.php?page=data-nilai');
}

$monitorSemesterOptions = array_map('strval', semester_upload_target($semesterAktif));
if ($semesterAktif === 'GENAP') {
    $monitorSemesterOptions[] = 'UAM';
}
$defaultMonitorSemester = $monitorSemesterOptions[0] ?? '1';

$monitorSemester = strtoupper(trim($_GET['semester_view'] ?? $defaultMonitorSemester));
$monitorStatus = trim($_GET['status_upload'] ?? 'all');
$monitorStatus = in_array($monitorStatus, ['all', 'uploaded', 'not_uploaded'], true) ? $monitorStatus : 'all';

if (!in_array($monitorSemester, $monitorSemesterOptions, true)) {
    $monitorSemester = $defaultMonitorSemester;
}

$mapelCount = (int) (db()->query('SELECT COUNT(*) c FROM mapel')->fetch()['c'] ?? 0);

// Filter students by current_semester matching the selected semester filter
if ($monitorSemester === 'UAM') {
    $students = db()->query("SELECT nisn, nis, nama, current_semester FROM siswa WHERE status_siswa='Aktif' AND current_semester = 6 ORDER BY nama")->fetchAll();
} else {
    $stStudents = db()->prepare("SELECT nisn, nis, nama, current_semester FROM siswa WHERE status_siswa='Aktif' AND current_semester=:sem ORDER BY nama");
    $stStudents->execute(['sem' => (int)$monitorSemester]);
    $students = $stStudents->fetchAll();
}

$rowsMonitor = [];
foreach ($students as $student) {
    $entryCount = 0;
    $statusLabel = 'Belum Terupload';

    if ($monitorSemester === 'UAM') {
        $st = db()->prepare('SELECT COUNT(*) c FROM nilai_uam WHERE nisn=:nisn');
        $st->execute(['nisn' => $student['nisn']]);
        $entryCount = (int) ($st->fetch()['c'] ?? 0);
        if ($entryCount > 0) {
            $statusLabel = $entryCount >= $mapelCount ? 'Sudah Terupload (Lengkap)' : 'Sudah Terupload (Sebagian)';
        }
    } else {
        $st = db()->prepare('SELECT COUNT(*) c FROM nilai_rapor WHERE nisn=:nisn AND semester=:semester AND tahun_ajaran=:ta');
        $st->execute([
            'nisn' => $student['nisn'],
            'semester' => (int) $monitorSemester,
            'ta' => $setting['tahun_ajaran'],
        ]);
        $entryCount = (int) ($st->fetch()['c'] ?? 0);
        if ($entryCount > 0) {
            $statusLabel = $entryCount >= $mapelCount ? 'Sudah Terupload (Lengkap)' : 'Sudah Terupload (Sebagian)';
        }
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

// Helper function for clickable monitoring sort links
$getMonitorSortLink = function($column, $label) use ($monitorSortBy, $monitorSortDir, $monitorSearch, $monitorSemester, $monitorStatus, $monitorPerPage) {
    $newDir = ($monitorSortBy === $column && $monitorSortDir === 'ASC') ? 'DESC' : 'ASC';
    $indicator = ($monitorSortBy === $column) ? ($monitorSortDir === 'ASC' ? ' ↑' : ' ↓') : '';
    $url = "index.php?page=data-nilai" .
        "&search_monitoring=" . urlencode($monitorSearch) .
        "&sort_by_monitoring=" . urlencode($column) .
        "&sort_dir_monitoring=" . urlencode($newDir) .
        "&per_page_monitoring=" . urlencode((string) $monitorPerPage) .
        "&semester_view=" . urlencode($monitorSemester) .
        "&status_upload=" . urlencode($monitorStatus);
    return '<a href="' . e($url) . '" class="text-dark text-decoration-none">' . e($label) . $indicator . '</a>';
};

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-1">Import Data Nilai</h3>
        <p class="text-secondary mb-0">Download template dan upload nilai.</p>
    </div>
    <div class="card-body">
        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalImportNilai">
            <i class="bi bi-cloud-arrow-up me-1"></i>Import Nilai
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-1">Monitoring Upload Nilai (TA <?= e($setting['tahun_ajaran']) ?>)</h3>
        <p class="text-secondary mb-0">Filter semester <?= e(implode('/', $monitorSemesterOptions)) ?> serta status sudah terupload atau belum terupload.</p>
    </div>
    <div class="card-body">
        <form method="get" class="row g-2 mb-3">
            <input type="hidden" name="page" value="data-nilai">
            <input type="hidden" name="semester_view" value="<?= e($monitorSemester) ?>">
            <input type="hidden" name="status_upload" value="<?= e($monitorStatus) ?>">
            <div class="col-md-8">
                <input type="text" name="search_monitoring" class="form-control form-control-sm" placeholder="Cari Nama/NIS/NISN..." value="<?= e($monitorSearch) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success btn-sm w-100">Cari</button>
            </div>
            <div class="col-md-2">
                <a href="index.php?page=data-nilai&semester_view=<?= e($monitorSemester) ?>&status_upload=<?= e($monitorStatus) ?>" class="btn btn-outline-secondary btn-sm w-100">Reset Cari</a>
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
                    <th><?php echo $getMonitorSortLink('nisn', 'NISN'); ?></th>
                    <th><?php echo $getMonitorSortLink('nis', 'NIS'); ?></th>
                    <th><?php echo $getMonitorSortLink('nama', 'Nama'); ?></th>
                    <th><?php echo $getMonitorSortLink('current_semester', 'Current Semester'); ?></th>
                    <th>Jumlah Entri</th>
                    <th><?php echo $getMonitorSortLink('status_label', 'Status'); ?></th>
                    <th class="text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($rowsMonitorPaginated) === 0): ?>
                    <tr>
                        <td colspan="7" class="text-center text-secondary">Tidak ada data untuk filter ini.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rowsMonitorPaginated as $row): ?>
                        <tr>
                            <td><?= e($row['nisn']) ?></td>
                            <td><?= e($row['nis']) ?></td>
                            <td><?= e($row['nama']) ?></td>
                            <td><?= e($row['current_semester']) ?></td>
                            <td><?= e((string) $row['entry_count']) ?></td>
                            <td>
                                <span class="badge <?= $row['uploaded'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= e($row['status_label']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if (!$row['uploaded']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="if(confirm('Nonaktifkan siswa <?= e($row['nama']) ?>? Status akan diubah menjadi Tidak Melanjutkan.')) document.getElementById('formNonaktif<?= e($row['nisn']) ?>').submit();" title="Nonaktifkan">
                                        <i class="bi bi-x-circle"></i> Nonaktifkan
                                    </button>
                                    <form id="formNonaktif<?= e($row['nisn']) ?>" method="post" class="d-none">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="nonaktifkan_siswa">
                                        <input type="hidden" name="nisn" value="<?= e($row['nisn']) ?>">
                                    </form>
                                <?php else: ?>
                                    <span class="text-secondary">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalMonitorPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                    <?php if ($monitorPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=data-nilai&semester_view=<?= e($monitorSemester) ?>&status_upload=<?= e($monitorStatus) ?>&search_monitoring=<?= e($monitorSearch) ?>&sort_by_monitoring=<?= e($monitorSortBy) ?>&sort_dir_monitoring=<?= e($monitorSortDir) ?>&per_page_monitoring=<?= e((string) $monitorPerPage) ?>&page_monitoring=1">Pertama</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=data-nilai&semester_view=<?= e($monitorSemester) ?>&status_upload=<?= e($monitorStatus) ?>&search_monitoring=<?= e($monitorSearch) ?>&sort_by_monitoring=<?= e($monitorSortBy) ?>&sort_dir_monitoring=<?= e($monitorSortDir) ?>&per_page_monitoring=<?= e((string) $monitorPerPage) ?>&page_monitoring=<?= e((string) ($monitorPage - 1)) ?>">Sebelumnya</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($p = max(1, $monitorPage - 2); $p <= min($totalMonitorPages, $monitorPage + 2); $p++): ?>
                        <li class="page-item <?= $p === $monitorPage ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?page=data-nilai&semester_view=<?= e($monitorSemester) ?>&status_upload=<?= e($monitorStatus) ?>&search_monitoring=<?= e($monitorSearch) ?>&sort_by_monitoring=<?= e($monitorSortBy) ?>&sort_dir_monitoring=<?= e($monitorSortDir) ?>&per_page_monitoring=<?= e((string) $monitorPerPage) ?>&page_monitoring=<?= e((string) $p) ?>"><?= e((string) $p) ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($monitorPage < $totalMonitorPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=data-nilai&semester_view=<?= e($monitorSemester) ?>&status_upload=<?= e($monitorStatus) ?>&search_monitoring=<?= e($monitorSearch) ?>&sort_by_monitoring=<?= e($monitorSortBy) ?>&sort_dir_monitoring=<?= e($monitorSortDir) ?>&per_page_monitoring=<?= e((string) $monitorPerPage) ?>&page_monitoring=<?= e((string) ($monitorPage + 1)) ?>">Selanjutnya</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=data-nilai&semester_view=<?= e($monitorSemester) ?>&status_upload=<?= e($monitorStatus) ?>&search_monitoring=<?= e($monitorSearch) ?>&sort_by_monitoring=<?= e($monitorSortBy) ?>&sort_dir_monitoring=<?= e($monitorSortDir) ?>&per_page_monitoring=<?= e((string) $monitorPerPage) ?>&page_monitoring=<?= e((string) $totalMonitorPages) ?>">Terakhir</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalImportNilai" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Import Data Nilai</h5>
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
<?php require dirname(__DIR__) . '/partials/footer.php';
