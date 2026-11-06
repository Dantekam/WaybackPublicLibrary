<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Renewal - Wayback Public Library</title>
  <link rel="stylesheet" href="style/style.css">
  <script src="js/renewal.js" defer></script>
</head>
<body>
  <header class="site-header">
    <h1>Wayback Public Library</h1>
    <h4>Renewal</h4>
    <nav class="library-nav" aria-label="Library tasks">
      <a href="index.php">Checkout</a>
      <a href="add-library-item.php">Add Library Item</a>
      <a href="return-item.php">Checkin</a>
      <a href="renewal.php" aria-current="page">Renew</a>
      <a href="all-items.php">All Items</a>
    </nav>
  </header>

  <main class="page-container library-page">
    <div class="main-layout">
      <section class="library-panel lookup-card">
        <h3>Find Item</h3>

        <form id="renewalLookupForm">
          <label for="itemCopyID">Item Copy ID</label>
          <div class="input-row">
            <input type="number" id="itemCopyID" name="itemCopyID" min="1" placeholder="Enter Item Copy ID" required>
            <button type="submit">Find Item</button>
          </div>
        </form>

        <div class="result-box" id="renewalResult">
          <p><strong>Patron:</strong> <span data-field="patronName">--</span></p>
          <p><strong>Author:</strong> <span data-field="author">--</span></p>
          <p><strong>Title:</strong> <span data-field="title">--</span></p>
          <p><strong>Item Copy ID:</strong> <span data-field="itemCopyID">--</span></p>
          <p><strong>Item Type:</strong> <span data-field="itemType">--</span></p>
          <p><strong>Current Branch:</strong> <span data-field="branchName">--</span></p>
          <p><strong>Status:</strong> <span data-field="status">--</span></p>
          <p><strong>Current Due Date:</strong> <span data-field="dueDate">--</span></p>
          <p><strong>Renewal Count:</strong> <span data-field="renewalCount">--</span></p>
          <p class="msg error" data-field="error" hidden></p>
        </div>
      </section>

      <aside class="checkout-card sidebar-wrapper">
        <div class="checkout-summary">
          <h3>Checkout Summary</h3>
          <p><strong>Patron:</strong> <span data-field="patronDisplay">--</span></p>
          <p><strong>Item Copy ID:</strong> <span data-field="summaryItemCopyID">--</span></p>
          <p><strong>Item Type:</strong> <span data-field="summaryItemType">--</span></p>
          <p><strong>Current Due Date:</strong> <span data-field="summaryDueDate">--</span></p>
          <p><strong>Renewal Count:</strong> <span data-field="summaryRenewalCount">--</span></p>
        </div>

        <form id="renewalForm">
          <input type="hidden" name="itemCopyID" value="">
          <div class="library-actions">
            <button type="submit" id="saveRenewalBtn" class="primary-button">Renew Checkout</button>
          </div>
        </form>
      </aside>
    </div>
  </main>
</body>
</html>
