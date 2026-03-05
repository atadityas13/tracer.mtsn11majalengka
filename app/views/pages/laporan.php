<?php
use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$setting = setting_akademik();
$tahunAjaranAktif = $setting['tahun_ajaran'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('ekspor-cetak');

    $action = $_POST['action'] ?? '';

    if ($action === 'leger') {
        if (!class_exists(Spreadsheet::class)) {
            set_flash('error', 'PhpSpreadsheet belum terpasang.');
            redirect('index.php?page=ekspor-cetak');
        }

        $semester = (int) ($_POST['semester'] ?? 1);
        if ($semester < 1 || $semester > 5) {
            $semester = 1;
        }

        $stmt = db()->prepare("SELECT s.nisn, s.nama, m.nama_mapel, nr.nilai_angka, nr.is_finalized
                               FROM nilai_rapor nr
                               JOIN siswa s ON s.nisn = nr.nisn
                               JOIN mapel m ON m.id = nr.mapel_id
                               WHERE nr.semester = :semester AND nr.tahun_ajaran = :ta
                               ORDER BY s.nama, m.nama_mapel");
        $stmt->execute(['semester' => $semester, 'ta' => $tahunAjaranAktif]);
        $rows = $stmt->fetchAll();

        $sheet = new Spreadsheet();
        $active = $sheet->getActiveSheet();
        $active->setTitle('Semester ' . $semester);
        $active->fromArray(['NISN', 'Nama', 'Mapel', 'Nilai', 'Finalized'], null, 'A1');

        $line = 2;
        foreach ($rows as $r) {
            $active->fromArray([
                $r['nisn'],
                $r['nama'],
                $r['nama_mapel'],
                (float) $r['nilai_angka'],
                (int) $r['is_finalized'] === 1 ? 'Ya' : 'Belum',
            ], null, 'A' . $line);
            $line++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="export_semester_' . $semester . '_' . str_replace('/', '-', $tahunAjaranAktif) . '.xlsx"');
        $writer = new Xlsx($sheet);
        $writer->save('php://output');
        exit;
    }

    if ($action === 'rekap_angkatan') {
        if (!class_exists(Spreadsheet::class)) {
            set_flash('error', 'PhpSpreadsheet belum terpasang.');
            redirect('index.php?page=ekspor-cetak');
        }

        $maxSemesterRaw = normalize_current_semester((int) (db()->query("SELECT MAX(current_semester) m FROM siswa WHERE status_siswa='Aktif'")->fetch()['m'] ?? 0));
        if ($maxSemesterRaw < 1) {
            set_flash('error', 'Belum ada data angkatan aktif untuk diekspor.');
            redirect('index.php?page=ekspor-cetak');
        }

        $maxSemesterAktif = $maxSemesterRaw >= 6 ? 5 : $maxSemesterRaw;
        $isAkhir = $maxSemesterRaw >= 6;

        if ($isAkhir) {
            $stSiswa = db()->prepare("SELECT nisn, nama FROM siswa WHERE status_siswa='Aktif' AND current_semester=6 ORDER BY nama");
            $stSiswa->execute();
        } else {
            $stSiswa = db()->prepare("SELECT nisn, nama FROM siswa WHERE status_siswa='Aktif' AND current_semester=:semester ORDER BY nama");
            $stSiswa->execute(['semester' => $maxSemesterAktif]);
        }
        $cohort = $stSiswa->fetchAll();

        if (count($cohort) === 0) {
            set_flash('error', 'Data angkatan untuk semester aktif tidak ditemukan.');
            redirect('index.php?page=ekspor-cetak');
        }

        $cohortMap = [];
        foreach ($cohort as $item) {
            $cohortMap[$item['nisn']] = $item['nama'];
        }

        $sheet = new Spreadsheet();
        $active = $sheet->getActiveSheet();
        $active->setTitle('Rekap Angkatan');
        $active->fromArray(['NISN', 'Nama', 'Jenis', 'Semester', 'Mapel', 'Nilai'], null, 'A1');

        $line = 2;
        $stRapor = db()->prepare("SELECT nr.nisn, nr.semester, m.nama_mapel, nr.nilai_angka
                                 FROM nilai_rapor nr
                                 JOIN mapel m ON m.id = nr.mapel_id
                                 WHERE nr.tahun_ajaran=:ta AND nr.semester BETWEEN 1 AND :maxSemester
                                 ORDER BY nr.nisn, nr.semester, m.nama_mapel");
        $stRapor->execute([
            'ta' => $tahunAjaranAktif,
            'maxSemester' => $maxSemesterAktif,
        ]);

        foreach ($stRapor->fetchAll() as $r) {
            if (!isset($cohortMap[$r['nisn']])) {
                continue;
            }
            $active->fromArray([
                $r['nisn'],
                $cohortMap[$r['nisn']],
                'RAPOR',
                (string) $r['semester'],
                $r['nama_mapel'],
                (float) $r['nilai_angka'],
            ], null, 'A' . $line);
            $line++;
        }

        if ($maxSemesterRaw >= 6) {
            $stUam = db()->query("SELECT nu.nisn, m.nama_mapel, nu.nilai_angka
                                  FROM nilai_uam nu
                                  JOIN mapel m ON m.id = nu.mapel_id
                                  ORDER BY nu.nisn, m.nama_mapel");
            foreach ($stUam->fetchAll() as $u) {
                if (!isset($cohortMap[$u['nisn']])) {
                    continue;
                }
                $active->fromArray([
                    $u['nisn'],
                    $cohortMap[$u['nisn']],
                    'UAM',
                    'UAM',
                    $u['nama_mapel'],
                    (float) $u['nilai_angka'],
                ], null, 'A' . $line);
                $line++;
            }
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="export_angkatan_sampai_semester_' . $maxSemesterAktif . '.xlsx"');
        $writer = new Xlsx($sheet);
        $writer->save('php://output');
        exit;
    }

    if ($action === 'transkrip') {
        if (!class_exists(Dompdf::class)) {
            set_flash('error', 'Dompdf belum terpasang.');
            redirect('index.php?page=ekspor-cetak');
        }

        $nisn = trim($_POST['nisn'] ?? '');
        $stmt = db()->prepare('SELECT a.nisn, a.angkatan_lulus, a.data_ijazah_json, s.nama FROM alumni a LEFT JOIN siswa s ON s.nisn=a.nisn WHERE a.nisn=:nisn LIMIT 1');
        $stmt->execute(['nisn' => $nisn]);
        $alumni = $stmt->fetch();

        if (!$alumni) {
            set_flash('error', 'Data alumni tidak ditemukan.');
            redirect('index.php?page=ekspor-cetak');
        }

        $detail = json_decode($alumni['data_ijazah_json'], true) ?: [];
        $rows = '';
        foreach ($detail as $d) {
            $rows .= '<tr>'
                . '<td>' . e($d['mapel']) . '</td>'
                . '<td>' . e((string) $d['rata_rapor']) . '</td>'
                . '<td>' . e((string) $d['nilai_uam']) . '</td>'
                . '<td>' . e((string) $d['nilai_ijazah']) . '</td>'
                . '<td>' . e($d['terbilang']) . '</td>'
                . '</tr>';
        }

        $html = '<h2>Transkrip Nilai Ijazah</h2>'
            . '<p>NISN: ' . e($alumni['nisn']) . '</p>'
            . '<p>Angkatan: ' . e((string) $alumni['angkatan_lulus']) . '</p>'
            . '<table border="1" cellspacing="0" cellpadding="6" width="100%">'
            . '<thead><tr><th>Mapel</th><th>Rata Rapor</th><th>UAM</th><th>Nilai Ijazah</th><th>Terbilang</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>';

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('transkrip_' . $nisn . '.pdf', ['Attachment' => true]);
        exit;
    }
}

$alumniList = db()->query('SELECT nisn, angkatan_lulus FROM alumni ORDER BY angkatan_lulus DESC, nisn')->fetchAll();
$maxSemesterRaw = normalize_current_semester((int) (db()->query("SELECT MAX(current_semester) m FROM siswa WHERE status_siswa='Aktif'")->fetch()['m'] ?? 0));
$maxSemesterAktif = $maxSemesterRaw >= 6 ? 5 : ($maxSemesterRaw >= 1 ? $maxSemesterRaw : 0);
$maxSemesterLabel = $maxSemesterRaw >= 6 ? 'Akhir' : ($maxSemesterAktif > 0 ? (string) $maxSemesterAktif : '-');

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-1">Ekspor Nilai Per Semester (Excel)</h3>
        <p class="text-secondary mb-0">Ekspor nilai per semester pada tahun ajaran aktif: <?= e($tahunAjaranAktif) ?>.</p>
    </div>
    <div class="card-body">
        <form method="post" class="row g-3 align-items-end">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="leger">
            <div class="col-md-6">
                <label class="form-label">Semester</label>
                <select name="semester" class="form-select">
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-success w-100">Download Excel</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-1">Ekspor 1 Angkatan (Semester 1 s/d Current)</h3>
        <p class="text-secondary mb-0">
            Berdasarkan current semester pada tahun ajaran aktif.
            <?php if ($maxSemesterAktif > 0): ?>
                Angkatan aktif terdeteksi di semester <?= e($maxSemesterLabel) ?>,
                sehingga file berisi nilai semester 1 sampai <?= e((string) $maxSemesterAktif) ?><?= $maxSemesterRaw >= 6 ? ' + UAM' : '' ?>.
            <?php endif; ?>
        </p>
    </div>
    <div class="card-body">
        <?php if ($maxSemesterAktif === 0): ?>
            <div class="alert alert-warning border mb-0">Belum ada data siswa aktif untuk menentukan angkatan.</div>
        <?php else: ?>
            <form method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="rekap_angkatan">
                <button type="submit" class="btn btn-primary">Download Ekspor Angkatan</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-0">Cetak Transkrip Ijazah (PDF)</h3>
    </div>
    <div class="card-body">
        <form method="post" class="row g-3 align-items-end">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="transkrip">
            <div class="col-md-8">
                <label class="form-label">Pilih Alumni (NISN)</label>
                <select name="nisn" class="form-select" required>
                    <option value="">-- pilih alumni --</option>
                    <?php foreach ($alumniList as $a): ?>
                        <option value="<?= e($a['nisn']) ?>"><?= e($a['nisn']) ?> - Angkatan <?= e((string) $a['angkatan_lulus']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-outline-success w-100">Download PDF</button>
            </div>
        </form>
    </div>
</div>
<?php require dirname(__DIR__) . '/partials/footer.php';
