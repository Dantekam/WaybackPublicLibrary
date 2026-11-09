
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
    $destination = trim((string)($_GET['destinationBranchID'] ?? ''));

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
         FROM Branch ORDER BY BranchName'
    )->fetchAll();

    reply([
        'ok' => true,
        'items' => $stmt->fetchAll(),
        'branches' => $branches
    ]);
}

function applyAction($pdo, $copy, $action) {
    $id = (int)$copy['ItemCopyID'];
    $status = $copy['Status'];
    $routing = $copy['RoutingStatus'];

    if ($status === 'CheckedOut' || $status === 'Reserved') {
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
        if ($status !== 'Processing' || $routing !== 'Arrived') {
            throw new DomainException(
                "Copy {$id} has not been confirmed as arrived."
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

        // Do not make a copy available if it has an active loan.
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM CheckOut
             WHERE ItemCopyID = ? AND ReturnDate IS NULL'
        );
        $stmt->execute([$id]);

        if ((int)$stmt->fetchColumn() > 0) {
            throw new DomainException(
                "Copy {$id} has an active checkout."
            );
        }

        // Pending reservations require a separate pickup-ready
        // operation. Do not bypass their priority.
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM Reservation
             WHERE ItemCopyID = ?
               AND Status IN ('Pending', 'Ready')"
        );
        $stmt->execute([$id]);

        if ((int)$stmt->fetchColumn() > 0) {
            throw new DomainException(
                "Copy {$id} has an active reservation. " .
                "Process its hold before general shelving."
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
                 RoutingStatus = 'NotApplicable'
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
        reply(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }

    $action = trim((string)($_POST['action'] ?? ''));
    $ids = $_POST['itemCopyIDs'] ?? [];

    $allowed = [
        'startTransport',
        'confirmArrival',
        'beginReshelving',
        'confirmShelved',
        'maintenance',
        'processing'
    ];

    if (!in_array($action, $allowed, true)) {
        reply(['ok' => false, 'error' => 'Choose a valid action.'], 400);
    }

    if (!is_array($ids) || count($ids) === 0 ||
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
            reply(['ok' => false, 'error' => 'Invalid copy ID.'], 400);
        }
    }

    sort($ids, SORT_NUMERIC);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT ItemCopyID, Status, RoutingStatus,
                DestinationBranchID
         FROM Item_Copy
         WHERE ItemCopyID = ?
         FOR UPDATE'
    );

    foreach ($ids as $id) {
        $stmt->execute([(int)$id]);
        $copy = $stmt->fetch();

        if (!$copy) {
            throw new DomainException("Copy {$id} not found.");
        }

        applyAction($pdo, $copy, $action);
    }

    $pdo->commit();

    reply([
        'ok' => true,
        'message' => count($ids) . ' item copy/copies updated.',
        'updatedCount' => count($ids)
    ]);

} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    reply(['ok' => false, 'error' => $e->getMessage()], 409);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    error_log('itemStatuses: ' . $e->getMessage());

    reply([
        'ok' => false,
        'error' => 'Could not update inventory.'
    ], 500);
}
