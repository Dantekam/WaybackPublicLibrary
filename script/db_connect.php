<?php

$host = 'ada.cis.uncw.edu';
$db = 'project3';
$user = 'project3';
$charset = 'utf8mb4';

try {
    $pass = require __DIR__ . '/.db_pass.php';

    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

} catch (Throwable $e) {
    error_log('WPL database connection: ' . $e->getMessage());

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'ok' => false,
        'error' => 'Database connection unavailable.'
    ]);

    exit;
}