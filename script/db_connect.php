<?php
// establish the database connection using PDO
$host = 'ada.cis.uncw.edu';
$db = 'project3';
$user = 'project3';
$charset = 'utf8mb4';
$pass = require __DIR__ . '/.db_pass.php';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	PDO::ATTR_EMULATE_PREPARES => false,
];

try {
	$pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
	die("Database connection failed.");
}

if ($searchTerm !== '') {
	$likeTerm = '%' . $searchTerm . '%';
	$sql = "SELECT * FROM users WHERE book LIKE :search1 OR author LIKE :search2";

}

?>