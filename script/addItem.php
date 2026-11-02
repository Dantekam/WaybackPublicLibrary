<?php
header('Content-Type: application/json');

require __DIR__ . '/db_connect.php';

// Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// Read form values.
$itemCopyID = trim((string)($_POST['itemCopyID'] ?? ''));
$title = trim((string)($_POST['title'] ?? ''));
$itemID = trim((string)($_POST['itemID'] ?? ''));
$branchID = trim((string)($_POST['branchID'] ?? ''));
$shelfLocation = trim((string)($_POST['shelfLocation'] ?? ''));
$itemType = trim((string)($_POST['itemType'] ?? ''));
$status = trim((string)($_POST['status'] ?? 'Available'));

// Validate numeric IDs.
if (!ctype_digit($itemCopyID) || (int)$itemCopyID < 1) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Please enter a valid Item Copy ID.'
    ]);
    exit;
}

if (!ctype_digit($itemID) || (int)$itemID < 1) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Please enter a valid Item ID.'
    ]);
    exit;
}

if (!ctype_digit($branchID) || (int)$branchID < 1) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Please enter a valid Branch ID.'
    ]);
    exit;
}

// Validate text fields.
if ($title === '' || $itemType === '' || $shelfLocation === '') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Please fill in the title, item type, and shelf location.'
    ]);
    exit;
}

// Match the statuses used by the checkout system.
$allowedStatuses = [
    'Available',
    'CheckedOut',
    'Maintenance',
    'Retired'
];

if (!in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid item status.'
    ]);
    exit;
}

try {
    // Check for an existing physical copy.
    $checkCopy = $pdo->prepare(
        'SELECT 1 FROM Item_Copy WHERE ItemCopyID = ?'
    );
    $checkCopy->execute([(int)$itemCopyID]);

    if ($checkCopy->fetchColumn()) {
        http_response_code(409);
        echo json_encode([
            'ok' => false,
            'error' => 'An item copy with that ID already exists.'
        ]);
        exit;
    }

    // Confirm the catalog item exists.
    $checkItem = $pdo->prepare(
        'SELECT ItemType FROM ITEM WHERE ItemID = ?'
    );
    $checkItem->execute([(int)$itemID]);

    $catalogItemType = $checkItem->fetchColumn();

    if ($catalogItemType === false) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Item ID does not exist in the catalog. Create the catalog item first.'
        ]);
        exit;
    }

    // Ensure the physical copy uses the catalog item's type.
    if (strcasecmp(trim((string)$catalogItemType), $itemType) !== 0) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Item Type must match the existing catalog item type: ' . $catalogItemType
        ]);
        exit;
    }

    // Confirm the branch exists.
    $checkBranch = $pdo->prepare(
        'SELECT 1 FROM Branch WHERE BranchID = ?'
    );
    $checkBranch->execute([(int)$branchID]);

    if (!$checkBranch->fetchColumn()) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Branch ID does not exist.'
        ]);
        exit;
    }

    // Add the physical copy.
    $stmt = $pdo->prepare(
        'INSERT INTO Item_Copy
            (ItemCopyID, Title, ItemID, ShelfLocationCode,
             BranchID, ItemType, Status, AcquisitionDate)
         VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())'
    );

    $stmt->execute([
        (int)$itemCopyID,
        $title,
        (int)$itemID,
        $shelfLocation,
        (int)$branchID,
        $itemType,
        $status
    ]);

    echo json_encode([
        'ok' => true,
        'item' => [
            'itemCopyID' => (int)$itemCopyID,
            'title' => $title,
            'itemID' => (int)$itemID,
            'shelfLocation' => $shelfLocation,
            'branchID' => (int)$branchID,
            'itemType' => $itemType,
            'status' => $status,
            'available' => $status === 'Available'
        ]
    ]);

} catch (PDOException $e) {
    $mysqlError = $e->errorInfo[1] ?? null;

    error_log('addItem.php database error: ' . $e->getMessage());

    if ($mysqlError == 1062) {
        http_response_code(409);
        $message = 'An item copy with that ID already exists.';
    } elseif ($mysqlError == 1452) {
        http_response_code(400);
        $message = 'Invalid Item ID or Branch ID. The referenced record does not exist.';
    } else {
        http_response_code(500);
        $message = 'Unable to save the item copy due to a database error.';
    }

    echo json_encode([
        'ok' => false,
        'error' => $message
    ]);
}
