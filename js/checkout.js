(() => {
  'use strict';

  const $ = (selector, root = document) =>
    root ? root.querySelector(selector) : null;

  function setField(box, field, value) {
    const element = $(`[data-field="${field}"]`, box);
    if (element) element.textContent = value ?? '--';
  }

  function setError(box, message) {
    const element = $('[data-field="error"]', box);
    if (!element) return;
    element.textContent = message || '';
    element.hidden = !message;
  }

  async function fetchJSON(url, options = {}) {
    const response = await fetch(url, {
      cache: 'no-store',
      ...options
    });

    const text = await response.text();
    let data;

    try {
      data = JSON.parse(text);
    } catch (error) {
      console.error('Invalid server response:', text);
      throw new Error('The server returned an invalid response.');
    }

    if (!data || typeof data !== 'object') {
      throw new Error('Unexpected server response.');
    }

    return data;
  }

  const patronForm = $('#patronForm');
  const patronResult = $('#patronResult');
  const itemForm = $('#itemForm');
  const itemResult = $('#itemResult');
  const checkoutForm = $('#checkoutForm');
  const checkoutSummary = $('.checkout-summary');
  const confirmationCard = $('#confirmationCard');
  const doneButton = $('#doneButton');

  // Supports the existing HTML without modifying index.php.
  const checkoutsBody =
    $('#checkoutsBody') ||
    $('.current-checkouts tbody');

  if (!patronForm || !patronResult ||
      !itemForm || !itemResult ||
      !checkoutForm || !checkoutSummary) {
    console.error('Checkout initialization failed.');
    return;
  }

  const state = {
    patron: null,
    item: null,
    submitting: false,
    patronRequest: 0,
    itemRequest: 0,
    checkoutsRequest: 0
  };

  const submitButton = checkoutForm.querySelector(
    'button[type="submit"], input[type="submit"]'
  );

  function clearPatronDisplay() {
    for (const field of [
      'name', 'expiration', 'activeCheckouts',
      'outstandingFines', 'eligibility'
    ]) {
      setField(patronResult, field, '--');
    }
  }

  function clearItemDisplay() {
    for (const field of [
      'title', 'author',
      'itemCopyID', 'itemType', 'status', 'branch'
    ]) {
      setField(itemResult, field, '--');
    }
  }

  function showCheckoutError(message) {
    setError(checkoutSummary, message);
  }

  function updateSummary() {
    setField(
      checkoutSummary,
      'patronName',
      state.patron ? state.patron.name : '--'
    );

    setField(
      checkoutSummary,
      'itemCopyID',
      state.item ? state.item.itemCopyID : '--'
    );

    setField(
      checkoutSummary,
      'itemType',
      state.item ? state.item.itemType : '--'
    );

    setField(
      checkoutSummary, 
      'title',
      state.item ? state.item.title : '--'
    );

    setField(
      checkoutSummary, 
      'author',
      state.item ? state.item.author : '--'
    );

    setField(
      checkoutSummary, 
      'branch',
      state.item ? (state.item.branchName || state.item.branchID) : '--'
    );

    const patronInput =
      checkoutForm.elements.namedItem('patronID');

    const itemInput =
      checkoutForm.elements.namedItem('itemCopyID');

    if (patronInput) {
      patronInput.value = state.patron
        ? state.patron.patronID : '';
    }

    if (itemInput) {
      itemInput.value = state.item
        ? state.item.itemCopyID : '';
    }

    showCheckoutError('');
  }

  function setSubmitting(submitting) {
    state.submitting = submitting;

    if (submitButton) {
      submitButton.disabled = submitting;
      const label = submitting
        ? 'Recording...' : 'Record Checkout';

      if (submitButton.tagName === 'INPUT') {
        submitButton.value = label;
      } else {
        submitButton.textContent = label;
      }
    }
  }

  // Current Checkouts Table
  function showTableMessage(message) {
    if (!checkoutsBody) return;

    checkoutsBody.replaceChildren();

    const row = document.createElement('tr');
    row.className = 'empty-row';

    const cell = document.createElement('td');
    cell.colSpan = 8;
    cell.textContent = message;

    row.appendChild(cell);
    checkoutsBody.appendChild(row);
  }

  function addCheckoutRow(checkout) {
    if (!checkoutsBody) return;

    const row = document.createElement('tr');

    const values = [
      checkout.checkoutID,
      checkout.patron,
      checkout.title,
      checkout.author,
      checkout.itemCopyID,
      checkout.itemType,
      checkout.checkoutDate,
      checkout.dueDate
    ];

    for (const value of values) {
      const cell = document.createElement('td');
      cell.textContent = value ?? '--';
      row.appendChild(cell);
    }

    checkoutsBody.appendChild(row);
  }

  function renderCheckouts(checkouts) {
    if (!checkoutsBody) return;

    checkoutsBody.replaceChildren();

    if (!Array.isArray(checkouts) ||
        checkouts.length === 0) {
      showTableMessage(
        'This patron has no active checkouts.'
      );
      return;
    }

    for (const checkout of checkouts) {
      addCheckoutRow(checkout);
    }
  }

  async function loadPatronCheckouts(patronID) {
    const requestNumber = ++state.checkoutsRequest;

    if (!patronID) {
      showTableMessage(
        'Search for a patron to view their current checkouts.'
      );
      return;
    }

    showTableMessage('Loading current checkouts...');

    try {
      const data = await fetchJSON(
        'script/getPatronCheckouts.php?patronID=' +
        encodeURIComponent(patronID)
      );

      if (requestNumber !== state.checkoutsRequest) {
        return;
      }

      if (!state.patron ||
          String(state.patron.patronID) !== String(patronID)) {
        return;
      }

      if (!data.ok || !Array.isArray(data.checkouts)) {
        throw new Error(
          data.error || 'Unable to load current checkouts.'
        );
      }

      renderCheckouts(data.checkouts);

    } catch (error) {
      if (requestNumber !== state.checkoutsRequest) {
        return;
      }

      console.error('Checkout list failed:', error);

      showTableMessage(
        'Unable to load current checkouts. Please try again.'
      );
    }
  }

  function clearCheckoutTable() {
    ++state.checkoutsRequest;

    showTableMessage(
      'Search for a patron to view their current checkouts.'
    );
  }

  // Find Patron
  patronForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const requestNumber = ++state.patronRequest;

    state.patron = null;
    clearPatronDisplay();
    updateSummary();
    setError(patronResult, '');
    clearCheckoutTable();

    const input =
      patronForm.elements.namedItem('patronID');

    const patronID = input
      ? input.value.trim() : '';

    if (!patronID) {
      setError(patronResult, 'Please enter a Patron ID.');
      return;
    }

    try {
      const data = await fetchJSON(
        'script/findPatron.php?patronID=' +
        encodeURIComponent(patronID)
      );

      if (requestNumber !== state.patronRequest) {
        return;
      }

      if (!data.ok || !data.patron) {
        setError(
          patronResult,
          data.error || 'Patron not found.'
        );
        return;
      }

      const patron = data.patron;

      if (
        typeof patron.activeCheckouts === 'undefined' ||
        typeof patron.outstandingFines === 'undefined' ||
        typeof patron.eligible !== 'boolean'
      ) {
        setError(
          patronResult,
          'Patron eligibility details are missing.'
        );
        return;
      }

      state.patron = patron;

      setField(patronResult, 'name', patron.name);
      setField(patronResult, 'expiration', patron.expiration);

      setField(
        patronResult,
        'activeCheckouts',
        `${patron.activeCheckouts} / ${patron.checkoutLimit ?? 20}`
      );

      setField(
        patronResult,
        'outstandingFines',
        `$${Number(patron.outstandingFines).toFixed(2)}`
      );

      const reasons =
        Array.isArray(patron.eligibilityReasons)
          ? patron.eligibilityReasons : [];

      setField(
        patronResult,
        'eligibility',
        patron.eligible
          ? 'Eligible'
          : 'Ineligible — ' +
            (reasons.join(', ') ||
             'Patron does not meet checkout requirements')
      );

      updateSummary();

      // Automatically load this patron's active loans.
      await loadPatronCheckouts(patron.patronID);

    } catch (error) {
      if (requestNumber !== state.patronRequest) {
        return;
      }

      console.error('Patron lookup failed:', error);

      state.patron = null;
      clearPatronDisplay();
      updateSummary();
      clearCheckoutTable();

      setError(
        patronResult,
        'Unable to retrieve patron information. Please try again.'
      );
    }
  });

  // Find Item
  itemForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const requestNumber = ++state.itemRequest;

    state.item = null;
    clearItemDisplay();
    updateSummary();
    setError(itemResult, '');

    const input =
      itemForm.elements.namedItem('itemCopyID');

    const itemCopyID = input
      ? input.value.trim() : '';

    if (!itemCopyID) {
      setError(itemResult, 'Please enter an Item Copy ID.');
      return;
    }

    try {
      const data = await fetchJSON(
        'script/findItem.php?itemCopyID=' +
        encodeURIComponent(itemCopyID)
      );

      if (requestNumber !== state.itemRequest) {
        return;
      }

      if (!data.ok || !data.item) {
        setError(
          itemResult,
          data.error || 'Item copy not found.'
        );
        return;
      }

      state.item = data.item;

      setField(
        itemResult, 'itemCopyID', data.item.itemCopyID
      );

      setField(
        itemResult, 'itemType', data.item.itemType
      );

      setField(
        itemResult, 'status', data.item.status
      );

      setField(
        itemResult, 'title',  data.item.title
      );

      setField(
        itemResult, 'author', data.item.author
      );

      setField(
        itemResult, 'branch',
        data.item.branchName || data.item.branchID
      );

      updateSummary();

      if (!data.item.available) {
        setError(
          itemResult,
          'This copy is not available for checkout.'
        );
      }

    } catch (error) {
      if (requestNumber !== state.itemRequest) {
        return;
      }

      console.error('Item lookup failed:', error);

      state.item = null;
      clearItemDisplay();
      updateSummary();

      setError(
        itemResult,
        'Unable to retrieve item information. Please try again.'
      );
    }
  });

  // Confirmation
  function showConfirmation(checkout) {
    if (!confirmationCard) return;

    setField(
      confirmationCard, 'checkoutID', checkout.checkoutID
    );
    setField(
      confirmationCard, 'patron', checkout.patron
    );
    setField(
      confirmationCard, 'itemCopyID', checkout.itemCopyID
    );
    setField(
      confirmationCard, 'checkoutDate', checkout.checkoutDate
    );
    setField(
      confirmationCard, 'dueDate', checkout.dueDate
    );
    setField(
      confirmationCard, 'title',  checkout.title
    );
    setField(
      confirmationCard, 'author', checkout.author
    );
    setField(
      confirmationCard, 'branch',
      checkout.branchName || checkout.branchID
    );

    confirmationCard.hidden = false;
  }

  // Record Checkout
  checkoutForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (state.submitting) return;

    showCheckoutError('');

    if (!state.patron || !state.item) {
      showCheckoutError(
        'Please select a patron and an item first.'
      );
      return;
    }

    if (state.patron.expired || !state.patron.eligible) {
      const reasons =
        Array.isArray(state.patron.eligibilityReasons)
          ? state.patron.eligibilityReasons : [];

      showCheckoutError(
        'Cannot check out: ' +
        (reasons.join(', ') || 'patron is not eligible') +
        '.'
      );
      return;
    }

    if (!state.item.available) {
      showCheckoutError(
        'Cannot check out: this item is not available.'
      );
      return;
    }

    const patronID = state.patron.patronID;
    const formData = new FormData(checkoutForm);

    setSubmitting(true);

    let data;

    try {
      data = await fetchJSON(
        'script/recordCheckout.php',
        {
          method: 'POST',
          body: formData
        }
      );
    } catch (error) {
      console.error('Checkout request failed:', error);

      showCheckoutError(
        'Unable to confirm the checkout result. ' +
        'Check the database before trying again.'
      );

      setSubmitting(false);
      return;
    }

    if (!data.ok) {
      showCheckoutError(
        data.error || 'Checkout was denied.'
      );

      setSubmitting(false);
      return;
    }

    if (!data.checkout) {
      showCheckoutError(
        'Checkout may have been recorded, but confirmation ' +
        'details are missing. Check the database.'
      );

      setSubmitting(false);
      return;
    }

    const checkout = data.checkout;

    // Clear the selected item to prevent a duplicate attempt.
    ++state.itemRequest;
    state.item = null;
    itemForm.reset();
    clearItemDisplay();
    setError(itemResult, '');

    updateSummary();
    setSubmitting(false);

    try {
      showConfirmation(checkout);

      // Update the patron count and list from the database.
      // The current patron remains selected.
      const patronData = await fetchJSON(
        'script/findPatron.php?patronID=' +
        encodeURIComponent(patronID)
      );

      if (
        patronData.ok &&
        patronData.patron &&
        state.patron &&
        String(state.patron.patronID) === String(patronID)
      ) {
        state.patron = patronData.patron;

        setField(
          patronResult,
          'activeCheckouts',
          `${state.patron.activeCheckouts} / ${state.patron.checkoutLimit ?? 20}`
        );

        const reasons =
          Array.isArray(state.patron.eligibilityReasons)
            ? state.patron.eligibilityReasons : [];

        setField(
          patronResult,
          'eligibility',
          state.patron.eligible
            ? 'Eligible'
            : 'Ineligible — ' +
              (reasons.join(', ') ||
               'Patron does not meet checkout requirements')
        );

        await loadPatronCheckouts(patronID);
      }

    } catch (error) {
      console.error(
        'Checkout saved, but list refresh failed:',
        error
      );

      showTableMessage(
        'Checkout recorded. Search for the patron again ' +
        'to refresh current checkouts.'
      );
    }

    if (confirmationCard) {
      confirmationCard.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
      });
    }
  });

  if (doneButton) {
    doneButton.addEventListener('click', () => {
      if (confirmationCard) {
        confirmationCard.hidden = true;
      }

      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });
  }

  clearCheckoutTable();
})();
