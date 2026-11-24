<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <title>Reservations - Wayback Public Library</title>
    <link rel="stylesheet" href="style/style.css">
    <script src="js/reservations.js" defer></script>

</head>
<body>
<header class="site-header">
    <h1>Wayback Public Library</h1>
    <h4>Reservation Management</h4>
    <?php require __DIR__ . '/includes/navigation.php'; ?>
</header>

<main class="reservation-wrap">
    <section class="reservation-panel">
        <h2>Create Reservation</h2>
        <p class="reservation-note">
            Reserve a specific physical copy for a patron.
            Available copies at the pickup branch can become
            ready immediately. Other copies remain pending.
        </p>

        <form id="createReservationForm">
            <div class="reservation-grid">
                <label>
                    Patron ID
                    <input type="number" name="patronID"
                           min="1" required>
                </label>
                <label>
                    Item Copy ID
                    <input type="number" name="itemCopyID"
                           min="1" required>
                </label>
                <label>
                    Pickup Branch
                    <select name="pickupBranchID"
                            id="reservationPickupBranch"
                            required>
                        <option value="">Loading branches...</option>
                    </select>
                </label>
            </div>

            <div class="reservation-actions">
                <button type="submit" id="createReservationBtn">
                    Create Reservation
                </button>
            </div>
        </form>
    </section>

    <section class="reservation-panel">
        <h2>Manage Reservations</h2>

        <div class="reservation-grid">
            <label>
                Search by patron, copy, title, or reservation ID
                <input type="search" id="reservationSearch"
                       placeholder="Search reservations">
            </label>
            <label>
                Filter by Status
                <select id="reservationStatusFilter">
                    <option value="">All statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Ready">Ready</option>
                    <option value="Fulfilled">Fulfilled</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="Expired">Expired</option>
                </select>
            </label>
        </div>

        <div class="reservation-actions">
            <button type="button" id="refreshReservations">
                Refresh Reservations
            </button>
        </div>

        <div class="reservation-table-wrap">
            <table class="reservation-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patron</th>
                        <th>Copy</th>
                        <th>Title</th>
                        <th>Pickup Branch</th>
                        <th>Current Branch</th>
                        <th>Status</th>
                        <th>Routing</th>
                        <th>Ready Date</th>
                        <th>Expires</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="reservationRows">
                    <tr>
                        <td colspan="11">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p id="reservationMessage"
           class="reservation-message"
           role="status" aria-live="polite"></p>

        <p class="reservation-note">
            Mark Ready only after the copy has physically
            reached the pickup branch. Fulfill records
            the patron's checkout. Cancel releases the hold.
            Expired ready holds return to processing.
        </p>
    </section>
</main>
<footer class="site-footer" aria-hidden="true"></footer>
</body>
</html>