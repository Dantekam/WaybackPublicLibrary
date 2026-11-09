
<?php
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

$navigation = [
    'index.php' => 'Checkout',
    'add-library-item.php' => 'Add Library Item',
    'checkin.php' => 'Check-in',
    'renewal.php' => 'Renewals',
    'reservations.php' => 'Reservations',
    'statuses.php' => 'Item Statuses'
];
?>
<nav class="library-nav" aria-label="Library tasks">
    <?php foreach ($navigation as $page => $label): ?>
        <a href="<?= htmlspecialchars(
            $page, ENT_QUOTES, 'UTF-8'
        ) ?>"
           <?= $currentPage === $page
               ? 'aria-current="page"'
               : '' ?>>
            <?= htmlspecialchars(
                $label, ENT_QUOTES, 'UTF-8'
            ) ?>
        </a>
    <?php endforeach; ?>
</nav>
