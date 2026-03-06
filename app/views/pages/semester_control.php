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
        $semesterId = (int)($_POST['semester_id'] ?? 0);
        
        if ($semesterId <= 0) {
            set_flash('error', 'Pilih tahun ajaran dan semester.');
            redirect('index.php?page=semester-control');
        }
        
        db()->beginTransaction();
        try {
            // Matikan semua yang aktif
            db()->exec('UPDATE pengaturan_akademik SET is_aktif=0');
            
            // Aktifkan yang dipilih
            $stmt = db()->prepare('UPDATE pengaturan_akademik SET is_aktif=1 WHERE id=?');
            $stmt->execute([$semesterId]);
            
            db()->commit();
            set_flash('success', 'Tahun ajaran dan semester berhasil diaktifkan.');
        } catch (Throwable $e) {
            db()->rollBack();
            set_flash('error', 'Gagal set aktif: ' . $e->getMessage());
        }
        
        redirect('index.php?page=semester-control');
    }

}

$setting = setting_akademik();
$semesterList = db()->query('SELECT id, tahun_ajaran, semester_aktif FROM pengaturan_akademik ORDER BY id DESC')->fetchAll();

require dirname(__DIR__) . '/partials/header.php';
?>
<!-- Info Tahun Ajaran & Semester Aktif -->
<div class="alert alert-info border mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <strong>📅 Tahun Ajaran & Semester Aktif:</strong><br>
            <span style="font-size: 1.1em; margin-top: 0.5rem; display: inline-block;">
                <strong><?= e($setting['tahun_ajaran']) ?></strong> - Semester <strong style="color: #059669;"><?= e($setting['semester_aktif']) ?></strong>
            </span>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-0">Tambah Tahun Ajaran Baru</h3>
    </div>
    <div class="card-body">
        <form method="post" class="row g-3">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="tambah_tahun_ajaran">
            <div class="col-md-6">
                <label class="form-label">Tahun Ajaran</label>
                <input type="text" class="form-control" name="tahun_ajaran" placeholder="contoh: 2026/2027" required>
                <small class="text-secondary">Format: YYYY/YYYY (contoh: 2025/2026)</small>
            </div>
            <div class="col-md-3">
                <label class="form-label">Semester</label>
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
            <div class="col-md-8">
                <label class="form-label">Pilih Tahun Ajaran & Semester</label>
                <select name="semester_id" class="form-select" id="selectSemesterAktif" required>
                    <option value="">-- Pilih Tahun Ajaran & Semester --</option>
                    <?php foreach ($semesterList as $sem): ?>
                        <option value="<?= e($sem['id']) ?>" 
                            <?php
                                if ($setting['tahun_ajaran'] === $sem['tahun_ajaran'] && 
                                    $setting['semester_aktif'] === $sem['semester_aktif']) {
                                    echo 'selected';
                                }
                            ?>>
                            <?= e($sem['tahun_ajaran']) ?> - <?= e($sem['semester_aktif']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100">Aktifkan</button>
            </div>
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/partials/footer.php';
