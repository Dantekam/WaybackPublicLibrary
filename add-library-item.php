<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Library Item - Wayback Public Library</title>
  <link rel="stylesheet" href="style/style.css">
  <script src="js/libraryItem.js" defer></script>
</head>
<body>
  <header class="site-header"><h1>Wayback Public Library</h1></header>
  <main class="page-container library-page">
    <nav class="library-nav" aria-label="Library tasks">
      <a href="index.php">Checkout</a>
      <a href="add-library-item.php" aria-current="page">Add Library Item</a>
    </nav>
    <h2>Add Library Item</h2>
    <p>Find a catalog definition or create one, then add a physical copy.</p>
    <form id="libraryItemForm">
      <section class="library-panel">
        <h3>1. Catalog definition</h3>
        <div class="library-radio-row">
          <label><input type="radio" name="mode" value="existing" checked> Use existing item</label>
          <label><input type="radio" name="mode" value="new"> Create new item</label>
        </div>
        <div id="existingFields">
          <label for="catalogSearch">Search by title or Item ID</label>
          <div class="input-row"><input id="catalogSearch" type="search" placeholder="Enter a title or ID"><button id="searchCatalogBtn" type="button">Search</button></div>
          <label for="catalogResults">Select catalog item</label>
          <select id="catalogResults" name="existingItemID" required><option value="">Search and select an item</option></select>
          <p id="selectedCatalogDetails" class="library-note"></p>
        </div>
        <div id="newFields" hidden>
          <div class="library-grid">
            <label>New Item ID<input type="number" name="newItemID" min="1"></label>
            <label>Title<input type="text" name="title" maxlength="100"></label>
            <label>Item Type<select name="itemType"><option value="">Choose type</option><option>Book</option><option>DVD</option><option>Periodical</option><option>Comic Book</option><option>CD</option><option>Recording</option></select></label>
            <label>Publisher<input type="text" name="publisher" maxlength="100"></label>
            <label>Library of Congress Code<input type="text" name="libraryOfCongressCode" maxlength="150"></label>
            <label>Cost (whole dollars)<input type="number" name="cost" min="0" step="1"></label>
            <label>Author First Name (optional)<input type="text" name="authorFirstName" maxlength="50"></label>
            <label>Author Last Name (optional)<input type="text" name="authorLastName" maxlength="50"></label>
          </div>
        </div>
      </section>
      <section class="library-panel">
        <h3>2. Physical copy</h3>
        <div class="library-grid">
          <label>Item Copy ID<input type="number" name="itemCopyID" min="1" required></label>
          <label>Acquisition Date<input id="acquisitionDate" type="date" name="acquisitionDate" required></label>
          <label>Branch<select id="branchSelect" name="branchID" required><option value="">Loading branches...</option></select></label>
          <label>Shelf Location<input type="text" name="shelfLocation" maxlength="50" required placeholder="e.g., P1"></label>
        </div>
        <p id="branchNote" class="library-note">Central copies are available. Copies for other branches will be marked Maintenance until processed.</p>
      </section>
      <p id="libraryMessage" class="msg" role="status" aria-live="polite"></p>
      <div class="library-actions"><a href="index.php">Back to Checkout</a><button type="submit" id="saveLibraryBtn">Save Library Item</button></div>
    </form>
  </main>
</body>
</html>
