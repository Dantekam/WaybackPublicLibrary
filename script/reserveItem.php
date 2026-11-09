
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

    // Lock the physical copy to serialize reservation requests.
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

    if (in_array($copy['Status'], ['Retired', 'Maintenance'], true)) {
        throw new DomainException(
            'This item copy cannot currently be reserved.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT PatronID, MembershipExpiration
         FROM Patron
         WHERE PatronID = ?'
    );
    $stmt->execute([$patronID]);
    $patron = $stmt->fetch();

    if (!$patron) {
        throw new DomainException('Patron not found.');
    }

    if (!$patron['MembershipExpiration'] ||
        $patron['MembershipExpiration'] < date('Y-m-d')) {
        throw new DomainException(
            'Patron membership has expired.'
        );
    }

    $stmt = $pdo->prepare(
        "SELECT ReservationID
         FROM Reservation
         WHERE ItemCopyID = ?
           AND Status IN ('Pending', 'Ready')
         LIMIT 1"
    );
    $stmt->execute([$copyID]);

    if ($stmt->fetch()) {
        throw new DomainException(
            'This item copy already has an active reservation.'
        );
    }

    $available = $copy['Status'] === 'Available';

    $status = $available ? 'Ready' : 'Pending';
    $readyDate = $available ? date('Y-m-d H:i:s') : null;
    $expiration = $available
        ? date('Y-m-d H:i:s', strtotime('+5 days'))
        : null;

    $stmt = $pdo->prepare(
        'INSERT INTO Reservation
         (PatronID, ItemCopyID, ReservationDateTime,
          ReadyForPickupDateTime, ExpirationDateTime, Status)
         VALUES (?, ?, NOW(), ?, ?, ?)'
    );
    $stmt->execute([
        $patronID,
        $copyID,
        $readyDate,
        $expiration,
        $status
    ]);

    $reservationID = (int)$pdo->lastInsertId();

    if ($available) {
        $stmt = $pdo->prepare(
            "UPDATE Item_Copy
             SET Status = 'Reserved'
             WHERE ItemCopyID = ?
               AND Status = 'Available'"
        );
        $stmt->execute([$copyID]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Reservation status update failed.');
        }
    }

    $pdo->commit();

    respond([
        'ok' => true,
        'message' => $available
            ? 'Item reserved and ready for pickup.'
            : 'Reservation created. Pickup begins when the item is ready.',
        'reservation' => [
            'reservationID' => $reservationID,
            'patronID' => $patronID,
            'itemCopyID' => $copyID,
            'status' => $status,
            'readyForPickupDateTime' => $readyDate,
            'expirationDateTime' => $expiration
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

    error_log('reserveItem: ' . $e->getMessage());

    respond([
        'ok' => false,
        'error' => 'Could not create the reservation.'
    ], 500);
}
