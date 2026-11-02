<?php
header('Content-Type: application/json');

// Db Connection
require __DIR__ . '/db_connect.php';

$itemCopyID = $_POST['ItemCopyID'] ?? '';

if (!ctype_digit((string)$checkoutID)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid checkout.']);
    exit;
}

$itemCopyID = (int)$itemCopyID;

try {
    $pdo->beginTransaction();

    // 1. Confirm checkout is active and get the item type and loan period
    $stmt = $pdo->prepare(
        'SELECT C.CheckoutID, C.DueDate, C.RenewalCount, IT.LoanPeriodDays
           FROM Checkout C
           JOIN Item_Copy IC ON C.ItemCopyID = IC.ItemCopyID
           JOIN Item I ON IC.ItemID = I.ItemID
           JOIN Item_Type IT ON I.ItemTypeID = IT.ItemTypeID
          WHERE ItemCopyID = ?
            AND C.ReturnDate IS NULL
          FOR UPDATE'
    );
    $stmt->execute([$ItemCopyID]);
    $checkout = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$checkout) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Active checkout not found.']);
        exit;
    }
    $checkoutID = $checkout['CheckOutID'];

    // 2. Confirm the item has not already been renewed two times
    if ((int)$checkout['RenewalCount'] >= 2) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Item has reached the renewal limit.']);
        exit;
    }

    // 3. Check if the renewal count is null and change

    // 3. Calculate the new due date based on the item type
    $stmt = $pdo->prepare(
        'UPDATE Checkout
            SET DueDate = DATE_ADD(CURDATE(), INTERVAL ? DAY),
                RenewalCount = RenewalCount + 1
          WHERE CheckoutID = ?'
    );
    $stmt->execute([(int)$checkout['LoanPeriodDays'], $checkoutID]);

    // 4. Get the updated checkout information
    $stmt = $pdo->prepare(
        'SELECT DueDate, RenewalCount
           FROM Checkout
          WHERE CheckOutID = ?'
    );
    $stmt->execute([$checkoutID]);
    $updatedCheckout = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'message' => 'Item renewed successfully.',
        'checkout' => [
            'checkoutID' => $checkoutID,
            'dueDate' => $updatedCheckout['DueDate'],
            'renewalCount' => (int)$updatedCheckout['RenewalCount']
        ]
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not renew checkout.']);
}
?>