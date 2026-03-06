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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('finalisasi');

    $action = $_POST['action'] ?? '';

    if ($action === 'finalisasi_per_target') {
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

            db()->commit();

            $keteranganKelas = $kelasFilter !== '' ? (' kelas ' . $kelasFilter) : ' semua kelas';
            set_flash(
                'success',
                'Finalisasi berhasil untuk semester ' . $semesterTarget . $keteranganKelas
                . '. Nilai terkunci: ' . $nilaiUpdated . ' baris, siswa dipromosikan: ' . $siswaUpdated . ' orang.'
            );
        } catch (Throwable $e) {
            db()->rollBack();
            set_flash('error', 'Finalisasi gagal: ' . $e->getMessage());
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

<?php require dirname(__DIR__) . '/partials/footer.php';
