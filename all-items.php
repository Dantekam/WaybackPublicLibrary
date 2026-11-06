<?php
require __DIR__ . '/script/db_connect.php';

$sort = $_GET['sort'] ?? 'name';
$direction = strtolower($_GET['dir'] ?? 'asc');

if (!in_array($sort, ['name', 'status'], true)) {
    $sort = 'name';
}
if (!in_array($direction, ['asc', 'desc'], true)) {
    $direction = 'asc';
}

switch ($sort) {
    case 'status':
        $orderClause = 'ic.Status ' . strtoupper($direction) . ', COALESCE(i.Title, ic.Title) ASC, ic.ItemCopyID ASC';
        break;
    case 'name':
    default:
        $orderClause = 'COALESCE(i.Title, ic.Title) ' . strtoupper($direction) . ', ic.ItemCopyID ASC';
        break;
}

$sql = "
    SELECT
        ic.ItemCopyID,
        COALESCE(i.Title, ic.Title) AS title,
        COALESCE(it.TypeName, ic.ItemType) AS itemType,
        ic.Status,
        cb.BranchName AS branchName,
        GROUP_CONCAT(DISTINCT CONCAT(a.FirstName, ' ', a.LastName) ORDER BY a.LastName SEPARATOR ', ') AS author
    FROM Item_Copy ic
    LEFT JOIN ITEM i ON i.ItemID = ic.ItemID
    LEFT JOIN Item_Type it ON it.ItemTypeID = i.ItemTypeID
    LEFT JOIN Branch cb ON cb.BranchID = ic.CurrentBranchID
    LEFT JOIN Item_Author ia ON ia.ItemID = i.ItemID
    LEFT JOIN Author a ON a.AuthorID = ia.AuthorID
    GROUP BY ic.ItemCopyID, title, itemType, ic.Status, cb.BranchName
    ORDER BY " . $orderClause;

$stmt = $pdo->query($sql);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Library Items</title>
    <link rel="stylesheet" href="style/style.css">
</head>
<body>
    <header class="site-header">
        <h1>Wayback Public Library</h1>
        <h4>All Library Items</h4>
        <nav class="library-nav" aria-label="Library tasks">
            <a href="index.php">Checkout</a>
            <a href="add-library-item.php">Add Library Item</a>
            <a href="return-item.php">Checkin</a>
            <a href="renewal.php">Renew</a>
            <a href="all-items.php" aria-current="page">All Items</a>
        </nav>
    </header>

    <main class="page-container library-page">
        <section class="library-panel">
            <div class="table-toolbar">
                <h3>Library Item Catalog</h3>

                <form method="get" class="sort-toolbar">
                    <label for="sort">Sort by</label>
                    <select id="sort" name="sort">
                        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Title</option>
                        <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>Status</option>
                    </select>

                    <label for="dir">Order</label>
                    <select id="dir" name="dir">
                        <option value="asc" <?php echo $direction === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                        <option value="desc" <?php echo $direction === 'desc' ? 'selected' : ''; ?>>Descending</option>
                    </select>

                    <button type="submit">Apply</button>
                </form>
            </div>

            <div class="table-container">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item Copy ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Current Branch</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr class="empty-row">
                                <td colspan="6">No items are available in the catalog.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)($item['ItemCopyID'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string)($item['title'] ?? 'Unknown')) ?></td>
                                    <td><?= htmlspecialchars((string)($item['author'] ?? 'Unknown')) ?></td>
                                    <td><?= htmlspecialchars((string)($item['itemType'] ?? 'Unknown')) ?></td>
                                    <td><?= htmlspecialchars((string)($item['Status'] ?? 'Unknown')) ?></td>
                                    <td><?= htmlspecialchars((string)($item['branchName'] ?? 'Unknown')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
