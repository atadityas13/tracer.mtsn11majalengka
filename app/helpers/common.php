<?php

declare(strict_types=1);

function app_config($key = null, $default = null)
{
    $config = $GLOBALS['app_config'] ?? [];
    if ($key === null) {
        return $config;
    }

    $segments = explode('.', $key);
    $value = $config;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['_csrf'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf_request(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return true;
    }

    $requestToken = (string) ($_POST['_csrf'] ?? '');
    $sessionToken = (string) ($_SESSION['_csrf'] ?? '');

    return $requestToken !== '' && $sessionToken !== '' && hash_equals($sessionToken, $requestToken);
}

function enforce_csrf(string $page): void
{
    if (!verify_csrf_request()) {
        set_flash('error', 'Token CSRF tidak valid. Silakan ulangi proses.');
        redirect('index.php?page=' . $page);
    }
}

function setting_akademik(): array
{
    $stmt = db()->query("SELECT tahun_ajaran, semester_aktif FROM pengaturan_akademik ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch();

    return $row ?: ['tahun_ajaran' => date('Y') . '/' . (date('Y') + 1), 'semester_aktif' => 'GANJIL'];
}

function semester_upload_target(string $semesterAktif): array
{
    if (strtoupper($semesterAktif) === 'GANJIL') {
        return [1, 3, 5];
    }

    return [2, 4];
}

function hitung_nilai_ijazah(float $rataRapor, float $uam): float
{
    return round(($rataRapor * 0.6) + ($uam * 0.4), 2);
}

function terbilang_nilai(float $angka): string
{
    $formatted = number_format($angka, 2, ',', '');
    [$int, $dec] = explode(',', $formatted);

    return trim(terbilang_bulat((int) $int)) . ' koma ' . trim(terbilang_bulat((int) $dec));
}

function terbilang_bulat(int $angka): string
{
    $angka = abs($angka);
    $huruf = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    if ($angka < 12) {
        return ' ' . $huruf[$angka];
    }
    if ($angka < 20) {
        return terbilang_bulat($angka - 10) . ' belas';
    }
    if ($angka < 100) {
        return terbilang_bulat(intdiv($angka, 10)) . ' puluh' . terbilang_bulat($angka % 10);
    }
    if ($angka < 200) {
        return ' seratus' . terbilang_bulat($angka - 100);
    }

    return (string) $angka;
}
