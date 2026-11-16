<?php
require "auth.php";
require "config/koneksi.php";
require "includes/helpers.php";

$q      = trim($_GET["q"] ?? "");
$status = $_GET["status"] ?? "";
if (!in_array($status, ["ORI", "SURRENDERED"], true)) $status = "";
$hal    = max(1, (int)($_GET["hal"] ?? 1));
$per    = 25;

$where  = [];
$params = [];
if ($q !== "") {
    $like = "%" . addcslashes($q, "%_\\") . "%";
    $where[] = "(nomor_bl LIKE ? OR shipper LIKE ? OR vessel LIKE ?)";
    array_push($params, $like, $like, $like);
}
if ($status !== "") {
    $where[] = "status = ?";
    $params[] = $status;
}
$sqlWhere = $where ? "WHERE " . implode(" AND ", $where) : "";

$st = $pdo->prepare("SELECT COUNT(*) FROM bill_of_lading $sqlWhere");
$st->execute($params);
$totalHasil = (int)$st->fetchColumn();
$jumlahHal  = max(1, (int)ceil($totalHasil / $per));
if ($hal > $jumlahHal) $hal = $jumlahHal;
$offset = ($hal - 1) * $per;

$st = $pdo->prepare(
    "SELECT * FROM bill_of_lading $sqlWhere
     ORDER BY tanggal_bl DESC, id_bl DESC
     LIMIT " . (int)$per . " OFFSET " . (int)$offset
);
$st->execute($params);
$daftar = $st->fetchAll();

// Ringkasan keseluruhan (tidak ikut filter)
$stat = ["ORI" => 0, "SURRENDERED" => 0];
foreach ($pdo->query("SELECT status, COUNT(*) AS n FROM bill_of_lading GROUP BY status") as $r) {
    $stat[$r["status"]] = (int)$r["n"];
}
$total = $stat["ORI"] + $stat["SURRENDERED"];

function url_dashboard(array $ubah = []) {
    global $q, $status;
    $p = array_merge(["q" => $q, "status" => $status], $ubah);
    $p = array_filter($p, function ($v) { return $v !== "" && $v !== null; });
    $qs = http_build_query($p);
    return "dashboard.php" . ($qs ? "?" . $qs : "");
}

$judul = "Data B/L";
$aktif = "dashboard";
require "includes/layout_top.php";
?>

<div class="page-head">
    <div>
        <h1>Data Bill of Lading</h1>
        <p class="muted">SURRENDERED = dokumen sudah diterima. ORI = belum.</p>
    </div>
    <div class="head-actions">
        <a href="import.php" class="btn btn-ghost">Import Excel</a>
        <a href="bl_form.php" class="btn btn-primary">Tambah B/L</a>
    </div>
</div>

<div class="stat-strip">
    <div class="stat"><span class="stat-num"><?= $total ?></span><span class="stat-label">Total B/L</span></div>
    <div class="stat stat-ori"><span class="stat-num"><?= $stat["ORI"] ?></span><span class="stat-label">ORI (belum diterima)</span></div>
    <div class="stat stat-surr"><span class="stat-num"><?= $stat["SURRENDERED"] ?></span><span class="stat-label">SURRENDERED</span></div>
</div>

<form method="GET" action="dashboard.php" class="toolbar">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nomor B/L, shipper, atau vessel" aria-label="Cari">
    <?php if ($status !== ""): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <button type="submit" class="btn btn-primary">Cari</button>
    <div class="chips">
        <a href="<?= e(url_dashboard(["status" => "", "hal" => ""])) ?>" class="chip <?= $status === "" ? "aktif" : "" ?>">Semua</a>
        <a href="<?= e(url_dashboard(["status" => "ORI", "hal" => ""])) ?>" class="chip <?= $status === "ORI" ? "aktif" : "" ?>">ORI</a>
        <a href="<?= e(url_dashboard(["status" => "SURRENDERED", "hal" => ""])) ?>" class="chip <?= $status === "SURRENDERED" ? "aktif" : "" ?>">SURRENDERED</a>
    </div>
</form>

<?php if ($total === 0): ?>
    <div class="manifest-panel">
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="7" width="18" height="13" rx="1.5"/>
            <path d="M7 7V5.5A1.5 1.5 0 0 1 8.5 4h7A1.5 1.5 0 0 1 17 5.5V7"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
        </svg>
        <div>
            <h3>Belum ada data B/L</h3>
            <p>Tambah satu per satu, atau import sekaligus dari file Excel yang sudah ada.</p>
            <div class="action-row">
                <a href="import.php">Import dari Excel</a>
            </div>
        </div>
    </div>
<?php elseif (!$daftar): ?>
    <div class="manifest-panel">
        <div>
            <h3>Tidak ada hasil</h3>
            <p>Tidak ada B/L yang cocok dengan pencarian ini. Coba kata kunci lain atau ganti filter status.</p>
        </div>
    </div>
<?php else: ?>
    <p class="muted hasil-info"><?= $totalHasil ?> data ditemukan<?= $jumlahHal > 1 ? " &middot; halaman $hal dari $jumlahHal" : "" ?></p>
    <div class="table-wrap">
        <table class="bl-table">
            <thead>
                <tr>
                    <th>Nomor B/L</th>
                    <th>Shipper</th>
                    <th>Vessel</th>
                    <th>Tgl B/L</th>
                    <th>Status</th>
                    <th class="aksi-col">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($daftar as $r): ?>
                <tr>
                    <td class="mono-strong"><?= e($r["nomor_bl"]) ?></td>
                    <td><?= e($r["shipper"]) ?></td>
                    <td><?= e($r["vessel"]) ?></td>
                    <td class="nowrap"><?= e(format_tanggal($r["tanggal_bl"])) ?></td>
                    <td><?= badge_status($r["status"]) ?></td>
                    <td class="aksi-col">
                        <a href="bl_form.php?id=<?= (int)$r["id_bl"] ?>" class="link-aksi">Edit</a>
                        <?php if (($_SESSION["role"] ?? "") === "admin"): ?>
                            <form method="POST" action="bl_hapus.php" class="inline-form"
                                  onsubmit="return confirm('Hapus B/L <?= e(addslashes($r["nomor_bl"])) ?>? Data tidak bisa dikembalikan.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$r["id_bl"] ?>">
                                <button type="submit" class="link-aksi link-bahaya">Hapus</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($jumlahHal > 1): ?>
        <nav class="pager" aria-label="Halaman">
            <?php if ($hal > 1): ?>
                <a href="<?= e(url_dashboard(["hal" => $hal - 1])) ?>">Sebelumnya</a>
            <?php endif; ?>
            <span>Halaman <?= $hal ?> / <?= $jumlahHal ?></span>
            <?php if ($hal < $jumlahHal): ?>
                <a href="<?= e(url_dashboard(["hal" => $hal + 1])) ?>">Berikutnya</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require "includes/layout_bottom.php"; ?>
