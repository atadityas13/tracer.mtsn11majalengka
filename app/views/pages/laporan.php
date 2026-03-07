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

        // Ambil data ttd dari modal (hari selalu 2 digit, contoh: 01 Januari 2026)
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
        
        // Ambil nomor urut surat awal (tidak dibatasi digit)
        $nomorUrutAwal = (int) ($_POST['nomor_urut'] ?? 1);
        
        // Ekstrak bulan dan tahun dari titimangsa untuk nomor surat
        // Format titimangsa: "01 Januari 2026" atau "1 Januari 2026"
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
            set_flash('error', 'Tidak ada data alumni untuk dicetak.');
            redirect('index.php?page=ekspor-cetak');
        }

        $dompdf = new Dompdf();
        $dompdf->set_option('isHtml5ParserEnabled', true);
        $dompdf->set_option('isRemoteEnabled', true);

        // Load logo once instead of per-student to reduce IO overhead.
        $logoDataUri = '';
        $logoPath = dirname(__DIR__, 3) . '/public/assets/logo-kemenag.png';
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
        $verifyPath = $scriptDir . '/verify.php';
        $updateNomorSuratStmt = $pdo->prepare('UPDATE alumni SET nomor_surat = :nomor_surat WHERE nisn = :nisn');
        $pdo->exec('CREATE TABLE IF NOT EXISTS alumni_verifikasi_meta (
            verification_token VARCHAR(64) PRIMARY KEY,
            nomor_surat VARCHAR(120) NULL,
            titimangsa VARCHAR(100) NULL,
            ttd_nama VARCHAR(150) NULL,
            ttd_nip VARCHAR(60) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )');
        $upsertVerifikasiMetaStmt = $pdo->prepare('INSERT INTO alumni_verifikasi_meta (verification_token, nomor_surat, titimangsa, ttd_nama, ttd_nip)
            VALUES (:verification_token, :nomor_surat, :titimangsa, :ttd_nama, :ttd_nip)
            ON DUPLICATE KEY UPDATE
                nomor_surat = VALUES(nomor_surat),
                titimangsa = VALUES(titimangsa),
                ttd_nama = VALUES(ttd_nama),
                ttd_nip = VALUES(ttd_nip)');
        $qrCacheDir = dirname(__DIR__, 3) . '/storage/cache/qr';
        if (!is_dir($qrCacheDir)) {
            @mkdir($qrCacheDir, 0775, true);
        }

        $normalizeMapel = static function (string $text): string {
            $text = strtolower($text);
            return preg_replace('/[^a-z0-9]+/', '', $text) ?? '';
        };
        $normalizeTerbilang = static function (string $text): string {
            $text = trim((string) preg_replace('/\s+/', ' ', $text));
            $text = (string) preg_replace('/\s+koma\s*$/i', '', $text);
            return trim($text);
        };
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
            ['type' => 'item', 'no' => '', 'prefix' => 'A.', 'label' => 'Al Qur\'an Hadis', 'keywords' => ['alquranhadis', 'alquranhadis', 'quranhadis']],
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
        $startedTransaction = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
        foreach ($alumniRows as $idx => $alumni) {
            if ($firstAlumniName === '') {
                $firstAlumniName = (string) ($alumni['nama'] ?? '');
            }

            $detail = json_decode($alumni['data_ijazah_json'], true) ?: [];
            
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

            $nism = '-';
            $nisSiswa = trim((string) ($alumni['nis'] ?? ''));
            if ($nisSiswa !== '') {
                $nism = '121132100013' . $nisSiswa;
            }
            
            // Tahun Ajaran berdasarkan angkatan lulus
            $tahunAjaran = '';
            if ($alumni['angkatan_lulus']) {
                $tahunLulus = (int)$alumni['angkatan_lulus'];
                $tahunAjaran = ($tahunLulus - 1) . '/' . $tahunLulus;
            }
            
            // Generate nomor surat dengan format: {nomor}/Mts.10.89/PP.00.5/{bulan}/{tahun}
            // Untuk bulk, nomor auto-increment per siswa
            // Bulan dan tahun diambil dari titimangsa, bukan dari tanggal kelulusan
            $nomorUrutSekarang = $nomorUrutAwal + $idx;
            $nomorSuratLengkap = $nomorUrutSekarang . '/Mts.10.89/PP.00.5/' . $bulanSurat . '/' . $tahunSurat;
            $updateNomorSuratStmt->execute([
                'nomor_surat' => $nomorSuratLengkap,
                'nisn' => $alumni['nisn'],
            ]);
            $upsertVerifikasiMetaStmt->execute([
                'verification_token' => $alumni['verification_token'],
                'nomor_surat' => $nomorSuratLengkap,
                'titimangsa' => (string) $titimangsa,
                'ttd_nama' => (string) $namaKepsek,
                'ttd_nip' => (string) $nipKepsek,
            ]);

            // Generate token-only verification URL (no mutable metadata in query string).
            $verifyUrl = $baseUrl . $verifyPath . '?token=' . urlencode($alumni['verification_token']);
            $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . urlencode($verifyUrl);
            $qrCodeSrc = $qrCodeUrl;
            $qrBinary = false;
            $qrCacheFile = $qrCacheDir . '/' . sha1((string) $alumni['verification_token']) . '.png';
            if (is_file($qrCacheFile)) {
                $qrBinary = @file_get_contents($qrCacheFile);
            }
            if ($qrBinary === false) {
                $qrContext = stream_context_create([
                    'http' => ['timeout' => 2],
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                ]);
                $qrBinary = @file_get_contents($qrCodeUrl, false, $qrContext);
                if ($qrBinary !== false) {
                    @file_put_contents($qrCacheFile, $qrBinary);
                }
            }
            if ($qrBinary !== false) {
                $qrCodeSrc = 'data:image/png;base64,' . base64_encode($qrBinary);
            }

            $nilaiByMapel = [];
            foreach ($detail as $d) {
                $mapelNama = trim((string) ($d['mapel'] ?? ''));
                if ($mapelNama === '') {
                    continue;
                }
                $rataRapor = (float) ($d['rata_rapor'] ?? 0);
                $nilaiUam = (float) ($d['nilai_uam'] ?? 0);
                $nilaiIjazahRaw = (float) hitung_nilai_ijazah($rataRapor, $nilaiUam);
                $nilaiIjazah = (int) round($nilaiIjazahRaw);

                $nilaiByMapel[] = [
                    'norm' => $normalizeMapel($mapelNama),
                    'mapel' => $mapelNama,
                    'rata_rapor' => (int) round($rataRapor),
                    'nilai_uam' => (int) round($nilaiUam),
                    'nilai_ijazah' => $nilaiIjazah,
                    'terbilang' => $normalizeTerbilang(terbilang_nilai($nilaiIjazah)),
                ];
            }

            $rows = '';
            $sumRapor = 0.0;
            $sumUam = 0.0;
            $sumIjazah = 0.0;
            $countNilai = 0;

            foreach ($layoutRows as $layoutRow) {
                if ($layoutRow['type'] === 'group') {
                    $rows .= '<tr><td colspan="6" style="padding: 4px 6px; border: 1px solid #000;">' . htmlspecialchars($layoutRow['label']) . '</td></tr>';
                    continue;
                }

                if ($layoutRow['type'] === 'parent') {
                    $rows .= '<tr>
                        <td style="padding: 4px 6px; border: 1px solid #000; text-align: center; width: 30px;">' . htmlspecialchars($layoutRow['no']) . '</td>
                        <td style="padding: 4px 6px; border: 1px solid #000;">' . htmlspecialchars($layoutRow['label']) . '</td>
                        <td style="padding: 4px 6px; border: 1px solid #000;"></td>
                        <td style="padding: 4px 6px; border: 1px solid #000;"></td>
                        <td style="padding: 4px 6px; border: 1px solid #000;"></td>
                        <td style="padding: 4px 6px; border: 1px solid #000;"></td>
                    </tr>';
                    continue;
                }

                $nilaiMapel = $findMapel($nilaiByMapel, $layoutRow['keywords']);
                $mapelLabel = trim(($layoutRow['prefix'] !== '' ? $layoutRow['prefix'] . ' ' : '') . $layoutRow['label']);
                $noCell = $layoutRow['no'] === '' ? '&nbsp;' : htmlspecialchars($layoutRow['no']);
                $raporCell = '';
                $uamCell = '';
                $ijazahCell = '';
                $terbilangCell = '';

                if (is_array($nilaiMapel)) {
                    $raporCell = (string) $nilaiMapel['rata_rapor'];
                    $uamCell = (string) $nilaiMapel['nilai_uam'];
                    $ijazahCell = (string) $nilaiMapel['nilai_ijazah'];
                    $terbilangCell = (string) $nilaiMapel['terbilang'];
                    $sumRapor += (float) $nilaiMapel['rata_rapor'];
                    $sumUam += (float) $nilaiMapel['nilai_uam'];
                    $sumIjazah += (float) $nilaiMapel['nilai_ijazah'];
                    $countNilai++;
                }

                $rows .= '<tr>
                    <td style="padding: 4px 6px; border: 1px solid #000; text-align: center; width: 30px;">' . $noCell . '</td>
                    <td style="padding: 4px 6px; border: 1px solid #000;">' . htmlspecialchars($mapelLabel) . '</td>
                    <td style="padding: 4px 6px; border: 1px solid #000; text-align: center; width: 60px;">' . htmlspecialchars($raporCell) . '</td>
                    <td style="padding: 4px 6px; border: 1px solid #000; text-align: center; width: 60px;">' . htmlspecialchars($uamCell) . '</td>
                    <td style="padding: 4px 6px; border: 1px solid #000; text-align: center; width: 60px; font-weight: bold;">' . htmlspecialchars($ijazahCell) . '</td>
                    <td style="padding: 4px 6px; border: 1px solid #000; font-style: italic;">' . htmlspecialchars($terbilangCell) . '</td>
                </tr>';
            }

            $avgRapor = $countNilai > 0 ? $sumRapor / $countNilai : 0;
            $avgUam = $countNilai > 0 ? $sumUam / $countNilai : 0;
            $avgIjazah = $countNilai > 0 ? $sumIjazah / $countNilai : 0;
            $terbilangTotal = $normalizeTerbilang(terbilang_nilai($avgIjazah));

            $pageBreak = ($idx < count($nisnList) - 1) ? '<div style="page-break-after: always;"></div>' : '';

            $allHtml .= '
            <div style="font-family: Arial, sans-serif; font-size: 11px; padding: 10px 12px; color: #000;">
                <!-- Header -->
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                    <tr>
                        <td style="width: 96px; text-align: center; vertical-align: middle; padding-right: 4px;">
                            ' . ($logoDataUri !== ''
                                ? '<img src="' . $logoDataUri . '" style="width: 80px; height: 80px; object-fit: contain;">'
                                : '<div style="width: 100px; height: 80px; border: 1px solid #000; margin: 0 auto; font-size: 10px; line-height: 80px; text-align: center;">LOGO</div>') . '
                        </td>
                        <td style="text-align: center; vertical-align: middle; padding-right: 4px;">
                            <h2 style="margin: 0; font-size: 16px; font-weight: bold; font-family: Times New Roman, serif; letter-spacing: 1.8px;">KEMENTERIAN AGAMA REPUBLIK INDONESIA</h2>
                            <h1 style="margin: 2px 0; font-size: 25px; line-height: 1.05; font-weight: bold; font-family: Times New Roman, serif; letter-spacing: 2.5px;">MTsN 11 MAJALENGKA</h1>
                            <p style="margin: 3px 0 0 0; font-size: 11px; font-style: italic; font-family: Times New Roman, serif; letter-spacing: 1px;">Kp. Sindanghurip Desa Maniis Kec. Cingambul Kab. Majalengka, 45467.</p>
                            <p style="margin: 0; font-size: 11px; font-style: italic; font-family: Times New Roman, serif; letter-spacing: 1px;">Telp. (0233) 3600020  E-mail: mtsn11majalengka@gmail.com </p>
                        </td>
                    </tr>
                </table>
                
                <div style="height: 1px; border-top: 2px solid #000; border-bottom: 1px solid #000; margin: 6px 0 10px 0;"></div>
                
                <!-- Title -->
                <h2 style="text-align: center; margin: 2px 0 0 0; font-size: 16px; font-weight: bold; letter-spacing: 0.3px;">TRANSKRIP NILAI</h2>
                <p style="text-align: center; margin: 1px 0 12px 0; font-size: 14px; ">TAHUN AJARAN ' . htmlspecialchars($tahunAjaran) . '</p>
                
                <!-- Info Box (No Borders) -->
                <table style="width: 100%; margin-bottom: 10px; font-size: 12px;">
                    <tr>
                        <td style="width: 35%; padding: 2px 0;">Satuan Pendidikan</td>
                        <td style="width: 2%; padding: 2px 0;">:</td>
                        <td style="padding: 2px 0 2px 10px;">MTsN 11 MAJALENGKA</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0;">Nomor Pokok Sekolah Nasional</td>
                        <td style="padding: 2px 0;">:</td>
                        <td style="padding: 2px 0 2px 10px;">20278893</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0;">Nama Lengkap</td>
                        <td style="padding: 2px 0;">:</td>
                        <td style="padding: 2px 0 2px 10px;">' . htmlspecialchars(strtoupper($alumni['nama'])) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0;">Tempat dan Tanggal Lahir</td>
                        <td style="padding: 2px 0;">:</td>
                        <td style="padding: 2px 0 2px 10px;">' . htmlspecialchars($tempatTglLahir) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0;">Nomor Induk Siswa Nasional</td>
                        <td style="padding: 2px 0;">:</td>
                        <td style="padding: 2px 0 2px 10px;">' . htmlspecialchars($alumni['nisn']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0;">Nomor Induk Siswa Madrasah</td>
                        <td style="padding: 2px 0;">:</td>
                        <td style="padding: 2px 0 2px 10px;">' . htmlspecialchars($nism) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0;">Nomor Transkrip Nilai</td>
                        <td style="padding: 2px 0;">:</td>
                        <td style="padding: 2px 0 2px 10px;">' . htmlspecialchars($nomorSuratLengkap) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 0;">Tanggal Kelulusan</td>
                        <td style="padding: 2px 0;">:</td>
                        <td style="padding: 2px 0 2px 10px;">' . htmlspecialchars($tglKelulusanFormat) . '</td>
                    </tr>
                </table>
                
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; border: 1px solid #000; font-size: 10.5px;">
                    <thead>
                        <tr>
                            <th rowspan="2" style="padding: 4px; border: 1px solid #000; text-align: center; width: 30px;">No</th>
                            <th rowspan="2" style="padding: 4px; border: 1px solid #000; text-align: center;">Mata Pelajaran</th>
                            <th colspan="3" style="padding: 4px; border: 1px solid #000; text-align: center;">Nilai</th>
                            <th rowspan="2" style="padding: 4px; border: 1px solid #000; text-align: center; width: 170px;">Nilai Ijazah Terbilang</th>
                        </tr>
                        <tr>
                            <th style="padding: 4px; border: 1px solid #000; text-align: center; width: 72px;">Rapor<br>
                                <span style="font-size: 8px; font-weight: normal;">(Rata-rata Sem. 1-5)</span></th>
                            <th style="padding: 4px; border: 1px solid #000; text-align: center; width: 65px;">UAM<br>
                                <span style="font-size: 8px; font-weight: normal;">(Ujian Akhir Madrasah)</span></th>
                            <th style="padding: 3px 2px; border: 1px solid #000; text-align: center; width: 90px;">
                                Ijazah<br>
                                <span style="font-size: 8px; font-weight: normal;">(60% Rapor+40% UAM)</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $rows . '
                        <tr>
                            <td colspan="2" style="padding: 5px; border: 1px solid #000; text-align: center; font-weight: bold;">Rata-Rata</td>
                            <td style="padding: 5px; border: 1px solid #000; text-align: center; font-weight: bold;">' . number_format($avgRapor, 2, ',', '.') . '</td>
                            <td style="padding: 5px; border: 1px solid #000; text-align: center; font-weight: bold;">' . number_format($avgUam, 2, ',', '.') . '</td>
                            <td style="padding: 5px; border: 1px solid #000; text-align: center; font-weight: bold;">' . number_format($avgIjazah, 2, ',', '.') . '</td>
                            <td style="padding: 5px; border: 1px solid #000; font-style: italic; font-weight: bold;">' . htmlspecialchars($terbilangTotal) . '</td>
                        </tr>
                    </tbody>
                </table>

                <table style="width: 100%; margin-top: 8px;">
                    <tr>
                        <td style="width: 32%; vertical-align: top; text-align: left; padding-left: 8px;">
                            <img src="' . $qrCodeSrc . '" style="width: 90px; height: 90px;">
                        </td>
                        <td style="width: 68%; text-align: left; vertical-align: top; padding-top: 2px;">
                            <div style="width: 230px; margin-left: auto; margin-right: 20px;">
                                <p style="margin: 0; font-size: 12px;">Majalengka, ' . htmlspecialchars($titimangsa) . '</p>
                                <p style="margin: 1px 0 48px 0; font-size: 12px;">Kepala Madrasah</p>
                                <p style="margin: 0; font-weight: bold; font-size: 12px;">' . htmlspecialchars($namaKepsek) . '</p>
                                <p style="margin: 1px 0 0 0; font-size: 12px;">NIP. ' . htmlspecialchars($nipKepsek) . '</p>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            ' . $pageBreak;
        }
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }
        } catch (Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
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
        // Open PDF inline with explicit filename so browser viewer does not fallback to "index.php".
        $pdfBinary = $dompdf->output();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . strlen($pdfBinary));
        echo $pdfBinary;
        exit;
    }
}

$alumniList = db()->query('SELECT nisn, nama, angkatan_lulus FROM alumni ORDER BY angkatan_lulus DESC, nama')->fetchAll();
$angkatanList = db()->query("SELECT DISTINCT angkatan_lulus FROM alumni WHERE angkatan_lulus IS NOT NULL AND angkatan_lulus <> '' ORDER BY angkatan_lulus DESC")->fetchAll();

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
                <button class="nav-link" id="tab-transkrip-bulk" data-bs-toggle="tab" data-bs-target="#bulk-tab" type="button">Cetak per Angkatan</button>
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
                    <input type="hidden" name="nomor_urut" id="nomor_urut_value">
                    
                    <div class="col-md-8">
                        <label class="form-label">Pilih Alumni (Ketik Nama atau NISN)</label>
                        <input type="text" id="searchAlumni" class="form-control mb-2" placeholder="Cari nama atau NISN...">
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
            </div>

            <!-- Tab Bulk -->
            <div class="tab-pane fade" id="bulk-tab">
                <?php if (empty($alumniList)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Belum ada data alumni. Lakukan proses migrasi siswa ke alumni terlebih dahulu di menu <strong>Kelulusan</strong>.
                    </div>
                <?php elseif (empty($angkatanList)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> Data alumni sudah ada, tetapi belum ada <strong>angkatan lulus</strong> yang terisi. Lengkapi angkatan di menu <strong>Kelulusan</strong> agar fitur cetak per angkatan bisa digunakan.
                    </div>
                <?php else: ?>
                <form method="post" id="formBulkTranskrip" class="row g-3 align-items-end">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="bulk_transkrip">
                    <input type="hidden" name="titimangsa" id="titimangsa_bulk">
                    <input type="hidden" name="nama_kepsek" id="nama_kepsek_bulk">
                    <input type="hidden" name="nip_kepsek" id="nip_kepsek_bulk">
                    <input type="hidden" name="nomor_urut" id="nomor_urut_bulk">
                    
                    <div class="col-md-8">
                        <label class="form-label">Pilih Angkatan</label>
                        <select name="angkatan" class="form-select" required>
                            <option value="">-- pilih angkatan --</option>
                            <?php foreach ($angkatanList as $row): ?>
                                <option value="<?= e((string) $row['angkatan_lulus']) ?>"><?= e((string) $row['angkatan_lulus']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-outline-primary w-100" onclick="showModalTTD('formBulkTranskrip')">
                            <i class="bi bi-printer"></i> Cetak Semua
                        </button>
                    </div>
                </form>
                <div class="alert alert-info mt-3 mb-0">
                    <i class="bi bi-info-circle"></i> Cetak per angkatan akan menghasilkan satu file PDF berisi semua transkrip alumni pada angkatan yang dipilih.
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
                    <label class="form-label">Nomor Urut Surat</label>
                    <input type="number" id="input_nomor_urut" class="form-control" placeholder="1" min="1" required>
                    <div class="invalid-feedback">Nomor urut surat wajib diisi.</div>
                    <small class="form-text text-muted">Untuk cetak per angkatan, nomor surat akan bertambah otomatis sesuai jumlah siswa.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Titimangsa (Tanggal)</label>
                    <input type="date" id="input_titimangsa" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    <div class="invalid-feedback">Tanggal wajib diisi.</div>
                    <small class="form-text text-muted">Contoh: 02/05/2026</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Kepala Madrasah</label>
                    <input type="text" id="input_nama_kepsek" class="form-control" placeholder="Nama Lengkap" value="H. Jajang Gunawan, S.Ag., M.Pd.I." required>
                    <div class="invalid-feedback">Nama Kepala Madrasah wajib diisi.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">NIP Kepala Madrasah</label>
                    <input type="text" id="input_nip_kepsek" class="form-control" placeholder="NIP" value="196708251992031003" required>
                    <div class="invalid-feedback">NIP Kepala Madrasah wajib diisi.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" id="btnSubmitTTD" class="btn btn-primary" onclick="submitWithTTD()">Cetak PDF</button>
            </div>
        </div>
    </div>
</div>

<script>
let targetFormId = '';
let isGeneratingTranscript = false;

function showGenerateLoading(message) {
    if (typeof Swal === 'undefined') {
        return;
    }
    Swal.fire({
        title: 'Menyiapkan Transkrip',
        text: message,
        icon: 'info',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: function() {
            Swal.showLoading();
        }
    });
}

function showSwalWarning(message) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Perhatian',
            text: message,
            icon: 'warning',
            confirmButtonText: 'OK'
        });
        return;
    }
}

function showModalTTD(formId) {
    targetFormId = formId;
    
    // Validasi pilihan sebelum buka modal
    if (targetFormId === 'formTranskrip') {
        const nisn = document.getElementById('selectAlumni').value;
        if (!nisn) {
            showSwalWarning('Silakan pilih alumni terlebih dahulu!');
            return;
        }
    } else if (targetFormId === 'formBulkTranskrip') {
        const angkatan = document.querySelector('select[name="angkatan"]').value;
        if (!angkatan) {
            showSwalWarning('Silakan pilih angkatan terlebih dahulu!');
            return;
        }
    }
    
    const modal = new bootstrap.Modal(document.getElementById('modalTTD'));
    modal.show();
}

function submitWithTTD() {
    if (isGeneratingTranscript) {
        return;
    }

    const nomorUrutEl = document.getElementById('input_nomor_urut');
    const tglEl = document.getElementById('input_titimangsa');
    const namaEl = document.getElementById('input_nama_kepsek');
    const nipEl = document.getElementById('input_nip_kepsek');
    
    const nomorUrut = nomorUrutEl.value;
    const tgl = tglEl.value;
    const nama = namaEl.value;
    const nip = nipEl.value;

    // Clear previous validation
    [nomorUrutEl, tglEl, namaEl, nipEl].forEach(function(el) {
        el.classList.remove('is-invalid');
    });

    // Validate each field
    let hasError = false;
    if (!nomorUrut) {
        nomorUrutEl.classList.add('is-invalid');
        hasError = true;
    }
    if (!tgl) {
        tglEl.classList.add('is-invalid');
        hasError = true;
    }
    if (!nama.trim()) {
        namaEl.classList.add('is-invalid');
        hasError = true;
    }
    if (!nip.trim()) {
        nipEl.classList.add('is-invalid');
        hasError = true;
    }

    if (hasError) {
        return;
    }
    
    // Simpan ke localStorage untuk persistensi
    localStorage.setItem('kepsek_nama', nama);
    localStorage.setItem('kepsek_nip', nip);

    // Format tanggal ke Indonesia
    const bulanIndo = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const parts = tgl.split('-');
    const titimangsa = parseInt(parts[2]) + ' ' + bulanIndo[parseInt(parts[1])] + ' ' + parts[0];

    const form = document.getElementById(targetFormId);
    
    if (targetFormId === 'formTranskrip') {
        document.getElementById('nomor_urut_value').value = nomorUrut;
        document.getElementById('titimangsa_value').value = titimangsa;
        document.getElementById('nama_kepsek_value').value = nama;
        document.getElementById('nip_kepsek_value').value = nip;
    } else {
        document.getElementById('nomor_urut_bulk').value = nomorUrut;
        document.getElementById('titimangsa_bulk').value = titimangsa;
        document.getElementById('nama_kepsek_bulk').value = nama;
        document.getElementById('nip_kepsek_bulk').value = nip;
    }

    isGeneratingTranscript = true;
    const submitBtn = document.getElementById('btnSubmitTTD');
    if (submitBtn) {
        submitBtn.disabled = true;
    }

    const modalEl = document.getElementById('modalTTD');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) {
        modalInstance.hide();
    }

    const loadingText = targetFormId === 'formBulkTranskrip'
        ? 'Sistem sedang menyusun transkrip satu angkatan. Mohon tunggu...'
        : 'Sistem sedang menyiapkan transkrip siswa. Mohon tunggu...';
    showGenerateLoading(loadingText);

    setTimeout(function() {
        form.submit();
    }, 80);
}

// Initialize search on alumni select
document.addEventListener('DOMContentLoaded', function() {
    // Load prefilled dari localStorage jika ada
    const savedNama = localStorage.getItem('kepsek_nama');
    const savedNip = localStorage.getItem('kepsek_nip');
    if (savedNama) {
        document.getElementById('input_nama_kepsek').value = savedNama;
    }
    if (savedNip) {
        document.getElementById('input_nip_kepsek').value = savedNip;
    }
    
    // Clear validation on input
    const modalInputs = ['input_nomor_urut', 'input_titimangsa', 'input_nama_kepsek', 'input_nip_kepsek'];
    modalInputs.forEach(function(inputId) {
        const el = document.getElementById(inputId);
        if (el) {
            el.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
            el.addEventListener('change', function() {
                this.classList.remove('is-invalid');
            });
        }
    });
    
    const selectAlumni = document.getElementById('selectAlumni');
    const searchAlumni = document.getElementById('searchAlumni');
    if (selectAlumni && searchAlumni) {
        const defaultOptionText = '-- ketik untuk mencari --';
        const allOptions = Array.from(selectAlumni.options)
            .filter(function(opt) { return opt.value !== ''; })
            .map(function(opt) {
                return { value: opt.value, text: opt.text };
            });

        const renderOptions = function(keyword) {
            const selectedValue = selectAlumni.value;
            const q = keyword.trim().toLowerCase();

            selectAlumni.innerHTML = '';
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = defaultOptionText;
            selectAlumni.appendChild(defaultOption);

            const filtered = allOptions.filter(function(item) {
                return q === '' || item.text.toLowerCase().indexOf(q) !== -1 || item.value.toLowerCase().indexOf(q) !== -1;
            });

            filtered.forEach(function(item) {
                const option = document.createElement('option');
                option.value = item.value;
                option.textContent = item.text;
                if (item.value === selectedValue) {
                    option.selected = true;
                }
                selectAlumni.appendChild(option);
            });

            if (filtered.length === 0) {
                defaultOption.textContent = '-- tidak ada hasil --';
            }
        };

        searchAlumni.addEventListener('input', function() {
            renderOptions(this.value);
        });

        searchAlumni.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });

        renderOptions('');
    }
});
</script>
<?php require dirname(__DIR__) . '/partials/footer.php';
