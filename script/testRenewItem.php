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

$url = $_ENV['RENEW_ITEM_URL'] ?? getenv('RENEW_ITEM_URL');

if (!$url) {
    die("SETUP ERROR: RENEW_ITEM_URL is not configured in .env.\n");
}

if (!extension_loaded('curl')) {
    die("SETUP ERROR: PHP cURL extension is not enabled.\n");
}

$activeCheckoutCopyID = '10021';
$noActiveCheckoutCopyID = '10024';
$missingCopyID = '999999';
$notCheckedOutCopyID = '20000';

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
$missingPatronId = '999999';

$successCopyID = '10048';

$tests = [];

function addTest($name, $method, $postData, $expectedHttp, $assertion)
{
    global $tests;

    $tests[] = [
        'name' => $name,
        'method' => $method,
        'postData' => $postData,
        'expectedHttp' => $expectedHttp,
        'assertion' => $assertion
    ];
}

function assertTrue($condition, $message)
{
    if (!$condition) {
        throw new Exception($message);
    }
}

function requestJson($url, $method, $postData = [])
{
    $ch = curl_init($url);

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json'
        ]
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $postData;
    } else {
        $options[CURLOPT_CUSTOMREQUEST] = $method;
    }

    curl_setopt_array($ch, $options);

    $body = curl_exec($ch);

    if ($body === false) {
        $error = curl_error($ch);
        throw new Exception("cURL request failed: $error");
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $decoded = json_decode($body, true);

    return [
        'http' => $httpCode,
        'body' => $body,
        'json' => $decoded,
        'jsonError' => json_last_error_msg()
    ];
}

function errorAssertion($expectedError)
{
    return function ($response) use ($expectedError) {
        $json = $response['json'];

        assertTrue(
            is_array($json),
            "Response was not valid JSON: " .
            $response['jsonError'] .
            "; response body: " . $response['body']
        );

        assertTrue(
            ($json['ok'] ?? null) === false,
            'Expected ok=false.'
        );

        assertTrue(
            ($json['error'] ?? '') === $expectedError,
            "Expected error '$expectedError', received: " .
            ($json['error'] ?? '(missing error)')
        );
    };
}

function renewPost($copyID)
{
    return ['itemCopyID' => $copyID];
}

function validID($id)
{
    return is_string($id) &&
        $id !== '' &&
        ctype_digit($id) &&
        (int)$id > 0;
}

addTest(
    'GET request is rejected',
    'GET',
    [],
    405,
    errorAssertion('POST required.')
);

addTest(
    'Missing item copy ID',
    'POST',
    [],
    400,
    errorAssertion('Valid item copy ID required.')
);

addTest(
    'Empty item copy ID',
    'POST',
    renewPost(''),
    400,
    errorAssertion('Valid item copy ID required.')
);

addTest(
    'Zero item copy ID',
    'POST',
    renewPost('0'),
    400,
    errorAssertion('Valid item copy ID required.')
);

addTest(
    'Negative item copy ID',
    'POST',
    renewPost('-1'),
    400,
    errorAssertion('Valid item copy ID required.')
);

addTest(
    'Nonnumeric item copy ID',
    'POST',
    renewPost('abc'),
    400,
    errorAssertion('Valid item copy ID required.')
);

addTest(
    'Decimal item copy ID',
    'POST',
    renewPost('1.5'),
    400,
    errorAssertion('Valid item copy ID required.')
);

addTest(
    'Array item copy ID',
    'POST',
    ['itemCopyID' => ['0']],
    400,
    errorAssertion('Valid item copy ID required.')
);

addTest(
    'Item copy does not exist',
    'POST',
    renewPost($missingCopyID),
    409,
    errorAssertion('Copy is not currently checked out.')
);

addTest(
    'Item copy is not checked out',
    'POST',
    renewPost($notCheckedOutCopyID),
    409,
    errorAssertion('Copy is not currently checked out.')
);

if (validID($multipleActiveCheckoutsCopyID)) {
    addTest(
        'Multiple active checkouts exist',
        'POST',
        renewPost($multipleActiveCheckoutsCopyID),
        409,
        errorAssertion('Exactly one active checkout is required.')
    );
}

if (validID($finesCopyID)) {
    addTest(
        'Patron has outstanding fines',
        'POST',
        renewPost($finesCopyID),
        409,
        errorAssertion('Renewal denied: patron has outstanding fines.')
    );
}

if (validID($renewalLimitCopyID)) {
    addTest(
        'Two-renewal limit has been reached',
        'POST',
        renewPost($renewalLimitCopyID),
        409,
        errorAssertion('Two-renewal limit reached.')
    );
}

if (validID($noDueDateCopyID)) {
    addTest(
        'Active checkout has no due date',
        'POST',
        renewPost($noDueDateCopyID),
        409,
        errorAssertion('Checkout has no due date.')
    );
}

if (validID($overdueCopyID)) {
    addTest(
        'Checkout is overdue',
        'POST',
        renewPost($overdueCopyID),
        409,
        errorAssertion('Overdue items cannot be renewed.')
    );
}

if (validID($reservedCopyID)) {
    addTest(
        'Another patron has reserved the copy',
        'POST',
        renewPost($reservedCopyID),
        409,
        errorAssertion('Another patron has reserved this copy.')
    );
}

if (validID($noLoanPeriodCopyID)) {
    addTest(
        'Item type has no configured loan period',
        'POST',
        renewPost($noLoanPeriodCopyID),
        409,
        errorAssertion('No loan period configured for this item type.')
    );
}

addTest(
    'Successfully renew checked-out book copy',
    'POST',
    renewPost($successCopyID),
    200,
    function ($response) use ($successCopyID) {
        $json = $response['json'];

        assertTrue(
            is_array($json),
            "Response was not valid JSON: " . $response['body']
        );

        assertTrue(
            ($json['ok'] ?? null) === true,
            "Expected ok=true. Server response: " . $response['body']
        );

        assertTrue(
            ($json['message'] ?? '') === 'Renewal completed successfully.',
            'Unexpected success message.'
        );

        $checkout = $json['checkout'] ?? null;

        assertTrue(
            is_array($checkout),
            'Missing checkout object.'
        );

        assertTrue(
            (string)($checkout['itemCopyID'] ?? '') === $successCopyID,
            'Returned itemCopyID does not match the test copy.'
        );

        assertTrue(
            isset($checkout['checkoutID']) &&
            (int)$checkout['checkoutID'] > 0,
            'Missing or invalid checkoutID.'
        );

        assertTrue(
            !empty($checkout['patronName']),
            'Missing patronName.'
        );

        assertTrue(
            !empty($checkout['previousDueDate']) &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkout['previousDueDate']) === 1,
            'Invalid or missing previousDueDate.'
        );

        assertTrue(
            !empty($checkout['dueDate']) &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkout['dueDate']) === 1,
            'Invalid or missing dueDate.'
        );

        assertTrue(
            strtotime($checkout['dueDate']) >
            strtotime($checkout['previousDueDate']),
            'New due date is not later than previous due date.'
        );

        assertTrue(
            isset($checkout['renewalCount']) &&
            (int)$checkout['renewalCount'] >= 1,
            'Invalid renewalCount.'
        );

        assertTrue(
            ($checkout['status'] ?? '') === 'CheckedOut',
            "Expected status 'CheckedOut'."
        );
    }
);

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    try {
        $response = requestJson(
            $url,
            $test['method'],
            $test['postData']
        );

        if ($response['http'] !== $test['expectedHttp']) {
            throw new Exception(
                "Expected HTTP " . $test['expectedHttp'] .
                ", received " . $response['http'] .
                ".\nServer response: " . $response['body']
            );
        }

        ($test['assertion'])($response);

        echo "PASS: " . $test['name'] . "\n";
        $passed++;

    } catch (Throwable $e) {
        echo "FAIL: " . $test['name'] .
            " — " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\nResults: $passed passed, $failed failed, " .
    count($tests) . " executed.\n";

if ($failed > 0) {
    exit(1);
}

exit(0);

?>

