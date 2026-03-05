<?php
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!function_exists('normalize_siswa_header')) {
    function normalize_siswa_header(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = str_replace(['.', '-', '/', '_'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }
}

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

if (!function_exists('download_template_siswa')) {
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
            ['4. Current Semester boleh 1-5 atau Akhir. Jika kosong/invalid, otomatis jadi 1.'],
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
}

$searchQuery = trim($_GET['search'] ?? '');
$perPage = (int) ($_GET['per_page'] ?? 20);
$perPage = in_array($perPage, [20, 30, 50, 100, 999999], true) ? $perPage : 20;
$page = max(1, (int) ($_GET['page'] ?? 1));
$sortBy = $_GET['sort_by'] ?? 'nama';
$sortBy = in_array($sortBy, ['nama', 'current_semester', 'status_siswa'], true) ? $sortBy : 'nama';
$sortDir = strtoupper($_GET['sort_dir'] ?? 'ASC');
$sortDir = in_array($sortDir, ['ASC', 'DESC'], true) ? $sortDir : 'ASC';

// Helper function untuk toggle sort
$getSortLink = function($column, $label) use ($searchQuery, $sortBy, $sortDir, $perPage) {
    $newDir = ($sortBy === $column && $sortDir === 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($sortBy === $column) {
        $icon = $sortDir === 'ASC' ? ' ↑' : ' ↓';
    }
    $url = "index.php?page=siswa&search=" . urlencode($searchQuery) . "&sort_by={$column}&sort_dir={$newDir}&per_page={$perPage}";
    return "<a href=\"$url\" style=\"text-decoration: none; color: inherit; cursor: pointer;\">{$label}{$icon}</a>";
};

$where = '';
$params = [];
if ($searchQuery !== '') {
    $where = 'WHERE nama LIKE :search OR nisn LIKE :search OR nis LIKE :search';
    $params['search'] = '%' . $searchQuery . '%';
}

$countStmt = db()->prepare("SELECT COUNT(*) as total FROM siswa {$where}");
$countStmt->execute($params);
$totalRecords = (int) $countStmt->fetch()['total'];
$totalPages = $perPage >= 999999 ? 1 : ceil($totalRecords / $perPage);
$page = min($page, max(1, $totalPages));
$offset = ($page - 1) * $perPage;

$sql = "SELECT * FROM siswa {$where} ORDER BY {$sortBy} {$sortDir} LIMIT {$offset}, {$perPage}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$siswa = $stmt->fetchAll();
$mapelList = db()->query('SELECT id, nama_mapel FROM mapel ORDER BY id')->fetchAll();
$setting = setting_akademik();

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-1">Import Data Siswa dari Excel</h3>
        <p class="text-secondary mb-0">Unduh template dan upload data siswa</p>
    </div>
    <div class="card-body">
        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalImportSiswa">
            <i class="bi bi-cloud-arrow-up me-1"></i>Import Siswa
        </button>
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
        <form method="get" class="row g-2 mb-3">
            <input type="hidden" name="page" value="siswa">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari Nama/NIS/NISN..." value="<?= e($searchQuery) ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-success btn-sm w-100">Cari</button>
            </div>
            <div class="col-md-3">
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
                url.searchParams.set('page', '1');
                window.location = url;
            });
        </script>

        <div class="table-wrap">
            <table>
                <thead><tr><th><?php echo $getSortLink('nisn', 'NISN'); ?></th><th><?php echo $getSortLink('nama', 'Nama'); ?></th><th><?php echo $getSortLink('current_semester', 'Semester'); ?></th><th><?php echo $getSortLink('status_siswa', 'Status'); ?></th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($siswa as $s): ?>
                    <tr>
                        <td><?= e($s['nisn']) ?></td>
                        <td><?= e($s['nama']) ?></td>
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
                            <a class="page-link" href="index.php?page=siswa&search=<?= e($searchQuery) ?>&sort_by=<?= e($sortBy) ?>&sort_dir=<?= e($sortDir) ?>&per_page=<?= e((string) $perPage) ?>&page=1">Pertama</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=siswa&search=<?= e($searchQuery) ?>&sort_by=<?= e($sortBy) ?>&sort_dir=<?= e($sortDir) ?>&per_page=<?= e((string) $perPage) ?>&page=<?= e((string) ($page - 1)) ?>">Sebelumnya</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?page=siswa&search=<?= e($searchQuery) ?>&sort_by=<?= e($sortBy) ?>&sort_dir=<?= e($sortDir) ?>&per_page=<?= e((string) $perPage) ?>&page=<?= e((string) $p) ?>"><?= e((string) $p) ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=siswa&search=<?= e($searchQuery) ?>&sort_by=<?= e($sortBy) ?>&sort_dir=<?= e($sortDir) ?>&per_page=<?= e((string) $perPage) ?>&page=<?= e((string) ($page + 1)) ?>">Selanjutnya</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=siswa&search=<?= e($searchQuery) ?>&sort_by=<?= e($sortBy) ?>&sort_dir=<?= e($sortDir) ?>&per_page=<?= e((string) $perPage) ?>&page=<?= e((string) $totalPages) ?>">Terakhir</a>
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
                        <div class="col-md-4"><label class="form-label">Tanggal Lahir</label><input type="date" class="form-control" name="tgl_lahir" required></div>
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

<div class="modal fade" id="modalImportSiswa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Import Data Siswa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary mb-3">Unduh template, isi data siswa, lalu upload file Excel untuk input massal.</p>
                <form method="post" class="mb-3">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="download_template_siswa">
                    <button type="submit" class="btn btn-outline-success w-100">
                        <i class="bi bi-download me-1"></i>Download Template Siswa
                    </button>
                </form>

                <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="import_excel_siswa">
                    <div class="col-md-8">
                        <label class="form-label">File Excel (.xlsx/.xls)</label>
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
                        $stNilai = db()->prepare('SELECT nr.nilai_angka, m.nama_mapel FROM nilai_rapor nr JOIN mapel m ON nr.mapel_id=m.id WHERE nr.nisn=:nisn AND nr.semester=:sem AND nr.tahun_ajaran=:ta ORDER BY m.id');
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
                                                <td class="text-center"><?= e(number_format($n['nilai_angka'], 0)) ?></td>
                                                <td class="text-center"><?= ucwords(terbilang_bulat((int)$n['nilai_angka'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-secondary fw-bold">
                                            <td>Rata-Rata</td>
                                            <td class="text-center"><?= e(number_format($rataRata, 2)) ?></td>
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
                        $stUam = db()->prepare('SELECT nu.nilai_angka, m.nama_mapel FROM nilai_uam nu JOIN mapel m ON nu.mapel_id=m.id WHERE nu.nisn=:nisn ORDER BY m.id');
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
                                                <td class="text-center"><?= e(number_format($n['nilai_angka'], 0)) ?></td>
                                                <td class="text-center"><?= ucwords(terbilang_bulat((int)$n['nilai_angka'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-secondary fw-bold">
                                            <td>Rata-Rata</td>
                                            <td class="text-center"><?= e(number_format($rataRataUam, 2)) ?></td>
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
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require dirname(__DIR__) . '/partials/footer.php';
