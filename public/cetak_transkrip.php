<?php
/**
 * Endpoint khusus untuk cetak transkrip ijazah (PDF)
 * Memisahkan endpoint agar title tab browser menampilkan nama file, bukan index.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use Dompdf\Dompdf;

// Cek login
if (!is_logged_in()) {
    http_response_code(403);
    exit('Akses ditolak. Silakan login terlebih dahulu.');
}

// Validasi CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    http_response_code(403);
    exit('CSRF validation failed');
}

// Cek library Dompdf
if (!class_exists(Dompdf::class)) {
    http_response_code(500);
    exit('Dompdf belum terpasang. Harap install dependency terlebih dahulu.');
}

$action = $_POST['action'] ?? '';
if (!in_array($action, ['transkrip', 'bulk_transkrip'], true)) {
    http_response_code(400);
    exit('Action tidak valid');
}

// Ambil data TTD dari form
$titimangsaInput = trim((string) ($_POST['titimangsa'] ?? ''));
if ($titimangsaInput === '') {
    $titimangsa = date('d F Y');
} elseif (preg_match('/^(\d{1,2})\s+(.+)\s+(\d{4})$/', $titimangsaInput, $mTitimangsa)) {
    $titimangsa = str_pad((string) ((int) $mTitimangsa[1]), 2, '0', STR_PAD_LEFT) . ' ' . trim($mTitimangsa[2]) . ' ' . $mTitimangsa[3];
} else {
    $titimangsa = $titimangsaInput;
}
$namaKepsek = $_POST['nama_kepsek'] ?? 'Kepala Madrasah';
$nipKepsek = $_POST['nip_kepsek'] ?? '';
$nomorUrutAwal = (int) ($_POST['nomor_urut'] ?? 1);

// Ekstrak bulan dan tahun dari titimangsa untuk nomor surat
$bulanSurat = date('m');
$tahunSurat = date('Y');
if (preg_match('/\d{1,2}\s+(\w+)\s+(\d{4})/', $titimangsa, $mTiti)) {
    $bulanIndo = ['januari' => '01', 'februari' => '02', 'maret' => '03', 'april' => '04', 
                 'mei' => '05', 'juni' => '06', 'juli' => '07', 'agustus' => '08',
                 'september' => '09', 'oktober' => '10', 'november' => '11', 'desember' => '12'];
    $bulanNama = strtolower($mTiti[1]);
    if (isset($bulanIndo[$bulanNama])) {
        $bulanSurat = $bulanIndo[$bulanNama];
    }
    $tahunSurat = $mTiti[2];
}

$pdo = db();

// Tentukan NISN yang akan dicetak
$nisnList = [];
$alumniRows = [];
if ($action === 'bulk_transkrip') {
    $angkatanFilter = (int) ($_POST['angkatan'] ?? 0);
    $stmtBulk = $pdo->prepare('SELECT a.nisn, a.nama, a.angkatan_lulus, a.tanggal_kelulusan, a.nomor_surat, a.data_ijazah_json, a.verification_token,
        s.tempat_lahir, s.tgl_lahir, s.nis
        FROM alumni a
        LEFT JOIN siswa s ON s.nisn = a.nisn
        WHERE a.angkatan_lulus = :angkatan
        ORDER BY a.nama');
    $stmtBulk->execute(['angkatan' => $angkatanFilter]);
    $alumniRows = $stmtBulk->fetchAll();
    $nisnList = array_column($alumniRows, 'nisn');
} else {
    $nisn = trim((string) ($_POST['nisn'] ?? ''));
    if ($nisn !== '') {
        $stmtOne = $pdo->prepare('SELECT a.nisn, a.nama, a.angkatan_lulus, a.tanggal_kelulusan, a.nomor_surat, a.data_ijazah_json, a.verification_token,
            s.tempat_lahir, s.tgl_lahir, s.nis
            FROM alumni a
            LEFT JOIN siswa s ON s.nisn = a.nisn
            WHERE a.nisn = :nisn LIMIT 1');
        $stmtOne->execute(['nisn' => $nisn]);
        $one = $stmtOne->fetch();
        if ($one) {
            $alumniRows = [$one];
            $nisnList = [$nisn];
        }
    }
}

if (empty($alumniRows)) {
    http_response_code(404);
    exit('Tidak ada data alumni untuk dicetak.');
}

$dompdf = new Dompdf();
$dompdf->set_option('isHtml5ParserEnabled', true);
$dompdf->set_option('isRemoteEnabled', true);

// Load logo
$logoDataUri = '';
$logoPath = dirname(__DIR__) . '/public/assets/logo-kemenag.png';
if (is_file($logoPath)) {
    $logoBinary = file_get_contents($logoPath);
    if ($logoBinary !== false) {
        $logoDataUri = 'data:image/png;base64,' . base64_encode($logoBinary);
    }
}

$allHtml = '';
$firstAlumniName = '';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]";
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
if ($scriptDir === '.' || $scriptDir === '/') {
    $scriptDir = '';
}

$stmtMapel = $pdo->query('SELECT id, nama_mapel, kelompok FROM mapel ORDER BY urutan_tampil, nama_mapel');
$dataMapel = $stmtMapel->fetchAll();
$mapelById = [];
foreach ($dataMapel as $m) {
    $mapelById[$m['id']] = $m;
}

$nomorUrut = $nomorUrutAwal;
foreach ($alumniRows as $idx => $alumni) {
    if ($idx === 0) {
        $firstAlumniName = $alumni['nama'] ?? '';
    }

    $currentNisn = $alumni['nisn'];
    $namaLengkap = $alumni['nama'] ?? '';
    $tempat = $alumni['tempat_lahir'] ?? '';
    $tglLahir = $alumni['tgl_lahir'] ? date('d F Y', strtotime($alumni['tgl_lahir'])) : '';
    $nis = $alumni['nis'] ?? '';

    $dataNilaiJson = $alumni['data_ijazah_json'] ?? '';
    $arrNilai = [];
    if ($dataNilaiJson !== '' && $dataNilaiJson !== null) {
        $decoded = json_decode($dataNilaiJson, true);
        if (is_array($decoded)) {
            $arrNilai = $decoded;
        }
    }

    $verificationToken = $alumni['verification_token'] ?? '';
    $qrCodeUrl = '';
    if ($verificationToken !== '') {
        $verifyUrl = $baseUrl . $scriptDir . '/verify.php?token=' . urlencode($verificationToken);
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode($verifyUrl);
    }

    $formatNomorSurat = str_pad((string) $nomorUrut, 3, '0', STR_PAD_LEFT) . '/MTs.10.89/PP.00.5/' . $bulanSurat . '/' . $tahunSurat;
    $nomorUrut++;

    $html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: "Times New Roman", serif; font-size: 10pt; margin: 0; padding: 0; }
.page { width: 210mm; min-height: 297mm; padding: 15mm 20mm; box-sizing: border-box; page-break-after: always; }
.header { text-align: center; margin-bottom: 10px; }
.header table { width: 100%; border-collapse: collapse; }
.header td { vertical-align: middle; }
.header .logo { width: 80px; text-align: left; }
.header .logo img { width: 70px; height: auto; }
.header .kop { text-align: center; }
.header .kop h3 { margin: 0; font-size: 13pt; font-weight: bold; }
.header .kop p { margin: 2px 0; font-size: 9pt; }
.header .qr { width: 80px; text-align: right; }
.header .qr img { width: 60px; height: 60px; }
.header-border { border-top: 3px solid #000; margin-top: 5px; }
.title { text-align: center; margin: 15px 0 10px 0; }
.title h2 { margin: 0; font-size: 14pt; text-decoration: underline; font-weight: bold; }
.title p { margin: 2px 0; font-size: 10pt; }
.identitas { margin-bottom: 10px; }
.identitas table { width: 100%; font-size: 10pt; }
.identitas td { padding: 3px 0; vertical-align: top; }
.identitas td:first-child { width: 35%; }
.identitas td:nth-child(2) { width: 5%; }
.nilai-table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 9pt; }
.nilai-table th, .nilai-table td { border: 1px solid #000; padding: 5px; text-align: center; }
.nilai-table th { background-color: #e0e0e0; font-weight: bold; }
.nilai-table td.left { text-align: left; padding-left: 8px; }
.ttd-section { margin-top: 20px; }
.ttd-table { width: 100%; font-size: 10pt; }
.ttd-table td { padding: 3px; vertical-align: top; }
.ttd-row { display: flex; justify-content: flex-end; }
.ttd-box { width: 45%; text-align: center; }
.signature-space { height: 60px; }
.underline { border-bottom: 1px solid #000; display: inline-block; padding: 0 5px; }
</style>
</head>
<body>
<div class="page">
    <div class="header">
        <table>
            <tr>
                <td class="logo">' . ($logoDataUri ? '<img src="' . $logoDataUri . '" alt="Logo">' : '') . '</td>
                <td class="kop">
                    <h3>KEMENTERIAN AGAMA REPUBLIK INDONESIA</h3>
                    <h3>MTsN 11 MAJALENGKA</h3>
                    <p><em>Kp. Sindanghurip Desa Manis Kec. Cingambul Kab. Majalengka, 45467.</em></p>
                    <p><em>Telp. (0233) 3600020 E-mail: mtsn11majalengka@gmail.com</em></p>
                </td>
                <td class="qr">' . ($qrCodeUrl ? '<img src="' . $qrCodeUrl . '" alt="QR">' : '') . '</td>
            </tr>
        </table>
        <div class="header-border"></div>
    </div>
    
    <div class="title">
        <h2>TRANSKRIP NILAI</h2>
        <p>TAHUN AJARAN 2025/2026</p>
    </div>
    
    <div class="identitas">
        <table>
            <tr><td>Satuan Pendidikan</td><td>:</td><td>MTsN 11 MAJALENGKA</td></tr>
            <tr><td>Nomor Pokok Sekolah Nasional</td><td>:</td><td>20278893</td></tr>
            <tr><td>Nama Lengkap</td><td>:</td><td>' . htmlspecialchars($namaLengkap, ENT_QUOTES, 'UTF-8') . '</td></tr>
            <tr><td>Tempat dan Tanggal Lahir</td><td>:</td><td>' . htmlspecialchars($tempat . ', ' . $tglLahir, ENT_QUOTES, 'UTF-8') . '</td></tr>
            <tr><td>Nomor Induk Siswa Nasional</td><td>:</td><td>' . htmlspecialchars($currentNisn, ENT_QUOTES, 'UTF-8') . '</td></tr>
            <tr><td>Nomor Induk Siswa Madrasah</td><td>:</td><td>' . htmlspecialchars($nis, ENT_QUOTES, 'UTF-8') . '</td></tr>
            <tr><td>Nomor Transkrip Nilai</td><td>:</td><td>' . htmlspecialchars($formatNomorSurat, ENT_QUOTES, 'UTF-8') . '</td></tr>
            <tr><td>Tanggal Kelulusan</td><td>:</td><td>07 Maret 2026</td></tr>
        </table>
    </div>
    
    <table class="nilai-table">
        <thead>
            <tr>
                <th rowspan="2">No</th>
                <th rowspan="2">Mata Pelajaran</th>
                <th colspan="3">Nilai</th>
                <th rowspan="2">Nilai Ijazah Terbilang</th>
            </tr>
            <tr>
                <th>Rapor<br>(Rata-rata<br>Semester 1-5)</th>
                <th>UAM<br>(Ujian Akhir<br>Madrasah)</th>
                <th>Ijazah<br>(60% Rapor+40% UAM)</th>
            </tr>
        </thead>
        <tbody>';

    $no = 1;
    foreach ($dataMapel as $mapel) {
        $mapelId = $mapel['id'];
        $namaMapel = $mapel['nama_mapel'];
        $kelompok = $mapel['kelompok'] ?? '';
        
        $nilaiRapor = $arrNilai[$mapelId]['rapor'] ?? 0;
        $nilaiUAM = $arrNilai[$mapelId]['uam'] ?? 0;
        $nilaiIjazah = $arrNilai[$mapelId]['ijazah'] ?? 0;
        $terbilang = $arrNilai[$mapelId]['terbilang'] ?? '';

        if ($kelompok !== '') {
            $html .= '<tr><td colspan="6" class="left" style="background-color: #f0f0f0; font-weight: bold;">' . htmlspecialchars($kelompok, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }

        $html .= '<tr>
            <td>' . $no . '</td>
            <td class="left">' . htmlspecialchars($namaMapel, ENT_QUOTES, 'UTF-8') . '</td>
            <td>' . ($nilaiRapor > 0 ? number_format($nilaiRapor, 0) : '-') . '</td>
            <td>' . ($nilaiUAM > 0 ? number_format($nilaiUAM, 0) : '-') . '</td>
            <td>' . ($nilaiIjazah > 0 ? '<strong>' . number_format($nilaiIjazah, 0) . '</strong>' : '-') . '</td>
            <td class="left"><em>' . htmlspecialchars($terbilang, ENT_QUOTES, 'UTF-8') . '</em></td>
        </tr>';
        $no++;
    }

    $html .= '
        </tbody>
    </table>
    
    <div class="ttd-section">
        <div class="ttd-row">
            <div class="ttd-box">
                <p style="margin: 0 0 5px 0;">Majalengka, ' . htmlspecialchars($titimangsa, ENT_QUOTES, 'UTF-8') . '</p>
                <p style="margin: 0 0 5px 0; font-weight: bold;">Kepala Madrasah</p>
                <div class="signature-space"></div>
                <p style="margin: 0;"><span class="underline">' . htmlspecialchars($namaKepsek, ENT_QUOTES, 'UTF-8') . '</span></p>
                ' . ($nipKepsek !== '' ? '<p style="margin: 0; font-size: 9pt;">NIP. ' . htmlspecialchars($nipKepsek, ENT_QUOTES, 'UTF-8') . '</p>' : '') . '
            </div>
        </div>
    </div>
</div>
</body>
</html>';

    $allHtml .= $html;
}

$dompdf->loadHtml($allHtml);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

if ($action === 'bulk_transkrip') {
    $filename = 'transkrip_angkatan_' . $angkatanFilter . '.pdf';
} else {
    $safeName = trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $firstAlumniName), '_');
    if ($safeName === '') {
        $safeName = 'transkrip_' . $nisnList[0];
    }
    $filename = $safeName . '.pdf';
}

$pdfBinary = $dompdf->output();
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Content-Length: ' . strlen($pdfBinary));
header('Cache-Control: public, max-age=0');
echo $pdfBinary;
exit;
