<?php
/**
 * Integration tests for findItem.php.
 *
 * Run:
 *   $env:FIND_ITEM_URL="http://project3.cis.uncw.edu/WaybackPublicLibrary/findItem.php" \
 *   $env:FIND_ITEM_AVAILABLE_ID=1 
 *   $env:FIND_ITEM_UNAVAILABLE_ID=2 
 *   $env:FIND_ITEM_MISSING_ID=999999 
 *   .\php\php.exe .\script\testFindItem.php
 * Note: Test values above just examples and not actual values
 */
// make sure to change values
$endpoint = getenv('FIND_ITEM_URL') ?: 'http://localhost/library/findItem.php';
$availableId = getenv('FIND_ITEM_AVAILABLE_ID');
$unavailableId = getenv('FIND_ITEM_UNAVAILABLE_ID');
$missingId = getenv('FIND_ITEM_MISSING_ID') ?: '999999';
$dbErrorUrl = getenv('FIND_ITEM_DB_ERROR_URL');

$tests = [];
$passed = 0;
$failed = 0;

function addTest(array &$tests, string $name, string $url, ?int $expectedHttp,
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
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException("HTTP request failed: $curlError");
    }

    $json = json_decode($body, true);
    return [
        'http' => $httpCode,
        'body' => $body,
        'json' => $json,
        'jsonError' => json_last_error_msg()
    ];
}

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function itemUrl(string $endpoint, ?string $id = null, bool $asArray = false): string {
    if ($id === null) {
        return $endpoint;
    }
    if ($asArray) {
        return $endpoint . '?' . http_build_query(['itemCopyID' => [$id]]);
    }
    return $endpoint . '?' . http_build_query(['itemCopyID' => $id]);
}

// Input-validatin: missing parameter
addTest($tests, 'Missing itemCopyID', itemUrl($endpoint), 400,
    fn($r) => assertTrue(
        ($r['json']['ok'] ?? null) === false &&
        ($r['json']['error'] ?? '') === 'Please enter a valid numeric Item Copy ID.',
        'Expected the missing-ID validation error.'
    )
);

// Input-validation: empty value
addTest($tests, 'Empty itemCopyID', itemUrl($endpoint, ''), 400,
    fn($r) => assertTrue(
        ($r['json']['ok'] ?? null) === false,
        'Expected ok=false for an empty ID.'
    )
);

// Input-validation: zero
addTest($tests, 'Zero itemCopyID', itemUrl($endpoint, '0'), 400,
    fn($r) => assertTrue(
        ($r['json']['ok'] ?? null) === false,
        'Expected ok=false for ID zero.'
    )
);

// Input-validation: negative number
addTest($tests, 'Negative itemCopyID', itemUrl($endpoint, '-5'), 400,
    fn($r) => assertTrue(
        ($r['json']['ok'] ?? null) === false,
        'Expected ok=false for a negative ID.'
    )
);

// Input-validation: alphabetic value. 
addTest($tests, 'Non-numeric itemCopyID', itemUrl($endpoint, 'abc'), 400,
    fn($r) => assertTrue(
        ($r['json']['ok'] ?? null) === false,
        'Expected ok=false for a non-numeric ID.'
    )
);

// Input-validation: decimal value
addTest($tests, 'Decimal itemCopyID', itemUrl($endpoint, '1.5'), 400,
    fn($r) => assertTrue(
        ($r['json']['ok'] ?? null) === false,
        'Expected ok=false for a decimal ID.'
    )
);

// Input-validation: array instead of a string
addTest($tests, 'Array itemCopyID', itemUrl($endpoint, '1', true), 400,
    fn($r) => assertTrue(
        ($r['json']['ok'] ?? null) === false,
        'Expected ok=false when itemCopyID is an array.'
    )
);

// Database lookup: no matching copy
addTest($tests, 'Item copy not found', itemUrl($endpoint, $missingId), 200,
    fn($r) => assertTrue(
        ($r['json']['ok'] ?? null) === false &&
        ($r['json']['error'] ?? '') === 'Item copy not found.',
        'Expected the item-copy-not-found response.'
    )
);

// Successful lookup: available copy
if ($availableId !== false && $availableId !== '') {
    addTest($tests, 'Existing available item copy', itemUrl($endpoint, $availableId), 200,
        function ($r) use ($availableId) {
            $item = $r['json']['item'] ?? [];
            assertTrue(($r['json']['ok'] ?? null) === true, 'Expected ok=true.');
            assertTrue((string)($item['itemCopyID'] ?? '') === (string)$availableId,
                'Returned itemCopyID does not match the requested ID.');
            assertTrue(isset($item['title']) && $item['title'] !== '',
                'Expected the item title.');
            assertTrue(array_key_exists('itemType', $item), 'Expected itemType.');
            assertTrue(($item['status'] ?? null) === 'Available',
                'Fixture must have status exactly "Available".');
            assertTrue(($item['available'] ?? null) === true,
                'Expected available=true for an Available copy.');
        }
    );
} else {
    echo "SKIP: Existing available item copy (set FIND_ITEM_AVAILABLE_ID)\n";
}

// Successful lookup: existing but unavailable copy
if ($unavailableId !== false && $unavailableId !== '') {
    addTest($tests, 'Existing unavailable item copy', itemUrl($endpoint, $unavailableId), 200,
        function ($r) use ($unavailableId) {
            $item = $r['json']['item'] ?? [];
            assertTrue(($r['json']['ok'] ?? null) === true, 'Expected ok=true.');
            assertTrue((string)($item['itemCopyID'] ?? '') === (string)$unavailableId,
                'Returned itemCopyID does not match the requested ID.');
            assertTrue(isset($item['title']) && $item['title'] !== '',
                'Expected the item title.');
            assertTrue(array_key_exists('itemType', $item), 'Expected itemType.');
            assertTrue(($item['status'] ?? null) !== 'Available',
                'Fixture must have a status other than "Available".');
            assertTrue(($item['available'] ?? null) === false,
                'Expected available=false for an unavailable copy.');
        }
    );
} else {
    echo "SKIP: Existing unavailable item copy (set FIND_ITEM_UNAVAILABLE_ID)\n";
}

foreach ($tests as $test) {
    try {
        $response = requestJson($test['url']);

        assertTrue(
            $response['json'] !== null && is_array($response['json']),
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