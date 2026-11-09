<?php
/**
 * Integration tests for recordCheckout.php.
 *
 * Run:
 *   .\php\php.exe .\script\testRecordCheckout.php
 *
 */

require_once __DIR__ . '/../loadEnv.php';
loadEnv(__DIR__ . '/../.env');

$url = $_ENV['RECORD_CHECKOUT_URL'] ?? '';
if ($url === '') {
    throw new RuntimeException('Set RECORD_CHECKOUT_URL in .env.');
}

$eligibleId = (string)($_ENV['RECORD_CHECKOUT_ELIGIBLE_PATRON_ID'] ?? '');
$expiredId  = (string)($_ENV['RECORD_CHECKOUT_EXPIRED_PATRON_ID'] ?? '');
$finesId    = (string)($_ENV['RECORD_CHECKOUT_FINES_PATRON_ID'] ?? '');
$limitId    = (string)($_ENV['RECORD_CHECKOUT_LIMIT_PATRON_ID'] ?? '');

$availableBookId      = (string)($_ENV['RECORD_CHECKOUT_AVAILABLE_BOOK_ID'] ?? '');
$availableMovieId     = (string)($_ENV['RECORD_CHECKOUT_AVAILABLE_MOVIE_ID'] ?? '');
$availablePeriodicalId = (string)($_ENV['RECORD_CHECKOUT_AVAILABLE_PERIODICAL_ID'] ?? '');
$unavailableCopyId    = (string)($_ENV['RECORD_CHECKOUT_UNAVAILABLE_COPY_ID'] ?? '');
$unresolvedCopyId     = (string)($_ENV['RECORD_CHECKOUT_UNRESOLVED_COPY_ID'] ?? '');
$unknownTypeCopyId    = (string)($_ENV['RECORD_CHECKOUT_UNKNOWN_TYPE_COPY_ID'] ?? '');
$missingCopyId        = (string)($_ENV['RECORD_CHECKOUT_MISSING_COPY_ID'] ?? '999999');
$missingPatronId      = (string)($_ENV['RECORD_CHECKOUT_MISSING_PATRON_ID'] ?? '999999');

$tests = [];
$passed = 0;
$failed = 0;

$createdCheckoutIds = [];
$successfulPairs = [];

function addTest(
    array &$tests,
    string $name,
    string $url,
    array $postData,
    ?int $expectedHttp,
    callable $assertion,
    bool $trackPossibleCheckout = false,
    string $method = 'POST'
): void {
    $tests[] = [
        'name' => $name,
        'url' => $url,
        'postData' => $postData,
        'expectedHttp' => $expectedHttp,
        'assertion' => $assertion,
        'trackPossibleCheckout' => $trackPossibleCheckout,
        'method' => $method
    ];
}

function requestJson(string $url, string $method = 'POST', array $postData = []): array {
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

function checkoutPost(string $patronId, string $copyId): array {
    return ['patronID' => $patronId, 'itemCopyID' => $copyId];
}

function assertCheckoutError(array $response, string $expectedError): void {
    assertTrue(($response['json']['ok'] ?? null) === false, 'Expected ok=false.');
    assertTrue(
        ($response['json']['error'] ?? '') === $expectedError,
        "Expected error '$expectedError'; received '" .
        ($response['json']['error'] ?? '') . "'."
    );
}

function trackCreatedCheckout(
    PDO $pdo,
    string $patronId,
    string $copyId,
    array &$createdCheckoutIds,
    array &$successfulPairs
): void {
    if (!validId($patronId) || !validId($copyId)) {
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT CheckOutID
         FROM CheckOut
         WHERE PatronID = ?
           AND ItemCopyID = ?
           AND ReturnDate IS NULL'
    );
    $stmt->execute([(int)$patronId, (int)$copyId]);

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $createdCheckoutIds[(int)$id] = true;
    }

    $successfulPairs[$patronId . ':' . $copyId] = [
        'patronID' => (int)$patronId,
        'itemCopyID' => (int)$copyId
    ];
}

function cleanupCheckouts(
    PDO $pdo,
    array $createdCheckoutIds,
    array $successfulPairs
): void {
    $idsToDelete = array_keys($createdCheckoutIds);

    foreach ($successfulPairs as $pair) {
        $stmt = $pdo->prepare(
            'SELECT CheckOutID
             FROM CheckOut
             WHERE PatronID = ?
               AND ItemCopyID = ?
               AND ReturnDate IS NULL'
        );
        $stmt->execute([$pair['patronID'], $pair['itemCopyID']]);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $idsToDelete[] = (int)$id;
        }
    }

    $idsToDelete = array_values(array_unique(array_map('intval', $idsToDelete)));

    if (!$idsToDelete) {
        echo "CLEANUP: No test checkout records to remove.\n";
        return;
    }

    $pdo->beginTransaction();

    try {
        $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));

        $stmt = $pdo->prepare(
            "SELECT DISTINCT ItemCopyID
             FROM CheckOut
             WHERE CheckOutID IN ($placeholders)"
        );
        $stmt->execute($idsToDelete);
        $copyIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $stmt = $pdo->prepare(
            "DELETE FROM CheckOut WHERE CheckOutID IN ($placeholders)"
        );
        $stmt->execute($idsToDelete);

        if ($copyIds) {
            $copyPlaceholders = implode(',', array_fill(0, count($copyIds), '?'));
            $restore = $pdo->prepare(
                "UPDATE Item_Copy
                 SET Status = 'Available'
                 WHERE ItemCopyID IN ($copyPlaceholders)"
            );
            $restore->execute($copyIds);
        }

        $pdo->commit();
        echo "CLEANUP: Removed " . count($idsToDelete) .
             " test checkout record(s) and restored " . count($copyIds) .
             " item copy/copies to Available.\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "CLEANUP ERROR: " . $e->getMessage() . "\n";
        throw $e;
    }
}

addTest($tests, 'GET request is rejected', $url, [], 405,
    fn($r) => assertCheckoutError($r, 'POST request required.'),
    false,
    'GET'
);

addTest($tests, 'Missing patronID and itemCopyID', $url, [], 400,
    fn($r) => assertCheckoutError(
        $r,
        'Please provide valid patron and item copy IDs.'
    )
);

addTest($tests, 'Empty IDs', $url, checkoutPost('', ''), 400,
    fn($r) => assertCheckoutError(
        $r,
        'Please provide valid patron and item copy IDs.'
    )
);

addTest($tests, 'Zero patronID', $url, checkoutPost('0', '1'), 400,
    fn($r) => assertCheckoutError(
        $r,
        'Please provide valid patron and item copy IDs.'
    )
);

addTest($tests, 'Zero itemCopyID', $url, checkoutPost('1', '0'), 400,
    fn($r) => assertCheckoutError(
        $r,
        'Please provide valid patron and item copy IDs.'
    )
);

addTest($tests, 'Negative IDs', $url, checkoutPost('-1', '-2'), 400,
    fn($r) => assertCheckoutError(
        $r,
        'Please provide valid patron and item copy IDs.'
    )
);

addTest($tests, 'Nonnumeric IDs', $url, checkoutPost('abc', 'xyz'), 400,
    fn($r) => assertCheckoutError(
        $r,
        'Please provide valid patron and item copy IDs.'
    )
);

addTest($tests, 'Decimal IDs', $url, checkoutPost('1.5', '2.5'), 400,
    fn($r) => assertCheckoutError(
        $r,
        'Please provide valid patron and item copy IDs.'
    )
);

addTest($tests, 'Array patronID', $url, ['patronID' => ['1'], 'itemCopyID' => '1'], 400,
    fn($r) => assertCheckoutError(
        $r,
        'Please provide valid patron and item copy IDs.'
    )
);

addTest($tests, 'Array itemCopyID', $url, ['patronID' => '1', 'itemCopyID' => ['1']], 400,
    fn($r) => assertCheckoutError(
        $r,
        'Please provide valid patron and item copy IDs.'
    )
);

if (validId($missingPatronId) && validId($availableBookId)) {
    addTest($tests, 'Patron not found', $url,
        checkoutPost($missingPatronId, $availableBookId), 200,
        fn($r) => assertCheckoutError($r, 'Patron not found.')
    );
} else {
    echo "SKIP: Patron not found (set valid missing-patron and available-book IDs)\n";
}

if (validId($expiredId) && validId($availableBookId)) {
    addTest($tests, 'Expired membership', $url,
        checkoutPost($expiredId, $availableBookId), 200,
        fn($r) => assertCheckoutError($r, 'Patron membership has expired.')
    );
} else {
    echo "SKIP: Expired membership (set RECORD_CHECKOUT_EXPIRED_PATRON_ID and available book ID)\n";
}

if (validId($finesId) && validId($availableBookId)) {
    addTest($tests, 'Outstanding fines', $url,
        checkoutPost($finesId, $availableBookId), 200,
        fn($r) => assertCheckoutError(
            $r,
            'Checkout denied: patron has outstanding fines.'
        )
    );
} else {
    echo "SKIP: Outstanding fines (set RECORD_CHECKOUT_FINES_PATRON_ID and available book ID)\n";
}

if (validId($limitId) && validId($availableBookId)) {
    addTest($tests, '20-item checkout limit', $url,
        checkoutPost($limitId, $availableBookId), 200,
        fn($r) => assertCheckoutError(
            $r,
            'Checkout denied: patron has reached the 20-item limit.'
        )
    );
} else {
    echo "SKIP: 20-item limit (set RECORD_CHECKOUT_LIMIT_PATRON_ID and available book ID)\n";
}

if (validId($eligibleId) && validId($missingCopyId)) {
    addTest($tests, 'Item copy not found', $url,
        checkoutPost($eligibleId, $missingCopyId), 200,
        fn($r) => assertCheckoutError($r, 'Item copy not found.')
    );
} else {
    echo "SKIP: Item copy not found (set eligible patron and missing-copy IDs)\n";
}

if (validId($eligibleId) && validId($unavailableCopyId)) {
    addTest($tests, 'Unavailable item copy', $url,
        checkoutPost($eligibleId, $unavailableCopyId), 200,
        fn($r) => assertCheckoutError($r, 'Item is not available for checkout.')
    );
} else {
    echo "SKIP: Unavailable copy (set RECORD_CHECKOUT_UNAVAILABLE_COPY_ID)\n";
}

if (validId($eligibleId) && validId($unresolvedCopyId)) {
    addTest($tests, 'Copy has unresolved checkout record', $url,
        checkoutPost($eligibleId, $unresolvedCopyId), 200,
        fn($r) => assertCheckoutError(
            $r,
            'Checkout denied: this copy has an unresolved checkout record.'
        )
    );
} else {
    echo "SKIP: Unresolved checkout (set RECORD_CHECKOUT_UNRESOLVED_COPY_ID)\n";
}

if (validId($eligibleId) && validId($unknownTypeCopyId)) {
    addTest($tests, 'Unknown item type', $url,
        checkoutPost($eligibleId, $unknownTypeCopyId), 200,
        fn($r) => assertCheckoutError(
            $r,
            'Loan duration has not been configured for this item type.'
        )
    );
} else {
    echo "SKIP: Unknown item type (set RECORD_CHECKOUT_UNKNOWN_TYPE_COPY_ID)\n";
}

$successCases = [
    [
        'name' => 'Successful book checkout (14-day loan)',
        'copyId' => $availableBookId,
        'expectedDays' => 14
    ],
    [
        'name' => 'Successful movie checkout (7-day loan)',
        'copyId' => $availableMovieId,
        'expectedDays' => 7
    ],
    [
        'name' => 'Successful periodical checkout (1-day loan)',
        'copyId' => $availablePeriodicalId,
        'expectedDays' => 1
    ]
];

foreach ($successCases as $case) {
    if (!validId($eligibleId) || !validId($case['copyId'])) {
        echo "SKIP: {$case['name']} (set eligible patron and the matching copy ID)\n";
        continue;
    }

    $patronId = $eligibleId;
    $copyId = $case['copyId'];
    $expectedDays = $case['expectedDays'];

    addTest($tests, $case['name'], $url, checkoutPost($patronId, $copyId), 200,
        function ($r) use ($patronId, $copyId, $expectedDays) {
            $checkout = $r['json']['checkout'] ?? [];

            assertTrue(($r['json']['ok'] ?? null) === true, 'Expected ok=true.');
            assertTrue(isset($checkout['checkoutID']) &&
                (int)$checkout['checkoutID'] > 0,
                'Expected a positive checkoutID.');
            assertTrue((string)($checkout['itemCopyID'] ?? '') === $copyId,
                'Returned itemCopyID does not match the requested copy.');
            assertTrue(isset($checkout['patron']) &&
                trim((string)$checkout['patron']) !== '',
                'Expected the patron name.');
            assertTrue(isset($checkout['itemType']) &&
                trim((string)$checkout['itemType']) !== '',
                'Expected the item type.');

            $today = new DateTimeImmutable('today');
            $expectedDueDate = $today->modify("+$expectedDays days")->format('Y-m-d');

            assertTrue(($checkout['checkoutDate'] ?? '') === $today->format('Y-m-d'),
                'Checkout date does not match today.');
            assertTrue(($checkout['dueDate'] ?? '') === $expectedDueDate,
                "Expected due date $expectedDueDate.");
        },
        true
    );
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
            if ($test['trackPossibleCheckout']) {
                $patronForCheck = (int)$test['postData']['patronID'];
                $copyForCheck = (int)$test['postData']['itemCopyID'];

                $check = $pdo->prepare(
                    'SELECT c.Status,
                            (SELECT COUNT(*)
                             FROM CheckOut co
                             WHERE co.ItemCopyID = c.ItemCopyID
                               AND co.ReturnDate IS NULL) AS ActiveCount
                     FROM Item_Copy c
                     WHERE c.ItemCopyID = ?'
                );
                $check->execute([$copyForCheck]);
                $copyState = $check->fetch(PDO::FETCH_ASSOC);

                assertTrue($copyState !== false,
                    'Configured success-test item copy does not exist.');
                assertTrue($copyState['Status'] === 'Available',
                    'Configured success-test copy is not Available; refusing to modify it.');
                assertTrue((int)$copyState['ActiveCount'] === 0,
                    'Configured success-test copy already has an active checkout; refusing to modify it.');
            }

            $response = requestJson($test['url'], $test['method'], $test['postData']);

            if ($test['trackPossibleCheckout']) {
                trackCreatedCheckout(
                    $pdo,
                    (string)$test['postData']['patronID'],
                    (string)$test['postData']['itemCopyID'],
                    $createdCheckoutIds,
                    $successfulPairs
                );
            }

            assertTrue(
                is_array($response['json']),
                'Response was not valid JSON: ' . $response['jsonError'] .
                '; response body: ' . $response['body']
            );

            if ($test['expectedHttp'] !== null) {
                assertTrue(
                    $response['http'] === $test['expectedHttp'],
                    "Expected HTTP {$test['expectedHttp']}, received {$response['http']}."
                );
            }

            ($test['assertion'])($response);
            $passed++;
            echo "PASS: {$test['name']}\n";
        } catch (Throwable $e) {
            $failed++;
            echo "FAIL: {$test['name']} — {$e->getMessage()}\n";
        }
    }
} catch (Throwable $e) {
    echo "SETUP ERROR: {$e->getMessage()}\n";
    $failed++;
} finally {
    if ($pdo instanceof PDO) {
        try {
            cleanupCheckouts($pdo, $createdCheckoutIds, $successfulPairs);
        } catch (Throwable $e) {
            $failed++;
            echo "FAIL: Checkout cleanup — {$e->getMessage()}\n";
        }
    } else {
        echo "CLEANUP NOT RUN: No PDO connection was available.\n";
    }
}

echo "\nResults: {$passed} passed, {$failed} failed, " .
     count($tests) . " executed.\n";

exit($failed === 0 ? 0 : 1);
?>
