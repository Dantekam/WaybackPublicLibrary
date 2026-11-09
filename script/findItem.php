
<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db_connect.php';

$id = $_GET['itemCopyID'] ?? '';

if (!is_string($id) || !ctype_digit($id) || (int)$id <= 0) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Please enter a valid numeric Item Copy ID.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT
            ic.ItemCopyID,
            ic.ItemID,
            COALESCE(i.Title, ic.Title) AS title,
            COALESCE(it.TypeName, i.ItemType, ic.ItemType)
                AS itemType,
            ic.Status,
            ic.RoutingStatus,
            ic.DestinationBranchID,
            ic.CurrentBranchID,
            cb.BranchName AS branchName,
            ic.HomeBranchID,
            hb.BranchName AS homeBranchName,
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
         FROM Item_Copy ic
         LEFT JOIN ITEM i ON i.ItemID = ic.ItemID
         LEFT JOIN Item_Type it ON it.ItemTypeID = i.ItemTypeID
         LEFT JOIN Branch cb
            ON cb.BranchID = ic.CurrentBranchID
         LEFT JOIN Branch hb
            ON hb.BranchID = ic.HomeBranchID
         WHERE ic.ItemCopyID = ?'
    );

    $stmt->execute([(int)$id]);
    $item = $stmt->fetch();

    if (!$item) {
        echo json_encode([
            'ok' => false,
            'error' => 'Item copy not found.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT
            c.CheckOutID,
            c.PatronID,
            c.DueDate,
            COALESCE(c.RenewalCount, 0) AS RenewalCount,
            CONCAT(p.FirstName, " ", p.LastName) AS patronName
         FROM CheckOut c
         JOIN Patron p ON p.PatronID = c.PatronID
         WHERE c.ItemCopyID = ?
           AND c.ReturnDate IS NULL
         ORDER BY c.CheckOutID DESC'
    );
    $stmt->execute([(int)$id]);
    $loans = $stmt->fetchAll();

    $active = null;

    if (count($loans) === 1) {
        $loan = $loans[0];

        $active = [
            'checkoutID' => (int)$loan['CheckOutID'],
            'patronID' => (int)$loan['PatronID'],
            'patronName' => $loan['patronName'],
            'dueDate' => $loan['DueDate'],
            'renewalCount' => (int)$loan['RenewalCount']
        ];
    }

    $available = $item['Status'] === 'Available'
        && count($loans) === 0;

    echo json_encode([
        'ok' => true,
        'item' => [
            'itemCopyID' => (int)$item['ItemCopyID'],
            'itemID' => (int)$item['ItemID'],
            'title' => $item['title'],
            'itemType' => $item['itemType'] ?: 'Unknown',
            'author' => $item['author'] ?: 'Unknown',
            'status' => $item['Status'],
            'routingStatus' => $item['RoutingStatus'],
            'destinationBranchID' => $item['DestinationBranchID'],
            'branchID' => $item['CurrentBranchID'],
            'branchName' => $item['branchName'],
            'homeBranchID' => $item['HomeBranchID'],
            'homeBranchName' => $item['homeBranchName'],
            'activeCheckout' => $active,
            'activeCheckoutCount' => count($loans),
            'available' => $available
        ]
    ]);

} catch (Throwable $e) {
    error_log('findItem: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Unable to retrieve item information.'
    ]);
}
