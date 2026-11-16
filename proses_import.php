<?php
require "auth.php";
require "config/koneksi.php";
require "includes/helpers.php";
require "includes/import_lib.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: import.php");
    exit;
}
csrf_wajib();

$baris = $_SESSION["import_baris"] ?? null;
if (!$baris) {
    flash_set("error", "Sesi import habis. Upload ulang filenya.");
    header("Location: import.php");
    exit;
}

// Hitung ulang dari data di sesi (jangan percaya data dari browser)
$an = analisis_import($pdo, $baris);
$keputusan = (isset($_POST["keputusan"]) && is_array($_POST["keputusan"])) ? $_POST["keputusan"] : [];
$hasil = terapkan_keputusan($an, $keputusan);

$final  = $hasil["final"];
$naikDb = $hasil["naikDb"];
foreach ($an["aksi"] as $key => $aksi) {
    if ($aksi === "upgrade") {
        $naikDb[$an["db"][$key]["id_bl"]] = true;
    }
}

$baru = 0;
$upgrade = 0;

try {
    $pdo->beginTransaction();

    $ins = $pdo->prepare("INSERT INTO bill_of_lading (nomor_bl, shipper, vessel, tanggal_bl, status, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($final as $key => $d) {
        if (($an["aksi"][$key] ?? "") !== "baru") continue;
        $ins->execute([$d["nomor_bl"], $d["shipper"], $d["vessel"], $d["tanggal_bl"], $d["status"], $_SESSION["id_user"]]);
        $baru++;
    }

    $upd = $pdo->prepare("UPDATE bill_of_lading SET status = 'SURRENDERED' WHERE id_bl = ? AND status = 'ORI'");
    foreach (array_keys($naikDb) as $idBl) {
        $upd->execute([(int)$idBl]);
        $upgrade += $upd->rowCount();
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash_set("error", "Import dibatalkan, tidak ada data yang tersimpan. Penyebab: " . $e->getMessage());
    header("Location: import_review.php");
    exit;
}

unset($_SESSION["import_baris"], $_SESSION["import_nama"]);

$pesan = "Import selesai: $baru B/L baru";
if ($upgrade > 0) $pesan .= ", $upgrade di-upgrade ke SURRENDERED";
if ($hasil["digabung"] > 0) $pesan .= ", " . $hasil["digabung"] . " nomor salah ketik digabung";
$pesan .= ".";
flash_set("sukses", $pesan);
header("Location: dashboard.php");
exit;
