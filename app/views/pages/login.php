<?php
/**
 * ========================================================
 * TRACER MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Manajemen Data Nilai Siswa
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Login Page
 * Deskripsi: Halaman autentikasi user untuk akses aplikasi TRACER
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
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="icon" type="image/png" href="assets/logo-tracer-mtsn11majalengka.png">
    <link rel="apple-touch-icon" href="assets/logo-tracer-mtsn11majalengka.png">
    <style>
        .login-brand {
            background: linear-gradient(135deg, #064e3b 0%, #10b981 100%);
            color: #fff;
            border-radius: 0.95rem 0.95rem 0 0;
            padding: 1.15rem 1.2rem 1rem;
            text-align: center;
        }

        .login-brand-logo {
            margin-bottom: 0;
        }

        .login-brand-subtitle {
            margin: 0.45rem 0 0;
            font-size: 0.88rem;
            font-weight: 600;
            opacity: 0.95;
            letter-spacing: 0.03em;
        }

        .login-brand-tagline {
            margin: 0.1rem 0 0;
            font-size: 0.79rem;
            font-style: italic;
            opacity: 0.9;
        }

        .login-brand-logo img {
            height: 160px;
            width: auto;
            object-fit: contain;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.15));
            animation: slideDown 0.6s ease-out;
        }

        .login-panel .form-label {
            margin-bottom: 0.35rem;
            font-size: 0.9rem;
        }

        .login-panel .input-group-text,
        .login-panel .form-control {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 576px) {
            .login-brand {
                padding: 1rem 0.9rem 0.85rem;
            }

            .login-brand-logo img {
                height: 120px;
            }

            .login-brand-subtitle {
                font-size: 0.8rem;
            }

            .login-brand-tagline {
                font-size: 0.74rem;
            }
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
                <div class="login-brand-logo">
                    <img src="assets/logo-tracer-mtsn11majalengka.png" alt="TRACER Logo">
                </div>
                <p class="login-brand-subtitle">Transkrip &amp; Academic Ledger</p>
                <p class="login-brand-tagline">Tracing Progress, Graduating Success.</p>
            </div>

            <div class="login-panel p-3 p-md-4">
                <h2 class="h5 mb-3">Masuk ke Aplikasi</h2>

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

                    <div class="mb-3">
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
            <div>Developed by <a href="https://www.instagram.com/atadityas_13/" class="link-clean" target="_blank" rel="noopener noreferrer">A.T. Aditya</a></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
