-- =========================================
-- Database: bl_management
-- Sistem Informasi Pendataan dan Monitoring
-- Bill of Lading (B/L)
-- Instalasi BARU (Tahap 1 + Tahap 2)
-- =========================================

CREATE DATABASE IF NOT EXISTS bl_management CHARACTER SET utf8mb4;
USE bl_management;

-- =========================
-- Tabel users (untuk login & register)
-- =========================
CREATE TABLE IF NOT EXISTS users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- disimpan dalam bentuk hash (password_hash)
    role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- Tabel bill_of_lading
-- Satu nomor B/L = satu baris (UNIQUE di nomor_bl).
-- Aturan status: SURRENDERED menang atas ORI (SURRENDERED = sudah diterima).
-- =========================
CREATE TABLE IF NOT EXISTS bill_of_lading (
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

-- =========================================
-- CATATAN AKUN ADMIN (bukan perintah otomatis, cuma panduan):
-- Gak ada akun admin default di sini karena password WAJIB di-hash lewat PHP
-- (pakai password_hash), bukan ditulis polos di file SQL.
-- Caranya bikin admin:
--   1. Daftar akun biasa dulu lewat register.php
--   2. Jalankan query ini manual buat naikkan role akun itu jadi admin:
--      UPDATE users SET role = 'admin' WHERE username = 'username_kamu';
-- =========================================
