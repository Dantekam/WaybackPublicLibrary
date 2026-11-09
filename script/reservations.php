<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db_connect.php';

function answer($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function positive($key) {
    $v = $_POST[$key] ?? '';
    return is_scalar($v) && ctype_digit((string)$v)
        && (int)$v > 0 ? (int)$v : 0;
}

function failBusiness($message) {
    throw new DomainException($message);
}

function loanDays($pdo, $itemID) {
    $stmt = $pdo->prepare(
        'SELECT t.LoanPeriodDays
         FROM ITEM i
         JOIN Item_Type t
           ON t.ItemTypeID = i.ItemTypeID
         WHERE i.ItemID = ?'
    );
    $stmt->execute([$itemID]);
    $days = (int)$stmt->fetchColumn();

    if ($days <= 0) {
        failBusiness('Item type has no configured loan period.');
    }
    return $days;
}

function releaseHold($pdo, $reservation, $status) {
    $stmt = $pdo->prepare(
        'UPDATE Reservation SET Status = ?
         WHERE ReservationID = ?'
    );
    $stmt->execute([$status, $reservation['ReservationID']]);

    if ($reservation['Status'] !== 'Ready') return;

    $stmt = $pdo->prepare(
        'SELECT Status FROM Item_Copy
         WHERE ItemCopyID = ? FOR UPDATE'
    );
    $stmt->execute([$reservation['ItemCopyID']]);
    $copyStatus = $stmt->fetchColumn();

    if ($copyStatus === 'Reserved') {
        $stmt = $pdo->prepare(
            "UPDATE Item_Copy
             SET Status = 'Processing',
                 RoutingStatus = 'AwaitingReshelving',
                 DestinationBranchID = NULL
             WHERE ItemCopyID = ?"
        );
        $stmt->execute([$reservation['ItemCopyID']]);
    }
}

function expireHolds($pdo) {
    $stmt = $pdo->query(
        "SELECT ReservationID
         FROM Reservation
         WHERE Status = 'Ready'
           AND ExpirationDateTime <= NOW()
         ORDER BY ReservationID"
    );

    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $id) {
        $pdo->beginTransaction();

        $q = $pdo->prepare(
            'SELECT * FROM Reservation
             WHERE ReservationID = ? FOR UPDATE'
        );
        $q->execute([$id]);
        $reservation = $q->fetch();

        if ($reservation &&
            $reservation['Status'] === 'Ready' &&
            $reservation['ExpirationDateTime'] <= date('Y-m-d H:i:s')) {
            releaseHold($pdo, $reservation, 'Expired');
        }

        $pdo->commit();
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        expireHolds($pdo);

        $rows = $pdo->query(
            'SELECT
                r.ReservationID AS reservationID,
                r.PatronID AS patronID,
                CONCAT(p.FirstName, " ", p.LastName)
                    AS patronName,
                r.ItemCopyID AS itemCopyID,
                COALESCE(i.Title, ic.Title) AS title,
                r.PickupBranchID AS pickupBranchID,
                b.BranchName AS pickupBranch,
                cb.BranchName AS currentBranch,
                r.ReservationDateTime AS createdAt,
                r.ReadyForPickupDateTime AS readyAt,
                r.ExpirationDateTime AS expiresAt,
                r.Status AS status,
                ic.Status AS copyStatus,
                ic.RoutingStatus AS routingStatus
             FROM Reservation r
             JOIN Patron p ON p.PatronID = r.PatronID
             JOIN Item_Copy ic ON ic.ItemCopyID = r.ItemCopyID
             LEFT JOIN ITEM i ON i.ItemID = ic.ItemID
             LEFT JOIN Branch b ON b.BranchID = r.PickupBranchID
             LEFT JOIN Branch cb
               ON cb.BranchID = ic.CurrentBranchID
             ORDER BY
                CASE r.Status
                  WHEN "Ready" THEN 0
                  WHEN "Pending" THEN 1
                  ELSE 2
                END,
                r.ReservationDateTime DESC
             LIMIT 300'
        )->fetchAll();

        $branches = $pdo->query(
            'SELECT BranchID, BranchName
             FROM Branch ORDER BY BranchName'
        )->fetchAll();

        answer([
            'ok' => true,
            'reservations' => $rows,
            'branches' => $branches
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        answer(['ok' => false, 'error' => 'POST required.'], 405);
    }

    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'create') {
        $patronID = positive('patronID');
        $copyID = positive('itemCopyID');
        $pickupID = positive('pickupBranchID');

        if (!$patronID || !$copyID || !$pickupID) {
            failBusiness('Patron, copy, and pickup branch are required.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT ItemCopyID, ItemID, Status,
                    CurrentBranchID, RoutingStatus
             FROM Item_Copy
             WHERE ItemCopyID = ? FOR UPDATE'
        );
        $stmt->execute([$copyID]);
        $copy = $stmt->fetch();

        if (!$copy) failBusiness('Item copy not found.');

        if (!in_array(
            $copy['Status'],
            ['Available', 'CheckedOut', 'Processing'],
            true
        )) {
            failBusiness('This copy cannot be reserved.');
        }

        $stmt = $pdo->prepare(
            'SELECT MembershipExpiration
             FROM Patron WHERE PatronID = ?'
        );
        $stmt->execute([$patronID]);
        $expiry = $stmt->fetchColumn();

        if (!$expiry || $expiry < date('Y-m-d')) {
            failBusiness('Patron not found or membership expired.');
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM Branch WHERE BranchID = ?'
        );
        $stmt->execute([$pickupID]);
        if (!(int)$stmt->fetchColumn()) {
            failBusiness('Pickup branch not found.');
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM Reservation
             WHERE ItemCopyID = ?
               AND Status IN ('Pending', 'Ready')"
        );
        $stmt->execute([$copyID]);

        if ((int)$stmt->fetchColumn()) {
            failBusiness('This copy already has an active reservation.');
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM CheckOut
             WHERE ItemCopyID = ? AND ReturnDate IS NULL'
        );
        $stmt->execute([$copyID]);
        $activeLoans = (int)$stmt->fetchColumn();

        if ($activeLoans > 1) {
            failBusiness('Multiple active loans require manual review.');
        }

        if ($copy['Status'] === 'CheckedOut' && !$activeLoans) {
            failBusiness('Copy status and loan history disagree.');
        }

        if ($copy['Status'] !== 'CheckedOut' && $activeLoans) {
            failBusiness('Copy has an active checkout.');
        }

        $sameBranch =
            (int)$copy['CurrentBranchID'] === $pickupID;

        $ready =
            $copy['Status'] === 'Available' &&
            $sameBranch &&
            $copy['RoutingStatus'] === 'NotApplicable';

        $status = $ready ? 'Ready' : 'Pending';

        $stmt = $pdo->prepare(
            'INSERT INTO Reservation
             (PatronID, ItemCopyID, PickupBranchID,
              ReservationDateTime,
              ReadyForPickupDateTime,
              ExpirationDateTime, Status)
             VALUES (?, ?, ?, NOW(),
                     IF(? = "Ready", NOW(), NULL),
                     IF(? = "Ready",
                        DATE_ADD(NOW(), INTERVAL 5 DAY),
                        NULL), ?)'
        );
        $stmt->execute([
            $patronID, $copyID, $pickupID,
            $status, $status, $status
        ]);

        $reservationID = (int)$pdo->lastInsertId();

        if ($ready) {
            $stmt = $pdo->prepare(
                "UPDATE Item_Copy SET Status = 'Reserved'
                 WHERE ItemCopyID = ?"
            );
            $stmt->execute([$copyID]);

        } elseif ($copy['Status'] === 'Available') {
            $stmt = $pdo->prepare(
                "UPDATE Item_Copy
                 SET Status = 'Processing',
                     RoutingStatus = 'AwaitingTransport',
                     DestinationBranchID = ?
                 WHERE ItemCopyID = ?"
            );
            $stmt->execute([$pickupID, $copyID]);
        }

        $pdo->commit();

        answer([
            'ok' => true,
            'message' => $ready
                ? 'Reservation ready for pickup.'
                : 'Pending reservation created.',
            'reservationID' => $reservationID,
            'status' => $status
        ]);
    }

    $reservationID = positive('reservationID');
    if (!$reservationID) {
        failBusiness('Select a valid reservation.');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT * FROM Reservation
         WHERE ReservationID = ? FOR UPDATE'
    );
    $stmt->execute([$reservationID]);
    $reservation = $stmt->fetch();

    if (!$reservation) failBusiness('Reservation not found.');

    $copyID = (int)$reservation['ItemCopyID'];

    $stmt = $pdo->prepare(
        'SELECT * FROM Item_Copy
         WHERE ItemCopyID = ? FOR UPDATE'
    );
    $stmt->execute([$copyID]);
    $copy = $stmt->fetch();

    if (!$copy) failBusiness('Physical copy not found.');

    if ($action === 'cancel') {
        if (!in_array(
            $reservation['Status'],
            ['Pending', 'Ready'],
            true
        )) {
            failBusiness('Reservation is already closed.');
        }

        releaseHold($pdo, $reservation, 'Cancelled');

        // A cancelled pending hold must no longer force
        // transport to the former pickup branch.
        if ($reservation['Status'] === 'Pending' &&
            $copy['Status'] === 'Processing' &&
            $copy['RoutingStatus'] === 'AwaitingTransport') {
            $home = (int)$copy['HomeBranchID'];
            $current = (int)$copy['CurrentBranchID'];

            $stmt = $pdo->prepare(
                'UPDATE Item_Copy
                 SET RoutingStatus = ?,
                     DestinationBranchID = ?
                 WHERE ItemCopyID = ?'
            );
            $stmt->execute([
                $home === $current
                    ? 'AwaitingReshelving'
                    : 'AwaitingTransport',
                $home === $current ? null : $home,
                $copyID
            ]);
        }

        $message = 'Reservation cancelled.';

    } elseif ($action === 'ready') {
        if ($reservation['Status'] !== 'Pending') {
            failBusiness('Only pending reservations can become ready.');
        }

        if ($copy['Status'] !== 'Processing' ||
            !in_array(
                $copy['RoutingStatus'],
                ['Arrived', 'AwaitingReshelving', 'NotApplicable'],
                true
            ) ||
            (int)$copy['CurrentBranchID'] !==
                (int)$reservation['PickupBranchID']) {
            failBusiness(
                'Copy must be physically at the pickup branch ' .
                'and finished with transport before marking ready.'
            );
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM CheckOut
             WHERE ItemCopyID = ? AND ReturnDate IS NULL'
        );
        $stmt->execute([$copyID]);
        if ((int)$stmt->fetchColumn()) {
            failBusiness('Copy is still checked out.');
        }

        $stmt = $pdo->prepare(
            "UPDATE Reservation
             SET Status = 'Ready',
                 ReadyForPickupDateTime = NOW(),
                 ExpirationDateTime =
                     DATE_ADD(NOW(), INTERVAL 5 DAY)
             WHERE ReservationID = ?"
        );
        $stmt->execute([$reservationID]);

        $stmt = $pdo->prepare(
            "UPDATE Item_Copy
             SET Status = 'Reserved',
                 RoutingStatus = 'NotApplicable',
                 DestinationBranchID = NULL
             WHERE ItemCopyID = ?"
        );
        $stmt->execute([$copyID]);

        $message = 'Copy is ready for pickup for five days.';

    } elseif ($action === 'fulfill') {
        if ($reservation['Status'] !== 'Ready' ||
            $copy['Status'] !== 'Reserved') {
            failBusiness('Reservation is not ready for pickup.');
        }

        if ($reservation['ExpirationDateTime'] <=
            date('Y-m-d H:i:s')) {
            failBusiness('Reservation has expired. Refresh the page.');
        }

        $patronID = (int)$reservation['PatronID'];

        $stmt = $pdo->prepare(
            'SELECT MembershipExpiration
             FROM Patron WHERE PatronID = ?'
        );
        $stmt->execute([$patronID]);
        $membership = $stmt->fetchColumn();

        if (!$membership || $membership < date('Y-m-d')) {
            failBusiness('Patron membership is expired.');
        }

        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(GREATEST(
                COALESCE(Amount, 0) -
                COALESCE(AmountPaid, 0), 0
            )), 0)
             FROM Fine WHERE PatronID = ?'
        );
        $stmt->execute([$patronID]);

        if ((float)$stmt->fetchColumn() > 0) {
            failBusiness('Patron has outstanding fines.');
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM CheckOut
             WHERE PatronID = ? AND ReturnDate IS NULL'
        );
        $stmt->execute([$patronID]);

        if ((int)$stmt->fetchColumn() >= 20) {
            failBusiness('Patron has reached the checkout limit.');
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM CheckOut
             WHERE ItemCopyID = ? AND ReturnDate IS NULL'
        );
        $stmt->execute([$copyID]);

        if ((int)$stmt->fetchColumn()) {
            failBusiness('Copy already has an active checkout.');
        }

        $days = loanDays($pdo, $copy['ItemID']);

        $stmt = $pdo->prepare(
            'INSERT INTO CheckOut
             (PatronID, ItemCopyID, CheckOutDate,
              DueDate, ReturnDate, RenewalCount)
             VALUES (?, ?, CURDATE(),
                     DATE_ADD(CURDATE(), INTERVAL ? DAY),
                     NULL, 0)'
        );
        $stmt->execute([$patronID, $copyID, $days]);

        $stmt = $pdo->prepare(
            "UPDATE Reservation SET Status = 'Fulfilled'
             WHERE ReservationID = ?"
        );
        $stmt->execute([$reservationID]);

        $stmt = $pdo->prepare(
            "UPDATE Item_Copy
             SET Status = 'CheckedOut',
                 RoutingStatus = 'NotApplicable',
                 DestinationBranchID = NULL
             WHERE ItemCopyID = ?"
        );
        $stmt->execute([$copyID]);

        $message = 'Reservation fulfilled and checkout recorded.';

    } else {
        failBusiness('Unknown reservation action.');
    }

    $pdo->commit();
    answer(['ok' => true, 'message' => $message]);

} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    answer(['ok' => false, 'error' => $e->getMessage()], 409);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('reservations: ' . $e->getMessage());
    answer([
        'ok' => false,
        'error' => 'Reservation operation failed.'
    ], 500);
}