<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/pdf.php';

/**
 * CSRF token helpers
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}
function csrf_verify(): void
{
    $t = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        die('Sesi kedaluwarsa / token tidak valid. Silakan muat ulang halaman.');
    }
}

/**
 * Captcha gambar berbasis GD (session-based)
 */
function captcha_generate(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 5; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
    $_SESSION['captcha'] = $code;

    $w = 150; $h = 48;
    $img = imagecreatetruecolor($w, $h);
    $bg = imagecolorallocate($img, 238, 242, 250);
    imagefilledrectangle($img, 0, 0, $w, $h, $bg);
    // noise lines
    for ($i = 0; $i < 5; $i++) {
        $c = imagecolorallocate($img, random_int(150, 210), random_int(150, 210), random_int(170, 220));
        imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
    }
    for ($i = 0; $i < 60; $i++) {
        $c = imagecolorallocate($img, random_int(160, 220), random_int(160, 220), random_int(180, 230));
        imagesetpixel($img, random_int(0, $w), random_int(0, $h), $c);
    }
    // huruf
    $font = 5;
    $x = 12;
    foreach (str_split($code) as $ch) {
        $c = imagecolorallocate($img, random_int(20, 80), random_int(40, 90), random_int(120, 180));
        imagestring($img, $font, $x, random_int(12, 26), $ch, $c);
        $x += imagefontwidth($font) + random_int(4, 8);
    }
    header('Content-Type: image/png');
    imagepng($img);
    imagedestroy($img);
    exit;
}

function captcha_check(string $input): bool
{
    $ok = isset($_SESSION['captcha']) && strtoupper(trim($input)) === strtoupper($_SESSION['captcha']);
    unset($_SESSION['captcha']);
    return $ok;
}

/**
 * Validasi NIK 16 digit
 */
function valid_nik(string $nik): bool
{
    return (bool)preg_match('/^\d{16}$/', $nik);
}

/**
 * Ambil data permohonan lengkap untuk PDF/cek publik
 */
function permohonan_detail(int $id): ?array
{
    $p = row("SELECT p.*, j.nama AS jenis_nama, j.kode AS jenis_kode, l.nomor AS loket_nomor, l.nama AS loket_nama
              FROM permohonan p
              JOIN jenis_permohonan j ON j.id = p.jenis_id
              LEFT JOIN loket l ON l.id = p.loket_id
              WHERE p.id = ?", [$id]);
    if (!$p) return null;
    $p['data_isian'] = json_decode($p['data_isian'] ?? '{}', true) ?: [];
    $p['syarat'] = rows("SELECT * FROM upload_syarat WHERE permohonan_id = ?", [$id]);
    return $p;
}

function field_labels_map(int $jenis_id): array
{
    $map = [];
    foreach (rows("SELECT nama_field, label FROM field_isian WHERE jenis_id = ? ORDER BY urutan", [$jenis_id]) as $f) {
        $map[$f['nama_field']] = $f['label'];
    }
    return $map;
}

/** URL absolut dasar (untuk QR & link email) */
function base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return ($https ? 'https://' : 'http://') . $host . ($script === '/' ? '' : $script);
}
