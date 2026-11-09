
<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db_connect.php';

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'error' => 'POST required.'], 405);
}

$raw = $_POST['itemCopyID'] ?? '';

if (!is_string($raw) || !ctype_digit($raw) || (int)$raw <= 0) {
    respond([
        'ok' => false,
        'error' => 'Missing or invalid item copy ID.'
    ], 400);
}

$copyID = (int)$raw;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT ItemCopyID, Status
         FROM Item_Copy
         WHERE ItemCopyID = ?
         FOR UPDATE'
    );
    $stmt->execute([$copyID]);
    $copy = $stmt->fetch();

    if (!$copy) {
        throw new DomainException('Item copy not found.');
    }

    if ($copy['Status'] !== 'CheckedOut') {
        throw new DomainException(
            'This item is not currently marked as checked out.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT c.CheckOutID, c.PatronID, c.DueDate,
                COALESCE(c.RenewalCount, 0) AS renewalCount,
                i.Title, i.ItemType,
                it.LoanPeriodDays,
                CONCAT(p.FirstName, " ", p.LastName) AS patronName
         FROM CheckOut c
         JOIN Item_Copy ic ON ic.ItemCopyID = c.ItemCopyID
         JOIN ITEM i ON i.ItemID = ic.ItemID
         LEFT JOIN Item_Type it ON it.ItemTypeID = i.ItemTypeID
         JOIN Patron p ON p.PatronID = c.PatronID
         WHERE c.ItemCopyID = ?
           AND c.ReturnDate IS NULL
         FOR UPDATE'
    );
    $stmt->execute([$copyID]);
    $loans = $stmt->fetchAll();

    if (count($loans) !== 1) {
        throw new DomainException(
            'Exactly one active checkout is required for renewal.'
        );
    }

    $loan = $loans[0];

    if ((int)$loan['renewalCount'] >= 2) {
        throw new DomainException(
            'This item has reached its two-renewal limit.'
        );
    }

    if (!$loan['DueDate']) {
        throw new DomainException(
            'This checkout has no valid due date.'
        );
    }

    $today = new DateTimeImmutable('today');
    $currentDue = new DateTimeImmutable($loan['DueDate']);

    if ($currentDue < $today) {
        throw new DomainException(
            'Overdue items cannot be renewed.'
        );
    }

    // Do not renew an item needed by another patron.
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM Reservation
         WHERE ItemCopyID = ?
           AND PatronID <> ?
           AND Status IN ('Pending', 'Ready')"
    );
    $stmt->execute([$copyID, $loan['PatronID']]);

    if ((int)$stmt->fetchColumn() > 0) {
        throw new DomainException(
            'Renewal denied: another patron has reserved this copy.'
        );
    }

    $loanDays = (int)$loan['LoanPeriodDays'];

    if ($loanDays <= 0) {
        switch (strtolower(trim($loan['ItemType']))) {
            case 'book':
                $loanDays = 14;
                break;
            case 'dvd':
            case 'movie':
            case 'video':
                $loanDays = 7;
                break;
            case 'periodical':
                $loanDays = 1;
                break;
            default:
                throw new DomainException(
                    'Loan period is not configured for this item type.'
                );
        }
    }

    $newDue = $currentDue
        ->modify('+' . $loanDays . ' days')
        ->format('Y-m-d');

    $stmt = $pdo->prepare(
        'UPDATE CheckOut
         SET DueDate = ?,
             RenewalCount = COALESCE(RenewalCount, 0) + 1
         WHERE CheckOutID = ?
           AND ReturnDate IS NULL'
    );
    $stmt->execute([$newDue, $loan['CheckOutID']]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Renewal update failed.');
    }

    $pdo->commit();

    respond([
        'ok' => true,
        'message' => 'Checkout renewed successfully.',
        'checkout' => [
            'checkoutID' => (int)$loan['CheckOutID'],
            'itemCopyID' => $copyID,
            'patronName' => $loan['patronName'],
            'title' => $loan['Title'],
            'previousDueDate' => $loan['DueDate'],
            'dueDate' => $newDue,
            'renewalCount' => (int)$loan['renewalCount'] + 1,
            'status' => 'CheckedOut'
        ]
    ]);

} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respond(['ok' => false, 'error' => $e->getMessage()], 409);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('renewItem: ' . $e->getMessage());

    respond([
        'ok' => false,
        'error' => 'Could not renew the checkout.'
    ], 500);
}
