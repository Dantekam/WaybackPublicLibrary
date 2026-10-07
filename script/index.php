<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ------------------------------------------------------------
// 1. INITIALIZE VARIABLES
// ------------------------------------------------------------
$searchTerm      = '';
$searchPerformed = false;
$results         = [];
$dbError         = '';

// ------------------------------------------------------------
// 2. DATABASE CONNECTION
// ------------------------------------------------------------
$host       = 'localhost';
$dbname     = 'project3';
$username   = 'project3';
$dbPassword = require __DIR__ . '/.db_pass.php';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $dbPassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    $dbError = "Database connection failed: " . $e->getMessage();
}

// ------------------------------------------------------------
// 3. HANDLE THE SEARCH  (only if $pdo exists)
// ------------------------------------------------------------
if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $searchTerm      = trim($_GET['search']);
    $searchPerformed = true;

    if (isset($pdo)) {
        $sql = "SELECT
                    CONCAT(p.FirstName, ' ', p.LastName) AS patron,
                    p.MembershipExpiration              AS membership_expiration,
                    ic.ItemType                         AS item_type,
                    ic.Status                           AS item_status,
                    c.CheckOutDate                      AS checkout_date,
                    c.DueDate                           AS due_date
                FROM Patron p
                JOIN CheckOut  c  ON p.PatronID   = c.PatronID
                JOIN Item_Copy ic ON c.ItemCopyID = ic.ItemCopyID
                WHERE p.FirstName LIKE :term
                   OR p.LastName  LIKE :term
                   OR CONCAT(p.FirstName, ' ', p.LastName) LIKE :term
                ORDER BY p.LastName ASC, p.FirstName ASC, c.CheckOutDate DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':term' => '%' . $searchTerm . '%']);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patron Checkout Search - Wayback Public Library</title>
</head>
<body>

    <header>
        <p>Wayback Public Library</p>
    </header>

    <form action="index.php" method="GET">
        <label for="search">Search Patrons by Name:</label>
        <input type="text"
               id="search"
               name="search"
               placeholder="Enter a patron's first or last name..."
               value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>"
               required>
        <button type="submit">Search</button>
    </form>

    <hr>

    <?php if (!empty($dbError)): ?>
        <p style="color:red;"><?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if ($searchPerformed): ?>
        <h2>Results for "<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>"</h2>

        <?php if (!empty($results)): ?>
            <table border="1" cellpadding="8">
                <thead>
                    <tr>
                        <th>Patron</th>
                        <th>Membership Expiration</th>
                        <th>Item Type</th>
                        <th>Item Status</th>
                        <th>Checkout Date</th>
                        <th>Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['patron'],                ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['membership_expiration'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['item_type'],             ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['item_status'],           ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['checkout_date'],         ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['due_date'] ?? 'N/A',     ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-results">No patrons matched your search query.</p>
        <?php endif; ?>
    <?php endif; ?>

    <div class="back-link">
        <a href="../index.html">&larr; Back to Home</a>
    </div>

</body>
</html>