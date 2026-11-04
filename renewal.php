<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Renewal - Wayback Public Library</title>
  <link rel="stylesheet" href="style/style.css">
  <script src="js/" defer></script>
</head>
<body>
  <header class="site-header">
    <h1>Wayback Public Library</h1>
    <h4>Renewal</h4>
        <!-- Navigation row stays full-width at the top -->
    <nav class="library-nav" aria-label="Library tasks">
      <a href="index.php">Checkout</a>
      <a href="add-library-item.php">Add Library Item</a>
      <a href="return-item.php">Checkin</a>
      <a href="renewal.php" aria-current="page">Renew</a>
    </nav>
  </header>

  <main class="page-container library-page">
    <!-- Global layout wrapper for columns -->
    <div class="main-layout">
      
      <!-- LEFT COLUMN: Takes up 70% width -->
      <section class="library-panel">
        
        <!-- ===== Find Item Card ===== -->
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
              <button type="submit">Find Item</button>
            </div>
          </form>

          <div class="result-box" id="itemResult">
            <p><strong>Author:</strong> <span data-field="author">--</span></p>
            <p><strong>Title:</strong> <span data-field="title">--</span></p>
            <p><strong>Item Copy ID:</strong> <span data-field="itemCopyID">--</span></p>
            <p><strong>Item Type:</strong> <span data-field="itemType">--</span></p>
            <p><strong>Branch:</strong> <span data-field="branchID">--</span></p>
            <p><strong>Status:</strong> <span data-field="status">--</span></p>
            <p class="msg error" data-field="error" hidden></p>
          </div>
        </div>

        <!-- Global Action Messages & Buttons linked to panels -->
        <p id="libraryMessage" class="msg" role="status" aria-live="polite"></p>
        
        <div class="library-actions">
          <button type="button" id="saveRenewalBtn" class="primary-button">Save Renewal Item</button>
        </div>

      </section>

      <!-- RIGHT COLUMN: Takes up 30% width -->
      <div class="sidebar-wrapper">
        
        <!-- ===== Checkout Summary ===== -->
        <aside class="checkout-card">
          <div class="checkout-summary">
            <h3>Checkout Summary</h3>
            <p><strong>Selected Patron:</strong> <span data-field="patronName">--</span></p>
            <p><strong>Author:</strong> <span data-field="authorLastName" data-field="authorFirstName">--</span></p>
            <p><strong>Item Copy ID:</strong> <span data-field="itemCopyID">--</span></p>
            <p><strong>Branch:</strong> <span data-field="branchID">--</span></p>
            <p><strong>Item Type:</strong> <span data-field="itemType">--</span></p>
            <p class="msg error" data-field="error" hidden></p>
          </div>

          <form id="checkoutForm">
            <input type="hidden" name="patronID" value="">
            <input type="hidden" name="itemCopyID" value="">
            <button class="primary-button" type="submit">Record Checkout</button>
          </form>
        </aside>

        <!-- ===== Confirmation ===== -->
        <aside class="confirmation-card" id="confirmationCard" hidden>
          <h3>Checkout Recorded</h3>
          <div class="confirmation-details">
            <p><strong>Checkout ID:</strong> <span data-field="checkoutID">--</span></p>
            <p><strong>Patron:</strong> <span data-field="patron">--</span></p>
            <p><strong>Author:</strong> <span data-field="authorLastName" data-field="authorFirstName">--</span></p>
            <p><strong>Title:</strong> <span data-field="title">--</span></p>
            <p><strong>Branch:</strong> <span data-field="branchID">--</span></p>
            <p><strong>Item Copy ID:</strong> <span data-field="itemCopyID">--</span></p>
            <p><strong>Checkout Date:</strong> <span data-field="checkoutDate">--</span></p>
            <p><strong>Due Date:</strong> <span data-field="dueDate">--</span></p>
            <p class="msg error" data-field="error" hidden></p>
          </div>
          <button type="button" id="doneButton">Done</button>
        </aside>

      </div> <!-- End sidebar-wrapper -->

    </div> <!-- End main-layout -->
  </main>
</body>
</html>
