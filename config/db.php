<?php
declare(strict_types=1);
/**
 * DB config for SQL Server (COSYSA).
 * Fill in your real server/credentials. Example DSN:
 *   sqlsrv:Server=INTELISIS;Database=COSYSA
 * Requires: php-sqlsrv + php-pdo_sqlsrv extensions installed/enabled.
 */
$DB_DSN  = getenv('DB_DSN') ?: 'sqlsrv:Server=INTELISIS;Database=COSYSA';
$DB_USER = getenv('DB_USER') ?: 'intelisis';
$DB_PASS = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO($DB_DSN, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::SQLSRV_ATTR_ENCODING    => PDO::SQLSRV_ENCODING_UTF8,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error de conexión a BD: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
