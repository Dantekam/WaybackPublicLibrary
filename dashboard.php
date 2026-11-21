<?php
require __DIR__ . '/script/db_connect.php';

$dashboardError = null;
$stats = [];
$dashboardSeries = [];
$itemStatuses = [];
$recentCheckouts = [];
$today = new DateTimeImmutable('today');
$currentMonthStart = $today->modify('first day of this month');
$previousMonthStart = $currentMonthStart->modify('-1 month');
$yearStart = $today->modify('-12 months');
$dataStart = $previousMonthStart < $yearStart
    ? $previousMonthStart
    : $yearStart;

try {
    $stats = $pdo->query(
        'SELECT
            (SELECT COUNT(*) FROM Patron) AS total_patrons,
            (SELECT COUNT(*) FROM Patron
             WHERE MembershipExpiration >= CURDATE())
                AS active_memberships,
            (SELECT COUNT(*) FROM Item_Copy) AS total_copies,
            (SELECT COUNT(*) FROM Item_Copy
             WHERE Status = "Available") AS available_copies,
            (SELECT COUNT(*) FROM CheckOut
             WHERE ReturnDate IS NULL) AS active_checkouts,
            (SELECT COUNT(*) FROM CheckOut
             WHERE ReturnDate IS NULL
               AND DueDate < CURDATE()) AS overdue_checkouts,
            (SELECT COUNT(*) FROM Reservation
             WHERE Status IN ("Pending", "Ready"))
                AS active_reservations'
    )->fetch();

    $rangeStart = $dataStart->format('Y-m-d');
    $rangeEnd = $today->modify('+1 day')->format('Y-m-d');
    $events = $pdo->prepare(
        'SELECT activity_date,
                SUM(copies_added) AS copies_added,
                SUM(checkouts_started) AS checkouts_started,
                SUM(copies_returned) AS copies_returned
         FROM (
            SELECT AcquisitionDate AS activity_date,
                   COUNT(*) AS copies_added,
                   0 AS checkouts_started,
                   0 AS copies_returned
            FROM Item_Copy
            WHERE AcquisitionDate >= ? AND AcquisitionDate < ?
            GROUP BY AcquisitionDate
            UNION ALL
            SELECT CheckOutDate AS activity_date,
                   0 AS copies_added,
                   COUNT(*) AS checkouts_started,
                   0 AS copies_returned
            FROM CheckOut
            WHERE CheckOutDate >= ? AND CheckOutDate < ?
            GROUP BY CheckOutDate
            UNION ALL
            SELECT ReturnDate AS activity_date,
                   0 AS copies_added,
                   0 AS checkouts_started,
                   COUNT(*) AS copies_returned
            FROM CheckOut
            WHERE ReturnDate >= ? AND ReturnDate < ?
            GROUP BY ReturnDate
         ) AS inventory_events
         GROUP BY activity_date'
    );
    $events->execute([
        $rangeStart, $rangeEnd,
        $rangeStart, $rangeEnd,
        $rangeStart, $rangeEnd
    ]);

    $eventsByDate = [];
    foreach ($events->fetchAll() as $event) {
        $eventsByDate[$event['activity_date']] = [
            'copies' => (int)$event['copies_added'],
            'checkouts' => (int)$event['checkouts_started'],
            'returns' => (int)$event['copies_returned']
        ];
    }

    $baseline = $pdo->prepare(
        'SELECT COUNT(*)
         FROM CheckOut
         WHERE CheckOutDate < ?
           AND (ReturnDate IS NULL OR ReturnDate >= ?)'
    );
    $baseline->execute([$rangeStart, $rangeStart]);
    $activeAtRangeStart = (int)$baseline->fetchColumn();

    $dailySeries = [];
    $activeCheckouts = $activeAtRangeStart;
    for (
        $date = $dataStart;
        $date <= $today;
        $date = $date->modify('+1 day')
    ) {
        $dateKey = $date->format('Y-m-d');
        $dailyEvents = $eventsByDate[$dateKey] ?? [
            'copies' => 0,
            'checkouts' => 0,
            'returns' => 0
        ];
        $activeCheckouts +=
            $dailyEvents['checkouts'] - $dailyEvents['returns'];

        $dailySeries[] = [
            'date' => $dateKey,
            'dayLabel' => $date->format('M j'),
            'monthKey' => $date->format('Y-m'),
            'monthLabel' => $date->format('M y'),
            'copies' => $dailyEvents['copies'],
            'checkouts' => $dailyEvents['checkouts'],
            'active' => $activeCheckouts
        ];
    }

    $currentMonthSeries = array_values(array_filter(
        $dailySeries,
        static fn($point) => $point['date'] >=
            $currentMonthStart->format('Y-m-d')
    ));
    $previousMonthSeries = array_values(array_filter(
        $dailySeries,
        static fn($point) => $point['date'] >=
            $previousMonthStart->format('Y-m-d')
            && $point['date'] < $currentMonthStart->format('Y-m-d')
    ));

    $yearByMonth = [];
    foreach ($dailySeries as $point) {
        if ($point['date'] < $yearStart->format('Y-m-d')) {
            continue;
        }
        $key = $point['monthKey'];
        if (!isset($yearByMonth[$key])) {
            $yearByMonth[$key] = [
                'label' => $point['monthLabel'],
                'copies' => 0,
                'checkouts' => 0,
                'active' => $point['active']
            ];
        }
        $yearByMonth[$key]['copies'] += $point['copies'];
        $yearByMonth[$key]['checkouts'] += $point['checkouts'];
        $yearByMonth[$key]['active'] = $point['active'];
    }

    $dashboardSeries = [
        'current' => [
            'label' => 'This month',
            'points' => array_map(
                static fn($point) => [
                    'label' => $point['dayLabel'],
                    'copies' => $point['copies'],
                    'checkouts' => $point['checkouts'],
                    'active' => $point['active']
                ],
                $currentMonthSeries
            )
        ],
        'previous' => [
            'label' => 'Last month',
            'points' => array_map(
                static fn($point) => [
                    'label' => $point['dayLabel'],
                    'copies' => $point['copies'],
                    'checkouts' => $point['checkouts'],
                    'active' => $point['active']
                ],
                $previousMonthSeries
            )
        ],
        'year' => [
            'label' => 'Last 12 months',
            'points' => array_values($yearByMonth)
        ]
    ];

    $itemStatuses = $pdo->query(
        'SELECT Status, COUNT(*) AS item_count
         FROM Item_Copy
         GROUP BY Status
         ORDER BY item_count DESC, Status ASC'
    )->fetchAll();
    $combinedStatuses = [];
    foreach ($itemStatuses as $itemStatus) {
        $statusKey = strtolower(
            preg_replace('/\s+/', '', trim($itemStatus['Status']))
        );
        if (!isset($combinedStatuses[$statusKey])) {
            $combinedStatuses[$statusKey] = [
                'Status' => $itemStatus['Status'],
                'item_count' => 0
            ];
        }
        $combinedStatuses[$statusKey]['item_count'] +=
            (int)$itemStatus['item_count'];
    }

    $statusLabels = [
        'available' => 'Available',
        'checkedout' => 'Checked out',
        'processing' => 'Processing',
        'reserved' => 'Reserved',
        'unavailable' => 'Unavailable',
        'unresolved' => 'Unresolved'
    ];
    foreach ($combinedStatuses as $statusKey => &$itemStatus) {
        $itemStatus['Status'] = $statusLabels[$statusKey]
            ?? ucwords(strtolower(trim($itemStatus['Status'])));
    }
    unset($itemStatus);
    $itemStatuses = array_values($combinedStatuses);
    usort(
        $itemStatuses,
        static fn($left, $right) =>
            $right['item_count'] <=> $left['item_count']
            ?: strcasecmp($left['Status'], $right['Status'])
    );

    $recentCheckouts = $pdo->query(
        'SELECT
            CONCAT(p.FirstName, " ", p.LastName) AS patron_name,
            COALESCE(i.Title, ic.Title, "Untitled item") AS item_title,
            c.CheckOutDate AS checkout_date,
            c.DueDate AS due_date,
            c.ReturnDate AS return_date
         FROM CheckOut c
         JOIN Patron p ON p.PatronID = c.PatronID
         JOIN Item_Copy ic ON ic.ItemCopyID = c.ItemCopyID
         LEFT JOIN ITEM i ON i.ItemID = ic.ItemID
         ORDER BY c.CheckOutDate DESC, c.CheckOutID DESC
         LIMIT 6'
    )->fetchAll();
} catch (Throwable $e) {
    error_log('dashboard: ' . $e->getMessage());
    $dashboardError = 'Dashboard statistics are temporarily unavailable.';
}

function dashboardEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$hasStats = $dashboardError === null && $stats !== [];
$totalCopies = $hasStats ? (int)$stats['total_copies'] : 0;
$statCards = [
    [
        'label' => 'Registered patrons',
        'key' => 'total_patrons',
        'note' => 'All accounts'
    ],
    [
        'label' => 'Active memberships',
        'key' => 'active_memberships',
        'note' => 'In good standing'
    ],
    [
        'label' => 'Catalog copies',
        'key' => 'total_copies',
        'note' => 'In the collection'
    ],
    [
        'label' => 'Available now',
        'key' => 'available_copies',
        'note' => 'Ready to borrow'
    ],
    [
        'label' => 'Active checkouts',
        'key' => 'active_checkouts',
        'note' => 'Currently on loan'
    ],
    [
        'label' => 'Overdue loans',
        'key' => 'overdue_checkouts',
        'note' => 'Past their due date'
    ],
    [
        'label' => 'Open reservations',
        'key' => 'active_reservations',
        'note' => 'Pending or ready'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Wayback Public Library</title>
    <link rel="stylesheet" href="style/style.css">
</head>
<body>
<header class="site-header">
    <h1>Wayback Public Library</h1>
    <h4>Dashboard</h4>
    <?php require __DIR__ . '/includes/navigation.php'; ?>
</header>

<main class="page-container dashboard-page">
    <?php if ($dashboardError !== null): ?>
        <p class="dashboard-error" role="alert">
            <?= dashboardEscape($dashboardError) ?>
        </p>
    <?php endif; ?>

    <section class="dashboard-panel dashboard-overview" aria-labelledby="dashboard-title">
        <div class="dashboard-panel-heading">
            <div>
                <p class="dashboard-eyebrow">LIBRARY OVERVIEW</p>
                <h2 id="dashboard-title">Library at a glance</h2>
            </div>
            <p class="dashboard-date"><?= dashboardEscape(date('l, F j, Y')) ?></p>
        </div>
        <div class="dashboard-stats" aria-label="Library statistics">
            <?php foreach ($statCards as $card): ?>
                <article class="dashboard-stat">
                    <p class="dashboard-stat-label"><?= dashboardEscape($card['label']) ?></p>
                    <p class="dashboard-stat-value">
                        <?= $hasStats
                            ? number_format((int)$stats[$card['key']])
                            : '--' ?>
                    </p>
                    <p class="dashboard-stat-note"><?= dashboardEscape($card['note']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="dashboard-content">
        <section class="dashboard-panel dashboard-collection" aria-labelledby="collection-title">
            <div class="dashboard-panel-heading">
                <div>
                    <p class="dashboard-eyebrow">COLLECTION</p>
                    <h2 id="collection-title">Library activity over time</h2>
                </div>
                <label class="dashboard-range-label" for="dashboard-range">
                    Show
                    <select id="dashboard-range">
                        <option value="current">This month</option>
                        <option value="previous">Last month</option>
                        <option value="year" selected>Last 12 months</option>
                    </select>
                </label>
            </div>

            <?php if ($dashboardError !== null): ?>
                <p class="dashboard-empty">Library activity history is unavailable.</p>
            <?php elseif ($totalCopies === 0): ?>
                <p class="dashboard-empty">No items have been added to the collection yet.</p>
            <?php else: ?>
                <p class="dashboard-chart-note">
                    Monthly views show daily counts; the 12-month view shows monthly totals. Active checkouts are end-of-day counts (month-end in the 12-month view).
                </p>
                <div class="dashboard-small-multiples" id="dashboard-charts">
                    <article class="dashboard-mini-chart" data-series="copies">
                        <h3>Copies added</h3>
                        <svg viewBox="0 0 360 180" role="img" aria-label="Copies added over time"></svg>
                    </article>
                    <article class="dashboard-mini-chart" data-series="checkouts">
                        <h3>Checkouts started</h3>
                        <svg viewBox="0 0 360 180" role="img" aria-label="Checkouts started over time"></svg>
                    </article>
                    <article class="dashboard-mini-chart" data-series="active">
                        <h3>Active checkouts</h3>
                        <svg viewBox="0 0 360 180" role="img" aria-label="Active checkouts over time"></svg>
                    </article>
                </div>
                <script type="application/json" id="dashboard-series"><?= json_encode(
                    $dashboardSeries,
                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
                ) ?></script>

                <section class="dashboard-status-breakdown" aria-labelledby="inventory-status-title">
                    <div class="dashboard-panel-heading">
                        <div>
                            <p class="dashboard-eyebrow">CURRENT INVENTORY</p>
                            <h3 id="inventory-status-title">Copies by status</h3>
                        </div>
                        <span class="dashboard-panel-count">
                            <?= number_format($totalCopies) ?> copies
                        </span>
                    </div>
                    <?php if ($itemStatuses === []): ?>
                        <p class="dashboard-empty">No inventory statuses to show.</p>
                    <?php else: ?>
                        <ul class="dashboard-status-list">
                            <?php foreach ($itemStatuses as $itemStatus): ?>
                                <?php
                                $count = (int)$itemStatus['item_count'];
                                $percentage = $totalCopies > 0
                                    ? (int)round($count / $totalCopies * 100)
                                    : 0;
                                ?>
                                <li class="dashboard-status-item">
                                    <div class="dashboard-status-label">
                                        <span><?= dashboardEscape($itemStatus['Status']) ?></span>
                                        <span><?= number_format($count) ?> <small>(<?= $percentage ?>%)</small></span>
                                    </div>
                                    <div class="dashboard-status-track">
                                        <span style="width: <?= $percentage ?>%"></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </section>

        <section class="dashboard-panel dashboard-recent" aria-labelledby="recent-title">
            <div class="dashboard-panel-heading">
                <div>
                    <p class="dashboard-eyebrow">CIRCULATION</p>
                    <h2 id="recent-title">Recent checkouts</h2>
                </div>
                <a class="dashboard-text-link" href="index.php">Go to checkout</a>
            </div>

            <?php if ($recentCheckouts === [] && $dashboardError === null): ?>
                <p class="dashboard-empty">No checkouts have been recorded yet.</p>
            <?php elseif ($recentCheckouts !== []): ?>
                <div class="dashboard-table-wrap">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th scope="col">Patron</th>
                                <th scope="col">Item</th>
                                <th scope="col">Due date</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentCheckouts as $checkout): ?>
                                <?php $isReturned = $checkout['return_date'] !== null; ?>
                                <tr>
                                    <td><?= dashboardEscape($checkout['patron_name']) ?></td>
                                    <td><?= dashboardEscape($checkout['item_title']) ?></td>
                                    <td><?= dashboardEscape($checkout['due_date'] ?: 'Not set') ?></td>
                                    <td>
                                        <span class="dashboard-loan-status<?= $isReturned ? ' is-returned' : '' ?>">
                                            <?= $isReturned ? 'Returned' : 'On loan' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <nav class="dashboard-actions" aria-label="Quick library tasks">
        <h2>Quick tasks</h2>
        <a href="index.php">Check out an item <span aria-hidden="true">&rarr;</span></a>
        <a href="add-library-item.php">Add to the collection <span aria-hidden="true">&rarr;</span></a>
        <a href="checkin.php">Check in an item <span aria-hidden="true">&rarr;</span></a>
        <a href="reservations.php">Manage reservations <span aria-hidden="true">&rarr;</span></a>
    </nav>
</main>
<script src="js/dashboard.js"></script>
</body>
</html>
