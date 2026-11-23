<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patron Record - Wayback Public Library</title>
    <link rel="stylesheet" href="style/style.css">
    <script src="js/patron.js" defer></script>
</head>
<body>
<header class="site-header">
    <h1>Wayback Public Library</h1>
    <h4>Patron Record</h4>
    <?php require __DIR__ . '/includes/navigation.php'; ?>
</header>

<main class="page-container patron-page">
    <section class="patron-search-panel" aria-labelledby="patron-search-title">
        <div class="patron-panel-heading">
            <div>
                <p class="dashboard-eyebrow">PATRON SERVICES</p>
                <h2 id="patron-search-title">Find a patron</h2>
            </div>
        </div>
        <form class="patron-search-form" id="patron-search-form">
            <div>
                <label for="patron-id">Patron ID</label>
                <input id="patron-id" name="patronID" type="number" min="1"
                       inputmode="numeric" required>
            </div>
            <button type="submit">Find patron</button>
        </form>
        <p class="patron-message" id="patron-message" role="status"
           aria-live="polite">Enter a Patron ID to view the record.</p>
    </section>

    <section class="patron-record" id="patron-record" hidden
             aria-labelledby="patron-record-title">
        <div class="patron-record-heading">
            <div>
                <p class="dashboard-eyebrow">PATRON RECORD</p>
                <h2 id="patron-record-title"><span id="patron-name"></span></h2>
                <p class="patron-id-line">Patron ID <span id="patron-id-value"></span></p>
            </div>
            <span class="patron-status" id="patron-eligibility"></span>
        </div>

        <div class="patron-summary-grid" aria-label="Patron summary">
            <article class="patron-summary-item">
                <p>Membership</p>
                <strong id="patron-membership-status"></strong>
                <small>Expires <span id="patron-membership-expiration"></span></small>
            </article>
            <article class="patron-summary-item">
                <p>Active checkouts</p>
                <strong id="patron-checkout-count"></strong>
                <small>Checkout limit</small>
            </article>
            <article class="patron-summary-item">
                <p>Outstanding fines</p>
                <strong id="patron-fine-balance"></strong>
                <small>Unpaid balance</small>
            </article>
        </div>

        <p class="patron-eligibility-reasons" id="patron-eligibility-reasons" hidden></p>

        <section class="patron-loans-section" aria-labelledby="patron-loans-title">
            <div class="patron-panel-heading">
                <div>
                    <p class="dashboard-eyebrow">CIRCULATION</p>
                    <h2 id="patron-loans-title">Active checkouts</h2>
                </div>
                <span class="patron-loan-count" id="patron-loan-count"></span>
            </div>
            <p class="patron-empty" id="patron-checkouts-empty" hidden>
                This patron has no active checkouts.
            </p>
            <div class="patron-table-wrap" id="patron-checkouts-wrap" hidden>
                <table class="patron-table">
                    <thead>
                        <tr>
                            <th scope="col">Item</th>
                            <th scope="col">Copy ID</th>
                            <th scope="col">Checked out</th>
                            <th scope="col">Due date</th>
                            <th scope="col">Renewals</th>
                            <th scope="col">Home branch</th>
                        </tr>
                    </thead>
                    <tbody id="patron-checkouts-body"></tbody>
                </table>
            </div>
        </section>
    </section>
</main>
<footer class="site-footer" aria-hidden="true"></footer>
</body>
</html>