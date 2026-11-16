<?php
require "auth.php";
require "config/koneksi.php";
require "includes/helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: dashboard.php");
    exit;
}
csrf_wajib();
wajib_admin();

$id = (int)($_POST["id"] ?? 0);
$st = $pdo->prepare("SELECT nomor_bl FROM bill_of_lading WHERE id_bl = ?");
$st->execute([$id]);
$row = $st->fetch();

if ($row) {
    $pdo->prepare("DELETE FROM bill_of_lading WHERE id_bl = ?")->execute([$id]);
    flash_set("sukses", "B/L " . $row["nomor_bl"] . " dihapus.");
} else {
    flash_set("error", "Data B/L tidak ditemukan.");
}
header("Location: dashboard.php");
exit;
