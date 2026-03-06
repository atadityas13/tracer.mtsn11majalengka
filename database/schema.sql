CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(120) NOT NULL,
    role ENUM('admin','kurikulum') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS siswa (
    nisn VARCHAR(20) PRIMARY KEY,
    nis VARCHAR(20) NOT NULL UNIQUE,
    nama VARCHAR(120) NOT NULL,
    tempat_lahir VARCHAR(80) NOT NULL,
    tgl_lahir DATE NOT NULL,
    kelas VARCHAR(20) DEFAULT NULL,
    nomor_absen TINYINT DEFAULT NULL,
    current_semester TINYINT NOT NULL DEFAULT 1,
    status_siswa ENUM('Aktif','Tidak Melanjutkan','Lulus') NOT NULL DEFAULT 'Aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS mapel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_mapel VARCHAR(120) NOT NULL,
    kelompok ENUM('A','B') NOT NULL,
    is_sub_pai TINYINT(1) NOT NULL DEFAULT 0,
    urutan INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_nama_mapel (nama_mapel)
);

CREATE TABLE IF NOT EXISTS nilai_rapor (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    nisn VARCHAR(20) NOT NULL,
    mapel_id INT NOT NULL,
    semester TINYINT NOT NULL,
    tahun_ajaran VARCHAR(20) NOT NULL,
    nilai_angka DECIMAL(5,2) NOT NULL,
    is_finalized TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_nilai_rapor (nisn, mapel_id, semester, tahun_ajaran),
    CONSTRAINT fk_nilai_rapor_siswa FOREIGN KEY (nisn) REFERENCES siswa(nisn) ON DELETE CASCADE,
    CONSTRAINT fk_nilai_rapor_mapel FOREIGN KEY (mapel_id) REFERENCES mapel(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS nilai_uam (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    nisn VARCHAR(20) NOT NULL,
    mapel_id INT NOT NULL,
    nilai_angka DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_nilai_uam (nisn, mapel_id),
    CONSTRAINT fk_nilai_uam_siswa FOREIGN KEY (nisn) REFERENCES siswa(nisn) ON DELETE CASCADE,
    CONSTRAINT fk_nilai_uam_mapel FOREIGN KEY (mapel_id) REFERENCES mapel(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS alumni (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    nisn VARCHAR(20) NOT NULL,
    nama VARCHAR(150) NULL,
    angkatan_lulus YEAR NOT NULL,
    data_ijazah_json JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_alumni (nisn)
);

CREATE TABLE IF NOT EXISTS pengaturan_akademik (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran VARCHAR(20) NOT NULL,
    semester_aktif ENUM('GANJIL','GENAP') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO pengaturan_akademik (tahun_ajaran, semester_aktif)
SELECT '2025/2026', 'GANJIL'
WHERE NOT EXISTS (SELECT 1 FROM pengaturan_akademik);

INSERT INTO mapel (nama_mapel, kelompok, is_sub_pai, urutan)
VALUES
("Al-Qur'an Hadis", 'A', 1, 1),
('Akidah Akhlak', 'A', 1, 2),
('Fikih', 'A', 1, 3),
('SKI', 'A', 1, 4),
('PPKn', 'A', 0, 5),
('Bahasa Indonesia', 'A', 0, 6),
('Bahasa Arab', 'A', 0, 7),
('Matematika', 'A', 0, 8),
('IPA', 'A', 0, 9),
('IPS', 'A', 0, 10),
('Bahasa Inggris', 'A', 0, 11),
('Seni Budaya', 'B', 0, 12),
('Penjasorkes', 'B', 0, 13),
('Prakarya/Informatika', 'B', 0, 14),
('Bahasa Daerah', 'B', 0, 15)
ON DUPLICATE KEY UPDATE nama_mapel=VALUES(nama_mapel), urutan=VALUES(urutan);

INSERT INTO users (username, password, nama_lengkap, role)
SELECT 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='superadmin');
