<?php
/**
 * Halaman Verifikasi Transkrip Ijazah
 * Diakses via QR Code pada dokumen transkrip
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

$token = $_GET['token'] ?? '';

if ($token === '') {
    http_response_code(400);
    die('Token verifikasi tidak valid.');
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
    die('Dokumen tidak ditemukan. Token verifikasi tidak valid.');
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
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Transkrip Ijazah - MTsN 11 Majalengka</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f6f8fb; }
        .header-card {
            background: linear-gradient(135deg, #0b5d3a 0%, #0f7a4c 100%);
            color: #fff;
            border-radius: 12px;
        }
        .table-sm td, .table-sm th { font-size: 0.88rem; }
        .identity td { padding: 0.3rem 0; }
        .group-row td { background: #f2f5f7; font-weight: 600; }
        .parent-row td { background: #fbfcfd; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="header-card p-4 mb-4 shadow-sm">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h1 class="h4 mb-1">Verifikasi Transkrip Nilai</h1>
                    <p class="mb-0 opacity-75">MTsN 11 Majalengka</p>
                </div>
                <span class="badge bg-light text-success border border-success-subtle fs-6">Dokumen Terverifikasi</span>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="alert alert-success mb-4" role="alert">
                    Dokumen ini <strong>ASLI</strong> dan terverifikasi di sistem e-Leger MTsN 11 Majalengka.
                </div>

                <h5 class="mb-3">Data Siswa</h5>
                <table class="table table-borderless identity mb-0">
                    <tr><td style="width: 320px;">Satuan Pendidikan</td><td style="width: 20px;">:</td><td>MTsN 11 MAJALENGKA</td></tr>
                    <tr><td>Nomor Pokok Sekolah Nasional</td><td>:</td><td>20278893</td></tr>
                    <tr><td>Nama Lengkap</td><td>:</td><td><strong><?= htmlspecialchars(strtoupper($alumni['nama'])) ?></strong></td></tr>
                    <tr><td>Tempat dan Tanggal Lahir</td><td>:</td><td><?= htmlspecialchars($tempatTglLahir) ?></td></tr>
                    <tr><td>Nomor Induk Siswa Nasional</td><td>:</td><td><?= htmlspecialchars($alumni['nisn']) ?></td></tr>
                    <tr><td>Nomor Induk Siswa Madrasah</td><td>:</td><td><?= htmlspecialchars($nism) ?></td></tr>
                    <tr><td>Nomor Transkrip Nilai</td><td>:</td><td><?= htmlspecialchars((string) ($alumni['nomor_surat'] ?: '-')) ?></td></tr>
                    <tr><td>Tanggal Kelulusan</td><td>:</td><td><?= htmlspecialchars($tglKelulusanFormat) ?></td></tr>
                    <tr><td>Tahun Ajaran</td><td>:</td><td><?= htmlspecialchars($tahunAjaran) ?></td></tr>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Rincian Nilai Ijazah</h5>
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
                                <th style="width: 120px;">Rata-rata Rapor</th>
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
            <small>Dokumen ini diverifikasi otomatis oleh sistem e-Leger MTsN 11 Majalengka.</small><br>
            <small>Untuk informasi lanjut: (0233) 3600020 | mtsn11majalengka@gmail.com</small>
        </div>
    </div>

</body>
</html>
