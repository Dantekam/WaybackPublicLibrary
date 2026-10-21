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

// Invalid Item Copy IDs
test('Empty ID', '?itemCopyID=', 400, 'valid numeric Item Copy ID');
test('Letters in ID', '?itemCopyID=abc', 400, 'valid numeric Item Copy ID');
test('Mixed ID', '?itemCopyID=12abc', 400, 'valid numeric Item Copy ID');
test('Negative ID', '?itemCopyID=-5', 400, 'valid numeric Item Copy ID');
test('Decimal ID', '?itemCopyID=1.5', 400, 'valid numeric Item Copy ID');

// Item not found
test('Missing item', '?itemCopyID=999999', 200, 'Itemcopy not found');

// Existing item: change 1 to a real ItemCopyID in your database
test('Existing item', '?itemCopyID=1', 200, '"ok":true');
test('Item has type', '?itemCopyID=1', 200, '"itemType":');
test('Item has status', '?itemCopyID=1', 200, '"status":');
test('Item has availability', '?itemCopyID=1', 200, '"available":');

?>
