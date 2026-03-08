# TRACER MTsN 11 Majalengka

<div align="center">
  
  ![TRACER Logo](public/assets/logo-tracer-mtsn11majalengka.png)
  
  ### Transkrip & Academic Ledger
  **Tracing Progress, Graduating Success.**
  
  Sistem manajemen transkrip nilai akademik **berbasis web** untuk MTsN 11 Majalengka dengan verifikasi QR code dan kontrol semester otomatis.
  
  ![PHP](https://img.shields.io/badge/PHP-Native-777BB4?logo=php&logoColor=white)
  ![MySQL](https://img.shields.io/badge/MySQL-PDO-4479A1?logo=mysql&logoColor=white)
  ![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3.3-7952B3?logo=bootstrap&logoColor=white)
  ![License](https://img.shields.io/badge/License-Proprietary-red)
  ![Release](https://img.shields.io/badge/First%20Released-March%202026-10b981)
  
</div>

---

## 🎯 Filosofi TRACER

**TRACER** adalah sistem pelacakan (*trace*) progres akademik siswa dari **Semester 1 hingga UAM** — mencatat setiap jejak prestasi, menghasilkan dokumen resmi yang terverifikasi, dan memastikan setiap lulusan meninggalkan catatan yang dapat dipercaya.

## ✨ Fitur Unggulan

### 🔐 **Keamanan & Verifikasi**
- **QR Code Verification**: Setiap transkrip dilengkapi QR code unik yang dapat dipindai untuk memverifikasi keaslian dokumen
- **HTTP Security Headers**: Perlindungan berlapis dengan X-Frame-Options, CSP, dan security headers lainnya
- **Token-based Verification**: Sistem verifikasi berbasis token dengan error handling profesional
- **Role-based Access**: Login hierarkis untuk `admin` (Super Admin) dan `kurikulum`

### 📊 **Manajemen Akademik**
- **Dashboard Interaktif**: Statistik siswa real-time + monitoring status upload nilai per mapel
- **Master Data Komprehensif**: Kelola user, mata pelajaran, dan data siswa (termasuk tracking siswa yang tidak melanjutkan)
- **Import Nilai Excel**: Upload massal berbasis NISN menggunakan PhpSpreadsheet
  - Semester **GANJIL** → Proses otomatis untuk siswa di semester 1, 3, 5
  - Semester **GENAP** → Proses otomatis untuk siswa di semester 2, 4 + import **UAM** semester 6
  - Validasi nilai rentang 70-100 dengan feedback error yang jelas

### ⚙️ **Kontrol Semester Otomatis**
- **Tahun Ajaran Aktif**: Set dan kelola periode akademik dengan mudah
- **Finalisasi Semester**: Satu klik untuk mengunci nilai dan menaikkan `current_semester` seluruh siswa aktif
- **Normalisasi Otomatis**: Sistem mencegah duplikasi input dengan cek semester siswa

### 🎓 **Sistem Kelulusan**
- **Migrasi Alumni**: Transfer otomatis siswa eligible dari tabel siswa ke tabel alumni
- **Perhitungan Ijazah**: Formula 60% rapor + 40% UAM dengan konversi terbilang
- **Data Ijazah JSON**: Penyimpanan terstruktur untuk kebutuhan arsip digital

### 📄 **Laporan & Dokumen**
- **Leger Kolektif Excel**: Export massal nilai siswa untuk arsip kurikulum
- **Transkrip PDF Premium**: Generate menggunakan Dompdf dengan optimasi performa:
  - **Batch Processing**: Proses query dalam satu batch untuk 50+ siswa
  - **QR Code Caching**: Cache gambar QR untuk mengurangi request API eksternal
  - **Transaction Wrapping**: Gunakan database transaction untuk konsistensi data
  - Header resmi Kemenag dengan layout profesional

### 🗄️ **Database Tools**
- Truncate tabel untuk development
- Backup & restore database
- Migration support untuk update schema

---

## 📁 Struktur Direktori

```
tracer-mtsn11majalengka/
├── app/
│   ├── config/              # Konfigurasi aplikasi & database
│   ├── helpers/             # Common functions & utilities
│   ├── middleware/          # Authentication & authorization
│   └── views/
│       ├── pages/           # Semua modul halaman aplikasi
│       └── partials/        # Header, footer, sidebar
├── database/
│   ├── schema.sql           # Skema database & seed data
│   ├── backups/             # Database backups
│   └── migrations/          # SQL migration files
├── public/                  # Document root
│   ├── index.php            # Front controller
│   ├── verify.php           # QR verification landing page
│   ├── assets/              # CSS, JS, images
│   └── uploads/             # File uploads (siswa photos, imports)
└── storage/
    └── exports/             # Generated PDFs & Excel files
```

---

## 🚀 Instalasi Lokal

### Prerequisites
- PHP 7.4+
- MySQL 5.7+
- Composer
- Web server (Apache/Nginx)

### Langkah Instalasi

1. **Clone Repository**
   ```bash
   git clone <repository-url>
   cd tracer-mtsn11majalengka
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Setup Environment**
   ```bash
   copy .env.example .env
   ```
   Edit file `.env` dan isi kredensial database Anda:
   ```env
   DB_HOST=localhost
   DB_NAME=tracer_db
   DB_USER=root
   DB_PASS=
   ```

4. **Import Database**
   - Buat database baru di MySQL/phpMyAdmin
   - Import file `database/schema.sql`

5. **Jalankan Server**
   ```bash
   php -S localhost:8000 -t public
   ```
   Akses aplikasi di: `http://localhost:8000`

### Akun Default
- **Username**: `superadmin`
- **Password**: `password`

> ⚠️ **Penting**: Ganti password default setelah login pertama kali!

---

## 🌐 Deploy ke cPanel Hosting

### Langkah Deploy

1. **Upload Source Code**
   - Kompres seluruh folder project (ZIP)
   - Upload via cPanel File Manager ke folder `public_html` atau subdomain folder
   - Extract file ZIP

2. **Setup Database**
   - Buka **MySQL Databases** di cPanel
   - Buat database baru dan user MySQL
   - Catat nama database, username, dan password
   - Buka **phpMyAdmin** dan import `database/schema.sql`

3. **Konfigurasi Environment**
   - Copy `.env.example` menjadi `.env` di cPanel File Manager
   - Edit `.env` dengan kredensial database hosting:
     ```env
     DB_HOST=localhost
     DB_NAME=cpanel_tracerdb
     DB_USER=cpanel_traceruser
     DB_PASS=password_hosting
     ```

4. **Install Composer Dependencies**
   - Jika hosting support SSH:
     ```bash
     cd /home/username/public_html
     composer install --no-dev --optimize-autoloader
     ```
   - Jika tidak support composer, build folder `vendor` di lokal lalu upload

5. **Atur Document Root**
   - Arahkan domain/subdomain ke folder `public`
   - Contoh: `tracer.mtsn11majalengka.sch.id` → `/public_html/tracer/public`
   - Jika tidak bisa ubah document root:
     - Pindahkan isi folder `public` ke `public_html`
     - Update path bootstrap di `public_html/index.php`:
       ```php
       require __DIR__ . '/../app/bootstrap.php';
       ```

6. **Set Permission**
   ```bash
   chmod -R 755 public/uploads
   chmod -R 755 storage/exports
   chmod -R 755 database/backups
   ```

### Tips Git Pull di Hosting

Untuk menghindari konflik saat `git pull`:

1. **Jangan Edit File Tracked di Server**
   - Gunakan `.env` untuk konfigurasi, bukan hardcode di file tracked

2. **Skip Worktree untuk File Markdown** (Jika tidak ingin markdown di hosting)
   ```bash
   git update-index --skip-worktree *.md
   ```

3. **Reset File Jika Terlanjut Edit**
   ```bash
   git checkout -- app/config/database.php
   git pull origin main
   ```

---

## 🛡️ Keamanan & Best Practices

### Fitur Keamanan Built-in

- ✅ **PDO Prepared Statements**: Semua query database menggunakan prepared statements untuk mencegah SQL injection
- ✅ **Password Hashing**: Password disimpan dengan `password_hash()` menggunakan algoritma BCRYPT
- ✅ **CSRF Protection**: Semua form POST menggunakan token CSRF untuk mencegah serangan cross-site request forgery
- ✅ **Input Validation**: Validasi nilai rapor/UAM dalam rentang 70-100
- ✅ **Unique Constraints**: Validasi duplikasi NISN/NIS diterapkan di level database
- ✅ **HTTP Security Headers**: Perlindungan berlapis untuk halaman verifikasi QR:
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY`
  - `X-XSS-Protection: 1; mode=block`
  - `Referrer-Policy: no-referrer`
  - `Content-Security-Policy`
  - `Permissions-Policy`

### Database Migration

Jika database sudah berjalan sebelum update, jalankan migration berikut:

```sql
-- Tambah constraint unique untuk NIS
ALTER TABLE siswa ADD UNIQUE KEY uniq_siswa_nis (nis);
```

---

## 🎨 Teknologi Stack

| Kategori | Teknologi |
|----------|-----------|
| **Backend** | PHP Native 7.4+ (PDO) |
| **Database** | MySQL 5.7+ |
| **Frontend** | Bootstrap 5.3.3, Bootstrap Icons |
| **PDF Generation** | Dompdf |
| **Excel Processing** | PhpSpreadsheet |
| **QR Code** | api.qrserver.com + Local Caching |
| **Dependency Manager** | Composer |
| **Version Control** | Git |

### Design System

- **Primary Color**: `#064e3b` (Emerald 900)
- **Accent Color**: `#10b981` (Emerald 500)
- **Logo**: Emerald gradient dengan konsep "TRACER"
- **Typography**: Bootstrap default font-family

---

## 📚 Dokumentasi Lengkap

Untuk informasi lebih detail, lihat dokumentasi berikut:

| Dokumen | Deskripsi |
|---------|-----------|
| [CHANGELOG.md](CHANGELOG.md) | Riwayat perubahan dan versioning |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Panduan kontribusi untuk developer |
| [SECURITY.md](SECURITY.md) | Kebijakan keamanan dan pelaporan vulnerability |
| [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) | Kode etik kontributor |
| [LICENSE.md](LICENSE.md) | Lisensi penggunaan (Proprietary) |

### Template GitHub

- 🐛 [Bug Report](.github/ISSUE_TEMPLATE/bug_report.md) — Laporkan bug yang ditemukan
- ✨ [Feature Request](.github/ISSUE_TEMPLATE/feature_request.md) — Ajukan fitur baru
- 🔀 [Pull Request](.github/pull_request_template.md) — Kirim perubahan kode

---

## 🤝 Kontribusi

Kami menyambut kontribusi untuk meningkatkan TRACER! Silakan baca [CONTRIBUTING.md](CONTRIBUTING.md) untuk panduan lengkap.

**Kontributor diharapkan:**
- Mengikuti standar kode yang ada
- Menulis kode yang aman dan efisien
- Menguji perubahan sebelum submit PR
- Menghormati Code of Conduct

---

## 📞 Kontak & Support

**MTsN 11 Majalengka**  
Email: mtsn11majalengka@gmail.com  
Website: https://mtsn11majalengka.sch.id

Untuk pelaporan bug atau feature request, gunakan [GitHub Issues](../../issues).

---

## 📅 Release Info

- **First Created**: March 6, 2026
- **Published**: March 2026

Untuk riwayat versi lengkap, lihat **[CHANGELOG.md](CHANGELOG.md)**.

---

## 👨‍💻 Developer

- Developed by: [A.T. Aditya](https://www.instagram.com/atadityas_13/)
- Full Name: Anzas Tio Aditya
- Profile: Computer Science B.S. Information Systems Specialist & AI-Augmented Developer appointed as an Aparatur Sipil Negara at the Ministry of Religious Affairs. Beyond managing educational platforms, I design data-driven solutions like TRACER to automate academic workflows while maintaining robust Madrasah website and server infrastructures to ensure data integrity and seamless digital services.
- GitHub: [atadityas13 - Anzas Tio Aditya](https://github.com/atadityas13)
- Email: at.adityas13@gmail.com

---

## 📜 Lisensi

Project ini menggunakan **Proprietary License** untuk kebutuhan internal MTsN 11 Majalengka.  
Lihat [LICENSE.md](LICENSE.md) untuk detail lengkap.

**© 2026 MTsN 11 Majalengka. All rights reserved.**

---

<div align="center">
  
  **TRACER** — *Tracing Progress, Graduating Success.*
  
  Dibuat dengan ❤️ untuk pendidikan yang lebih baik
  
</div>
