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
        </section>

        <section class="lookup-container">

            <div class="lookup-card">
                <h3>Find Patron</h3>

                <form method="GET" action="index.php">
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

                        <button type="submit" name="action" value="findPatron">
                            Find Patron
                        </button>
                    </div>
                </form>

                <div class="result-box">
                    <p>
                        <strong>Name:</strong>
                        <span>--</span>
                    </p>

                    <p>
                        <strong>Membership Expiration:</strong>
                        <span>--</span>
                    </p>
                </div>
            </div>

            <div class="lookup-card">
                <h3>Find Item</h3>

                <form method="GET" action="index.php">
                    <label for="itemCopyID">Item Copy ID</label>

                    <div class="input-row">
                        <input
                            type="number"
                            id="itemCopyID"
                            name="itemCopyID"
                            min="1"
                            placeholder="Enter Item Copy ID"
                            required
                        >

                        <button type="submit" name="action" value="findItem">
                            Find Item
                        </button>
                    </div>
                </form>

                <div class="result-box">
                    <p>
                        <strong>Item Copy ID:</strong>
                        <span>--</span>
                    </p>

                    <p>
                        <strong>Item Type:</strong>
                        <span>--</span>
                    </p>

                    <p>
                        <strong>Status:</strong>
                        <span>--</span>
                    </p>
                </div>
            </div>

        </section>

        <section class="checkout-card">

            <div class="checkout-summary">
                <h3>Checkout Summary</h3>

                <p>
                    <strong>Selected Patron:</strong>
                    <span>--</span>
                </p>

                <p>
                    <strong>Item Copy ID:</strong>
                    <span>--</span>
                </p>

                <p>
                    <strong>Item Type:</strong>
                    <span>--</span>
                </p>
            </div>

            <form method="POST" action="index.php">

                <input type="hidden" name="patronID" value="">
                <input type="hidden" name="itemCopyID" value="">

                <button
                    class="checkout-button"
                    type="submit"
                    name="action"
                    value="recordCheckout"
                >
                    Record Checkout
                </button>

            </form>

        </section>

        <section class="confirmation-card">

            <h3>Checkout Recorded</h3>

            <div class="confirmation-details">

                <p>
                    <strong>Checkout ID:</strong>
                    <span>--</span>
                </p>

                <p>
                    <strong>Patron:</strong>
                    <span>--</span>
                </p>

                <p>
                    <strong>Item Copy ID:</strong>
                    <span>--</span>
                </p>

                <p>
                    <strong>Checkout Date:</strong>
                    <span>--</span>
                </p>

                <p>
                    <strong>Due Date:</strong>
                    <span>--</span>
                </p>

            </div>

            <button type="button">Done</button>

        </section>

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

</body>

</html>
