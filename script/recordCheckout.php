<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db_connect.php';

function respond($ok, $message, $status = 200) {
    http_response_code($status);
    echo json_encode([
        'ok' => $ok,
        'error' => $message
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'POST request required.', 405);
}

$patronID = $_POST['patronID'] ?? '';
$itemCopyID = $_POST['itemCopyID'] ?? '';

if (!is_string($patronID) || !ctype_digit($patronID) ||
    !is_string($itemCopyID) || !ctype_digit($itemCopyID) ||
    (int)$patronID <= 0 || (int)$itemCopyID <= 0) {
    respond(false, 'Please provide valid patron and item copy IDs.', 400);
}

$patronID = (int)$patronID;
$itemCopyID = (int)$itemCopyID;

try {
    $pdo->beginTransaction();

    // Lock the patron row to serialize checkouts for this patron.
    $stmt = $pdo->prepare(
        'SELECT FirstName, LastName, MembershipExpiration
         FROM Patron
         WHERE PatronID = ?
         FOR UPDATE'
    );
    $stmt->execute([$patronID]);
    $patron = $stmt->fetch();

    if (!$patron) {
        $pdo->rollBack();
        respond(false, 'Patron not found.');
    }

    if (empty($patron['MembershipExpiration']) ||
        $patron['MembershipExpiration'] < date('Y-m-d')) {
        $pdo->rollBack();
        respond(false, 'Patron membership has expired.');
    }

    // Check outstanding recorded fines.
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(
            GREATEST(Amount - AmountPaid, 0)
        ), 0) AS OutstandingBalance
         FROM Fine
         WHERE PatronID = ?'
    );
    $stmt->execute([$patronID]);
    $balance = (float)$stmt->fetchColumn();

    if ($balance > 0) {
        $pdo->rollBack();
        respond(false, 'Checkout denied: patron has outstanding fines.');
    }

    // Count checkouts that have not been returned.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM CheckOut
         WHERE PatronID = ?
           AND ReturnDate IS NULL'
    );
    $stmt->execute([$patronID]);
    $activeCount = (int)$stmt->fetchColumn();

    if ($activeCount >= 20) {
        $pdo->rollBack();
        respond(false, 'Checkout denied: patron has reached the 20-item limit.');
    }

    // Lock the physical copy and read its catalog item type.
    $stmt = $pdo->prepare(
        'SELECT c.Status, i.ItemType
         FROM Item_Copy c
         INNER JOIN ITEM i ON c.ItemID = i.ItemID
         WHERE c.ItemCopyID = ?
         FOR UPDATE'
    );
    $stmt->execute([$itemCopyID]);
    $copy = $stmt->fetch();

    if (!$copy) {
        $pdo->rollBack();
        respond(false, 'Item copy not found.');
    }

    if ($copy['Status'] !== 'Available') {
        $pdo->rollBack();
        respond(false, 'Item is not available for checkout.');
    }

    // Prevent a second active loan for the same physical copy.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM CheckOut
         WHERE ItemCopyID = ?
           AND ReturnDate IS NULL'
    );
    $stmt->execute([$itemCopyID]);

    if ((int)$stmt->fetchColumn() > 0) {
        $pdo->rollBack();
        respond(
            false,
            'Checkout denied: this copy has an unresolved checkout record.'
        );
    }

    // Loan rules from the WPL narrative.
    $type = strtolower(trim($copy['ItemType']));

    switch ($type) {
        case 'book':
            $loanDays = 14;
            break;

        case 'movie':
        case 'dvd':
        case 'video':
            $loanDays = 7;
            break;

        case 'periodical':
            $loanDays = 1;
            break;

        default:
            $pdo->rollBack();
            respond(
                false,
                'Loan duration has not been configured for this item type.'
            );
    }

    $checkoutDate = date('Y-m-d');
    $dueDate = date('Y-m-d', strtotime("+$loanDays days"));

    $stmt = $pdo->prepare(
        'INSERT INTO CheckOut
            (CheckOutDate, DueDate, PatronID, ItemCopyID, ReturnDate)
         VALUES (?, ?, ?, ?, NULL)'
    );
    $stmt->execute([
        $checkoutDate,
        $dueDate,
        $patronID,
        $itemCopyID
    ]);

    $checkoutID = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "UPDATE Item_Copy
         SET Status = 'CheckedOut'
         WHERE ItemCopyID = ?
           AND Status = 'Available'"
    );
    $stmt->execute([$itemCopyID]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Copy status update failed.');
    }

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'checkout' => [
            'checkoutID' => $checkoutID,
            'patron' => trim(
                $patron['FirstName'] . ' ' . $patron['LastName']
            ),
            'itemCopyID' => $itemCopyID,
            'itemType' => $copy['ItemType'],
            'checkoutDate' => $checkoutDate,
            'dueDate' => $dueDate
        ]
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Could not record checkout. Please try again.'
    ]);
}
