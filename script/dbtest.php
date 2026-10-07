<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$pw = require __DIR__ . '/.db_pass.php';
echo "Password length: " . strlen($pw) . "<br><br>";

foreach (['localhost', '127.0.0.1', 'ada.cis.uncw.edu'] as $host) {
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=svd2152;charset=utf8mb4",
            'svd2152',
            $pw,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_TIMEOUT => 3]
        );
        echo "✅ $host — connected<br>";
    } catch (PDOException $e) {
        echo "❌ $host — " . htmlspecialchars($e->getMessage()) . "<br>";
    }
}