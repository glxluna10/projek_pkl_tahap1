<?php
require "auth.php";
require "config/koneksi.php";
require "includes/helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: dashboard.php");
    exit;
}
csrf_wajib();

$id       = (int)($_POST["id"] ?? 0);
$key      = normalisasi_nomor($_POST["nomor_bl"] ?? "");
$shipper  = rapikan_teks($_POST["shipper"] ?? "");
$vessel   = rapikan_teks($_POST["vessel"] ?? "");
$tglRaw   = trim($_POST["tanggal_bl"] ?? "");
$tanggal  = $tglRaw === "" ? null : parse_tanggal($tglRaw);
$status   = normalisasi_status($_POST["status"] ?? "");
$konfirm  = !empty($_POST["konfirmasi_mirip"]);

// Balik ke form sambil membawa isian + pesan
function kembali_ke_form($id, $pesan, $mirip = []) {
    global $key, $shipper, $vessel, $tglRaw, $status;
    $_SESSION["old"] = [
        "nomor_bl"   => $key,
        "shipper"    => $shipper,
        "vessel"     => $vessel,
        "tanggal_bl" => $tglRaw,
        "status"     => $status ?: "ORI",
    ];
    $_SESSION["mirip"] = $mirip;
    if ($pesan !== "") flash_set("error", $pesan);
    header("Location: bl_form.php" . ($id > 0 ? "?id=" . $id : ""));
    exit;
}

if ($key === "")                 kembali_ke_form($id, "Nomor B/L wajib diisi.");
if (strlen($key) > 100)          kembali_ke_form($id, "Nomor B/L maksimal 100 karakter.");
if ($shipper === "")             kembali_ke_form($id, "Shipper wajib diisi.");
if ($vessel === "")              kembali_ke_form($id, "Vessel wajib diisi.");
if (mb_strlen($shipper) > 150 || mb_strlen($vessel) > 150) kembali_ke_form($id, "Shipper dan vessel maksimal 150 karakter.");
if ($status === null)            kembali_ke_form($id, "Status harus ORI atau SURRENDERED.");
if ($tglRaw !== "" && $tanggal === null) kembali_ke_form($id, "Tanggal tidak valid.");

/* ===== EDIT ===== */
if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM bill_of_lading WHERE id_bl = ?");
    $st->execute([$id]);
    $lama = $st->fetch();
    if (!$lama) {
        flash_set("error", "Data B/L tidak ditemukan.");
        header("Location: dashboard.php");
        exit;
    }

    $st = $pdo->prepare("SELECT nomor_bl, status FROM bill_of_lading WHERE nomor_bl = ? AND id_bl <> ?");
    $st->execute([$key, $id]);
    if ($st->fetch()) {
        kembali_ke_form($id, "Nomor $key sudah dipakai B/L lain. Satu nomor hanya boleh punya satu data.");
    }

    if ($key !== strtoupper($lama["nomor_bl"]) && !$konfirm) {
        $mirip = cari_mirip($pdo, $key, $id, $shipper);
        if ($mirip) kembali_ke_form($id, "", $mirip);
    }

    $st = $pdo->prepare("UPDATE bill_of_lading SET nomor_bl = ?, shipper = ?, vessel = ?, tanggal_bl = ?, status = ? WHERE id_bl = ?");
    $st->execute([$key, $shipper, $vessel, $tanggal, $status, $id]);

    flash_set("sukses", "B/L $key berhasil diperbarui.");
    header("Location: dashboard.php");
    exit;
}

/* ===== TAMBAH BARU ===== */
$st = $pdo->prepare("SELECT id_bl, status FROM bill_of_lading WHERE nomor_bl = ?");
$st->execute([$key]);
$ada = $st->fetch();

if ($ada) {
    if ($ada["status"] === "ORI" && $status === "SURRENDERED") {
        // Aturan inti: SURRENDERED menang atas ORI
        $st = $pdo->prepare("UPDATE bill_of_lading SET status = 'SURRENDERED' WHERE id_bl = ?");
        $st->execute([$ada["id_bl"]]);
        flash_set("sukses", "Nomor $key sudah ada dengan status ORI. Statusnya diperbarui menjadi SURRENDERED.");
        header("Location: dashboard.php");
        exit;
    }
    if ($ada["status"] === "SURRENDERED" && $status === "ORI") {
        kembali_ke_form($id, "Nomor $key sudah SURRENDERED. Status tidak bisa diturunkan kembali ke ORI.");
    }
    kembali_ke_form($id, "Nomor $key sudah ada dengan status $status. Tidak perlu dimasukkan dua kali.");
}

if (!$konfirm) {
    $mirip = cari_mirip($pdo, $key, 0, $shipper);
    if ($mirip) kembali_ke_form($id, "", $mirip);
}

$st = $pdo->prepare("INSERT INTO bill_of_lading (nomor_bl, shipper, vessel, tanggal_bl, status, created_by) VALUES (?, ?, ?, ?, ?, ?)");
$st->execute([$key, $shipper, $vessel, $tanggal, $status, $_SESSION["id_user"]]);

flash_set("sukses", "B/L $key berhasil ditambahkan.");
header("Location: dashboard.php");
exit;
