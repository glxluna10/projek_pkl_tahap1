<?php
// cek_bl.php
// Dipanggil lewat fetch() dari form: cek nomor B/L yang sedang diketik.
require "auth.php";
require "config/koneksi.php";
require "includes/helpers.php";

header("Content-Type: application/json; charset=utf-8");

$key     = normalisasi_nomor($_GET["nomor"] ?? "");
$id      = (int)($_GET["id"] ?? 0);
$shipper = rapikan_teks($_GET["shipper"] ?? "");
$out     = ["ada" => null, "mirip" => []];

if ($key !== "") {
    $st = $pdo->prepare("SELECT id_bl, nomor_bl, shipper, status FROM bill_of_lading WHERE nomor_bl = ? AND id_bl <> ?");
    $st->execute([$key, $id]);
    $row = $st->fetch();
    if ($row) {
        $out["ada"] = $row;
    } else {
        $out["mirip"] = cari_mirip($pdo, $key, $id, $shipper);
    }
}

echo json_encode($out);
