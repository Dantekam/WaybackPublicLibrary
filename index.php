<?php

require __DIR__ . '/script/db_connect.php';

$searchTerm = '';
$results = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_in'])) {
    $itemCopyID = $_POST['item_copy_id'];

    $sql = "UPDATE Item_Copy
            SET Status = 'Open'
            WHERE ItemCopyID = :itemCopyID";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'itemCopyID' => $itemCopyID
    ]);

    $message = "Item " . htmlspecialchars($itemCopyID) . " has been checked in.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searchTerm = trim($_POST['search'] ?? '');

    if ($searchTerm !== '') {
        $sql = "
            SELECT
                Item_Copy.ItemCopyID,
                Item_Copy.Title,
                Item_Copy.ItemType,
                Item_Copy.Status,
                Patron.FirstName,
                Patron.LastName,
                CheckOut.DueDate
            FROM Item_Copy
            LEFT JOIN CheckOut
                ON Item_Copy.ItemCopyID = CheckOut.ItemCopyID
            LEFT JOIN Patron
                ON CheckOut.PatronID = Patron.PatronID
            WHERE Item_Copy.Title LIKE :search
        ";

        $statement = $pdo->prepare($sql);
        $statement->execute([
            'search' => '%' . $searchTerm . '%'
        ]);

        $results = $statement->fetchAll();
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wayback Public Library</title>
</head>

<body>

    <header>
        <h1>Wayback Public Library</h1>
    </header>

    <section>
        <h2>Search Library Items</h2>

        <?php if ($message !== ''): ?>
            <p><?= $message ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="search">Title:</label>

            <input
                type="text"
                id="search"
                name="search"
                value="<?= htmlspecialchars($searchTerm) ?>"
            >

            <input type="submit" value="Search">
        </form>
    </section>

    <?php if ($searchTerm !== ''): ?>

        <section>
            <h2>Search Results</h2>

            <?php if (count($results) > 0): ?>

                <table border="1">
                    <tr>
                        <th>Item Copy ID</th>
                        <th>Title</th>
                        <th>Item Type</th>
                        <th>Status</th>
                        <th>Patron</th>
                        <th>Due Date</th>
                        <th>Action</th>
                    </tr>

                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['ItemCopyID']) ?></td>
                            <td><?= htmlspecialchars($row['Title']) ?></td>
                            <td><?= htmlspecialchars($row['ItemType']) ?></td>
                            <td><?= htmlspecialchars($row['Status']) ?></td>

                            <td>
                                <?php
                                if ($row['FirstName'] !== null) {
                                    echo htmlspecialchars(
                                        $row['FirstName'] . ' ' . $row['LastName']
                                    );
                                } else {
                                    echo 'Not checked out';
                                }
                                ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['DueDate'] ?? '--') ?>
                            </td>

                            <td>
                                <?php if ($row['Status'] === 'CheckedOut'): ?>
                                    <form method="POST">
                                        <input
                                            type="hidden"
                                            name="item_copy_id"
                                            value="<?= htmlspecialchars($row['ItemCopyID']) ?>"
                                        >
                                        <input
                                            type="hidden"
                                            name="search"
                                            value="<?= htmlspecialchars($searchTerm) ?>"
                                        >
                                        <button type="submit" name="check_in">
                                            Check In
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                </table>

            <?php else: ?>

                <p>No matching items found.</p>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</body>
</html>