
<?php
/**
 * Integration tests for routeReturnedItem.php.
 *
 * Run:
 *   .\php\php.exe .\script\testRouteReturnedItem.php
 *
 * Requires PHP's cURL extension and the same database connection used by
 * routeReturnedItem.php.
 */

require_once __DIR__ . '/../loadEnv.php';
loadEnv(__DIR__ . '/../.env');

$url = $_ENV['ROUTE_RETURNED_ITEM_URL'] ?? getenv('ROUTE_RETURNED_ITEM_URL');

if (!$url) {
    die("SETUP ERROR: ROUTE_RETURNED_ITEM_URL is not configured in .env.\n");
}

if (!extension_loaded('curl')) {
    die("SETUP ERROR: PHP cURL extension is not enabled.\n");
}

// test data - change as needed
$homeBranchCopyID = '10021';
$otherBranchCopyID = '10024';

$homeBranchID = '1007';
$otherBranchID = '1008';

$missingCopyID = '999999';
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
        curl_close($ch);
        throw new Exception("cURL request failed: $error");
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

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

// Missing item copy ID
addTest(
    'Missing item copy ID',
    'POST',
    [
        'branchID' => $homeBranchID
    ],
    400,
    errorAssertion('Valid item copy and branch IDs are required.')
);

// Non-numeric item copy ID
addTest(
    'Non-numeric item copy ID',
    'POST',
    [
        'itemCopyID' => 'abc',
        'branchID' => $homeBranchID
    ],
    400,
    errorAssertion('Valid item copy and branch IDs are required.')
);

// Missing branch ID
addTest(
    'Missing branch ID',
    'POST',
    [
        'itemCopyID' => $homeBranchCopyID
    ],
    400,
    errorAssertion('Valid item copy and branch IDs are required.')
);

// Non-numeric branch ID
addTest(
    'Non-numeric branch ID',
    'POST',
    [
        'itemCopyID' => $homeBranchCopyID,
        'branchID' => 'abc'
    ],
    400,
    errorAssertion('Valid item copy and branch IDs are required.')
);

// Item copy does not exist
addTest(
    'Item copy does not exist',
    'POST',
    [
        'itemCopyID' => $missingCopyID,
        'branchID' => $homeBranchID
    ],
    404,
    errorAssertion('Item copy not found.')
);

// Branch does not exist
addTest(
    'Branch does not exist',
    'POST',
    [
        'itemCopyID' => $homeBranchCopyID,
        'branchID' => $missingBranchID
    ],
    404,
    errorAssertion('Branch not found.')
);

// Successfully route item to its home branch
addTest(
    'Successfully route item to home branch',
    'POST',
    [
        'itemCopyID' => $homeBranchCopyID,
        'branchID' => $homeBranchID
    ],
    200,

    function ($response) {
        $json = $response['json'];

        assertTrue(
            is_array($json),
            "Response was not valid JSON: " . $response['body']
        );

        assertTrue(
            ($json['ok'] ?? null) === true,
            "Expected ok=true. Server response: " . $response['body']
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
            ($checkout['status'] ?? '') === 'AwaitingReshelving',
            "Expected status 'AwaitingReshelving'."
        );
    }
);

// Successfully route item to a different branch
addTest(
    'Successfully route item to another branch',
    'POST',
    [
        'itemCopyID' => $otherBranchCopyID,
        'branchID' => $otherBranchID
    ],
    200,

    function ($response) {
        $json = $response['json'];

        assertTrue(
            is_array($json),
            "Response was not valid JSON: " . $response['body']
        );

        assertTrue(
            ($json['ok'] ?? null) === true,
            "Expected ok=true. Server response: " . $response['body']
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
            ($checkout['status'] ?? '') === 'AwaitingTransport',
            "Expected status 'AwaitingTransport'."
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
