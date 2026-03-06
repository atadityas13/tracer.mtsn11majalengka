<?php
/**
 * ========================================================
 * E-LEGER MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Manajemen Data Nilai Siswa
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Ekspor & Cetak Laporan Page
 * Deskripsi: Halaman untuk ekspor dan cetak laporan nilai siswa dalam berbagai format
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
 * Features:
 * - Ekspor nilai ke format Excel
 * - Cetak laporan nilai per siswa
 * - Filter per semester/kelas
 * - Format laporan yang rapi dan profesional
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
use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$setting = setting_akademik();
$tahunAjaranAktif = $setting['tahun_ajaran'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('ekspor-cetak');

    $action = $_POST['action'] ?? '';

    if ($action === 'ekspor_nilai') {
        if (!class_exists(Spreadsheet::class)) {
            set_flash('error', 'PhpSpreadsheet belum terpasang.');
            redirect('index.php?page=ekspor-cetak');
        }

        $semesterPilihan = (int) ($_POST['semester_target'] ?? 1);
        $isAkhir = ($semesterPilihan === 6);
        if ($semesterPilihan < 1 || $semesterPilihan > 6) {
            $semesterPilihan = 1;
            $isAkhir = false;
        }
        if ($isAkhir) {
            $semesterPilihan = 5; // Akhir includes semesters 1-5
        }

        // Ambil siswa angkatan berdasarkan semester target (siswa yang current_semester >= target)
        $stSiswa = db()->prepare("SELECT nisn, nis, nama FROM siswa WHERE status_siswa='Aktif' AND current_semester >= :semester_target ORDER BY COALESCE(kelas, ''), COALESCE(nomor_absen, 999), nama");
        $stSiswa->execute(['semester_target' => $semesterPilihan]);
        $angkatanSiswa = $stSiswa->fetchAll();

        if (count($angkatanSiswa) === 0) {
            set_flash('error', 'Tidak ada siswa aktif di semester ' . $semesterPilihan . ' ke atas.');
            redirect('index.php?page=ekspor-cetak');
        }

        // Ambil daftar mapel kelompok A (semua mata pelajaran utama)
        $stMapel = db()->query("SELECT id, nama_mapel FROM mapel WHERE kelompok='A' ORDER BY urutan");
        $mapelList = $stMapel->fetchAll();

        // Buat spreadsheet dengan tab per semester 1 sampai target
        $sheet = new Spreadsheet();
        $sheet->removeSheetByIndex(0); // Hapus sheet default

        for ($sem = 1; $sem <= $semesterPilihan; $sem++) {
            $sheetSem = $sheet->createSheet();
            $sheetSem->setTitle('Semester ' . $sem);

            // Header kolom: No, NISN, NIS, Nama Lengkap, [Mapel-mapel], RATA-RATA
            $headerRow = ['No', 'NISN', 'NIS', 'Nama Lengkap'];
            foreach ($mapelList as $m) {
                $headerRow[] = $m['nama_mapel'];
            }
            $headerRow[] = 'RATA-RATA';
            $sheetSem->fromArray($headerRow, null, 'A1');

            // Styling header (warna kuning background, bold text)
            $lastCol = chr(65 + count($headerRow) - 1); // Convert 0-based to A,B,C...
            $headerStyle = $sheetSem->getStyle('A1:' . $lastCol . '1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $headerStyle->getFill()->getStartColor()->setARGB('FFFFFF00'); // Yellow background

            // Data siswa per semester
            $siswaNo = 1;
            $dataRowStart = 2; // Row 2 is first data row
            foreach ($angkatanSiswa as $siswa) {
                $rowData = [$siswaNo++, $siswa['nisn'], $siswa['nis'], $siswa['nama']];

                // Ambil nilai rapor siswa di semester ini untuk semua mapel
                $stNilai = db()->prepare("SELECT mapel_id, nilai_angka FROM nilai_rapor WHERE nisn=:nisn AND semester=:semester AND tahun_ajaran=:ta");
                $stNilai->execute([
                    'nisn' => $siswa['nisn'],
                    'semester' => $sem,
                    'ta' => $tahunAjaranAktif,
                ]);
                $nilaiData = $stNilai->fetchAll(\PDO::FETCH_KEY_PAIR);

                // Isi nilai per mapel dalam urutan mapelList, dan hitung rata-rata
                $nilaiValues = [];
                foreach ($mapelList as $m) {
                    $nilai = $nilaiData[$m['id']] ?? null;
                    if ($nilai !== null) {
                        $nilaiValues[] = (float) $nilai;
                        $rowData[] = (float) $nilai;
                    } else {
                        $rowData[] = '';
                    }
                }

                // Hitung rata-rata jika ada nilai
                $rataRata = count($nilaiValues) > 0 ? round(array_sum($nilaiValues) / count($nilaiValues), 2) : '';
                $rowData[] = $rataRata;

                $sheetSem->fromArray([$rowData], null, 'A' . ($dataRowStart + $siswaNo - 2));
            }

            // Auto-fit columns untuk readability
            foreach ($sheetSem->getColumnIterator() as $column) {
                $sheetSem->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }
        }

        // Tambahkan sheet UAM jika Akhir dipilih
        if ($isAkhir) {
            $sheetUam = $sheet->createSheet();
            $sheetUam->setTitle('UAM (Akhir)');

            // Header kolom untuk UAM
            $headerRow = ['No', 'NISN', 'NIS', 'Nama Lengkap'];
            foreach ($mapelList as $m) {
                $headerRow[] = $m['nama_mapel'];
            }
            $sheetUam->fromArray($headerRow, null, 'A1');

            // Styling header (warna kuning background, bold text)
            $lastCol = chr(65 + count($headerRow) - 1);
            $headerStyle = $sheetUam->getStyle('A1:' . $lastCol . '1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $headerStyle->getFill()->getStartColor()->setARGB('FFFFFF00'); // Yellow background

            // Data UAM untuk siswa angkatan
            $siswaNo = 1;
            $dataRowStart = 2;
            foreach ($angkatanSiswa as $siswa) {
                $rowData = [$siswaNo++, $siswa['nisn'], $siswa['nis'], $siswa['nama']];

                // Ambil nilai UAM siswa
                $stUam = db()->prepare("SELECT mapel_id, nilai_angka FROM nilai_uam WHERE nisn=:nisn");
                $stUam->execute(['nisn' => $siswa['nisn']]);
                $nilaiUam = $stUam->fetchAll(\PDO::FETCH_KEY_PAIR);

                // Isi nilai UAM per mapel
                foreach ($mapelList as $m) {
                    $nilai = $nilaiUam[$m['id']] ?? '';
                    $rowData[] = $nilai !== '' ? (float) $nilai : '';
                }

                $sheetUam->fromArray([$rowData], null, 'A' . ($dataRowStart + $siswaNo - 2));
            }

            // Auto-fit columns
            foreach ($sheetUam->getColumnIterator() as $column) {
                $sheetUam->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }

            // Tambahkan sheet Nilai Ijazah
            $sheetIjazah = $sheet->createSheet();
            $sheetIjazah->setTitle('Nilai Ijazah');

            // Header kolom untuk Nilai Ijazah
            $headerRow = ['No', 'NISN', 'NIS', 'Nama Lengkap'];
            foreach ($mapelList as $m) {
                $headerRow[] = $m['nama_mapel'];
            }
            $headerRow[] = 'RATA-RATA IJAZAH';
            $sheetIjazah->fromArray($headerRow, null, 'A1');

            // Styling header (warna kuning background, bold text)
            $lastCol = chr(65 + count($headerRow) - 1);
            $headerStyle = $sheetIjazah->getStyle('A1:' . $lastCol . '1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $headerStyle->getFill()->getStartColor()->setARGB('FFFFFF00'); // Yellow background

            // Data Nilai Ijazah untuk siswa angkatan
            // Formula: (rata_rapor * 0.6) + (nilai_uam * 0.4)
            $siswaNo = 1;
            $dataRowStart = 2;
            foreach ($angkatanSiswa as $siswa) {
                $rowData = [$siswaNo++, $siswa['nisn'], $siswa['nis'], $siswa['nama']];

                // Ambil nilai rapor semester 5 siswa
                $stRapor5 = db()->prepare("SELECT mapel_id, nilai_angka FROM nilai_rapor WHERE nisn=:nisn AND semester=5 AND tahun_ajaran=:ta");
                $stRapor5->execute(['nisn' => $siswa['nisn'], 'ta' => $tahunAjaranAktif]);
                $nilaiRapor5 = $stRapor5->fetchAll(\PDO::FETCH_KEY_PAIR);

                // Ambil nilai UAM siswa
                $stUam = db()->prepare("SELECT mapel_id, nilai_angka FROM nilai_uam WHERE nisn=:nisn");
                $stUam->execute(['nisn' => $siswa['nisn']]);
                $nilaiUam = $stUam->fetchAll(\PDO::FETCH_KEY_PAIR);

                // Hitung nilai ijazah per mapel
                $nilaiIjazahValues = [];
                foreach ($mapelList as $m) {
                    $rapor5 = $nilaiRapor5[$m['id']] ?? null;
                    $uam = $nilaiUam[$m['id']] ?? null;

                    if ($rapor5 !== null && $uam !== null) {
                        $nilaiIjazah = round(((float) $rapor5 * 0.6) + ((float) $uam * 0.4), 2);
                        $nilaiIjazahValues[] = $nilaiIjazah;
                        $rowData[] = $nilaiIjazah;
                    } else {
                        $rowData[] = '';
                    }
                }

                // Hitung rata-rata ijazah
                $rataIjazah = count($nilaiIjazahValues) > 0 ? round(array_sum($nilaiIjazahValues) / count($nilaiIjazahValues), 2) : '';
                $rowData[] = $rataIjazah;

                $sheetIjazah->fromArray([$rowData], null, 'A' . ($dataRowStart + $siswaNo - 2));
            }

            // Auto-fit columns
            foreach ($sheetIjazah->getColumnIterator() as $column) {
                $sheetIjazah->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $filename = $isAkhir ? 'leger_nilai_akhir_' . str_replace('/', '-', $tahunAjaranAktif) . '.xlsx' : 'leger_nilai_sem' . $semesterPilihan . '_' . str_replace('/', '-', $tahunAjaranAktif) . '.xlsx';
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $writer = new Xlsx($sheet);
        $writer->save('php://output');
        exit;
    }

    if ($action === 'transkrip') {
        if (!class_exists(Dompdf::class)) {
            set_flash('error', 'Dompdf belum terpasang.');
            redirect('index.php?page=ekspor-cetak');
        }

        $nisn = trim($_POST['nisn'] ?? '');
        $stmt = db()->prepare('SELECT a.nisn, a.angkatan_lulus, a.data_ijazah_json, s.nama FROM alumni a LEFT JOIN siswa s ON s.nisn=a.nisn WHERE a.nisn=:nisn LIMIT 1');
        $stmt->execute(['nisn' => $nisn]);
        $alumni = $stmt->fetch();

        if (!$alumni) {
            set_flash('error', 'Data alumni tidak ditemukan.');
            redirect('index.php?page=ekspor-cetak');
        }

        $detail = json_decode($alumni['data_ijazah_json'], true) ?: [];
        $rows = '';
        foreach ($detail as $d) {
            $rows .= '<tr>'
                . '<td>' . e($d['mapel']) . '</td>'
                . '<td>' . e((string) $d['rata_rapor']) . '</td>'
                . '<td>' . e((string) $d['nilai_uam']) . '</td>'
                . '<td>' . e((string) $d['nilai_ijazah']) . '</td>'
                . '<td>' . e($d['terbilang']) . '</td>'
                . '</tr>';
        }

        $html = '<h2>Transkrip Nilai Ijazah</h2>'
            . '<p>NISN: ' . e($alumni['nisn']) . '</p>'
            . '<p>Angkatan: ' . e((string) $alumni['angkatan_lulus']) . '</p>'
            . '<table border="1" cellspacing="0" cellpadding="6" width="100%">'
            . '<thead><tr><th>Mapel</th><th>Rata Rapor</th><th>UAM</th><th>Nilai Ijazah</th><th>Terbilang</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>';

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('transkrip_' . $nisn . '.pdf', ['Attachment' => true]);
        exit;
    }
}

$alumniList = db()->query('SELECT nisn, angkatan_lulus FROM alumni ORDER BY angkatan_lulus DESC, nisn')->fetchAll();

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-1">Ekspor Leger Nilai (Excel)</h3>
        <p class="text-secondary mb-0">
            Ekspor nilai per semester untuk seluruh siswa angkatan pada tahun ajaran aktif: <?= e($tahunAjaranAktif) ?>.
            Data akan diunduh dalam format Excel dengan tab per semester.
        </p>
    </div>
    <div class="card-body">
        <form method="post" class="row g-3 align-items-end">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="ekspor_nilai">
            <div class="col-md-6">
                <label class="form-label">Pilih Kelompok Semester</label>
                <select name="semester_target" class="form-select">
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                    <option value="6">Akhir</option>
                </select>
                <small class="text-secondary d-block mt-2">
                    Sistem akan mengekspor nilai semester 1 hingga semester yang dipilih 
                    untuk semua siswa aktif pada semester itu ke atas.
                </small>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-success w-100">Download Excel</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-0">Cetak Transkrip Ijazah (PDF)</h3>
    </div>
    <div class="card-body">
        <form method="post" class="row g-3 align-items-end">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="transkrip">
            <div class="col-md-8">
                <label class="form-label">Pilih Alumni (NISN)</label>
                <select name="nisn" class="form-select" required>
                    <option value="">-- pilih alumni --</option>
                    <?php foreach ($alumniList as $a): ?>
                        <option value="<?= e($a['nisn']) ?>"><?= e($a['nisn']) ?> - Angkatan <?= e((string) $a['angkatan_lulus']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-outline-success w-100">Download PDF</button>
            </div>
        </form>
    </div>
</div>
<?php require dirname(__DIR__) . '/partials/footer.php';
