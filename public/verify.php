<?php
/**
 * Halaman Verifikasi Transkrip Ijazah
 * Diakses via QR Code pada dokumen transkrip
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

// Set security headers to prevent browser security warnings
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' https://cdn.jsdelivr.net; style-src \'self\' https://cdn.jsdelivr.net \'unsafe-inline\'; font-src \'self\' https://cdn.jsdelivr.net; img-src \'self\' data:; connect-src \'self\'');

$token = $_GET['token'] ?? '';

if ($token === '') {
    http_response_code(400);
    $logoWebPath = 'assets/logo-kemenag.png';
    $logoExists = is_file(__DIR__ . '/assets/logo-kemenag.png');
    ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Token Tidak Valid - MTsN 11 Majalengka</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="icon" type="image/png" href="assets/logo-tracer-mtsn11majalengka.png">
    <link rel="apple-touch-icon" href="assets/logo-tracer-mtsn11majalengka.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f6f8fb; }
        .header-card {
            background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
            color: #fff;
            border-radius: 12px;
        }
        .official-head {
            display: grid;
            grid-template-columns: 76px 1fr;
            gap: 0.8rem;
            align-items: center;
        }
        .official-logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 6px;
        }
        .official-kemenag {
            margin: 0;
            font-size: 0.9rem;
            letter-spacing: 0.08em;
            font-weight: 700;
            text-transform: uppercase;
            opacity: 0.95;
        }
        .official-school {
            margin: 0.1rem 0;
            font-size: 1.6rem;
            line-height: 1;
            font-weight: 800;
            letter-spacing: 0.04em;
        }
        .official-address {
            margin: 0;
            font-size: 0.78rem;
            opacity: 0.9;
            font-style: italic;
        }
        .verified-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: #ffffff;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: 999px;
            padding: 0.45rem 0.85rem;
            font-weight: 600;
        }
        .meta-note {
            border-left: 4px solid #dc2626;
            background: #fef2f2;
            color: #7f1d1d;
            padding: 0.8rem 1rem;
            border-radius: 8px;
        }
        @media (max-width: 768px) {
            .container.py-4 { padding-top: 1rem !important; padding-bottom: 1rem !important; }
            .header-card { padding: 1rem !important; }
            .official-head { grid-template-columns: 54px 1fr; gap: 0.55rem; }
            .official-logo { width: 52px; height: 52px; padding: 4px; }
            .official-kemenag { font-size: 0.65rem; letter-spacing: 0.05em; }
            .official-school { font-size: 1.05rem; letter-spacing: 0.02em; }
            .official-address { font-size: 0.66rem; }
            .verified-pill { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="header-card p-4 mb-4 shadow-sm">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="official-head">
                    <?php if ($logoExists): ?>
                        <img src="<?= htmlspecialchars($logoWebPath) ?>" alt="Logo Kementerian Agama" class="official-logo">
                    <?php else: ?>
                        <div class="official-logo d-flex align-items-center justify-content-center small">LOGO</div>
                    <?php endif; ?>
                    <div>
                        <p class="official-kemenag">Kementerian Agama Republik Indonesia</p>
                        <h1 class="official-school">MTsN 11 MAJALENGKA</h1>
                        <p class="official-address">Kp. Sindanghurip Desa Maniis Kec. Cingambul<br>Kab. Majalengka, 45467</p>
                    </div>
                </div>
                <span class="verified-pill"><i class="bi bi-exclamation-triangle-fill"></i> Dokumen Tidak Terverifikasi</span>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-x-circle" style="font-size: 3rem; color: #dc2626;"></i>
                        <h4 class="mt-3 mb-2">Token Verifikasi Tidak Valid</h4>
                        <p class="text-muted mb-3">Token tidak ditemukan atau sudah kadaluarsa.</p>
                        <div class="meta-note">
                            <strong><i class="bi bi-info-circle"></i> Instruksi:</strong><br>
                            Silakan scan ulang QR Code yang terdapat pada dokumen transkrip asli.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4 text-muted">
            <small><b>Sistem Verifikasi TRACER MTsN 11 Majalengka</b><br>
            <em>Tracing Progress, Graduating Success.</em><br>
            Hubungi: (0233) 3600020 | mtsn11majalengka@gmail.com</small>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

$stmt = db()->prepare('SELECT a.nisn, a.nama, a.angkatan_lulus, a.tanggal_kelulusan, a.nomor_surat, a.data_ijazah_json,
    s.tempat_lahir, s.tgl_lahir, s.nis
    FROM alumni a
    LEFT JOIN siswa s ON s.nisn = a.nisn
    WHERE a.verification_token = :token LIMIT 1');
$stmt->execute(['token' => $token]);
$alumni = $stmt->fetch();

if (!$alumni) {
    http_response_code(404);
    $logoWebPath = 'assets/logo-kemenag.png';
    $logoExists = is_file(__DIR__ . '/assets/logo-kemenag.png');
    ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumen Tidak Ditemukan - MTsN 11 Majalengka</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="icon" type="image/png" href="assets/logo-tracer-mtsn11majalengka.png">
    <link rel="apple-touch-icon" href="assets/logo-tracer-mtsn11majalengka.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f6f8fb; }
        .header-card {
            background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
            color: #fff;
            border-radius: 12px;
        }
        .official-head {
            display: grid;
            grid-template-columns: 76px 1fr;
            gap: 0.8rem;
            align-items: center;
        }
        .official-logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 6px;
        }
        .official-kemenag {
            margin: 0;
            font-size: 0.9rem;
            letter-spacing: 0.08em;
            font-weight: 700;
            text-transform: uppercase;
            opacity: 0.95;
        }
        .official-school {
            margin: 0.1rem 0;
            font-size: 1.6rem;
            line-height: 1;
            font-weight: 800;
            letter-spacing: 0.04em;
        }
        .official-address {
            margin: 0;
            font-size: 0.78rem;
            opacity: 0.9;
            font-style: italic;
        }
        .verified-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: #ffffff;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: 999px;
            padding: 0.45rem 0.85rem;
            font-weight: 600;
        }
        .meta-note {
            border-left: 4px solid #dc2626;
            background: #fef2f2;
            color: #7f1d1d;
            padding: 0.8rem 1rem;
            border-radius: 8px;
            text-align: left;
        }
        @media (max-width: 768px) {
            .container.py-4 { padding-top: 1rem !important; padding-bottom: 1rem !important; }
            .header-card { padding: 1rem !important; }
            .official-head { grid-template-columns: 54px 1fr; gap: 0.55rem; }
            .official-logo { width: 52px; height: 52px; padding: 4px; }
            .official-kemenag { font-size: 0.65rem; letter-spacing: 0.05em; }
            .official-school { font-size: 1.05rem; letter-spacing: 0.02em; }
            .official-address { font-size: 0.66rem; }
            .verified-pill { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="header-card p-4 mb-4 shadow-sm">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="official-head">
                    <?php if ($logoExists): ?>
                        <img src="<?= htmlspecialchars($logoWebPath) ?>" alt="Logo Kementerian Agama" class="official-logo">
                    <?php else: ?>
                        <div class="official-logo d-flex align-items-center justify-content-center small">LOGO</div>
                    <?php endif; ?>
                    <div>
                        <p class="official-kemenag">Kementerian Agama Republik Indonesia</p>
                        <h1 class="official-school">MTsN 11 MAJALENGKA</h1>
                        <p class="official-address">Kp. Sindanghurip Desa Maniis Kec. Cingambul<br>Kab. Majalengka, 45467</p>
                    </div>
                </div>
                <span class="verified-pill"><i class="bi bi-exclamation-triangle-fill"></i> Dokumen Tidak Terverifikasi</span>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-search" style="font-size: 3rem; color: #dc2626;"></i>
                        <h4 class="mt-3 mb-2">Dokumen Tidak Ditemukan</h4>
                        <p class="text-muted mb-3">Data dokumen yang diminta tidak tersedia dalam sistem.</p>
                        <div class="meta-note">
                            <strong><i class="bi bi-info-circle"></i> Kemungkinan Penyebab:</strong><br>
                            • Token sudah kadaluarsa atau dihapus<br>
                            • Dokumen belum tersebar secara resmi<br>
                            • Terjadi kesalahan teknis pada sistem<br><br>
                            <strong>Hubungi Madrasah:</strong> (0233) 3600020 | mtsn11majalengka@gmail.com
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4 text-muted">
            <small><b>Sistem Verifikasi TRACER MTsN 11 Majalengka</b><br>
            <em>Tracing Progress, Graduating Success.</em></small>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

$detail = json_decode($alumni['data_ijazah_json'], true) ?: [];

// Format tanggal kelulusan
$bulanIndo = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$tglKelulusanFormat = '';
if ($alumni['tanggal_kelulusan']) {
    $tglParts = explode('-', $alumni['tanggal_kelulusan']);
    $tglKelulusanFormat = str_pad((int)$tglParts[2], 2, '0', STR_PAD_LEFT) . ' ' . $bulanIndo[(int)$tglParts[1]] . ' ' . $tglParts[0];
}

// Format tempat tanggal lahir
$tempatTglLahir = '';
if ($alumni['tempat_lahir'] && $alumni['tgl_lahir']) {
    $tglLahirParts = explode('-', $alumni['tgl_lahir']);
    $tempatTglLahir = strtoupper($alumni['tempat_lahir']) . ', ' . (int)$tglLahirParts[2] . ' ' . $bulanIndo[(int)$tglLahirParts[1]] . ' ' . $tglLahirParts[0];
} elseif ($alumni['tempat_lahir']) {
    $tempatTglLahir = strtoupper($alumni['tempat_lahir']);
}

// Tahun Ajaran berdasarkan angkatan lulus
$tahunAjaran = '';
if ($alumni['angkatan_lulus']) {
    $tahunLulus = (int)$alumni['angkatan_lulus'];
    $tahunAjaran = ($tahunLulus - 1) . '/' . $tahunLulus;
}

$metaNomorSurat = '';
$metaTtdNama = '';
$metaTtdNip = '';
$metaTitimangsa = '';
try {
    $stmtMeta = db()->prepare('SELECT nomor_surat, titimangsa, ttd_nama, ttd_nip
        FROM alumni_verifikasi_meta
        WHERE verification_token = :token LIMIT 1');
    $stmtMeta->execute(['token' => $token]);
    $meta = $stmtMeta->fetch();
    if (is_array($meta)) {
        $metaNomorSurat = trim((string) ($meta['nomor_surat'] ?? ''));
        $metaTitimangsa = trim((string) ($meta['titimangsa'] ?? ''));
        $metaTtdNama = trim((string) ($meta['ttd_nama'] ?? ''));
        $metaTtdNip = trim((string) ($meta['ttd_nip'] ?? ''));
    }
} catch (Throwable $e) {
    // Metadata table may not exist yet; fallback to alumni fields.
}

$nomorTranskripTampil = $metaNomorSurat !== ''
    ? $metaNomorSurat
    : (string) (($alumni['nomor_surat'] ?? '') !== '' ? $alumni['nomor_surat'] : '-');

$nism = '-';
$nisSiswa = trim((string) ($alumni['nis'] ?? ''));
if ($nisSiswa !== '') {
    $nism = '121132100013' . $nisSiswa;
}

$normalizeMapel = static function (string $text): string {
    $text = strtolower($text);
    return preg_replace('/[^a-z0-9]+/', '', $text) ?? '';
};

$nilaiByMapel = [];
foreach ($detail as $d) {
    $mapelNama = trim((string) ($d['mapel'] ?? ''));
    if ($mapelNama === '') {
        continue;
    }

    $rataRapor = (float) ($d['rata_rapor'] ?? 0);
    $nilaiUam = (float) ($d['nilai_uam'] ?? 0);
    $nilaiIjazah = (int) round((float) ($d['nilai_ijazah'] ?? hitung_nilai_ijazah($rataRapor, $nilaiUam)));

    $nilaiByMapel[] = [
        'norm' => $normalizeMapel($mapelNama),
        'mapel' => $mapelNama,
        'rata_rapor' => (int) round($rataRapor),
        'nilai_uam' => (int) round($nilaiUam),
        'nilai_ijazah' => $nilaiIjazah,
        'terbilang' => terbilang_nilai((float) $nilaiIjazah),
    ];
}

$findMapel = static function (array $rowsMapel, array $keywords) use ($normalizeMapel): ?array {
    $normKeywords = [];
    foreach ($keywords as $kw) {
        $normKeywords[] = $normalizeMapel($kw);
    }
    foreach ($rowsMapel as $rowMapel) {
        foreach ($normKeywords as $nkw) {
            if ($nkw !== '' && strpos($rowMapel['norm'], $nkw) !== false) {
                return $rowMapel;
            }
        }
    }
    return null;
};

$layoutRows = [
    ['type' => 'group', 'label' => 'Kelompok A'],
    ['type' => 'parent', 'no' => '1', 'label' => 'Pendidikan Agama Islam'],
    ['type' => 'item', 'no' => '', 'prefix' => 'A.', 'label' => 'Al Qur\'an Hadis', 'keywords' => ['alquranhadis', 'quranhadis']],
    ['type' => 'item', 'no' => '', 'prefix' => 'B.', 'label' => 'Akidah Akhlak', 'keywords' => ['akidahakhlak']],
    ['type' => 'item', 'no' => '', 'prefix' => 'C.', 'label' => 'Fikih', 'keywords' => ['fikih', 'fiqih']],
    ['type' => 'item', 'no' => '', 'prefix' => 'D.', 'label' => 'Sejarah Kebudayaan Islam', 'keywords' => ['sejarahkebudayaanislam', 'ski']],
    ['type' => 'item', 'no' => '2', 'prefix' => '', 'label' => 'Pendidikan Pancasila dan Kewarganegaraan', 'keywords' => ['pancasila', 'kewarganegaraan', 'ppkn']],
    ['type' => 'item', 'no' => '3', 'prefix' => '', 'label' => 'Bahasa Indonesia', 'keywords' => ['bahasaindonesia']],
    ['type' => 'item', 'no' => '4', 'prefix' => '', 'label' => 'Bahasa Arab', 'keywords' => ['bahasaarab']],
    ['type' => 'item', 'no' => '5', 'prefix' => '', 'label' => 'Matematika', 'keywords' => ['matematika', 'mtk']],
    ['type' => 'item', 'no' => '6', 'prefix' => '', 'label' => 'Ilmu Pengetahuan Alam', 'keywords' => ['ilmupengetahuanalam', 'ipa']],
    ['type' => 'item', 'no' => '7', 'prefix' => '', 'label' => 'Ilmu Pengetahuan Sosial', 'keywords' => ['ilmupengetahuansosial', 'ips']],
    ['type' => 'item', 'no' => '8', 'prefix' => '', 'label' => 'Bahasa Inggris', 'keywords' => ['bahasainggris', 'inggris']],
    ['type' => 'group', 'label' => 'Kelompok B'],
    ['type' => 'item', 'no' => '1', 'prefix' => '', 'label' => 'Seni Budaya', 'keywords' => ['senibudaya']],
    ['type' => 'item', 'no' => '2', 'prefix' => '', 'label' => 'Pendidikan Jasmani, Olahraga dan Kesehatan', 'keywords' => ['pendidikanjasmaniolahragadankesehatan', 'pendidikanjasmani', 'pjok', 'penjaskes', 'penjasorkes', 'penjas', 'olahragadankesehatan', 'jasmaniolahraga']],
    ['type' => 'item', 'no' => '3', 'prefix' => '', 'label' => 'Prakarya dan/atau Informatika', 'keywords' => ['prakarya', 'informatika']],
    ['type' => 'parent', 'no' => '4', 'label' => 'Muatan Lokal'],
    ['type' => 'item', 'no' => '', 'prefix' => 'A.', 'label' => 'Bahasa Daerah', 'keywords' => ['bahasadaerah']],
];

$rowsTabel = [];
$sumRapor = 0.0;
$sumUam = 0.0;
$sumIjazah = 0.0;
$countNilai = 0;

foreach ($layoutRows as $layoutRow) {
    if ($layoutRow['type'] === 'group') {
        $rowsTabel[] = ['type' => 'group', 'label' => $layoutRow['label']];
        continue;
    }

    if ($layoutRow['type'] === 'parent') {
        $rowsTabel[] = [
            'type' => 'parent',
            'no' => $layoutRow['no'],
            'label' => $layoutRow['label'],
        ];
        continue;
    }

    $nilaiMapel = $findMapel($nilaiByMapel, $layoutRow['keywords']);
    $mapelLabel = trim(($layoutRow['prefix'] !== '' ? $layoutRow['prefix'] . ' ' : '') . $layoutRow['label']);

    $rapor = '';
    $uam = '';
    $ijazah = '';
    $terbilang = '';

    if (is_array($nilaiMapel)) {
        $rapor = (string) $nilaiMapel['rata_rapor'];
        $uam = (string) $nilaiMapel['nilai_uam'];
        $ijazah = (string) $nilaiMapel['nilai_ijazah'];
        $terbilang = (string) $nilaiMapel['terbilang'];

        $sumRapor += (float) $nilaiMapel['rata_rapor'];
        $sumUam += (float) $nilaiMapel['nilai_uam'];
        $sumIjazah += (float) $nilaiMapel['nilai_ijazah'];
        $countNilai++;
    }

    $rowsTabel[] = [
        'type' => 'item',
        'no' => $layoutRow['no'],
        'label' => $mapelLabel,
        'rapor' => $rapor,
        'uam' => $uam,
        'ijazah' => $ijazah,
        'terbilang' => $terbilang,
    ];
}

$avgRapor = $countNilai > 0 ? $sumRapor / $countNilai : 0;
$avgUam = $countNilai > 0 ? $sumUam / $countNilai : 0;
$avgIjazah = $countNilai > 0 ? $sumIjazah / $countNilai : 0;
$terbilangTotal = terbilang_nilai($avgIjazah);
$logoWebPath = 'assets/logo-kemenag.png';
$logoExists = is_file(__DIR__ . '/assets/logo-kemenag.png');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Transkrip Ijazah - MTsN 11 Majalengka</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="icon" type="image/png" href="assets/logo-tracer-mtsn11majalengka.png">
    <link rel="apple-touch-icon" href="assets/logo-tracer-mtsn11majalengka.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f6f8fb; }
        .header-card {
            background: linear-gradient(135deg, #0b5d3a 0%, #0f7a4c 100%);
            color: #fff;
            border-radius: 12px;
        }
        .official-head {
            display: grid;
            grid-template-columns: 76px 1fr;
            gap: 0.8rem;
            align-items: center;
        }
        .official-logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 6px;
        }
        .official-kemenag {
            margin: 0;
            font-size: 0.9rem;
            letter-spacing: 0.08em;
            font-weight: 700;
            text-transform: uppercase;
            opacity: 0.95;
        }
        .official-school {
            margin: 0.1rem 0;
            font-size: 1.6rem;
            line-height: 1;
            font-weight: 800;
            letter-spacing: 0.04em;
        }
        .official-address {
            margin: 0;
            font-size: 0.78rem;
            opacity: 0.9;
            font-style: italic;
        }
        .verified-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: #ffffff;
            color: #166534;
            border: 1px solid #bbf7d0;
            border-radius: 999px;
            padding: 0.45rem 0.85rem;
            font-weight: 600;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
        }
        .status-item {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 0.75rem;
        }
        .status-item .label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .status-item .value { font-size: 0.9rem; font-weight: 600; color: #0f172a; }
        .table-sm td, .table-sm th { font-size: 0.88rem; }
        .identity td { padding: 0.3rem 0; }
        .group-row td { background: #f2f5f7; font-weight: 600; }
        .parent-row td { background: #fbfcfd; }
        .meta-note {
            border-left: 4px solid #22c55e;
            background: #f0fdf4;
            color: #14532d;
            padding: 0.8rem 1rem;
            border-radius: 8px;
        }
        .table-responsive thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f8fafc;
        }
        @media (max-width: 768px) {
            .container.py-4 { padding-top: 1rem !important; padding-bottom: 1rem !important; }
            .header-card { padding: 1rem !important; }
            .official-head { grid-template-columns: 54px 1fr; gap: 0.55rem; }
            .official-logo { width: 52px; height: 52px; padding: 4px; }
            .official-kemenag { font-size: 0.65rem; letter-spacing: 0.05em; }
            .official-school { font-size: 1.05rem; letter-spacing: 0.02em; }
            .official-address { font-size: 0.66rem; }
            .status-grid { grid-template-columns: 1fr; }
            .table-sm td, .table-sm th { font-size: 0.78rem; }
            .identity td { font-size: 0.85rem; vertical-align: top; }
            .identity td:first-child { width: 45% !important; }
            .card-body { padding: 0.85rem; }
            .verified-pill { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="header-card p-4 mb-4 shadow-sm">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="official-head">
                    <?php if ($logoExists): ?>
                        <img src="<?= htmlspecialchars($logoWebPath) ?>" alt="Logo Kementerian Agama" class="official-logo">
                    <?php else: ?>
                        <div class="official-logo d-flex align-items-center justify-content-center small">LOGO</div>
                    <?php endif; ?>
                    <div>
                        <p class="official-kemenag">Kementerian Agama Republik Indonesia</p>
                        <h1 class="official-school">MTsN 11 MAJALENGKA</h1>
                        <p class="official-address">Kp. Sindanghurip Desa Maniis Kec. Cingambul<br>Kab. Majalengka, 45467</p>
                    </div>
                </div>
                <span class="verified-pill"><i class="bi bi-patch-check-fill"></i> Dokumen Terverifikasi</span>
            </div>
        </div>

        <div class="status-grid mb-3">
            <div class="status-item">
                <div class="label">Status Keaslian</div>
                <div class="value text-success"><i class="bi bi-check-circle-fill"></i> Valid dan Asli</div>
            </div>
            <div class="status-item">
                <div class="label">Nomor Verifikasi</div>
                <div class="value"><?= htmlspecialchars(substr($token, 0, 16)) ?>...</div>
            </div>
            <div class="status-item">
                <div class="label">Waktu Cek</div>
                <div class="value"><?= date('d M Y H:i') ?> WIB</div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="meta-note mb-4" role="alert">
                    <strong><i class="bi bi-shield-check"></i> Validasi Sistem Berhasil</strong><br>
                    Dokumen dianggap asli selama data pada dokumen fisik identik dengan hasil verifikasi pada Sistem TRACER MTsN 11 Majalengka.
                </div>

                <h5 class="mb-3">Meta Data</h5>
                <table class="table table-borderless identity mb-0">
                    <tr><td style="width: 320px;">Satuan Pendidikan</td><td style="width: 20px;">:</td><td>MTsN 11 MAJALENGKA</td></tr>
                    <tr><td>NPSN</td><td>:</td><td>20278893</td></tr>
                    <tr><td>Nama Lengkap</td><td>:</td><td><strong><?= htmlspecialchars(strtoupper($alumni['nama'])) ?></strong></td></tr>
                    <tr><td>Tempat, Tanggal Lahir</td><td>:</td><td><?= htmlspecialchars($tempatTglLahir) ?></td></tr>
                    <tr><td>Nomor Induk Siswa Nasional</td><td>:</td><td><?= htmlspecialchars($alumni['nisn']) ?></td></tr>
                    <tr><td>Nomor Induk Siswa Madrasah</td><td>:</td><td><?= htmlspecialchars($nism) ?></td></tr>
                    <tr><td>Nomor Transkrip Nilai</td><td>:</td><td><?= htmlspecialchars($nomorTranskripTampil) ?></td></tr>
                    <tr><td>Tanggal Kelulusan</td><td>:</td><td><?= htmlspecialchars($tglKelulusanFormat) ?></td></tr>
                    <tr><td>Tahun Ajaran</td><td>:</td><td><?= htmlspecialchars($tahunAjaran) ?></td></tr>
                    <?php if ($metaTitimangsa !== ''): ?>
                        <tr><td>Titimangsa</td><td>:</td><td>Majalengka, <?= htmlspecialchars($metaTitimangsa) ?></td></tr>
                    <?php endif; ?>
                    <?php if ($metaTtdNama !== ''): ?>
                        <tr><td>Nama Penandatangan</td><td>:</td><td><?= htmlspecialchars($metaTtdNama) ?></td></tr>
                    <?php endif; ?>
                    <?php if ($metaTtdNip !== ''): ?>
                        <tr><td>NIP Penandatangan</td><td>:</td><td><?= htmlspecialchars($metaTtdNip) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Transkrip Nilai</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light text-center align-middle">
                            <tr>
                                <th rowspan="2" style="width: 44px;">No</th>
                                <th rowspan="2">Mata Pelajaran</th>
                                <th colspan="3">Nilai</th>
                                <th rowspan="2" style="width: 230px;">Nilai Ijazah Terbilang</th>
                            </tr>
                            <tr>
                                <th style="width: 120px;">Rapor</th>
                                <th style="width: 90px;">UAM</th>
                                <th style="width: 90px;">Ijazah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rowsTabel as $row): ?>
                                <?php if ($row['type'] === 'group'): ?>
                                    <tr class="group-row"><td colspan="6"><?= htmlspecialchars($row['label']) ?></td></tr>
                                <?php elseif ($row['type'] === 'parent'): ?>
                                    <tr class="parent-row">
                                        <td class="text-center"><?= htmlspecialchars($row['no']) ?></td>
                                        <td><?= htmlspecialchars($row['label']) ?></td>
                                        <td></td><td></td><td></td><td></td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td class="text-center"><?= $row['no'] === '' ? '&nbsp;' : htmlspecialchars($row['no']) ?></td>
                                        <td><?= htmlspecialchars($row['label']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($row['rapor']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($row['uam']) ?></td>
                                        <td class="text-center fw-bold"><?= htmlspecialchars($row['ijazah']) ?></td>
                                        <td><em><?= htmlspecialchars($row['terbilang']) ?></em></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold">
                                <td colspan="2" class="text-center">Rata-Rata</td>
                                <td class="text-center"><?= number_format($avgRapor, 2, ',', '.') ?></td>
                                <td class="text-center"><?= number_format($avgUam, 2, ',', '.') ?></td>
                                <td class="text-center"><?= number_format($avgIjazah, 2, ',', '.') ?></td>
                                <td><em><?= htmlspecialchars($terbilangTotal) ?></em></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="text-center mt-4 text-muted">
            <small><b>Diverifikasi otomatis oleh sistem.</b><br>Dokumen dianggap asli selama data pada dokumen fisik identik dengan hasil verifikasi pada sistem.</small><br>
            <small>Untuk informasi lanjut:<br>(0233) 3600020 | mtsn11majalengka@gmail.com</small>
        </div>
    </div>

</body>
</html>
