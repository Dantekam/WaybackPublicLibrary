(() => {
  'use strict';

  const lookupForm = document.getElementById('checkinLookupForm');
  const resultBox = document.getElementById('checkinResult');
  const checkinForm = document.getElementById('checkinForm');
  const branchSelect = document.getElementById('returnBranchID');

  if (!lookupForm || !resultBox || !checkinForm || !branchSelect) {
    return;
  }

  const itemInput = checkinForm.querySelector('input[name="itemCopyID"]');

  function setField(field, value) {
    const element = resultBox.querySelector(`[data-field="${field}"]`);
    if (element) {
      element.textContent = value ?? '--';
    }

    const summaryElement = document.querySelector(`[data-field="summary${field.charAt(0).toUpperCase() + field.slice(1)}"]`);
    if (summaryElement && field !== 'error') {
      const mapped = {
        itemCopyID: 'summaryCopyID',
        dueDate: 'summaryDueDate',
      };
      const key = mapped[field] || null;
      if (key) {
        const target = document.querySelector(`[data-field="${key}"]`);
        if (target) target.textContent = value ?? '--';
      }
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
    ['patronName', 'author', 'title', 'itemCopyID', 'itemType', 'branchName', 'dueDate', 'status', 'error'].forEach((field) => {
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

      setField('author', item.author || 'Unknown');
      setField('title', item.title || '--');
      setField('itemCopyID', item.itemCopyID);
      setField('itemType', item.itemType || 'Unknown');
      setField('branchName', item.branchName || item.branchID || '--');
      setField('status', item.status || '--');
      setField('dueDate', item.activeCheckout?.dueDate || '--');
      setField('patronName', item.activeCheckout?.patronName || 'No active checkout');
      const checkoutDueDate = item.activeCheckout?.dueDate || '--';
      setField('dueDate', checkoutDueDate);
      setField('itemCopyID', item.itemCopyID);
      const summaryCopy = document.querySelector('[data-field="summaryCopyID"]');
      const summaryDue = document.querySelector('[data-field="summaryDueDate"]');
      if (summaryCopy) summaryCopy.textContent = item.itemCopyID ?? '--';
      if (summaryDue) summaryDue.textContent = checkoutDueDate;

      itemInput.value = String(item.itemCopyID);

      if (!item.activeCheckout) {
        setError('No active checkout exists for this item copy.');
        return;
      }

      if (item.branchID) {
        branchSelect.value = String(item.branchID);
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

    const formData = new FormData(checkinForm);

    try {
      const data = await fetchJSON('script/returnItem.php', {
        method: 'POST',
        body: formData,
      });

      setField('status', data.checkout?.status || 'Returned');
      setField('dueDate', data.checkout?.dueDate || '--');
      setField('branchName', data.checkout?.returnBranchID ? `Branch #${data.checkout.returnBranchID}` : '--');
      alert(data.message || 'Item checked in successfully.');
    } catch (error) {
      setError(error.message || 'Unable to record the return.');
    }
  });

  loadBranches();
})();
