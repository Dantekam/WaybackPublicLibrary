
<?php
/**
 * Integration tests for addItem.php.
 *
 * Run:
 *   .\php\php.exe .\script\testAddItem.php
 *
 * Requires PHP's cURL extension and the same database connection used by
 * addItem.php.
 *
 * CLEANUP INSTRUCTIONS:
 * Run the following SQL after testing to remove the successfully added copy:
 *
 * DELETE FROM Item_Copy
 * WHERE ItemCopyID = 10025;
 */

require_once __DIR__ . '/../loadEnv.php';
loadEnv(__DIR__ . '/../.env');

$url = $_ENV['ADD_ITEM_URL'] ?? getenv('ADD_ITEM_URL');

if (!$url) {
    die("SETUP ERROR: ADD_ITEM_URL is not configured in .env.\n");
}

if (!extension_loaded('curl')) {
    die("SETUP ERROR: PHP cURL extension is not enabled.\n");
}

// test data
$newItemCopyID = '10025';
$existingItemCopyID = '10021';

$itemID = '1';
$missingItemID = '999999';

$branchID = '1007';
$missingBranchID = '999999';

// Must match the ItemType of the existing item
$itemType = 'DVD';

// Test item information.
$title = 'Integration Test Item';
$shelfLocation = 'TEST-SHELF-01';

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

$validItem = [
    'itemCopyID' => $newItemCopyID,
    'title' => $title,
    'itemID' => $itemID,
    'branchID' => $branchID,
    'shelfLocation' => $shelfLocation,
    'itemType' => $itemType,
    'status' => 'Available'
];

// GET request rejected
addTest(
    'GET request is rejected',
    'GET',
    [],
    405,
    errorAssertion('Method not allowed. Use POST.')
);

// Missing Item Copy ID
$testData = $validItem;
unset($testData['itemCopyID']);

addTest(
    'Missing Item Copy ID',
    'POST',
    $testData,
    400,
    errorAssertion('Please enter a valid Item Copy ID.')
);

// Non-numeric Item Copy ID
$testData = $validItem;
$testData['itemCopyID'] = 'abc';

addTest(
    'Non-numeric Item Copy ID',
    'POST',
    $testData,
    400,
    errorAssertion('Please enter a valid Item Copy ID.')
);

// Missing Item ID
$testData = $validItem;
unset($testData['itemID']);

addTest(
    'Missing Item ID',
    'POST',
    $testData,
    400,
    errorAssertion('Please enter a valid Item ID.')
);

// Non-numeric Item ID
$testData = $validItem;
$testData['itemID'] = 'abc';

addTest(
    'Non-numeric Item ID',
    'POST',
    $testData,
    400,
    errorAssertion('Please enter a valid Item ID.')
);

// Missing Branch ID
$testData = $validItem;
unset($testData['branchID']);

addTest(
    'Missing Branch ID',
    'POST',
    $testData,
    400,
    errorAssertion('Please enter a valid Branch ID.')
);

// Non-numeric Branch ID
$testData = $validItem;
$testData['branchID'] = 'abc';

addTest(
    'Non-numeric Branch ID',
    'POST',
    $testData,
    400,
    errorAssertion('Please enter a valid Branch ID.')
);

// Missing title
$testData = $validItem;
$testData['title'] = '';

addTest(
    'Missing title',
    'POST',
    $testData,
    400,
    errorAssertion('Please fill in the title, item type, and shelf location.')
);

// Missing Item Type
$testData = $validItem;
$testData['itemType'] = '';

addTest(
    'Missing Item Type',
    'POST',
    $testData,
    400,
    errorAssertion('Please fill in the title, item type, and shelf location.')
);

// Missing shelf location
$testData = $validItem;
$testData['shelfLocation'] = '';

addTest(
    'Missing shelf location',
    'POST',
    $testData,
    400,
    errorAssertion('Please fill in the title, item type, and shelf location.')
);

// Invalid status
$testData = $validItem;
$testData['status'] = 'Lost';

addTest(
    'Invalid item status',
    'POST',
    $testData,
    400,
    errorAssertion('Invalid item status.')
);

// Duplicate Item Copy ID
$testData = $validItem;
$testData['itemCopyID'] = $existingItemCopyID;

addTest(
    'Item Copy ID already exists',
    'POST',
    $testData,
    409,
    errorAssertion('An item copy with that ID already exists.')
);

// Catalog Item ID does not exist
$testData = $validItem;
$testData['itemID'] = $missingItemID;

addTest(
    'Catalog Item ID does not exist',
    'POST',
    $testData,
    400,
    errorAssertion(
        'Item ID does not exist in the catalog. Create the catalog item first.'
    )
);

// Item Type does not match catalog item
$testData = $validItem;
$testData['itemType'] = 'InvalidType';

addTest(
    'Item Type does not match catalog item',
    'POST',
    $testData,
    400,
    function ($response) use ($itemType) {
        $json = $response['json'];

        assertTrue(
            is_array($json),
            "Response was not valid JSON: " . $response['body']
        );

        assertTrue(
            ($json['ok'] ?? null) === false,
            'Expected ok=false.'
        );

        assertTrue(
            ($json['error'] ?? '') ===
            'Item Type must match the existing catalog item type: ' . $itemType,
            'Unexpected Item Type validation message.'
        );
    }
);

// Branch does not exist
$testData = $validItem;
$testData['branchID'] = $missingBranchID;

addTest(
    'Branch ID does not exist',
    'POST',
    $testData,
    400,
    errorAssertion('Branch ID does not exist.')
);

// Successfully add item copy
addTest(
    'Successfully add new item copy',
    'POST',
    $validItem,
    200,
    function ($response) use (
        $newItemCopyID,
        $itemID,
        $branchID,
        $title,
        $shelfLocation,
        $itemType
    ) {
        $json = $response['json'];

        assertTrue(
            is_array($json),
            "Response was not valid JSON: " . $response['body']
        );

        assertTrue(
            ($json['ok'] ?? null) === true,
            "Expected ok=true. Server response: " . $response['body']
        );

        $item = $json['item'] ?? null;

        assertTrue(
            is_array($item),
            'Missing item object.'
        );

        assertTrue(
            (string)($item['itemCopyID'] ?? '') === $newItemCopyID,
            'Returned Item Copy ID does not match.'
        );

        assertTrue(
            ($item['title'] ?? '') === $title,
            'Returned title does not match.'
        );

        assertTrue(
            (string)($item['itemID'] ?? '') === $itemID,
            'Returned Item ID does not match.'
        );

        assertTrue(
            ($item['shelfLocation'] ?? '') === $shelfLocation,
            'Returned shelf location does not match.'
        );

        assertTrue(
            (string)($item['branchID'] ?? '') === $branchID,
            'Returned Branch ID does not match.'
        );

        assertTrue(
            ($item['itemType'] ?? '') === $itemType,
            'Returned Item Type does not match.'
        );

        assertTrue(
            ($item['status'] ?? '') === 'Available',
            "Expected status 'Available'."
        );

        assertTrue(
            ($item['available'] ?? null) === true,
            'Expected available=true.'
        );
    }
);

// Execute tests
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
