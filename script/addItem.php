<?php
header('Content-Type: application/json');

// DB Connection
require __DIR__ . '/db_connect.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$itemCopyID = $_POST['itemCopyID'] ?? '';
$itemType   = trim($_POST['itemType'] ?? '');
$status     = trim($_POST['status'] ?? 'Open');

// Validate itemCopyID
if ($itemCopyID === '' || !ctype_digit((string)$itemCopyID)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid numeric Item Copy ID.']);
    exit;
}

// Validate itemType
if ($itemType === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please enter an Item Type.']);
    exit;
}

// Validate status against allowed values
$allowedStatuses = ['Open', 'On Loan', 'Maintenance', 'Retired'];
if (!in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid status. Allowed: ' . implode(', ', $allowedStatuses)]);
    exit;
}

try {
    // Optional: check for duplicate ID first for a friendlier error
    $check = $pdo->prepare('SELECT 1 FROM Item_Copy WHERE ItemCopyID = ?');
    $check->execute([(int)$itemCopyID]);
    if ($check->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'An item copy with that ID already exists.']);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO Item_Copy (ItemCopyID, ItemType, Status)
         VALUES (?, ?, ?)'
    );
    $stmt->execute([(int)$itemCopyID, $itemType, $status]);

    echo json_encode([
        'ok' => true,
        'item' => [
            'itemCopyID' => (int)$itemCopyID,
            'itemType'   => $itemType,
            'status'     => $status,
            'available'  => strcasecmp($status, 'Open') === 0,
        ],
    ]);
} catch (PDOException $e) {
    // 23000 = integrity constraint violation (e.g. duplicate primary key)
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'An item copy with that ID already exists.']);
        exit;
    }

    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Insert failed.']);
    exit;
}