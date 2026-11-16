# B/L Control - Tahap 2

## Cara pasang
1. Salin folder ini ke htdocs (XAMPP) atau www (Laragon).
2. Database:
   - Instalasi baru: import `database.sql`.
   - Sudah punya database dari Tahap 1: import `migrasi_dari_tahap1.sql` (menghapus & membuat ulang tabel `bill_of_lading`, tabel `users` aman).
3. Pastikan ekstensi PHP `zip` aktif (XAMPP: php.ini -> `extension=zip`) supaya file .xlsx bisa dibaca. Kalau tidak, simpan Excel sebagai .csv.
4. Buka `login.php`, daftar akun, lalu jadikan admin lewat SQL:
   `UPDATE users SET role = 'admin' WHERE username = 'username_kamu';`

## Isi Tahap 2
- Data B/L: tambah, edit, hapus (admin), cari, filter status, pagination
- Aturan: satu nomor B/L = satu baris; SURRENDERED menang atas ORI
- Deteksi nomor mirip (salah ketik) saat input dan saat import
- Import Excel/CSV dengan halaman tinjau sebelum disimpan
- Tema terang/gelap (tombol di pojok kanan atas)
