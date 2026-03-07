# TRACER MTsN 11 Majalengka

> **Transkrip & Academic Ledger** — *Tracing Progress, Graduating Success.*

Aplikasi web TRACER berbasis **PHP Native + PDO**, **MySQL**, dan **CSS/JS murni** tanpa framework.

## Fitur Utama

- Login 2 level user: `admin` (Super Admin) dan `kurikulum`.
- Dashboard statistik siswa + status upload nilai.
- Master Data: user, mapel, siswa (termasuk status `Tidak Melanjutkan`).
- Olah Nilai import Excel (`PhpSpreadsheet`) berbasis NISN:
  - Semester aktif `GANJIL` memproses rapor siswa current semester `1,3,5`.
  - Semester aktif `GENAP` memproses rapor siswa current semester `2,4` dan import `UAM` untuk semester `5`.
- Kontrol Semester:
  - Set Tahun Ajaran Aktif.
  - Finalisasi semester (kunci nilai + naikkan `current_semester` siswa aktif).
- Kelulusan:
  - Migrasi siswa eligible ke tabel alumni.
  - Simpan data ijazah JSON (rumus 60% rapor + 40% UAM + terbilang).
- Laporan:
  - Leger kolektif Excel.
  - Transkrip nilai ijazah PDF (`dompdf`).

## Struktur Direktori

- `public/` front controller + assets.
- `app/config/` konfigurasi aplikasi dan database.
- `app/views/pages/` semua modul halaman.
- `database/schema.sql` skema dan seed data awal.

## Instalasi

1. Salin env contoh:
   - copy `.env.example` menjadi `.env` lalu isi koneksi DB.
2. Install dependency composer:
   - `composer install`
3. Buat database dan import schema:
   - jalankan isi file `database/schema.sql`.
4. Arahkan document root web server ke folder `public`.

## Deploy di cPanel + phpMyAdmin

1. Upload source code ke hosting (ZIP lalu Extract di File Manager).
2. Buat database dan user MySQL di menu **MySQL Databases** cPanel.
3. Buka **phpMyAdmin** lalu import file `database/schema.sql`.
4. Buat file `.env` (copy dari `.env.example`) dan isi kredensial DB hosting.
5. Pastikan domain/subdomain `e-leger.mtsn11majalengka.sch.id` mengarah ke folder `public`.
  - Jika tidak bisa mengubah document root, pindahkan isi folder `public` ke `public_html` dan sesuaikan path bootstrap di `index.php`.
6. Jalankan `composer install` (Terminal cPanel/SSH). Jika tidak ada akses composer, build `vendor` di lokal lalu upload folder `vendor`.

## Update untuk Database Existing

Jika database sudah terlanjur berjalan sebelum update ini, jalankan SQL berikut agar NIS unik:

```sql
ALTER TABLE siswa ADD UNIQUE KEY uniq_siswa_nis (nis);
```

## Akun Awal

- Username: `superadmin`
- Password: `password`

## Catatan Keamanan

- Semua query menggunakan PDO prepared statements.
- Password disimpan dalam bentuk hash (`password_hash`).
- Seluruh form POST menggunakan proteksi CSRF token.
- Validasi nilai impor rapor/UAM dibatasi pada rentang `70-100`.
- Validasi duplikasi NISN/NIS diterapkan saat tambah siswa.

## Tips Aman Saat Git Pull di cPanel

- Jangan edit file tracked seperti `app/config/database.php` langsung di server.
- Simpan konfigurasi sensitif di file `.env`.
- Jika terlanjur mengubah file tracked di server, kembalikan dulu sebelum pull:

```bash
git checkout -- app/config/database.php
git pull
```

## Dokumentasi GitHub

- Panduan kontribusi: [CONTRIBUTING.md](CONTRIBUTING.md)
- Kebijakan keamanan: [SECURITY.md](SECURITY.md)
- Kode etik kontributor: [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)
- Riwayat perubahan: [CHANGELOG.md](CHANGELOG.md)
- Lisensi proyek: [LICENSE.md](LICENSE.md)

## Template Kolaborasi GitHub

- Template bug report: [.github/ISSUE_TEMPLATE/bug_report.md](.github/ISSUE_TEMPLATE/bug_report.md)
- Template feature request: [.github/ISSUE_TEMPLATE/feature_request.md](.github/ISSUE_TEMPLATE/feature_request.md)
- Template pull request: [.github/pull_request_template.md](.github/pull_request_template.md)

## Lisensi

Project ini menggunakan lisensi proprietary internal sekolah. Detail lengkap dapat dilihat di [LICENSE.md](LICENSE.md).
