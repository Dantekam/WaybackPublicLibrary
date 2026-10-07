
<?php

// check form submitted
if ((isset($_POST["submit"])) && $_POST["submit"] == 'submit') {
    $errors = array();
    $missing = array();

    $patronID = trim($_POST["patronID"]);
    $itemCopyID = trim($_POST["itemCopyID"]);
    $checkoutDate = trim($_POST["checkoutDate"]);
    $dueDate = trim($_POST["dueDate"]);

    // Check required fields
    if (empty($patronID)) {
        $missing[] = "patronID";
    }

    if (empty($itemCopyID)) {
        $missing[] = "itemCopyID";
    }

    if (empty($checkoutDate)) {
        $missing[] = "checkoutDate";
    }

    if (empty($dueDate)) {
        $missing[] = "dueDate";
    }

    // Database connection
    try {
        require_once '../../pdo_connect.php';
        // Only insert if there are no missing fields
        if (!$missing) {

            // Check that the Patron exists
            $sql = "SELECT * FROM Patron WHERE PatronID = :patronID";
            $stmt = $dbc->prepare($sql);
            $stmt->bindParam(':patronID', $patronID);
            $stmt->execute();

            $numRows = $stmt->rowCount();

            if ($numRows == 0) {
                $errors['patron'] = "That patron does not exist.";
            }

            // Check that the Item Copy exists
            $sql = "SELECT * FROM ITEM_COPY WHERE ItemCopyID = :itemCopyID";
            $stmt = $dbc->prepare($sql);
            $stmt->bindParam(':itemCopyID', $itemCopyID);
            $stmt->execute();

            $numRows = $stmt->rowCount();

            if ($numRows == 0) {
                $errors['item'] = "That item copy does not exist.";
            }

            // Insert checkout if there are no errors
            if (!$errors) {

                $sql2 = "INSERT INTO CHECKOUT
                        (PatronID, ItemCopyID, CheckoutDate, DueDate)
                        VALUES (?, ?, ?, ?)";

                $stmt2 = $dbc->prepare($sql2);

                $stmt2->bindParam(1, $patronID);
                $stmt2->bindParam(2, $itemCopyID);
                $stmt2->bindParam(3, $checkoutDate);
                $stmt2->bindParam(4, $dueDate);

                $stmt2->execute();

                $numRows = $stmt2->rowCount();

                if ($numRows != 1) {
                    echo "<h2>Unable to process your request. Please try again later.</h2>";
                }
                else {
                    echo "<h2>Checkout successfully added!</h2>";
                }
            }
        }
    }
    catch (PDOException $e) {
        echo $e->getMessage();
    }
}
?>
<form action="./checkout.php" method="post">
    <?php
    if (!empty($missing)) {
        echo "<h3 class='warning'>Please correct item(s) indicated:</h3>";
    }
    ?>
    <?php
    if (isset($missing) && in_array("patronID", $missing)) {
        echo "<p class='warning'>Patron ID is required.</p>";
    }
    ?>
    <?php
    if (isset($errors['patron'])) {
        echo "<p class='warning'>{$errors['patron']}</p>";
    }
    ?>
    <p>
        <label for="patronID">
            Patron ID:
            <input type="number"
                   id="patronID"
                   name="patronID"
                   <?php
                   if (isset($patronID))
                       echo " value=" . htmlspecialchars($patronID);
                   ?>>
        </label>
    </p>
    <?php
    if (isset($missing) && in_array("itemCopyID", $missing)) {
        echo "<p class='warning'>Item Copy ID is required.</p>";
    }
    ?>
    <?php
    if (isset($errors['item'])) {
        echo "<p class='warning'>{$errors['item']}</p>";
    }
    ?>
    <p>
        <label for="itemCopyID">
            Item Copy ID:
            <input type="number"
                   id="itemCopyID"
                   name="itemCopyID"
                   <?php
                   if (isset($itemCopyID))
                       echo " value=" . htmlspecialchars($itemCopyID);
                   ?>>
        </label>
    </p>
    <?php
    if (isset($missing) && in_array("checkoutDate", $missing)) {
        echo "<p class='warning'>Checkout date is required.</p>";
    }
    ?>
    <p>
        <label for="checkoutDate">
            Checkout Date:
            <input type="date"
                   id="checkoutDate"
                   name="checkoutDate"
                   <?php
                   if (isset($checkoutDate))
                       echo " value=" . htmlspecialchars($checkoutDate);
                   ?>>
        </label>
    </p>
    <?php
    if (isset($missing) && in_array("dueDate", $missing)) {
        echo "<p class='warning'>Due date is required.</p>";
    }
    ?>
    <p>
        <label for="dueDate">
            Due Date:
            <input type="date"
                   id="dueDate"
                   name="dueDate"
                   <?php
                   if (isset($dueDate))
                       echo " value=" . htmlspecialchars($dueDate);
                   ?>>
        </label>
    </p>

    <input type="submit" id="submit" name="submit" value="submit">

</form>
