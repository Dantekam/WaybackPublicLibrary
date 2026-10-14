<?php
header('Content-Type: application/json');

// db connection
require __DIR__ . '/db_connect.php';

$patronID = $_GET['patronID'] ?? '';

// search for patron
if ($patronID === '' || !ctype_digit((string)$patronID)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid numeric Patron ID.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT PatronID, FirstName, LastName, MembershipExpiration
           FROM Patron
          WHERE PatronID = ?'
    );
    $stmt->execute([(int)$patronID]);
    $patron = $stmt->fetch();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Lookup failed.']);
    exit;
}

if (!$patron) {
    echo json_encode(['ok' => false, 'error' => 'Patron not found.']);
    exit;
}

$fullName = trim($patron['FirstName'] . ' ' . $patron['LastName']);
$expired  = strtotime($patron['MembershipExpiration']) < time();

echo json_encode([
    'ok' => true,
    'patron' => [
        'patronID'   => (int)$patron['PatronID'],
        'name'       => $fullName,
        'expiration' => $patron['MembershipExpiration'],
        'expired'    => $expired,
    ],
]);