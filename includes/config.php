<?php
/**
 * Konfigurasi Sistem Administrasi Kependudukan (SIDUK)
 */
define('APP_NAME', 'SIDUK');
define('APP_TITLE', 'Sistem Informasi Administrasi Kependudukan');
define('BASE_PATH', dirname(__DIR__));

/**
 * Loader .env sederhana (tanpa library pihak ketiga).
 * Membaca file .env di root aplikasi menjadi variabel lingkungan,
 * sehingga konfigurasi bisa diatur lewat file tanpa mengubah kode.
 * Nilai environment yang sudah diset server (mis. SetEnv/apache/php-fpm)
 * tetap lebih diprioritaskan daripada isi .env.
 */
if (!function_exists('siduk_load_env')) {
    function siduk_load_env(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return; // tidak ada .env -> gunakan default/getenv dari server
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue; // komentar / baris kosong
            }
            if (strpos($line, 'export ') === 0) {
                $line = substr($line, 7);
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key   = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if ($key === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
                continue;
            }
            // buka kutip pembuka-penutup yang sama ("..." atau '...')
            if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"')
                || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            // jangan menimpa variabel yang sudah diset oleh server
            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}
siduk_load_env(BASE_PATH . '/.env');

// Database — prioritas: environment server > file .env > nilai default
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'siduk');

// Path upload
define('UPLOAD_DIR', BASE_PATH . '/uploads');
define('UPLOAD_URL', 'uploads');

// Zona waktu
date_default_timezone_set('Asia/Jakarta');

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_name('SIDUKSESS');
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '0'); // set '1' saat development
