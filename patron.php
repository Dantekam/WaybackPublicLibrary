<?php
require __DIR__ . '/script/db_connect.php';

// -----------------------------------------------------------------
// Which tab is active? 'patron' (default) or 'item'
// -----------------------------------------------------------------
$type = $_GET['type'] ?? 'patron';
if (!in_array($type, ['patron', 'item'], true)) $type = 'patron';

// -----------------------------------------------------------------
// Search input + filters
// -----------------------------------------------------------------
$q           = trim($_GET['q'] ?? '');
$filterFines  = $_GET['fines']  ?? '';   // '' | 'yes' | 'no'
$filterStatus = $_GET['status'] ?? '';   // patron: ''|active|expired  item: ''|Available|CheckedOut|…
$filterMedia  = $_GET['media']  ?? '';   // item type

// -----------------------------------------------------------------
// State
// -----------------------------------------------------------------
$patronId  = 0;
$patron    = null;
$fines     = [];
$checkouts = [];
$matches   = [];
$items     = [];
$message   = '';
$error     = '';

// -----------------------------------------------------------------
// Load item types up front (used by the item-tab dropdown)
// -----------------------------------------------------------------
$itemTypes = [];
try {
    $itemTypes = $pdo->query(
        'SELECT DISTINCT ItemType FROM ITEM
         WHERE ItemType IS NOT NULL AND ItemType <> ""
         ORDER BY ItemType'
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    error_log('item types: ' . $e->getMessage());
}

// -----------------------------------------------------------------
// Resolve search term FIRST so POST handlers can use $patronId.
// -----------------------------------------------------------------
if ($q !== '' && $type === 'patron') {
    try {
        if (ctype_digit($q)) {
            $patronId = (int)$q;
        } else {
            $like = '%' . addcslashes($q, '%_\\') . '%';

            $sql = 'SELECT p.PatronID, p.FirstName, p.LastName,
                           p.MembershipExpiration
                    FROM Patron p
                    WHERE (p.FirstName LIKE ?
                        OR p.LastName  LIKE ?
                        OR CONCAT(p.FirstName, " ", p.LastName) LIKE ?)';
            $params = [$like, $like, $like];

            if ($filterFines === 'yes') {
                $sql .= ' AND EXISTS (
                            SELECT 1 FROM Fine f
                            WHERE f.PatronID = p.PatronID
                              AND f.AmountPaid < f.Amount
                        )';
            } elseif ($filterFines === 'no') {
                $sql .= ' AND NOT EXISTS (
                            SELECT 1 FROM Fine f
                            WHERE f.PatronID = p.PatronID
                              AND f.AmountPaid < f.Amount
                        )';
            }

            if ($filterStatus === 'active') {
                $sql .= ' AND p.MembershipExpiration >= CURDATE()';
            } elseif ($filterStatus === 'expired') {
                $sql .= ' AND (p.MembershipExpiration IS NULL
                            OR p.MembershipExpiration < CURDATE())';
            }

            $sql .= ' ORDER BY p.LastName, p.FirstName LIMIT 25';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $matches = $stmt->fetchAll();

            if (count($matches) === 1) {
                $patronId = (int)$matches[0]['PatronID'];
                $matches  = [];
            } elseif (!$matches) {
                $error = 'No patrons match those criteria.';
            }
        }
    } catch (Throwable $e) {
        error_log('patron search: ' . $e->getMessage());
        $error = 'Search failed. Please try again.';
    }

} elseif ($q !== '' && $type === 'item') {
    try {
        $like = '%' . addcslashes($q, '%_\\') . '%';

        // Author is pulled from Item_Author so we don't depend on
        // an ITEM.Author column existing.
        $sql = 'SELECT i.ItemID, i.Title, i.ItemType,
                       (SELECT GROUP_CONCAT(
                                   DISTINCT CONCAT(a.FirstName, " ", a.LastName)
                                   ORDER BY a.LastName, a.FirstName
                                   SEPARATOR ", ")
                        FROM Item_Author ia
                        JOIN Author a ON a.AuthorID = ia.AuthorID
                        WHERE ia.ItemID = i.ItemID) AS Author,
                       (SELECT COUNT(*) FROM Item_Copy ic
                        WHERE ic.ItemID = i.ItemID) AS copyCount
                FROM ITEM i
                WHERE (i.Title LIKE ? OR i.ItemID = ?)';
        $params = [$like, ctype_digit($q) ? (int)$q : -1];

        if ($filterStatus !== '') {
            $sql .= ' AND EXISTS (
                        SELECT 1 FROM Item_Copy ic
                        WHERE ic.ItemID = i.ItemID
                          AND ic.Status = ?
                      )';
            $params[] = $filterStatus;
        }

        if ($filterMedia !== '') {
            $sql .= ' AND i.ItemType = ?';
            $params[] = $filterMedia;
        }

        $sql .= ' ORDER BY i.Title LIMIT 50';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        if (!$items) $error = 'No items match those criteria.';
    } catch (Throwable $e) {
        error_log('item search: ' . $e->getMessage());
        $error = 'Search failed. Please try again.';
    }
}

// -----------------------------------------------------------------
// POST handlers: pay one fine, pay all, renew membership
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $patronId > 0) {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'pay_fine') {
            $fineId = (int)($_POST['fine_id'] ?? 0);
            $amount = round((float)($_POST['amount'] ?? 0), 2);

            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT FineID, Amount, AmountPaid FROM Fine
                 WHERE FineID = ? AND PatronID = ? FOR UPDATE'
            );
            $stmt->execute([$fineId, $patronId]);
            $fine = $stmt->fetch();

            if (!$fine) {
                $pdo->rollBack();
                $error = 'Fine not found for this patron.';
            } else {
                $remaining = (float)$fine['Amount'] - (float)$fine['AmountPaid'];
                if ($amount <= 0 || $amount > $remaining + 0.001) {
                    $pdo->rollBack();
                    $error = sprintf('Payment must be between $0.01 and $%.2f.', $remaining);
                } else {
                    $pdo->prepare('UPDATE Fine SET AmountPaid = AmountPaid + ? WHERE FineID = ?')
                        ->execute([$amount, $fineId]);
                    $message = sprintf('Paid $%.2f.', $amount);
                    $pdo->commit();
                }
            }
        } elseif ($action === 'pay_all_fines') {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT FineID, Amount, AmountPaid FROM Fine
                 WHERE PatronID = ? AND AmountPaid < Amount FOR UPDATE'
            );
            $stmt->execute([$patronId]);
            $rows = $stmt->fetchAll();

            $total = 0.0;
            $upd = $pdo->prepare('UPDATE Fine SET AmountPaid = Amount WHERE FineID = ?');
            foreach ($rows as $r) {
                $total += (float)$r['Amount'] - (float)$r['AmountPaid'];
                $upd->execute([$r['FineID']]);
            }
            $pdo->commit();
            $message = sprintf('Collected $%.2f across %d fine(s).', $total, count($rows));
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('patron action: ' . $e->getMessage());
        $error = 'Action failed. Please try again.';
    }
}

// -----------------------------------------------------------------
// Load patron + fines + checkouts (patron tab only)
// -----------------------------------------------------------------
if ($patronId > 0 && $error === '') {
    try {
        $stmt = $pdo->prepare(
            'SELECT PatronID, FirstName, LastName, MembershipExpiration,
                    MembershipRenewalCount
             FROM Patron WHERE PatronID = ?'
        );
        $stmt->execute([$patronId]);
        $patron = $stmt->fetch() ?: null;

        if ($patron) {
            $stmt = $pdo->prepare(
                'SELECT f.FineID, f.Amount, f.AmountPaid,
                        COALESCE(i.Title, ic.Title, "Untitled item") AS item_title
                 FROM Fine f
                 LEFT JOIN CheckOut c   ON c.CheckOutID = f.CheckoutID
                 LEFT JOIN Item_Copy ic ON ic.ItemCopyID = c.ItemCopyID
                 LEFT JOIN ITEM i       ON i.ItemID     = ic.ItemID
                 WHERE f.PatronID = ?
                 ORDER BY f.FineID DESC'
            );
            $stmt->execute([$patronId]);
            foreach ($stmt->fetchAll() as $r) {
                $a = (float)$r['Amount'];
                $p = (float)$r['AmountPaid'];
                $fines[] = [
                    'id'       => (int)$r['FineID'],
                    'item'     => $r['item_title'],
                    'assessed' => $a,
                    'paid'     => $p,
                    'balance'  => round(max(0, $a - $p), 2),
                ];
            }

            $stmt = $pdo->prepare(
                'SELECT c.ItemCopyID, c.CheckOutDate, c.DueDate,
                        COALESCE(i.Title, ic.Title, "Untitled item") AS item_title
                 FROM CheckOut c
                 JOIN Item_Copy ic ON ic.ItemCopyID = c.ItemCopyID
                 LEFT JOIN ITEM i  ON i.ItemID      = ic.ItemID
                 WHERE c.PatronID = ? AND c.ReturnDate IS NULL
                 ORDER BY c.DueDate ASC'
            );
            $stmt->execute([$patronId]);
            $checkouts = $stmt->fetchAll();
        } else {
            $error = 'No patron with that ID.';
        }
    } catch (Throwable $e) {
        error_log('patron load: ' . $e->getMessage());
        $error = 'Could not load patron record.';
    }
}

// Derived values for the view
$totalBalance = 0.0;
foreach ($fines as $f) $totalBalance += $f['balance'];
$openFines = array_values(array_filter($fines, fn($f) => $f['balance'] > 0));

$expired = $patron && $patron['MembershipExpiration']
    && new DateTimeImmutable($patron['MembershipExpiration']) < new DateTimeImmutable('today');

$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

// Tab URLs preserve current query but swap type
$tabBase = $_GET;
unset($tabBase['type']);
$patronTabUrl = '?' . http_build_query(array_merge($tabBase, ['type' => 'patron']));
$itemTabUrl   = '?' . http_build_query(array_merge($tabBase, ['type' => 'item']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patron &amp; Item Search - Wayback Public Library</title>
    <link rel="stylesheet" href="style/style.css">
</head>
<body>
<header class="site-header">
    <h1>Wayback Public Library</h1>
    <h4>Search</h4>
    <?php require __DIR__ . '/includes/navigation.php'; ?>
</header>

<main class="page-container patron-page">

    <section class="patron-search-panel">
        <div class="patron-panel-heading">
            <div>
                <p class="dashboard-eyebrow">SEARCH</p>
                <h2><?= $type === 'patron' ? 'Find a patron' : 'Find an item' ?></h2>
            </div>
        </div>

        <nav class="search-tabs" role="tablist" aria-label="Search type">
            <a href="<?= $esc($patronTabUrl) ?>"
               role="tab"
               aria-selected="<?= $type === 'patron' ? 'true' : 'false' ?>"
               class="search-tab<?= $type === 'patron' ? ' is-active' : '' ?>">
                Patrons
            </a>
            <a href="<?= $esc($itemTabUrl) ?>"
               role="tab"
               aria-selected="<?= $type === 'item' ? 'true' : 'false' ?>"
               class="search-tab<?= $type === 'item' ? ' is-active' : '' ?>">
                Items
            </a>
        </nav>

        <form class="patron-search-form" id="patron-search-form" method="get">
            <input type="hidden" name="type" value="<?= $esc($type) ?>">

            <div>
                <label for="q">
                    <?= $type === 'patron' ? 'Patron ID or name' : 'Title or Item ID' ?>
                </label>
                <input id="q" name="q" type="text"
                       value="<?= $esc($q) ?>"
                       placeholder="<?= $type === 'patron' ? 'e.g. 42 or Jane Doe' : 'e.g. Dune or 1234' ?>"
                       required>
            </div>

            <?php if ($type === 'patron'): ?>
                <div>
                    <label for="fines">Fines</label>
                    <select id="fines" name="fines">
                        <option value=""    <?= $filterFines === ''    ? 'selected' : '' ?>>All patrons</option>
                        <option value="yes" <?= $filterFines === 'yes' ? 'selected' : '' ?>>Has unpaid fines</option>
                        <option value="no"  <?= $filterFines === 'no'  ? 'selected' : '' ?>>No unpaid fines</option>
                    </select>
                </div>
                <div>
                    <label for="status">Membership</label>
                    <select id="status" name="status">
                        <option value=""        <?= $filterStatus === ''        ? 'selected' : '' ?>>All memberships</option>
                        <option value="active"  <?= $filterStatus === 'active'  ? 'selected' : '' ?>>Active only</option>
                        <option value="expired" <?= $filterStatus === 'expired' ? 'selected' : '' ?>>Expired only</option>
                    </select>
                </div>
            <?php else: ?>
                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value=""           <?= $filterStatus === ''           ? 'selected' : '' ?>>All statuses</option>
                        <option value="Available"  <?= $filterStatus === 'Available'  ? 'selected' : '' ?>>Available</option>
                        <option value="CheckedOut" <?= $filterStatus === 'CheckedOut' ? 'selected' : '' ?>>Checked out</option>
                        <option value="Reserved"   <?= $filterStatus === 'Reserved'   ? 'selected' : '' ?>>Reserved</option>
                        <option value="Processing" <?= $filterStatus === 'Processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="Maintenance"<?= $filterStatus === 'Maintenance'? 'selected' : '' ?>>Maintenance</option>
                    </select>
                </div>
                <div>
                    <label for="media">Type</label>
                    <select id="media" name="media">
                        <option value="" <?= $filterMedia === '' ? 'selected' : '' ?>>All types</option>
                        <?php foreach ($itemTypes as $t): ?>
                            <option value="<?= $esc($t) ?>"
                                <?= $filterMedia === $t ? 'selected' : '' ?>>
                                <?= $esc($t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <button type="submit">Search</button>
        </form>

        <?php if ($message): ?>
            <p class="patron-message" role="status"><?= $esc($message) ?></p>
        <?php elseif ($error): ?>
            <p class="patron-message is-error" role="alert"><?= $esc($error) ?></p>
        <?php elseif ($q === ''): ?>
            <p class="patron-message" role="status">
                <?= $type === 'patron'
                    ? 'Enter a Patron ID or name to view the record.'
                    : 'Enter a title or Item ID to search the catalog.' ?>
            </p>
        <?php endif; ?>
    </section>

    <?php if ($type === 'patron' && $matches): ?>
    <section class="patron-record">
        <div class="patron-record-heading">
            <div>
                <p class="dashboard-eyebrow">SEARCH RESULTS</p>
                <h2><?= count($matches) ?> patrons match &ldquo;<?= $esc($q) ?>&rdquo;</h2>
                <p class="patron-id-line">Select a patron to open their record.</p>
            </div>
        </div>
        <div class="patron-table-wrap">
            <table class="patron-table">
                <thead>
                    <tr>
                        <th scope="col">Patron ID</th>
                        <th scope="col">Name</th>
                        <th scope="col">Membership expires</th>
                        <th scope="col"><span class="visually-hidden">Action</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matches as $m): ?>
                        <tr>
                            <td><?= (int)$m['PatronID'] ?></td>
                            <td><?= $esc(trim($m['FirstName'] . ' ' . $m['LastName'])) ?></td>
                            <td><?= $esc($m['MembershipExpiration'] ?: '—') ?></td>
                            <td>
                                <a class="patron-action-button"
                                   href="?type=patron&amp;q=<?= (int)$m['PatronID'] ?>">Open</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($type === 'item' && $items): ?>
    <section class="patron-record">
        <div class="patron-record-heading">
            <div>
                <p class="dashboard-eyebrow">CATALOG RESULTS</p>
                <h2><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?> found</h2>
                <p class="patron-id-line">
                    Filters: status = <?= $esc($filterStatus ?: 'all') ?>,
                    type = <?= $esc($filterMedia ?: 'all') ?>
                </p>
            </div>
        </div>
        <div class="patron-table-wrap">
            <table class="patron-table">
                <thead>
                    <tr>
                        <th scope="col">Item ID</th>
                        <th scope="col">Title</th>
                        <th scope="col">Author</th>
                        <th scope="col">Type</th>
                        <th scope="col">Copies</th>
                        <th scope="col"><span class="visually-hidden">Action</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= (int)$it['ItemID'] ?></td>
                            <td><?= $esc($it['Title']) ?></td>
                            <td><?= $esc($it['Author'] ?: '—') ?></td>
                            <td><?= $esc($it['ItemType'] ?: '—') ?></td>
                            <td><?= (int)$it['copyCount'] ?></td>
                            <td>
                                <a class="patron-action-button"
                                   href="statuses.php?search=<?= (int)$it['ItemID'] ?>">
                                    View copies
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($patron): ?>
    <section class="patron-record">
        <div class="patron-record-heading">
            <div>
                <p class="dashboard-eyebrow">PATRON RECORD</p>
                <h2><?= $esc(trim($patron['FirstName'] . ' ' . $patron['LastName'])) ?></h2>
                <p class="patron-id-line">Patron ID <?= (int)$patron['PatronID'] ?></p>
            </div>
            <span class="patron-status<?= $expired ? ' is-blocked' : '' ?>">
                <?= $expired ? 'Expired' : 'Active' ?>
            </span>
        </div>

        <div class="patron-summary-grid">
            <article class="patron-summary-item">
                <p>Membership</p>
                <strong><?= $expired ? 'Expired' : 'Active' ?></strong>
                <small>Expires <?= $esc($patron['MembershipExpiration'] ?: '—') ?></small>
            </article>
            <article class="patron-summary-item">
                <p>Active checkouts</p>
                <strong><?= count($checkouts) ?></strong>
                <small>Items currently on loan</small>
            </article>
            <article class="patron-summary-item">
                <p>Outstanding fines</p>
                <strong<?= $totalBalance > 0 ? ' class="is-error"' : '' ?>>
                    $<?= number_format($totalBalance, 2) ?>
                </strong>
                <small>Unpaid balance</small>
            </article>
        </div>

        <section class="patron-loans-section">
            <div class="patron-panel-heading">
                <div>
                    <p class="dashboard-eyebrow">CIRCULATION</p>
                    <h2>Active checkouts</h2>
                </div>
                <span class="patron-loan-count"><?= count($checkouts) ?> items</span>
            </div>
            <?php if (!$checkouts): ?>
                <p class="patron-empty">This patron has no active checkouts.</p>
            <?php else: ?>
                <div class="patron-table-wrap">
                    <table class="patron-table">
                        <thead>
                            <tr>
                                <th scope="col">Item</th>
                                <th scope="col">Copy ID</th>
                                <th scope="col">Checked out</th>
                                <th scope="col">Due date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checkouts as $c): ?>
                                <tr>
                                    <td><?= $esc($c['item_title']) ?></td>
                                    <td><?= (int)$c['ItemCopyID'] ?></td>
                                    <td><?= $esc($c['CheckOutDate'] ?: '—') ?></td>
                                    <td><?= $esc($c['DueDate'] ?: '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="patron-loans-section">
            <div class="patron-panel-heading">
                <div>
                    <p class="dashboard-eyebrow">FINES</p>
                    <h2>Outstanding fines</h2>
                </div>
                <span class="patron-loan-count">$<?= number_format($totalBalance, 2) ?></span>
            </div>

            <?php if (!$openFines): ?>
                <p class="patron-empty">This patron has no outstanding fines.</p>
            <?php else: ?>
                <div class="patron-table-wrap">
                    <table class="patron-table">
                        <thead>
                            <tr>
                                <th scope="col">Item</th>
                                <th scope="col">Assessed</th>
                                <th scope="col">Paid</th>
                                <th scope="col">Balance</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($openFines as $f): ?>
                                <tr>
                                    <td><?= $esc($f['item']) ?></td>
                                    <td>$<?= number_format($f['assessed'], 2) ?></td>
                                    <td>$<?= number_format($f['paid'], 2) ?></td>
                                    <td>$<?= number_format($f['balance'], 2) ?></td>
                                    <td>
                                        <form method="post" class="patron-inline-form"
                                              onsubmit="return confirm('Pay the full remaining balance?')">
                                            <input type="hidden" name="type" value="patron">
                                            <input type="hidden" name="q" value="<?= $esc($q) ?>">
                                            <input type="hidden" name="action" value="pay_fine">
                                            <input type="hidden" name="fine_id" value="<?= $f['id'] ?>">
                                            <input type="hidden" name="amount" value="<?= number_format($f['balance'], 2, '.', '') ?>">
                                            <button type="submit" class="patron-action-button">
                                                Pay $<?= number_format($f['balance'], 2) ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <form method="post" class="patron-form-actions"
                      onsubmit="return confirm('Mark all fines as paid in full?')">
                    <input type="hidden" name="type" value="patron">
                    <input type="hidden" name="q" value="<?= $esc($q) ?>">
                    <input type="hidden" name="action" value="pay_all_fines">
                    <button type="submit" class="patron-action-button">Pay all fines</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="patron-loans-section">
            <div class="patron-panel-heading">
                <div>
                    <p class="dashboard-eyebrow">MEMBERSHIP</p>
                    <h2>Membership actions</h2>
                </div>
                <span class="patron-loan-count">
                    Renewed <?= (int)$patron['MembershipRenewalCount'] ?> time(s)
                </span>
            </div>
            <form class="patron-membership-actions" onsubmit="return false;">
                <label>
                    Renew for
                    <select id="patron-renew-months" name="months">
                        <option value="1">1 month</option>
                        <option value="3">3 months</option>
                        <option value="6">6 months</option>
                        <option value="12" selected>12 months</option>
                        <option value="24">24 months</option>
                    </select>
                </label>
                <button type="button"
                        id="renew-membership-btn"
                        class="patron-action-button"
                        data-patron-id="<?= (int)$patron['PatronID'] ?>"
                        data-last-renewed-on="<?= htmlspecialchars(
                            $patron['MembershipLastRenewedOn']
                                ? substr($patron['MembershipLastRenewedOn'], 0, 10)
                                : '',
                            ENT_QUOTES
                        ) ?>">
                    <?= $expired ? 'Re-establish membership' : 'Renew membership' ?>
                </button>
            </form>
            <p class="patron-message" id="patron-action-message" role="status" aria-live="polite"></p>
        </section>
    </section>
    <?php endif; ?>
</main>
<footer class="site-footer" aria-hidden="true"></footer>
<script src="js/patron.js"></script>
</body>
</html>