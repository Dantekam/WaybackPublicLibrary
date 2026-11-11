
<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item Status Management - Wayback Public Library</title>
    <link rel="stylesheet" href="style/style.css">
    <script src="js/statuses.js" defer></script>
    <style>
        .status-wrap {
            max-width: 1300px;
            margin: 0 auto;
            padding: 24px;
        }
        .status-panel {
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccc;
            border-radius: 8px;
            background: #fff;
            color: #222;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 12px;
        }
        .status-grid label {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .status-grid input,
        .status-grid select,
        .status-actions select {
            padding: 9px;
            max-width: 100%;
        }
        .status-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 16px;
        }
        .status-actions button,
        .status-confirmation button {
            padding: 10px 16px;
            cursor: pointer;
        }
        .status-actions label {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .status-table-wrap {
            overflow-x: auto;
        }
        .status-table {
            border-collapse: collapse;
            width: 100%;
        }
        .status-table th,
        .status-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        .status-table th {
            background: #f1f3f5;
        }
        .status-table tbody tr:hover {
            background: #f7f8fa;
        }
        .status-message {
            margin: 12px 0;
            min-height: 24px;
            font-weight: 600;
        }
        .status-note {
            color: #555;
            font-size: .9rem;
        }
        .status-confirmation {
            margin-top: 16px;
            padding: 16px;
            border: 1px solid #c9d8cd;
            border-left: 4px solid #246b46;
            border-radius: 5px;
            background: #f3f8f4;
        }
        .status-confirmation[hidden],
        [hidden] {
            display: none !important;
        }
        .status-confirmation p {
            margin: 10px 0;
        }
        .status-confirmation button {
            margin-right: 8px;
        }
        #routeDestinationBranch:disabled {
            opacity: .55;
        }
        button:disabled {
            opacity: .6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<header class="site-header">
    <h1>Wayback Public Library</h1>
    <h4>Item Status Management</h4>
    <?php require __DIR__ . '/includes/navigation.php'; ?>
</header>

<main class="status-wrap">

    <section class="status-panel">
        <h2>Inventory Search</h2>

        <form id="statusSearchForm">
            <div class="status-grid">
                <label>
                    Item Copy ID or Title
                    <input
                        type="search"
                        id="statusSearch"
                        placeholder="Search copies or titles"
                    >
                </label>

                <label>
                    Current Branch
                    <select id="statusBranch">
                        <option value="">All branches</option>
                    </select>
                </label>

                <label>
                    Item Status
                    <select id="statusFilter">
                        <option value="">All statuses</option>
                        <option>Available</option>
                        <option>CheckedOut</option>
                        <option>Processing</option>
                        <option>Maintenance</option>
                        <option>Reserved</option>
                        <option>Retired</option>
                    </select>
                </label>

                <label>
                    Routing Status
                    <select id="routingFilter">
                        <option value="">All routing statuses</option>
                        <option value="NotApplicable">
                            Not Applicable
                        </option>
                        <option value="AwaitingReshelving">
                            Awaiting Reshelving
                        </option>
                        <option value="AwaitingTransport">
                            Awaiting Transport
                        </option>
                        <option value="InTransit">
                            In Transit
                        </option>
                        <option value="Arrived">
                            Arrived
                        </option>
                    </select>
                </label>

                <label>
                    Destination Branch
                    <select id="destinationFilter">
                        <option value="">All destinations</option>
                    </select>
                </label>
            </div>

            <div class="status-actions">
                <button type="submit">
                    Search Inventory
                </button>
                <button type="button" id="resetStatusFilters">
                    Reset Filters
                </button>
            </div>
        </form>
    </section>

    <section class="status-panel">
        <h2>Inventory Results</h2>
        <p id="statusCount" class="status-note"></p>

        <div class="status-table-wrap">
            <table class="status-table">
                <thead>
                    <tr>
                        <th>
                            <input
                                type="checkbox"
                                id="selectAllCopies"
                                aria-label="Select all displayed copies"
                            >
                        </th>
                        <th>Copy ID</th>
                        <th>Title</th>
                        <th>Current Branch</th>
                        <th>Home Branch</th>
                        <th>Destination</th>
                        <th>Item Status</th>
                        <th>Routing Status</th>
                        <th>Shelf</th>
                    </tr>
                </thead>
                <tbody id="statusTableBody">
                    <tr>
                        <td colspan="9">Loading inventory...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <form id="statusActionForm" class="status-actions">
            <label for="bulkAction">
                Bulk Action
                <select id="bulkAction" required>
                    <option value="">Choose action</option>
                    <option value="routeToBranch">
                        Route to Branch
                    </option>
                    <option value="startTransport">
                        Start Transport
                    </option>
                    <option value="confirmArrival">
                        Confirm Arrival
                    </option>
                    <option value="beginReshelving">
                        Begin Reshelving
                    </option>
                    <option value="confirmShelved">
                        Confirm Shelved / Available
                    </option>
                    <option value="maintenance">
                        Send to Maintenance
                    </option>
                    <option value="processing">
                        Return to Processing
                    </option>
                </select>
            </label>

            <label for="routeDestinationBranch">
                Route To
                <select id="routeDestinationBranch" disabled>
                    <option value="">
                        Select destination branch
                    </option>
                </select>
            </label>

            <button type="submit" id="applyStatusAction">
                Update Selected
            </button>
        </form>

        <div
            id="statusConfirmation"
            class="status-confirmation"
            hidden
        >
            <strong>Confirm Inventory Action</strong>
            <p id="statusConfirmationText"></p>

            <button type="button" id="confirmStatusAction">
                Confirm Action
            </button>
            <button type="button" id="cancelStatusAction">
                Cancel
            </button>
        </div>

        <p
            id="statusMessage"
            class="status-message"
            role="status"
            aria-live="polite"
        ></p>

        <p class="status-note">
            Route to Branch assigns the destination without
            moving the copy immediately. Start Transport records
            departure. Confirm Arrival updates the current branch
            after physical delivery.
        </p>

        <p class="status-note">
            Copies with active reservations must be delivered to
            their requested pickup branch. After arrival, use
            Reservations to mark the copy ready. Copies without
            reservations can be reshelved and made available.
        </p>
    </section>
</main>
</body>
</html>
