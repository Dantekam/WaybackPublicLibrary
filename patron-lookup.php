<?php
require __DIR__ . '/script/db_connect.php';
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['staff_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated.']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Empty search.']);
    exit;
}

try {
    if (ctype_digit($q)) {
        $stmt = $pdo->prepare(
            'SELECT PatronID, FirstName, LastName, MembershipExpiration,
                    MembershipRenewalCount, email, phone_number, HomeBranchID
             FROM Patron
             WHERE PatronID = ?'
        );
        $stmt->execute([(int)$q]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT PatronID, FirstName, LastName, MembershipExpiration,
                    MembershipRenewalCount, email, phone_number, HomeBranchID
             FROM Patron
             WHERE CONCAT(FirstName, " ", LastName) LIKE ?
             ORDER BY LastName, FirstName
             LIMIT 1'
        );
        $stmt->execute(['%' . $q . '%']);
    }

    $p = $stmt->fetch();
    if (!$p) {
        echo json_encode(['ok' => false, 'error' => 'No matching patron.']);
        exit;
    }

    $patronId = (int)$p['PatronID'];

    // ---- Fines ----
    $fineStmt = $pdo->prepare(
        'SELECT f.FineID,
                f.Amount,
                f.AmountPaid,
                COALESCE(i.Title, ic.Title, "Untitled item") AS item_title
         FROM Fine f
         LEFT JOIN CheckOut c   ON c.CheckOutID = f.CheckoutID
         LEFT JOIN Item_Copy ic ON ic.ItemCopyID = c.ItemCopyID
         LEFT JOIN ITEM i       ON i.ItemID     = ic.ItemID
         WHERE f.PatronID = ?
         ORDER BY f.FineID DESC'
    );
    $fineStmt->execute([$patronId]);

    $fines        = [];
    $totalBalance = 0.0;
    foreach ($fineStmt->fetchAll() as $row) {
        $assessed = (float)$row['Amount'];
        $paid     = (float)$row['AmountPaid'];
        $balance  = round(max(0, $assessed - $paid), 2);
        $totalBalance += $balance;
        $fines[] = [
            'id'       => (int)$row['FineID'],
            'item'     => $row['item_title'],
            'assessed' => $assessed,
            'paid'     => $paid,
            'balance'  => $balance
        ];
    }

    // ---- Active checkouts (for the count + table) ----
    $coStmt = $pdo->prepare(
        'SELECT c.CheckOutID, c.ItemCopyID, c.CheckOutDate, c.DueDate,
                COALESCE(i.Title, ic.Title, "Untitled item") AS item_title,
                ic.HomeBranchID
         FROM CheckOut c
         JOIN Item_Copy ic ON ic.ItemCopyID = c.ItemCopyID
         LEFT JOIN ITEM i  ON i.ItemID      = ic.ItemID
         WHERE c.PatronID = ? AND c.ReturnDate IS NULL
         ORDER BY c.DueDate ASC'
    );
    $coStmt->execute([$patronId]);

    $checkouts = [];
    foreach ($coStmt->fetchAll() as $row) {
        $checkouts[] = [
            'id'          => (int)$row['CheckOutID'],
            'copyId'      => (int)$row['ItemCopyID'],
            'item'        => $row['item_title'],
            'checkoutDate'=> $row['CheckOutDate'],
            'dueDate'     => $row['DueDate'],
            'homeBranch'  => $row['HomeBranchID']
        ];
    }

    // ---- Reviews ----
    $reviewStmt = $pdo->prepare(
        'SELECT ReviewID, ItemID, Title, Item_Type, Rating, WrittenReview
         FROM PatronReview
         WHERE PatronID = ?
         ORDER BY ReviewID DESC'
    );
    $reviewStmt->execute([$patronId]);

    $reviews = [];
    foreach ($reviewStmt->fetchAll() as $row) {
        $reviews[] = [
            'id'     => (int)$row['ReviewID'],
            'itemId' => (int)$row['ItemID'],
            'title'  => $row['Title'],
            'type'   => $row['Item_Type'],
            'rating' => $row['Rating'] !== null ? (int)$row['Rating'] : null,
            'text'   => $row['WrittenReview']
        ];
    }

    // ---- Home branch name (optional) ----
    $branchName = null;
    if (!empty($p['HomeBranchID'])) {
        try {
            $b = $pdo->prepare('SELECT BranchName FROM Branch WHERE BranchID = ?');
            $b->execute([$p['HomeBranchID']]);
            $branchName = $b->fetchColumn() ?: null;
        } catch (Throwable $ignore) {
            // Branch table name differs — leave null
        }
    }

    echo json_encode([
        'ok'     => true,
        'patron' => [
            'id'           => $patronId,
            'name'         => trim($p['FirstName'] . ' ' . $p['LastName']),
            'email'        => $p['email'],
            'phone'        => $p['phone_number'],
            'homeBranch'   => $branchName,
            'expiration'   => $p['MembershipExpiration'],
            'renewalCount' => (int)$p['MembershipRenewalCount'],
            'totalBalance' => round($totalBalance, 2),
            'fines'        => $fines,
            'checkouts'    => $checkouts,
            'reviews'      => $reviews
        ]
    ]);
} catch (Throwable $e) {
    error_log('patron-lookup: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Lookup failed.']);
}