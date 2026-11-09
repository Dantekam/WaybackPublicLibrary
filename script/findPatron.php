<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db_connect.php';

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$id = $_GET['patronID'] ?? '';

if (!is_string($id) || !ctype_digit($id) || (int)$id <= 0) {
    respond([
        'ok' => false,
        'error' => 'Please enter a valid numeric Patron ID.'
    ], 400);
}

try {
    $stmt = $pdo->prepare(
        'SELECT PatronID, FirstName, LastName,
                MembershipExpiration
         FROM Patron
         WHERE PatronID = ?'
    );
    $stmt->execute([(int)$id]);
    $patron = $stmt->fetch();

    if (!$patron) {
        respond(['ok' => false, 'error' => 'Patron not found.']);
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM CheckOut
         WHERE PatronID = ?
           AND ReturnDate IS NULL'
    );
    $stmt->execute([(int)$id]);
    $active = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COALESCE(
            SUM(GREATEST(
                COALESCE(Amount, 0) -
                COALESCE(AmountPaid, 0), 0
            )), 0)
         FROM Fine
         WHERE PatronID = ?'
    );
    $stmt->execute([(int)$id]);
    $fines = (float)$stmt->fetchColumn();

    $expiration = $patron['MembershipExpiration'];
    $expired = !$expiration || $expiration < date('Y-m-d');

    $reasons = [];

    if ($expired) {
        $reasons[] = 'Membership expired';
    }
    if ($fines > 0) {
        $reasons[] = 'Outstanding fines';
    }
    if ($active >= 20) {
        $reasons[] = '20-item limit reached';
    }

    respond([
        'ok' => true,
        'patron' => [
            'patronID' => (int)$patron['PatronID'],
            'name' => trim(
                $patron['FirstName'] . ' ' . $patron['LastName']
            ),
            'expiration' => $expiration,
            'expired' => $expired,
            'activeCheckouts' => $active,
            'checkoutLimit' => 20,
            'outstandingFines' => $fines,
            'eligible' => count($reasons) === 0,
            'eligibilityReasons' => $reasons
        ]
    ]);

} catch (Throwable $e) {
    error_log('findPatron: ' . $e->getMessage());

    respond([
        'ok' => false,
        'error' => 'Unable to retrieve patron information.'
    ], 500);
}