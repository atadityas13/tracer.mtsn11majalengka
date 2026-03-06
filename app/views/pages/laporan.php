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

        $semesterInput = (int) ($_POST['semester_target'] ?? 1);
        $semesterPilihan = $semesterInput;
        $isAkhir = ($semesterInput === 6);
        if ($semesterPilihan < 1 || $semesterPilihan > 6) {
            $semesterPilihan = 1;
            $isAkhir = false;
        }
        if ($isAkhir) {
            $semesterPilihan = 5; // Akhir includes semesters 1-5
        }

        // Jika pilih Akhir, hanya ambil siswa yang benar-benar sudah mencapai semester akhir (6).
        $sqlSiswa = "SELECT nisn, nis, nama FROM siswa WHERE status_siswa='Aktif'";
        $paramsSiswa = [];
        if ($isAkhir) {
            $sqlSiswa .= " AND current_semester = 6";
        } else {
            $sqlSiswa .= " AND current_semester >= :semester_target";
            $paramsSiswa['semester_target'] = $semesterPilihan;
        }
        $sqlSiswa .= " ORDER BY COALESCE(kelas, ''), COALESCE(nomor_absen, 999), nama";

        $stSiswa = db()->prepare($sqlSiswa);
        $stSiswa->execute($paramsSiswa);
        $angkatanSiswa = $stSiswa->fetchAll();

        if (count($angkatanSiswa) === 0) {
            if ($isAkhir) {
                set_flash('error', 'Tidak ada siswa aktif di semester Akhir.');
            } else {
                set_flash('error', 'Tidak ada siswa aktif yang sudah mencapai semester ' . $semesterPilihan . '.');
            }
            redirect('index.php?page=ekspor-cetak');
        }

        // Ambil daftar mapel (semua mata pelajaran kelompok A dan B)
        $stMapel = db()->query("SELECT id, nama_mapel FROM mapel ORDER BY urutan");
        $mapelList = $stMapel->fetchAll();

        // Buat spreadsheet dengan tab per semester 1 sampai target
        $sheet = new Spreadsheet();
        $sheet->removeSheetByIndex(0); // Hapus sheet default

        for ($sem = 1; $sem <= $semesterPilihan; $sem++) {
            $sheetSem = $sheet->createSheet();
            $sheetSem->setTitle('Semester ' . $sem);

            // Hitung kolom terakhir untuk merge title
            $numMapel = count($mapelList);
            $totalCols = 4 + $numMapel + 1; // 4 info cols + mapel + rata-rata
            $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);
            
            // BARIS 1 & 2: Merge cells vertikal untuk kolom info siswa (A-D)
            $sheetSem->mergeCells('A1:A2');
            $sheetSem->setCellValue('A1', 'No');
            $sheetSem->mergeCells('B1:B2');
            $sheetSem->setCellValue('B1', 'NISN');
            $sheetSem->mergeCells('C1:C2');
            $sheetSem->setCellValue('C1', 'NIS');
            $sheetSem->mergeCells('D1:D2');
            $sheetSem->setCellValue('D1', 'Nama Lengkap');
            
            // BARIS 1: Merge horizontal untuk judul "NILAI RAPORT SEMESTER X"
            $sheetSem->mergeCells('E1:' . $lastCol . '1');
            $sheetSem->setCellValue('E1', 'NILAI RAPORT SEMESTER ' . $sem);
            
            // BARIS 2: Header kolom untuk mata pelajaran (mulai dari E2)
            $colIndex = 5; // E = 5
            foreach ($mapelList as $m) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheetSem->setCellValue($colLetter . '2', $m['nama_mapel']);
                $colIndex++;
            }
            // Kolom terakhir untuk RATA-RATA
            $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheetSem->setCellValue($lastColLetter . '2', 'RATA-RATA');
            
            // Styling untuk kolom info (A1:D2) - merged cells
            $infoStyle = $sheetSem->getStyle('A1:D2');
            $infoStyle->getFont()->setBold(true);
            $infoStyle->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $infoStyle->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $infoStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $infoStyle->getFill()->getStartColor()->setARGB('FFFFFF00'); // Yellow background
            
            // Styling untuk judul baris 1 (E1 merged)
            $titleStyle = $sheetSem->getStyle('E1:' . $lastCol . '1');
            $titleStyle->getFont()->setBold(true);
            $titleStyle->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $titleStyle->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $titleStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $titleStyle->getFill()->getStartColor()->setARGB('FFFFFF00'); // Yellow background

            // Styling untuk header mapel baris 2 (E2 sampai lastCol)
            $headerStyle = $sheetSem->getStyle('E2:' . $lastCol . '2');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $headerStyle->getFill()->getStartColor()->setARGB('FFFFFF00'); // Yellow background

            // Data siswa per semester
            $siswaNo = 1;
            $dataRowStart = 3; // Row 3 is first data row (karena row 1 & 2 untuk header)
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
                        $rowData[] = (int)round((float) $nilai);
                    } else {
                        $rowData[] = '';
                    }
                }

                // Hitung rata-rata jika ada nilai
                $rataRata = count($nilaiValues) > 0 ? round(array_sum($nilaiValues) / count($nilaiValues), 0) : '';
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

            // Hitung kolom terakhir untuk merge title
            $numMapel = count($mapelList);
            $totalCols = 4 + $numMapel; // 4 info cols + mapel (UAM tidak ada rata-rata)
            $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);
            
            // BARIS 1 & 2: Merge cells vertikal untuk kolom info siswa (A-D)
            $sheetUam->mergeCells('A1:A2');
            $sheetUam->setCellValue('A1', 'No');
            $sheetUam->mergeCells('B1:B2');
            $sheetUam->setCellValue('B1', 'NISN');
            $sheetUam->mergeCells('C1:C2');
            $sheetUam->setCellValue('C1', 'NIS');
            $sheetUam->mergeCells('D1:D2');
            $sheetUam->setCellValue('D1', 'Nama Lengkap');
            
            // BARIS 1: Merge horizontal untuk judul "NILAI UAM (AKHIR)"
            $sheetUam->mergeCells('E1:' . $lastCol . '1');
            $sheetUam->setCellValue('E1', 'NILAI UAM (AKHIR)');
            
            // BARIS 2: Header kolom untuk mata pelajaran (mulai dari E2)
            $colIndex = 5; // E = 5
            foreach ($mapelList as $m) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheetUam->setCellValue($colLetter . '2', $m['nama_mapel']);
                $colIndex++;
            }
            
            // Styling untuk kolom info (A1:D2) - merged cells
            $infoStyle = $sheetUam->getStyle('A1:D2');
            $infoStyle->getFont()->setBold(true);
            $infoStyle->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $infoStyle->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $infoStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $infoStyle->getFill()->getStartColor()->setARGB('FFFFFF00'); // Yellow background
            
            // Styling untuk judul baris 1 (E1 merged)
            $titleStyle = $sheetUam->getStyle('E1:' . $lastCol . '1');
            $titleStyle->getFont()->setBold(true);
            $titleStyle->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $titleStyle->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $titleStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $titleStyle->getFill()->getStartColor()->setARGB('FFFFFF00'); // Yellow background

            // Styling untuk header mapel baris 2 (E2 sampai lastCol)
            $headerStyle = $sheetUam->getStyle('E2:' . $lastCol . '2');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $headerStyle->getFill()->getStartColor()->setARGB('FFFFFF00'); // Yellow background

            // Data UAM untuk siswa angkatan
            $siswaNo = 1;
            $dataRowStart = 3; // Row 3 is first data row (karena row 1 & 2 untuk header)
            foreach ($angkatanSiswa as $siswa) {
                $rowData = [$siswaNo++, $siswa['nisn'], $siswa['nis'], $siswa['nama']];

                // Ambil nilai UAM siswa
                $stUam = db()->prepare("SELECT mapel_id, nilai_angka FROM nilai_uam WHERE nisn=:nisn");
                $stUam->execute(['nisn' => $siswa['nisn']]);
                $nilaiUam = $stUam->fetchAll(\PDO::FETCH_KEY_PAIR);

                // Isi nilai UAM per mapel
                foreach ($mapelList as $m) {
                    $nilai = $nilaiUam[$m['id']] ?? '';
                    $rowData[] = $nilai !== '' ? (int)round((float) $nilai) : '';
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

            // Hitung kolom terakhir untuk merge title
            $numMapel = count($mapelList);
            $totalCols = 4 + $numMapel + 1; // 4 info cols + mapel + rata-rata ijazah
            $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);
            
            // BARIS 1 & 2: Merge cells vertikal untuk kolom info siswa (A-D)
            $sheetIjazah->mergeCells('A1:A2');
            $sheetIjazah->setCellValue('A1', 'No');
            $sheetIjazah->mergeCells('B1:B2');
            $sheetIjazah->setCellValue('B1', 'NISN');
            $sheetIjazah->mergeCells('C1:C2');
            $sheetIjazah->setCellValue('C1', 'NIS');
            $sheetIjazah->mergeCells('D1:D2');
            $sheetIjazah->setCellValue('D1', 'Nama Lengkap');
            
            // BARIS 1: Merge horizontal untuk judul "NILAI IJAZAH"
            $sheetIjazah->mergeCells('E1:' . $lastCol . '1');
            $sheetIjazah->setCellValue('E1', 'NILAI IJAZAH');
            
            // BARIS 2: Header kolom untuk mata pelajaran (mulai dari E2)
            $colIndex = 5; // E = 5
            foreach ($mapelList as $m) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheetIjazah->setCellValue($colLetter . '2', $m['nama_mapel']);
                $colIndex++;
            }
            // Kolom terakhir untuk RATA-RATA IJAZAH
            $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheetIjazah->setCellValue($lastColLetter . '2', 'RATA-RATA IJAZAH');
            
            // Styling untuk kolom info (A1:D2) - merged cells
            $infoStyle = $sheetIjazah->getStyle('A1:D2');
            $infoStyle->getFont()->setBold(true);
            $infoStyle->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $infoStyle->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $infoStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $infoStyle->getFill()->getStartColor()->setARGB('FFFFFF00'); // Yellow background
            
            // Styling untuk judul baris 1 (E1 merged)
            $titleStyle = $sheetIjazah->getStyle('E1:' . $lastCol . '1');
            $titleStyle->getFont()->setBold(true);
            $titleStyle->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $titleStyle->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $titleStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $titleStyle->getFill()->getStartColor()->setARGB('FFFFFF00'); // Yellow background

            // Styling untuk header mapel baris 2 (E2 sampai lastCol)
            $headerStyle = $sheetIjazah->getStyle('E2:' . $lastCol . '2');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $headerStyle->getFill()->getStartColor()->setARGB('FFFFFF00'); // Yellow background

            // Data Nilai Ijazah untuk siswa angkatan
            // Formula: (rata-rata rapor semester 1-5 * 0.6) + (nilai UAM * 0.4)
            $siswaNo = 1;
            $dataRowStart = 3; // Row 3 is first data row (karena row 1 & 2 untuk header)
            foreach ($angkatanSiswa as $siswa) {
                $rowData = [$siswaNo++, $siswa['nisn'], $siswa['nis'], $siswa['nama']];

                // Ambil rata-rata nilai rapor semester 1-5 per mapel
                $stRataRapor = db()->prepare("SELECT mapel_id, AVG(nilai_angka) AS rata_rapor FROM nilai_rapor WHERE nisn=:nisn AND semester BETWEEN 1 AND 5 AND tahun_ajaran=:ta GROUP BY mapel_id");
                $stRataRapor->execute(['nisn' => $siswa['nisn'], 'ta' => $tahunAjaranAktif]);
                $rataRaporByMapel = $stRataRapor->fetchAll(\PDO::FETCH_KEY_PAIR);

                // Ambil nilai UAM siswa
                $stUam = db()->prepare("SELECT mapel_id, nilai_angka FROM nilai_uam WHERE nisn=:nisn");
                $stUam->execute(['nisn' => $siswa['nisn']]);
                $nilaiUam = $stUam->fetchAll(\PDO::FETCH_KEY_PAIR);

                // Hitung nilai ijazah per mapel
                $nilaiIjazahValues = [];
                foreach ($mapelList as $m) {
                    $rataRapor = $rataRaporByMapel[$m['id']] ?? null;
                    $uam = $nilaiUam[$m['id']] ?? null;

                    if ($rataRapor !== null && $uam !== null) {
                        $nilaiIjazah = round(hitung_nilai_ijazah((float) $rataRapor, (float) $uam), 0);
                        $nilaiIjazahValues[] = $nilaiIjazah;
                        $rowData[] = (int)$nilaiIjazah;
                    } else {
                        $rowData[] = '';
                    }
                }

                // Hitung rata-rata ijazah
                $rataIjazah = count($nilaiIjazahValues) > 0 ? round(array_sum($nilaiIjazahValues) / count($nilaiIjazahValues), 0) : '';
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

    if ($action === 'transkrip' || $action === 'bulk_transkrip') {
        if (!class_exists(Dompdf::class)) {
            set_flash('error', 'Dompdf belum terpasang.');
            redirect('index.php?page=ekspor-cetak');
        }

        // Ambil data ttd dari modal
        $titimangsa = $_POST['titimangsa'] ?? date('d F Y');
        $namaKepsek = $_POST['nama_kepsek'] ?? 'Kepala Madrasah';
        $nipKepsek = $_POST['nip_kepsek'] ?? '';

        // Tentukan NISN yang akan dicetak
        $nisnList = [];
        if ($action === 'bulk_transkrip') {
            $angkatanFilter = (int) ($_POST['angkatan'] ?? 0);
            $stmtBulk = db()->prepare('SELECT nisn FROM alumni WHERE angkatan_lulus = :angkatan ORDER BY nama');
            $stmtBulk->execute(['angkatan' => $angkatanFilter]);
            $nisnList = array_column($stmtBulk->fetchAll(), 'nisn');
        } else {
            $nisnList = [trim($_POST['nisn'] ?? '')];
        }

        if (empty($nisnList)) {
            set_flash('error', 'Tidak ada data alumni untuk dicetak.');
            redirect('index.php?page=ekspor-cetak');
        }

        $dompdf = new Dompdf();
        $dompdf->set_option('isHtml5ParserEnabled', true);
        $dompdf->set_option('isRemoteEnabled', true);
        
        $allHtml = '';
        foreach ($nisnList as $idx => $nisn) {
            $stmt = db()->prepare('SELECT a.nisn, a.nama, a.angkatan_lulus, a.tanggal_kelulusan, a.nomor_surat, a.data_ijazah_json, a.verification_token,
                s.tempat_lahir, s.tgl_lahir
                FROM alumni a 
                LEFT JOIN siswa s ON s.nisn = a.nisn
                WHERE a.nisn=:nisn LIMIT 1');
            $stmt->execute(['nisn' => $nisn]);
            $alumni = $stmt->fetch();

            if (!$alumni) {
                continue;
            }

            $detail = json_decode($alumni['data_ijazah_json'], true) ?: [];
            
            // Generate QR Code URL
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            $verifyUrl = $baseUrl . '/verify.php?token=' . urlencode($alumni['verification_token']);
            $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . urlencode($verifyUrl);

            // Format tanggal kelulusan Indonesia
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

            $rows = '';
            foreach ($detail as $d) {
                $rows .= '<tr>
                    <td style="padding: 8px; border: 1px solid #333;">' . htmlspecialchars($d['mapel']) . '</td>
                    <td style="padding: 8px; border: 1px solid #333; text-align: center;">' . htmlspecialchars((string) $d['rata_rapor']) . '</td>
                    <td style="padding: 8px; border: 1px solid #333; text-align: center;">' . htmlspecialchars((string) $d['nilai_uam']) . '</td>
                    <td style="padding: 8px; border: 1px solid #333; text-align: center;"><strong>' . htmlspecialchars((string) $d['nilai_ijazah']) . '</strong></td>
                    <td style="padding: 8px; border: 1px solid #333;">' . htmlspecialchars($d['terbilang']) . '</td>
                </tr>';
            }

            $pageBreak = ($idx < count($nisnList) - 1) ? '<div style="page-break-after: always;"></div>' : '';

            $allHtml .= '
            <div style="font-family: Arial, sans-serif; font-size: 12px; padding: 20px;">
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                    <tr>
                        <td style="width: 80px; text-align: center; vertical-align: top;">
                            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==" style="width: 70px; height: 70px;">
                        </td>
                        <td style="text-align: center; vertical-align: top;">
                            <h2 style="margin: 0; font-size: 16px; font-weight: bold;">KEMENTERIAN AGAMA REPUBLIK INDONESIA</h2>
                            <h3 style="margin: 5px 0; font-size: 14px; font-weight: bold;">MADRASAH TSANAWIYAH NEGERI 11 MAJALENGKA</h3>
                            <p style="margin: 5px 0; font-size: 11px;">Jl. Raya Majalengka, Kabupaten Majalengka, Jawa Barat 45418</p>
                            <p style="margin: 5px 0; font-size: 11px;">Telp: (0233) 8319182 | Email: mtsn11majalengka@gmail.com</p>
                        </td>
                        <td style="width: 80px;"></td>
                    </tr>
                </table>
                
                <hr style="border: 2px solid #000; margin: 10px 0;">
                
                <h3 style="text-align: center; margin: 20px 0; font-size: 14px; font-weight: bold; text-decoration: underline;">TRANSKRIP NILAI IJAZAH</h3>
                
                <table style="width: 100%; margin-bottom: 20px;">
                    <tr>
                        <td style="width: 40%;">Nomor Transkrip</td>
                        <td style="width: 5%;">:</td>
                        <td>' . htmlspecialchars($alumni['nomor_surat'] ?? '-') . '</td>
                    </tr>
                    <tr>
                        <td>Tahun Ajaran</td>
                        <td>:</td>
                        <td>' . htmlspecialchars($tahunAjaran) . '</td>
                    </tr>
                </table>

                <table style="width: 100%; margin-bottom: 20px;">
                    <tr>
                        <td style="width: 40%;">Satuan Pendidikan</td>
                        <td style="width: 5%;">:</td>
                        <td>MTsN 11 MAJALENGKA</td>
                    </tr>
                    <tr>
                        <td>Nomor Pokok Sekolah Nasional</td>
                        <td>:</td>
                        <td>20278893</td>
                    </tr>
                    <tr>
                        <td>Nama Lengkap</td>
                        <td>:</td>
                        <td>' . htmlspecialchars(strtoupper($alumni['nama'])) . '</td>
                    </tr>
                    <tr>
                        <td>Tempat dan Tanggal Lahir</td>
                        <td>:</td>
                        <td>' . htmlspecialchars($tempatTglLahir) . '</td>
                    </tr>
                    <tr>
                        <td>Nomor Induk Siswa Nasional</td>
                        <td>:</td>
                        <td>' . htmlspecialchars($alumni['nisn']) . '</td>
                    </tr>
                    <tr>
                        <td>Tanggal Kelulusan</td>
                        <td>:</td>
                        <td>' . htmlspecialchars($tglKelulusanFormat) . '</td>
                    </tr>
                </table>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #333;">
                    <thead>
                        <tr style="background-color: #f0f0f0;">
                            <th style="padding: 10px; border: 1px solid #333; text-align: left;">Mata Pelajaran</th>
                            <th style="padding: 10px; border: 1px solid #333; text-align: center; width: 10%;">Rata Rapor</th>
                            <th style="padding: 10px; border: 1px solid #333; text-align: center; width: 10%;">Nilai UAM</th>
                            <th style="padding: 10px; border: 1px solid #333; text-align: center; width: 10%;">Nilai Ijazah</th>
                            <th style="padding: 10px; border: 1px solid #333; width: 25%;">Terbilang</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $rows . '
                    </tbody>
                </table>

                <table style="width: 100%;">
                    <tr>
                        <td style="width: 50%; vertical-align: top; text-align: center;">
                            <img src="' . $qrCodeUrl . '" style="width: 100px; height: 100px; margin-top: 10px;"><br>
                            <small style="font-size: 9px;">Scan untuk verifikasi</small>
                        </td>
                        <td style="width: 50%; vertical-align: top; text-align: center;">
                            <p style="margin: 0;">Majalengka, ' . htmlspecialchars($titimangsa) . '</p>
                            <p style="margin: 5px 0;">Kepala Madrasah,</p>
                            <br><br><br>
                            <p style="margin: 0; font-weight: bold; text-decoration: underline;">' . htmlspecialchars($namaKepsek) . '</p>
                            <p style="margin: 0;">NIP. ' . htmlspecialchars($nipKepsek) . '</p>
                        </td>
                    </tr>
                </table>
            </div>
            ' . $pageBreak;
        }

        $dompdf->loadHtml($allHtml);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $filename = $action === 'bulk_transkrip' ? 'transkrip_bulk_' . $angkatanFilter . '.pdf' : 'transkrip_' . $nisnList[0] . '.pdf';
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }
}

$alumniList = db()->query('SELECT nisn, nama, angkatan_lulus FROM alumni ORDER BY angkatan_lulus DESC, nama')->fetchAll();

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
                    Sistem mengekspor nilai dari semester 1 sampai semester yang dipilih.
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
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-transkrip-individu" data-bs-toggle="tab" data-bs-target="#individu-tab" type="button">Cetak Individu</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-transkrip-bulk" data-bs-toggle="tab" data-bs-target="#bulk-tab" type="button">Cetak Bulk per Angkatan</button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Tab Individu -->
            <div class="tab-pane fade show active" id="individu-tab">
                <?php if (empty($alumniList)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Belum ada data alumni. Lakukan proses migrasi siswa ke alumni terlebih dahulu di menu <strong>Kelulusan</strong>.
                    </div>
                <?php else: ?>
                <form method="post" id="formTranskrip" class="row g-3 align-items-end">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="transkrip">
                    <input type="hidden" name="titimangsa" id="titimangsa_value">
                    <input type="hidden" name="nama_kepsek" id="nama_kepsek_value">
                    <input type="hidden" name="nip_kepsek" id="nip_kepsek_value">
                    
                    <div class="col-md-8">
                        <label class="form-label">Pilih Alumni (Ketik Nama atau NISN)</label>
                        <select name="nisn" id="selectAlumni" class="form-select" required>
                            <option value="">-- ketik untuk mencari --</option>
                            <?php foreach ($alumniList as $a): ?>
                                <option value="<?= e($a['nisn']) ?>"><?= e($a['nama']) ?> (<?= e($a['nisn']) ?>) - Angkatan <?= e((string) $a['angkatan_lulus']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-outline-success w-100" onclick="showModalTTD('formTranskrip')">
                            <i class="bi bi-download"></i> Download PDF
                        </button>
                    </div>
                </form>
                <?php endif; ?>

            <!-- Tab Bulk -->
            <div class="tab-pane fade" id="bulk-tab">
                <?php if (empty($alumniList)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Belum ada data alumni. Lakukan proses migrasi siswa ke alumni terlebih dahulu di menu <strong>Kelulusan</strong>.
                    </div>
                <?php else: ?>
                <form method="post" id="formBulkTranskrip" class="row g-3 align-items-end">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="bulk_transkrip">
                    <input type="hidden" name="titimangsa" id="titimangsa_bulk">
                    <input type="hidden" name="nama_kepsek" id="nama_kepsek_bulk">
                    <input type="hidden" name="nip_kepsek" id="nip_kepsek_bulk">
                    
                    <div class="col-md-8">
                        <label class="form-label">Pilih Angkatan</label>
                        <select name="angkatan" class="form-select" required>
                            <option value="">-- pilih angkatan --</option>
                            <?php
                            $angkatanStmt = db()->query('SELECT DISTINCT angkatan_lulus FROM alumni ORDER BY angkatan_lulus DESC');
                            while ($row = $angkatanStmt->fetch()) {
                                echo '<option value="' . e((string) $row['angkatan_lulus']) . '">' . e((string) $row['angkatan_lulus']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-outline-primary w-100" onclick="showModalTTD('formBulkTranskrip')">
                            <i class="bi bi-printer"></i> Cetak Semua
                        </button>
                    </div>
                </form>
                <div class="alert alert-info mt-3 mb-0">
                    <i class="bi bi-info-circle"></i> Cetak bulk akan menghasilkan satu file PDF berisi semua transkrip alumni pada angkatan yang dipilih.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal TTD -->
<div class="modal fade" id="modalTTD" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Data Penandatangan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Titimangsa (Tanggal)</label>
                    <input type="date" id="input_titimangsa" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    <small class="form-text text-muted">Contoh: 15 Juni 2024</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Kepala Madrasah</label>
                    <input type="text" id="input_nama_kepsek" class="form-control" placeholder="Nama Lengkap" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">NIP Kepala Madrasah</label>
                    <input type="text" id="input_nip_kepsek" class="form-control" placeholder="NIP" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" onclick="submitWithTTD()">Cetak PDF</button>
            </div>
        </div>
    </div>
</div>

<script>
let targetFormId = '';

function showModalTTD(formId) {
    targetFormId = formId;
    
    // Validasi pilihan sebelum buka modal
    if (targetFormId === 'formTranskrip') {
        const nisn = document.getElementById('selectAlumni').value;
        if (!nisn) {
            alert('Silakan pilih alumni terlebih dahulu!');
            return;
        }
    } else if (targetFormId === 'formBulkTranskrip') {
        const angkatan = document.querySelector('select[name="angkatan"]').value;
        if (!angkatan) {
            alert('Silakan pilih angkatan terlebih dahulu!');
            return;
        }
    }
    
    const modal = new bootstrap.Modal(document.getElementById('modalTTD'));
    modal.show();
}

function submitWithTTD() {
    const tgl = document.getElementById('input_titimangsa').value;
    const nama = document.getElementById('input_nama_kepsek').value;
    const nip = document.getElementById('input_nip_kepsek').value;

    if (!tgl || !nama || !nip) {
        alert('Semua field wajib diisi!');
        return;
    }

    // Format tanggal ke Indonesia
    const bulanIndo = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const parts = tgl.split('-');
    const titimangsa = parseInt(parts[2]) + ' ' + bulanIndo[parseInt(parts[1])] + ' ' + parts[0];

    const form = document.getElementById(targetFormId);
    
    if (targetFormId === 'formTranskrip') {
        document.getElementById('titimangsa_value').value = titimangsa;
        document.getElementById('nama_kepsek_value').value = nama;
        document.getElementById('nip_kepsek_value').value = nip;
    } else {
        document.getElementById('titimangsa_bulk').value = titimangsa;
        document.getElementById('nama_kepsek_bulk').value = nama;
        document.getElementById('nip_kepsek_bulk').value = nip;
    }

    form.submit();
}

// Initialize search on alumni select
document.addEventListener('DOMContentLoaded', function() {
    const selectAlumni = document.getElementById('selectAlumni');
    if (selectAlumni) {
        // Simple search filter
        selectAlumni.addEventListener('focus', function() {
            this.size = 10;
        });
        selectAlumni.addEventListener('blur', function() {
            this.size = 1;
        });
        selectAlumni.addEventListener('change', function() {
            this.size = 1;
        });
    }
});
</script>
<?php require dirname(__DIR__) . '/partials/footer.php';
