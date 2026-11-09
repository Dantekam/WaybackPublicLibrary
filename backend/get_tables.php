<?php
// file to get the tables using multiple join queries
try {
    require_once '../../pdo_connect.php';

    //sql statement
    $sql = "SELECT 
                Patron.PatronID,
                Patron.FirstName,
                Patron.LastName,
                ITEM_COPY.ItemCopyID,
                ITEM_COPY.Title,
                ITEM_COPY.ItemType,
                ITEM_COPY.Status,
                CHECKOUT.CheckoutID,
                CHECKOUT.CheckoutDate,
                CHECKOUT.DueDate
            FROM CHECKOUT
            INNER JOIN Patron
                ON CHECKOUT.PatronID = Patron.PatronID
            INNER JOIN ITEM_COPY
                ON CHECKOUT.ItemCopyID = ITEM_COPY.ItemCopyID";

    $result = $dbc->query($sql);

}
catch (PDOException $e) {
    echo $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Checkouts</title>
    <link rel="stylesheet" href="../style/style.css">
</head>

<body>

<header class="site-header">
    <h1>Library Checkouts</h1>
</header>

<main class="page-container">
<section class="current-checkouts">
<div class="table-container">
<table>
    <tr>
        <th>Patron ID</th>
        <th>Patron Name</th>
        <th>Item Copy ID</th>
        <th>Title</th>
        <th>Item Type</th>
        <th>Status</th>
        <th>Checkout Date</th>
        <th>Due Date</th>
    </tr>

    <?php foreach ($result as $row) { ?>
        <tr>
            <td><?php echo $row['PatronID']; ?></td>
            <td>
                <?php 
                    echo $row['FirstName'] . " " . $row['LastName']; 
                ?>
            </td>
            <td><?php echo $row['ItemCopyID']; ?></td>
            <td><?php echo $row['Title']; ?></td>
            <td><?php echo $row['ItemType']; ?></td>
            <td><?php echo $row['Status']; ?></td>
            <td><?php echo $row['CheckoutDate']; ?></td>
            <td><?php echo $row['DueDate']; ?></td>
        </tr>
    <?php } ?>

</table>
</div>
</section>
</main>

</body>
</html>