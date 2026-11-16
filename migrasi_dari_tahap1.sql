-- =========================================
-- MIGRASI dari Tahap 1 ke Tahap 2
-- Pakai file ini kalau database bl_management SUDAH ada dari Tahap 1.
--
-- PERINGATAN: tabel bill_of_lading lama DIHAPUS dan dibuat ulang.
-- Aman dijalankan SEKALI sekarang karena di Tahap 1 tabel itu belum dipakai (kosong).
-- Jangan dijalankan lagi setelah ada data B/L asli.
-- Tabel users TIDAK disentuh.
-- =========================================

USE bl_management;

DROP TABLE IF EXISTS bill_of_lading;

CREATE TABLE bill_of_lading (
    id_bl INT AUTO_INCREMENT PRIMARY KEY,
    nomor_bl VARCHAR(100) NOT NULL,
    shipper VARCHAR(150) NOT NULL DEFAULT '',
    vessel VARCHAR(150) NOT NULL DEFAULT '',
    tanggal_bl DATE NULL,
    status ENUM('ORI', 'SURRENDERED') NOT NULL DEFAULT 'ORI',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_nomor_bl (nomor_bl),
    KEY idx_status (status),
    KEY idx_tanggal (tanggal_bl),
    KEY idx_shipper (shipper)
);
