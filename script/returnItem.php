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

$copyRaw = $_POST['itemCopyID'] ?? '';
$branchRaw = $_POST['returnBranchID'] ?? '';

if (!is_string($copyRaw) || !ctype_digit($copyRaw) ||
    (int)$copyRaw <= 0 ||
    !is_string($branchRaw) || !ctype_digit($branchRaw) ||
    (int)$branchRaw <= 0) {
    respond([
        'ok' => false,
        'error' => 'Valid copy and return branch IDs are required.'
    ], 400);
}

$copyID = (int)$copyRaw;
$branchID = (int)$branchRaw;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT
            ic.ItemCopyID,
            ic.Status,
            ic.HomeBranchID,
            ic.ItemID,
            i.ItemType,
            i.ItemTypeID
         FROM Item_Copy ic
         JOIN ITEM i ON i.ItemID = ic.ItemID
         WHERE ic.ItemCopyID = ?
         FOR UPDATE'
    );
    $stmt->execute([$copyID]);
    $copy = $stmt->fetch();

    if (!$copy) {
        throw new DomainException('Item copy not found.');
    }

    $stmt = $pdo->prepare(
        'SELECT BranchID, BranchName
         FROM Branch
         WHERE BranchID = ?'
    );
    $stmt->execute([$branchID]);
    $branch = $stmt->fetch();

    if (!$branch) {
        throw new DomainException('Return branch not found.');
    }

    $stmt = $pdo->prepare(
        'SELECT CheckOutID, PatronID, DueDate
         FROM CheckOut
         WHERE ItemCopyID = ?
           AND ReturnDate IS NULL
         FOR UPDATE'
    );
    $stmt->execute([$copyID]);
    $loans = $stmt->fetchAll();

    if (count($loans) !== 1) {
        throw new DomainException(
            'Exactly one active checkout is required for check-in.'
        );
    }

    if ($copy['Status'] !== 'CheckedOut') {
        throw new DomainException(
            'Copy status disagrees with its active checkout. ' .
            'Manual review is required.'
        );
    }

    $checkout = $loans[0];

    $stmt = $pdo->prepare(
        "SELECT ReservationID, PickupBranchID
         FROM Reservation
         WHERE ItemCopyID = ?
           AND Status = 'Pending'
         ORDER BY ReservationDateTime, ReservationID
         FOR UPDATE"
    );
    $stmt->execute([$copyID]);
    $holds = $stmt->fetchAll();

    if (count($holds) > 1) {
        throw new DomainException(
            'Multiple pending reservations require manual review.'
        );
    }

    $hold = $holds[0] ?? null;

    $homeBranchID = (int)$copy['HomeBranchID'];

    if ($homeBranchID <= 0) {
        throw new DomainException(
            'Copy has no valid home branch.'
        );
    }

    $destinationID = $hold
        ? (int)$hold['PickupBranchID']
        : $homeBranchID;

    if ($destinationID <= 0) {
        throw new DomainException(
            'Reservation has no valid pickup branch.'
        );
    }

    $today = new DateTimeImmutable('today');
    $returnDate = $today->format('Y-m-d');

    $daysOverdue = 0;
    $dueDate = $checkout['DueDate'];

    if ($dueDate) {
        $due = new DateTimeImmutable(
            substr($dueDate, 0, 10)
        );

        if ($today > $due) {
            $daysOverdue = (int)$due->diff($today)->days;
        }
    }

    $stmt = $pdo->prepare(
        'SELECT DailyFineRate
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

    $fineRate = $stmt->fetchColumn();

    if ($fineRate === false) {
        throw new DomainException(
            'No fine policy is configured for this item type.'
        );
    }

    $fineRate = (int)$fineRate;
    $fineUnits = $daysOverdue * $fineRate;

    $stmt = $pdo->prepare(
        'UPDATE CheckOut
         SET ReturnDate = ?, ReturnBranchID = ?
         WHERE CheckOutID = ?
           AND ReturnDate IS NULL'
    );
    $stmt->execute([
        $returnDate,
        $branchID,
        $checkout['CheckOutID']
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Could not record return.');
    }

    $sameBranch = $branchID === $destinationID;

    $routing = $sameBranch
        ? 'AwaitingReshelving'
        : 'AwaitingTransport';

    $nextDestination = $sameBranch
        ? null
        : $destinationID;

    $stmt = $pdo->prepare(
        'UPDATE Item_Copy
         SET CurrentBranchID = ?,
             Status = ?,
             RoutingStatus = ?,
             DestinationBranchID = ?
         WHERE ItemCopyID = ?'
    );
    $stmt->execute([
        $branchID,
        'Processing',
        $routing,
        $nextDestination,
        $copyID
    ]);

    $stmt = $pdo->prepare(
        'SELECT BranchName FROM Branch WHERE BranchID = ?'
    );
    $stmt->execute([$destinationID]);
    $destinationName = $stmt->fetchColumn();

    $pdo->commit();

    if ($sameBranch && $hold) {
        $instruction =
            'Move copy to reservation processing. ' .
            'Mark the hold Ready after physically verifying it.';
    } elseif ($sameBranch) {
        $instruction =
            'Place copy on the reshelving cart.';
    } elseif ($hold) {
        $instruction =
            'Send copy to the reservation pickup branch: ' .
            $destinationName . '.';
    } else {
        $instruction =
            'Send copy to its home branch: ' .
            $destinationName . '.';
    }

    respond([
        'ok' => true,
        'message' => 'Item checked in successfully.',
        'checkout' => [
            'checkoutID' => (int)$checkout['CheckOutID'],
            'itemCopyID' => $copyID,
            'patronID' => (int)$checkout['PatronID'],
            'returnBranchID' => $branchID,
            'returnBranchName' => $branch['BranchName'],
            'homeBranchID' => $homeBranchID,
            'destinationBranchID' => $nextDestination,
            'destinationBranchName' => $destinationName,
            'reservationID' => $hold
                ? (int)$hold['ReservationID']
                : null,
            'dueDate' => $dueDate,
            'returnDate' => $returnDate,
            'daysOverdue' => $daysOverdue,
            'dailyFineRate' => $fineRate,
            'calculatedFineUnits' => $fineUnits,
            'fineAssessed' => null,
            'status' => 'Processing',
            'routingStatus' => $routing,
            'routingInstruction' => $instruction
        ]
    ]);

} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond(['ok' => false, 'error' => $e->getMessage()], 409);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('returnItem: ' . $e->getMessage());
    respond([
        'ok' => false,
        'error' => 'Could not record return.'
    ], 500);
}