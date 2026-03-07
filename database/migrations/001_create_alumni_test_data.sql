-- ========================================================
-- E-Leger Alumni Test Data Migration
-- Complete Setup: Siswa + Nilai Rapor + Nilai UAM + Alumni
-- ========================================================

-- 1. INSERT SISWA (Alumni Student)
INSERT IGNORE INTO siswa (
    nisn, nis, nama, tempat_lahir, tgl_lahir, 
    kelas, nomor_absen, current_semester, tahun_masuk, 
    status_siswa, created_at
) VALUES (
    '1234567890123', 
    '2000123',
    'Muhammad Rizki Al-Azhari',
    'Majalengka',
    '2009-06-15',
    'IX',
    1,
    6,
    '2022/2023',
    'Lulus',
    NOW()
);

-- 2. INSERT NILAI RAPOR (Semester 1-5, All Subjects)
INSERT IGNORE INTO nilai_rapor (nisn, mapel_id, semester, tahun_ajaran, nilai_angka, is_finalized)
SELECT '1234567890123', m.id, s.semester, '2025/2026', 
    ROUND(75 + RAND() * 20, 2), 1
FROM mapel m
CROSS JOIN (
    SELECT 1 as semester 
    UNION ALL SELECT 2 
    UNION ALL SELECT 3 
    UNION ALL SELECT 4 
    UNION ALL SELECT 5
) s
WHERE NOT EXISTS (
    SELECT 1 FROM nilai_rapor nr 
    WHERE nr.nisn = '1234567890123' 
    AND nr.mapel_id = m.id 
    AND nr.semester = s.semester
);

-- 3. INSERT NILAI UAM (Final Exam)
INSERT IGNORE INTO nilai_uam (nisn, mapel_id, nilai_angka)
SELECT '1234567890123', m.id, ROUND(76 + RAND() * 18, 2)
FROM mapel m
WHERE NOT EXISTS (
    SELECT 1 FROM nilai_uam nu 
    WHERE nu.nisn = '1234567890123' 
    AND nu.mapel_id = m.id
);

-- 4. INSERT ALUMNI RECORD (Basic)
INSERT IGNORE INTO alumni (nisn, nama, angkatan_lulus, tanggal_kelulusan, nomor_surat, verification_token, data_ijazah_json, created_at)
VALUES (
    '1234567890123',
    'Muhammad Rizki Al-Azhari',
    2026,
    CURDATE(),
    CONCAT('       /Mts.10.89/PP.00.5/', DATE_FORMAT(NOW(), '%m/%Y')),
    CONCAT('token_', SHA2('1234567890123', 256)),
    '[]',
    NOW()
);

-- 5. VERIFIKASI DATA
SELECT 
    'Siswa' as type,
    COUNT(*) as count,
    MAX(created_at) as last_updated
FROM siswa
WHERE nisn = '1234567890123'

UNION ALL

SELECT 
    'Nilai Rapor' as type,
    COUNT(*) as count,
    MAX(created_at) as last_updated
FROM nilai_rapor
WHERE nisn = '1234567890123'

UNION ALL

SELECT 
    'Nilai UAM' as type,
    COUNT(*) as count,
    MAX(created_at) as last_updated
FROM nilai_uam
WHERE nisn = '1234567890123'

UNION ALL

SELECT 
    'Alumni' as type,
    COUNT(*) as count,
    MAX(created_at) as last_updated
FROM alumni
WHERE nisn = '1234567890123';
