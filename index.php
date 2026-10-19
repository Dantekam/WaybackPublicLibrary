<?php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wayback Public Library - Checkout</title>
    <link rel="stylesheet" href="style/style.css">
</head>

<body>

    <header class="site-header">
        <h1>Wayback Public Library</h1>
    </header>

    <main class="page-container">

        <section class="page-heading">
            <h2>Checkout a Library Item</h2>
            <button id="addItemBtn" type="button">Add Item</button>
        </section>

        <section class="lookup-container">

            <!-- ===== Find Patron ===== -->
            <div class="lookup-card">
                <h3>Find Patron</h3>

                <form id="patronForm">
                    <label for="patronID">Patron ID</label>

                    <div class="input-row">
                        <input
                            type="number"
                            id="patronID"
                            name="patronID"
                            min="1"
                            placeholder="Enter Patron ID"
                            required
                        >

                        <button type="submit">Find Patron</button>
                    </div>
                </form>

                <div class="result-box" id="patronResult">
                    <p>
                        <strong>Name:</strong>
                        <span data-field="name">--</span>
                    </p>

                    <p>
                        <strong>Membership Expiration:</strong>
                        <span data-field="expiration">--</span>
                    </p>
                    <p class="error" data-field="error" hidden></p>
                </div>
            </div>

            <!-- ===== Find Item ===== -->
            <div class="lookup-card">
                <h3>Find Item</h3>

                <form id="itemForm">
                    <label for="itemCopyID">Item Copy ID</label>

                    <div class="input-row">
                        <input
                            type="number"
                            id="itemCopyID"
                            name="itemCopyID"
                            min="1000"
                            placeholder="Enter Item Copy ID"
                            required
                        >

                        <button type="submit">
                            Find Item
                        </button>
                    </div>
                </form>

                <div class="result-box" id="itemResult">
                    <p>
                        <strong>Item Copy ID:</strong>
                        <span data-field="itemCopyID">--</span>
                    </p>

                    <p>
                        <strong>Item Type:</strong>
                        <span data-field="itemType">--</span>
                    </p>

                    <p>
                        <strong>Status:</strong>
                        <span data-field="status">--</span>
                    </p>
                    <p class="error" data-field="error" hidden></p>
                </div>
            </div>

        </section>

        <!-- ===== Checkout Summary ===== -->
        <section class="checkout-card">

            <div class="checkout-summary">
                <h3>Checkout Summary</h3>

                <p>
                    <strong>Selected Patron:</strong>
                    <span data-field="patronName">--</span>
                </p>

                <p>
                    <strong>Item Copy ID:</strong>
                    <span data-field="itemCopyID">--</span>
                </p>

                <p>
                    <strong>Item Type:</strong>
                    <span data-field="itemType">--</span>
                </p>
                <p class="error" data-field="error" hidden></p>
            </div>

            <form id="checkoutForm">

                <input type="hidden" name="patronID" value="">
                <input type="hidden" name="itemCopyID" value="">

                <button class="checkout-button" type="submit">Record Checkout</button>

            </form>

        </section>

        <!-- ===== Confirmation ===== -->
        <section class="confirmation-card" id="confirmationCard" hidden>

            <h3>Checkout Recorded</h3>

            <div class="confirmation-details">

                <p>
                    <strong>Checkout ID:</strong>
                    <span data-field="checkoutID">--</span>
                </p>

                <p>
                    <strong>Patron:</strong>
                    <span data-field="patron">--</span>
                </p>

                <p>
                    <strong>Item Copy ID:</strong>
                    <span data-field="itemCopyID">--</span>
                </p>

                <p>
                    <strong>Checkout Date:</strong>
                    <span data-field="checkoutDate">--</span>
                </p>

                <p>
                    <strong>Due Date:</strong>
                    <span data-field="dueDate">--</span>
                </p>
                <p class="error" data-field="error" hidden></p>

            </div>

            <button type="button" id="doneButton">Done</button>

        </section>
        
        <!-- ===== Current Checkouts Table ===== -->
        <section class="current-checkouts">

            <div class="table-heading">
                <h2>Current Checkouts</h2>
            </div>

            <div class="table-container">

                <table>

                    <thead>
                        <tr>
                            <th>Checkout ID</th>
                            <th>Patron</th>
                            <th>Item Copy ID</th>
                            <th>Item Type</th>
                            <th>Checkout Date</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>

                    <tbody>

                        <tr class="empty-row">
                            <td colspan="6">
                                Current checkout records will appear here.
                            </td>
                        </tr>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

    <!-- ===== Add Item ===== -->
    <div id="addItemModal" class="modal" hidden>
        <div class="modal-content">
            <h3>Add Item Copy</h3>
            <form id="addItemForm">
                <label>
                    Item Copy ID
                    <input type="number" name="itemCopyID" required min="1000" step="1">
                </label>
                <label>
                    Title
                    <input type="text" name="title" required maxlength="50">
                </label>
                <label>
                    Item ID
                    <input type="number" name="itemID" required min="3000" step="1">
                </label>
                <label>
                    Branch ID
                    <input type="number" name="branchID" required min="1000" step="1">
                </label>
                <label>
                    Shelf location
                    <input type="text" name="shelfLocation" required maxlength="50">
                </label>
                <label>
                    Item Type
                    <input type="text" name="itemType" required maxlength="50">
                </label>
                <label>
                    Status
                    <select name="status">
                        <option value="Open" selected>Open</option>
                        <option value="CheckedOut">Checked Out</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Available">Available</option>
                    </select>
                </label>
                <p id="addItemMsg" class="msg"></p>
                <div class="modal-actions">
                    <button type="button" id="cancelAddItem">Cancel</button>
                    <button type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/addItem.js" defer></script>
    <script src="js/checkout.js" defer></script>

</body>

</html>
