<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db_connect.php';

if ($dbError) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database unavailable.']);
    exit;
}

$itemCopyID = $_GET['itemCopyID'] ?? '';

if (!is_string($itemCopyID) ||
    !ctype_digit($itemCopyID) ||
    (int)$itemCopyID <= 0) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Please enter a valid numeric Item Copy ID.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT
            c.ItemCopyID,
            i.ItemID,
            i.Title,
            i.ItemType,
            c.Status,
            c.BranchID,
            b.BranchName,
            c.ShelfLocationCode
         FROM Item_Copy c
         INNER JOIN Item   i ON c.ItemID   = i.ItemID
         LEFT  JOIN Branch b ON c.BranchID = b.BranchID
         WHERE c.ItemCopyID = ?'
    );

    $stmt->execute([(int)$itemCopyID]);
    $item = $stmt->fetch();

    if (!$item) {
        echo json_encode([
            'ok' => false,
            'error' => 'Item copy not found.'
        ]);
        exit;
    }

    $available = $item['Status'] === 'Available';

    echo json_encode([
        'ok' => true,
        'item' => [
            'itemCopyID'    => (int)$item['ItemCopyID'],
            'itemID'        => (int)$item['ItemID'],
            'title'         => $item['Title'],
            'itemType'      => $item['ItemType'],
            'status'        => $item['Status'],
            'branchID'      => (int)$item['BranchID'],
            'branchName'    => $item['BranchName'],
            'shelfLocation' => $item['ShelfLocationCode'],
            'available'     => $available,
        ]
    ]);

} catch (PDOException $e) {
    error_log('item-lookup: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Unable to retrieve item information.'
    ]);
}