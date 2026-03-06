-- Migration: Tambah field tanggal_kelulusan dan nomor_surat di tabel alumni
-- Date: 2026-03-07

ALTER TABLE alumni 
ADD COLUMN tanggal_kelulusan DATE NULL AFTER angkatan_lulus,
ADD COLUMN nomor_surat VARCHAR(100) NULL AFTER tanggal_kelulusan,
ADD COLUMN verification_token VARCHAR(64) NULL AFTER data_ijazah_json,
ADD INDEX idx_verification_token (verification_token);
