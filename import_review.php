<?php
require "auth.php";
require "config/koneksi.php";
require "includes/helpers.php";
require "includes/import_lib.php";

$baris = $_SESSION["import_baris"] ?? null;
if (!$baris) {
    flash_set("error", "Tidak ada file yang sedang ditinjau. Upload dulu.");
    header("Location: import.php");
    exit;
}

$an = analisis_import($pdo, $baris);

$hitung = ["baru" => 0, "upgrade" => 0, "sama" => 0, "ditolak" => 0];
foreach ($an["aksi"] as $a) $hitung[$a]++;
$dobelDibuang = 0;
foreach ($an["dobel"] as $d) $dobelDibuang += $d["jumlah"] - 1;
$bisaSimpan = ($hitung["baru"] + $hitung["upgrade"]) > 0;

// Info satu nomor untuk tabel pasangan
function info_nomor($key, $an) {
    if (isset($an["db"][$key])) {
        $r = $an["db"][$key];
        return ["sumber" => "Sudah di database", "status" => $r["status"], "shipper" => $r["shipper"], "vessel" => $r["vessel"], "tgl" => $r["tanggal_bl"], "db" => true];
    }
    $r = $an["final"][$key];
    return ["sumber" => "Dari file (baris " . $r["line"] . ")", "status" => $r["status"], "shipper" => $r["shipper"], "vessel" => $r["vessel"], "tgl" => $r["tanggal_bl"], "db" => false];
}

$judul = "Tinjau import";
$aktif = "import";
require "includes/layout_top.php";
?>

<div class="page-head">
    <div>
        <h1>Tinjau import</h1>
        <p class="muted"><?= e($_SESSION["import_nama"] ?? "") ?> &middot; <?= $an["total_baris"] ?> baris dibaca. Belum ada yang disimpan.</p>
    </div>
</div>

<div class="stat-strip stat-strip-6">
    <div class="stat stat-surr"><span class="stat-num"><?= $hitung["baru"] ?></span><span class="stat-label">B/L baru</span></div>
    <div class="stat"><span class="stat-num"><?= $hitung["upgrade"] ?></span><span class="stat-label">Di-upgrade ke SURRENDERED</span></div>
    <div class="stat"><span class="stat-num"><?= $dobelDibuang ?></span><span class="stat-label">Baris ganda digabung</span></div>
    <div class="stat"><span class="stat-num"><?= $hitung["sama"] + $hitung["ditolak"] ?></span><span class="stat-label">Sudah ada, dilewati</span></div>
    <div class="stat stat-ori"><span class="stat-num"><?= count($an["pasangan"]) ?></span><span class="stat-label">Nomor mirip, perlu dicek</span></div>
    <div class="stat <?= $an["error"] ? "stat-bahaya" : "" ?>"><span class="stat-num"><?= count($an["error"]) ?></span><span class="stat-label">Baris bermasalah</span></div>
</div>

<?php if ($an["error"]): ?>
    <section class="blok">
        <h2>Baris bermasalah (tidak diimport)</h2>
        <div class="table-wrap">
            <table class="bl-table">
                <thead><tr><th>Baris Excel</th><th>Masalah</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($an["error"], 0, 30) as $er): ?>
                    <tr><td class="nowrap"><?= (int)$er["line"] ?></td><td><?= e($er["pesan"]) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($an["error"]) > 30): ?><p class="muted">&hellip; dan <?= count($an["error"]) - 30 ?> lainnya.</p><?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($an["peringatan"]): ?>
    <section class="blok">
        <h2>Peringatan tanggal</h2>
        <ul class="daftar-biasa">
            <?php foreach (array_slice($an["peringatan"], 0, 20) as $pw): ?>
                <li>Baris <?= (int)$pw["line"] ?>: <?= e($pw["pesan"]) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($an["dobel"]): ?>
    <section class="blok">
        <h2>Nomor ganda yang digabung otomatis</h2>
        <p class="muted">SURRENDERED menang atas ORI. Kalau statusnya sama, satu baris dibuang.</p>
        <div class="table-wrap">
            <table class="bl-table">
                <thead><tr><th>Nomor B/L</th><th>Muncul</th><th>Status yang terlihat</th><th>Dipakai</th></tr></thead>
                <tbody>
                <?php foreach ($an["dobel"] as $key => $d): ?>
                    <tr>
                        <td class="mono-strong"><?= e($key) ?></td>
                        <td><?= (int)$d["jumlah"] ?>x</td>
                        <td><?= e(implode(" + ", $d["status_terlihat"])) ?></td>
                        <td><?= badge_status($d["status"]) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<form method="POST" action="proses_import.php">
    <?= csrf_field() ?>

    <?php if ($an["pasangan"]): ?>
        <section class="blok">
            <h2>Nomor mirip: salah ketik atau memang berbeda?</h2>
            <p class="muted">Sistem tidak menebak. Default-nya disimpan terpisah, ubah kalau kamu yakin itu salah ketik.</p>

            <?php foreach ($an["pasangan"] as $p):
                $ia = info_nomor($p["a"], $an);
                $ib = info_nomor($p["b"], $an);
            ?>
                <fieldset class="pasangan">
                    <legend>Alasan: <?= e($p["alasan"]) ?></legend>
                    <div class="pasangan-grid">
                        <?php foreach ([["a", $p["a"], $ia], ["b", $p["b"], $ib]] as $item): ?>
                            <div class="pasangan-item">
                                <div class="mono-strong"><?= e($item[1]) ?></div>
                                <div class="muted"><?= e($item[2]["sumber"]) ?></div>
                                <div><?= badge_status($item[2]["status"]) ?></div>
                                <div class="kecil"><?= e($item[2]["shipper"]) ?></div>
                                <div class="kecil"><?= e($item[2]["vessel"]) ?> &middot; <?= e(format_tanggal($item[2]["tgl"])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="pilihan">
                        <label class="radio-row">
                            <input type="radio" name="keputusan[<?= e($p["id"]) ?>]" value="pisah" checked>
                            Beda B/L, simpan terpisah
                        </label>
                        <?php
                        // Nomor dari file yang belum ada di database boleh jadi "yang benar".
                        // Kalau salah satunya sudah ada di database, hanya itu yang boleh jadi acuan.
                        $bolehA = $ia["db"] || !$ib["db"];
                        $bolehB = $ib["db"] || !$ia["db"];
                        ?>
                        <?php if ($bolehA): ?>
                            <label class="radio-row">
                                <input type="radio" name="keputusan[<?= e($p["id"]) ?>]" value="a">
                                Salah ketik, pakai <span class="mono-strong"><?= e($p["a"]) ?></span>
                            </label>
                        <?php endif; ?>
                        <?php if ($bolehB): ?>
                            <label class="radio-row">
                                <input type="radio" name="keputusan[<?= e($p["id"]) ?>]" value="b">
                                Salah ketik, pakai <span class="mono-strong"><?= e($p["b"]) ?></span>
                            </label>
                        <?php endif; ?>
                    </div>
                </fieldset>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <div class="form-actions form-actions-sticky">
        <?php if ($bisaSimpan): ?>
            <button type="submit" class="btn btn-primary">Simpan ke database</button>
        <?php else: ?>
            <span class="muted">Tidak ada data baru yang bisa disimpan.</span>
        <?php endif; ?>
        <a href="import.php?batal=1" class="btn btn-ghost">Batalkan</a>
    </div>
</form>

<?php require "includes/layout_bottom.php"; ?>
