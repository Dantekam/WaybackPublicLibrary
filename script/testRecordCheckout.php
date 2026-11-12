
<?php
/**
 * Integration tests for recordCheckout.php.
 *
 * Run:
 *   .\php\php.exe .\script\testRecordCheckout.php
 * 
 * CLEANUP PROCESS:
 * Execute the folliwng sql commands on the database
 * DELETE FROM CheckOut
 * WHERE ItemCopyID IN (10021, 10022, 10023);
 * UPDATE Item_Copy
 * SET Status = 'Available'
 * WHERE ItemCopyID IN (10021, 10022, 10023);
 */

require_once __DIR__ . '/../loadEnv.php';
loadEnv(__DIR__ . '/../.env');

$url = $_ENV['RECORD_CHECKOUT_URL'] ?? '';
if ($url === '') {
    throw new RuntimeException('Set RECORD_CHECKOUT_URL in .env.');
}

$eligibleId = '41';
$expiredId = '42';
$finesId = '43';
$limitId = '44';

$availableBookId = '10021';
$availableMovieId = '10022';
$availablePeriodicalId = '10023';
$unavailableCopyId = '10024';
$unresolvedCopyId = '10025';
$unknownTypeCopyId = '10047';
$missingCopyId = '999999';
$missingPatronId = '999999';

$tests = [];
$passed = 0;
$failed = 0;

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

addTest($tests, 'Missing patronID and itemCopyID', $url, [], 400,
    fn($r) => assertCheckoutError(
        $r,
        'Valid patron and item copy IDs are required.'
    )
);

addTest($tests, 'Empty IDs', $url, checkoutPost('', ''), 400,
    fn($r) => assertCheckoutError(
        $r,
        'Valid patron and item copy IDs are required.'
    )
);

addTest($tests, 'Zero patronID', $url, checkoutPost('0', '1'), 400,
    fn($r) => assertCheckoutError(
        $r,
        'Valid patron and item copy IDs are required.'
    )
);

addTest($tests, 'Zero itemCopyID', $url, checkoutPost('1', '0'), 400,
    fn($r) => assertCheckoutError(
        $r,
        'Valid patron and item copy IDs are required.'
    )
);

addTest($tests, 'Negative IDs', $url, checkoutPost('-1', '-2'), 400,
    fn($r) => assertCheckoutError(
        $r,
        'Valid patron and item copy IDs are required.'
    )
);

addTest($tests, 'Nonnumeric IDs', $url, checkoutPost('abc', 'xyz'), 400,
    fn($r) => assertCheckoutError(
        $r,
        'Valid patron and item copy IDs are required.'
    )
);

addTest($tests, 'Decimal IDs', $url, checkoutPost('1.5', '2.5'), 400,
    fn($r) => assertCheckoutError(
        $r,
        'Valid patron and item copy IDs are required.'
    )
);

addTest($tests, 'Array patronID', $url, ['patronID' => ['1'], 'itemCopyID' => '1'], 400,
    fn($r) => assertCheckoutError(
        $r,
        'Valid patron and item copy IDs are required.'
    )
);

addTest($tests, 'Array itemCopyID', $url, ['patronID' => '1', 'itemCopyID' => ['1']], 400,
    fn($r) => assertCheckoutError(
        $r,
        'Valid patron and item copy IDs are required.'
    )
);

if (validId($missingPatronId) && validId($availableBookId)) {
    addTest($tests, 'Patron not found', $url,
        checkoutPost($missingPatronId, $availableBookId), 409,
        fn($r) => assertCheckoutError($r, 'Patron not found.')
    );
} else {
    echo "SKIP: Patron not found (set valid missing-patron and available-book IDs)\n";
}

if (validId($expiredId) && validId($availableBookId)) {
    addTest($tests, 'Expired membership', $url,
        checkoutPost($expiredId, $availableBookId), 409,
        fn($r) => assertCheckoutError($r, 'Patron membership has expired.')
    );
} else {
    echo "SKIP: Expired membership (set RECORD_CHECKOUT_EXPIRED_PATRON_ID and available book ID)\n";
}

if (validId($finesId) && validId($availableBookId)) {
    addTest($tests, 'Outstanding fines', $url,
        checkoutPost($finesId, $availableBookId), 409,
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
        checkoutPost($limitId, $availableBookId), 409,
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
        checkoutPost($eligibleId, $missingCopyId), 409,
        fn($r) => assertCheckoutError($r, 'Item copy not found.')
    );
} else {
    echo "SKIP: Item copy not found (set eligible patron and missing-copy IDs)\n";
}

if (validId($eligibleId) && validId($unavailableCopyId)) {
    addTest($tests, 'Unavailable item copy', $url,
        checkoutPost($eligibleId, $unavailableCopyId), 409,
        fn($r) => assertCheckoutError($r, 'Item is not available. Reserved items must be checked out through Reservation Management.')
    );
} else {
    echo "SKIP: Unavailable copy (set RECORD_CHECKOUT_UNAVAILABLE_COPY_ID)\n";
}

if (validId($eligibleId) && validId($unresolvedCopyId)) {
    addTest($tests, 'Copy has unresolved checkout record', $url,
        checkoutPost($eligibleId, $unresolvedCopyId), 409,
        fn($r) => assertCheckoutError(
            $r,
            'Item is not available. Reserved items must be checked out through Reservation Management.'
        )
    );
} else {
    echo "SKIP: Unresolved checkout (set RECORD_CHECKOUT_UNRESOLVED_COPY_ID)\n";
}

if (validId($eligibleId) && validId($unknownTypeCopyId)) {
    addTest($tests, 'Unknown item type', $url,
        checkoutPost($eligibleId, $unknownTypeCopyId), 409,
        fn($r) => assertCheckoutError(
            $r,
            'No loan period is configured for this item type.'
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

            $checkoutDate = $checkout['checkoutDate'] ?? '';
            assertTrue(
                preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkoutDate) === 1,
                "Invalid checkout date returned: '$checkoutDate'."
            );

            $expectedDueDate = (new DateTimeImmutable($checkoutDate))
                ->modify("+$expectedDays days")
                ->format('Y-m-d');
            assertTrue(($checkout['dueDate'] ?? '') === $expectedDueDate,
                "Expected due date $expectedDueDate.");
        }
    );
}

foreach ($tests as $test) {
    try {
        $response = requestJson(
            $test['url'],
            $test['method'],
            $test['postData']
        );

        assertTrue(
            is_array($response['json']),
            'Response was not valid JSON: ' . $response['jsonError'] .
            '; response body: ' . $response['body']
        );

        if ($test['expectedHttp'] !== null) {
            assertTrue(
                $response['http'] === $test['expectedHttp'],
                "Expected HTTP {$test['expectedHttp']}, received {$response['http']}." .
                "\nServer response: " . $response['body']
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

echo "\nResults: {$passed} passed, {$failed} failed, " .
     count($tests) . " executed.\n";

exit($failed === 0 ? 0 : 1);
?>
