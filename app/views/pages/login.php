<?php
/**
 * ========================================================
 * E-LEGER MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Manajemen Data Nilai Siswa
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Login Page
 * Deskripsi: Halaman autentikasi user untuk akses aplikasi e-leger
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
 * ========================================================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('login');

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'nama_lengkap' => $user['nama_lengkap'],
            'role' => $user['role'],
        ];
        redirect('index.php?page=dashboard');
    }

    set_flash('error', 'Username atau password salah.');
    redirect('index.php?page=login');
}

$flash = get_flash();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(app_config('name')) ?> - Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-brand {
            background: linear-gradient(135deg, #064e3b 0%, #10b981 100%);
            color: #fff;
            border-radius: 0.95rem 0.95rem 0 0;
            padding: 1.5rem;
        }

        .login-brand .subtitle {
            opacity: 0.92;
            font-size: 0.9rem;
        }

        .login-panel {
            border-radius: 0 0 0.95rem 0.95rem;
            background: #fff;
        }

        .btn-theme {
            background: linear-gradient(135deg, #064e3b 0%, #10b981 100%);
            border: none;
            color: #fff;
        }

        .btn-theme:hover {
            color: #fff;
            filter: brightness(0.97);
        }

        .login-note {
            color: #64748b;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<div class="login-shell">
    <div class="login-box">
        <div class="card border-0 shadow-lg">
            <div class="login-brand">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-mortarboard-fill fs-4"></i>
                    <h1 class="h4 mb-0">TRACER MTsN 11 Majalengka</h1>
                </div>
                <div class="subtitle">Transkrip & Academic Ledger<br><small class="text-muted" style="font-size: 0.85rem;">Tracing Progress, Graduating Success.</small></div>
            </div>

            <div class="login-panel p-4 p-md-5">
                <h2 class="h5 mb-1">Masuk ke Aplikasi</h2>

                <?php if ($flash['error'] ?? null): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <?= e($flash['error']) ?>
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <?= csrf_input() ?>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-theme w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                    </button>
                </form>
            </div>
        </div>

        <div class="text-center mt-3 text-secondary small">
            <div>© <?= e((string) date('Y')) ?> MTsN 11 Majalengka</div>
            <div>Developed by A.T. Aditya</div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
