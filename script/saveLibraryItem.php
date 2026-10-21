<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db_connect.php';
function failRequest($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') failRequest('POST required.', 405);
function field($key) { return trim((string)($_POST[$key] ?? '')); }
function positiveId($key) {
    $value = field($key);
    return ctype_digit($value) && (int)$value > 0 ? (int)$value : null;
}
$mode = field('mode');
$copyID = positiveId('itemCopyID');
$branchID = positiveId('branchID');
$shelf = field('shelfLocation');
$date = field('acquisitionDate');
if (!$copyID || !$branchID || $shelf === '') failRequest('Enter a valid copy ID, branch and shelf location.');
if (strlen($shelf) > 50) failRequest('Shelf location is too long.');
$parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$parsed || $parsed->format('Y-m-d') !== $date) failRequest('Enter a valid acquisition date.');
if (!in_array($mode, ['existing', 'new'], true)) failRequest('Choose an existing or new catalog item.');
try {
    $pdo->beginTransaction();
    $branchStmt = $pdo->prepare('SELECT BranchName FROM Branch WHERE BranchID = ?');
    $branchStmt->execute([$branchID]);
    $branchName = $branchStmt->fetchColumn();
    if ($branchName === false) throw new DomainException('Select a valid branch.');
    $centralStmt = $pdo->query("SELECT BranchID FROM Branch WHERE LOWER(BranchName) LIKE '%central%' ORDER BY BranchID LIMIT 1");
    $centralID = $centralStmt->fetchColumn();
    if ($centralID === false) throw new DomainException('Central branch was not found. Please verify branch names.');
    $isCentral = $branchID === (int)$centralID;
    // Copies going to another branch cannot be checked out until processed there.
    $status = $isCentral ? 'Available' : 'Maintenance';
    if ($mode === 'existing') {
        $itemID = positiveId('existingItemID');
        if (!$itemID) throw new DomainException('Select a catalog item from search results.');
        $itemStmt = $pdo->prepare('SELECT Title, ItemType FROM ITEM WHERE ItemID = ?');
        $itemStmt->execute([$itemID]);
        $item = $itemStmt->fetch();
        if (!$item) throw new DomainException('Selected catalog item does not exist.');
        $title = $item['Title'];
        $itemType = $item['ItemType'];
    } else {
        $itemID = positiveId('newItemID');
        $title = field('title');
        $itemType = field('itemType');
        $publisher = field('publisher');
        $loc = field('libraryOfCongressCode');
        $cost = field('cost');
        if (!$itemID || !$title || !$itemType || !$publisher || !$loc || $cost === '') throw new DomainException('Complete all catalog definition fields.');
        if (strlen($title) > 100 || strlen($itemType) > 100 || strlen($publisher) > 100 || strlen($loc) > 150) throw new DomainException('A catalog field exceeds its allowed length.');
        if (!ctype_digit($cost) || (int)$cost > 2147483647) throw new DomainException('Cost must be a nonnegative whole number (database stores integer cost).');
        $insert = $pdo->prepare('INSERT INTO ITEM (ItemID, Title, ItemType, Publisher, LibraryofCongressCode, Cost) VALUES (?, ?, ?, ?, ?, ?)');
        $insert->execute([$itemID, $title, $itemType, $publisher, $loc, (int)$cost]);
        $first = field('authorFirstName');
        $last = field('authorLastName');
        if (($first === '') !== ($last === '')) throw new DomainException('Provide both author first and last name, or leave both blank.');
        if ($first !== '' && $last !== '') {
            if (strlen($first) > 50 || strlen($last) > 50) throw new DomainException('Author name is too long.');
            $authorStmt = $pdo->prepare('SELECT AuthorID FROM Author WHERE FirstName = ? AND LastName = ? ORDER BY AuthorID LIMIT 1');
            $authorStmt->execute([$first, $last]);
            $authorID = $authorStmt->fetchColumn();
            if ($authorID === false) {
                $nextAuthor = $pdo->query('SELECT COALESCE(MAX(AuthorID), 0) + 1 FROM Author FOR UPDATE')->fetchColumn();
                $pdo->prepare('INSERT INTO Author (AuthorID, FirstName, LastName) VALUES (?, ?, ?)')->execute([(int)$nextAuthor, $first, $last]);
                $authorID = (int)$nextAuthor;
            }
            $pdo->prepare('INSERT INTO Item_Author (ItemID, AuthorID, Title) VALUES (?, ?, ?)')->execute([$itemID, (int)$authorID, $title]);
        }
    }
    $copyStmt = $pdo->prepare('INSERT INTO Item_Copy (ItemCopyID, Title, ItemType, Status, ItemID, BranchID, AcquisitionDate, ShelfLocationCode) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $copyStmt->execute([$copyID, $title, $itemType, $status, $itemID, $branchID, $date, $shelf]);
    $pdo->commit();
    echo json_encode(['ok' => true, 'itemID' => $itemID, 'itemCopyID' => $copyID, 'title' => $title, 'status' => $status, 'branch' => $branchName, 'acquisitionDate' => $date]);
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    failRequest($e->getMessage());
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('saveLibraryItem: ' . $e->getMessage());
    if (($e->errorInfo[1] ?? null) == 1062) failRequest('This Item ID or Item Copy ID already exists.', 409);
    failRequest('Database could not save the item. Check the server log.', 500);
}
