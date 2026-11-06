<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db_connect.php';

$itemCopyID = $_POST['itemCopyID'] ?? '';

if (!ctype_digit((string)$itemCopyID) || (int)$itemCopyID <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid item copy ID.']);
    exit;
}

$itemCopyID = (int)$itemCopyID;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT
            c.CheckOutID,
            c.DueDate,
            COALESCE(c.RenewalCount, 0) AS renewalCount,
            it.LoanPeriodDays,
            c.PatronID,
            CONCAT(p.FirstName, " ", p.LastName) AS patronName,
            ic.ItemCopyID,
            COALESCE(i.Title, ic.Title) AS title
         FROM CheckOut c
         JOIN Item_Copy ic ON ic.ItemCopyID = c.ItemCopyID
         LEFT JOIN ITEM i ON i.ItemID = ic.ItemID
         LEFT JOIN Item_Type it ON it.ItemTypeID = i.ItemTypeID
         LEFT JOIN Patron p ON p.PatronID = c.PatronID
         WHERE c.ItemCopyID = ? AND c.ReturnDate IS NULL
         ORDER BY c.CheckOutID DESC
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([$itemCopyID]);
    $checkout = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$checkout) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'No active checkout was found for this item copy.']);
        exit;
    }

    if ((int)$checkout['renewalCount'] >= 2) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'This item has already reached the renewal limit.']);
        exit;
    }

    $dueDate = $checkout['DueDate'] ? new DateTime($checkout['DueDate']) : null;
    if ($dueDate && $dueDate < new DateTime('today')) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Overdue items cannot be renewed.']);
        exit;
    }

    $loanDays = (int)($checkout['LoanPeriodDays'] ?? 14);
    $newDueDate = (new DateTime('today'))->modify('+' . $loanDays . ' days')->format('Y-m-d');

    $stmt = $pdo->prepare(
        'UPDATE CheckOut
           SET DueDate = ?, RenewalCount = COALESCE(RenewalCount, 0) + 1
         WHERE CheckOutID = ?'
    );
    $stmt->execute([$newDueDate, $checkout['CheckOutID']]);

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'message' => 'Item renewed successfully.',
        'checkout' => [
            'checkoutID' => (int)$checkout['CheckOutID'],
            'itemCopyID' => (int)$itemCopyID,
            'patronName' => $checkout['patronName'],
            'dueDate' => $newDueDate,
            'renewalCount' => (int)$checkout['renewalCount'] + 1,
            'title' => $checkout['title'],
        ]
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('renewal: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not renew the checkout.']);
}
?>