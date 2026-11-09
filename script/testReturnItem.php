<?php
/**
 * Integration tests for returnItem.php.
 *
 * Run:
 *   .\php\php.exe .\script\testReturnItem.php
 *
 * Requires PHP's cURL extension and the same database connection used by
 * returnItem.php. Configure the test fixtures in .env before running.
 */

require_once __DIR__ . '/../loadEnv.php';
loadEnv(__DIR__ . '/../.env');

$url = $_ENV['RETURN_ITEM_URL'] ?? '';

if ($url === '') {
    throw new RuntimeException('Set RETURN_ITEM_URL in .env.');
}

$availableCopyId = (string)($_ENV['RETURN_ITEM_AVAILABLE_ID'] ?? '');
$otherBranchCopyId = (string)($_ENV['RETURN_ITEM_OTHER_COPY_ID'] ?? '');
$unavailableCopyId = (string)($_ENV['RETURN_ITEM_UNAVAILABLE_ID'] ?? '');
$missingCopyId = (string)($_ENV['RETURN_ITEM_MISSING_ID'] ?? '999999');

$centralBranchId = (string)($_ENV['RETURN_ITEM_CENTRAL_BRANCH_ID'] ?? '');
$otherBranchId = (string)($_ENV['RETURN_ITEM_OTHER_BRANCH_ID'] ?? '');
$missingBranchId = (string)($_ENV['RETURN_ITEM_MISSING_BRANCH_ID'] ?? '999999');

$tests = [];
$passed = 0;
$failed = 0;
$checkoutSnapshots = [];

function addTest(
    array &$tests,
    string $name,
    string $url,
    array $postData,
    ?int $expectedHttp,
    callable $assertion,
    ?string $snapshotCopyId = null
): void {
    $tests[] = [
        'name' => $name,
        'url' => $url,
        'postData' => $postData,
        'expectedHttp' => $expectedHttp,
        'assertion' => $assertion,
        'snapshotCopyId' => $snapshotCopyId
    ];
}

function requestJson(string $url, array $postData): array {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);

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

function returnPost(string $copyId, string $branchId): array {
    return [
        'itemCopyID' => $copyId,
        'branchID' => $branchId
    ];
}

function assertReturnError(array $response, string $expectedError): void {
    assertTrue(($response['json']['ok'] ?? null) === false, 'Expected ok=false.');
    assertTrue(
        ($response['json']['error'] ?? '') === $expectedError,
        "Expected error '$expectedError'; received '" .
        ($response['json']['error'] ?? '') . "'."
    );
}

function snapshotCheckout(PDO $pdo, string $copyId, array &$checkoutSnapshots): bool {
    if (!validId($copyId)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT CheckoutID, BranchID, Status, DueDate, ReturnDate, PatronID
         FROM Checkout
         WHERE ItemCopyID = ?
         ORDER BY CheckoutID DESC
         LIMIT 1'
    );
    $stmt->execute([(int)$copyId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return false;
    }

    $checkoutSnapshots[(int)$row['CheckoutID']] = $row;
    return true;
}

function restoreCheckouts(PDO $pdo, array $checkoutSnapshots): void {
    if (!$checkoutSnapshots) {
        echo "CLEANUP: No checkout records needed restoration.\n";
        return;
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'UPDATE Checkout
             SET BranchID = ?,
                 Status = ?,
                 DueDate = ?,
                 ReturnDate = ?,
                 PatronID = ?
             WHERE CheckoutID = ?'
        );

        foreach ($checkoutSnapshots as $checkoutID => $row) {
            $stmt->execute([
                $row['BranchID'],
                $row['Status'],
                $row['DueDate'],
                $row['ReturnDate'],
                $row['PatronID'],
                $checkoutID
            ]);
        }

        $pdo->commit();
        echo "CLEANUP: Restored " . count($checkoutSnapshots) .
             " checkout record(s) to their original values.\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "CLEANUP ERROR: " . $e->getMessage() . "\n";
        throw $e;
    }
}

function branchReturnAssertion(array $response, string $copyId, string $expectedStatus): void {
    $checkout = $response['json']['checkout'] ?? [];

    assertTrue(($response['json']['ok'] ?? null) === true, 'Expected ok=true.');
    assertTrue((string)($checkout['itemCopyID'] ?? '') === $copyId,
        'Returned itemCopyID does not match the requested copy.');
    assertTrue(isset($checkout['checkoutID']) && (int)$checkout['checkoutID'] > 0,
        'Expected a valid checkoutID.');
    assertTrue(array_key_exists('returnDate', $checkout),
        'Expected returnDate in the response.');
    assertTrue(($checkout['status'] ?? null) === $expectedStatus,
        "Expected status '$expectedStatus'.");
    assertTrue(($checkout['dueDate'] ?? null) === null,
        'Expected dueDate to be null after return.');
}

addTest($tests, 'Missing itemCopyID and branchID', $url, [], 400,
    fn($r) => assertReturnError($r, 'Missing or invalid branch/item.')
);

addTest($tests, 'Empty itemCopyID', $url, returnPost('', '1'), 400,
    fn($r) => assertReturnError($r, 'Missing or invalid branch/item.')
);

addTest($tests, 'Empty branchID', $url, returnPost('1', ''), 400,
    fn($r) => assertReturnError($r, 'Missing or invalid branch/item.')
);

addTest($tests, 'Zero itemCopyID', $url, returnPost('0', '1'), 400,
    fn($r) => assertReturnError($r, 'Missing or invalid branch/item.')
);

addTest($tests, 'Zero branchID', $url, returnPost('1', '0'), 400,
    fn($r) => assertReturnError($r, 'Missing or invalid branch/item.')
);

addTest($tests, 'Negative IDs', $url, returnPost('-1', '-2'), 400,
    fn($r) => assertReturnError($r, 'Missing or invalid branch/item.')
);

addTest($tests, 'Nonnumeric IDs', $url, returnPost('abc', 'xyz'), 400,
    fn($r) => assertReturnError($r, 'Missing or invalid branch/item.')
);

addTest($tests, 'Decimal IDs', $url, returnPost('1.5', '2.5'), 400,
    fn($r) => assertReturnError($r, 'Missing or invalid branch/item.')
);

addTest($tests, 'Array itemCopyID', $url,
    ['itemCopyID' => ['1'], 'branchID' => '1'], 400,
    fn($r) => assertReturnError($r, 'Missing or invalid branch/item.')
);

addTest($tests, 'Array branchID', $url,
    ['itemCopyID' => '1', 'branchID' => ['1']], 400,
    fn($r) => assertReturnError($r, 'Missing or invalid branch/item.')
);

if (validId($missingCopyId) && validId($centralBranchId)) {
    addTest($tests, 'Item copy not found', $url,
        returnPost($missingCopyId, $centralBranchId), 200,
        fn($r) => assertReturnError($r, 'Item copy not found.')
    );
} else {
    echo "SKIP: Item copy not found (set RETURN_ITEM_MISSING_ID and central branch ID)\n";
}

if (validId($unavailableCopyId) && validId($centralBranchId)) {
    addTest($tests, 'Item copy is not open', $url,
        returnPost($unavailableCopyId, $centralBranchId), 200,
        fn($r) => assertReturnError($r, 'Item is not available for checkout.')
    );
} else {
    echo "SKIP: Unavailable item copy (set RETURN_ITEM_UNAVAILABLE_ID and central branch ID)\n";
}

if (validId($availableCopyId) && validId($missingBranchId)) {
    addTest($tests, 'Branch not found', $url,
        returnPost($availableCopyId, $missingBranchId), 200,
        fn($r) => assertReturnError($r, 'Branch not found.')
    );
} else {
    echo "SKIP: Branch not found (set RETURN_ITEM_AVAILABLE_ID and RETURN_ITEM_MISSING_BRANCH_ID)\n";
}

if (validId($availableCopyId) && validId($centralBranchId)) {
    addTest($tests, 'Successful return to Central Library', $url,
        returnPost($availableCopyId, $centralBranchId), 200,
        fn($r) => branchReturnAssertion($r, $availableCopyId, 'Available'),
        $availableCopyId
    );
} else {
    echo "SKIP: Central Library return (set RETURN_ITEM_AVAILABLE_ID and RETURN_ITEM_CENTRAL_BRANCH_ID)\n";
}

if (validId($otherBranchCopyId) && validId($otherBranchId) &&
    $otherBranchId !== $centralBranchId) {
    addTest($tests, 'Successful return to another branch', $url,
        returnPost($otherBranchCopyId, $otherBranchId), 200,
        fn($r) => branchReturnAssertion($r, $otherBranchCopyId, 'Open'),
        $otherBranchCopyId
    );
} else {
    echo "SKIP: Other-branch return (set RETURN_ITEM_OTHER_COPY_ID and a different RETURN_ITEM_OTHER_BRANCH_ID)\n";
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
                    snapshotCheckout($pdo, $test['snapshotCopyId'], $checkoutSnapshots),
                    'No checkout record found for the configured return-test copy.'
                );
            }

            $response = requestJson($test['url'], $test['postData']);

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
    $failed++;
    echo "SETUP ERROR: {$e->getMessage()}\n";
} finally {
    if ($pdo instanceof PDO) {
        try {
            restoreCheckouts($pdo, $checkoutSnapshots);
        } catch (Throwable $e) {
            $failed++;
            echo "FAIL: Checkout restoration — {$e->getMessage()}\n";
        }
    } else {
        echo "CLEANUP NOT RUN: No PDO connection was available.\n";
    }
}

echo "\nResults: {$passed} passed, {$failed} failed, " .
     count($tests) . " executed.\n";

exit($failed === 0 ? 0 : 1);
?>
