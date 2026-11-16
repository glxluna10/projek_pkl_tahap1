<?php
require "auth.php";
require "config/koneksi.php";
require "includes/helpers.php";
require "includes/import_lib.php";

// Batalkan import yang sedang menunggu tinjauan
if (isset($_GET["batal"])) {
    unset($_SESSION["import_baris"], $_SESSION["import_nama"]);
    header("Location: import.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_wajib();

    $f = $_FILES["berkas"] ?? null;
    if (!$f || $f["error"] !== UPLOAD_ERR_OK) {
        flash_set("error", "Upload gagal. Pilih file .xlsx atau .csv lalu coba lagi.");
        header("Location: import.php");
        exit;
    }
    if ($f["size"] > 5 * 1024 * 1024) {
        flash_set("error", "File terlalu besar (maksimal 5 MB).");
        header("Location: import.php");
        exit;
    }

    $ext = strtolower(pathinfo($f["name"], PATHINFO_EXTENSION));
    try {
        if ($ext === "xlsx") {
            $rows = baca_xlsx($f["tmp_name"]);
        } elseif ($ext === "csv") {
            $rows = baca_csv($f["tmp_name"]);
        } else {
            throw new RuntimeException("Format file harus .xlsx atau .csv.");
        }
        $baris = ekstrak_baris($rows);
        if (!$baris) {
            throw new RuntimeException("Tidak ada baris data di bawah header.");
        }
    } catch (Throwable $e) {
        flash_set("error", $e->getMessage());
        header("Location: import.php");
        exit;
    }

    $_SESSION["import_baris"] = $baris;
    $_SESSION["import_nama"]  = $f["name"];
    header("Location: import_review.php");
    exit;
}

$judul = "Import Excel";
$aktif = "import";
require "includes/layout_top.php";
?>

<div class="page-head">
    <div>
        <h1>Import dari Excel</h1>
        <p class="muted">Data tidak langsung disimpan. Kamu tinjau dulu duplikat dan nomor yang mirip.</p>
    </div>
</div>

<form method="POST" action="import.php" enctype="multipart/form-data" class="form-box form-lebar">
    <?= csrf_field() ?>
    <label for="berkas">File Excel (.xlsx) atau CSV</label>
    <input type="file" id="berkas" name="berkas" accept=".xlsx,.csv" required>
    <p class="field-hint">Maksimal 5 MB. Baris pertama yang berisi judul kolom akan dikenali otomatis.</p>

    <div class="callout">
        <strong>Kolom yang dibaca</strong>
        <ul>
            <li><span class="mono-strong">B / L</span> &ndash; nomor B/L</li>
            <li><span class="mono-strong">SHIPPER</span>, <span class="mono-strong">VESSEL</span></li>
            <li><span class="mono-strong">TGL B/L</span> &ndash; tanggal asli Excel, atau teks seperti 25-Agus-25</li>
            <li><span class="mono-strong">STATUS B/L</span> &ndash; ORI atau SURRENDERED</li>
        </ul>
        <p class="field-hint">Kalau satu nomor muncul lebih dari sekali, SURRENDERED yang dipakai.</p>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Baca file</button>
        <a href="dashboard.php" class="btn btn-ghost">Batal</a>
    </div>
</form>

<?php require "includes/layout_bottom.php"; ?>
