<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db_connect.php';

if ($dbError) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database unavailable.']);
    exit;
}

$patronID = $_GET['patronID'] ?? '';

if (!is_string($patronID) ||
    !ctype_digit($patronID) ||
    (int)$patronID <= 0) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Please enter a valid numeric Patron ID.'
    ]);
    exit;
}

try {
    // Find the patron.
    $stmt = $pdo->prepare(
        'SELECT PatronID, FirstName, LastName, MembershipExpiration
         FROM Patron
         WHERE PatronID = ?'
    );
    $stmt->execute([(int)$patronID]);
    $patron = $stmt->fetch();

    if (!$patron) {
        echo json_encode([
            'ok' => false,
            'error' => 'Patron not found.'
        ]);
        exit;
    }

    // Count active checkouts.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM CheckOut
         WHERE PatronID = ?
           AND ReturnDate IS NULL'
    );
    $stmt->execute([(int)$patronID]);
    $activeCheckouts = (int)$stmt->fetchColumn();

    // Calculate remaining outstanding fines.
    $stmt = $pdo->prepare(
        'SELECT COALESCE(
            SUM(GREATEST(
                COALESCE(Amount, 0) - COALESCE(AmountPaid, 0),
                0
            )), 0
         )
         FROM Fine
         WHERE PatronID = ?'
    );
    $stmt->execute([(int)$patronID]);
    $outstandingFines = (float)$stmt->fetchColumn();

    // Membership remains valid through its expiration date.
    $expired = empty($patron['MembershipExpiration']) ||
        $patron['MembershipExpiration'] < date('Y-m-d');

    $reasons = [];

    if ($expired) {
        $reasons[] = 'Membership expired';
    }

    if ($outstandingFines > 0) {
        $reasons[] = 'Outstanding fines';
    }

    if ($activeCheckouts >= 20) {
        $reasons[] = '20-item limit reached';
    }

    $eligible = count($reasons) === 0;

    echo json_encode([
        'ok' => true,
        'patron' => [
            'patronID' => (int)$patron['PatronID'],
            'name' => trim(
                $patron['FirstName'] . ' ' . $patron['LastName']
            ),
            'expiration' => $patron['MembershipExpiration'],
            'expired' => $expired,
            'activeCheckouts' => $activeCheckouts,
            'checkoutLimit' => 20,
            'outstandingFines' => $outstandingFines,
            'eligible' => $eligible,
            'eligibilityReasons' => $reasons
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Unable to retrieve patron information.'
    ]);
}
