<?php
/**
 * Integration tests for returnItem.php.
 *
 * Run:
 *   .\php\php.exe .\script\testReturnItem.php
 *
 * Requires PHP's cURL extension and the same database connection used by
 * returnItem.php. Configure the test fixtures in .env before running.
 * 
 * CLEANUP INSTRUCTIONS:
 * Run the following sql transaction in the database
 * START TRANSACTION;
 *
 *UPDATE CheckOut
 * SET
 *     ReturnDate = NULL,
 *     ReturnBranchID = NULL
 * WHERE ItemCopyID = 10021
 *   AND ReturnDate IS NOT NULL;
 * 
 * UPDATE Item_Copy
 * SET
 *     Status = 'CheckedOut',
 *     CurrentBranchID = HomeBranchID,
 *     RoutingStatus = "NotApplicable",
 *     DestinationBranchID = NULL
 * WHERE ItemCopyID = 10021;
 *
 * COMMIT;
 */

require_once __DIR__ . '/../loadEnv.php';
loadEnv(__DIR__ . '/../.env');

$url = $_ENV['RETURN_ITEM_URL'] ?? getenv('RETURN_ITEM_URL');

if (!$url) {
    die("SETUP ERROR: RETURN_ITEM_URL is not configured in .env.\n");
}

if (!extension_loaded('curl')) {
    die("SETUP ERROR: PHP cURL extension is not enabled.\n");
}

$activeCheckoutCopyID = '10021';
$noActiveCheckoutCopyID = '10024';
$missingCopyID = '999999';

$returnBranchID = '1007';
$missingBranchID = '999999';

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
    [
        'returnBranchID' => $returnBranchID
    ],
    400,
    errorAssertion('Valid copy and return branch IDs are required.')
);

addTest(
    'Non-numeric item copy ID',
    'POST',
    [
        'itemCopyID' => 'abc',
        'returnBranchID' => $returnBranchID
    ],
    400,
    errorAssertion('Valid copy and return branch IDs are required.')
);

addTest(
    'Missing return branch ID',
    'POST',
    [
        'itemCopyID' => $activeCheckoutCopyID
    ],
    400,
    errorAssertion('Valid copy and return branch IDs are required.')
);

addTest(
    'Non-numeric return branch ID',
    'POST',
    [
        'itemCopyID' => $activeCheckoutCopyID,
        'returnBranchID' => 'abc'
    ],
    400,
    errorAssertion('Valid copy and return branch IDs are required.')
);

addTest(
    'Item copy does not exist',
    'POST',
    [
        'itemCopyID' => $missingCopyID,
        'returnBranchID' => $returnBranchID
    ],
    409,
    errorAssertion('Item copy not found.')
);

addTest(
    'Return branch does not exist',
    'POST',
    [
        'itemCopyID' => $activeCheckoutCopyID,
        'returnBranchID' => $missingBranchID
    ],
    409,
    errorAssertion('Return branch not found.')
);

addTest(
    'Copy has no active checkout',
    'POST',
    [
        'itemCopyID' => $noActiveCheckoutCopyID,
        'returnBranchID' => $returnBranchID
    ],
    409,
    errorAssertion('Exactly one active checkout is required for check-in.')
);

addTest(
    'Successfully return checked-out book copy',
    'POST',
    [
        'itemCopyID' => $activeCheckoutCopyID,
        'returnBranchID' => $returnBranchID
    ],
    200,
    
    function ($response) use ($activeCheckoutCopyID, $returnBranchID) {
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
            ($json['message'] ?? '') === 'Item checked in successfully.',
            'Unexpected success message.'
        );

        $checkout = $json['checkout'] ?? null;

        assertTrue(
            is_array($checkout),
            'Missing checkout object.'
        );

        assertTrue(
            !empty($checkout['checkoutID']),
            'Missing checkoutID.'
        );

        assertTrue(
            (string)($checkout['itemCopyID'] ?? '') === $activeCheckoutCopyID,
            'Returned itemCopyID does not match the test copy.'
        );

        assertTrue(
            !empty($checkout['patronID']),
            'Missing patronID.'
        );

        assertTrue(
            (string)($checkout['returnBranchID'] ?? '') === $returnBranchID,
            'Returned returnBranchID does not match BranchID 1007.'
        );

        assertTrue(
            !empty($checkout['returnBranchName']),
            'Missing returnBranchName.'
        );

        assertTrue(
            !empty($checkout['homeBranchID']),
            'Missing homeBranchID.'
        );

        assertTrue(
            !empty($checkout['destinationBranchName']),
            'Missing destinationBranchName.'
        );

        assertTrue(
            isset($checkout['dueDate']) &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkout['dueDate']) === 1,
            'Invalid or missing dueDate.'
        );

        assertTrue(
            isset($checkout['returnDate']) &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkout['returnDate']) === 1,
            'Invalid or missing returnDate.'
        );

        assertTrue(
            isset($checkout['daysOverdue']) &&
            is_numeric($checkout['daysOverdue']) &&
            $checkout['daysOverdue'] >= 0,
            'Invalid daysOverdue.'
        );

        assertTrue(
            isset($checkout['dailyFineRate']) &&
            is_numeric($checkout['dailyFineRate']),
            'Invalid dailyFineRate.'
        );

        assertTrue(
            isset($checkout['calculatedFineUnits']) &&
            is_numeric($checkout['calculatedFineUnits']),
            'Invalid calculatedFineUnits.'
        );

        assertTrue(
            array_key_exists('fineAssessed', $checkout) &&
            $checkout['fineAssessed'] === null,
            'Expected fineAssessed to be null.'
        );

        assertTrue(
            ($checkout['status'] ?? '') === 'Processing',
            "Expected status 'Processing'."
        );

        assertTrue(
            in_array(
                $checkout['routingStatus'] ?? '',
                ['AwaitingReshelving', 'AwaitingTransport'],
                true
            ),
            "Unexpected routingStatus: '" .
            ($checkout['routingStatus'] ?? '') . "'."
        );

        assertTrue(
            !empty($checkout['routingInstruction']),
            'Missing routingInstruction.'
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
