<?php
header('Content-Type: application/json');

//Db Connection
require __DIR__ . '/db_connect.php';

$itemCopyID = $_POST['itemCopyID'] ?? '';
$branchID = $_POST['branchID'] ?? '';

$itemCopyID = (int)$itemCopyID;
$branchID = (int)$branchID;

try {
    $pdo->beginTransaction();

    // 1. Get the items home branch
    $stmt = $pdo->prepare(
        'SELECT HomeBranchID
           FROM Item_Copy
          WHERE ItemCopyID = ?
          FOR UPDATE'
    );
    $stmt->execute([$itemCopyID]);
    $copy = $stmt->fetch();
    $homeBranch = $copy['HomeBranchID'];
    $homeBranch = (int)$homeBranch

    // 2. Compare home branch and returned branch
    //  If at home branch, assign AwaitingReshelving status and 
    // display instructions to place on reshelving cart
    if ($branchID == $homeBranch) {
        // set status to AwaitingReshevling
        $stmt = $pdo->prepare(
            'UPDATE Checkout SET Status = "AwaitingReshelving" WHERE CheckoutID = ?'
        );
        $stmt->execute([$checkoutID]);
    } else {
        // Make the status of item unavailable while await shelving or transport
        $stmt = $pdo->prepare(
            'UPDATE Checkout SET Status = "AwaitingTransport" WHERE CheckoutID = ?'
        );
        $stmt->execute([$checkoutID]);
    }
    
    // 3. marked available after reshelving complete
    // TODO
    
    // return the item and patron info
    echo json_encode([
        'ok' => true,
        'checkout' => [
            'checkoutID' => $checkout['CheckoutID'],
            'patron' => $checkout['FullName'];,
            'itemCopyID' => $itemCopyID,
            'title' => $checkout['Item']
            'itemType' => $checkout['ItemType'],
            'checkoutDate' => $checkout['CheckoutDate'],
            'dueDate' => $checkout['DueDate'],
            'status' => $checkout['Status']
        ],
    ]);*/

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not return checkout.']);
}