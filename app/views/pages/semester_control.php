<?php
/**
 * ========================================================
 * E-LEGER MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Manajemen Data Nilai Siswa
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Pengaturan Semester/Tahun Ajaran Control Page
 * Deskripsi: Halaman untuk manage tahun ajaran dan semester aktif
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
 * - Tambah tahun ajaran baru
 * - Pilih semester aktif (GANJIL/GENAP)
 * - Finalisasi semester (lock values, promote students)
 * - Lihat history tahun ajaran & semester
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

$setting = setting_akademik();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('semester-control');

    $action = $_POST['action'] ?? '';

    if ($action === 'tambah_tahun_ajaran') {
        $tahunAjaran = trim($_POST['tahun_ajaran'] ?? '');
        $semesterPilih = strtoupper($_POST['semester_pilih'] ?? 'GANJIL');
        
        if ($tahunAjaran === '') {
            set_flash('error', 'Tahun ajaran wajib diisi.');
            redirect('index.php?page=semester-control');
        }
        
        // Cek apakah kombinasi sudah ada
        $cek = db()->prepare('SELECT id FROM pengaturan_akademik WHERE tahun_ajaran=:ta AND semester_aktif=:sem LIMIT 1');
        $cek->execute(['ta' => $tahunAjaran, 'sem' => $semesterPilih]);
        
        if ($cek->fetch()) {
            set_flash('error', "Tahun ajaran {$tahunAjaran} dengan semester {$semesterPilih} sudah terdaftar.");
            redirect('index.php?page=semester-control');
        }
        
        $stmt = db()->prepare('INSERT INTO pengaturan_akademik (tahun_ajaran, semester_aktif, is_aktif) VALUES (:ta,:sem,:aktif)');
        $stmt->execute([
            'ta' => $tahunAjaran,
            'sem' => $semesterPilih,
            'aktif' => 0, // Default tidak aktif
        ]);
        set_flash('success', "Tahun ajaran {$tahunAjaran} semester {$semesterPilih} berhasil ditambahkan.");
        redirect('index.php?page=semester-control');
    }

    if ($action === 'set_aktif') {
        $tahunAjaran = trim($_POST['tahun_ajaran'] ?? '');
        $semesterAktif = strtoupper($_POST['semester_aktif'] ?? 'GANJIL');
        
        if ($tahunAjaran === '') {
            set_flash('error', 'Pilih tahun ajaran dan semester.');
            redirect('index.php?page=semester-control');
        }
        
        db()->beginTransaction();
        try {
            // Matikan semua yang aktif
            db()->exec('UPDATE pengaturan_akademik SET is_aktif=0');
            
            // Aktifkan yang dipilih
            $stmt = db()->prepare('UPDATE pengaturan_akademik SET is_aktif=1 WHERE tahun_ajaran=:ta AND semester_aktif=:sem');
            $stmt->execute(['ta' => $tahunAjaran, 'sem' => $semesterAktif]);
            
            db()->commit();
            set_flash('success', "Tahun ajaran {$tahunAjaran} semester {$semesterAktif} berhasil diaktifkan.");
        } catch (Throwable $e) {
            db()->rollBack();
            set_flash('error', 'Gagal set aktif: ' . $e->getMessage());
        }
        
        redirect('index.php?page=semester-control');
    }

    if ($action === 'finalisasi') {
        $active = setting_akademik();
        $target = semester_upload_target($active['semester_aktif']);

        db()->beginTransaction();
        try {
            $in = implode(',', array_fill(0, count($target), '?'));

            $params = $target;
            array_unshift($params, $active['tahun_ajaran']);

            $sqlFinal = "UPDATE nilai_rapor SET is_finalized=1 WHERE tahun_ajaran=? AND semester IN ($in)";
            $stmtFinal = db()->prepare($sqlFinal);
            $stmtFinal->execute($params);

            $sqlNaik = "UPDATE siswa SET current_semester = CASE
                        WHEN current_semester < 5 THEN current_semester + 1
                        WHEN current_semester = 5 THEN 6
                        ELSE current_semester END
                        WHERE status_siswa='Aktif' AND current_semester IN ($in)";
            $stmtNaik = db()->prepare($sqlNaik);
            $stmtNaik->execute($target);

            db()->commit();
            set_flash('success', 'Finalisasi berhasil: nilai dikunci dan current_semester siswa aktif dinaikkan.');
        } catch (Throwable $e) {
            db()->rollBack();
            set_flash('error', 'Finalisasi gagal: ' . $e->getMessage());
        }

        redirect('index.php?page=semester-control');
    }
}

$setting = setting_akademik();
$allSettings = db()->query('SELECT tahun_ajaran, semester_aktif, is_aktif FROM pengaturan_akademik ORDER BY tahun_ajaran DESC, FIELD(semester_aktif, "GANJIL", "GENAP")')->fetchAll();
$tahunAjaranList = db()->query('SELECT DISTINCT tahun_ajaran FROM pengaturan_akademik ORDER BY tahun_ajaran DESC')->fetchAll();

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-0">Tambah Tahun Ajaran Baru</h3>
    </div>
    <div class="card-body">
        <form method="post" class="row g-3">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="tambah_tahun_ajaran">
            <div class="col-md-6">
                <label class="form-label">Tahun Ajaran Baru</label>
                <input type="text" class="form-control" name="tahun_ajaran" placeholder="contoh: 2026/2027" required>
                <small class="text-secondary">Format: YYYY/YYYY (contoh: 2025/2026)</small>
            </div>
            <div class="col-md-3">
                <label class="form-label">Semester Pertama</label>
                <select name="semester_pilih" class="form-select" required>
                    <option value="GANJIL">GANJIL</option>
                    <option value="GENAP">GENAP</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Tambah Tahun Ajaran</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-0">Atur Tahun Ajaran & Semester Aktif</h3>
    </div>
    <div class="card-body">
        <form method="post" class="row g-3">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="set_aktif">
            <div class="col-md-5">
                <label class="form-label">Pilih Tahun Ajaran</label>
                <select name="tahun_ajaran" class="form-select" id="selectTahunAjaran" required>
                    <option value="">-- Pilih Tahun Ajaran --</option>
                    <?php foreach ($tahunAjaranList as $ta): ?>
                        <option value="<?= e($ta['tahun_ajaran']) ?>" <?= $setting['tahun_ajaran'] === $ta['tahun_ajaran'] ? 'selected' : '' ?>>
                            <?= e($ta['tahun_ajaran']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Pilih Semester</label>
                <select name="semester_aktif" class="form-select" id="selectSemester" required>
                    <option value="">-- Pilih Semester --</option>
                    <option value="GANJIL" <?= $setting['semester_aktif'] === 'GANJIL' ? 'selected' : '' ?>>GANJIL</option>
                    <option value="GENAP" <?= $setting['semester_aktif'] === 'GENAP' ? 'selected' : '' ?>>GENAP</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100">Aktifkan</button>
            </div>
        </form>
        <div class="alert alert-info border mt-3">
            <strong>Tahun Ajaran Aktif:</strong> <?= e($setting['tahun_ajaran']) ?> - Semester <strong><?= e($setting['semester_aktif']) ?></strong>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-0">Daftar Tahun Ajaran & Semester</h3>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tahun Ajaran</th>
                        <th>Semester</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($allSettings as $s): ?>
                    <tr>
                        <td><?= e($s['tahun_ajaran']) ?></td>
                        <td><?= e($s['semester_aktif']) ?></td>
                        <td>
                            <?php if ($s['is_aktif'] == 1): ?>
                                <span class="badge text-bg-success">AKTIF</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Tidak Aktif</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-0">Finalisasi Semester</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-warning border mb-3">
            Proses ini akan mengunci nilai rapor semester target dan menaikkan current semester siswa aktif. Setelah semester 5, siswa akan menjadi semester Akhir. Siswa status Tidak Melanjutkan tidak diproses.
        </div>
        <form method="post" data-confirm="Yakin finalisasi semester aktif? Nilai akan dikunci dan semester siswa aktif dinaikkan." data-confirm-title="Konfirmasi Finalisasi">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="finalisasi">
            <button type="submit" class="btn btn-danger">Finalisasi Sekarang</button>
        </form>
    </div>
</div>
<?php require dirname(__DIR__) . '/partials/footer.php';
