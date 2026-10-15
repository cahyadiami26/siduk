<?php
/**
 * Konfigurasi Sistem Administrasi Kependudukan (SIDUK)
 */
define('APP_NAME', 'SIDUK');
define('APP_TITLE', 'Sistem Informasi Administrasi Kependudukan');
define('BASE_PATH', dirname(__DIR__));

// Database
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
