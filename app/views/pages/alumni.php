<?php
$filterYear = trim($_GET['tahun_lulus'] ?? '');

$sql = 'SELECT a.nisn, a.angkatan_lulus, a.data_ijazah_json, s.nama
        FROM alumni a
        LEFT JOIN siswa s ON s.nisn = a.nisn';
$params = [];

if ($filterYear !== '') {
    $sql .= ' WHERE a.angkatan_lulus = :tahun_lulus';
    $params['tahun_lulus'] = $filterYear;
}

$sql .= ' ORDER BY a.angkatan_lulus DESC, a.nisn';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$years = db()->query('SELECT DISTINCT angkatan_lulus FROM alumni ORDER BY angkatan_lulus DESC')->fetchAll();

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-1">Data Alumni</h3>
        <p class="text-secondary mb-0">Daftar siswa alumni dengan filter tahun lulus.</p>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end mb-2">
            <input type="hidden" name="page" value="alumni">
            <div class="col-md-4">
                <label class="form-label">Tahun Lulus</label>
                <select name="tahun_lulus" class="form-select">
                    <option value="">Semua Tahun</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= e((string) $year['angkatan_lulus']) ?>" <?= $filterYear === (string) $year['angkatan_lulus'] ? 'selected' : '' ?>>
                            <?= e((string) $year['angkatan_lulus']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-success w-100">Terapkan Filter</button>
            </div>
            <div class="col-md-3">
                <a href="index.php?page=alumni" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>NISN</th>
                    <th>Nama</th>
                    <th>Tahun Lulus</th>
                    <th class="text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($rows) === 0): ?>
                    <tr>
                        <td colspan="4" class="text-center text-secondary">Belum ada data alumni.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['nisn']) ?></td>
                            <td><?= e($row['nama'] ?: '-') ?></td>
                            <td><?= e((string) $row['angkatan_lulus']) ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalNilaiAlumni<?= e($row['nisn']) ?>">
                                    <i class="bi bi-eye me-1"></i>Lihat Nilai
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($rows as $row): ?>
    <?php
    $dataIjazah = [];
    if (!empty($row['data_ijazah_json'])) {
        $decoded = json_decode((string) $row['data_ijazah_json'], true);
        if (is_array($decoded)) {
            $dataIjazah = $decoded;
        }
    }

    $nilaiPerSemester = [];
    for ($semester = 1; $semester <= 5; $semester++) {
        $stmtSemester = db()->prepare('SELECT m.nama_mapel, nr.nilai_angka FROM nilai_rapor nr JOIN mapel m ON m.id = nr.mapel_id WHERE nr.nisn=:nisn AND nr.semester=:semester ORDER BY m.id');
        $stmtSemester->execute([
            'nisn' => $row['nisn'],
            'semester' => $semester,
        ]);
        $nilaiPerSemester[$semester] = $stmtSemester->fetchAll();
    }

    $nilaiUam = [];
    if (count($dataIjazah) > 0) {
        foreach ($dataIjazah as $item) {
            $nilaiUam[] = [
                'nama_mapel' => $item['mapel'] ?? '-',
                'nilai_angka' => (float) ($item['nilai_uam'] ?? 0),
            ];
        }
    } else {
        $stmtUam = db()->prepare('SELECT m.nama_mapel, nu.nilai_angka FROM nilai_uam nu JOIN mapel m ON m.id = nu.mapel_id WHERE nu.nisn=:nisn ORDER BY m.id');
        $stmtUam->execute(['nisn' => $row['nisn']]);
        $nilaiUam = $stmtUam->fetchAll();
    }

    $nilaiIjazahRows = [];
    $totalIjazah = 0.0;
    foreach ($dataIjazah as $item) {
        $rataRapor = (float) ($item['rata_rapor'] ?? 0);
        $uam = (float) ($item['nilai_uam'] ?? 0);
        $ijazahHitungUlang = hitung_nilai_ijazah($rataRapor, $uam);
        $nilaiIjazahRows[] = [
            'mapel' => $item['mapel'] ?? '-',
            'rata_rapor' => $rataRapor,
            'nilai_uam' => $uam,
            'nilai_ijazah' => $ijazahHitungUlang,
        ];
        $totalIjazah += $ijazahHitungUlang;
    }
    $rataIjazahAkhir = count($nilaiIjazahRows) > 0 ? $totalIjazah / count($nilaiIjazahRows) : 0;
    ?>

    <div class="modal fade" id="modalNilaiAlumni<?= e($row['nisn']) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title">Nilai Alumni: <?= e($row['nama'] ?: '-') ?> (<?= e($row['nisn']) ?>)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" id="tabNilaiAlumni<?= e($row['nisn']) ?>" role="tablist">
                        <?php for ($semester = 1; $semester <= 5; $semester++): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= $semester === 1 ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#alumni-sem<?= $semester ?>-<?= e($row['nisn']) ?>" type="button">Semester <?= $semester ?></button>
                            </li>
                        <?php endfor; ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#alumni-uam-<?= e($row['nisn']) ?>" type="button">UM/UAM</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#alumni-ijazah-<?= e($row['nisn']) ?>" type="button">Nilai Ijazah</button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <?php for ($semester = 1; $semester <= 5; $semester++): ?>
                            <?php $semesterRows = $nilaiPerSemester[$semester] ?? []; ?>
                            <div class="tab-pane fade <?= $semester === 1 ? 'show active' : '' ?>" id="alumni-sem<?= $semester ?>-<?= e($row['nisn']) ?>">
                                <h6 class="mb-3">Nilai Semester <?= $semester ?></h6>
                                <?php if (count($semesterRows) === 0): ?>
                                    <p class="text-secondary text-center mb-0">Data nilai semester tidak tersedia.</p>
                                <?php else: ?>
                                    <div class="table-wrap">
                                        <table class="table table-sm table-bordered">
                                            <thead>
                                            <tr>
                                                <th>Mata Pelajaran</th>
                                                <th class="text-center">Angka</th>
                                                <th class="text-center">Huruf</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $totalSem = 0.0;
                                            foreach ($semesterRows as $semRow):
                                                $angka = (float) ($semRow['nilai_angka'] ?? 0);
                                                $totalSem += $angka;
                                                ?>
                                                <tr>
                                                    <td><?= e($semRow['nama_mapel']) ?></td>
                                                    <td class="text-center"><?= e(number_format($angka, 2)) ?></td>
                                                    <td class="text-center"><?= e(ucwords(terbilang_nilai($angka))) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php $rataSem = $totalSem / count($semesterRows); ?>
                                            <tr class="table-secondary fw-bold">
                                                <td>Rata-Rata</td>
                                                <td class="text-center"><?= e(number_format($rataSem, 2)) ?></td>
                                                <td class="text-center"><?= e(ucwords(terbilang_nilai($rataSem))) ?></td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>

                        <div class="tab-pane fade" id="alumni-uam-<?= e($row['nisn']) ?>">
                            <h6 class="mb-3">Nilai UM/UAM</h6>
                            <?php if (count($nilaiUam) === 0): ?>
                                <p class="text-secondary text-center mb-0">Data nilai UM/UAM tidak tersedia.</p>
                            <?php else: ?>
                                <div class="table-wrap">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                        <tr>
                                            <th>Mata Pelajaran</th>
                                            <th class="text-center">Angka</th>
                                            <th class="text-center">Huruf</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $totalUam = 0.0;
                                        foreach ($nilaiUam as $uamRow):
                                            $angkaUam = (float) ($uamRow['nilai_angka'] ?? 0);
                                            $totalUam += $angkaUam;
                                            ?>
                                            <tr>
                                                <td><?= e($uamRow['nama_mapel']) ?></td>
                                                <td class="text-center"><?= e(number_format($angkaUam, 2)) ?></td>
                                                <td class="text-center"><?= e(ucwords(terbilang_nilai($angkaUam))) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php $rataUam = $totalUam / count($nilaiUam); ?>
                                        <tr class="table-secondary fw-bold">
                                            <td>Rata-Rata</td>
                                            <td class="text-center"><?= e(number_format($rataUam, 2)) ?></td>
                                            <td class="text-center"><?= e(ucwords(terbilang_nilai($rataUam))) ?></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="tab-pane fade" id="alumni-ijazah-<?= e($row['nisn']) ?>">
                            <h6 class="mb-3">Nilai Ijazah (Rumus: 60% Rata-Rata Rapor + 40% UM/UAM)</h6>
                            <?php if (count($nilaiIjazahRows) === 0): ?>
                                <p class="text-secondary text-center mb-0">Data nilai ijazah tidak tersedia.</p>
                            <?php else: ?>
                                <div class="table-wrap">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                        <tr>
                                            <th>Mata Pelajaran</th>
                                            <th class="text-center">Rata Rapor</th>
                                            <th class="text-center">UM/UAM</th>
                                            <th class="text-center">Nilai Ijazah</th>
                                            <th class="text-center">Huruf</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($nilaiIjazahRows as $ijazahRow): ?>
                                            <tr>
                                                <td><?= e($ijazahRow['mapel']) ?></td>
                                                <td class="text-center"><?= e(number_format($ijazahRow['rata_rapor'], 2)) ?></td>
                                                <td class="text-center"><?= e(number_format($ijazahRow['nilai_uam'], 2)) ?></td>
                                                <td class="text-center"><?= e(number_format($ijazahRow['nilai_ijazah'], 2)) ?></td>
                                                <td class="text-center"><?= e(ucwords(terbilang_nilai($ijazahRow['nilai_ijazah']))) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-secondary fw-bold">
                                            <td colspan="3">Rata-Rata Nilai Ijazah</td>
                                            <td class="text-center"><?= e(number_format($rataIjazahAkhir, 2)) ?></td>
                                            <td class="text-center"><?= e(ucwords(terbilang_nilai($rataIjazahAkhir))) ?></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
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
