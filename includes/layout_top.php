<?php
// includes/layout_top.php
// Variabel yang dibaca: $judul (string), $aktif ('dashboard' | 'import')
// Wajib sudah require auth.php dan includes/helpers.php sebelum file ini.
$judul = $judul ?? 'B/L Control';
$aktif = $aktif ?? '';
$flash = flash_ambil();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> - B/L Control</title>
    <link rel="stylesheet" href="style.css">
    <script src="theme.js"></script>
</head>
<body>
<div class="dashboard-container">
    <header class="topbar">
        <div class="brand-group">
            <span class="brand">B/L Control</span>
            <nav class="nav">
                <a href="dashboard.php" class="<?= $aktif === 'dashboard' ? 'aktif' : '' ?>">Data B/L</a>
                <a href="import.php" class="<?= $aktif === 'import' ? 'aktif' : '' ?>">Import Excel</a>
            </nav>
        </div>
        <div class="user-info">
            <?= ikon_tema() ?>
            <span class="user-greet">
                Halo, <strong><?= e($_SESSION["nama"]) ?></strong>
                (<?= e($_SESSION["role"]) ?>)
            </span>
            <a href="logout.php" class="btn-logout">Keluar</a>
        </div>
    </header>

    <main>
        <?php if ($flash): ?>
            <div class="flash flash-<?= e($flash['tipe']) ?>"><?= e($flash['pesan']) ?></div>
        <?php endif; ?>
