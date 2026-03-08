<?php
/**
 * Intermediate loader page for transcript generation.
 * Shows a waiting screen in the new tab, then reposts to cetak_transkrip.php.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!is_logged_in()) {
    http_response_code(403);
    exit('Akses ditolak. Silakan login terlebih dahulu.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    http_response_code(403);
    exit('CSRF validation failed');
}

$forwardFields = [
    '_csrf',
    'action',
    'nisn',
    'angkatan',
    'titimangsa',
    'nama_kepsek',
    'nip_kepsek',
    'nomor_urut',
];

$forwardData = [];
foreach ($forwardFields as $field) {
    if (isset($_POST[$field])) {
        $forwardData[$field] = (string) $_POST[$field];
    }
}

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menyiapkan Transkrip...</title>
    <style>
        :root {
            --bg: #f7f9fc;
            --card: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --accent: #0ea5e9;
            --ring: #bae6fd;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at 20% 20%, #e0f2fe 0%, var(--bg) 45%, #f1f5f9 100%);
            color: var(--text);
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .card {
            width: min(560px, 100%);
            background: var(--card);
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 32px 28px;
            box-shadow: 0 10px 30px rgba(2, 132, 199, 0.12);
            text-align: center;
        }
        .spinner {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            border: 6px solid var(--ring);
            border-top-color: var(--accent);
            margin: 0 auto 18px;
            animation: spin 1s linear infinite;
        }
        h1 {
            margin: 0 0 10px;
            font-size: 30px;
            line-height: 1.2;
        }
        p {
            margin: 0;
            font-size: 18px;
            color: var(--muted);
            line-height: 1.5;
        }
        .hint {
            margin-top: 16px;
            font-size: 14px;
            color: #64748b;
        }
        .manual {
            margin-top: 20px;
        }
        .manual button {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #0f172a;
            border-radius: 10px;
            padding: 10px 14px;
            cursor: pointer;
            font-size: 14px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <main class="card" role="status" aria-live="polite">
        <div class="spinner" aria-hidden="true"></div>
        <h1>Menyiapkan Transkrip</h1>
        <p>Sistem sedang membuat dokumen PDF. Mohon tunggu sebentar.</p>
        <div class="hint">Halaman ini akan otomatis beralih ke preview PDF.</div>

        <form id="forwardForm" method="post" action="cetak_transkrip.php" class="manual">
            <?php foreach ($forwardData as $key => $value): ?>
                <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
            <?php endforeach; ?>
            <noscript>
                <button type="submit">Lanjutkan Ke Preview PDF</button>
            </noscript>
        </form>
    </main>

    <script>
        window.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('forwardForm');
            if (form) {
                setTimeout(function () {
                    form.submit();
                }, 50);
            }
        });
    </script>
</body>
</html>
