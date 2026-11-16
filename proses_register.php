<?php
require "config/koneksi.php";

$nama       = trim($_POST["nama"] ?? "");
$username   = trim($_POST["username"] ?? "");
$password   = $_POST["password"] ?? "";
$konfirmasi = $_POST["konfirmasi_password"] ?? "";

if ($nama === "" || $username === "") {
    header("Location: register.php?pesan=" . urlencode("Nama dan username wajib diisi."));
    exit;
}

if ($password !== $konfirmasi) {
    header("Location: register.php?pesan=" . urlencode("Password dan konfirmasi tidak sama."));
    exit;
}

if (strlen($password) < 6) {
    header("Location: register.php?pesan=" . urlencode("Password minimal 6 karakter."));
    exit;
}

$stmt = $pdo->prepare("SELECT id_user FROM users WHERE username = ?");
$stmt->execute([$username]);
if ($stmt->fetch()) {
    header("Location: register.php?pesan=" . urlencode("Username sudah dipakai, coba yang lain."));
    exit;
}

// Akun baru selalu role 'staff'. Admin cuma bisa di-set manual lewat query database,
// lihat catatan di database.sql.
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, 'staff')");
$stmt->execute([$nama, $username, $hash]);

header("Location: login.php?pesan=" . urlencode("Akun berhasil dibuat, silakan masuk.") . "&tipe=sukses");
exit;