<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Library Item - Wayback Public Library</title>
    <link rel="stylesheet" href="style/style.css">
    <script src="js/libraryItem.js" defer></script>
    <style>
        .author-entry {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            align-items: end;
            gap: 12px;
            margin: 12px 0;
        }
        .author-entry label {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .author-entry input { width: 100%; }
        .author-entry button { padding: 10px; }
        .item-help { color: #555; margin: 10px 0; }
        .item-feedback { min-height: 24px; margin: 15px 0; }
        .item-feedback.error { color: #b42318; }
        .item-feedback.success { color: #176b38; }
        .item-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 14px;
        }
        @media (max-width: 650px) {
            .author-entry { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header class="site-header">
    <h1>Wayback Public Library</h1>
    <h4>Add Library Item</h4>
    <?php require __DIR__ . '/includes/navigation.php'; ?>
</header>

<main class="page-container library-page">
    <form id="libraryItemForm">

        <section class="library-panel">
            <h3>1. Catalog Definition</h3>

            <div class="library-radio-row">
                <label>
                    <input type="radio" name="mode"
                           value="existing" checked>
                    Add a copy of an existing catalog item
                </label>
                <label>
                    <input type="radio" name="mode" value="new">
                    Create a new catalog item
                </label>
            </div>

            <div id="existingFields">
                <p class="item-help">
                    Search the catalog first to avoid creating duplicate
                    definitions of the same book or other library material.
                </p>

                <label for="catalogSearch">
                    Search by title or Item ID
                </label>
                <div class="input-row">
                    <input id="catalogSearch" type="search"
                           placeholder="Enter title or Item ID">
                    <button id="searchCatalogBtn" type="button">
                        Search
                    </button>
                </div>

                <label for="catalogResults">
                    Select Catalog Item
                </label>
                <select id="catalogResults"
                        name="existingItemID" required>
                    <option value="">
                        Search and select an item
                    </option>
                </select>

                <p id="selectedCatalogDetails"
                   class="library-note"></p>
            </div>

            <div id="newFields" hidden>
                <div class="library-grid">
                    <label>
                        New Catalog Item ID
                        <input type="number" name="newItemID"
                               min="1" max="2147483647">
                    </label>

                    <label>
                        Title
                        <input type="text" name="title"
                               maxlength="100">
                    </label>

                    <label>
                        Item Type
                        <select name="itemType">
                            <option value="">Choose type</option>
                            <option value="Book">Book</option>
                            <option value="DVD">DVD</option>
                            <option value="Periodical">Periodical</option>
                            <option value="Comic Book">Comic Book</option>
                            <option value="CD">CD</option>
                            <option value="Recording">Recording</option>
                        </select>
                    </label>

                    <label>
                        Publisher
                        <input type="text" name="publisher"
                               maxlength="100">
                    </label>

                    <label>
                        Publication Date
                        <input type="date" name="publicationDate">
                    </label>

                    <label>
                        Library of Congress Code
                        <input type="text"
                               name="libraryOfCongressCode"
                               maxlength="150">
                    </label>

                    <label>
                        Cost (whole dollars)
                        <input type="number" name="cost"
                               min="0" max="2147483647" step="1">
                    </label>
                </div>

                <h4>Authors</h4>
                <p class="item-help">
                    Add all known authors. Leave the author section
                    empty for materials without a named author.
                </p>

                <div id="authorEntries">
                    <div class="author-entry">
                        <label>
                            First Name
                            <input type="text"
                                   name="authorFirstName[]"
                                   maxlength="50">
                        </label>
                        <label>
                            Last Name
                            <input type="text"
                                   name="authorLastName[]"
                                   maxlength="50">
                        </label>
                        <button type="button"
                                class="remove-author">
                            Remove
                        </button>
                    </div>
                </div>

                <div class="item-actions">
                    <button type="button" id="addAuthorBtn">
                        + Add Another Author
                    </button>
                </div>
            </div>
        </section>

        <section class="library-panel">
            <h3>2. Physical Copy</h3>

            <p class="item-help">
                Every physical copy has its own Item Copy ID,
                even when multiple copies share one catalog item.
            </p>

            <div class="library-grid">
                <label>
                    Item Copy ID
                    <input type="number" name="itemCopyID"
                           min="1" max="2147483647" required>
                </label>

                <label>
                    Acquisition Date
                    <input type="date" id="acquisitionDate"
                           name="acquisitionDate" required>
                </label>

                <label>
                    Home / Receiving Branch
                    <select id="branchSelect"
                            name="branchID" required>
                        <option value="">Loading branches...</option>
                    </select>
                </label>

                <label>
                    Shelf Location Code
                    <input type="text" name="shelfLocation"
                           maxlength="50" required
                           placeholder="e.g., B2-14">
                </label>
            </div>

            <p id="branchNote" class="library-note">
                New copies begin in Processing and must be
                confirmed as shelved before checkout.
            </p>
        </section>

        <p id="libraryMessage" class="msg item-feedback"
           role="status" aria-live="polite"></p>

        <div class="library-actions">
            <a href="statuses.php">View Item Statuses</a>
            <a href="index.php">Back to Checkout</a>
            <button type="submit" id="saveLibraryBtn"
                    class="primary-button">
                Save Library Item
            </button>
        </div>
    </form>
</main>
</body>
</html>