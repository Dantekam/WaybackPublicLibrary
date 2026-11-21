
<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db_connect.php';

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond([
        'ok' => false,
        'error' => 'POST required.'
    ], 405);
}

// Validate the Patron ID.
$raw = $_POST['patronID'] ?? '';

if (!is_string($raw) ||
    !ctype_digit($raw) ||
    (int)$raw <= 0) {
    respond([
        'ok' => false,
        'error' => 'Valid patron ID required.'
    ], 400);
}

$patronID = (int)$raw;

try {
    $pdo->beginTransaction();

    // 1-2. get patron and info
    $stmt = $pdo->prepare(
        'SELECT
            PatronID,
            FirstName,
            LastName,
            MembershipExpiration
         FROM Patron
         WHERE PatronID = ?
         FOR UPDATE'
    );

    $stmt->execute([$patronID]);
    $patron = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patron) {
        throw new DomainException(
            'Patron not found.'
        );
    }

    $fullName = trim(
        $patron['FirstName'] . ' ' . $patron['LastName']
    );

    $previousExpiration = $patron['MembershipExpiration'];

    // 4-5. Calculate the new expiration date as two years from the current renewal date
    $renewalDate = new DateTimeImmutable('today');

    $newExpiration = $renewalDate
        ->modify('+2 years')
        ->format('Y-m-d');

    // 6. Update the existing patron record
    $stmt = $pdo->prepare(
        'UPDATE Patron
         SET MembershipExpiration = ?
         WHERE PatronID = ?'
    );

    $stmt->execute([
        $newExpiration,
        $patronID
    ]);

    // Verify the updated membership expiration date
    $stmt = $pdo->prepare(
        'SELECT MembershipExpiration
         FROM Patron
         WHERE PatronID = ?'
    );

    $stmt->execute([$patronID]);
    $updatedExpiration = $stmt->fetchColumn();

    if ($updatedExpiration === false ||
        $updatedExpiration !== $newExpiration) {
        throw new RuntimeException(
            'Could not update membership expiration.'
        );
    }

    $pdo->commit();

    // 7. Display the updated membership data
    respond([
        'ok' => true,
        'message' => 'Membership renewed successfully.',
        'patron' => [
            'patronID' => $patronID,
            'firstName' => $patron['FirstName'],
            'lastName' => $patron['LastName'],
            'fullName' => $fullName,
            'previousExpirationDate' => $previousExpiration,
            'membershipExpiration' => $updatedExpiration,
            'renewalDate' => $renewalDate->format('Y-m-d')
        ]
    ]);

} catch (DomainException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respond([
        'ok' => false,
        'error' => $e->getMessage()
    ], 404);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('renewMembership: ' . $e->getMessage());

    respond([
        'ok' => false,
        'error' => 'Could not renew membership.'
    ], 500);
}

?>
