<?php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wayback Public Library - Checkin</title>
    <link rel="stylesheet" href="style/style.css">
    <script src="js/checkin.js" defer></script>
</head>

<body>
    <header class="site-header">
        <h1>Wayback Public Library</h1>
        <h4>Checkin</h4>
        <?php require __DIR__ . '/includes/navigation.php'; ?>
    </header>

    <main class="page-container library-page">
        <div class="main-layout">
            <section class="library-panel lookup-card">
                <h3>Check In Item</h3>

                <form id="checkinLookupForm">
                    <label for="itemCopyID">Item Copy ID</label>
                    <div class="input-row">
                        <input type="number" id="itemCopyID" name="itemCopyID" min="1" placeholder="Enter Item Copy ID" aria-describedby="unscannableHelp" required>
                        <button type="submit" id="findCheckinItem">Find Item</button>
                    </div>
                    <p id="unscannableHelp" class="library-note">
                        Barcode unreadable? Select manual identification and enter the Item Copy ID printed on the item.
                    </p>
                    <label class="library-checkbox" for="unscannableMode">
                        <input type="checkbox" id="unscannableMode">
                        Barcode cannot be scanned
                    </label>
                </form>

                <div class="result-box" id="checkinResult">
                    <p><strong>Patron:</strong> <span data-field="patronName">--</span></p>
                    <p><strong>Author:</strong> <span data-field="author">--</span></p>
                    <p><strong>Title:</strong> <span data-field="title">--</span></p>
                    <p><strong>Item Copy ID:</strong> <span data-field="itemCopyID">--</span></p>
                    <p><strong>Item Type:</strong> <span data-field="itemType">--</span></p>
                    <p><strong>Current Branch:</strong> <span data-field="branchName">--</span></p>
                    <p><strong>Due Date:</strong> <span data-field="dueDate">--</span></p>
                    <p><strong>Status:</strong> <span data-field="status">--</span></p>
                    <p class="msg error" data-field="error" hidden></p>
                    <p class="msg" data-field="message" aria-live="polite" hidden></p>
                </div>
            </section>

            <aside class="checkout-card sidebar-wrapper">
                <div class="checkout-summary">
                    <h3>Checkin Summary</h3>
                    <p><strong>Item Copy ID:</strong> <span data-field="summaryCopyID">--</span></p>
                    <p><strong>Due Date:</strong> <span data-field="summaryDueDate">--</span></p>
                    <p><strong>Checkin Date:</strong> <span data-field="summaryCheckinDate">--</span></p>
                    <p><strong>Days Overdue:</strong> <span data-field="summaryDaysOverdue">--</span></p>
                    <p><strong>Fine Assessed:</strong> <span data-field="summaryFine">--</span></p>
                </div>

                <form id="checkinForm">
                    <input type="hidden" name="itemCopyID" value="">
                    <label for="returnBranchID">Return Branch</label>
                    <div class="input-row">
                        <select id="returnBranchID" name="returnBranchID" required>
                            <option value="">Select a branch</option>
                        </select>
                    </div>
                    <div class="library-actions">
                        <button type="submit" id="checkinLibraryItem" class="primary-button">Record Return</button>
                    </div>
                </form>
            </aside>
        </div>
    </main>
</body>

</html>