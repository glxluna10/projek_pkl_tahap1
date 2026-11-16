<?php
session_start();
require "config/koneksi.php";

$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user["password"])) {
    header("Location: login.php?pesan=" . urlencode("Username atau password salah."));
    exit;
}

$_SESSION["id_user"]  = $user["id_user"];
$_SESSION["nama"]     = $user["nama"];
$_SESSION["username"] = $user["username"];
$_SESSION["role"]     = $user["role"]; // 'admin' atau 'staff'

header("Location: dashboard.php");
exit;