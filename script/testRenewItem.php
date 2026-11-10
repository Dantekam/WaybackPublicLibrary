<?php
/**
 * Integration tests for renewItem.php.
 *
 * Run:
 *   .\php\php.exe .\script\testRenewItem.php
 *
 * Requires PHP's cURL extension and the same database connection used by
 * renewItem.php. Configure the test fixtures in .env before running.
 */

require_once __DIR__ . '/../loadEnv.php';
loadEnv(__DIR__ . '/../.env');

$url = $_ENV['RENEW_ITEM_URL'] ??
    (isset($_ENV['RECORD_CHECKOUT_URL'])
        ? str_replace('recordCheckout.php', 'renewItem.php', $_ENV['RECORD_CHECKOUT_URL'])
        : '');

if ($url === '') {
    throw new RuntimeException(
        'Set RENEW_ITEM_URL or RECORD_CHECKOUT_URL in .env.'
    );
}

$missingCopyId = (string)($_ENV['RENEW_ITEM_MISSING_ID'] ??
    $_ENV['FIND_ITEM_MISSING_ID'] ?? '999999');

$notCheckedOutCopyId = (string)($_ENV['RENEW_ITEM_NOT_CHECKED_OUT_ID'] ?? '');
$noActiveCheckoutCopyId = (string)($_ENV['RENEW_ITEM_NO_ACTIVE_CHECKOUT_ID'] ?? '');
$multipleActiveCheckoutsCopyId = (string)($_ENV['RENEW_ITEM_MULTIPLE_ACTIVE_CHECKOUTS_ID'] ?? '');
$finesCopyId = (string)($_ENV['RENEW_ITEM_FINES_ID'] ?? '');
$renewalLimitCopyId = (string)($_ENV['RENEW_ITEM_LIMIT_ID'] ?? '');
$noDueDateCopyId = (string)($_ENV['RENEW_ITEM_NO_DUE_DATE_ID'] ?? '');
$overdueCopyId = (string)($_ENV['RENEW_ITEM_OVERDUE_ID'] ?? '');
$reservedCopyId = (string)($_ENV['RENEW_ITEM_RESERVED_ID'] ?? '');
$noLoanPeriodCopyId = (string)($_ENV['RENEW_ITEM_NO_LOAN_PERIOD_ID'] ?? '');
$successCopyId = (string)($_ENV['RENEW_ITEM_SUCCESS_ID'] ?? '');

$tests = [];
$passed = 0;
$failed = 0;
$checkoutSnapshots = [];

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

function renewPost(string $copyId): array {
    return ['itemCopyID' => $copyId];
}

function assertRenewError(array $response, string $expectedError): void {
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
        'SELECT CheckOutID, DueDate, RenewalCount
         FROM CheckOut
         WHERE ItemCopyID = ?
           AND ReturnDate IS NULL
         FOR UPDATE'
    );
    $stmt->execute([(int)$copyId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) !== 1) {
        return false;
    }

    $row = $rows[0];
    $checkoutSnapshots[(int)$row['CheckOutID']] = $row;
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
            'UPDATE CheckOut
             SET DueDate = ?,
                 RenewalCount = ?
             WHERE CheckOutID = ?'
        );

        foreach ($checkoutSnapshots as $checkoutID => $row) {
            $stmt->execute([
                $row['DueDate'],
                $row['RenewalCount'],
                $checkoutID
            ]);
        }

        $pdo->commit();
        echo "CLEANUP: Restored " . count($checkoutSnapshots) .
             " checkout record(s) to their original due dates and renewal counts.\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "CLEANUP ERROR: " . $e->getMessage() . "\n";
        throw $e;
    }
}

function successRenewalAssertion(array $response, string $copyId): void {
    $checkout = $response['json']['checkout'] ?? [];

    assertTrue(($response['json']['ok'] ?? null) === true, 'Expected ok=true.');
    assertTrue(
        ($response['json']['message'] ?? '') === 'Renewal completed successfully.',
        'Expected the successful renewal message.'
    );
    assertTrue(
        (string)($checkout['itemCopyID'] ?? '') === $copyId,
        'Returned itemCopyID does not match the requested copy.'
    );
    assertTrue(
        isset($checkout['checkoutID']) && (int)$checkout['checkoutID'] > 0,
        'Expected a valid checkoutID.'
    );
    assertTrue(
        !empty($checkout['patronName']),
        'Expected patronName in the renewal response.'
    );
    assertTrue(
        !empty($checkout['previousDueDate']) && !empty($checkout['dueDate']),
        'Expected previousDueDate and dueDate in the renewal response.'
    );
    assertTrue(
        strtotime($checkout['dueDate']) > strtotime($checkout['previousDueDate']),
        'Expected the new due date to be later than the previous due date.'
    );
    assertTrue(
        isset($checkout['renewalCount']) && (int)$checkout['renewalCount'] >= 1,
        'Expected renewalCount to be at least 1.'
    );
    assertTrue(
        ($checkout['status'] ?? null) === 'CheckedOut',
        'Expected status CheckedOut.'
    );
}

addTest($tests, 'GET request is rejected', $url, 'GET', [], 405,
    fn($r) => assertRenewError($r, 'POST required.')
);

// Input validation: missing, empty, zero, negative, nonnumeric, decimal, and array values
addTest($tests, 'Missing itemCopyID', $url, 'POST', [], 400,
    fn($r) => assertRenewError($r, 'Valid item copy ID required.')
);

addTest($tests, 'Empty itemCopyID', $url, 'POST', renewPost(''), 400,
    fn($r) => assertRenewError($r, 'Valid item copy ID required.')
);

addTest($tests, 'Zero itemCopyID', $url, 'POST', renewPost('0'), 400,
    fn($r) => assertRenewError($r, 'Valid item copy ID required.')
);

addTest($tests, 'Negative itemCopyID', $url, 'POST', renewPost('-1'), 400,
    fn($r) => assertRenewError($r, 'Valid item copy ID required.')
);

addTest($tests, 'Nonnumeric itemCopyID', $url, 'POST', renewPost('abc'), 400,
    fn($r) => assertRenewError($r, 'Valid item copy ID required.')
);

addTest($tests, 'Decimal itemCopyID', $url, 'POST', renewPost('1.5'), 400,
    fn($r) => assertRenewError($r, 'Valid item copy ID required.')
);

addTest($tests, 'Array itemCopyID', $url, 'POST',
    ['itemCopyID' => ['1']], 400,
    fn($r) => assertRenewError($r, 'Valid item copy ID required.')
);

if (validId($missingCopyId)) {
    addTest($tests, 'Item copy does not exist', $url, 'POST',
        renewPost($missingCopyId), 409,
        fn($r) => assertRenewError($r, 'Copy is not currently checked out.')
    );
} else {
    echo "SKIP: Missing copy test (set FIND_ITEM_MISSING_ID or RENEW_ITEM_MISSING_ID)\n";
}

if (validId($notCheckedOutCopyId)) {
    addTest($tests, 'Item copy is not checked out', $url, 'POST',
        renewPost($notCheckedOutCopyId), 409,
        fn($r) => assertRenewError($r, 'Copy is not currently checked out.')
    );
} else {
    echo "SKIP: Not-checked-out copy (set RENEW_ITEM_NOT_CHECKED_OUT_ID)\n";
}

// Active checkout count branches: zero active loans and more than one active loan
if (validId($noActiveCheckoutCopyId)) {
    addTest($tests, 'No active checkout exists', $url, 'POST',
        renewPost($noActiveCheckoutCopyId), 409,
        fn($r) => assertRenewError($r, 'Exactly one active checkout is required.')
    );
} else {
    echo "SKIP: No-active-checkout test (set RENEW_ITEM_NO_ACTIVE_CHECKOUT_ID)\n";
}

if (validId($multipleActiveCheckoutsCopyId)) {
    addTest($tests, 'Multiple active checkouts exist', $url, 'POST',
        renewPost($multipleActiveCheckoutsCopyId), 409,
        fn($r) => assertRenewError($r, 'Exactly one active checkout is required.')
    );
} else {
    echo "SKIP: Multiple-active-checkouts test (set RENEW_ITEM_MULTIPLE_ACTIVE_CHECKOUTS_ID)\n";
}

// exceptions
if (validId($finesCopyId)) {
    addTest($tests, 'Patron has outstanding fines', $url, 'POST',
        renewPost($finesCopyId), 409,
        fn($r) => assertRenewError($r, 'Renewal denied: patron has outstanding fines.')
    );
} else {
    echo "SKIP: Outstanding-fines test (set RENEW_ITEM_FINES_ID)\n";
}

if (validId($renewalLimitCopyId)) {
    addTest($tests, 'Two-renewal limit has been reached', $url, 'POST',
        renewPost($renewalLimitCopyId), 409,
        fn($r) => assertRenewError($r, 'Two-renewal limit reached.')
    );
} else {
    echo "SKIP: Renewal-limit test (set RENEW_ITEM_LIMIT_ID)\n";
}

if (validId($noDueDateCopyId)) {
    addTest($tests, 'Active checkout has no due date', $url, 'POST',
        renewPost($noDueDateCopyId), 409,
        fn($r) => assertRenewError($r, 'Checkout has no due date.')
    );
} else {
    echo "SKIP: No-due-date test (set RENEW_ITEM_NO_DUE_DATE_ID)\n";
}

if (validId($overdueCopyId)) {
    addTest($tests, 'Checkout is overdue', $url, 'POST',
        renewPost($overdueCopyId), 409,
        fn($r) => assertRenewError($r, 'Overdue items cannot be renewed.')
    );
} else {
    echo "SKIP: Overdue test (set RENEW_ITEM_OVERDUE_ID)\n";
}

if (validId($reservedCopyId)) {
    addTest($tests, 'Another patron has reserved the copy', $url, 'POST',
        renewPost($reservedCopyId), 409,
        fn($r) => assertRenewError($r, 'Another patron has reserved this copy.')
    );
} else {
    echo "SKIP: Reservation test (set RENEW_ITEM_RESERVED_ID)\n";
}

if (validId($noLoanPeriodCopyId)) {
    addTest($tests, 'Item type has no configured loan period', $url, 'POST',
        renewPost($noLoanPeriodCopyId), 409,
        fn($r) => assertRenewError($r, 'No loan period configured for this item type.')
    );
} else {
    echo "SKIP: No-loan-period test (set RENEW_ITEM_NO_LOAN_PERIOD_ID)\n";
}

// Successful renewal
if (validId($successCopyId)) {
    addTest($tests, 'Successful item renewal', $url, 'POST',
        renewPost($successCopyId), 200,
        fn($r) => successRenewalAssertion($r, $successCopyId),
        $successCopyId
    );
} else {
    echo "SKIP: Successful renewal (set RENEW_ITEM_SUCCESS_ID)\n";
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
                    'Expected exactly one active checkout to snapshot for the success test.'
                );
            }

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
