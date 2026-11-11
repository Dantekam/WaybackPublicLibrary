<?php
/**
 * Integration tests for reserveItem.php.
 *
 * Run:
 *   .\php\php.exe .\script\testReserveItem.php
 *
 * Requires PHP's cURL extension and the same database connection used by
 * reserveItem.php. Configure the test fixtures in .env before running.
 */

require_once __DIR__ . '/../loadEnv.php';
loadEnv(__DIR__ . '/../.env');

// Reuse RECORD_CHECKOUT_URL to derive reserveItem.php when possible.
$url = $_ENV['RESERVE_ITEM_URL'] ??
    (isset($_ENV['RECORD_CHECKOUT_URL'])
        ? str_replace('recordCheckout.php', 'reserveItem.php', $_ENV['RECORD_CHECKOUT_URL'])
        : '');

if ($url === '') {
    throw new RuntimeException(
        'Set RESERVE_ITEM_URL or RECORD_CHECKOUT_URL in .env.'
    );
}

$eligiblePatronId = (string)($_ENV['RESERVE_ITEM_ELIGIBLE_PATRON_ID'] ??
    $_ENV['RECORD_CHECKOUT_ELIGIBLE_PATRON_ID'] ??
    $_ENV['FIND_PATRON_ELIGIBLE_ID'] ?? '');
$expiredPatronId = (string)($_ENV['RECORD_CHECKOUT_EXPIRED_PATRON_ID'] ??
    $_ENV['FIND_PATRON_EXPIRED_ID'] ?? '');
$missingPatronId = (string)($_ENV['RECORD_CHECKOUT_MISSING_PATRON_ID'] ??
    $_ENV['FIND_PATRON_MISSING_ID'] ?? '999999');
$missingCopyId = (string)($_ENV['FIND_ITEM_MISSING_ID'] ?? '999999');

$availableCopyId = (string)($_ENV['RESERVE_ITEM_AVAILABLE_COPY_ID'] ?? '');
$pendingCopyId = (string)($_ENV['RESERVE_ITEM_PENDING_COPY_ID'] ?? '');
$retiredCopyId = (string)($_ENV['RESERVE_ITEM_RETIRED_COPY_ID'] ?? '');
$maintenanceCopyId = (string)($_ENV['RESERVE_ITEM_MAINTENANCE_COPY_ID'] ?? '');
$activeReservationCopyId = (string)($_ENV['RESERVE_ITEM_ACTIVE_RESERVATION_COPY_ID'] ?? '');
$missingMembershipPatronId = (string)($_ENV['RESERVE_ITEM_NO_MEMBERSHIP_DATE_PATRON_ID'] ?? '');

$tests = [];
$passed = 0;
$failed = 0;
$itemCopySnapshots = [];
$createdReservationIds = [];

function addTest(
    array &$tests,
    string $name,
    string $url,
    string $method,
    array $postData,
    int $expectedHttp,
    callable $assertion,
    ?string $snapshotCopyId = null
): void {
    $tests[] = [
        'name' => $name,
        'url' => $url,
        'method' => $method,
        'postData' => $postData,
        'expectedHttp' => $expectedHttp,
        'assertion' => $assertion,
        'snapshotCopyId' => $snapshotCopyId
    ];
}

function requestJson(string $url, string $method, array $postData): array {
    $ch = curl_init($url);

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($postData);
    } else {
        $options[CURLOPT_CUSTOMREQUEST] = $method;
    }

    curl_setopt_array($ch, $options);

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($body === false) {
        throw new RuntimeException("HTTP request failed: $curlError");
    }

    return [
        'http' => $httpCode,
        'body' => $body,
        'json' => json_decode($body, true),
        'jsonError' => json_last_error_msg()
    ];
}

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function validId(string $id): bool {
    return $id !== '' && ctype_digit($id) && (int)$id > 0;
}

function reservePost(string $patronId, string $copyId): array {
    return [
        'patronID' => $patronId,
        'itemCopyID' => $copyId
    ];
}

function assertReserveError(array $response, string $expectedError): void {
    assertTrue(($response['json']['ok'] ?? null) === false, 'Expected ok=false.');
    assertTrue(
        ($response['json']['error'] ?? '') === $expectedError,
        "Expected error '$expectedError'; received '" .
        ($response['json']['error'] ?? '') . "'."
    );
}

function snapshotItemCopy(PDO $pdo, string $copyId, array &$itemCopySnapshots): bool {
    if (!validId($copyId)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT ItemCopyID, Status
         FROM Item_Copy
         WHERE ItemCopyID = ?
         FOR UPDATE'
    );
    $stmt->execute([(int)$copyId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return false;
    }

    $itemCopySnapshots[(int)$row['ItemCopyID']] = $row['Status'];
    return true;
}

function cleanupReservations(
    PDO $pdo,
    array $createdReservationIds,
    array $itemCopySnapshots
): void {
    if (!$createdReservationIds && !$itemCopySnapshots) {
        echo "CLEANUP: No reservation records needed cleanup.\n";
        return;
    }

    $pdo->beginTransaction();

    try {
        if ($createdReservationIds) {
            $placeholders = implode(',', array_fill(0, count($createdReservationIds), '?'));
            $stmt = $pdo->prepare(
                "DELETE FROM Reservation
                 WHERE ReservationID IN ($placeholders)"
            );
            $stmt->execute(array_values($createdReservationIds));
        }

        if ($itemCopySnapshots) {
            $stmt = $pdo->prepare(
                'UPDATE Item_Copy
                 SET Status = ?
                 WHERE ItemCopyID = ?'
            );

            foreach ($itemCopySnapshots as $copyId => $status) {
                $stmt->execute([$status, $copyId]);
            }
        }

        $pdo->commit();

        echo "CLEANUP: Deleted " . count($createdReservationIds) .
             " test reservation(s) and restored " .
             count($itemCopySnapshots) . " item-copy status(es).\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "CLEANUP ERROR: " . $e->getMessage() . "\n";
        throw $e;
    }
}

function successReservationAssertion(
    array $response,
    string $patronId,
    string $copyId,
    string $expectedStatus
): void {
    $reservation = $response['json']['reservation'] ?? [];

    assertTrue(($response['json']['ok'] ?? null) === true, 'Expected ok=true.');
    assertTrue(
        (string)($reservation['reservationID'] ?? '') !== '' &&
        (int)$reservation['reservationID'] > 0,
        'Expected a valid reservationID.'
    );
    assertTrue(
        (string)($reservation['patronID'] ?? '') === $patronId,
        'Returned patronID does not match the requested patron.'
    );
    assertTrue(
        (string)($reservation['itemCopyID'] ?? '') === $copyId,
        'Returned itemCopyID does not match the requested copy.'
    );
    assertTrue(
        ($reservation['status'] ?? null) === $expectedStatus,
        "Expected reservation status '$expectedStatus'."
    );

    if ($expectedStatus === 'Ready') {
        assertTrue(
            ($response['json']['message'] ?? '') === 'Item reserved and ready for pickup.',
            'Expected the ready-for-pickup success message.'
        );
        assertTrue(
            !empty($reservation['readyForPickupDateTime']),
            'Expected readyForPickupDateTime for a Ready reservation.'
        );
        assertTrue(
            !empty($reservation['expirationDateTime']),
            'Expected expirationDateTime for a Ready reservation.'
        );
        assertTrue(
            strtotime($reservation['expirationDateTime']) >
            strtotime($reservation['readyForPickupDateTime']),
            'Expected expirationDateTime to be later than readyForPickupDateTime.'
        );
    } else {
        assertTrue(
            ($response['json']['message'] ?? '') ===
                'Reservation created. Pickup begins when the item is ready.',
            'Expected the pending-reservation success message.'
        );
        assertTrue(
            ($reservation['readyForPickupDateTime'] ?? null) === null,
            'Expected readyForPickupDateTime to be null for a Pending reservation.'
        );
        assertTrue(
            ($reservation['expirationDateTime'] ?? null) === null,
            'Expected expirationDateTime to be null for a Pending reservation.'
        );
    }
}

addTest($tests, 'GET request is rejected', $url, 'GET', [], 405,
    fn($r) => assertReserveError($r, 'POST required.')
);

addTest($tests, 'Missing patronID and itemCopyID', $url, 'POST', [], 400,
    fn($r) => assertReserveError($r, 'Valid patron and item copy IDs are required.')
);

addTest($tests, 'Empty patronID', $url, 'POST', reservePost('', '1'), 400,
    fn($r) => assertReserveError($r, 'Valid patron and item copy IDs are required.')
);

addTest($tests, 'Empty itemCopyID', $url, 'POST', reservePost('1', ''), 400,
    fn($r) => assertReserveError($r, 'Valid patron and item copy IDs are required.')
);

addTest($tests, 'Zero patronID', $url, 'POST', reservePost('0', '1'), 400,
    fn($r) => assertReserveError($r, 'Valid patron and item copy IDs are required.')
);

addTest($tests, 'Zero itemCopyID', $url, 'POST', reservePost('1', '0'), 400,
    fn($r) => assertReserveError($r, 'Valid patron and item copy IDs are required.')
);

addTest($tests, 'Negative IDs', $url, 'POST', reservePost('-1', '-2'), 400,
    fn($r) => assertReserveError($r, 'Valid patron and item copy IDs are required.')
);

addTest($tests, 'Nonnumeric IDs', $url, 'POST', reservePost('abc', 'xyz'), 400,
    fn($r) => assertReserveError($r, 'Valid patron and item copy IDs are required.')
);

addTest($tests, 'Decimal IDs', $url, 'POST', reservePost('1.5', '2.5'), 400,
    fn($r) => assertReserveError($r, 'Valid patron and item copy IDs are required.')
);

addTest($tests, 'Array patronID', $url, 'POST',
    ['patronID' => ['1'], 'itemCopyID' => '1'], 400,
    fn($r) => assertReserveError($r, 'Valid patron and item copy IDs are required.')
);

addTest($tests, 'Array itemCopyID', $url, 'POST',
    ['patronID' => '1', 'itemCopyID' => ['1']], 400,
    fn($r) => assertReserveError($r, 'Valid patron and item copy IDs are required.')
);

// Item-copy lookup and reservation eligibility 
if (validId($eligiblePatronId) && validId($missingCopyId)) {
    addTest($tests, 'Item copy does not exist', $url, 'POST',
        reservePost($eligiblePatronId, $missingCopyId), 409,
        fn($r) => assertReserveError($r, 'Item copy not found.')
    );
} else {
    echo "SKIP: Missing-copy test (set an eligible patron ID and FIND_ITEM_MISSING_ID)\n";
}

if (validId($eligiblePatronId) && validId($retiredCopyId)) {
    addTest($tests, 'Retired item copy cannot be reserved', $url, 'POST',
        reservePost($eligiblePatronId, $retiredCopyId), 409,
        fn($r) => assertReserveError($r, 'This item copy cannot currently be reserved.')
    );
} else {
    echo "SKIP: Retired-copy test (set RESERVE_ITEM_RETIRED_COPY_ID)\n";
}

if (validId($eligiblePatronId) && validId($maintenanceCopyId)) {
    addTest($tests, 'Maintenance item copy cannot be reserved', $url, 'POST',
        reservePost($eligiblePatronId, $maintenanceCopyId), 409,
        fn($r) => assertReserveError($r, 'This item copy cannot currently be reserved.')
    );
} else {
    echo "SKIP: Maintenance-copy test (set RESERVE_ITEM_MAINTENANCE_COPY_ID)\n";
}

// Patron lookup and membership 
if (validId($missingPatronId) && validId($availableCopyId)) {
    addTest($tests, 'Patron does not exist', $url, 'POST',
        reservePost($missingPatronId, $availableCopyId), 409,
        fn($r) => assertReserveError($r, 'Patron not found.')
    );
} else {
    echo "SKIP: Missing-patron test (set FIND_PATRON_MISSING_ID and an available copy ID)\n";
}

if (validId($expiredPatronId) && validId($availableCopyId)) {
    addTest($tests, 'Patron membership has expired', $url, 'POST',
        reservePost($expiredPatronId, $availableCopyId), 409,
        fn($r) => assertReserveError($r, 'Patron membership has expired.')
    );
} else {
    echo "SKIP: Expired-membership test (set FIND_PATRON_EXPIRED_ID and an available copy ID)\n";
}

if (validId($missingMembershipPatronId) && validId($availableCopyId)) {
    addTest($tests, 'Patron has no membership expiration date', $url, 'POST',
        reservePost($missingMembershipPatronId, $availableCopyId), 409,
        fn($r) => assertReserveError($r, 'Patron membership has expired.')
    );
} else {
    echo "SKIP: Null-membership-date test (set RESERVE_ITEM_NO_MEMBERSHIP_DATE_PATRON_ID)\n";
}

// Existing active reservation
if (validId($eligiblePatronId) && validId($activeReservationCopyId)) {
    addTest($tests, 'Item copy already has an active reservation', $url, 'POST',
        reservePost($eligiblePatronId, $activeReservationCopyId), 409,
        fn($r) => assertReserveError($r, 'This item copy already has an active reservation.')
    );
} else {
    echo "SKIP: Active-reservation test (set RESERVE_ITEM_ACTIVE_RESERVATION_COPY_ID)\n";
}

// Successful reservation
if (validId($eligiblePatronId) && validId($availableCopyId)) {
    addTest($tests, 'Successful reservation for an available copy', $url, 'POST',
        reservePost($eligiblePatronId, $availableCopyId), 200,
        fn($r) => successReservationAssertion(
            $r, $eligiblePatronId, $availableCopyId, 'Ready'
        ),
        $availableCopyId
    );
} else {
    echo "SKIP: Available-copy reservation (set RESERVE_ITEM_AVAILABLE_COPY_ID)\n";
}

// Successful unavailable copy reservation
if (validId($eligiblePatronId) && validId($pendingCopyId)) {
    addTest($tests, 'Successful reservation for a non-available copy', $url, 'POST',
        reservePost($eligiblePatronId, $pendingCopyId), 200,
        fn($r) => successReservationAssertion(
            $r, $eligiblePatronId, $pendingCopyId, 'Pending'
        ),
        $pendingCopyId
    );
} else {
    echo "SKIP: Pending-copy reservation (set RESERVE_ITEM_PENDING_COPY_ID)\n";
}

$pdo = null;

try {
    require __DIR__ . '/../db_connect.php';

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException(
            'db_connect.php did not provide a PDO connection in $pdo.'
        );
    }

    foreach ($tests as $test) {
        try {
            if ($test['snapshotCopyId'] !== null) {
                assertTrue(
                    snapshotItemCopy($pdo, $test['snapshotCopyId'], $itemCopySnapshots),
                    'Could not snapshot the configured item copy before reservation.'
                );
            }

            $response = requestJson(
                $test['url'],
                $test['method'],
                $test['postData']
            );

            $createdId = $response['json']['reservation']['reservationID'] ?? null;
            if ($createdId !== null && (int)$createdId > 0) {
                $createdReservationIds[] = (int)$createdId;
            }

            assertTrue(
                is_array($response['json']),
                'Response was not valid JSON: ' . $response['jsonError'] .
                '; response body: ' . $response['body']
            );

            assertTrue(
                $response['http'] === $test['expectedHttp'],
                "Expected HTTP {$test['expectedHttp']}, received {$response['http']}."
            );

            ($test['assertion'])($response);
            $passed++;
            echo "PASS: {$test['name']}\n";
        } catch (Throwable $e) {
            $failed++;
            echo "FAIL: {$test['name']} — {$e->getMessage()}\n";
        }
    }
} catch (Throwable $e) {
    $failed++;
    echo "SETUP ERROR: {$e->getMessage()}\n";
} finally {
    if ($pdo instanceof PDO) {
        try {
            cleanupReservations(
                $pdo,
                $createdReservationIds,
                $itemCopySnapshots
            );
        } catch (Throwable $e) {
            $failed++;
            echo "FAIL: Reservation cleanup — {$e->getMessage()}\n";
        }
    } else {
        echo "CLEANUP NOT RUN: No PDO connection was available.\n";
    }
}

echo "\nResults: {$passed} passed, {$failed} failed, " .
     count($tests) . " executed.\n";

exit($failed === 0 ? 0 : 1);
?>
