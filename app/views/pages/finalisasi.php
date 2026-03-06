<?php
/**
 * ========================================================
 * E-LEGER MTSN 11 MAJALENGKA
 * ========================================================
 *
 * File: Finalisasi Nilai Per Kelompok Semester
 * Deskripsi: Finalisasi nilai rapor dan kenaikan semester per target semester
 *            dan per kelas/angkatan, tidak sekaligus.
 */

$setting = setting_akademik();
$targetSemester = semester_upload_target($setting['semester_aktif']);
$user = current_user();
$operator = (string) (($user['username'] ?? '') !== '' ? $user['username'] : ($user['nama_lengkap'] ?? 'system'));

$riwayatReady = true;
try {
    db()->query('SELECT id FROM finalisasi_riwayat LIMIT 1');
} catch (Throwable $e) {
    $riwayatReady = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('finalisasi');

    $action = $_POST['action'] ?? '';

    if ($action === 'finalisasi_per_target') {
        if (!$riwayatReady) {
            set_flash('error', 'Tabel riwayat finalisasi belum tersedia. Jalankan migration 002_create_finalisasi_riwayat.sql terlebih dahulu.');
            redirect('index.php?page=finalisasi');
        }

        $semesterTarget = (int) ($_POST['semester_target'] ?? 0);
        $kelasFilter = trim($_POST['kelas_filter'] ?? '');

        if (!in_array($semesterTarget, $targetSemester, true)) {
            set_flash('error', 'Semester target tidak valid untuk semester aktif saat ini.');
            redirect('index.php?page=finalisasi');
        }

        $paramsSiswa = ['sem' => $semesterTarget];
        $whereKelas = '';
        if ($kelasFilter !== '') {
            $whereKelas = ' AND kelas = :kelas';
            $paramsSiswa['kelas'] = $kelasFilter;
        }

        $sqlSiswa = "SELECT nisn FROM siswa WHERE status_siswa='Aktif' AND current_semester = :sem" . $whereKelas;
        $stmtSiswa = db()->prepare($sqlSiswa);
        $stmtSiswa->execute($paramsSiswa);
        $nisnList = array_column($stmtSiswa->fetchAll(), 'nisn');

        if (empty($nisnList)) {
            set_flash('error', 'Tidak ada siswa aktif pada semester/angkatan yang dipilih.');
            redirect('index.php?page=finalisasi');
        }

        db()->beginTransaction();
        try {
            $in = implode(',', array_fill(0, count($nisnList), '?'));

            $paramsFinal = $nisnList;
            array_unshift($paramsFinal, $semesterTarget);
            array_unshift($paramsFinal, $setting['tahun_ajaran']);

            $sqlFinal = "UPDATE nilai_rapor
                         SET is_finalized = 1
                         WHERE tahun_ajaran = ?
                           AND semester = ?
                           AND nisn IN ($in)";
            $stmtFinal = db()->prepare($sqlFinal);
            $stmtFinal->execute($paramsFinal);
            $nilaiUpdated = $stmtFinal->rowCount();

            $sqlNaik = "UPDATE siswa
                        SET current_semester = CASE
                            WHEN current_semester < 5 THEN current_semester + 1
                            WHEN current_semester = 5 THEN 6
                            ELSE current_semester
                        END
                        WHERE status_siswa = 'Aktif'
                          AND current_semester = ?
                          AND nisn IN ($in)";
            $paramsNaik = $nisnList;
            array_unshift($paramsNaik, $semesterTarget);
            $stmtNaik = db()->prepare($sqlNaik);
            $stmtNaik->execute($paramsNaik);
            $siswaUpdated = $stmtNaik->rowCount();

            $stmtRiwayat = db()->prepare('INSERT INTO finalisasi_riwayat
                (tahun_ajaran, semester_aktif, semester_target, kelas_filter, nisn_json, jumlah_nilai_locked, jumlah_siswa_promoted, created_by)
                VALUES (:ta,:sem_aktif,:sem_target,:kelas,:nisn_json,:locked,:promoted,:created_by)');
            $stmtRiwayat->execute([
                'ta' => (string) $setting['tahun_ajaran'],
                'sem_aktif' => (string) $setting['semester_aktif'],
                'sem_target' => $semesterTarget,
                'kelas' => $kelasFilter !== '' ? $kelasFilter : null,
                'nisn_json' => json_encode(array_values($nisnList), JSON_UNESCAPED_UNICODE),
                'locked' => $nilaiUpdated,
                'promoted' => $siswaUpdated,
                'created_by' => $operator,
            ]);
            $riwayatId = (int) db()->lastInsertId();

            db()->commit();

            $keteranganKelas = $kelasFilter !== '' ? (' kelas ' . $kelasFilter) : ' semua kelas';
            set_flash(
                'success',
                'Finalisasi berhasil untuk semester ' . $semesterTarget . $keteranganKelas
                . '. Nilai terkunci: ' . $nilaiUpdated . ' baris, siswa dipromosikan: ' . $siswaUpdated . ' orang. Riwayat #' . $riwayatId . ' tercatat.'
            );
        } catch (Throwable $e) {
            db()->rollBack();
            set_flash('error', 'Finalisasi gagal: ' . $e->getMessage());
        }

        redirect('index.php?page=finalisasi');
    }

    if ($action === 'batalkan_finalisasi') {
        if (!$riwayatReady) {
            set_flash('error', 'Tabel riwayat finalisasi belum tersedia. Jalankan migration 002_create_finalisasi_riwayat.sql terlebih dahulu.');
            redirect('index.php?page=finalisasi');
        }

        $riwayatId = (int) ($_POST['riwayat_id'] ?? 0);
        if ($riwayatId <= 0) {
            set_flash('error', 'Data riwayat finalisasi tidak valid.');
            redirect('index.php?page=finalisasi');
        }

        $stmtRiwayat = db()->prepare('SELECT * FROM finalisasi_riwayat WHERE id = :id LIMIT 1');
        $stmtRiwayat->execute(['id' => $riwayatId]);
        $riwayat = $stmtRiwayat->fetch();

        if (!$riwayat) {
            set_flash('error', 'Riwayat tidak ditemukan atau sudah dibatalkan sebelumnya.');
            redirect('index.php?page=finalisasi');
        }
        $nisnList = json_decode((string) ($riwayat['nisn_json'] ?? '[]'), true);
        if (!is_array($nisnList) || count($nisnList) === 0) {
            set_flash('error', 'Data NISN pada riwayat tidak valid.');
            redirect('index.php?page=finalisasi');
        }

        $semesterTarget = (int) ($riwayat['semester_target'] ?? 0);
        if ($semesterTarget <= 0) {
            set_flash('error', 'Semester target pada riwayat tidak valid.');
            redirect('index.php?page=finalisasi');
        }

        $semesterNaik = $semesterTarget < 5 ? ($semesterTarget + 1) : 6;

        db()->beginTransaction();
        try {
            $in = implode(',', array_fill(0, count($nisnList), '?'));

            $paramsUnlock = $nisnList;
            array_unshift($paramsUnlock, $semesterTarget);
            array_unshift($paramsUnlock, (string) $riwayat['tahun_ajaran']);

            $sqlUnlock = "UPDATE nilai_rapor
                          SET is_finalized = 0
                          WHERE tahun_ajaran = ?
                            AND semester = ?
                            AND nisn IN ($in)";
            $stmtUnlock = db()->prepare($sqlUnlock);
            $stmtUnlock->execute($paramsUnlock);
            $nilaiUnlocked = $stmtUnlock->rowCount();

            $paramsTurun = $nisnList;
            array_unshift($paramsTurun, $semesterNaik);
            array_unshift($paramsTurun, $semesterTarget);

            $sqlTurun = "UPDATE siswa
                         SET current_semester = ?
                         WHERE status_siswa = 'Aktif'
                           AND current_semester = ?
                           AND nisn IN ($in)";
            $stmtTurun = db()->prepare($sqlTurun);
            $stmtTurun->execute($paramsTurun);
            $siswaDiturunkan = $stmtTurun->rowCount();

            $stmtDeleteRiwayat = db()->prepare('DELETE FROM finalisasi_riwayat WHERE id = :id');
            $stmtDeleteRiwayat->execute(['id' => $riwayatId]);

            if ($stmtDeleteRiwayat->rowCount() !== 1) {
                throw new RuntimeException('Riwayat gagal dihapus.');
            }

            db()->commit();
            set_flash('success', 'Pembatalan finalisasi #' . $riwayatId . ' berhasil. Nilai dibuka: ' . $nilaiUnlocked . ' baris, semester dikembalikan: ' . $siswaDiturunkan . ' siswa.');
        } catch (Throwable $e) {
            db()->rollBack();
            set_flash('error', 'Pembatalan finalisasi gagal: ' . $e->getMessage());
        }

        redirect('index.php?page=finalisasi');
    }
}

$setting = setting_akademik();
$targetSemester = semester_upload_target($setting['semester_aktif']);

$inSem = implode(',', array_fill(0, count($targetSemester), '?'));
$stmtKelas = db()->prepare("SELECT DISTINCT kelas FROM siswa
    WHERE status_siswa='Aktif' AND kelas IS NOT NULL AND kelas <> '' AND current_semester IN ($inSem)
    ORDER BY kelas ASC");
$stmtKelas->execute($targetSemester);
$kelasList = array_column($stmtKelas->fetchAll(), 'kelas');

$ringkasan = [];
foreach ($targetSemester as $sem) {
    $stmtCount = db()->prepare("SELECT COUNT(*) FROM siswa WHERE status_siswa='Aktif' AND current_semester = :sem");
    $stmtCount->execute(['sem' => $sem]);
    $ringkasan[$sem] = (int) $stmtCount->fetchColumn();
}

$riwayatList = [];
$riwayatTaOptions = [];
$filterTa = trim((string) ($_GET['filter_ta'] ?? ''));
$filterKelompok = strtoupper(trim((string) ($_GET['filter_kelompok'] ?? 'ALL')));

if (!in_array($filterKelompok, ['ALL', 'GANJIL', 'GENAP'], true)) {
    $filterKelompok = 'ALL';
}

if ($riwayatReady) {
    $riwayatTaOptions = db()->query('SELECT DISTINCT tahun_ajaran FROM finalisasi_riwayat ORDER BY tahun_ajaran DESC')->fetchAll();

    $where = [];
    $params = [];

    if ($filterTa !== '') {
        $where[] = 'tahun_ajaran = :ta';
        $params['ta'] = $filterTa;
    }
    if ($filterKelompok !== 'ALL') {
        $where[] = 'semester_aktif = :kelompok';
        $params['kelompok'] = $filterKelompok;
    }

    $sqlRiwayat = 'SELECT id, tahun_ajaran, semester_aktif, semester_target, kelas_filter,
        jumlah_nilai_locked, jumlah_siswa_promoted, created_by, created_at
        FROM finalisasi_riwayat';
    if (!empty($where)) {
        $sqlRiwayat .= ' WHERE ' . implode(' AND ', $where);
    }
    $sqlRiwayat .= ' ORDER BY id DESC LIMIT 100';

    $stmtRiwayatList = db()->prepare($sqlRiwayat);
    $stmtRiwayatList->execute($params);
    $riwayatList = $stmtRiwayatList->fetchAll();
}

require dirname(__DIR__) . '/partials/header.php';
?>

<div class="alert alert-info border mb-4">
    <strong>Tahun Ajaran Aktif:</strong> <?= e($setting['tahun_ajaran']) ?>
    <span class="mx-2">|</span>
    <strong>Semester Aktif:</strong> <?= e($setting['semester_aktif']) ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-0">Finalisasi Per Kelompok Semester</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-warning border mb-3">
            Finalisasi dilakukan bertahap, tidak sekaligus. Pilih 1 semester target lalu pilih angkatan/kelas
            (opsional) agar Anda bisa finalisasi per kelompok.
            <br>
            Target semester sesuai semester aktif:
            <strong>
                <?php if (strtoupper($setting['semester_aktif']) === 'GANJIL'): ?>
                    GANJIL: 1, 3, 5
                <?php else: ?>
                    GENAP: 2, 4
                <?php endif; ?>
            </strong>
        </div>

        <form method="post" class="row g-3 align-items-end"
              data-confirm="Yakin finalisasi sesuai semester dan angkatan yang dipilih? Proses ini mengunci nilai dan menaikkan semester siswa." 
              data-confirm-title="Konfirmasi Finalisasi">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="finalisasi_per_target">

            <div class="col-md-4">
                <label class="form-label">Semester Target</label>
                <select name="semester_target" class="form-select" required>
                    <option value="">-- pilih semester --</option>
                    <?php foreach ($targetSemester as $sem): ?>
                        <option value="<?= e((string) $sem) ?>">Semester <?= e((string) $sem) ?> (<?= e((string) ($ringkasan[$sem] ?? 0)) ?> siswa aktif)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-5">
                <label class="form-label">Angkatan/Kelas (Opsional)</label>
                <select name="kelas_filter" class="form-select">
                    <option value="">Semua kelas pada semester target</option>
                    <?php foreach ($kelasList as $kelas): ?>
                        <option value="<?= e($kelas) ?>"><?= e($kelas) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-secondary">Jika diisi, finalisasi hanya untuk kelas tersebut.</small>
            </div>

            <div class="col-md-3">
                <button type="submit" class="btn btn-danger w-100">Finalisasi Sekarang</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-0">Riwayat Finalisasi</h3>
    </div>
    <div class="card-body">
        <?php if (!$riwayatReady): ?>
            <div class="alert alert-danger border mb-0">
                Tabel riwayat finalisasi belum tersedia. Jalankan migration <code>database/migrations/002_create_finalisasi_riwayat.sql</code>.
            </div>
        <?php elseif (empty($riwayatList)): ?>
            <form method="get" class="row g-2 mb-3">
                <input type="hidden" name="page" value="finalisasi">
                <div class="col-md-4">
                    <label class="form-label">Tahun Ajaran</label>
                    <select name="filter_ta" class="form-select">
                        <option value="">Semua</option>
                        <?php foreach ($riwayatTaOptions as $opt): ?>
                            <?php $optTa = (string) ($opt['tahun_ajaran'] ?? ''); ?>
                            <option value="<?= e($optTa) ?>" <?= $filterTa === $optTa ? 'selected' : '' ?>><?= e($optTa) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kelompok</label>
                    <select name="filter_kelompok" class="form-select">
                        <option value="ALL" <?= $filterKelompok === 'ALL' ? 'selected' : '' ?>>Semua</option>
                        <option value="GANJIL" <?= $filterKelompok === 'GANJIL' ? 'selected' : '' ?>>GANJIL</option>
                        <option value="GENAP" <?= $filterKelompok === 'GENAP' ? 'selected' : '' ?>>GENAP</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                </div>
            </form>
            <div class="alert alert-secondary border mb-0">Belum ada riwayat finalisasi.</div>
        <?php else: ?>
            <form method="get" class="row g-2 mb-3">
                <input type="hidden" name="page" value="finalisasi">
                <div class="col-md-4">
                    <label class="form-label">Tahun Ajaran</label>
                    <select name="filter_ta" class="form-select">
                        <option value="">Semua</option>
                        <?php foreach ($riwayatTaOptions as $opt): ?>
                            <?php $optTa = (string) ($opt['tahun_ajaran'] ?? ''); ?>
                            <option value="<?= e($optTa) ?>" <?= $filterTa === $optTa ? 'selected' : '' ?>><?= e($optTa) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kelompok</label>
                    <select name="filter_kelompok" class="form-select">
                        <option value="ALL" <?= $filterKelompok === 'ALL' ? 'selected' : '' ?>>Semua</option>
                        <option value="GANJIL" <?= $filterKelompok === 'GANJIL' ? 'selected' : '' ?>>GANJIL</option>
                        <option value="GENAP" <?= $filterKelompok === 'GENAP' ? 'selected' : '' ?>>GENAP</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                </div>
            </form>
            <div class="small text-secondary mb-2">Menampilkan <?= e((string) count($riwayatList)) ?> riwayat terakhir (maks. 100 baris).</div>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Tahun Ajaran</th>
                            <th>Kelompok</th>
                            <th>Semester Target</th>
                            <th>Kelas</th>
                            <th>Nilai Terkunci</th>
                            <th>Siswa Dipromosikan</th>
                            <th>Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayatList as $row): ?>
                            <tr>
                                <td>#<?= e((string) $row['id']) ?></td>
                                <td><?= e((string) $row['tahun_ajaran']) ?></td>
                                <td><?= e((string) $row['semester_aktif']) ?></td>
                                <td><?= e((string) $row['semester_target']) ?></td>
                                <td><?= e((string) ($row['kelas_filter'] ?: 'Semua')) ?></td>
                                <td><?= e((string) $row['jumlah_nilai_locked']) ?></td>
                                <td><?= e((string) $row['jumlah_siswa_promoted']) ?></td>
                                <td>
                                    <?= e((string) $row['created_at']) ?><br>
                                    <small class="text-secondary">oleh <?= e((string) $row['created_by']) ?></small>
                                </td>
                                <td>
                                    <form method="post" class="d-inline"
                                          data-confirm="Yakin batalkan finalisasi #<?= e((string) $row['id']) ?>? Nilai akan dibuka dan semester siswa target dikembalikan."
                                          data-confirm-title="Konfirmasi Pembatalan">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="batalkan_finalisasi">
                                        <input type="hidden" name="riwayat_id" value="<?= e((string) $row['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Batalkan</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require dirname(__DIR__) . '/partials/footer.php';
