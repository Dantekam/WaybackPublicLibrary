<?php
header('Content-Type: application/json');

//Db Connection
require __DIR__ . '/db_connect.php';

$itemCopyID = $_POST['itemCopyID'] ?? '';
$branchID = $_POST['branchID'] ?? '';

if (!ctype_digit((string)$branchID) || !ctype_digit((string)$itemCopyID)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid branch/item.']);
    exit;
}

$itemCopyID = (int)$itemCopyID;
$branchID = (int)$branchID;

try {
    $pdo->beginTransaction();

    // 1. Confirm item copy exists, is open, and grab its type
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

    // 2. Confirm branch exists
    $stmt = $pdo->prepare(
        'SELECT BranchName
           FROM Branch
          WHERE BranchID = ?'
    );
    $stmt->execute([$branchID]);
    $branchName = trim($stmt->fetch());
    $branchName = strtolower($branchName);

    $newStatus = '';
    if (!$branchName) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Branch not found.']);
        exit;
    }
    if ($branchName == 'Central Library') {
        $newStatus = 'Available';
    } else {
        $newStatus = 'Open';
    }

    // 3. Get active checkout info 
    $stmt = $pdo->prepare(
        'SELECT C.CheckoutID, C.title AS Item, C.ItemType, CONCAT(P.FirstName, "", P.LastName) AS FullName, C.CheckoutDate, C.DueDate
           FROM Checkout C JOIN Patron P ON C.PatronID = P.PatronID 
          WHERE ItemCopyID = ?'
    );
    $stmt->execute([$itemCopyID]);
    $checkout = $stmt->fetch();

    // TODO: not sure if need right now
    // return the item and patron info
    /*echo json_encode([
        'ok' => true,
        'checkout' => [
            'checkoutID'   => $checkout['CheckoutID'],
            'patron'       => $checkout['FullName'];,
            'itemCopyID'   => $itemCopyID,
            'itemName'     => $checkout['Item']
            'itemType'     => $checkout['ItemType'],
            'checkoutDate' => $checkout['CheckoutDate'],
            'dueDate'      => $checkout['DueDate'],
        ],
    ]);*/

    // 4. Change to return branch
    $stmt = $pdo->prepare(
        'UPDATE Checkout SET BranchID = ? WHERE CheckoutID = ?'
    );
    $stmt->execute([$branchID, $checkoutID]);

    // 5. Change status of item
    $stmt = $pdo->prepare(
        'UPDATE Checkout SET Status = ? WHERE CheckoutID = ?'
    );
    $stmt->execute([$newStatus, $checkoutID]);

    // 6. Remove Due Date
    $stmt = $pdo->prepare(
        'UPDATE Checkout SET DueDate = NULL WHERE CheckoutID = ?'
    );
    $stmt->execute([$newStatus, $checkoutID]);

    # 7. Enter return date as current date
    $stmt = $pdo->prepare(
        'UPDATE Checkout SET ReturnDate = CURDATE() WHERE CheckoutID = ?'
    );
    $stmt->execute([$checkoutID]);

    // 9. remove patron from checkout
    $stmt = $pdo->prepare(
        'UPDATE Checkout SET PatronID = NULL WHERE CheckoutID = ?'
    );
    $stmt->execute([$checkoutID]);

    /* DO THESE
    The system confirms that the return was 
    successfully recorded.
    The returned item remains unavailable for 
    checkout until it has been routed and placed 
    back into an available state.
    The system prevents duplicate returns and 
    provides an appropriate message when no 
    active checkout exists for the identified item.
*/

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not return checkout.']);
}