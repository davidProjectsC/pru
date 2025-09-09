<?php
declare(strict_types=1);
/**
 * DB config for SQL Server (COSYSA) usando dblib (FreeTDS).
 * Ejemplo DSN:
 *   dblib:host=INTELISIS;dbname=COSYSA
 * Requiere: extensión pdo_dblib instalada/enabled.
 */
$DB_DSN  = getenv('DB_DSN') ?: 'dblib:host=INTELISIS;dbname=COSYSA';
$DB_USER = getenv('DB_USER') ?: 'intelisis';
$DB_PASS = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO($DB_DSN, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error de conexión a BD: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
