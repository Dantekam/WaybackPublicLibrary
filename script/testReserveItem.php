
<?php
/**
 * Integration tests for reserveItem.php.
 *
 * Run:
 *   .\php\php.exe .\script\testReserveItem.php
 *
 * Requires PHP's cURL extension and the same database connection used by
 * reserveItem.php.
 * 
 * CLEANUP:
 * run the following sql statements
 * DELETE FROM Reservation
 * WHERE itemCopyID = 10059;
 * DELETE FROM Reservation
 * WHERE itemCopyID = 10060;
 */

require_once __DIR__ . '/../loadEnv.php';
loadEnv(__DIR__ . '/../.env');

$url = $_ENV['RESERVE_ITEM_URL'] ?? getenv('RESERVE_ITEM_URL');

if (!$url) {
    die("SETUP ERROR: RENEW_ITEM_URL is not configured in .env.\n");
}

if (!extension_loaded('curl')) {
    die("SETUP ERROR: PHP cURL extension is not enabled.\n");
}

$eligiblePatronId = '41';
$expiredPatronId = '42';
$missingPatronId = '999999';
$missingMembershipPatronId = '47';

$availableCopyId = '10060';
$pendingCopyId = '10024';
$retiredCopyId = '10058';
$maintenanceCopyId = '10057';
$activeReservationCopyId = '10049';
$missingCopyId = '999999';
$notAvailableCopyId = '10059';

$tests = [];
$passed = 0;
$failed = 0;

$createdReservationIds = [];

function addTest($name, $method, $postData, $expectedHttp, $assertion)
{
    global $tests;

    $tests[] = [
        'name' => $name,
        'method' => $method,
        'postData' => $postData,
        'expectedHttp' => $expectedHttp,
        'assertion' => $assertion,
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

function reservePost($patronID, $copyID)
{
    return [
        'patronID' => $patronID,
        'itemCopyID' => $copyID
    ];
}

function validID($id)
{
    return is_string($id) &&
        $id !== '' &&
        ctype_digit($id) &&
        (int)$id > 0;
}

function successReservationAssertion(
    $response,
    $patronID,
    $copyID,
    $expectedStatus
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

    $reservation = $json['reservation'] ?? null;

    assertTrue(
        is_array($reservation),
        'Missing reservation object.'
    );

    assertTrue(
        isset($reservation['reservationID']) &&
        (int)$reservation['reservationID'] > 0,
        'Missing or invalid reservationID.'
    );

    assertTrue(
        (string)($reservation['patronID'] ?? '') === $patronID,
        'Returned patronID does not match the requested patron.'
    );

    assertTrue(
        (string)($reservation['itemCopyID'] ?? '') === $copyID,
        'Returned itemCopyID does not match the requested copy.'
    );

    assertTrue(
        ($reservation['status'] ?? '') === $expectedStatus,
        "Expected reservation status '$expectedStatus'."
    );

    if ($expectedStatus === 'Ready') {
        assertTrue(
            ($json['message'] ?? '') ===
            'Item reserved and ready for pickup.',
            'Unexpected ready-reservation success message.'
        );

        assertTrue(
            !empty($reservation['readyForPickupDateTime']),
            'Missing readyForPickupDateTime.'
        );

        assertTrue(
            !empty($reservation['expirationDateTime']),
            'Missing expirationDateTime.'
        );

        assertTrue(
            strtotime($reservation['expirationDateTime']) >
            strtotime($reservation['readyForPickupDateTime']),
            'Expiration date must be later than pickup date.'
        );

    } else {
        assertTrue(
            ($json['message'] ?? '') ===
            'Reservation created. Pickup begins when the item is ready.',
            'Unexpected pending-reservation success message.'
        );

        assertTrue(
            ($reservation['readyForPickupDateTime'] ?? null) === null,
            'Expected readyForPickupDateTime to be null.'
        );

        assertTrue(
            ($reservation['expirationDateTime'] ?? null) === null,
            'Expected expirationDateTime to be null.'
        );
    }
}

addTest(
    'GET request is rejected',
    'GET',
    [],
    405,
    errorAssertion('POST required.')
);

addTest(
    'Missing patronID and itemCopyID',
    'POST',
    [],
    400,
    errorAssertion('Valid patron and item copy IDs are required.')
);

addTest(
    'Empty patronID',
    'POST',
    reservePost('', '1'),
    400,
    errorAssertion('Valid patron and item copy IDs are required.')
);

addTest(
    'Empty itemCopyID',
    'POST',
    reservePost('1', ''),
    400,
    errorAssertion('Valid patron and item copy IDs are required.')
);

addTest(
    'Zero patronID',
    'POST',
    reservePost('0', '1'),
    400,
    errorAssertion('Valid patron and item copy IDs are required.')
);

addTest(
    'Zero itemCopyID',
    'POST',
    reservePost('1', '0'),
    400,
    errorAssertion('Valid patron and item copy IDs are required.')
);

addTest(
    'Negative IDs',
    'POST',
    reservePost('-1', '-2'),
    400,
    errorAssertion('Valid patron and item copy IDs are required.')
);

addTest(
    'Nonnumeric IDs',
    'POST',
    reservePost('abc', 'xyz'),
    400,
    errorAssertion('Valid patron and item copy IDs are required.')
);

addTest(
    'Decimal IDs',
    'POST',
    reservePost('1.5', '2.5'),
    400,
    errorAssertion('Valid patron and item copy IDs are required.')
);

if (validID($eligiblePatronId)) {
    addTest(
        'Item copy does not exist',
        'POST',
        reservePost($eligiblePatronId, $missingCopyId),
        409,
        errorAssertion('Item copy not found.')
    );
}

if (validID($eligiblePatronId) && validID($retiredCopyId)) {
    addTest(
        'Retired item copy cannot be reserved',
        'POST',
        reservePost($eligiblePatronId, $retiredCopyId),
        409,
        errorAssertion('This item copy cannot currently be reserved.')
    );
}

if (validID($eligiblePatronId) && validID($maintenanceCopyId)) {
    addTest(
        'Maintenance item copy cannot be reserved',
        'POST',
        reservePost($eligiblePatronId, $maintenanceCopyId),
        409,
        errorAssertion('This item copy cannot currently be reserved.')
    );
}

if (validID($availableCopyId)) {
    addTest(
        'Patron does not exist',
        'POST',
        reservePost($missingPatronId, $availableCopyId),
        409,
        errorAssertion('Patron not found.')
    );
}

if (validID($expiredPatronId) && validID($availableCopyId)) {
    addTest(
        'Patron membership has expired',
        'POST',
        reservePost($expiredPatronId, $availableCopyId),
        409,
        errorAssertion('Patron membership has expired.')
    );
}

if (validID($missingMembershipPatronId) && validID($availableCopyId)) {
    addTest(
        'Patron has no membership expiration date',
        'POST',
        reservePost($missingMembershipPatronId, $availableCopyId),
        409,
        errorAssertion('Patron membership has expired.')
    );
}

if (validID($eligiblePatronId) && validID($activeReservationCopyId)) {
    addTest(
        'Item copy already has an active reservation',
        'POST',
        reservePost($eligiblePatronId, $activeReservationCopyId),
        409,
        errorAssertion('This item copy already has an active reservation.')
    );
}

if (validID($eligiblePatronId) && validID($availableCopyId)) {
    addTest(
        'Successful reservation for an available copy',
        'POST',
        reservePost($eligiblePatronId, $availableCopyId),
        200,
        function ($response) use ($eligiblePatronId, $availableCopyId) {
            successReservationAssertion(
                $response,
                $eligiblePatronId,
                $availableCopyId,
                'Ready'
            );
        },
        $availableCopyId
    );
}

if (validID($eligiblePatronId) && validID($notAvailableCopyId)) {
    addTest(
        'Successful reservation for a non-available copy',
        'POST',
        reservePost($eligiblePatronId, $notAvailableCopyId),
        200,
        function ($response) use ($eligiblePatronId, $notAvailableCopyId) {
            successReservationAssertion(
                $response,
                $eligiblePatronId,
                $notAvailableCopyId,
                'Pending'
            );
        },
        $notAvailableCopyId
    );
}

try {

    foreach ($tests as $test) {
        try {

            $response = requestJson(
                $url,
                $test['method'],
                $test['postData']
            );

            $createdId = $response['json']['reservation']['reservationID'] ?? null;

            if ($createdId !== null && (int)$createdId > 0) {
                $createdReservationIds[] = (int)$createdId;
            }

            assertTrue(
                is_array($response['json']),
                "Response was not valid JSON: " .
                $response['jsonError'] .
                "; response body: " . $response['body']
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

} catch (Throwable $e) {
    echo "SETUP ERROR: " . $e->getMessage() . "\n";
    $failed++;

} 

echo "\nResults: $passed passed, $failed failed, " .
    count($tests) . " executed.\n";

if ($failed > 0) {
    exit(1);
}

exit(0);

?>
