<?php

/*
 Requirements:
    Must have php installed in a separate php folder in the root project folder
 To run use the command '.\php\php.exe .\script\testFindPatron.php' in terminal
*/

$url = ''; # temporary placeholder

function test($name, $data, $expectedStatus, $expectedText)
{
    global $url;

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'ignore_errors' => true
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

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

// Invalid IDs
test('Empty IDs', ['patronID' => '', 'itemCopyID' => ''], 400, 'Missing or invalid patron/item.');
test('Invalid patron ID', ['patronID' => 'abc', 'itemCopyID' => '1'], 400, 'Missing or invalid patron/item.');
test('Invalid item ID', ['patronID' => '1', 'itemCopyID' => 'abc'], 400, 'Missing or invalid patron/item.');
test('Negative patron ID', ['patronID' => '-1', 'itemCopyID' => '1'], 400, 'Missing or invalid patron/item.');
test('Decimal item ID', ['patronID' => '1', 'itemCopyID' => '1.5'], 400, 'Missing or invalid patron/item.');

// Patron not found
test('Missing patron', ['patronID' => '999999', 'itemCopyID' => '1'], 200, 'Patron not found.');

// Item not found
test('Missing item', ['patronID' => '1', 'itemCopyID' => '999999'], 200, 'Item copy not found.');

// Existing patron and item
test('Successful checkout', ['patronID' => '1', 'itemCopyID' => '1002'], 200, '"ok":true');
test('Checkout has patron name', ['patronID' => '1', 'itemCopyID' => '1003'], 200, '"patron":');
test('Checkout has due date', ['patronID' => '1', 'itemCopyID' => '1004'], 200, '"dueDate":');

?>