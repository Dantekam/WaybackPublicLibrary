
(() => {
  'use strict';

  // Helpers
  const $ = (selector, root = document) =>
    root ? root.querySelector(selector) : null;

  function setField(box, field, value) {
    const element = $(`[data-field="${field}"]`, box);

    if (element) {
      element.textContent = value ?? '--';
    }
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

      throw new Error(
        'The server returned an invalid response.'
      );
    }

    if (!data || typeof data !== 'object') {
      throw new Error('Unexpected server response.');
    }

    return data;
  }

  // Elements
  const patronForm = $('#patronForm');
  const patronResult = $('#patronResult');

  const itemForm = $('#itemForm');
  const itemResult = $('#itemResult');

  const checkoutForm = $('#checkoutForm');
  const checkoutSummary = $('.checkout-summary');

  const confirmationCard = $('#confirmationCard');
  const checkoutsBody = $('#checkoutsBody');

  const doneButton = $('#doneButton');

  if (
    !patronForm ||
    !patronResult ||
    !itemForm ||
    !itemResult ||
    !checkoutForm ||
    !checkoutSummary
  ) {
    console.error(
      'Checkout initialization failed: required HTML elements are missing.'
    );
    return;
  }

  // State
  const state = {
    patron: null,
    item: null,
    submitting: false,
    patronRequest: 0,
    itemRequest: 0
  };

  const submitButton = checkoutForm.querySelector(
    'button[type="submit"], input[type="submit"]'
  );

  // Clear displayed patron information
  function clearPatronDisplay() {
    setField(patronResult, 'name', '--');
    setField(patronResult, 'expiration', '--');
    setField(patronResult, 'activeCheckouts', '--');
    setField(patronResult, 'outstandingFines', '--');
    setField(patronResult, 'eligibility', '--');
  }

  // Clear displayed item information
  function clearItemDisplay() {
    setField(itemResult, 'itemCopyID', '--');
    setField(itemResult, 'itemType', '--');
    setField(itemResult, 'status', '--');
  }

  function showCheckoutError(message) {
    setError(checkoutSummary, message);
  }

  // Checkout Summary
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

    const patronInput =
      checkoutForm.elements.namedItem('patronID');

    const itemInput =
      checkoutForm.elements.namedItem('itemCopyID');

    if (patronInput) {
      patronInput.value = state.patron
        ? state.patron.patronID
        : '';
    }

    if (itemInput) {
      itemInput.value = state.item
        ? state.item.itemCopyID
        : '';
    }

    setError(checkoutSummary, '');
  }

  function setSubmitting(submitting) {
    state.submitting = submitting;

    if (submitButton) {
      submitButton.disabled = submitting;

      if (
        'value' in submitButton &&
        submitButton.tagName === 'INPUT'
      ) {
        submitButton.value = submitting
          ? 'Recording...'
          : 'Record Checkout';
      } else {
        submitButton.textContent = submitting
          ? 'Recording...'
          : 'Record Checkout';
      }
    }
  }

  // Find Patron
  patronForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const requestNumber = ++state.patronRequest;

    state.patron = null;
    clearPatronDisplay();
    updateSummary();
    setError(patronResult, '');

    const patronInput =
      patronForm.elements.namedItem('patronID');

    const patronID = patronInput
      ? patronInput.value.trim()
      : '';

    if (!patronID) {
      setError(
        patronResult,
        'Please enter a Patron ID.'
      );
      return;
    }

    try {
      const data = await fetchJSON(
        `script/findPatron.php?patronID=${encodeURIComponent(patronID)}`
      );

      // Ignore outdated search results.
      if (requestNumber !== state.patronRequest) return;

      if (!data.ok || !data.patron) {
        setError(
          patronResult,
          data.error || 'Patron not found.'
        );
        return;
      }

      // The updated PHP lookup must return eligibility data.
      const patron = data.patron;

      if (
        typeof patron.activeCheckouts === 'undefined' ||
        typeof patron.outstandingFines === 'undefined' ||
        typeof patron.eligible !== 'boolean'
      ) {
        setError(
          patronResult,
          'Patron eligibility details are missing. ' +
          'Check that the updated findPatron.php is deployed.'
        );
        return;
      }

      state.patron = patron;

      setField(
        patronResult,
        'name',
        patron.name
      );

      setField(
        patronResult,
        'expiration',
        patron.expiration
      );

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

      const reasons = Array.isArray(patron.eligibilityReasons)
        ? patron.eligibilityReasons
        : [];

      const eligibility = patron.eligible
        ? 'Eligible'
        : 'Denied — ' + (
            reasons.length
              ? reasons.join(', ')
              : 'Patron is not eligible'
          );

      setField(
        patronResult,
        'eligibility',
        eligibility
      );

      updateSummary();

    } catch (error) {
      if (requestNumber !== state.patronRequest) return;

      console.error('Patron lookup failed:', error);

      state.patron = null;
      clearPatronDisplay();
      updateSummary();

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

    const itemInput =
      itemForm.elements.namedItem('itemCopyID');

    const itemCopyID = itemInput
      ? itemInput.value.trim()
      : '';

    if (!itemCopyID) {
      setError(
        itemResult,
        'Please enter an Item Copy ID.'
      );
      return;
    }

    try {
      const data = await fetchJSON(
        `script/findItem.php?itemCopyID=${encodeURIComponent(itemCopyID)}`
      );

      if (requestNumber !== state.itemRequest) return;

      if (!data.ok || !data.item) {
        setError(
          itemResult,
          data.error || 'Item copy not found.'
        );
        return;
      }

      state.item = data.item;

      setField(
        itemResult,
        'itemCopyID',
        data.item.itemCopyID
      );

      setField(
        itemResult,
        'itemType',
        data.item.itemType
      );

      setField(
        itemResult,
        'status',
        data.item.status
      );

      if (!data.item.available) {
        setError(
          itemResult,
          'This copy is not available for checkout.'
        );
      }

      updateSummary();

    } catch (error) {
      if (requestNumber !== state.itemRequest) return;

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

  // Confirmation Display
  function showConfirmation(checkout) {
    if (!confirmationCard) {
      console.warn('Confirmation card not found.');
      return;
    }

    setField(
      confirmationCard,
      'checkoutID',
      checkout.checkoutID
    );

    setField(
      confirmationCard,
      'patron',
      checkout.patron
    );

    setField(
      confirmationCard,
      'itemCopyID',
      checkout.itemCopyID
    );

    setField(
      confirmationCard,
      'checkoutDate',
      checkout.checkoutDate
    );

    setField(
      confirmationCard,
      'dueDate',
      checkout.dueDate
    );

    confirmationCard.hidden = false;
  }

  // Current Checkouts Table
  function addCheckoutRow(checkout) {
    if (!checkoutsBody) {
      console.warn(
        'Current Checkouts table body not found.'
      );
      return;
    }

    const emptyRow =
      checkoutsBody.querySelector('.empty-row');

    if (emptyRow) {
      emptyRow.remove();
    }

    const row = document.createElement('tr');

    const values = [
      checkout.checkoutID,
      checkout.patron,
      checkout.itemCopyID,
      checkout.itemType,
      checkout.checkoutDate,
      checkout.dueDate
    ];

    values.forEach((value) => {
      const cell = document.createElement('td');
      cell.textContent = value ?? '--';
      row.appendChild(cell);
    });

    checkoutsBody.prepend(row);
  }

  // Reset After Successful Checkout
  function resetCheckoutSelection() {
    ++state.patronRequest;
    ++state.itemRequest;

    state.patron = null;
    state.item = null;

    patronForm.reset();
    itemForm.reset();

    clearPatronDisplay();
    clearItemDisplay();

    setError(patronResult, '');
    setError(itemResult, '');

    updateSummary();
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

    // Frontend eligibility check.
    // The backend still validates everything again.
    if (state.patron.expired) {
      showCheckoutError(
        'Cannot check out: patron membership is expired.'
      );
      return;
    }

    if (!state.patron.eligible) {
      const reasons = Array.isArray(
        state.patron.eligibilityReasons
      )
        ? state.patron.eligibilityReasons
        : [];

      showCheckoutError(
        'Cannot check out: ' +
        (
          reasons.length
            ? reasons.join(', ')
            : 'patron is not eligible'
        ) +
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

    const formData = new FormData(checkoutForm);

    setSubmitting(true);

    let data;

    // Keep the network request separate from UI rendering.
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

    const checkout = data.checkout;

    if (!checkout) {
      console.error(
        'Checkout succeeded but the response contains no checkout details.',
        data
      );

      showCheckoutError(
        'Checkout may have been recorded, but the confirmation ' +
        'details were missing. Check the database.'
      );

      setSubmitting(false);
      return;
    }

    // Reset first so the same selection cannot be submitted again.
    resetCheckoutSelection();
    setSubmitting(false);

    try {
      showConfirmation(checkout);
      addCheckoutRow(checkout);

      if (
        confirmationCard &&
        typeof confirmationCard.scrollIntoView === 'function'
      ) {
        confirmationCard.scrollIntoView({
          behavior: 'smooth',
          block: 'center'
        });
      }

    } catch (error) {
      console.error(
        'Checkout recorded, but the interface could not display it:',
        error
      );

      showCheckoutError(
        'Checkout was recorded successfully, but the display ' +
        'could not be updated. Checkout ID: ' +
        (checkout.checkoutID ?? 'unknown')
      );
    }
  });

  // Done Button
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

})();
