<?php

/*
 Requirements:
    Must have php installed in a separate php folder in the root project folder
 To run use the command '.\php\php.exe .\script\testFindPatron.php' in terminal
*/

$url = ''; # temporary placeholder

function test($name, $query, $expectedStatus, $expectedText)
{
    global $url;

    $context = stream_context_create([
        'http' => ['ignore_errors' => true]
    ]);

    $response = file_get_contents(
        $url . $query,
        false,
        $context
    );

    $status = 0;

    foreach ($http_response_header as $header) {
        if (preg_match('/HTTP\/\S+ (\d+)/', $header, $matches)) {
            $status = (int)$matches[1];
        }
    }

    $passed = $response !== false
        && $status === $expectedStatus
        && strpos($response, $expectedText) !== false;

    echo ($passed ? 'PASS' : 'FAIL') . " - $name\n";

    if (!$passed) {
        echo "Status: $status\n";
        echo "Response: $response\n";
    }
}

// Invalid Patron IDs
test('Empty ID', '?patronID=', 400, 'valid numeric Patron ID');
test('Letters in ID', '?patronID=abc', 400, 'valid numeric Patron ID');
test('Mixed ID', '?patronID=12abc', 400, 'valid numeric Patron ID');
test('Negative ID', '?patronID=-5', 400, 'valid numeric Patron ID');
test('Decimal ID', '?patronID=1.5', 400, 'valid numeric Patron ID');

// Patron not found
test('Missing patron', '?patronID=999999', 200, 'Patron not found');

// Existing patron: change 1 to a real PatronID in your database
test('Existing patron', '?patronID=1', 200, '"ok":true');
test('Patron has a name', '?patronID=1', 200, '"name":');
test('Patron has expiration', '?patronID=1', 200, '"expiration":');
test('Patron has expired field', '?patronID=1', 200, '"expired":');
?>

