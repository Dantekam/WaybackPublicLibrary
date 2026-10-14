<?php
header('Content-Type: application/json');

//DB Connection
require __DIR__ . '/db_connect.php';

$itemCopyID = $_GET['itemCopyID'] ?? '';

if ($itemCopyID === '' || !ctype_digit((string)$itemCopyID)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid numeric Item Copy ID.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT ItemCopyID, ItemType, Status
           FROM Item_Copy
          WHERE ItemCopyID = ?'
    );
    $stmt->execute([(int)$itemCopyID]);
    $item = $stmt->fetch();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Lookup failed.']);
    exit;
}

if (!$item) {
    echo json_encode(['ok' => false, 'error' => 'Itemcopy not found.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'item' => [
        'itemCopyID' => (int)$item['ItemCopyID'],
        'itemType'   => $item['ItemType'],
        'status'     => $item['Status'],
        'available'  => strcasecmp($item['Status'], 'Open') === 0,
    ],
]);