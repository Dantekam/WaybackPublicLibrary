<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db_connect.php';
try {
    $branches = $pdo->query('SELECT BranchID, BranchName FROM Branch ORDER BY BranchName')->fetchAll();
    $term = trim((string)($_GET['q'] ?? ''));
    $items = [];
    if ($term !== '') {
        $stmt = $pdo->prepare('SELECT ItemID, Title, ItemType, Publisher, LibraryofCongressCode, Cost FROM ITEM WHERE Title LIKE ? OR CAST(ItemID AS CHAR) LIKE ? ORDER BY Title LIMIT 40');
        $like = '%' . $term . '%';
        $stmt->execute([$like, $like]);
        $items = $stmt->fetchAll();
    }
    echo json_encode(['ok' => true, 'branches' => $branches, 'items' => $items]);
} catch (PDOException $e) {
    error_log('catalogData: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not load catalog data.']);
}
