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
$title          = trim($_POST['title'] ?? '');
$itemID         = trim($_POST['itemID'] ?? '');
$shelfLocation  = trim($_POST['shelfLocation'] ?? '');
$branchID       = trim($_POST['branchID'] ?? '');
$itemType       = trim($_POST['itemType'] ?? '');
$status         = trim($_POST['status'] ?? 'Open');

// Validate itemCopyID
if ($itemCopyID === '' || !ctype_digit((string)$itemCopyID)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid numeric Item Copy ID.']);
    exit;
}

// Validate title
if ($title === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please enter an title.']);
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
    $check = $pdo->prepare('SELECT 1 FROM Item_Copy WHERE ItemCopyID = ?');
    $check->execute([(int)$itemCopyID]);
    if ($check->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'An item copy with that ID already exists.']);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO Item_Copy (ItemCopyID, Title, ItemID, ShelfLocationCode, BranchID, ItemType, Status)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int)$itemCopyID,
        $title,
        (int)$itemID,
        $shelfLocation,
        (int)$branchID,
        $itemType,
        $status,
    ]);

    echo json_encode([
        'ok' => true,
        'item' => [
            'itemCopyID'    => (int)$itemCopyID,
            'title'         => $title,
            'itemID'        => (int)$itemID,
            'shelfLocation' => $shelfLocation,
            'branchID'      => (int)$branchID,
            'itemType'      => $itemType,
            'status'        => $status,
            'available'     => strcasecmp($status, 'Open') === 0,
        ],
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'An item copy with that ID already exists.']);
        exit;
    }

    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Insert failed: ' . $e->getMessage()]);
    exit;
}