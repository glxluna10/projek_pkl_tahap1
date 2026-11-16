<?php
// includes/helpers.php
// Fungsi bantu yang dipakai di banyak halaman.

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* ---------- CSRF ---------- */
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_wajib() {
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        die("Sesi form kedaluwarsa. Kembali ke halaman sebelumnya dan coba lagi.");
    }
}

/* ---------- Flash message ---------- */
function flash_set($tipe, $pesan) {
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

function flash_ambil() {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function wajib_admin() {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        flash_set('error', 'Aksi ini hanya untuk admin.');
        header('Location: dashboard.php');
        exit;
    }
}

/* ---------- Normalisasi data B/L ---------- */

// Huruf besar, semua spasi dibuang. "kav blw 1" dan "KAVBLW1" dianggap sama.
function normalisasi_nomor($s) {
    return strtoupper(preg_replace('/\s+/', '', trim((string)$s)));
}

function rapikan_teks($s) {
    return preg_replace('/\s+/', ' ', trim((string)$s));
}

// Hasil: 'ORI', 'SURRENDERED', atau null kalau tidak dikenali.
function normalisasi_status($s) {
    $s = strtoupper(trim((string)$s));
    if ($s === '') return null;
    if ($s === 'ORI' || $s === 'ORIGINAL') return 'ORI';
    if (preg_match('/^SURR?END/', $s)) return 'SURRENDERED';
    return null;
}

// Aturan inti: SURRENDERED menang atas ORI.
function gabung_status($a, $b) {
    return ($a === 'SURRENDERED' || $b === 'SURRENDERED') ? 'SURRENDERED' : 'ORI';
}

/* ---------- Tanggal ---------- */
function parse_tanggal($v) {
    if ($v === null) return null;
    if ($v instanceof DateTimeInterface) return $v->format('Y-m-d');

    $s = trim((string)$v);
    if ($s === '') return null;

    // Angka murni = serial tanggal Excel (46186 = 2026-06-xx)
    if (preg_match('/^\d+(\.\d+)?$/', $s)) {
        $n = (int)floor((float)$s);
        if ($n >= 20000 && $n <= 80000) {
            $d = new DateTime('1899-12-30');
            $d->modify('+' . $n . ' days');
            return $d->format('Y-m-d');
        }
        return null;
    }

    $bulan = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'mei' => 5, 'may' => 5,
        'jun' => 6, 'jul' => 7, 'agu' => 8, 'agus' => 8, 'aug' => 8,
        'sep' => 9, 'sept' => 9, 'okt' => 10, 'oct' => 10, 'nov' => 11,
        'des' => 12, 'dec' => 12,
    ];

    // 25-Agus-25, 10 Des 2025, 08/Des/25
    if (preg_match('/^(\d{1,2})[\s\-\/\.]([A-Za-z]+)[\s\-\/\.](\d{2,4})$/', $s, $m)) {
        $b = $bulan[strtolower($m[2])] ?? null;
        if (!$b) return null;
        $h = (int)$m[1];
        $th = (int)$m[3];
        if ($th < 100) $th += 2000;
        return checkdate($b, $h, $th) ? sprintf('%04d-%02d-%02d', $th, $b, $h) : null;
    }

    // 2025-12-10
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $s, $m)) {
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1])
            ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
    }

    // 10/12/2025 atau 10-12-25 (hari/bulan/tahun)
    if (preg_match('/^(\d{1,2})[\-\/\.](\d{1,2})[\-\/\.](\d{2,4})$/', $s, $m)) {
        $th = (int)$m[3];
        if ($th < 100) $th += 2000;
        return checkdate((int)$m[2], (int)$m[1], $th)
            ? sprintf('%04d-%02d-%02d', $th, $m[2], $m[1]) : null;
    }

    return null;
}

function format_tanggal($ymd) {
    if (!$ymd) return '-';
    $nama = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $p = explode('-', $ymd);
    if (count($p) !== 3) return $ymd;
    return (int)$p[2] . ' ' . $nama[(int)$p[1]] . ' ' . $p[0];
}

/* ---------- Deteksi nomor mirip (kemungkinan salah ketik) ---------- */
// Mengembalikan alasan (string) kalau dua nomor mirip, atau false kalau tidak.
// Sengaja TIDAK menandai:
//  - beda satu angka saja (mis. ...065 vs ...066): biasanya B/L berurutan yang sah
//  - beda huruf terakhir (mis. ...225A vs ...225B): biasanya B/L pecahan
function nomor_mirip($a, $b) {
    if ($a === $b) return false;
    $la = strlen($a);
    $lb = strlen($b);
    if ($la < 6 || $lb < 6) return false;

    if ($la === $lb) {
        $beda = [];
        for ($i = 0; $i < $la; $i++) {
            if ($a[$i] !== $b[$i]) {
                $beda[] = $i;
                if (count($beda) > 2) return false;
            }
        }
        if (count($beda) === 2
            && $beda[1] === $beda[0] + 1
            && $a[$beda[0]] === $b[$beda[1]]
            && $a[$beda[1]] === $b[$beda[0]]) {
            return 'dua karakter bertukar posisi';
        }
        if (count($beda) === 1) {
            $i = $beda[0];
            if (ctype_digit($a[$i]) && ctype_digit($b[$i])) return false;
            if ($i === $la - 1) return false;
            return 'satu huruf berbeda';
        }
        return false;
    }

    if (abs($la - $lb) === 1) {
        if ($la < $lb) { $pendek = $a; $panjang = $b; } else { $pendek = $b; $panjang = $a; }
        $n = strlen($panjang);
        for ($i = 0; $i < $n; $i++) {
            if (substr($panjang, 0, $i) . substr($panjang, $i + 1) === $pendek) {
                if ($i === $n - 1 && ctype_alpha($panjang[$i])) return false;
                return 'ada karakter yang kurang atau berlebih';
            }
        }
    }
    return false;
}

// Cari nomor mirip di database. Kalau $shipper diisi, hanya shipper yang sama yang dihitung.
function cari_mirip(PDO $pdo, $key, $kecualiId = 0, $shipper = '') {
    $len = strlen($key);
    $st = $pdo->prepare(
        "SELECT id_bl, nomor_bl, shipper, status FROM bill_of_lading
         WHERE CHAR_LENGTH(nomor_bl) BETWEEN ? AND ? AND id_bl <> ?"
    );
    $st->execute([$len - 1, $len + 1, (int)$kecualiId]);

    $shipperKey = strtoupper(rapikan_teks($shipper));
    $hasil = [];
    foreach ($st as $r) {
        if ($shipperKey !== '' && strtoupper(rapikan_teks($r['shipper'])) !== $shipperKey) continue;
        $alasan = nomor_mirip($key, strtoupper($r['nomor_bl']));
        if ($alasan) {
            $r['alasan'] = $alasan;
            $hasil[] = $r;
        }
    }
    return array_slice($hasil, 0, 5);
}

/* ---------- Tampilan ---------- */
function badge_status($status) {
    $kelas = $status === 'SURRENDERED' ? 'badge-surr' : 'badge-ori';
    return '<span class="badge ' . $kelas . '">' . e($status) . '</span>';
}

function ikon_tema() {
    return '<button type="button" class="theme-toggle" aria-label="Ganti tema">'
        . '<svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>'
        . '<svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>'
        . '</button>';
}
