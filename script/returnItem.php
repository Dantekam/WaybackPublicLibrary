
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
        'error' => 'Valid item copy and return branch are required.'
    ], 400);
}

$copyID = (int)$copyRaw;
$branchID = (int)$branchRaw;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT ItemCopyID, Status, HomeBranchID
         FROM Item_Copy
         WHERE ItemCopyID = ?
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
    $activeLoans = $stmt->fetchAll();

    if (count($activeLoans) === 0) {
        throw new DomainException(
            'No active checkout exists for this item copy.'
        );
    }

    if (count($activeLoans) > 1) {
        throw new DomainException(
            'Multiple active checkouts found. Manual review required.'
        );
    }

    $checkout = $activeLoans[0];

    $today = new DateTimeImmutable('today');
    $returnDate = $today->format('Y-m-d');
    $dueDate = $checkout['DueDate'];

    $daysOverdue = 0;

    if ($dueDate) {
        $due = new DateTimeImmutable($dueDate);

        if ($today > $due) {
            $daysOverdue = (int)$due->diff($today)->days;
        }
    }

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
        throw new RuntimeException('Return update failed.');
    }

    $homeBranchID = (int)$copy['HomeBranchID'];
    $sameBranch = $homeBranchID === $branchID;

    // The copy cannot be checked out again until
    // its physical routing or reshelving is completed.
    $routingStatus = $sameBranch
        ? 'AwaitingReshelving'
        : 'AwaitingTransport';

    $destinationBranchID = $sameBranch
        ? null
        : $homeBranchID;

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
        $routingStatus,
        $destinationBranchID,
        $copyID
    ]);

    $pdo->commit();

    $instruction = $sameBranch
        ? 'Place item on the reshelving cart.'
        : 'Place item in the transport bin for its home branch.';

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
            'dueDate' => $dueDate,
            'returnDate' => $returnDate,
            'daysOverdue' => $daysOverdue,
            'fineAssessed' => null,
            'status' => 'Processing',
            'routingStatus' => $routingStatus,
            'destinationBranchID' => $destinationBranchID,
            'routingInstruction' => $instruction
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

    error_log('returnItem: ' . $e->getMessage());

    respond([
        'ok' => false,
        'error' => 'Could not record the return.'
    ], 500);
}
