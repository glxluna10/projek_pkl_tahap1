<?php
session_start();
require "includes/helpers.php";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - B/L Control</title>
    <link rel="stylesheet" href="style.css">
    <script src="theme.js"></script>
</head>
<body>
    <div class="login-container">
        <div class="theme-floating"><?= ikon_tema() ?></div>
        <svg class="route-backdrop" viewBox="0 0 900 700" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
            <path d="M -40 560 C 160 480, 220 620, 380 520 S 620 340, 780 380 S 980 300, 1040 200"
                  fill="none" stroke="#49C6B0" stroke-width="1.5" stroke-dasharray="2 10" stroke-linecap="round" opacity="0.5"/>
            <circle cx="-40" cy="560" r="4" fill="#49C6B0" opacity="0.6"/>
            <circle cx="380" cy="520" r="4" fill="#D6A24B" opacity="0.7"/>
            <circle cx="780" cy="380" r="4" fill="#49C6B0" opacity="0.6"/>
            <circle cx="1040" cy="200" r="5" fill="#D6A24B" opacity="0.8"/>
            <path d="M -60 140 C 120 220, 300 90, 460 160 S 760 260, 960 120"
                  fill="none" style="stroke:var(--text-primary)" stroke-width="1" stroke-dasharray="1 8" stroke-linecap="round" opacity="0.15"/>
        </svg>

        <div class="login-box">
            <div class="status-pill" style="margin-bottom:14px;">
                <span class="status-dot"></span> Sistem aktif
            </div>
            <div class="brand">B/L Control</div>
            <p class="subtitle">Pendataan &amp; monitoring Bill of Lading</p>

            <?php if (isset($_GET["pesan"])): ?>
                <div class="<?= (($_GET["tipe"] ?? "") === "sukses") ? "alert-success" : "alert-error" ?>"><?= htmlspecialchars($_GET["pesan"]) ?></div>
            <?php endif; ?>

            <form method="POST" action="proses_login.php">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus>

                <label for="password">Password</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="password-toggle" data-target="password" aria-label="Tampilkan password">
                        <svg class="icon-eye-open" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/></svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:none"><path d="M3 3l18 18M10.6 10.6a3 3 0 004.24 4.24M9.9 5.1A11 11 0 0123 12s-1.5 2.6-4.2 4.6M6.1 6.1C3.6 7.8 1 12 1 12s1.9 3.3 5 5.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                </div>

                <button type="submit">Masuk</button>
            </form>

            <p class="auth-switch">Belum punya akun? <a href="register.php">Daftar di sini</a></p>
        </div>
    </div>
    <script src="app.js"></script>
</body>
</html>