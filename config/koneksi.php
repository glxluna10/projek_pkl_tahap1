<?php
// config/koneksi.php
// Koneksi database pakai PDO (biar error lebih jelas & gampang dipakai)

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bl_management;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Gagal konek ke database: " . $e->getMessage());
}
