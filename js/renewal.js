(() => {
  'use strict';

  const lookupForm = document.getElementById('renewalLookupForm');
  const resultBox = document.getElementById('renewalResult');
  const renewalForm = document.getElementById('renewalForm');

  if (!lookupForm || !resultBox || !renewalForm) {
    return;
  }

  const itemInput = renewalForm.querySelector('input[name="itemCopyID"]');

  function setField(field, value) {
    const element = resultBox.querySelector(`[data-field="${field}"]`);
    if (element) {
      element.textContent = value ?? '--';
    }

    const summaryFieldMap = {
      patronName: 'patronDisplay',
      itemCopyID: 'summaryItemCopyID',
      itemType: 'summaryItemType',
      dueDate: 'summaryDueDate',
      renewalCount: 'summaryRenewalCount',
    };

    const summaryKey = summaryFieldMap[field];
    if (summaryKey) {
      const target = document.querySelector(`[data-field="${summaryKey}"]`);
      if (target) target.textContent = value ?? '--';
    }
  }

  function setError(message) {
    const element = resultBox.querySelector('[data-field="error"]');
    if (!element) return;
    element.textContent = message || '';
    element.hidden = !message;
  }

  async function fetchJSON(url, options = {}) {
    const response = await fetch(url, {
      cache: 'no-store',
      ...options,
    });

    const text = await response.text();
    let data;

    try {
      data = JSON.parse(text);
    } catch (error) {
      throw new Error('The server returned an invalid response.');
    }

    if (!data || typeof data !== 'object') {
      throw new Error('Unexpected server response.');
    }

    if (!response.ok || !data.ok) {
      throw new Error(data.error || 'Request failed.');
    }

    return data;
  }

  function clearFields() {
    ['patronName', 'author', 'title', 'itemCopyID', 'itemType', 'branchName', 'status', 'dueDate', 'renewalCount', 'error'].forEach((field) => {
      if (field === 'error') {
        setError('');
        return;
      }
      setField(field, '--');
    });
  }

  lookupForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearFields();

    const rawValue = lookupForm.elements.namedItem('itemCopyID')?.value?.trim() ?? '';
    if (!rawValue) {
      setError('Please enter an Item Copy ID.');
      return;
    }

    try {
      const data = await fetchJSON('script/findItem.php?itemCopyID=' + encodeURIComponent(rawValue));
      const item = data.item;
      if (!item) {
        setError('Item not found.');
        return;
      }

      const checkout = item.activeCheckout || {};
      setField('patronName', checkout.patronName || 'No active checkout');
      setField('author', item.author || 'Unknown');
      setField('title', item.title || '--');
      setField('itemCopyID', item.itemCopyID);
      setField('itemType', item.itemType || 'Unknown');
      setField('branchName', item.branchName || item.branchID || '--');
      setField('status', item.status || '--');
      setField('dueDate', checkout.dueDate || '--');
      setField('renewalCount', checkout.renewalCount ?? '--');
      setField('itemCopyID', item.itemCopyID);

      itemInput.value = String(item.itemCopyID);

      if (!checkout.checkoutID) {
        setError('No active checkout exists for this item copy.');
      }
    } catch (error) {
      setError(error.message || 'Unable to find item.');
    }
  });

  renewalForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    setError('');

    const itemCopyID = itemInput.value.trim();
    if (!itemCopyID) {
      setError('Find an item before renewing it.');
      return;
    }

    try {
      const data = await fetchJSON('script/renewItem.php', {
        method: 'POST',
        body: new FormData(renewalForm),
      });

      const checkout = data.checkout || {};
      setField('dueDate', checkout.dueDate || '--');
      setField('renewalCount', checkout.renewalCount ?? '--');
      setField('status', 'Renewed');
      alert(data.message || 'Renewal was successful.');
    } catch (error) {
      setError(error.message || 'Unable to renew the item.');
    }
  });
})();
