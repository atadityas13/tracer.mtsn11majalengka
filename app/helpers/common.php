<?php
/**
 * ========================================================
 * E-LEGER MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Manajemen Data Nilai Siswa
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Common Helper Functions
 * Deskripsi: Utility functions yang digunakan di seluruh aplikasi
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
 * Functions:
 * - normalize_current_semester() - Normalisasi nilai semester (string ke int)
 * - current_semester_label() - Display label semester (Akhir untuk 6)
 * - hitung_nilai_ijazah() - Kalkulasi nilai ijazah (60% rapor + 40% UAM)
 * - terbilang_nilai() - Konversi angka ke teks (1-10)
 * - terbilang_bulat() - Konversi angka bulat ke teks
 * - semester_upload_target() - Return array semester untuk upload (GANJIL/GENAP)
 * - setting_akademik() - Fetch pengaturan akademik
 * - current_user() - Get current user session
 * - get_flash() - Get flash message dari session
 * - set_flash() - Set flash message
 * - redirect() - Server-side redirect
 * - require_login() - Enforce login authentication
 * - require_role() - Enforce role-based access control
 * - app_config() - Get app configuration value
 * - db() - Get database connection (PDO)
 * - e() - HTML escape function
 * - csrf_input() - Generate CSRF token input field
 * - enforce_csrf() - Validate CSRF token
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

declare(strict_types=1);

if (!function_exists('app_config')) {
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
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('set_flash')) {
    function set_flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('get_flash')) {
    function get_flash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['_csrf'];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf_request')) {
    function verify_csrf_request(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return true;
        }

        $requestToken = (string) ($_POST['_csrf'] ?? '');
        $sessionToken = (string) ($_SESSION['_csrf'] ?? '');

        return $requestToken !== '' && $sessionToken !== '' && hash_equals($sessionToken, $requestToken);
    }
}

if (!function_exists('enforce_csrf')) {
    function enforce_csrf(string $page): void
    {
        if (!verify_csrf_request()) {
            set_flash('error', 'Token CSRF tidak valid. Silakan ulangi proses.');
            redirect('index.php?page=' . $page);
        }
    }
}

if (!function_exists('setting_akademik')) {
    function setting_akademik(): array
    {
        $stmt = db()->query("SELECT tahun_ajaran, semester_aktif FROM pengaturan_akademik WHERE is_aktif=1 LIMIT 1");
        $row = $stmt->fetch();

        return $row ?: ['tahun_ajaran' => date('Y') . '/' . (date('Y') + 1), 'semester_aktif' => 'GANJIL'];
    }
}

if (!function_exists('semester_upload_target')) {
    function semester_upload_target(string $semesterAktif): array
    {
        if (strtoupper($semesterAktif) === 'GANJIL') {
            return [1, 3, 5];
        }

        return [2, 4];
    }
}

if (!function_exists('normalize_current_semester')) {
    function normalize_current_semester($value): int
    {
        $text = strtoupper(trim((string) $value));
        if ($text === 'AKHIR' || $text === 'UAM' || $text === 'UM') {
            return 6;
        }

        $semester = (int) $value;
        if ($semester < 1) {
            return 1;
        }
        if ($semester > 6) {
            return 6;
        }

        return $semester;
    }
}

if (!function_exists('current_semester_label')) {
    function current_semester_label($value): string
    {
        $semester = normalize_current_semester($value);
        return $semester === 6 ? 'Akhir' : (string) $semester;
    }
}

if (!function_exists('hitung_nilai_ijazah')) {
    function hitung_nilai_ijazah(float $rataRapor, float $uam): float
    {
        return round(($rataRapor * 0.6) + ($uam * 0.4), 2);
    }
}

if (!function_exists('terbilang_nilai')) {
    function terbilang_nilai(float $angka): string
    {
        $formatted = number_format($angka, 2, ',', '');
        [$int, $dec] = explode(',', $formatted);

        return trim(terbilang_bulat((int) $int)) . ' koma ' . trim(terbilang_bulat((int) $dec));
    }
}

if (!function_exists('terbilang_bulat')) {
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
}
