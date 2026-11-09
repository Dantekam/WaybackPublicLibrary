
<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db_connect.php';

function reply($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function loadInventory($pdo) {
    $search = trim((string)($_GET['search'] ?? ''));
    $branch = trim((string)($_GET['branchID'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));
    $routing = trim((string)($_GET['routingStatus'] ?? ''));
    $destination = trim(
        (string)($_GET['destinationBranchID'] ?? '')
    );

    $sql = '
        SELECT
            ic.ItemCopyID AS itemCopyID,
            COALESCE(i.Title, ic.Title) AS title,
            ic.ItemType AS itemType,
            ic.Status AS status,
            ic.RoutingStatus AS routingStatus,
            ic.HomeBranchID AS homeBranchID,
            hb.BranchName AS homeBranch,
            ic.CurrentBranchID AS currentBranchID,
            cb.BranchName AS currentBranch,
            ic.DestinationBranchID AS destinationBranchID,
            db.BranchName AS destinationBranch,
            ic.ShelfLocationCode AS shelfLocation
        FROM Item_Copy ic
        LEFT JOIN ITEM i ON i.ItemID = ic.ItemID
        LEFT JOIN Branch hb ON hb.BranchID = ic.HomeBranchID
        LEFT JOIN Branch cb ON cb.BranchID = ic.CurrentBranchID
        LEFT JOIN Branch db ON db.BranchID = ic.DestinationBranchID
        WHERE 1 = 1
    ';

    $params = [];

    if ($search !== '') {
        $sql .= ' AND (
            CAST(ic.ItemCopyID AS CHAR) LIKE ?
            OR COALESCE(i.Title, ic.Title) LIKE ?
        )';

        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if ($branch !== '' && ctype_digit($branch)) {
        $sql .= ' AND ic.CurrentBranchID = ?';
        $params[] = (int)$branch;
    }

    if ($status !== '') {
        $sql .= ' AND ic.Status = ?';
        $params[] = $status;
    }

    if ($routing !== '') {
        $sql .= ' AND ic.RoutingStatus = ?';
        $params[] = $routing;
    }

    if ($destination !== '' && ctype_digit($destination)) {
        $sql .= ' AND ic.DestinationBranchID = ?';
        $params[] = (int)$destination;
    }

    $sql .= ' ORDER BY ic.ItemCopyID LIMIT 500';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $branches = $pdo->query(
        'SELECT BranchID AS id, BranchName AS name
         FROM Branch
         ORDER BY BranchName'
    )->fetchAll();

    reply([
        'ok' => true,
        'items' => $stmt->fetchAll(),
        'branches' => $branches
    ]);
}

function activeReservation($pdo, $copyID) {
    $stmt = $pdo->prepare(
        "SELECT ReservationID, PickupBranchID, Status
         FROM Reservation
         WHERE ItemCopyID = ?
           AND Status IN ('Pending', 'Ready')
         ORDER BY ReservationID
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->execute([$copyID]);
    return $stmt->fetch() ?: null;
}

function activeLoanCount($pdo, $copyID) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM CheckOut
         WHERE ItemCopyID = ?
           AND ReturnDate IS NULL'
    );

    $stmt->execute([$copyID]);
    return (int)$stmt->fetchColumn();
}

function applyAction(
    $pdo,
    $copy,
    $action,
    $destinationBranchID = null
) {
    $id = (int)$copy['ItemCopyID'];
    $status = $copy['Status'];
    $routing = $copy['RoutingStatus'];

    $currentBranchID = (int)$copy['CurrentBranchID'];

    if ($action === 'routeToBranch') {
        if (!in_array(
            $status,
            ['Available', 'Processing'],
            true
        )) {
            throw new DomainException(
                "Copy {$id} cannot be routed while {$status}."
            );
        }

        if (!in_array(
            $routing,
            [
                'NotApplicable',
                'AwaitingReshelving',
                'Arrived'
            ],
            true
        )) {
            throw new DomainException(
                "Copy {$id} is already undergoing transport."
            );
        }

        if (!$currentBranchID) {
            throw new DomainException(
                "Copy {$id} has no current branch."
            );
        }

        if ($currentBranchID === (int)$destinationBranchID) {
            throw new DomainException(
                "Copy {$id} is already at the destination."
            );
        }

        if (activeLoanCount($pdo, $id) > 0) {
            throw new DomainException(
                "Copy {$id} still has an active checkout."
            );
        }

        $reservation = activeReservation($pdo, $id);

        if ($reservation) {
            if ($reservation['Status'] !== 'Pending') {
                throw new DomainException(
                    "Copy {$id} has a ready reservation."
                );
            }

            if ((int)$reservation['PickupBranchID'] !==
                (int)$destinationBranchID) {
                throw new DomainException(
                    "Copy {$id} is reserved for a different " .
                    "pickup branch."
                );
            }
        }

        $stmt = $pdo->prepare(
            "UPDATE Item_Copy
             SET Status = 'Processing',
                 RoutingStatus = 'AwaitingTransport',
                 DestinationBranchID = ?
             WHERE ItemCopyID = ?"
        );

        $stmt->execute([
            $destinationBranchID,
            $id
        ]);

        return;
    }

    if ($status === 'CheckedOut' ||
        $status === 'Reserved') {
        throw new DomainException(
            "Copy {$id} is checked out or reserved."
        );
    }

    if ($action === 'startTransport') {
        if ($status !== 'Processing' ||
            $routing !== 'AwaitingTransport' ||
            !$copy['DestinationBranchID']) {
            throw new DomainException(
                "Copy {$id} is not awaiting transport."
            );
        }

        if (activeLoanCount($pdo, $id) > 0) {
            throw new DomainException(
                "Copy {$id} still has an active checkout."
            );
        }

        $stmt = $pdo->prepare(
            "UPDATE Item_Copy
             SET RoutingStatus = 'InTransit'
             WHERE ItemCopyID = ?"
        );

        $stmt->execute([$id]);
        return;
    }

    if ($action === 'confirmArrival') {
        if ($status !== 'Processing' ||
            $routing !== 'InTransit' ||
            !$copy['DestinationBranchID']) {
            throw new DomainException(
                "Copy {$id} is not in transit."
            );
        }

        if (activeLoanCount($pdo, $id) > 0) {
            throw new DomainException(
                "Copy {$id} still has an active checkout."
            );
        }

        $reservation = activeReservation($pdo, $id);

        if ($reservation &&
            (int)$reservation['PickupBranchID'] !==
            (int)$copy['DestinationBranchID']) {
            throw new DomainException(
                "Copy {$id} is traveling to the wrong " .
                "branch for its reservation."
            );
        }

        $stmt = $pdo->prepare(
            "UPDATE Item_Copy
             SET CurrentBranchID = DestinationBranchID,
                 DestinationBranchID = NULL,
                 RoutingStatus = 'Arrived'
             WHERE ItemCopyID = ?"
        );

        $stmt->execute([$id]);
        return;
    }

    if ($action === 'beginReshelving') {
        if ($status !== 'Processing' ||
            !in_array(
                $routing,
                ['Arrived', 'AwaitingReshelving'],
                true
            )) {
            throw new DomainException(
                "Copy {$id} is not ready for reshelving."
            );
        }

        if (activeReservation($pdo, $id)) {
            throw new DomainException(
                "Copy {$id} has an active reservation. " .
                "Prepare its pickup instead of reshelving."
            );
        }

        if (activeLoanCount($pdo, $id) > 0) {
            throw new DomainException(
                "Copy {$id} has an active checkout."
            );
        }

        $stmt = $pdo->prepare(
            "UPDATE Item_Copy
             SET RoutingStatus = 'AwaitingReshelving'
             WHERE ItemCopyID = ?"
        );

        $stmt->execute([$id]);
        return;
    }

    if ($action === 'confirmShelved') {
        if ($status !== 'Processing' ||
            !in_array(
                $routing,
                ['AwaitingReshelving', 'NotApplicable'],
                true
            )) {
            throw new DomainException(
                "Copy {$id} is not ready for shelving."
            );
        }

        if (activeLoanCount($pdo, $id) > 0) {
            throw new DomainException(
                "Copy {$id} has an active checkout."
            );
        }

        if (activeReservation($pdo, $id)) {
            throw new DomainException(
                "Copy {$id} has an active reservation. " .
                "Process its hold before shelving."
            );
        }

        $stmt = $pdo->prepare(
            "UPDATE Item_Copy
             SET Status = 'Available',
                 RoutingStatus = 'NotApplicable',
                 DestinationBranchID = NULL
             WHERE ItemCopyID = ?"
        );

        $stmt->execute([$id]);
        return;
    }

    if ($action === 'maintenance') {
        if ($routing === 'InTransit') {
            throw new DomainException(
                "Copy {$id} must arrive before maintenance."
            );
        }

        if (activeLoanCount($pdo, $id) > 0) {
            throw new DomainException(
                "Copy {$id} has an active checkout."
            );
        }

        if (activeReservation($pdo, $id)) {
            throw new DomainException(
                "Copy {$id} has an active reservation. " .
                "Cancel or resolve the hold first."
            );
        }

        $stmt = $pdo->prepare(
            "UPDATE Item_Copy
             SET Status = 'Maintenance',
                 RoutingStatus = 'NotApplicable',
                 DestinationBranchID = NULL
             WHERE ItemCopyID = ?"
        );

        $stmt->execute([$id]);
        return;
    }

    if ($action === 'processing') {
        if ($status !== 'Maintenance') {
            throw new DomainException(
                "Copy {$id} is not in maintenance."
            );
        }

        $stmt = $pdo->prepare(
            "UPDATE Item_Copy
             SET Status = 'Processing',
                 RoutingStatus = 'NotApplicable',
                 DestinationBranchID = NULL
             WHERE ItemCopyID = ?"
        );

        $stmt->execute([$id]);
        return;
    }

    throw new DomainException('Invalid inventory action.');
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        loadInventory($pdo);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        reply([
            'ok' => false,
            'error' => 'Method not allowed.'
        ], 405);
    }

    $action = trim((string)($_POST['action'] ?? ''));
    $ids = $_POST['itemCopyIDs'] ?? [];

    $allowed = [
        'routeToBranch',
        'startTransport',
        'confirmArrival',
        'beginReshelving',
        'confirmShelved',
        'maintenance',
        'processing'
    ];

    if (!in_array($action, $allowed, true)) {
        reply([
            'ok' => false,
            'error' => 'Choose a valid action.'
        ], 400);
    }

    if (!is_array($ids) ||
        count($ids) === 0 ||
        count($ids) > 100) {
        reply([
            'ok' => false,
            'error' => 'Select between 1 and 100 copies.'
        ], 400);
    }

    $ids = array_values(array_unique($ids));

    foreach ($ids as $id) {
        if (!is_scalar($id) ||
            !ctype_digit((string)$id) ||
            (int)$id <= 0) {
            reply([
                'ok' => false,
                'error' => 'Invalid copy ID.'
            ], 400);
        }
    }

    sort($ids, SORT_NUMERIC);

    $destinationBranchID = null;

    if ($action === 'routeToBranch') {
        $rawDestination = (string)(
            $_POST['destinationBranchID'] ?? ''
        );

        if (!ctype_digit($rawDestination) ||
            (int)$rawDestination <= 0) {
            reply([
                'ok' => false,
                'error' => 'Select a destination branch.'
            ], 400);
        }

        $destinationBranchID = (int)$rawDestination;

        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM Branch
             WHERE BranchID = ?'
        );

        $stmt->execute([$destinationBranchID]);

        if (!(int)$stmt->fetchColumn()) {
            reply([
                'ok' => false,
                'error' => 'Destination branch does not exist.'
            ], 400);
        }
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT ItemCopyID,
                Status,
                RoutingStatus,
                HomeBranchID,
                CurrentBranchID,
                DestinationBranchID
         FROM Item_Copy
         WHERE ItemCopyID = ?
         FOR UPDATE'
    );

    foreach ($ids as $id) {
        $stmt->execute([(int)$id]);
        $copy = $stmt->fetch();

        if (!$copy) {
            throw new DomainException(
                "Copy {$id} not found."
            );
        }

        applyAction(
            $pdo,
            $copy,
            $action,
            $destinationBranchID
        );
    }

    $pdo->commit();

    $actionMessages = [
        'routeToBranch' =>
            'Transfer scheduled. Start Transport when the ' .
            'selected copies physically leave their branch.',
        'startTransport' =>
            'Transport started. Confirm Arrival after delivery.',
        'confirmArrival' =>
            'Arrival confirmed. Current branch updated.',
        'beginReshelving' =>
            'Copies are awaiting reshelving.',
        'confirmShelved' =>
            'Copies are now available.',
        'maintenance' =>
            'Copies moved to maintenance.',
        'processing' =>
            'Copies returned to processing.'
    ];

    reply([
        'ok' => true,
        'message' =>
            count($ids) . ' copy/copies updated. ' .
            $actionMessages[$action],
        'updatedCount' => count($ids)
    ]);

} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    reply([
        'ok' => false,
        'error' => $e->getMessage()
    ], 409);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('itemStatuses: ' . $e->getMessage());

    reply([
        'ok' => false,
        'error' => 'Could not update inventory.'
    ], 500);
}
