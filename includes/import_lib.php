<?php
// includes/import_lib.php
// Baca file Excel (.xlsx) / CSV dan analisis duplikat sebelum disimpan.
// .xlsx dibaca langsung (ZipArchive + SimpleXML), jadi gak butuh Composer.

/* ---------- Pembaca file ---------- */

function kolom_index($ref) {
    $huruf = preg_replace('/[^A-Z]/', '', strtoupper($ref));
    $n = 0;
    for ($i = 0; $i < strlen($huruf); $i++) {
        $n = $n * 26 + (ord($huruf[$i]) - 64);
    }
    return $n - 1;
}

// Hasil: [nomorBaris => [indexKolom => nilaiTeks]]
function baca_xlsx($path) {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Ekstensi PHP "zip" belum aktif. Aktifkan di php.ini (extension=zip), atau simpan file Excel sebagai .csv lalu upload.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('File Excel tidak bisa dibuka. Pastikan formatnya .xlsx yang valid.');
    }

    $shared = [];
    $xmlShared = $zip->getFromName('xl/sharedStrings.xml');
    if ($xmlShared !== false) {
        $sx = simplexml_load_string($xmlShared, 'SimpleXMLElement', LIBXML_NONET);
        if ($sx !== false) {
            foreach ($sx->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string)$si->t;
                } else {
                    $t = '';
                    foreach ($si->r as $r) $t .= (string)$r->t;
                    $shared[] = $t;
                }
            }
        }
    }

    $xmlSheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($xmlSheet === false) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nama = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/[^/]+\.xml$#', $nama)) {
                $xmlSheet = $zip->getFromIndex($i);
                break;
            }
        }
    }
    $zip->close();
    if ($xmlSheet === false) {
        throw new RuntimeException('Sheet pertama di file Excel tidak ketemu.');
    }

    $sheet = simplexml_load_string($xmlSheet, 'SimpleXMLElement', LIBXML_NONET);
    if ($sheet === false) {
        throw new RuntimeException('Isi sheet Excel tidak bisa dibaca.');
    }

    $rows = [];
    $urut = 0;
    foreach ($sheet->sheetData->row as $row) {
        $urut++;
        $nomor = isset($row['r']) ? (int)$row['r'] : $urut;
        $r = [];
        foreach ($row->c as $c) {
            $col = kolom_index((string)$c['r']);
            $tipe = (string)$c['t'];
            if ($tipe === 's') {
                $val = $shared[(int)$c->v] ?? '';
            } elseif ($tipe === 'inlineStr') {
                $val = '';
                if (isset($c->is->t)) {
                    $val = (string)$c->is->t;
                } else {
                    foreach ($c->is->r as $rr) $val .= (string)$rr->t;
                }
            } else {
                $val = isset($c->v) ? (string)$c->v : '';
            }
            $r[$col] = $val;
        }
        $rows[$nomor] = $r;
    }
    ksort($rows);
    return $rows;
}

function baca_csv($path) {
    $fh = fopen($path, 'r');
    if (!$fh) throw new RuntimeException('File CSV tidak bisa dibuka.');
    $baris1 = fgets($fh);
    rewind($fh);
    $pemisah = (substr_count((string)$baris1, ';') > substr_count((string)$baris1, ',')) ? ';' : ',';

    $rows = [];
    $nomor = 0;
    while (($data = fgetcsv($fh, 0, $pemisah)) !== false) {
        $nomor++;
        if ($nomor === 1 && isset($data[0])) {
            $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]); // buang BOM
        }
        $rows[$nomor] = $data;
    }
    fclose($fh);
    return $rows;
}

// Cari baris header lalu petakan kolom: B/L, shipper, vessel, tanggal, status.
function ekstrak_baris(array $rows) {
    $alias = [
        'bl'      => ['BL', 'NOMORBL', 'NOBL', 'BILLOFLADING'],
        'shipper' => ['SHIPPER'],
        'vessel'  => ['VESSEL', 'KAPAL'],
        'tgl'     => ['TGLBL', 'TANGGALBL', 'TANGGAL', 'TGL'],
        'status'  => ['STATUSBL', 'STATUS'],
    ];

    $peta = null;
    $nomorHeader = null;
    foreach ($rows as $nomor => $r) {
        $tmp = [];
        foreach ($r as $col => $val) {
            $n = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)$val));
            foreach ($alias as $field => $daftar) {
                if (in_array($n, $daftar, true) && !isset($tmp[$field])) $tmp[$field] = $col;
            }
        }
        if (isset($tmp['bl'], $tmp['status'])) {
            $peta = $tmp;
            $nomorHeader = $nomor;
            break;
        }
    }
    if ($peta === null) {
        throw new RuntimeException('Header kolom tidak ketemu. Pastikan ada kolom "B / L" dan "STATUS B/L".');
    }

    $baris = [];
    foreach ($rows as $nomor => $r) {
        if ($nomor <= $nomorHeader) continue;
        $ambil = function ($f) use ($r, $peta) {
            return (isset($peta[$f]) && isset($r[$peta[$f]])) ? trim((string)$r[$peta[$f]]) : '';
        };
        $bl = $ambil('bl');
        $shipper = $ambil('shipper');
        $vessel = $ambil('vessel');
        $status = $ambil('status');
        if ($bl === '' && $shipper === '' && $vessel === '' && $status === '') continue; // baris kosong

        $baris[] = [
            'line'       => $nomor,
            'bl'         => $bl,
            'shipper'    => $shipper,
            'vessel'     => $vessel,
            'tgl_raw'    => $ambil('tgl'),
            'status_raw' => $status,
        ];
    }
    return $baris;
}

/* ---------- Analisis ---------- */

// Gabungkan dua data dengan nomor B/L yang sama. SURRENDERED menang atas ORI.
function gabung_data(array $a, array $b) {
    $dasar = $a;
    $lain  = $b;
    if ($b['status'] === 'SURRENDERED' && $a['status'] === 'ORI') {
        $dasar = $b;
        $lain  = $a;
    }
    foreach (['shipper', 'vessel', 'tanggal_bl'] as $f) {
        if ($dasar[$f] === '' || $dasar[$f] === null) $dasar[$f] = $lain[$f];
    }
    $dasar['line']   = min($a['line'], $b['line']);
    $dasar['jumlah'] = $a['jumlah'] + $b['jumlah'];
    $dasar['status_terlihat'] = array_values(array_unique(array_merge($a['status_terlihat'], $b['status_terlihat'])));
    return $dasar;
}

// Analisis tanpa menulis ke database.
function analisis_import(PDO $pdo, array $baris) {
    $final = [];
    $error = [];
    $peringatan = [];

    foreach ($baris as $b) {
        $key = normalisasi_nomor($b['bl']);
        $status = normalisasi_status($b['status_raw']);

        if ($key === '') {
            $error[] = ['line' => $b['line'], 'pesan' => 'Nomor B/L kosong'];
            continue;
        }
        if (strlen($key) > 100) {
            $error[] = ['line' => $b['line'], 'pesan' => 'Nomor B/L terlalu panjang (' . $key . ')'];
            continue;
        }
        if ($status === null) {
            $error[] = ['line' => $b['line'], 'pesan' => 'Status "' . $b['status_raw'] . '" tidak dikenali untuk ' . $key . ' (harus ORI atau SURRENDERED)'];
            continue;
        }

        $tgl = parse_tanggal($b['tgl_raw']);
        if ($b['tgl_raw'] !== '' && $tgl === null) {
            $peringatan[] = ['line' => $b['line'], 'pesan' => 'Tanggal "' . $b['tgl_raw'] . '" tidak terbaca untuk ' . $key . ', disimpan kosong'];
        }

        $data = [
            'nomor_bl'        => $key,
            'shipper'         => mb_substr(rapikan_teks($b['shipper']), 0, 150),
            'vessel'          => mb_substr(rapikan_teks($b['vessel']), 0, 150),
            'tanggal_bl'      => $tgl,
            'status'          => $status,
            'line'            => $b['line'],
            'jumlah'          => 1,
            'status_terlihat' => [$status],
        ];

        $final[$key] = isset($final[$key]) ? gabung_data($final[$key], $data) : $data;
    }

    $dobel = [];
    foreach ($final as $key => $d) {
        if ($d['jumlah'] > 1) $dobel[$key] = $d;
    }

    // Cocokkan dengan database
    $db = [];
    foreach ($pdo->query("SELECT id_bl, nomor_bl, status, shipper, vessel, tanggal_bl FROM bill_of_lading") as $r) {
        $db[strtoupper($r['nomor_bl'])] = $r;
    }

    $aksi = [];
    foreach ($final as $key => $d) {
        if (!isset($db[$key])) {
            $aksi[$key] = 'baru';
        } elseif ($d['status'] === 'SURRENDERED' && $db[$key]['status'] === 'ORI') {
            $aksi[$key] = 'upgrade';
        } elseif ($d['status'] === $db[$key]['status']) {
            $aksi[$key] = 'sama';
        } else {
            $aksi[$key] = 'ditolak'; // file bilang ORI, database sudah SURRENDERED
        }
    }

    // Cari nomor mirip: minimal salah satunya data baru, dan shipper-nya sama
    $semua = array_keys($final + $db);
    $pasangan = [];
    $dilihat = [];
    foreach ($final as $k => $d) {
        if ($aksi[$k] !== 'baru') continue;
        foreach ($semua as $m) {
            if ($k === $m) continue;
            $p = [$k, $m];
            sort($p);
            $id = md5($p[0] . '|' . $p[1]);
            if (isset($dilihat[$id])) continue;

            $alasan = nomor_mirip($k, $m);
            if (!$alasan) continue;

            $infoK = $d;
            $infoM = isset($db[$m]) ? $db[$m] : $final[$m];
            $sk = strtoupper(rapikan_teks($infoK['shipper']));
            $sm = strtoupper(rapikan_teks($infoM['shipper']));
            if ($sk !== '' && $sm !== '' && $sk !== $sm) continue;

            $dilihat[$id] = true;
            $pasangan[] = [
                'id'     => $id,
                'a'      => $p[0],
                'b'      => $p[1],
                'alasan' => $alasan,
            ];
        }
    }

    return [
        'final'      => $final,
        'aksi'       => $aksi,
        'dobel'      => $dobel,
        'error'      => $error,
        'peringatan' => $peringatan,
        'pasangan'   => $pasangan,
        'db'         => $db,
        'total_baris' => count($baris),
    ];
}

// Terapkan keputusan user untuk pasangan nomor mirip.
// $keputusan[id] = 'pisah' | 'a' | 'b'  (a/b = nomor mana yang dipakai)
function terapkan_keputusan(array $an, array $keputusan) {
    $final = $an['final'];
    $aksi  = $an['aksi'];
    $db    = $an['db'];
    $naikDb = [];      // id_bl => true, status di database dinaikkan jadi SURRENDERED
    $digabung = 0;

    foreach ($an['pasangan'] as $p) {
        $pilih = $keputusan[$p['id']] ?? 'pisah';
        if ($pilih !== 'a' && $pilih !== 'b') continue;

        $kanon = $pilih === 'a' ? $p['a'] : $p['b'];
        $lain  = $pilih === 'a' ? $p['b'] : $p['a'];

        // Yang boleh dibuang hanya data baru dari file
        if (!isset($final[$lain]) || ($aksi[$lain] ?? '') !== 'baru') continue;

        if (isset($db[$kanon])) {
            if ($final[$lain]['status'] === 'SURRENDERED' && $db[$kanon]['status'] === 'ORI') {
                $naikDb[$db[$kanon]['id_bl']] = true;
            }
            unset($final[$lain]);
            $digabung++;
        } elseif (isset($final[$kanon]) && ($aksi[$kanon] ?? '') === 'baru') {
            $final[$kanon] = gabung_data($final[$kanon], $final[$lain]);
            unset($final[$lain]);
            $digabung++;
        }
    }

    return ['final' => $final, 'naikDb' => $naikDb, 'digabung' => $digabung];
}
