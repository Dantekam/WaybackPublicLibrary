
<?php
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

$navigation = [
    'index.php' => 'Checkout',
    'add-library-item.php' => 'Add Library Item',
    'checkin.php' => 'Check-in',
    'renewal.php' => 'Renewals',
    'statuses.php' => 'Item Statuses'
];
?>
<nav class="library-nav" aria-label="Library tasks">
    <?php foreach ($navigation as $page => $label): ?>
        <a href="<?= htmlspecialchars($page) ?>"
           <?= $currentPage === $page ? 'aria-current="page"' : '' ?>>
            <?= htmlspecialchars($label) ?>
        </a>
    <?php endforeach; ?>
</nav>
