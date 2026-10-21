(() => {
  'use strict';

  // Helpers
  const $  = (sel, root = document) => root.querySelector(sel);

  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, options);
    const data = await res.json().catch(() => ({
      ok: false,
      error: 'Server returned an invalid response.',
    }));
    return data;
  }

  function setField(box, field, value) {
    const el = $(`[data-field="${field}"]`, box);
    if (el) el.textContent = value;
  }

  function setError(box, message) {
    const err = $('[data-field="error"]', box);
    if (!err) return;
    if (message) {
      err.textContent = message;
      err.hidden = false;
    } else {
      err.textContent = '';
      err.hidden = true;
    }
  }

  function escapeHTML(s) {
    return String(s).replace(/[&<>"']/g, (ch) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;',
      '"': '&quot;', "'": '&#39;',
    }[ch]));
  }

  // State
  const state = {
    patron: null,
    item:   null,
  };

  // Find Patron
  const patronForm   = $('#patronForm');
  const patronResult = $('#patronResult');

  patronForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    setError(patronResult, '');

    const patronID = patronForm.patronID.value.trim();
    if (!patronID) return;

    try {
      const data = await fetchJSON(
        `script/findPatron.php?patronID=${encodeURIComponent(patronID)}`
      );

      if (!data.ok) {
        state.patron = null;
        setField(patronResult, 'name', '--');
        setField(patronResult, 'expiration', '--');
        setError(patronResult, data.error || 'Patron not found.');
        updateSummary();
        return;
      }

      state.patron = data.patron;
      setField(patronResult, 'name', data.patron.name);
      setField(patronResult, 'expiration', data.patron.expiration);

      if (data.patron.expired) {
        setError(patronResult, 'Warning: this membership has expired.');
      }
      updateSummary();
    } catch (err) {
      state.patron = null;
      updateSummary();
      setError(patronResult, 'Network error — please try again.');
    }
  });

  // Find Item 
  const itemForm   = $('#itemForm');
  const itemResult = $('#itemResult');

  itemForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    setError(itemResult, '');

    const itemCopyID = itemForm.itemCopyID.value.trim();
    if (!itemCopyID) return;

    try {
      const data = await fetchJSON(
        `script/findItem.php?itemCopyID=${encodeURIComponent(itemCopyID)}`
      );

      if (!data.ok) {
        state.item = null;
        setField(itemResult, 'itemCopyID', '--');
        setField(itemResult, 'itemType', '--');
        setField(itemResult, 'status', '--');
        setError(itemResult, data.error || 'Item not found.');
        updateSummary();
        return;
      }

      state.item = data.item;
      setField(itemResult, 'itemCopyID', data.item.itemCopyID);
      setField(itemResult, 'itemType', data.item.itemType);
      setField(itemResult, 'status', data.item.status);

      if (!data.item.available) {
        setError(itemResult, 'This copy is not available for checkout.');
      }
      updateSummary();
    } catch (err) {
      setError(itemResult, 'Network error — please try again.');
    }
  });

  // Checkout Summary 
  const checkoutSummary = $('.checkout-summary');
  const checkoutForm    = $('#checkoutForm');

  function updateSummary() {
    setField(checkoutSummary, 'patronName', state.patron ? state.patron.name : '--');
    setField(checkoutSummary, 'itemCopyID', state.item ? state.item.itemCopyID : '--');
    setField(checkoutSummary, 'itemType',   state.item ? state.item.itemType   : '--');

    checkoutForm.patronID.value   = state.patron ? state.patron.patronID : '';
    checkoutForm.itemCopyID.value = state.item   ? state.item.itemCopyID : '';

    setError(checkoutSummary, '');
  }

  // Record Checkout 
  const confirmationCard = $('#confirmationCard');
  const checkoutsBody    = $('#checkoutsBody');

  checkoutForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    setError(checkoutSummary, '');

    if (!state.patron || !state.item) {
      setError(checkoutSummary, 'Please select a patron and an item first.');
      return;
    }
    if (state.patron.expired) {
      setError(checkoutSummary, 'Cannot check out: patron membership is expired.');
      return;
    }
    if (!state.item.available) {
      setError(checkoutSummary, 'Cannot check out: item is not available.');
      return;
    }

    const formData = new FormData(checkoutForm);

    try {
      const data = await fetchJSON('script/recordCheckout.php', {
        method: 'POST',
        body: formData,
      });

      if (!data.ok) {
        setError(checkoutSummary, data.error || 'Checkout failed.');
        return;
      }

      const c = data.checkout;

      // Fill confirmation card
      setField(confirmationCard, 'checkoutID',   c.checkoutID);
      setField(confirmationCard, 'patron',       c.patron);
      setField(confirmationCard, 'itemCopyID',   c.itemCopyID);
      setField(confirmationCard, 'checkoutDate', c.checkoutDate);
      setField(confirmationCard, 'dueDate',      c.dueDate);
      confirmationCard.hidden = false;

      // Add row to current checkouts table
      addCheckoutRow(c);

      // Reset form and state so the user doesn't double-submit
      patronForm.reset();
      itemForm.reset();
      setField(patronResult, 'name', '--');
      setField(patronResult, 'expiration', '--');
      setField(itemResult, 'itemCopyID', '--');
      setField(itemResult, 'itemType', '--');
      setField(itemResult, 'status', '--');
      state.patron = null;
      state.item   = null;
      updateSummary();

      // Scroll the confirmation into view
      confirmationCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } catch (err) {
      setError(checkoutSummary, 'Network error — please try again.');
    }
  });

  // Current Checkouts table 
  function addCheckoutRow(c) {
    const emptyRow = checkoutsBody.querySelector('.empty-row');
    if (emptyRow) emptyRow.remove();

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${escapeHTML(c.checkoutID)}</td>
      <td>${escapeHTML(c.patron)}</td>
      <td>${escapeHTML(c.ID)}</td>
      <td>${escapeHTML(c.itemType)}</td>
      <td>${escapeHTML(c.checkoutDate)}</td>
      <td>${escapeHTML(c.dueDate)}</td>
    `;
    checkoutsBody.prepend(tr);
  }

  // Done button 
  $('#doneButton').addEventListener('click', () => {
    confirmationCard.hidden = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

})();
