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
<html>
<head>
    <title>Library Checkouts</title>
</head>

<body>

<h1>Library Checkouts</h1>

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

</body>
</html>