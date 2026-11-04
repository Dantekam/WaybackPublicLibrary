<?php
header('Content-Type: application/json');

//Db Connection
require __DIR__ . '/db_connect.php';

// 1. Get the item that could not be scanned/identified
$branchID = $_POST['branchID'] ?? '';
$branchID = (int)$branchID;
$itemCopyID = $_POST['itemCopyID'] ?? '';
$itemCopyID = (int)$itemCopyID;
$receivedDateTime = $_Post['receivedDateTime'] ?? date('Y-m-d H:i:s');
$desc = $_Post['description'] ?? '';

// Q: What other information is being given about the item???
// assume branchID itemCopyID if assoc, DateTime, Desc, Resolution, resolve date time
try {
    $pdo->beginTransaction();
    
    // 2. record an unresolved return without requiring known item copy id
    // 3. record date and branch where unscannable item reeceived
    // 5. additional info and comments added
    $stmt = $pdo->prepare(
        'INSERT INTO Error_Bin_Entry (BranchID, ItemCopyID, ReceivedDateTime, Description, ResolutionStatus, ResolvedDateTime)
            VALUES (?, ?, ?, ?, "Unresolved", NULL)'
    );
    $stmt->execute($branchID, $itemCopyID, $receivedDateTime, $desc);
    // 4. record item status as in error bin if assoc checkout
    // 6. cant be checked in or made available for checkout
    $stmt = $pdo->prepare(
        'SELECT ItemType, Status
           FROM Item_Copy
          WHERE ItemCopyID = ?
          FOR UPDATE'
    );
    $stmt->execute([$itemCopyID]);
    $copy = $stmt->fetch();

    if ($copy) {
        $stmt = $pdo->prepare(
            'UPDATE Checkout
                SET Status = "Maintenance"
                WHERE ItemCopyID = ?'
        );
        $stmt->execute($itemCopyID);
    }
    // 7. allows staff to review unsolved records
    $stmt = $pdo->prepare(
        'SELECT *
            FROM Error_Bin_Entry
            WHERE ResolutionStatus = "Unresolved"'
    );
    $stmt->execute();

    // return json of the error bin entries
    echo json_encode([
        'ok' => true,
        'error_bin' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ])

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not return checkout.']);
}
?>
