
<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db_connect.php';

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond([
        'ok' => false,
        'error' => 'POST required.'
    ], 405);
}

$raw = $_POST['itemCopyID'] ?? '';

if (!is_string($raw) ||
    !ctype_digit($raw) ||
    (int)$raw <= 0) {
    respond([
        'ok' => false,
        'error' => 'Valid item copy ID required.'
    ], 400);
}

$copyID = (int)$raw;

try {
    $pdo->beginTransaction();

    // Lock the item copy and find its catalog item type.
    $stmt = $pdo->prepare(
        'SELECT
            ic.ItemCopyID,
            ic.Status,
            i.ItemType,
            i.ItemTypeID
         FROM Item_Copy ic
         JOIN ITEM i ON i.ItemID = ic.ItemID
         WHERE ic.ItemCopyID = ?
         FOR UPDATE'
    );
    $stmt->execute([$copyID]);
    $copy = $stmt->fetch();

    if (!$copy || $copy['Status'] !== 'CheckedOut') {
        throw new DomainException(
            'Copy is not currently checked out.'
        );
    }

    // Lock the active checkout.
    $stmt = $pdo->prepare(
        'SELECT
            c.CheckOutID,
            c.PatronID,
            c.DueDate,
            COALESCE(c.RenewalCount, 0) AS renewalCount,
            CONCAT(p.FirstName, " ", p.LastName)
                AS patronName
         FROM CheckOut c
         JOIN Patron p ON p.PatronID = c.PatronID
         WHERE c.ItemCopyID = ?
           AND c.ReturnDate IS NULL
         FOR UPDATE'
    );
    $stmt->execute([$copyID]);
    $loans = $stmt->fetchAll();

    if (count($loans) !== 1) {
        throw new DomainException(
            'Exactly one active checkout is required.'
        );
    }

    $loan = $loans[0];

    // Outstanding fines prevent renewal.
    $stmt = $pdo->prepare(
        'SELECT COALESCE(
            SUM(GREATEST(
                COALESCE(Amount, 0) -
                COALESCE(AmountPaid, 0), 0
            )), 0)
         FROM Fine
         WHERE PatronID = ?'
    );
    $stmt->execute([(int)$loan['PatronID']]);

    if ((float)$stmt->fetchColumn() > 0) {
        throw new DomainException(
            'Renewal denied: patron has outstanding fines.'
        );
    }

    // Maximum two renewals per checkout.
    $renewalCount = (int)$loan['renewalCount'];

    if ($renewalCount >= 2) {
        throw new DomainException(
            'Two-renewal limit reached.'
        );
    }

    if (!$loan['DueDate']) {
        throw new DomainException(
            'Checkout has no due date.'
        );
    }

    $today = new DateTimeImmutable('today');

    $currentDue = new DateTimeImmutable(
        substr($loan['DueDate'], 0, 10)
    );

    // Do not renew overdue items.
    if ($currentDue < $today) {
        throw new DomainException(
            'Overdue items cannot be renewed.'
        );
    }

    // Do not renew if another patron has an active hold.
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM Reservation
         WHERE ItemCopyID = ?
           AND PatronID <> ?
           AND Status IN ('Pending', 'Ready')"
    );
    $stmt->execute([
        $copyID,
        (int)$loan['PatronID']
    ]);

    if ((int)$stmt->fetchColumn() > 0) {
        throw new DomainException(
            'Another patron has reserved this copy.'
        );
    }

    // Find the configured loan duration by item type.
    // ItemTypeID takes priority over legacy text matching.
    $stmt = $pdo->prepare(
        'SELECT LoanPeriodDays
         FROM Item_Type
         WHERE ItemTypeID = ?
            OR LOWER(TRIM(TypeName)) =
               LOWER(TRIM(?))
         ORDER BY
            CASE
                WHEN ItemTypeID = ? THEN 0
                ELSE 1
            END
         LIMIT 1'
    );
    $stmt->execute([
        $copy['ItemTypeID'],
        $copy['ItemType'],
        $copy['ItemTypeID']
    ]);

    $days = (int)$stmt->fetchColumn();

    if ($days <= 0) {
        throw new DomainException(
            'No loan period configured for this item type.'
        );
    }

    /*
     * NON-COMPOUNDING RENEWAL
     *
     * Calculate the renewal due date from today,
     * not from the existing due date.
     *
     * Preserve the existing due date if it is later,
     * so an early renewal never shortens a loan.
     *
     * Example: 14-day book loan
     * Checkout Nov 10 -> Nov 24
     * Renew Nov 10 -> Nov 24
     * Renew Nov 10 -> Nov 24
     *
     * Renewal count still increases each time.
     */

    $renewalDue = $today->modify(
        '+' . $days . ' days'
    );

    $newDueDate = $renewalDue > $currentDue
        ? $renewalDue
        : $currentDue;

    $newDue = $newDueDate->format('Y-m-d');

    $stmt = $pdo->prepare(
        'UPDATE CheckOut
         SET DueDate = ?,
             RenewalCount = COALESCE(RenewalCount, 0) + 1
         WHERE CheckOutID = ?
           AND ReturnDate IS NULL'
    );
    $stmt->execute([
        $newDue,
        (int)$loan['CheckOutID']
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Could not update renewal.'
        );
    }

    $pdo->commit();

    respond([
        'ok' => true,
        'message' => 'Renewal completed successfully.',
        'checkout' => [
            'checkoutID' => (int)$loan['CheckOutID'],
            'itemCopyID' => $copyID,
            'patronName' => $loan['patronName'],
            'itemType' => $copy['ItemType'],
            'previousDueDate' => $loan['DueDate'],
            'dueDate' => $newDue,
            'renewalCount' => $renewalCount + 1,
            'loanPeriodDays' => $days,
            'status' => 'CheckedOut'
        ]
    ]);

} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respond([
        'ok' => false,
        'error' => $e->getMessage()
    ], 409);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('renewItem: ' . $e->getMessage());

    respond([
        'ok' => false,
        'error' => 'Could not renew checkout.'
    ], 500);
}
