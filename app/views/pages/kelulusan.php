<?php
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
                    'rata_rapor' => $rata,
                    'nilai_uam' => $uam,
                    'nilai_ijazah' => $ijazah,
                    'terbilang' => terbilang_nilai($ijazah),
                ];
            }

            $stmtInsert = db()->prepare('INSERT INTO alumni (nisn, angkatan_lulus, data_ijazah_json) VALUES (:nisn,:angkatan,:json)
                ON DUPLICATE KEY UPDATE angkatan_lulus=VALUES(angkatan_lulus), data_ijazah_json=VALUES(data_ijazah_json)');
            $stmtInsert->execute([
                'nisn' => $s['nisn'],
                'angkatan' => $angkatan,
                'json' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            ]);

            $stmtDelete = db()->prepare('DELETE FROM siswa WHERE nisn=:nisn');
            $stmtDelete->execute(['nisn' => $s['nisn']]);
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
            <div class="col-md-4">
                <label class="form-label">Tahun Angkatan Lulus</label>
                <input type="number" class="form-control" name="angkatan_lulus" value="<?= e(date('Y')) ?>" required>
            </div>
            <div class="col-md-4">
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
