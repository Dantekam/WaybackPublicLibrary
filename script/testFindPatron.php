<?php
/**
 * Integration tests for findPatron.php.
 *
 * Run:
 *   .\php\php.exe .\script\testFindPatron.php
 *
 * Requires PHP's cURL extension
 * Note: Test values above just examples and not actual values
 */

require_once __DIR__ . '/../loadEnv.php';

loadEnv(__DIR__ . '/../.env');

$endpoint = $_ENV['FIND_PATRON_URL'];

$eligibleId = (int) $_ENV['FIND_PATRON_ELIGIBLE_ID'];
$expiredId = (int) $_ENV['FIND_PATRON_EXPIRED_ID'];
$finesId = (int) $_ENV['FIND_PATRON_FINES_ID'];
$limitId = (int) $_ENV['FIND_PATRON_LIMIT_ID'];
$missingId = (int) $_ENV['FIND_PATRON_MISSING_ID'];

$tests = [];
$passed = 0;
$failed = 0;

function addTest(array &$tests, string $name, string $endpoint, ?int $expectedHttp,
                 callable $assertion): void {
    $tests[] = [
        'name' => $name,
        'url' => $url,
        'expectedHttp' => $expectedHttp,
        'assertion' => $assertion
    ];
}

function requestJson(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

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

function patronUrl(string $endpoint, ?string $id = null, bool $asArray = false): string {
    if ($id === null) {
        return $endpoint;
    }
    if ($asArray) {
        return $endpoint . '?' . http_build_query(['patronID' => [$id]]);
    }
    return $endpoint . '?' . http_build_query(['patronID' => $id]);
}

// Input-validation: missing parameter
addTest($tests, 'Missing patronID', patronUrl($endpoint), 400,
    fn($r) => assertTrue(
        ($r['json']['ok'] ?? null) === false &&
        ($r['json']['error'] ?? '') === 'Please enter a valid numeric Patron ID.',
        'Expected the missing-ID validation error.'
    )
);

// Input-validation: empty parameter
addTest($tests, 'Empty patronID', patronUrl($endpoint, ''), 400,
    fn($r) => assertTrue(
        ($r['json']['ok'] ?? null) === false,
        'Expected ok=false for an empty patronID.'
    )
);

// Input-validation: zero is not a valid patron identifier
addTest($tests, 'Zero patronID', patronUrl($endpoint, '0'), 400,
    fn($r) => assertTrue(
        ($r['json']['ok'] ?? null) === false,
        'Expected ok=false for patronID zero.'
    )
);

// Input-validation: negative numbers are not valid identifiers
addTest($tests, 'Negative patronID', patronUrl($endpoint, '-5'), 400,
    fn($r) => assertTrue(
        ($r['json']['ok'] ?? null) === false,
        'Expected ok=false for a negative patronID.'
    )
);

// Input-validation: letters are not valid numeric identifiers
addTest($tests, 'Non-numeric patronID', patronUrl($endpoint, 'abc'), 400,
    fn($r) => assertTrue(
        ($r['json']['ok'] ?? null) === false,
        'Expected ok=false for a non-numeric patronID.'
    )
);

// Input-validation: decimal values are not accepted by ctype_digit()
addTest($tests, 'Decimal patronID', patronUrl($endpoint, '1.5'), 400,
    fn($r) => assertTrue(
        ($r['json']['ok'] ?? null) === false,
        'Expected ok=false for a decimal patronID.'
    )
);

// Input-validation: an array is not a string identifier
addTest($tests, 'Array patronID', patronUrl($endpoint, '1', true), 400,
    fn($r) => assertTrue(
        ($r['json']['ok'] ?? null) === false,
        'Expected ok=false when patronID is an array.'
    )
);

// Patron lookup: valid numeric ID with no matching patron record
addTest($tests, 'Patron not found', patronUrl($endpoint, $missingId), 200,
    fn($r) => assertTrue(
        ($r['json']['ok'] ?? null) === false &&
        ($r['json']['error'] ?? '') === 'Patron not found.',
        'Expected the patron-not-found response.'
    )
);

// Eligibility: active membership, no outstanding fines, and fewer than 20 checkouts
if ($eligibleId !== false && $eligibleId !== '') {
    addTest($tests, 'Existing eligible patron', patronUrl($endpoint, $eligibleId), 200,
        function ($r) use ($eligibleId) {
            $patron = $r['json']['patron'] ?? [];
            assertTrue(($r['json']['ok'] ?? null) === true, 'Expected ok=true.');
            assertTrue((string)($patron['patronID'] ?? '') === (string)$eligibleId,
                'Returned patronID does not match the requested ID.');
            assertTrue(isset($patron['name']) && trim($patron['name']) !== '',
                'Expected the patron name.');
            assertTrue(array_key_exists('expiration', $patron),
                'Expected the membership expiration date.');
            assertTrue(($patron['expired'] ?? null) === false,
                'Test patron must have a non-expired membership.');
            assertTrue(($patron['activeCheckouts'] ?? 20) < 20,
                'Test patron must have fewer than 20 active checkouts.');
            assertTrue(($patron['outstandingFines'] ?? -1) == 0,
                'Test patron must have no outstanding fines.');
            assertTrue(($patron['checkoutLimit'] ?? null) === 20,
                'Expected checkoutLimit to equal 20.');
            assertTrue(($patron['eligible'] ?? null) === true,
                'Expected eligible=true.');
            assertTrue(($patron['eligibilityReasons'] ?? null) === [],
                'Expected no eligibility reasons.');
        }
    );
} else {
    echo "SKIP: Existing eligible patron (set FIND_PATRON_ELIGIBLE_ID)\n";
}

// Eligibility: membership expiration date is before today's date (or is empty)
if ($expiredId !== false && $expiredId !== '') {
    addTest($tests, 'Expired membership', patronUrl($endpoint, $expiredId), 200,
        function ($r) use ($expiredId) {
            $patron = $r['json']['patron'] ?? [];
            $reasons = $patron['eligibilityReasons'] ?? [];
            assertTrue(($r['json']['ok'] ?? null) === true, 'Expected ok=true.');
            assertTrue((string)($patron['patronID'] ?? '') === (string)$expiredId,
                'Returned patronID does not match the requested ID.');
            assertTrue(($patron['expired'] ?? null) === true,
                'Test patron must have an expiration date before today or an empty expiration.');
            assertTrue(($patron['eligible'] ?? null) === false,
                'Expected eligible=false for an expired membership.');
            assertTrue(in_array('Membership expired', $reasons, true),
                'Expected Membership expired in eligibilityReasons.');
        }
    );
} else {
    echo "SKIP: Expired membership (set FIND_PATRON_EXPIRED_ID)\n";
}

// Eligibility: patron has a positive outstanding fine balance
if ($finesId !== false && $finesId !== '') {
    addTest($tests, 'Outstanding fines', patronUrl($endpoint, $finesId), 200,
        function ($r) use ($finesId) {
            $patron = $r['json']['patron'] ?? [];
            $reasons = $patron['eligibilityReasons'] ?? [];
            assertTrue(($r['json']['ok'] ?? null) === true, 'Expected ok=true.');
            assertTrue((string)($patron['patronID'] ?? '') === (string)$finesId,
                'Returned patronID does not match the requested ID.');
            assertTrue(($patron['outstandingFines'] ?? 0) > 0,
                'Test patron must have a positive outstanding fine balance.');
            assertTrue(($patron['eligible'] ?? null) === false,
                'Expected eligible=false when outstanding fines exist.');
            assertTrue(in_array('Outstanding fines', $reasons, true),
                'Expected Outstanding fines in eligibilityReasons.');
        }
    );
} else {
    echo "SKIP: Outstanding fines (set FIND_PATRON_FINES_ID)\n";
}

// Eligibility: patron has 20 or more active checkouts
if ($limitId !== false && $limitId !== '') {
    addTest($tests, '20-item checkout limit reached', patronUrl($endpoint, $limitId), 200,
        function ($r) use ($limitId) {
            $patron = $r['json']['patron'] ?? [];
            $reasons = $patron['eligibilityReasons'] ?? [];
            assertTrue(($r['json']['ok'] ?? null) === true, 'Expected ok=true.');
            assertTrue((string)($patron['patronID'] ?? '') === (string)$limitId,
                'Returned patronID does not match the requested ID.');
            assertTrue(($patron['activeCheckouts'] ?? 0) >= 20,
                'Test patron must have at least 20 active checkouts.');
            assertTrue(($patron['checkoutLimit'] ?? null) === 20,
                'Expected checkoutLimit to equal 20.');
            assertTrue(($patron['eligible'] ?? null) === false,
                'Expected eligible=false when the checkout limit is reached.');
            assertTrue(in_array('20-item limit reached', $reasons, true),
                'Expected 20-item limit reached in eligibilityReasons.');
        }
    );
} else {
    echo "SKIP: 20-item checkout limit (set FIND_PATRON_LIMIT_ID)\n";
}

// do tests
foreach ($tests as $test) {
    try {
        $response = requestJson($test['url']);
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

echo "\nResults: {$passed} passed, {$failed} failed, " . count($tests) . " executed.\n";
exit($failed === 0 ? 0 : 1);
?>
