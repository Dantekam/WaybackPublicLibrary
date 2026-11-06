<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db_connect.php';

$itemCopyID = $_POST['itemCopyID'] ?? '';
$branchID = $_POST['returnBranchID'] ?? $_POST['branchID'] ?? '';

if (!ctype_digit((string)$itemCopyID) || (int)$itemCopyID <= 0 || !ctype_digit((string)$branchID) || (int)$branchID <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid item copy or return branch.']);
    exit;
}

$itemCopyID = (int)$itemCopyID;
$branchID = (int)$branchID;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT ItemCopyID, Status, CurrentBranchID, HomeBranchID
           FROM Item_Copy
          WHERE ItemCopyID = ?
          FOR UPDATE'
    );
    $stmt->execute([$itemCopyID]);
    $copy = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$copy) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Item copy not found.']);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT CheckOutID, PatronID, DueDate, ReturnDate, RenewalCount
           FROM CheckOut
          WHERE ItemCopyID = ? AND ReturnDate IS NULL
          ORDER BY CheckOutID DESC
          LIMIT 1
          FOR UPDATE'
    );
    $stmt->execute([$itemCopyID]);
    $checkout = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$checkout) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'No active checkout exists for this item copy.']);
        exit;
    }

    $branchCheck = $pdo->prepare('SELECT BranchID FROM Branch WHERE BranchID = ?');
    $branchCheck->execute([$branchID]);
    if (!$branchCheck->fetch()) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Return branch not found.']);
        exit;
    }

    $returnDate = (new DateTime())->format('Y-m-d');

    $stmt = $pdo->prepare(
        'UPDATE CheckOut
            SET ReturnBranchID = ?, ReturnDate = ?, DueDate = NULL
          WHERE CheckOutID = ?'
    );
    $stmt->execute([$branchID, $returnDate, $checkout['CheckOutID']]);

    $stmt = $pdo->prepare(
        'UPDATE Item_Copy
            SET CurrentBranchID = ?, Status = "Available"
          WHERE ItemCopyID = ?'
    );
    $stmt->execute([$branchID, $itemCopyID]);

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'message' => 'Item checked in successfully.',
        'checkout' => [
            'checkoutID' => (int)$checkout['CheckOutID'],
            'itemCopyID' => $itemCopyID,
            'returnBranchID' => $branchID,
            'dueDate' => null,
            'returnDate' => $returnDate,
            'status' => 'Available'
        ]
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('return-item: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not record the return.']);
}