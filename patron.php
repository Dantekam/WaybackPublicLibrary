<?php
require __DIR__ . '/script/db_connect.php';

$patronId = isset($_GET['patronID']) ? (int)$_GET['patronID'] : 0;
$patron   = null;
$fines    = [];
$checkouts = [];
$message  = '';
$error    = '';

// ---------------------------------------------------------------
// POST handlers: pay one fine, pay all, renew membership
// ---------------------------------------------------------------
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
        } elseif ($action === 'renew_membership') {
            $months = max(1, min(60, (int)($_POST['months'] ?? 12)));

            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT MembershipExpiration FROM Patron
                 WHERE PatronID = ? FOR UPDATE'
            );
            $stmt->execute([$patronId]);
            $old = $stmt->fetchColumn();

            $today = new DateTimeImmutable('today');
            $base  = $old && new DateTimeImmutable($old) > $today
                ? new DateTimeImmutable($old)
                : $today;
            $new = $base->modify("+{$months} months")->format('Y-m-d');

            $pdo->prepare(
                'UPDATE Patron
                 SET MembershipExpiration = ?,
                     MembershipRenewalCount = MembershipRenewalCount + 1
                 WHERE PatronID = ?'
            )->execute([$new, $patronId]);
            $pdo->commit();
            $message = "Membership now expires {$new}.";
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('patron action: ' . $e->getMessage());
        $error = 'Action failed. Please try again.';
    }
}

// ---------------------------------------------------------------
// Load patron + fines + checkouts
// ---------------------------------------------------------------
if ($patronId > 0) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patron Record - Wayback Public Library</title>
    <link rel="stylesheet" href="style/style.css">
</head>
<body>
<header class="site-header">
    <h1>Wayback Public Library</h1>
    <h4>Patron Record</h4>
    <?php require __DIR__ . '/includes/navigation.php'; ?>
</header>

<main class="page-container patron-page">

    <section class="patron-search-panel">
        <div class="patron-panel-heading">
            <div>
                <p class="dashboard-eyebrow">PATRON SERVICES</p>
                <h2>Find a patron</h2>
            </div>
        </div>
        <form class="patron-search-form" method="get">
            <div>
                <label for="patron-id">Patron ID</label>
                <input id="patron-id" name="patronID" type="number" min="1"
                       value="<?= $patronId ?: '' ?>" required>
            </div>
            <button type="submit">Find patron</button>
        </form>
        <?php if ($message): ?>
            <p class="patron-message" role="status"><?= $esc($message) ?></p>
        <?php elseif ($error): ?>
            <p class="patron-message is-error" role="alert"><?= $esc($error) ?></p>
        <?php else: ?>
            <p class="patron-message" role="status">Enter a Patron ID to view the record.</p>
        <?php endif; ?>
    </section>

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
            <form method="post" class="patron-membership-actions">
                <input type="hidden" name="action" value="renew_membership">
                <label>
                    Renew for
                    <select name="months">
                        <option value="1">1 month</option>
                        <option value="3">3 months</option>
                        <option value="6">6 months</option>
                        <option value="12" selected>12 months</option>
                        <option value="24">24 months</option>
                    </select>
                </label>
                <button type="submit" class="patron-action-button">
                    <?= $expired ? 'Re-establish membership' : 'Renew membership' ?>
                </button>
            </form>
        </section>
    </section>
    <?php endif; ?>
</main>
<footer class="site-footer" aria-hidden="true"></footer>
</body>
</html>