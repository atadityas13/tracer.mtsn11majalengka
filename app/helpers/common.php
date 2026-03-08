<?php
/**
 * ========================================================
 * TRACER MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Manajemen Data Nilai Siswa
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Common Helper Functions
 * Deskripsi: Utility functions yang digunakan di seluruh aplikasi
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

        // Bagian bulat
        $terbilangBulat = trim(terbilang_bulat((int) $int));
        
        // Jika desimal adalah 00, return hanya bagian bulat tanpa koma
        if ($dec === '00') {
            return $terbilangBulat;
        }
        
        // Bagian desimal: setiap digit disebut satu per satu
        // Contoh: 85,50 → "delapan puluh lima koma lima nol"
        // Bukan: "delapan puluh lima koma lima puluh"
        $digitAngka = ['nol', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan'];
        $terbilangDesimal = '';
        for ($i = 0; $i < strlen($dec); $i++) {
            $digit = (int) $dec[$i];
            $terbilangDesimal .= $digitAngka[$digit];
            if ($i < strlen($dec) - 1) {
                $terbilangDesimal .= ' ';
            }
        }

        return $terbilangBulat . ' koma ' . $terbilangDesimal;
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

if (!function_exists('hitung_tahun_ajaran_dari_angkatan')) {
    /**
     * Hitung tahun ajaran berdasarkan tahun masuk (angkatan) dan semester saat ini
     * 
     * Logic:
     * - Semester 1,2 = tahun ke-0 (tahun masuk)
     * - Semester 3,4 = tahun ke-1
     * - Semester 5,6 = tahun ke-2
     * 
     * @param string $tahunMasuk Format: "2023/2024"
     * @param int $currentSemester 1-6
     * @return string Format: "2024/2025"
     */
    function hitung_tahun_ajaran_dari_angkatan(string $tahunMasuk, int $currentSemester): string
    {
        if (empty($tahunMasuk) || strpos($tahunMasuk, '/') === false) {
            return '';
        }

        // Parse tahun awal dari tahun_masuk (misal: "2023/2024" -> 2023)
        [$tahunAwal, $tahunAkhir] = explode('/', $tahunMasuk);
        $tahunAwal = (int) $tahunAwal;

        // Hitung offset tahun berdasarkan semester
        $offsetTahun = (int) floor(($currentSemester - 1) / 2);

        // Hitung tahun ajaran target
        $tahunAjaranAwal = $tahunAwal + $offsetTahun;
        $tahunAjaranAkhir = $tahunAjaranAwal + 1;

        return $tahunAjaranAwal . '/' . $tahunAjaranAkhir;
    }
}

if (!function_exists('get_upload_token_setting')) {
    /**
     * Get upload token verification settings for active semester
     * @return array ['require_token' => bool, 'token_mode' => 'manual|daily|disabled']
     */
    function get_upload_token_setting(): array
    {
        try {
            $stmt = db()->query("SELECT require_upload_token, token_mode FROM pengaturan_akademik WHERE is_aktif=1 LIMIT 1");
            $row = $stmt->fetch();
            
            if (!$row) {
                return ['require_token' => true, 'token_mode' => 'daily'];
            }

            return [
                'require_token' => $row['require_upload_token'] == 1,
                'token_mode' => $row['token_mode'] ?? 'daily'
            ];
        } catch (Exception $e) {
            return ['require_token' => true, 'token_mode' => 'daily'];
        }
    }
}

if (!function_exists('get_current_upload_token')) {
    /**
     * Get valid current upload token if exists
     * Token is valid if: not expired, not used, for current academic period
     * @return string|null The token string or null if no valid token
     */
    function get_current_upload_token(): ?string
    {
        try {
            $setting = setting_akademik();
            $stmt = db()->prepare("
                SELECT token FROM upload_token 
                WHERE created_tahun_ajaran = :ta 
                AND created_semester_aktif = :sem
                AND (expires_at IS NULL OR expires_at > NOW())
                AND is_used = 0
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([
                'ta' => $setting['tahun_ajaran'],
                'sem' => $setting['semester_aktif']
            ]);
            
            $row = $stmt->fetch();
            return $row ? $row['token'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('generate_upload_token')) {
    /**
     * Generate new upload token (manual or daily auto)
     * @param string $token_type 'manual' or 'daily'
     * @param string|null $created_by Username of admin who created it
     * @param int $expiry_hours Hours until token expires (0 = no expiry)
     * @return string|false New token string or false on error
     */
    function generate_upload_token(string $token_type = 'daily', ?string $created_by = null, int $expiry_hours = 24)
    {
        try {
            $setting = setting_akademik();
            $currentUser = current_user();
            $creator = $created_by ?? ($currentUser['username'] ?? 'system');
            
            // Generate random token (8 chars alphanumeric)
            $token = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            
            $expiresAt = $expiry_hours > 0 ? date('Y-m-d H:i:s', time() + ($expiry_hours * 3600)) : null;
            
            $stmt = db()->prepare("
                INSERT INTO upload_token 
                (token, token_type, created_by, expires_at, created_tahun_ajaran, created_semester_aktif, ip_address, user_agent)
                VALUES (:token, :type, :creator, :expires, :ta, :sem, :ip, :ua)
            ");
            
            $stmt->execute([
                'token' => $token,
                'type' => $token_type,
                'creator' => $creator,
                'expires' => $expiresAt,
                'ta' => $setting['tahun_ajaran'],
                'sem' => $setting['semester_aktif'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255)
            ]);
            
            return $token;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('validate_upload_token')) {
    /**
     * Validate upload token from user input
     * @param string $token_input Token string to validate
     * @return bool True if valid and not expired/used
     */
    function validate_upload_token(string $token_input): bool
    {
        try {
            $setting = setting_akademik();
            $tokenInput = strtoupper(trim($token_input));
            
            $stmt = db()->prepare("
                SELECT id, is_used, expires_at FROM upload_token
                WHERE token = :token
                AND created_tahun_ajaran = :ta 
                AND created_semester_aktif = :sem
            ");
            
            $stmt->execute([
                'token' => $tokenInput,
                'ta' => $setting['tahun_ajaran'],
                'sem' => $setting['semester_aktif']
            ]);
            
            $row = $stmt->fetch();
            
            if (!$row) {
                return false;
            }
            
            // Check if already used
            if ($row['is_used'] == 1) {
                return false;
            }
            
            // Check if expired
            if ($row['expires_at'] && strtotime($row['expires_at']) < time()) {
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('mark_upload_token_used')) {
    /**
     * Mark upload token as used after successful upload
     * @param string $token_input Token string
     * @param string|null $used_by Username of user who used it
     * @return bool True if marked successfully
     */
    function mark_upload_token_used(string $token_input, ?string $used_by = null): bool
    {
        try {
            $setting = setting_akademik();
            $tokenInput = strtoupper(trim($token_input));
            $currentUser = current_user();
            $userWhoUsed = $used_by ?? ($currentUser['username'] ?? 'unknown');
            
            $stmt = db()->prepare("
                UPDATE upload_token 
                SET is_used = 1, used_by = :used_by, used_at = NOW()
                WHERE token = :token
                AND created_tahun_ajaran = :ta 
                AND created_semester_aktif = :sem
            ");
            
            return $stmt->execute([
                'token' => $tokenInput,
                'used_by' => $userWhoUsed,
                'ta' => $setting['tahun_ajaran'],
                'sem' => $setting['semester_aktif']
            ]);
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('hitung_tahun_masuk_dari_semester')) {
    /**
     * Hitung tahun masuk (backfill) dari tahun ajaran saat ini dan semester
     * Digunakan untuk auto-fill tahun_masuk pada data lama
     * 
     * @param string $tahunAjaran Format: "2024/2025"
     * @param int $currentSemester 1-6
     * @return string Format: "2023/2024"
     */
    function hitung_tahun_masuk_dari_semester(string $tahunAjaran, int $currentSemester): string
    {
        if (empty($tahunAjaran) || strpos($tahunAjaran, '/') === false) {
            return '';
        }

        [$tahunAwal, $tahunAkhir] = explode('/', $tahunAjaran);
        $tahunAwal = (int) $tahunAwal;

        // Hitung mundur berdasarkan semester
        $offsetTahun = (int) floor(($currentSemester - 1) / 2);
        $tahunMasukAwal = $tahunAwal - $offsetTahun;
        $tahunMasukAkhir = $tahunMasukAwal + 1;

        return $tahunMasukAwal . '/' . $tahunMasukAkhir;
    }
}
