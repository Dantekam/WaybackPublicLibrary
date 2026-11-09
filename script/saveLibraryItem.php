<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db_connect.php';

function failRequest($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function field($key) {
    $value = $_POST[$key] ?? '';
    return is_array($value) ? '' : trim((string)$value);
}

function positiveId($key) {
    $value = field($key);
    if (!ctype_digit($value)) return null;
    $id = (int)$value;
    return $id > 0 && $id <= 2147483647 ? $id : null;
}

function validDate($value) {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

function authorsFromPost() {
    $firstNames = $_POST['authorFirstName'] ?? [];
    $lastNames = $_POST['authorLastName'] ?? [];

    if (!is_array($firstNames)) $firstNames = [$firstNames];
    if (!is_array($lastNames)) $lastNames = [$lastNames];

    if (count($firstNames) !== count($lastNames)) {
        throw new DomainException('Author fields do not match.');
    }

    $authors = [];

    foreach ($firstNames as $index => $first) {
        $last = $lastNames[$index] ?? '';

        if (!is_string($first) || !is_string($last)) {
            throw new DomainException('Invalid author information.');
        }

        $first = trim($first);
        $last = trim($last);

        if ($first === '' && $last === '') continue;

        if ($first === '' || $last === '') {
            throw new DomainException(
                'Enter both first and last name for each author.'
            );
        }

        if (strlen($first) > 50 || strlen($last) > 50) {
            throw new DomainException('Author name is too long.');
        }

        $key = strtolower($first . '|' . $last);
        $authors[$key] = [$first, $last];
    }

    return array_values($authors);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    failRequest('POST required.', 405);
}

$mode = field('mode');
$copyID = positiveId('itemCopyID');
$branchID = positiveId('branchID');
$shelf = field('shelfLocation');
$acquisitionDate = field('acquisitionDate');

if (!in_array($mode, ['existing', 'new'], true)) {
    failRequest('Choose an existing or new catalog item.');
}

if (!$copyID || !$branchID || $shelf === '') {
    failRequest('Enter a valid copy ID, branch, and shelf location.');
}

if (strlen($shelf) > 100) {
    failRequest('Shelf location must be 100 characters or fewer.');
}

if (!validDate($acquisitionDate)) {
    failRequest('Enter a valid acquisition date.');
}

try {
    $pdo->beginTransaction();

    $branchStmt = $pdo->prepare(
        'SELECT BranchName FROM Branch WHERE BranchID = ?'
    );
    $branchStmt->execute([$branchID]);
    $branchName = $branchStmt->fetchColumn();

    if ($branchName === false) {
        throw new DomainException('Selected branch does not exist.');
    }

    $copyCheck = $pdo->prepare(
        'SELECT ItemCopyID FROM Item_Copy WHERE ItemCopyID = ?'
    );
    $copyCheck->execute([$copyID]);

    if ($copyCheck->fetchColumn() !== false) {
        throw new DomainException('This Item Copy ID already exists.');
    }

    if ($mode === 'existing') {
        $itemID = positiveId('existingItemID');

        if (!$itemID) {
            throw new DomainException(
                'Select an existing catalog item from the search results.'
            );
        }

        $itemStmt = $pdo->prepare(
            'SELECT Title, ItemType FROM ITEM WHERE ItemID = ?'
        );
        $itemStmt->execute([$itemID]);
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new DomainException('Catalog item not found.');
        }

        $title = $item['Title'];
        $itemType = $item['ItemType'];

    } else {
        $itemID = positiveId('newItemID');
        $title = field('title');
        $itemType = field('itemType');
        $publisher = field('publisher');
        $loc = field('libraryOfCongressCode');
        $cost = field('cost');
        $publicationDate = field('publicationDate');

        if (!$itemID || $title === '' || $itemType === '' ||
            $publisher === '' || $loc === '' || $cost === '') {
            throw new DomainException(
                'Complete all required catalog fields.'
            );
        }

        if (strlen($title) > 100 ||
            strlen($itemType) > 100 ||
            strlen($publisher) > 100 ||
            strlen($loc) > 150) {
            throw new DomainException(
                'A catalog field exceeds its allowed length.'
            );
        }

        if (!ctype_digit($cost) ||
            (float)$cost > 2147483647) {
            throw new DomainException(
                'Cost must be a nonnegative whole number within the database limit.'
            );
        }

        if ($publicationDate !== '' && !validDate($publicationDate)) {
            throw new DomainException('Enter a valid publication date.');
        }

        $authors = authorsFromPost();

        $itemCheck = $pdo->prepare(
            'SELECT ItemID FROM ITEM WHERE ItemID = ?'
        );
        $itemCheck->execute([$itemID]);

        if ($itemCheck->fetchColumn() !== false) {
            throw new DomainException('This catalog Item ID already exists.');
        }

        $insertItem = $pdo->prepare(
            'INSERT INTO ITEM
             (ItemID, Title, ItemType, Publisher,
              LibraryofCongressCode, Cost, PublicationDate)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $insertItem->execute([
            $itemID,
            $title,
            $itemType,
            $publisher,
            $loc,
            (int)$cost,
            $publicationDate === '' ? null : $publicationDate
        ]);

        foreach ($authors as [$first, $last]) {
            $findAuthor = $pdo->prepare(
                'SELECT AuthorID
                 FROM Author
                 WHERE FirstName = ? AND LastName = ?
                 ORDER BY AuthorID LIMIT 1'
            );
            $findAuthor->execute([$first, $last]);
            $authorID = $findAuthor->fetchColumn();

            if ($authorID === false) {
                $nextAuthor = $pdo->query(
                    'SELECT COALESCE(MAX(AuthorID), 0) + 1
                     FROM Author'
                )->fetchColumn();

                $authorID = (int)$nextAuthor;

                $insertAuthor = $pdo->prepare(
                    'INSERT INTO Author
                     (AuthorID, FirstName, LastName)
                     VALUES (?, ?, ?)'
                );
                $insertAuthor->execute([
                    $authorID, $first, $last
                ]);
            }

            $linkAuthor = $pdo->prepare(
                'INSERT INTO Item_Author
                 (ItemID, AuthorID, Title)
                 VALUES (?, ?, ?)'
            );
            $linkAuthor->execute([
                $itemID, (int)$authorID, $title
            ]);
        }
    }

    // A new copy must be physically processed before checkout.
    // It begins at the selected branch, not in transit.
    $status = 'Processing';
    $routingStatus = 'NotApplicable';

    $insertCopy = $pdo->prepare(
        'INSERT INTO Item_Copy
         (ItemCopyID, ItemID, Title, ItemType,
          HomeBranchID, CurrentBranchID,
          Status, RoutingStatus, DestinationBranchID,
          AcquisitionDate, ShelfLocationCode)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $insertCopy->execute([
        $copyID,
        $itemID,
        $title,
        $itemType,
        $branchID,
        $branchID,
        $status,
        $routingStatus,
        null,
        $acquisitionDate,
        $shelf
    ]);

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'itemID' => $itemID,
        'itemCopyID' => $copyID,
        'title' => $title,
        'status' => $status,
        'routingStatus' => $routingStatus,
        'branch' => $branchName,
        'acquisitionDate' => $acquisitionDate
    ]);

} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    failRequest($e->getMessage());

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    error_log('saveLibraryItem: ' . $e->getMessage());

    if (($e->errorInfo[1] ?? null) == 1062) {
        failRequest(
            'A catalog item, copy, or author association already exists.',
            409
        );
    }

    failRequest(
        'Database could not save the item. Check the server log.',
        500
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    error_log('saveLibraryItem: ' . $e->getMessage());

    failRequest('Unexpected server error.', 500);
}