<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('users');

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $stmt = db()->prepare('INSERT INTO users (username, password, nama_lengkap, role) VALUES (:username,:password,:nama,:role)');
        $stmt->execute([
            'username' => trim($_POST['username'] ?? ''),
            'password' => password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT),
            'nama' => trim($_POST['nama_lengkap'] ?? ''),
            'role' => $_POST['role'] ?? 'kurikulum',
        ]);
        set_flash('success', 'User berhasil ditambahkan.');
        redirect('index.php?page=users');
    }

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM users WHERE id = :id AND id != :self');
        $stmt->execute([
            'id' => (int) ($_POST['id'] ?? 0),
            'self' => (int) ($_SESSION['user']['id'] ?? 0),
        ]);
        set_flash('success', 'User berhasil dihapus.');
        redirect('index.php?page=users');
    }
}

$users = db()->query('SELECT id, username, nama_lengkap, role FROM users ORDER BY id DESC')->fetchAll();

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
        <h3 class="mb-0">Data User</h3>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
            <i class="bi bi-plus-circle me-1"></i>Tambah User
        </button>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Username</th><th>Nama</th><th>Role</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= e($u['username']) ?></td>
                        <td><?= e($u['nama_lengkap']) ?></td>
                        <td><span class="badge text-bg-light border"><?= e($u['role']) ?></span></td>
                        <td class="text-end">
                            <?php if ((int)$u['id'] !== (int)$_SESSION['user']['id']): ?>
                                <form method="post" class="d-inline" data-confirm="Hapus user ini?" data-confirm-title="Konfirmasi Hapus">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e((string)$u['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Tambah User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="admin">Super Admin</option>
                                <option value="kurikulum">Admin Kurikulum</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
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
