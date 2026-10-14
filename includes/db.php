<?php
require_once __DIR__ . '/config.php';

/**
 * Koneksi database singleton (MySQLi + prepared statements)
 */
function db(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            die('Koneksi database gagal: ' . htmlspecialchars($e->getMessage()) .
                '<br>Jalankan import <code>database.sql</code> terlebih dahulu.');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

/** Jalankan query dengan parameter. Return mysqli_result|bool  */
function q(string $sql, array $params = []): mysqli_result|bool
{
    $conn = db();
    if (!$params) {
        return $conn->query($sql);
    }
    $stmt = $conn->prepare($sql);
    if ($params) {
        $types = '';
        foreach ($params as $v) {
            if (is_int($v))        $types .= 'i';
            elseif (is_float($v))  $types .= 'd';
            else                   $types .= 's';
        }
        $refs = [];
        foreach ($params as $k => $v) $refs[$k] = &$params[$k];
        $stmt->bind_param($types, ...$refs);
    }
    $stmt->execute();
    return $stmt->get_result() ?: true;
}

/** Ambil satu baris (asosiatif) */
function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params);
    if ($r instanceof mysqli_result) {
        $row = $r->fetch_assoc();
        $r->free();
        return $row ?: null;
    }
    return null;
}

/** Ambil semua baris */
function rows(string $sql, array $params = []): array
{
    $out = [];
    $r = q($sql, $params);
    if ($r instanceof mysqli_result) {
        while ($row = $r->fetch_assoc()) $out[] = $row;
        $r->free();
    }
    return $out;
}

/** Ambil satu nilai kolom pertama */
function val(string $sql, array $params = [])
{
    $row = row($sql, $params);
    return $row ? reset($row) : null;
}

/** ID hasil insert terakhir */
function ins_id(): int { return db()->insert_id; }
