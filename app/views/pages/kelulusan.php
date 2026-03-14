<?php
/**
 * ========================================================
 * TRACER MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Manajemen Data Nilai Siswa
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Kelulusan & Migrasi Alumni Page
 * Deskripsi: Halaman untuk migrasi siswa aktif ke alumni dengan perhitungan nilai ijazah
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
 * - Migrasi siswa dari aktif ke alumni
 * - Eligibility check: current_semester = 6 (Akhir)
 * - Perhitungan nilai ijazah: 60% rata-rata (sem 1-5) + 40% UAM
 * - Simpan data ijazah per mapel dalam JSON format
 * - Preservasi nama siswa di tabel alumni
 * - Auto ALTER TABLE untuk schema compatibility
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

$eligibleSql = "SELECT s.nisn, s.nama
FROM siswa s
WHERE s.status_siswa='Aktif' AND s.current_semester = 6
AND (
    SELECT COUNT(DISTINCT nr.semester) FROM nilai_rapor nr WHERE nr.nisn=s.nisn AND nr.semester BETWEEN 1 AND 5
) = 5
AND EXISTS (SELECT 1 FROM nilai_uam nu WHERE nu.nisn=s.nisn)";
$eligible = db()->query($eligibleSql)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'migrate') {
    enforce_csrf('kelulusan');

    $angkatan = (int) ($_POST['angkatan_lulus'] ?? date('Y'));
    $tanggalKelulusan = $_POST['tanggal_kelulusan'] ?? date('Y-m-d');

    db()->beginTransaction();
    try {
        foreach ($eligible as $s) {
            $stmtMapel = db()->prepare('SELECT id, nama_mapel FROM mapel ORDER BY id');
            $stmtMapel->execute();
            $mapel = $stmtMapel->fetchAll();

            $detail = [];
            foreach ($mapel as $m) {
                $stR = db()->prepare('SELECT AVG(nilai_angka) rata FROM nilai_rapor WHERE nisn=:nisn AND mapel_id=:mapel AND semester BETWEEN 1 AND 5');
                $stR->execute(['nisn' => $s['nisn'], 'mapel' => $m['id']]);
                $rata = (float) ($stR->fetch()['rata'] ?? 0);

                $stU = db()->prepare('SELECT nilai_angka FROM nilai_uam WHERE nisn=:nisn AND mapel_id=:mapel LIMIT 1');
                $stU->execute(['nisn' => $s['nisn'], 'mapel' => $m['id']]);
                $uam = (float) ($stU->fetch()['nilai_angka'] ?? 0);

                $ijazah = hitung_nilai_ijazah($rata, $uam);
                $detail[] = [
                    'mapel_id' => (int)$m['id'],
                    'mapel' => $m['nama_mapel'],
                    'rata_rapor' => (int)round($rata),
                    'nilai_uam' => (int)round($uam),
                    'nilai_ijazah' => (int)round($ijazah),
                    'terbilang' => terbilang_nilai($ijazah),
                ];
            }

            // Generate nomor surat dan verification token
            $bulanCetak = date('m');
            $tahunCetak = date('Y');
            $nomorSurat = '       /Mts.10.89/PP.00.5/' . $bulanCetak . '/' . $tahunCetak;
            $verificationToken = bin2hex(random_bytes(32));

            $stmtInsert = db()->prepare('INSERT INTO alumni (nisn, nama, angkatan_lulus, tanggal_kelulusan, nomor_surat, data_ijazah_json, verification_token) 
                VALUES (:nisn,:nama,:angkatan,:tgl_lulus,:nomor_surat,:json,:token)
                ON DUPLICATE KEY UPDATE nama=VALUES(nama), angkatan_lulus=VALUES(angkatan_lulus), tanggal_kelulusan=VALUES(tanggal_kelulusan), 
                    nomor_surat=VALUES(nomor_surat), data_ijazah_json=VALUES(data_ijazah_json), verification_token=VALUES(verification_token)');
            $stmtInsert->execute([
                'nisn' => $s['nisn'],
                'nama' => $s['nama'],
                'angkatan' => $angkatan,
                'tgl_lulus' => $tanggalKelulusan,
                'nomor_surat' => $nomorSurat,
                'json' => json_encode($detail, JSON_UNESCAPED_UNICODE),
                'token' => $verificationToken,
            ]);

            $stmtUpdate = db()->prepare('UPDATE siswa SET status_siswa=\'Lulus\' WHERE nisn=:nisn');
            $stmtUpdate->execute(['nisn' => $s['nisn']]);
        }

        db()->commit();
        set_flash('success', 'Migrasi kelulusan selesai. Data siswa lulus dipindah ke alumni.');
    } catch (Throwable $e) {
        db()->rollBack();
        set_flash('error', 'Migrasi gagal: ' . $e->getMessage());
    }

    redirect('index.php?page=kelulusan');
}

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-0">Migrasi Kelulusan ke Alumni</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info border mb-3">Syarat: siswa aktif semester Akhir, nilai rapor lengkap semester 1-5, dan memiliki nilai UAM.</div>
        <form method="post" class="row g-3 align-items-end" data-confirm="Proses migrasi kelulusan sekarang? Data siswa eligible akan dipindah ke alumni." data-confirm-title="Konfirmasi Migrasi">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="migrate">
            <div class="col-md-3">
                <label class="form-label">Tahun Angkatan Lulus</label>
                <input type="number" class="form-control" name="angkatan_lulus" value="<?= e(date('Y')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal Kelulusan</label>
                <input type="date" class="form-control" name="tanggal_kelulusan" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-success">Migrasi Sekarang</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
        <h3 class="mb-0">Daftar Siswa Eligible Kelulusan</h3>
        <span class="badge text-bg-light border"><?= e((string) count($eligible)) ?> siswa</span>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table>
                <thead><tr><th>NISN</th><th>Nama</th></tr></thead>
                <tbody>
                <?php foreach ($eligible as $s): ?>
                    <tr><td><?= e($s['nisn']) ?></td><td><?= e($s['nama']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require dirname(__DIR__) . '/partials/footer.php';
