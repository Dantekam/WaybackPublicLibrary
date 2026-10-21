<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db_connect.php';

$patronID = $_GET['patronID'] ?? '';

if (!is_string($patronID) ||
    !ctype_digit($patronID) ||
    (int)$patronID <= 0) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Please enter a valid Patron ID.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT
            c.CheckOutID AS checkoutID,
            CONCAT(p.FirstName, " ", p.LastName) AS patron,
            c.ItemCopyID AS itemCopyID,
            i.ItemType AS itemType,
            c.CheckOutDate AS checkoutDate,
            c.DueDate AS dueDate
         FROM CheckOut AS c
         INNER JOIN Patron AS p
            ON c.PatronID = p.PatronID
         INNER JOIN Item_Copy AS ic
            ON c.ItemCopyID = ic.ItemCopyID
         INNER JOIN ITEM AS i
            ON ic.ItemID = i.ItemID
         WHERE c.PatronID = ?
           AND c.ReturnDate IS NULL
         ORDER BY c.CheckOutDate DESC, c.CheckOutID DESC'
    );

    $stmt->execute([(int)$patronID]);

    echo json_encode([
        'ok' => true,
        'checkouts' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Unable to retrieve current checkouts.'
    ]);
}
