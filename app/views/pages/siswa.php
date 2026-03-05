<?php
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function normalize_siswa_header(string $value): string
{
    $value = strtoupper(trim($value));
    $value = str_replace(['.', '-', '/', '_'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return trim($value);
}

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

function download_template_siswa(): void
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Template Siswa');

    $headers = ['NISN', 'NIS', 'Nama', 'Tempat Lahir', 'Tanggal Lahir', 'Current Semester', 'Status Siswa'];
    $sheet->fromArray($headers, null, 'A1');
    $sheet->fromArray(['0112345678', '240001', 'NAMA SISWA', 'MAJALENGKA', '2012-07-10', '1', 'Aktif'], null, 'A2');

    for ($index = 1; $index <= count($headers); $index++) {
        $sheet->getColumnDimensionByColumn($index)->setAutoSize(true);
    }

    $guideSheet = $spreadsheet->createSheet();
    $guideSheet->setTitle('Petunjuk');
    $guideSheet->fromArray([
        ['PETUNJUK TEMPLATE SISWA'],
        ['1. Jangan ubah nama header pada baris pertama.'],
        ['2. Kolom wajib: NISN, NIS, Nama, Tempat Lahir, Tanggal Lahir.'],
        ['3. Format Tanggal Lahir disarankan YYYY-MM-DD (contoh: 2012-07-10).'],
        ['4. Current Semester boleh 1-5. Jika kosong/invalid, otomatis jadi 1.'],
        ['5. Status Siswa: Aktif / Tidak Melanjutkan / Lulus (default Aktif).'],
        ['6. NISN dan NIS harus unik. Data duplikat akan dilewati saat impor.'],
    ], null, 'A1');
    $guideSheet->getColumnDimension('A')->setWidth(120);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="template_import_siswa.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('siswa');

    $action = $_POST['action'] ?? '';

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

        $semesterIndex = $headerMap['CURRENT SEMESTER'] ?? null;
        $statusIndex = $headerMap['STATUS SISWA'] ?? null;

        db()->beginTransaction();
        try {
            $inserted = 0;
            $duplicate = 0;
            $invalid = 0;

            $checkStmt = db()->prepare('SELECT nisn, nis FROM siswa WHERE nisn=:nisn OR nis=:nis LIMIT 1');
            $insertStmt = db()->prepare('INSERT INTO siswa (nisn, nis, nama, tempat_lahir, tgl_lahir, current_semester, status_siswa) VALUES (:nisn,:nis,:nama,:tempat,:tgl,:semester,:status)');

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $nisn = trim((string) ($row[$headerMap['NISN']] ?? ''));
                $nis = trim((string) ($row[$headerMap['NIS']] ?? ''));
                $nama = trim((string) ($row[$headerMap['NAMA']] ?? ''));
                $tempat = trim((string) ($row[$headerMap['TEMPAT LAHIR']] ?? ''));
                $tgl = siswa_excel_date_to_mysql($row[$headerMap['TANGGAL LAHIR']] ?? null);

                if ($nisn === '' && $nis === '' && $nama === '') {
                    continue;
                }

                if ($nisn === '' || $nis === '' || $nama === '' || $tempat === '' || $tgl === null) {
                    $invalid++;
                    continue;
                }

                $semesterRaw = ($semesterIndex !== null) ? (string) ($row[$semesterIndex] ?? '') : '';
                $semester = (int) trim($semesterRaw);
                if ($semester < 1 || $semester > 5) {
                    $semester = 1;
                }

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

        $stmt = db()->prepare('INSERT INTO siswa (nisn, nis, nama, tempat_lahir, tgl_lahir, current_semester, status_siswa) VALUES (:nisn,:nis,:nama,:tempat,:tgl,:semester,:status)');
        $stmt->execute([
            'nisn' => $nisn,
            'nis' => $nis,
            'nama' => trim($_POST['nama'] ?? ''),
            'tempat' => trim($_POST['tempat_lahir'] ?? ''),
            'tgl' => $_POST['tgl_lahir'] ?? '',
            'semester' => (int) ($_POST['current_semester'] ?? 1),
            'status' => $_POST['status_siswa'] ?? 'Aktif',
        ]);
        set_flash('success', 'Data siswa ditambahkan.');
        redirect('index.php?page=siswa');
    }

    if ($action === 'update_status') {
        $stmt = db()->prepare('UPDATE siswa SET status_siswa=:status WHERE nisn=:nisn');
        $stmt->execute([
            'status' => $_POST['status_siswa'] ?? 'Aktif',
            'nisn' => $_POST['nisn'] ?? '',
        ]);
        set_flash('success', 'Status siswa diperbarui.');
        redirect('index.php?page=siswa');
    }
}

$siswa = db()->query('SELECT * FROM siswa ORDER BY nama')->fetchAll();

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-1">Import Data Siswa dari Excel</h3>
        <p class="text-secondary mb-0">Unduh template, isi data siswa, lalu upload untuk input massal.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <form method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="download_template_siswa">
                    <button type="submit" class="btn btn-outline-success w-100">
                        <i class="bi bi-download me-1"></i>Download Template Siswa
                    </button>
                </form>
            </div>
            <div class="col-md-8">
                <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="import_excel_siswa">
                    <div class="col-md-8">
                        <label class="form-label mb-1">File Excel (.xlsx/.xls)</label>
                        <input type="file" name="file_excel" class="form-control" accept=".xlsx,.xls" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-upload me-1"></i>Import Siswa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
        <h3 class="mb-0">Data Siswa</h3>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambahSiswa">
            <i class="bi bi-plus-circle me-1"></i>Tambah Siswa
        </button>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table>
                <thead><tr><th>NISN</th><th>Nama</th><th>Semester</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($siswa as $s): ?>
                    <tr>
                        <td><?= e($s['nisn']) ?></td>
                        <td><?= e($s['nama']) ?></td>
                        <td><?= e((string)$s['current_semester']) ?></td>
                        <td><span class="badge text-bg-light border"><?= e($s['status_siswa']) ?></span></td>
                        <td class="text-end">
                            <form method="post" class="d-inline-flex gap-2 align-items-center">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="nisn" value="<?= e($s['nisn']) ?>">
                                <select name="status_siswa" class="form-select form-select-sm">
                                    <option <?= $s['status_siswa'] === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                                    <option <?= $s['status_siswa'] === 'Tidak Melanjutkan' ? 'selected' : '' ?>>Tidak Melanjutkan</option>
                                    <option <?= $s['status_siswa'] === 'Lulus' ? 'selected' : '' ?>>Lulus</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

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
                        <div class="col-md-4"><label class="form-label">Tanggal Lahir</label><input type="date" class="form-control" name="tgl_lahir" required></div>
                        <div class="col-md-2">
                            <label class="form-label">Current Semester</label>
                            <select name="current_semester" class="form-select">
                                <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option>
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
<?php require dirname(__DIR__) . '/partials/footer.php';
