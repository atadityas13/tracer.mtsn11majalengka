<?php
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('profile');

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $username = trim($_POST['username'] ?? '');
        $nama = trim($_POST['nama_lengkap'] ?? '');

        if ($username === '' || $nama === '') {
            set_flash('error', 'Username dan nama lengkap wajib diisi.');
            redirect('index.php?page=profile');
        }

        $cek = db()->prepare('SELECT id FROM users WHERE username=:username AND id!=:id LIMIT 1');
        $cek->execute([
            'username' => $username,
            'id' => (int) $user['id'],
        ]);

        if ($cek->fetch()) {
            set_flash('error', 'Username sudah dipakai akun lain.');
            redirect('index.php?page=profile');
        }

        $stmt = db()->prepare('UPDATE users SET username=:username, nama_lengkap=:nama WHERE id=:id');
        $stmt->execute([
            'username' => $username,
            'nama' => $nama,
            'id' => (int) $user['id'],
        ]);

        $_SESSION['user']['username'] = $username;
        $_SESSION['user']['nama_lengkap'] = $nama;

        set_flash('success', 'Profil berhasil diperbarui.');
        redirect('index.php?page=profile');
    }

    if ($action === 'update_password') {
        $passwordBaru = $_POST['password_baru'] ?? '';
        $konfirmasi = $_POST['konfirmasi_password'] ?? '';

        if ($passwordBaru === '' || $konfirmasi === '') {
            set_flash('error', 'Password baru dan konfirmasi wajib diisi.');
            redirect('index.php?page=profile');
        }

        if (strlen($passwordBaru) < 6) {
            set_flash('error', 'Password minimal 6 karakter.');
            redirect('index.php?page=profile');
        }

        if ($passwordBaru !== $konfirmasi) {
            set_flash('error', 'Konfirmasi password tidak sama.');
            redirect('index.php?page=profile');
        }

        $stmt = db()->prepare('UPDATE users SET password=:password WHERE id=:id');
        $stmt->execute([
            'password' => password_hash($passwordBaru, PASSWORD_DEFAULT),
            'id' => (int) $user['id'],
        ]);

        set_flash('success', 'Password berhasil diperbarui.');
        redirect('index.php?page=profile');
    }
}

$stmt = db()->prepare('SELECT username, nama_lengkap, role FROM users WHERE id=:id LIMIT 1');
$stmt->execute(['id' => (int) $user['id']]);
$profile = $stmt->fetch();

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-3">
                <h3 class="mb-0">Ubah Profil</h3>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="col-12">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" value="<?= e($profile['nama_lengkap'] ?? '') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="<?= e($profile['username'] ?? '') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control" value="<?= e(strtoupper($profile['role'] ?? '')) ?>" disabled>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-success">Simpan Profil</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-3">
                <h3 class="mb-0">Ubah Password</h3>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="update_password">
                    <div class="col-12">
                        <label class="form-label">Password Baru</label>
                        <input type="password" name="password_baru" class="form-control" minlength="6" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Konfirmasi Password</label>
                        <input type="password" name="konfirmasi_password" class="form-control" minlength="6" required>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">Simpan Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require dirname(__DIR__) . '/partials/footer.php';
