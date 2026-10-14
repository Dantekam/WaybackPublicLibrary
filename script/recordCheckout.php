<?php
header('Content-Type: application/json');

//Db Connection
require __DIR__ . '/db_connect.php';

$patronID   = $_POST['patronID']   ?? '';
$itemCopyID = $_POST['itemCopyID'] ?? '';

if (!ctype_digit((string)$patronID) || !ctype_digit((string)$itemCopyID)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid patron/item.']);
    exit;
}

$patronID   = (int)$patronID;
$itemCopyID = (int)$itemCopyID;

try {
    $pdo->beginTransaction();

    // 1. Confirm patron exists and is not expired
    $stmt = $pdo->prepare(
        'SELECT FirstName, LastName, MembershipExpiration
           FROM Patron
          WHERE PatronID = ?'
    );
    $stmt->execute([$patronID]);
    $patron = $stmt->fetch();

    if (!$patron) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Patron not found.']);
        exit;
    }
    if (strtotime($patron['MembershipExpiration']) < time()) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Patron membership has expired.']);
        exit;
    }

    // 2. Confirm item copy exists, is open, and grab its type
    $stmt = $pdo->prepare(
        'SELECT ItemType, Status
           FROM Item_Copy
          WHERE ItemCopyID = ?
          FOR UPDATE'
    );
    $stmt->execute([$itemCopyID]);
    $copy = $stmt->fetch();

    if (!$copy) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Item copy not found.']);
        exit;
    }
    if (strcasecmp($copy['Status'], 'Open') !== 0) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Item is not available for checkout.']);
        exit;
    }

    // 3. Insert the checkout record
    $checkoutDate = date('Y-m-d');
    $dueDate      = date('Y-m-d', strtotime('+14 days'));

    $stmt = $pdo->prepare(
        'INSERT INTO CheckOut (CheckOutDate, DueDate, PatronId, ItemCopyID)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$checkoutDate, $dueDate, $patronID, $itemCopyID]);
    $checkoutID = (int)$pdo->lastInsertId();

    // 4. Mark the copy as checked out
    $stmt = $pdo->prepare(
        "UPDATE Item_Copy SET Status = 'CheckedOut' WHERE ItemCopyID = ?"
    );
    $stmt->execute([$itemCopyID]);

    $pdo->commit();

    $fullName = trim($patron['FirstName'] . ' ' . $patron['LastName']);

    echo json_encode([
        'ok' => true,
        'checkout' => [
            'checkoutID'   => $checkoutID,
            'patron'       => $fullName,
            'itemCopyID'   => $itemCopyID,
            'itemType'     => $copy['ItemType'],
            'checkoutDate' => $checkoutDate,
            'dueDate'      => $dueDate,
        ],
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not record checkout.']);
}