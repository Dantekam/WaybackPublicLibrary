<?php
require __DIR__ . '/script/db_connect.php';

$dashboardError = null;
$stats = [];
$dashboardSeries = [];
$emptyPeriodStats = [
    'copies' => 0,
    'checkouts' => 0,
    'returns' => 0,
    'active' => 0,
    'inventory' => 0
];
$dashboardOverviewPeriods = array_fill_keys(
    ['week', 'current', 'year', 'older', 'all'],
    $emptyPeriodStats
);
$emptyFineStats = [
    'assessed' => 0.0,
    'paid' => 0.0,
    'outstanding' => 0.0,
    'active' => 0,
    'patrons' => []
];
$dashboardFinePeriods = array_fill_keys(
    ['week', 'current', 'year', 'older', 'all'],
    $emptyFineStats
);
$itemStatuses = [];
$recentCheckouts = [];
$reportFines = [];
$reportCheckouts = [];
$today = new DateTimeImmutable('today');
$currentMonthStart = $today->modify('first day of this month');
$weekStart = $today->modify('-6 days');
$yearStart = $currentMonthStart->modify('-11 months');
$olderPeriodStart = $currentMonthStart->modify('-23 months');
$periodEnd = $today->modify('+1 day');
$allTimeStart = $today;

try {
    $firstActivityDate = $pdo->query(
        'SELECT MIN(activity_date)
         FROM (
            SELECT AcquisitionDate AS activity_date
            FROM Item_Copy
            WHERE AcquisitionDate IS NOT NULL
            UNION ALL
            SELECT CheckOutDate AS activity_date
            FROM CheckOut
            UNION ALL
            SELECT ReturnDate AS activity_date
            FROM CheckOut
            WHERE ReturnDate IS NOT NULL
         ) AS activity_dates'
    )->fetchColumn();
    $allTimeStart = $firstActivityDate
        ? new DateTimeImmutable(substr($firstActivityDate, 0, 10))
        : $today;

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

    $fineRanges = [
        'week' => [$weekStart, $periodEnd],
        'current' => [$currentMonthStart, $periodEnd],
        'year' => [$yearStart, $periodEnd],
        'older' => [$olderPeriodStart, $yearStart],
        'all' => [$allTimeStart, $periodEnd]
    ];
    $fineRows = $pdo->query(
        'SELECT f.PatronID,
                f.Amount,
                f.AmountPaid,
                COALESCE(c.ReturnDate, c.CheckOutDate) AS fine_date,
                CONCAT(p.FirstName, " ", p.LastName) AS patron_name,
                COALESCE(i.Title, ic.Title, "Untitled item") AS item_title,
                c.CheckOutDate AS checkout_date
         FROM Fine f
         LEFT JOIN Patron p ON p.PatronID = f.PatronID
         LEFT JOIN CheckOut c ON c.CheckOutID = f.CheckoutID
         LEFT JOIN Item_Copy ic ON ic.ItemCopyID = c.ItemCopyID
         LEFT JOIN ITEM i ON i.ItemID = ic.ItemID'
    )->fetchAll();

    foreach ($fineRows as $fine) {
        $amount = (float)($fine['Amount'] ?? 0);
        $amountPaid = (float)($fine['AmountPaid'] ?? 0);
        $fineDate = $fine['fine_date'] === null
            ? ''
            : substr($fine['fine_date'], 0, 10);
        $reportFines[] = [
            'date' => $fineDate,
            'patron' => $fine['patron_name'] ?? 'Unknown patron',
            'item' => $fine['item_title'] ?? 'Untitled item',
            'checkoutDate' => $fine['checkout_date'] ?? '',
            'assessed' => $amount,
            'paid' => $amountPaid,
            'outstanding' => max(0, $amount - $amountPaid)
        ];

        if ($fine['fine_date'] === null) {
            continue;
        }

        $fineDate = substr($fine['fine_date'], 0, 10);
        $outstanding = max(0, $amount - $amountPaid);

        foreach ($fineRanges as $period => [$start, $end]) {
            if (
                $fineDate < $start->format('Y-m-d') ||
                $fineDate >= $end->format('Y-m-d')
            ) {
                continue;
            }

            $dashboardFinePeriods[$period]['assessed'] += $amount;
            $dashboardFinePeriods[$period]['paid'] += $amountPaid;
            $dashboardFinePeriods[$period]['outstanding'] += $outstanding;
            if ($outstanding > 0) {
                $dashboardFinePeriods[$period]['active']++;
                $dashboardFinePeriods[$period]['patrons'][(string)$fine['PatronID']] = true;
            }
        }
    }

    foreach ($dashboardFinePeriods as &$periodFines) {
        $periodFines['patrons'] = count($periodFines['patrons']);
    }
    unset($periodFines);

    $dataStart = $allTimeStart < $olderPeriodStart
        ? $allTimeStart
        : $olderPeriodStart;

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

    $inventoryBaseline = $pdo->prepare(
        'SELECT COUNT(*)
         FROM Item_Copy
         WHERE AcquisitionDate IS NULL OR AcquisitionDate < ?'
    );
    $inventoryBaseline->execute([$rangeStart]);
    $copiesAtRangeStart = (int)$inventoryBaseline->fetchColumn();

    $dailySeries = [];
    $activeCheckouts = $activeAtRangeStart;
    $inventoryTotal = $copiesAtRangeStart;
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
        $inventoryTotal += $dailyEvents['copies'];

        $dailySeries[] = [
            'date' => $dateKey,
            'dayLabel' => $date->format('M j'),
            'monthKey' => $date->format('Y-m'),
            'monthLabel' => $date->format('M y'),
            'copies' => $dailyEvents['copies'],
            'checkouts' => $dailyEvents['checkouts'],
            'returns' => $dailyEvents['returns'],
            'transactions' =>
                $dailyEvents['checkouts'] + $dailyEvents['returns'],
            'active' => $activeCheckouts,
            'inventory' => $inventoryTotal
        ];
    }

    $currentMonthSeries = array_values(array_filter(
        $dailySeries,
        static fn($point) => $point['date'] >=
            $currentMonthStart->format('Y-m-d')
    ));
    $yearSeries = array_values(array_filter(
        $dailySeries,
        static fn($point) => $point['date'] >= $yearStart->format('Y-m-d')
    ));
    $olderPeriodSeries = array_values(array_filter(
        $dailySeries,
        static fn($point) =>
            $point['date'] >= $olderPeriodStart->format('Y-m-d') &&
            $point['date'] < $yearStart->format('Y-m-d')
    ));
    $weekSeries = array_values(array_filter(
        $dailySeries,
        static fn($point) => $point['date'] >= $weekStart->format('Y-m-d')
    ));

    $summarizePeriod = static function (array $points): array {
        $summary = [
            'copies' => 0,
            'checkouts' => 0,
            'returns' => 0,
            'active' => 0,
            'inventory' => 0
        ];
        foreach ($points as $point) {
            $summary['copies'] += $point['copies'];
            $summary['checkouts'] += $point['checkouts'];
            $summary['returns'] += $point['returns'];
            $summary['active'] = $point['active'];
            $summary['inventory'] = $point['inventory'];
        }
        return $summary;
    };
    $dashboardOverviewPeriods = [
        'week' => $summarizePeriod($weekSeries),
        'current' => $summarizePeriod($currentMonthSeries),
        'year' => $summarizePeriod($yearSeries),
        'older' => $summarizePeriod($olderPeriodSeries),
        'all' => $summarizePeriod($dailySeries)
    ];

    $yearByMonth = [];
    $olderPeriodByMonth = [];
    $allTimeByMonth = [];
    foreach ($dailySeries as $point) {
        $key = $point['monthKey'];
        if (!isset($allTimeByMonth[$key])) {
            $allTimeByMonth[$key] = [
                'label' => $point['monthLabel'],
                'copies' => 0,
                'checkouts' => 0,
                'transactions' => 0,
                'active' => $point['active'],
                'inventory' => $point['inventory']
            ];
        }
        $allTimeByMonth[$key]['copies'] += $point['copies'];
        $allTimeByMonth[$key]['checkouts'] += $point['checkouts'];
        $allTimeByMonth[$key]['transactions'] += $point['transactions'];
        $allTimeByMonth[$key]['active'] = $point['active'];
        $allTimeByMonth[$key]['inventory'] = $point['inventory'];

        if ($point['date'] >= $yearStart->format('Y-m-d')) {
            if (!isset($yearByMonth[$key])) {
                $yearByMonth[$key] = [
                    'label' => $point['monthLabel'],
                    'copies' => 0,
                    'checkouts' => 0,
                    'transactions' => 0,
                    'active' => $point['active'],
                    'inventory' => $point['inventory']
                ];
            }
            $yearByMonth[$key]['copies'] += $point['copies'];
            $yearByMonth[$key]['checkouts'] += $point['checkouts'];
            $yearByMonth[$key]['transactions'] += $point['transactions'];
            $yearByMonth[$key]['active'] = $point['active'];
            $yearByMonth[$key]['inventory'] = $point['inventory'];
        } elseif ($point['date'] >= $olderPeriodStart->format('Y-m-d')) {
            if (!isset($olderPeriodByMonth[$key])) {
                $olderPeriodByMonth[$key] = [
                    'label' => $point['monthLabel'],
                    'copies' => 0,
                    'checkouts' => 0,
                    'transactions' => 0,
                    'active' => $point['active'],
                    'inventory' => $point['inventory']
                ];
            }
            $olderPeriodByMonth[$key]['copies'] += $point['copies'];
            $olderPeriodByMonth[$key]['checkouts'] += $point['checkouts'];
            $olderPeriodByMonth[$key]['transactions'] += $point['transactions'];
            $olderPeriodByMonth[$key]['active'] = $point['active'];
            $olderPeriodByMonth[$key]['inventory'] = $point['inventory'];
        }
    }

    $dashboardSeries = [
        'current' => [
            'label' => 'This month',
            'points' => array_map(
                static fn($point) => [
                    'label' => $point['dayLabel'],
                    'copies' => $point['copies'],
                    'checkouts' => $point['checkouts'],
                    'transactions' => $point['transactions'],
                    'inventory' => $point['inventory'],
                    'active' => $point['active']
                ],
                $currentMonthSeries
            )
        ],
        'week' => [
            'label' => 'Last 7 days',
            'points' => array_map(
                static fn($point) => [
                    'label' => $point['dayLabel'],
                    'copies' => $point['copies'],
                    'checkouts' => $point['checkouts'],
                    'transactions' => $point['transactions'],
                    'inventory' => $point['inventory'],
                    'active' => $point['active']
                ],
                $weekSeries
            )
        ],
        'year' => [
            'label' => 'Last 12 months',
            'points' => array_values($yearByMonth)
        ],
        'older' => [
            'label' => '13-24 months ago',
            'points' => array_values($olderPeriodByMonth)
        ],
        'all' => [
            'label' => 'All time',
            'points' => array_values($allTimeByMonth)
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

    $reportCheckouts = $pdo->query(
        'SELECT
            CONCAT(p.FirstName, " ", p.LastName) AS patron_name,
            COALESCE(i.Title, ic.Title, "Untitled item") AS item_title,
            COALESCE(i.ItemType, ic.ItemType, "Media") AS item_type,
            c.CheckOutDate AS checkout_date,
            c.DueDate AS due_date,
            c.ReturnDate AS return_date,
            COALESCE(f.outstanding, 0) AS outstanding_fine
         FROM CheckOut c
         JOIN Patron p ON p.PatronID = c.PatronID
         JOIN Item_Copy ic ON ic.ItemCopyID = c.ItemCopyID
         LEFT JOIN ITEM i ON i.ItemID = ic.ItemID
         LEFT JOIN (
            SELECT CheckoutID,
                   SUM(GREATEST(
                       COALESCE(Amount, 0) - COALESCE(AmountPaid, 0),
                       0
                   )) AS outstanding
            FROM Fine
            GROUP BY CheckoutID
         ) f ON f.CheckoutID = c.CheckOutID
         ORDER BY c.CheckOutDate DESC, c.CheckOutID DESC'
    )->fetchAll();
    $recentCheckouts = array_slice($reportCheckouts, 0, 6);
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
$periodStatCards = [
    ['label' => 'Copies added', 'key' => 'copies', 'note' => 'During selected period'],
    ['label' => 'Checkouts', 'key' => 'checkouts', 'note' => 'Started in selected period'],
    ['label' => 'Returns', 'key' => 'returns', 'note' => 'During selected period'],
    ['label' => 'Active checkouts', 'key' => 'active', 'note' => 'At period end'],
    ['label' => 'Inventory total', 'key' => 'inventory', 'note' => 'At period end']
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

    <section class="dashboard-filter-bar" aria-labelledby="dashboard-filters-title">
        <h2 id="dashboard-filters-title">Dashboard filters</h2>
        <label class="dashboard-range-label" for="dashboard-range">
            Show
            <select id="dashboard-range">
                <option value="week">Last 7 days</option>
                <option value="current">This month</option>
                <option value="year" selected>Last 12 months</option>
                <option value="older">13-24 months ago</option>
                <option value="all">All time</option>
            </select>
        </label>
        <button class="dashboard-report-button" id="open-dashboard-report" type="button">
            Report options
        </button>
        <p class="dashboard-report-status" id="dashboard-report-status" role="status"
           aria-live="polite"></p>
    </section>

    <dialog class="dashboard-report-dialog" id="dashboard-report-dialog"
            aria-labelledby="dashboard-report-title">
        <div class="dashboard-panel-heading">
            <div>
                <p class="dashboard-eyebrow">REPORT OPTIONS</p>
                <h2 id="dashboard-report-title">Generate a report</h2>
            </div>
        </div>
        <form id="dashboard-report-form">
            <label class="dashboard-report-field" for="dashboard-report-type">
                Include
                <select id="dashboard-report-type" name="type">
                    <option value="overdue">Overdue items</option>
                    <option value="upcoming">Upcoming due (next 3 days)</option>
                </select>
            </label>
            <label class="dashboard-report-field" for="dashboard-report-format">
                File format
                <select id="dashboard-report-format" name="format">
                    <option value="txt">TXT</option>
                    <option value="docx">DOCX</option>
                    <option value="pdf">PDF</option>
                </select>
            </label>
            <p class="dashboard-report-period">
                Report dates: <strong id="dashboard-report-period"></strong>
            </p>
            <div class="dashboard-report-form-actions">
                <button class="dashboard-report-cancel" id="cancel-dashboard-report"
                        type="button">Cancel</button>
                <button class="dashboard-report-button" type="submit">
                    Generate report
                </button>
            </div>
        </form>
        <script type="application/json" id="dashboard-report-data"><?= json_encode(
            [
                'fines' => $reportFines,
                'checkouts' => $reportCheckouts,
                'today' => $today->format('Y-m-d'),
                'upcomingEnd' => $today->modify('+3 days')->format('Y-m-d'),
                'ranges' => [
                    'week' => [$weekStart->format('Y-m-d'), $periodEnd->format('Y-m-d')],
                    'current' => [$currentMonthStart->format('Y-m-d'), $periodEnd->format('Y-m-d')],
                    'year' => [$yearStart->format('Y-m-d'), $periodEnd->format('Y-m-d')],
                    'older' => [$olderPeriodStart->format('Y-m-d'), $yearStart->format('Y-m-d')],
                    'all' => [$allTimeStart->format('Y-m-d'), $periodEnd->format('Y-m-d')]
                ]
            ],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        ) ?></script>
    </dialog>

    <section class="dashboard-panel dashboard-overview" aria-labelledby="dashboard-title">
        <div class="dashboard-panel-heading">
            <div>
                <p class="dashboard-eyebrow">LIBRARY OVERVIEW</p>
                <h2 id="dashboard-title">Library at a glance</h2>
            </div>
        </div>
        <div class="dashboard-period-summary">
            <h3 id="dashboard-period-title">Last 12 months</h3>
            <div class="dashboard-stats" aria-labelledby="dashboard-period-title"
                 aria-live="polite">
                <?php foreach ($periodStatCards as $card): ?>
                    <article class="dashboard-stat">
                        <p class="dashboard-stat-label"><?= dashboardEscape($card['label']) ?></p>
                        <p class="dashboard-stat-value"
                           data-overview-stat="<?= dashboardEscape($card['key']) ?>">
                            <?= $hasStats
                                ? number_format((int)$dashboardOverviewPeriods['year'][$card['key']])
                                : '--' ?>
                        </p>
                        <p class="dashboard-stat-note"><?= dashboardEscape($card['note']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <script type="application/json" id="dashboard-overview-series"><?= json_encode(
            [
                'week' => ['label' => 'Last 7 days', 'stats' => $dashboardOverviewPeriods['week']],
                'current' => ['label' => 'This month', 'stats' => $dashboardOverviewPeriods['current']],
                'year' => ['label' => 'Last 12 months', 'stats' => $dashboardOverviewPeriods['year']],
                'older' => ['label' => '13-24 months ago', 'stats' => $dashboardOverviewPeriods['older']],
                'all' => ['label' => 'All time', 'stats' => $dashboardOverviewPeriods['all']],
                'fines' => $dashboardFinePeriods
            ],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        ) ?></script>
    </section>

    <section class="dashboard-panel dashboard-fines" aria-labelledby="fines-title">
        <div class="dashboard-panel-heading">
            <div>
                <p class="dashboard-eyebrow">FINES</p>
                <h2 id="fines-title">Fine summary</h2>
            </div>
        </div>
        <p class="dashboard-chart-note" id="fines-period-note">
            Fines are grouped by return date (checkout date if not returned). Fines without a linked checkout date are excluded.
        </p>
        <div class="dashboard-stats" aria-label="Fine statistics">
            <article class="dashboard-stat">
                <p class="dashboard-stat-label">Outstanding balance</p>
                <p class="dashboard-stat-value" data-fine-stat="outstanding">
                    <?= $hasStats
                        ? '$' . number_format($dashboardFinePeriods['year']['outstanding'], 2)
                        : '--' ?>
                </p>
                <p class="dashboard-stat-note">Unpaid fine amounts in this period</p>
            </article>
            <article class="dashboard-stat">
                <p class="dashboard-stat-label">Total assessed</p>
                <p class="dashboard-stat-value" data-fine-stat="assessed">
                    <?= $hasStats
                        ? '$' . number_format($dashboardFinePeriods['year']['assessed'], 2)
                        : '--' ?>
                </p>
                <p class="dashboard-stat-note">Fine amounts assessed in this period</p>
            </article>
            <article class="dashboard-stat">
                <p class="dashboard-stat-label">Amount collected</p>
                <p class="dashboard-stat-value" data-fine-stat="paid">
                    <?= $hasStats
                        ? '$' . number_format($dashboardFinePeriods['year']['paid'], 2)
                        : '--' ?>
                </p>
                <p class="dashboard-stat-note">Paid on fines assessed in this period</p>
            </article>
            <article class="dashboard-stat">
                <p class="dashboard-stat-label">Active fines</p>
                <p class="dashboard-stat-value" data-fine-stat="active">
                    <?= $hasStats
                        ? number_format($dashboardFinePeriods['year']['active'])
                        : '--' ?>
                </p>
                <p class="dashboard-stat-note">With an unpaid balance</p>
            </article>
            <article class="dashboard-stat">
                <p class="dashboard-stat-label">Patrons with balances</p>
                <p class="dashboard-stat-value" data-fine-stat="patrons">
                    <?= $hasStats
                        ? number_format($dashboardFinePeriods['year']['patrons'])
                        : '--' ?>
                </p>
                <p class="dashboard-stat-note">Owe fines in this period</p>
            </article>
        </div>
    </section>

    <div class="dashboard-content">
        <section class="dashboard-panel dashboard-collection" aria-labelledby="collection-title">
            <div class="dashboard-panel-heading">
                <div>
                    <p class="dashboard-eyebrow">COLLECTION</p>
                    <h2 id="collection-title">Library activity over time</h2>
                </div>
            </div>

            <?php if ($dashboardError !== null): ?>
                <p class="dashboard-empty">Library activity history is unavailable.</p>
            <?php elseif ($totalCopies === 0): ?>
                <p class="dashboard-empty">No items have been added to the collection yet.</p>
            <?php else: ?>
                <p class="dashboard-chart-note">
                    The 7-day and month views show daily counts; the two 12-month views show monthly totals. Transactions include checkouts and returns. Inventory totals are cumulative; active checkouts and inventory are end-of-day counts.
                </p>
                <div class="dashboard-small-multiples" id="dashboard-charts">
                    <article class="dashboard-mini-chart" data-series="copies">
                        <h3>Copies added</h3>
                        <svg viewBox="0 0 360 180" role="img" aria-label="Copies added over time"></svg>
                    </article>
                    <article class="dashboard-mini-chart" data-series="transactions">
                        <h3>Transactions over time</h3>
                        <svg viewBox="0 0 360 180" role="img" aria-label="Checkout and return transactions over time"></svg>
                    </article>
                    <article class="dashboard-mini-chart" data-series="inventory">
                        <h3>Inventory totals</h3>
                        <svg viewBox="0 0 360 180" role="img" aria-label="Cumulative inventory totals over time"></svg>
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
                <a class="dashboard-text-link" href="index.php?page=checkout">Go to checkout</a>
            </div>

            <?php if ($dashboardError !== null): ?>
                <p class="dashboard-empty">Checkout history is unavailable.</p>
            <?php else: ?>
                <p class="dashboard-empty" id="recent-checkouts-empty"
                   <?= $recentCheckouts !== [] ? 'hidden' : '' ?>>
                    No checkouts for this period.
                </p>
                <div class="dashboard-table-wrap" id="recent-checkouts-wrap"
                     <?= $recentCheckouts === [] ? 'hidden' : '' ?>>
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th scope="col">Patron</th>
                                <th scope="col">Item</th>
                                <th scope="col">Due date</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody id="recent-checkouts-body">
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
        <a href="index.php?page=checkout">Check out an item <span aria-hidden="true">&rarr;</span></a>
        <a href="add-library-item.php">Add to the collection <span aria-hidden="true">&rarr;</span></a>
        <a href="checkin.php">Check in an item <span aria-hidden="true">&rarr;</span></a>
        <a href="reservations.php">Manage reservations <span aria-hidden="true">&rarr;</span></a>
    </nav>
</main>
<script src="js/dashboard.js"></script>
<footer class="site-footer" aria-hidden="true"></footer>
</body>
</html>
