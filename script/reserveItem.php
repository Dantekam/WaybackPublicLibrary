<?php
header('Content-Type: application/json');

// Db Connection
require __DIR__ . '/db_connect.php';

$patronID = $_POST['patronID'] ?? '';
$itemCopyID = $_POST['itemCopyID'] ?? '';

if (!ctype_digit((string)$patronID) || !ctype_digit((string)$itemCopyID)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid patron/item.']);
    exit;
}

$patronID = (int)$patronID;
$itemCopyID = (int)$itemCopyID;

try {
    $pdo->beginTransaction();

    // 1. Confirm item copy exists and grab its current status
    $stmt = $pdo->prepare(
        'SELECT ItemCopyID, Status
           FROM Item_Copy
          WHERE ItemCopyID = ?
          FOR UPDATE'
    );
    $stmt->execute([$itemCopyID]);
    $copy = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$copy) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Item copy not found.']);
        exit;
    }

    // 2. Confirm patron exists
    $stmt = $pdo->prepare(
        'SELECT PatronID
           FROM Patron
          WHERE PatronID = ?'
    );
    $stmt->execute([$patronID]);
    $patron = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patron) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Patron not found.']);
        exit;
    }

    // 3. Check for an existing active reservation for this item copy
    $stmt = $pdo->prepare(
        "SELECT ReservationID
           FROM Reservation
          WHERE ItemCopyID = ?
            AND Status IN ('Pending', 'Ready')
          LIMIT 1"
    );
    $stmt->execute([$itemCopyID]);
    $existingReservation = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingReservation) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'This item copy already has an active reservation.']);
        exit;
    }

    // 4. Determine whether the item is available for pickup now
    $isAvailable = in_array(strtolower($copy['Status']), ['Available', 'Open'], true);

    if ($isAvailable) {
        $reservationStatus = 'Ready';
        $readyForPickup = date('Y-m-d H:i:s');
        $expiration = date('Y-m-d H:i:s', strtotime('+5 days'));
    } else {
        // Item is currently unavailable; the pickup period starts when it becomes ready
        $reservationStatus = 'Pending';
        $readyForPickup = null;
        $expiration = null;
    }

    // 5. Create the reservation
    $stmt = $pdo->prepare(
        'INSERT INTO Reservation
            (PatronID, ItemCopyID, ReservationDateTime, ReadyForPickupDateTime, ExpirationDateTime, Status)
         VALUES (?, ?, NOW(), ?, ?, ?)'
    );
    $stmt->execute([
        $patronID,
        $itemCopyID,
        $readyForPickup,
        $expiration,
        $reservationStatus
    ]);
    $reservationID = $pdo->lastInsertId();

    // 6. Mark the item copy as reserved if it is available for pickup
    if ($isAvailable) {
        $stmt = $pdo->prepare(
            "UPDATE Item_Copy
                SET Status = 'Reserved'
              WHERE ItemCopyID = ?"
        );
        $stmt->execute([$itemCopyID]);
    }

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'message' => $isAvailable
            ? 'Item reserved and ready for pickup.'
            : 'Reservation created. The five-day pickup period will begin when the item is ready.',
        'reservation' => [
            'reservationID' => $reservationID,
            'patronID' => $patronID,
            'itemCopyID' => $itemCopyID,
            'status' => $reservationStatus,
            'expirationDateTime' => $expiration
        ]
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}