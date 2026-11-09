
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

$patronRaw = $_POST['patronID'] ?? '';
$copyRaw = $_POST['itemCopyID'] ?? '';

if (!is_string($patronRaw) || !ctype_digit($patronRaw) ||
    (int)$patronRaw <= 0 ||
    !is_string($copyRaw) || !ctype_digit($copyRaw) ||
    (int)$copyRaw <= 0) {
    respond([
        'ok' => false,
        'error' => 'Valid patron and item copy IDs are required.'
    ], 400);
}

$patronID = (int)$patronRaw;
$copyID = (int)$copyRaw;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT PatronID, FirstName, LastName,
                MembershipExpiration
         FROM Patron
         WHERE PatronID = ?
         FOR UPDATE'
    );
    $stmt->execute([$patronID]);
    $patron = $stmt->fetch();

    if (!$patron) {
        throw new DomainException('Patron not found.');
    }

    if (!$patron['MembershipExpiration'] ||
        substr($patron['MembershipExpiration'], 0, 10)
            < date('Y-m-d')) {
        throw new DomainException('Patron membership has expired.');
    }

    $stmt = $pdo->prepare(
        'SELECT COALESCE(
            SUM(GREATEST(
                COALESCE(Amount, 0) -
                COALESCE(AmountPaid, 0), 0
            )), 0)
         FROM Fine
         WHERE PatronID = ?'
    );
    $stmt->execute([$patronID]);

    if ((float)$stmt->fetchColumn() > 0) {
        throw new DomainException(
            'Checkout denied: patron has outstanding fines.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM CheckOut
         WHERE PatronID = ? AND ReturnDate IS NULL'
    );
    $stmt->execute([$patronID]);

    if ((int)$stmt->fetchColumn() >= 20) {
        throw new DomainException(
            'Checkout denied: patron has reached the 20-item limit.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT
            ic.ItemCopyID,
            ic.Status,
            ic.RoutingStatus,
            ic.CurrentBranchID,
            i.ItemID,
            i.Title,
            i.ItemType,
            i.ItemTypeID,
            b.BranchName
         FROM Item_Copy ic
         JOIN ITEM i ON i.ItemID = ic.ItemID
         LEFT JOIN Branch b
           ON b.BranchID = ic.CurrentBranchID
         WHERE ic.ItemCopyID = ?
         FOR UPDATE'
    );
    $stmt->execute([$copyID]);
    $copy = $stmt->fetch();

    if (!$copy) {
        throw new DomainException('Item copy not found.');
    }

    if ($copy['Status'] !== 'Available') {
        throw new DomainException(
            'Item is not available. Reserved items must be ' .
            'checked out through Reservation Management.'
        );
    }

    if ($copy['RoutingStatus'] !== 'NotApplicable') {
        throw new DomainException(
            'Item has not completed its physical processing.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM CheckOut
         WHERE ItemCopyID = ?
           AND ReturnDate IS NULL'
    );
    $stmt->execute([$copyID]);

    if ((int)$stmt->fetchColumn() > 0) {
        throw new DomainException(
            'This copy already has an active checkout.'
        );
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM Reservation
         WHERE ItemCopyID = ?
           AND Status IN ('Pending', 'Ready')"
    );
    $stmt->execute([$copyID]);

    if ((int)$stmt->fetchColumn() > 0) {
        throw new DomainException(
            'This copy has an active reservation.'
        );
    }

    // Support older catalog records whose ItemTypeID is NULL.
    // The type name must still match a configured Item_Type row.
    $stmt = $pdo->prepare(
        'SELECT LoanPeriodDays
         FROM Item_Type
         WHERE ItemTypeID = ?
            OR LOWER(TRIM(TypeName)) =
               LOWER(TRIM(?))
         ORDER BY
            CASE WHEN ItemTypeID = ? THEN 0 ELSE 1 END
         LIMIT 1'
    );
    $stmt->execute([
        $copy['ItemTypeID'],
        $copy['ItemType'],
        $copy['ItemTypeID']
    ]);

    $loanDays = (int)$stmt->fetchColumn();

    if ($loanDays <= 0) {
        throw new DomainException(
            'No loan period is configured for this item type.'
        );
    }

    $today = new DateTimeImmutable('today');
    $checkoutDate = $today->format('Y-m-d');
    $dueDate = $today
        ->modify('+' . $loanDays . ' days')
        ->format('Y-m-d');

    $stmt = $pdo->prepare(
        'INSERT INTO CheckOut
         (PatronID, ItemCopyID, CheckOutDate,
          DueDate, ReturnDate, RenewalCount)
         VALUES (?, ?, ?, ?, NULL, 0)'
    );
    $stmt->execute([
        $patronID, $copyID, $checkoutDate, $dueDate
    ]);

    $checkoutID = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "UPDATE Item_Copy
         SET Status = 'CheckedOut'
         WHERE ItemCopyID = ?
           AND Status = 'Available'"
    );
    $stmt->execute([$copyID]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Could not update copy status.');
    }

    $stmt = $pdo->prepare(
        'SELECT GROUP_CONCAT(
            DISTINCT CONCAT(a.FirstName, " ", a.LastName)
            ORDER BY a.LastName, a.FirstName
            SEPARATOR ", "
        )
         FROM Item_Author ia
         JOIN Author a ON a.AuthorID = ia.AuthorID
         WHERE ia.ItemID = ?'
    );
    $stmt->execute([$copy['ItemID']]);
    $author = $stmt->fetchColumn() ?: 'Unknown';

    $pdo->commit();

    respond([
        'ok' => true,
        'message' => 'Checkout recorded successfully.',
        'checkout' => [
            'checkoutID' => $checkoutID,
            'patronID' => $patronID,
            'patron' => trim(
                $patron['FirstName'] . ' ' . $patron['LastName']
            ),
            'itemCopyID' => $copyID,
            'title' => $copy['Title'],
            'author' => $author,
            'itemType' => $copy['ItemType'],
            'branchID' => $copy['CurrentBranchID'],
            'branchName' => $copy['BranchName'],
            'checkoutDate' => $checkoutDate,
            'dueDate' => $dueDate,
            'renewalCount' => 0,
            'status' => 'CheckedOut'
        ]
    ]);

} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond(['ok' => false, 'error' => $e->getMessage()], 409);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('recordCheckout: ' . $e->getMessage());
    respond([
        'ok' => false,
        'error' => 'Could not record checkout.'
    ], 500);
}
