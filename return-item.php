<?php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wayback Public Library - Return Item</title>
    <link rel="stylesheet" href="style/style.css">
</head>

<body>

    <header class="site-header">
        <h1>Wayback Public Library</h1>
        <h4>Return Item</h4>
    </header>

    <main class="page-container">

        <nav class="library-nav" aria-label="Library tasks">
            <a href="index.php">Checkout</a>
            <a href="add-library-item.php">Add Library Item</a>
            <a href="return-item.php" aria-current="page">Return Item</a>
        </nav>

        <!-- ===== Return Item ===== -->
        <section class="return-pannel">
            <div class="return-card">
                <h3>Return Item</h3>

                <form id="returnForm">
                    <label for="itemCopyID">Return ID</label>
                    
                    <div class="input-row">
                        <input
                            type="number"
                            id="itemCopyID"
                            name="itemCopyID"
                            min="1000"
                            placeholder="Enter Item Copy ID"
                            required
                        >
                        <button type="submit">Find Item</button>
                    </div>
                </form>
            </div>

            <div class="return-item" id="returnItem">
                <p>
                    <strong>Author:</strong>
                    <span data-field="author">--</span>
                </p>
                <p>
                    <strong>Title:</strong>
                    <span data-field="title">--</span>
                </p>
                <p>
                    <strong>Item Copy ID:</strong>
                    <span data-field="itemCopyID">--</span>
                </p>
                <p>
                    <strong>Item Type:</strong>
                    <span data-field="itemType">--</span>
                </p>
                <p>
                    <strong>Branch:</strong>
                    <span data-field="branchID">--</span>
                </p>
                <p>
                    <strong>Status:</strong>
                    <span data-field="status">--</span>
                </p>
                <p class="error" data-field="error" hidden></p>
            </div>
            <form for="return-status">
                <select name="status" required>
                    <option value="">Choose Return Staus</option>
                    <option value="checkedout">Checkout</option>
                    <option value="Maintenance">Maintenance</option>
                    <option value="Available" selected>Available</option>
                </select>
            </form>
            <button type="submit" id="returnLibraryItem"> Return Item</button>

        </section>
    </main>
</body>

</html>