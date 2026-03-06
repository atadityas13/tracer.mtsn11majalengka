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
    s.tempat_lahir, s.tanggal_lahir
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
if ($alumni['tempat_lahir'] && $alumni['tanggal_lahir']) {
    $tglLahirParts = explode('-', $alumni['tanggal_lahir']);
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
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Transkrip Ijazah - MTsN 11 Majalengka</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        .verification-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .verification-badge {
            background: #28a745;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 1rem;
            font-weight: bold;
        }
        .info-row {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-label {
            font-weight: 600;
            color: #6c757d;
        }
        .table-nilai {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-light">
    <div class="verification-header text-center">
        <div class="container">
            <div class="verification-badge">
                <i class="bi bi-patch-check-fill"></i> DOKUMEN TERVERIFIKASI
            </div>
            <h1 class="h3 mb-2">Transkrip Nilai Ijazah</h1>
            <p class="mb-0">MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat</p>
        </div>
    </div>

    <div class="container pb-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="alert alert-success border-0">
                    <h5 class="alert-heading">✓ Dokumen Asli Terverifikasi</h5>
                    <p class="mb-0">Dokumen yang Anda scan adalah <strong>ASLI</strong> dan sesuai dengan data resmi yang tercatat di sistem MTsN 11 Majalengka.</p>
                </div>

                <h5 class="mb-3">Detail Transkrip</h5>
                <?php if ($alumni['nomor_surat']): ?>
                <div class="info-row">
                    <div class="row">
                        <div class="col-md-5 info-label">Nomor Transkrip</div>
                        <div class="col-md-7"><strong><?= htmlspecialchars($alumni['nomor_surat']) ?></strong></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($tahunAjaran): ?>
                <div class="info-row">
                    <div class="row">
                        <div class="col-md-5 info-label">Tahun Ajaran</div>
                        <div class="col-md-7"><?= htmlspecialchars($tahunAjaran) ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h5 class="mb-3">Informasi Alumni</h5>
                <div class="info-row">
                    <div class="row">
                        <div class="col-md-5 info-label">Satuan Pendidikan</div>
                        <div class="col-md-7">MTsN 11 MAJALENGKA</div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="row">
                        <div class="col-md-5 info-label">Nomor Pokok Sekolah Nasional</div>
                        <div class="col-md-7">20278893</div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="row">
                        <div class="col-md-5 info-label">Nama Lengkap</div>
                        <div class="col-md-7"><strong><?= htmlspecialchars(strtoupper($alumni['nama'])) ?></strong></div>
                    </div>
                </div>
                <?php if ($tempatTglLahir): ?>
                <div class="info-row">
                    <div class="row">
                        <div class="col-md-5 info-label">Tempat dan Tanggal Lahir</div>
                        <div class="col-md-7"><?= htmlspecialchars($tempatTglLahir) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <div class="row">
                        <div class="col-md-5 info-label">Nomor Induk Siswa Nasional</div>
                        <div class="col-md-7"><?= htmlspecialchars($alumni['nisn']) ?></div>
                    </div>
                </div>
                <?php if ($tglKelulusanFormat): ?>
                <div class="info-row">
                    <div class="row">
                        <div class="col-md-5 info-label">Tanggal Kelulusan</div>
                        <div class="col-md-7"><?= htmlspecialchars($tglKelulusanFormat) ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Nilai Ijazah Per Mata Pelajaran</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-nilai table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mata Pelajaran</th>
                                <th class="text-center">Rata-rata Rapor</th>
                                <th class="text-center">Nilai UAM</th>
                                <th class="text-center">Nilai Ijazah</th>
                                <th>Terbilang</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detail as $d): ?>
                            <tr>
                                <td><?= htmlspecialchars($d['mapel']) ?></td>
                                <td class="text-center"><?= htmlspecialchars((string) $d['rata_rapor']) ?></td>
                                <td class="text-center"><?= htmlspecialchars((string) $d['nilai_uam']) ?></td>
                                <td class="text-center"><strong><?= htmlspecialchars((string) $d['nilai_ijazah']) ?></strong></td>
                                <td><?= htmlspecialchars($d['terbilang']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="text-center mt-4 text-muted">
            <small>Dokumen ini diverifikasi secara otomatis oleh sistem e-Leger MTsN 11 Majalengka.</small><br>
            <small>Untuk informasi lebih lanjut hubungi: (0233) 8319182 | mtsn11majalengka@gmail.com</small>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</body>
</html>
