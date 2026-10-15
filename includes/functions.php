<?php
require_once __DIR__ . '/db.php';

/**
 * Fungsi bantu umum: escaping, settings, format tanggal, nomor pendaftaran,
 * log aktivitas, upload, dsb.
 */

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function post(string $k, $def = ''): string
{
    return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $def;
}
function get(string $k, $def = ''): string
{
    return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $def;
}

function redirect(string $url): never { header('Location: ' . $url); exit; }

function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['t' => $type, 'm' => $msg];
}
function flash_get(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------------- Settings ---------------- */
function setting(string $key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (rows("SELECT key_name, value FROM settings") as $r) {
            $cache[$r['key_name']] = $r['value'];
        }
    }
    return $cache[$key] ?? $default;
}

function setting_save(string $key, $value): void
{
    q("INSERT INTO settings (key_name, value) VALUES (?, ?)
       ON DUPLICATE KEY UPDATE value = VALUES(value)", [$key, (string)$value]);
}

/* ---------------- Tanggal & angka ---------------- */
const HARI_ID = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
const BULAN_ID = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus',
                  'September','Oktober','November','Desember'];

function tgl_id(?string $date, bool $hari = false, bool $jam = false): string
{
    if (!$date || $date === '0000-00-00') return '-';
    $ts = strtotime($date);
    if (!$ts) return '-';
    $d = date('j', $ts);
    $m = BULAN_ID[(int)date('n', $ts)];
    $y = date('Y', $ts);
    $out = "$d $m $y";
    if ($hari) $out = HARI_ID[date('w', $ts)] . ', ' . $out;
    if ($jam && strpos($date, ':') !== false) $out .= ' ' . date('H:i', $ts) . ' WIB';
    return $out;
}

function today(): string { return date('Y-m-d'); }

function numfmt(int|float|string $n): string { return number_format((float)$n, 0, ',', '.'); }

/* ---------------- Nomor pendaftaran ----------------
 * Format diset di dashboard, placeholder: {kode} {y} {m} {mm} {d} {dd} {yyyy} {n} {nn} {nnn} {nnnn}
 */
function render_format_nomor(string $format, string $kode_jenis, int $urutan, bool $with_number = true): string
{
    $now = time();
    $out = strtr($format, [
        '{KODE}' => strtoupper($kode_jenis),
        '{kode}' => strtolower($kode_jenis),
        '{Kode}' => ucfirst(strtolower($kode_jenis)),
        '{yyyy}' => date('Y', $now),
        '{yy}'   => date('y', $now),
        '{mm}'   => date('m', $now),
        '{m}'    => (string)(int)date('m', $now),
        '{dd}'   => date('d', $now),
        '{d}'    => (string)(int)date('d', $now),
        '{nnnn}' => $with_number ? str_pad((string)$urutan, 4, '0', STR_PAD_LEFT) : '{nnnn}',
        '{nnn}'  => $with_number ? str_pad((string)$urutan, 3, '0', STR_PAD_LEFT) : '{nnn}',
        '{nn}'   => $with_number ? str_pad((string)$urutan, 2, '0', STR_PAD_LEFT) : '{nn}',
        '{n}'    => $with_number ? (string)$urutan : '{n}',
    ]);
    return $out;
}

/** Buat nomor pendaftaran berikutnya berdasarkan format dari dashboard.
 *  Counter diambil dari trailing digits nomor terakhir dengan prefix yang sama. */
function generate_nomor_pendaftaran(string $kode_jenis): string
{
    $format = setting('format_nomor', 'DKC/{yy}/{mm}/{nnnn}');
    if (!str_contains($format, '{n')) $format = rtrim($format, '/-') . '/{nnnn}';
    // contoh render dgn angka 0 utk mengetahui prefix (sebelum bagian {n..})
    $pos   = mb_strpos($format, '{n');
    $preF  = substr($format, 0, $pos);
    $prefixContoh = str_replace(['{nnnn}','{nnn}','{nn}','{n}'], '', $preF);
    for ($try = 0; $try < 200; $try++) {
        $rendered = render_format_nomor($preF, $kode_jenis, 0, false);
        $last = val("SELECT nomor_pendaftaran FROM permohonan WHERE nomor_pendaftaran LIKE ? ORDER BY id DESC LIMIT 1",
                    [str_replace('//', '/', $rendered) . '%']);
        $next = 1;
        if ($last && preg_match('/(\d+)\s*$/', $last, $m)) $next = (int)$m[1] + 1;
        $nomor = render_format_nomor($format, $kode_jenis, $next);
        if (!val("SELECT id FROM permohonan WHERE nomor_pendaftaran = ?", [$nomor])) return $nomor;
        usleep(50000);
    }
    return render_format_nomor($format, $kode_jenis, time() % 1000000);
}

/* ---------------- Kuota & antrean ---------------- */
function kuota_sisa(int $jenis_id, string $tanggal): int
{
    $kuota = (int)val("SELECT kuota_per_hari FROM jenis_permohonan WHERE id = ?", [$jenis_id]);
    if ($kuota <= 0) $kuota = (int)setting('quota_global', 50);
    $terpakai = (int)val("SELECT COUNT(*) FROM permohonan WHERE jenis_id = ? AND tgl_verifikasi = ? AND status != 'dibatalkan'",
                        [$jenis_id, $tanggal]);
    return max(0, $kuota - $terpakai);
}

/** Tanggal hari kerja (Senin–Jumat). Sabtu & Minggu dilalui tanpa dihitung. */
function next_workday(string $from = 'now', int $addWorkdays = 0): string
{
    $ts = strtotime($from);
    for ($i = 0; $i < $addWorkdays; $i++) {
        do { $ts = strtotime('+1 day', $ts); } while (in_array((int)date('N', $ts), [6, 7]));
    }
    // pastikan hasil akhir juga hari kerja
    while (in_array((int)date('N', $ts), [6, 7])) { $ts = strtotime('+1 day', $ts); }
    return date('Y-m-d', $ts);
}

/** Tambah N hari kerja dari sebuah tanggal Y-m-d */
function add_workdays(string $ymd, int $n): string
{
    return next_workday($ymd, $n);
}

function pick_loket(int $jenis_id, string $jenis_loket = 'penerimaan'): ?array
{
    return row("SELECT l.* FROM loket l
                JOIN loket_jenis lj ON lj.loket_id = l.id
                WHERE lj.jenis_id = ? AND l.jenis = ? AND l.aktif = 1
                ORDER BY RAND() LIMIT 1", [$jenis_id, $jenis_loket]);
}

/* ---------------- Log aktivitas ---------------- */
function log_activity(string $aktivitas, string $modul = '', string $keterangan = ''): void
{
    $u = current_user();
    q("INSERT INTO activity_log (user_id, user_name, peran, aktivitas, keterangan, modul, ip)
       VALUES (?,?,?,?,?,?,?)",
      [$u['id'] ?? null, $u['nama'] ?? 'Publik/Warga', $u['role'] ?? 'warga',
       $aktivitas, $keterangan, $modul, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
}

/* ---------------- Autentikasi & otorisasi ---------------- */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}
function is_logged(): bool { return isset($_SESSION['user']); }

function require_login(): void
{
    if (!is_logged()) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
        redirect('login.php');
    }
}

function require_role(array $roles): void
{
    require_login();
    if (!in_array($_SESSION['user']['role'], $roles, true)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;text-align:center;padding:60px">
             <h1>403</h1><p>Anda tidak memiliki akses ke halaman ini.</p>
             <a href="dashboard.php">Kembali ke Dashboard</a></div>');
    }
}

function role_label(string $r): string
{
    return ['admin'=>'Administrator','kepala_dinas'=>'Kepala Dinas',
            'kepala_bidang'=>'Kepala Bidang','petugas'=>'Petugas'][$r] ?? $r;
}

function status_badge(string $s): string
{
    $map = [
        'menunggu_verifikasi' => ['Menunggu Verifikasi', 'warning'],
        'diproses'            => ['Diproses', 'info'],
        'selesai'             => ['Selesai', 'success'],
        'dibatalkan'          => ['Dibatalkan', 'danger'],
    ];
    [$label, $c] = $map[$s] ?? [$s, 'secondary'];
    return '<span class="badge bg-' . $c . '">' . $label . '</span>';
}

/* ---------------- Upload file ---------------- */
function handle_upload(array $file, string $subdir = 'dokumen'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    $allowed = ['pdf','jpg','jpeg','png'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) return null;
    if ($file['size'] > 4 * 1024 * 1024) return null;
    $dir = UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return $subdir . '/' . $name;
    }
    return null;
}

function json_out($data): void
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
