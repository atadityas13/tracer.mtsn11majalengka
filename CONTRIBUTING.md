# Contributing Guide

Terima kasih atas kontribusi Anda untuk **TRACER MTsN 11 Majalengka** (Transkrip & Academic Ledger).

## Alur Kontribusi

1. Fork repository ini.
2. Buat branch baru dari `main`:
   - `feature/nama-fitur` untuk fitur baru
   - `fix/nama-bug` untuk perbaikan bug
3. Lakukan perubahan dengan scope kecil dan jelas.
4. Pastikan aplikasi tetap berjalan tanpa error.
5. Buat Pull Request dengan deskripsi lengkap.

## Standar Kode

- Gunakan PHP sesuai style code yang sudah ada.
- Gunakan prepared statements untuk query database.
- Jangan hardcode kredensial, gunakan `.env`.
- Jangan ubah struktur semester tanpa update helper terkait (`normalize_current_semester`, `current_semester_label`).
- Hindari perubahan besar yang tidak diminta dalam satu PR.

## Commit Message

Gunakan format sederhana:

- `feat: tambah fitur ...`
- `fix: perbaiki bug ...`
- `refactor: rapikan ...`
- `docs: update dokumentasi ...`

## Pull Request Checklist

- [ ] Perubahan sesuai kebutuhan
- [ ] Tidak merusak fitur existing
- [ ] Validasi input dan keamanan dipertahankan
- [ ] Tidak ada file sensitif yang ikut ter-commit
- [ ] Dokumentasi terkait diperbarui (jika perlu)

## Catatan Penting

Repository ini digunakan untuk kebutuhan internal sekolah. Mohon tetap menjaga konsistensi, keamanan data, dan kualitas perubahan.
