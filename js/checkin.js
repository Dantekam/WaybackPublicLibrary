(() => {
  'use strict';

  const lookupForm = document.getElementById('checkinLookupForm');
  const lookupInput = lookupForm?.elements.namedItem('itemCopyID');
  const resultBox = document.getElementById('checkinResult');
  const checkinForm = document.getElementById('checkinForm');
  const branchSelect = document.getElementById('returnBranchID');
  const manualMode = document.getElementById('unscannableMode');
  const lookupButton = document.getElementById('findCheckinItem');
  const unscannableHelp = document.getElementById('unscannableHelp');

  if (!lookupForm || !lookupInput || !resultBox || !checkinForm || !branchSelect || !manualMode || !lookupButton || !unscannableHelp) {
    return;
  }

  const itemInput = checkinForm.querySelector('input[name="itemCopyID"]');

  function setField(field, value) {
    const element = resultBox.querySelector(`[data-field="${field}"]`);
    if (element) {
      element.textContent = value ?? '--';
    }

    const mapped = {
      itemCopyID: 'summaryCopyID',
      dueDate: 'summaryDueDate',
    };
    const summaryField = mapped[field];
    if (summaryField) {
      const target = document.querySelector(`[data-field="${summaryField}"]`);
      if (target) target.textContent = value ?? '--';
    }
  }

  function setError(message) {
    const element = resultBox.querySelector('[data-field="error"]');
    if (!element) return;
    element.textContent = message || '';
    element.hidden = !message;
  }

  function setMessage(message) {
    const element = resultBox.querySelector('[data-field="message"]');
    if (!element) return;
    element.textContent = message || '';
    element.hidden = !message;
  }

  function updateLookupMode() {
    if (manualMode.checked) {
      lookupButton.textContent = 'Find Manually Identified Item';
      lookupInput.placeholder = 'Type Item Copy ID from the item';
      unscannableHelp.textContent =
        'Enter the Item Copy ID printed on the item. After identification, complete the regular check-in.';
    } else {
      lookupButton.textContent = 'Find Item';
      lookupInput.placeholder = 'Enter Item Copy ID';
      unscannableHelp.textContent =
        'Barcode unreadable? Select manual identification and enter the Item Copy ID printed on the item.';
    }
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

  async function loadBranches() {
    try {
      const data = await fetchJSON('script/catalogData.php');
      branchSelect.replaceChildren(new Option('Select a branch', ''));

      if (Array.isArray(data.branches)) {
        data.branches.forEach((branch) => {
          branchSelect.appendChild(
            new Option(`${branch.BranchName} (#${branch.BranchID})`, String(branch.BranchID))
          );
        });
      }
    } catch (error) {
      setError(error.message || 'Unable to load branches.');
    }
  }

  function clearFields() {
    ['patronName', 'author', 'title', 'itemCopyID', 'itemType', 'branchName', 'dueDate', 'status'].forEach((field) => {
      setField(field, '--');
    });
    setError('');
    setMessage('');
    itemInput.value = '';
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

      setField('author', item.author || 'Unknown');
      setField('title', item.title || '--');
      setField('itemCopyID', item.itemCopyID);
      setField('itemType', item.itemType || 'Unknown');
      setField('branchName', item.branchName || item.branchID || '--');
      setField('status', item.status || '--');
      setField('dueDate', item.activeCheckout?.dueDate || '--');
      setField('patronName', item.activeCheckout?.patronName || 'No active checkout');
      itemInput.value = String(item.itemCopyID);

      if (!item.activeCheckout) {
        setError('No active checkout exists for this item copy.');
        return;
      }

      if (item.branchID) {
        branchSelect.value = String(item.branchID);
      }

      if (manualMode.checked) {
        setMessage('Item identified manually. Complete the regular check-in to record its return.');
      }
    } catch (error) {
      setError(error.message || 'Unable to find item.');
    }
  });

  checkinForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    setError('');

    const itemCopyID = itemInput.value.trim();
    const branchID = branchSelect.value;

    if (!itemCopyID) {
      setError('Find an item before checking it in.');
      return;
    }

    if (!branchID) {
      setError('Please select the return branch.');
      return;
    }

    const submitButton = checkinForm.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    try {
      const data = await fetchJSON('script/returnItem.php', {
        method: 'POST',
        body: new FormData(checkinForm),
      });

      setField('status', data.checkout?.status || 'Returned');
      setField('dueDate', data.checkout?.dueDate || '--');
      setField('branchName', data.checkout?.returnBranchID ? `Branch #${data.checkout.returnBranchID}` : '--');
      itemInput.value = '';
      setMessage(data.message || 'Item checked in successfully.');
    } catch (error) {
      setError(error.message || 'Unable to record the return.');
    } finally {
      submitButton.disabled = false;
    }
  });

  manualMode.addEventListener('change', updateLookupMode);
  updateLookupMode();
  loadBranches();
})();
