<?php
/**
 * ========================================================
 * TRACER MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Manajemen Data Nilai Siswa
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Data Alumni Page
 * Deskripsi: Halaman view data alumni dengan modal nilai rapor, UAM, dan ijazah
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
 * - List data alumni dengan nama, NISN, tahun lulus
 * - Modal view nilai per semester (rapor 1-5, UAM, ijazah)
 * - Kalkulasi nilai ijazah otomatis dari rapor + UAM
 * - Backfill nama alumni dari siswa untuk data migrasi lama
 * - Pagination dan search alumni
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
$hasNamaColumn = (bool) db()->query("SHOW COLUMNS FROM alumni LIKE 'nama'")->fetch();
if (!$hasNamaColumn) {
    db()->exec("ALTER TABLE alumni ADD COLUMN nama VARCHAR(150) NULL AFTER nisn");
}
db()->exec("UPDATE alumni a LEFT JOIN siswa s ON s.nisn = a.nisn SET a.nama = s.nama WHERE (a.nama IS NULL OR a.nama='') AND s.nama IS NOT NULL AND s.nama <> ''");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('alumni');
    $action = $_POST['action'] ?? '';

    if ($action === 'batal_individu') {
        $nisn = trim($_POST['nisn'] ?? '');
        db()->beginTransaction();
        try {
            $stUpdate = db()->prepare("UPDATE siswa SET status_siswa='Aktif', current_semester=6 WHERE nisn=:nisn");
            $stUpdate->execute(['nisn' => $nisn]);
            $stDel = db()->prepare("DELETE FROM alumni WHERE nisn=:nisn");
            $stDel->execute(['nisn' => $nisn]);
            db()->commit();
            set_flash('success', 'Kelulusan siswa berhasil dibatalkan. Status dikembalikan ke Aktif.');
        } catch (Throwable $e) {
            db()->rollBack();
            set_flash('error', 'Gagal membatalkan kelulusan: ' . $e->getMessage());
        }
        redirect('index.php?page=alumni');
    }

    if ($action === 'batal_angkatan') {
        $tahunLulus = trim($_POST['batal_tahun_lulus'] ?? '');
        if ($tahunLulus !== '') {
            db()->beginTransaction();
            try {
                $stUpdate = db()->prepare("UPDATE siswa s JOIN alumni a ON s.nisn = a.nisn SET s.status_siswa='Aktif', s.current_semester=6 WHERE a.angkatan_lulus=:thn");
                $stUpdate->execute(['thn' => $tahunLulus]);
                $stDel = db()->prepare("DELETE FROM alumni WHERE angkatan_lulus=:thn");
                $stDel->execute(['thn' => $tahunLulus]);
                db()->commit();
                set_flash('success', "Kelulusan angkatan {$tahunLulus} berhasil dibatalkan.");
            } catch (Throwable $e) {
                db()->rollBack();
                set_flash('error', 'Gagal membatalkan kelulusan angkatan: ' . $e->getMessage());
            }
        }
        redirect('index.php?page=alumni');
    }
}

$filterYear = trim($_GET['tahun_lulus'] ?? '');

$sql = 'SELECT a.nisn, a.nama, a.angkatan_lulus, a.data_ijazah_json
    FROM alumni a';
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
    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1">Data Alumni</h3>
            <p class="text-secondary mb-0">Daftar siswa alumni dengan filter tahun lulus.</p>
        </div>
        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalBatalAngkatan">
            Batalkan Kelulusan Angkatan
        </button>
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
                                <div class="d-inline-flex gap-1">
                                    <form method="post" class="d-inline-block" data-confirm="Yakin ingin membatalkan kelulusan siswa ini? Siswa akan dikembalikan menjadi Aktif di semester 6." data-confirm-title="Batalkan Kelulusan">
                                        <?= csrf_input('alumni') ?>
                                        <input type="hidden" name="action" value="batal_individu">
                                        <input type="hidden" name="nisn" value="<?= e($row['nisn']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Batalkan Kelulusan">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalNilaiAlumni<?= e($row['nisn']) ?>" title="Lihat Nilai">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Batal Angkatan -->
<div class="modal fade" id="modalBatalAngkatan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="post">
                <?= csrf_input('alumni') ?>
                <input type="hidden" name="action" value="batal_angkatan">
                <div class="modal-header">
                    <h5 class="modal-title">Batalkan Kelulusan Angkatan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger">Tindakan ini akan mengembalikan seluruh alumni pada tahun lulus terkait kembali menjadi Siswa Aktif pada semester 6.</p>
                    <div class="mb-3">
                        <label class="form-label">Pilih Tahun Lulus Angkatan</label>
                        <select name="batal_tahun_lulus" class="form-select" required>
                            <option value="">- Pilih Tahun -</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= e((string)$y['angkatan_lulus']) ?>"><?= e((string)$y['angkatan_lulus']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Apakah Anda benar-benar yakin membatalkan kelulusan angkatan ini?');">Proses Pembatalan</button>
                </div>
            </form>
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

    $hasGradeSem = [];
    $nilaiPerSemester = [];
    for ($semester = 1; $semester <= 5; $semester++) {
        $stmtSemester = db()->prepare('SELECT m.nama_mapel, nr.nilai_angka FROM mapel m LEFT JOIN nilai_rapor nr ON m.id = nr.mapel_id AND nr.nisn=:nisn AND nr.semester=:semester ORDER BY m.id');
        $stmtSemester->execute([
            'nisn' => $row['nisn'],
            'semester' => $semester,
        ]);
        $nilaiPerSemester[$semester] = $stmtSemester->fetchAll();
        
        $stCek = db()->prepare('SELECT 1 FROM nilai_rapor WHERE nisn=:nisn AND semester=:sem LIMIT 1');
        $stCek->execute(['nisn' => $row['nisn'], 'sem' => $semester]);
        $hasGradeSem[$semester] = (bool) $stCek->fetch();
    }

    $nilaiUam = [];
    $hasGradeUam = false;
    if (count($dataIjazah) > 0) {
        $hasGradeUam = true;
        foreach ($dataIjazah as $item) {
            $nilaiUam[] = [
                'nama_mapel' => $item['mapel'] ?? '-',
                'nilai_angka' => (float) ($item['nilai_uam'] ?? 0),
            ];
        }
    } else {
        $stmtUam = db()->prepare('SELECT m.nama_mapel, nu.nilai_angka FROM mapel m LEFT JOIN nilai_uam nu ON m.id = nu.mapel_id AND nu.nisn=:nisn ORDER BY m.id');
        $stmtUam->execute(['nisn' => $row['nisn']]);
        $nilaiUam = $stmtUam->fetchAll();
        
        $stCekUam = db()->prepare('SELECT 1 FROM nilai_uam WHERE nisn=:nisn LIMIT 1');
        $stCekUam->execute(['nisn' => $row['nisn']]);
        $hasGradeUam = (bool) $stCekUam->fetch();
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
                                <button class="nav-link <?= $semester === 1 ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#alumni-sem<?= $semester ?>-<?= e($row['nisn']) ?>" type="button">Semester <?= $semester ?> <?= !$hasGradeSem[$semester] ? '<span class="badge bg-danger ms-1">Kosong</span>' : '' ?></button>
                            </li>
                        <?php endfor; ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#alumni-uam-<?= e($row['nisn']) ?>" type="button">UM/UAM <?= !$hasGradeUam ? '<span class="badge bg-danger ms-1">Kosong</span>' : '' ?></button>
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
                                <?php if (!$hasGradeSem[$semester]): ?>
                                    <div class="alert alert-warning py-2 mb-3">Belum ada nilai untuk semester ini.</div>
                                <?php endif; ?>
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
                                            <?php
                                            $totalSem = 0.0;
                                            $jumlahMapel = 0;
                                            foreach ($semesterRows as $semRow):
                                                $angka = $semRow['nilai_angka'] !== null ? (float) $semRow['nilai_angka'] : null;
                                                if ($angka !== null) {
                                                    $totalSem += $angka;
                                                    $jumlahMapel++;
                                                }
                                                ?>
                                                <tr>
                                                    <td><?= e($semRow['nama_mapel']) ?></td>
                                                    <td class="text-center">
                                                        <input type="text" readonly class="form-control form-control-sm text-center bg-white border-0" style="width: 80px; margin: 0 auto; outline: none; box-shadow: none;" value="<?= $angka !== null ? e(number_format($angka, 0)) : '' ?>" placeholder="00">
                                                    </td>
                                                    <td class="text-center"><?= $angka !== null ? e(ucwords(terbilang_bulat((int)$angka))) : '-' ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php $rataSem = $jumlahMapel > 0 ? $totalSem / $jumlahMapel : 0; ?>
                                            <tr class="table-secondary fw-bold">
                                                <td>Jumlah Nilai</td>
                                                <td class="text-center"><?= e(number_format($totalSem, 0)) ?></td>
                                                <td class="text-center"><?= $jumlahMapel > 0 ? e(ucwords(terbilang_bulat((int)$totalSem))) : '-' ?></td>
                                            </tr>
                                            <tr class="table-secondary fw-bold">
                                                <td>Rata-Rata</td>
                                                <td class="text-center"><?= e(number_format($rataSem, 2)) ?></td>
                                                <td class="text-center"><?= $jumlahMapel > 0 ? e(ucwords(terbilang_nilai($rataSem))) : '-' ?></td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                            </div>
                        <?php endfor; ?>

                        <div class="tab-pane fade" id="alumni-uam-<?= e($row['nisn']) ?>">
                            <h6 class="mb-3">Nilai UM/UAM</h6>
                            <?php if (!$hasGradeUam): ?>
                                <div class="alert alert-warning py-2 mb-3">Belum ada nilai UM/UAM.</div>
                            <?php endif; ?>
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
                                        <?php
                                        $totalUam = 0.0;
                                        $jumlahUam = 0;
                                        foreach ($nilaiUam as $uamRow):
                                            $angkaUam = $uamRow['nilai_angka'] !== null ? (float) $uamRow['nilai_angka'] : null;
                                            if ($angkaUam !== null) {
                                                $totalUam += $angkaUam;
                                                $jumlahUam++;
                                            }
                                            ?>
                                            <tr>
                                                <td><?= e($uamRow['nama_mapel']) ?></td>
                                                <td class="text-center">
                                                        <input type="text" readonly class="form-control form-control-sm text-center bg-white border-0" style="width: 80px; margin: 0 auto; outline: none; box-shadow: none;" value="<?= $angkaUam !== null ? e(number_format($angkaUam, 0)) : '' ?>" placeholder="00">
                                                </td>
                                                <td class="text-center"><?= $angkaUam !== null ? e(ucwords(terbilang_bulat((int)$angkaUam))) : '-' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php $rataUam = $jumlahUam > 0 ? $totalUam / $jumlahUam : 0; ?>
                                        <tr class="table-secondary fw-bold">
                                            <td>Jumlah Nilai</td>
                                            <td class="text-center"><?= e(number_format($totalUam, 0)) ?></td>
                                            <td class="text-center"><?= $jumlahUam > 0 ? e(ucwords(terbilang_bulat((int)$totalUam))) : '-' ?></td>
                                        </tr>
                                        <tr class="table-secondary fw-bold">
                                            <td>Rata-Rata</td>
                                            <td class="text-center"><?= e(number_format($rataUam, 2)) ?></td>
                                            <td class="text-center"><?= $jumlahUam > 0 ? e(ucwords(terbilang_nilai($rataUam))) : '-' ?></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
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
                                                <td class="text-center"><?= e((string)round($ijazahRow['nilai_uam'])) ?></td>
                                                <td class="text-center"><?= e((string)round($ijazahRow['nilai_ijazah'])) ?></td>
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
