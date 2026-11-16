<?php
require "auth.php";
require "config/koneksi.php";
require "includes/helpers.php";

$id   = (int)($_GET["id"] ?? 0);
$edit = $id > 0;

$data = ["nomor_bl" => "", "shipper" => "", "vessel" => "", "tanggal_bl" => "", "status" => "ORI"];

if ($edit) {
    $st = $pdo->prepare("SELECT * FROM bill_of_lading WHERE id_bl = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        flash_set("error", "Data B/L tidak ditemukan.");
        header("Location: dashboard.php");
        exit;
    }
    foreach ($data as $k => $v) {
        $data[$k] = $row[$k] ?? "";
    }
}

// Isi ulang form kalau barusan ditolak server
$old = $_SESSION["old"] ?? null;
unset($_SESSION["old"]);
if (is_array($old)) $data = array_merge($data, $old);

$mirip = $_SESSION["mirip"] ?? [];
unset($_SESSION["mirip"]);

$daftarShipper = $pdo->query("SELECT DISTINCT shipper FROM bill_of_lading WHERE shipper <> '' ORDER BY shipper LIMIT 300")->fetchAll(PDO::FETCH_COLUMN);
$daftarVessel  = $pdo->query("SELECT DISTINCT vessel FROM bill_of_lading WHERE vessel <> '' ORDER BY vessel LIMIT 500")->fetchAll(PDO::FETCH_COLUMN);

$judul = $edit ? "Edit B/L" : "Tambah B/L";
$aktif = "dashboard";
require "includes/layout_top.php";
?>

<a href="dashboard.php" class="back-link">&larr; Kembali ke data B/L</a>
<div class="page-head">
    <h1><?= $edit ? "Edit B/L" : "Tambah B/L" ?></h1>
</div>

<form method="POST" action="proses_bl.php" class="form-box form-lebar" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <label for="nomor_bl">Nomor B/L</label>
    <input type="text" id="nomor_bl" name="nomor_bl" value="<?= e($data["nomor_bl"]) ?>" maxlength="100" required autofocus>
    <div id="hint-nomor" class="hint-live" hidden></div>

    <?php if ($mirip): ?>
        <div class="callout callout-warn">
            <strong>Nomor ini mirip dengan data yang sudah ada. Salah ketik?</strong>
            <ul>
                <?php foreach ($mirip as $m): ?>
                    <li>
                        <span class="mono-strong"><?= e($m["nomor_bl"]) ?></span>
                        (<?= e($m["status"]) ?>, <?= e($m["shipper"]) ?>) &ndash; <?= e($m["alasan"]) ?>.
                        <a href="bl_form.php?id=<?= (int)$m["id_bl"] ?>">Buka data ini</a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <label class="check-row">
                <input type="checkbox" name="konfirmasi_mirip" value="1">
                Ini memang B/L yang berbeda, tetap simpan.
            </label>
        </div>
    <?php endif; ?>

    <label for="shipper">Shipper</label>
    <input type="text" id="shipper" name="shipper" value="<?= e($data["shipper"]) ?>" maxlength="150" list="list-shipper" required>
    <datalist id="list-shipper">
        <?php foreach ($daftarShipper as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?>
    </datalist>

    <label for="vessel">Vessel dan voyage</label>
    <input type="text" id="vessel" name="vessel" value="<?= e($data["vessel"]) ?>" maxlength="150" list="list-vessel" required>
    <datalist id="list-vessel">
        <?php foreach ($daftarVessel as $v): ?><option value="<?= e($v) ?>"><?php endforeach; ?>
    </datalist>
    <p class="field-hint">Contoh: SMC QINGDAO V. 5011E</p>

    <label for="tanggal_bl">Tanggal B/L</label>
    <input type="date" id="tanggal_bl" name="tanggal_bl" value="<?= e($data["tanggal_bl"]) ?>">

    <label for="status">Status</label>
    <select id="status" name="status">
        <option value="ORI" <?= $data["status"] === "ORI" ? "selected" : "" ?>>ORI (belum diterima)</option>
        <option value="SURRENDERED" <?= $data["status"] === "SURRENDERED" ? "selected" : "" ?>>SURRENDERED (sudah diterima)</option>
    </select>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $edit ? "Simpan perubahan" : "Simpan B/L" ?></button>
        <a href="dashboard.php" class="btn btn-ghost">Batal</a>
    </div>
</form>

<script>
// Cek nomor B/L sambil mengetik: sudah ada? atau mirip dengan yang ada?
(function () {
    var input = document.getElementById("nomor_bl");
    var shipper = document.getElementById("shipper");
    var hint = document.getElementById("hint-nomor");
    var idEdit = <?= (int)$id ?>;
    var timer = null;

    function tampil(teks, kelas, daftar) {
        hint.className = "hint-live " + kelas;
        hint.textContent = teks;
        if (daftar) {
            daftar.forEach(function (d) {
                var div = document.createElement("div");
                div.textContent = d;
                hint.appendChild(div);
            });
        }
        hint.hidden = false;
    }

    function render(res) {
        if (res.ada) {
            var a = res.ada;
            var t = "Nomor ini sudah ada: " + a.status + ", " + a.shipper + ". ";
            if (a.status === "ORI") {
                t += "Kalau disimpan dengan status SURRENDERED, data lama otomatis di-upgrade.";
            } else {
                t += "Sudah SURRENDERED, tidak perlu dimasukkan lagi.";
            }
            tampil(t, "hint-warn");
        } else if (res.mirip && res.mirip.length) {
            tampil("Mirip dengan nomor yang sudah ada. Salah ketik?", "hint-warn",
                res.mirip.map(function (m) { return m.nomor_bl + " (" + m.status + ") - " + m.alasan; }));
        } else {
            hint.hidden = true;
        }
    }

    function cek() {
        var v = input.value.trim();
        if (v.length < 5) { hint.hidden = true; return; }
        var url = "cek_bl.php?nomor=" + encodeURIComponent(v) +
                  "&id=" + idEdit +
                  "&shipper=" + encodeURIComponent(shipper.value.trim());
        fetch(url, { credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(render)
            .catch(function () { hint.hidden = true; });
    }

    function tunda() {
        clearTimeout(timer);
        timer = setTimeout(cek, 350);
    }
    input.addEventListener("input", tunda);
    shipper.addEventListener("change", tunda);
})();
</script>

<?php require "includes/layout_bottom.php"; ?>
