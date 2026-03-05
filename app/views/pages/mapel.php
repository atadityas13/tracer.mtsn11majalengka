<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('mapel');

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $stmt = db()->prepare('INSERT INTO mapel (nama_mapel, kelompok, is_sub_pai) VALUES (:nama,:kelompok,:is_sub_pai)');
        $stmt->execute([
            'nama' => trim($_POST['nama_mapel'] ?? ''),
            'kelompok' => $_POST['kelompok'] ?? 'A',
            'is_sub_pai' => isset($_POST['is_sub_pai']) ? 1 : 0,
        ]);
        set_flash('success', 'Mapel berhasil ditambahkan.');
        redirect('index.php?page=mapel');
    }

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM mapel WHERE id=:id');
        $stmt->execute(['id' => (int) ($_POST['id'] ?? 0)]);
        set_flash('success', 'Mapel berhasil dihapus.');
        redirect('index.php?page=mapel');
    }
}

$mapel = db()->query('SELECT * FROM mapel ORDER BY kelompok, nama_mapel')->fetchAll();

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
        <h3 class="mb-0">Data Mapel</h3>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambahMapel">
            <i class="bi bi-plus-circle me-1"></i>Tambah Mapel
        </button>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Mapel</th><th>Kelompok</th><th>Sub PAI</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($mapel as $m): ?>
                    <tr>
                        <td><?= e($m['nama_mapel']) ?></td>
                        <td><span class="badge text-bg-light border"><?= e($m['kelompok']) ?></span></td>
                        <td><?= (int) $m['is_sub_pai'] === 1 ? 'Ya' : 'Tidak' ?></td>
                        <td class="text-end">
                            <form method="post" class="d-inline" data-confirm="Hapus mapel ini?" data-confirm-title="Konfirmasi Hapus">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e((string)$m['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahMapel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Mapel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Mapel</label>
                            <input type="text" name="nama_mapel" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Kelompok</label>
                            <select name="kelompok" class="form-select">
                                <option value="A">A</option>
                                <option value="B">B</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label d-block">Sub PAI</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" name="is_sub_pai" value="1" id="is_sub_pai_modal">
                                <label class="form-check-label" for="is_sub_pai_modal">Ya</label>
                            </div>
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
<?php require dirname(__DIR__) . '/partials/footer.php';
