<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db_connect.php';

$itemCopyID = $_GET['itemCopyID'] ?? '';

if (!is_string($itemCopyID) ||
    !ctype_digit($itemCopyID) ||
    (int)$itemCopyID <= 0) {
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
            COALESCE(ic.Title, i.Title) AS title,
            COALESCE(it.TypeName, ic.ItemType) AS itemType,
            ic.Status,
            ic.CurrentBranchID,
            cb.BranchName AS branchName,
            ic.HomeBranchID,
            hb.BranchName AS homeBranchName,
            GROUP_CONCAT(DISTINCT CONCAT(a.FirstName, " ", a.LastName) ORDER BY a.LastName SEPARATOR ", ") AS author,
            c.CheckOutID AS checkoutID,
            CONCAT(p.FirstName, " ", p.LastName) AS patronName,
            c.DueDate,
            c.RenewalCount,
            c.PatronID
         FROM Item_Copy ic
         LEFT JOIN ITEM i ON ic.ItemID = i.ItemID
         LEFT JOIN Item_Type it ON i.ItemTypeID = it.ItemTypeID
         LEFT JOIN Branch cb ON ic.CurrentBranchID = cb.BranchID
         LEFT JOIN Branch hb ON ic.HomeBranchID = hb.BranchID
         LEFT JOIN Item_Author ia ON ia.ItemID = i.ItemID
         LEFT JOIN Author a ON a.AuthorID = ia.AuthorID
         LEFT JOIN CheckOut c ON c.ItemCopyID = ic.ItemCopyID AND c.ReturnDate IS NULL
         LEFT JOIN Patron p ON p.PatronID = c.PatronID
         WHERE ic.ItemCopyID = ?
         GROUP BY ic.ItemCopyID, ic.ItemID, title, itemType, ic.Status, ic.CurrentBranchID, cb.BranchName, ic.HomeBranchID, hb.BranchName, c.CheckOutID, c.PatronID, patronName, c.DueDate, c.RenewalCount'
    );

    $stmt->execute([(int)$itemCopyID]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode([
            'ok' => false,
            'error' => 'Item copy not found.'
        ]);
        exit;
    }

    $available = strcasecmp((string)$item['Status'], 'Available') === 0;

    echo json_encode([
        'ok' => true,
        'item' => [
            'itemCopyID' => (int)$item['ItemCopyID'],
            'itemID' => (int)$item['ItemID'],
            'title' => $item['title'],
            'itemType' => $item['itemType'] ?? 'Unknown',
            'status' => $item['Status'],
            'branchID' => $item['CurrentBranchID'] ? (int)$item['CurrentBranchID'] : null,
            'branchName' => $item['branchName'],
            'homeBranchID' => $item['HomeBranchID'] ? (int)$item['HomeBranchID'] : null,
            'homeBranchName' => $item['homeBranchName'],
            'author' => $item['author'] ?: 'Unknown',
            'activeCheckout' => $item['checkoutID'] ? [
                'checkoutID' => (int)$item['checkoutID'],
                'patronName' => $item['patronName'],
                'dueDate' => $item['DueDate'],
                'renewalCount' => $item['RenewalCount'] !== null ? (int)$item['RenewalCount'] : 0,
            ] : null,
            'available' => $available,
        ]
    ]);
} catch (PDOException $e) {
    error_log('item-lookup: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Unable to retrieve item information.'
    ]);
}