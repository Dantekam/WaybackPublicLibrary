
<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db_connect.php';

$id = $_GET['patronID'] ?? '';

if (!is_string($id) || !ctype_digit($id) || (int)$id <= 0) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Please enter a valid Patron ID.'
    ]);
    exit;
}

try {
    $sql = '
        SELECT
            c.CheckOutID AS checkoutID,
            CONCAT(p.FirstName, " ", p.LastName) AS patron,
            c.ItemCopyID AS itemCopyID,
            i.Title AS title,
            COALESCE(
                NULLIF(i.ItemType, ""),
                ic.ItemType
            ) AS itemType,
            ic.CurrentBranchID AS branchID,
            cb.BranchName AS branch,
            c.CheckOutDate AS checkoutDate,
            c.DueDate AS dueDate,
            c.ReturnDate AS returnDate,
            c.ReturnBranchID AS returnBranchID,
            COALESCE(c.RenewalCount, 0) AS renewalCount,
            (
                SELECT GROUP_CONCAT(
                    DISTINCT CONCAT(a.FirstName, " ", a.LastName)
                    ORDER BY a.LastName, a.FirstName
                    SEPARATOR ", "
                )
                FROM Item_Author ia
                JOIN Author a ON a.AuthorID = ia.AuthorID
                WHERE ia.ItemID = i.ItemID
            ) AS author
        FROM CheckOut c
        JOIN Patron p ON p.PatronID = c.PatronID
        JOIN Item_Copy ic ON ic.ItemCopyID = c.ItemCopyID
        JOIN ITEM i ON i.ItemID = ic.ItemID
        LEFT JOIN Branch cb
            ON cb.BranchID = ic.CurrentBranchID
        WHERE c.PatronID = ?
          AND c.ReturnDate IS NULL
        ORDER BY c.CheckOutDate DESC, c.CheckOutID DESC
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$id]);

    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['checkoutID'] = (int)$row['checkoutID'];
        $row['itemCopyID'] = (int)$row['itemCopyID'];
        $row['renewalCount'] = (int)$row['renewalCount'];
        $row['author'] = $row['author'] ?: 'Unknown';
    }
    unset($row);

    echo json_encode([
        'ok' => true,
        'checkouts' => $rows
    ]);

} catch (Throwable $e) {
    error_log('getPatronCheckouts: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Unable to retrieve current checkouts.'
    ]);
}
