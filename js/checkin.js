(() => {
  'use strict';

  const lookupForm = document.getElementById('checkinLookupForm');
  const resultBox = document.getElementById('checkinResult');
  const checkinForm = document.getElementById('checkinForm');
  const branchSelect = document.getElementById('returnBranchID');

  if (!lookupForm || !resultBox || !checkinForm || !branchSelect) {
    return;
  }

  const itemInput =
    checkinForm.querySelector('input[name="itemCopyID"]');
  const submitButton =
    document.getElementById('checkinLibraryItem');

  function setField(field, value) {
    const target =
      document.querySelector(`[data-field="${field}"]`);

    if (target) target.textContent = value ?? '--';
  }

  function setError(message) {
    const target =
      resultBox.querySelector('[data-field="error"]');

    if (!target) return;

    target.textContent = message || '';
    target.hidden = !message;
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
    } catch {
      throw new Error('Server returned invalid JSON.');
    }

    if (!data || typeof data !== 'object') {
      throw new Error('Unexpected server response.');
    }

    if (!response.ok || !data.ok) {
      throw new Error(data.error || 'Request failed.');
    }

    return data;
  }

  function resetDetails() {
    const fields = [
      'patronName',
      'author',
      'title',
      'itemCopyID',
      'itemType',
      'branchName',
      'dueDate',
      'status',
      'summaryCopyID',
      'summaryDueDate',
      'summaryCheckinDate',
      'summaryDaysOverdue',
      'summaryFine',
      'summaryReturnBranch',
      'summaryRoutingInstruction'
    ];

    for (const field of fields) {
      setField(field, '--');
    }

    itemInput.value = '';
    setError('');
  }

  async function loadBranches() {
    try {
      const data =
        await fetchJSON('script/catalogData.php');

      branchSelect.replaceChildren(
        new Option('Select a branch', '')
      );

      for (const branch of data.branches || []) {
        branchSelect.appendChild(
          new Option(
            `${branch.BranchName} (#${branch.BranchID})`,
            String(branch.BranchID)
          )
        );
      }
    } catch (error) {
      setError(error.message);
    }
  }

  lookupForm.addEventListener('submit', async event => {
    event.preventDefault();
    resetDetails();

    const copyID =
      lookupForm.elements.namedItem('itemCopyID')
        ?.value?.trim() || '';

    if (!copyID) {
      setError('Please enter an Item Copy ID.');
      return;
    }

    try {
      const data = await fetchJSON(
        'script/findItem.php?itemCopyID=' +
        encodeURIComponent(copyID)
      );

      const item = data.item;

      if (!item) {
        throw new Error('Item not found.');
      }

      setField('author', item.author || 'Unknown');
      setField('title', item.title);
      setField('itemCopyID', item.itemCopyID);
      setField('itemType', item.itemType);
      setField(
        'branchName',
        item.homeBranchName ||
        (item.homeBranchID
          ? `Branch #${item.homeBranchID}`
          : 'Not assigned')
      );
      setField('status', item.status);
      setField(
        'patronName',
        item.activeCheckout?.patronName ||
        'No active checkout'
      );
      setField(
        'dueDate',
        item.activeCheckout?.dueDate || '--'
      );

      setField('summaryCopyID', item.itemCopyID);
      setField(
        'summaryDueDate',
        item.activeCheckout?.dueDate || '--'
      );

      if (!item.activeCheckout ||
          item.status !== 'CheckedOut') {
        setError(
          'This copy does not have an active checkout.'
        );
        return;
      }

      itemInput.value = String(item.itemCopyID);

      // Home branch is the default, but staff can
      // select the branch where the item was returned.
      if (item.homeBranchID) {
        branchSelect.value =
          String(item.homeBranchID);
      }

    } catch (error) {
      setError(error.message);
    }
  });

  checkinForm.addEventListener('submit', async event => {
    event.preventDefault();
    setError('');

    if (!itemInput.value) {
      setError('Find an active checkout first.');
      return;
    }

    if (!branchSelect.value) {
      setError('Select the actual return branch.');
      return;
    }

    const selectedBranch =
      branchSelect.selectedOptions[0]?.textContent ||
      branchSelect.value;

    if (!confirm(
      `Confirm return of copy #${itemInput.value} ` +
      `at ${selectedBranch}?`
    )) {
      return;
    }

    if (submitButton) submitButton.disabled = true;

    try {
      const data = await fetchJSON(
        'script/returnItem.php',
        {
          method: 'POST',
          body: new FormData(checkinForm)
        }
      );

      const checkout = data.checkout || {};

      setField('status', checkout.status || 'Processing');
      setField(
        'summaryCheckinDate',
        checkout.returnDate || '--'
      );
      setField(
        'summaryDaysOverdue',
        checkout.daysOverdue ?? 0
      );
      setField(
        'summaryFine',
        checkout.fineAssessed === null ||
        checkout.fineAssessed === undefined
          ? 'Not assessed'
          : String(checkout.fineAssessed)
      );
      setField(
        'summaryReturnBranch',
        checkout.returnBranchName || selectedBranch
      );
      setField(
        'summaryRoutingInstruction',
        checkout.routingInstruction ||
        'Check item status management.'
      );

      // The return succeeded; prevent accidental
      // duplicate check-in attempts.
      itemInput.value = '';

      alert(
        'Return recorded successfully.\n\n' +
        'Returned to: ' +
        (checkout.returnBranchName || selectedBranch) +
        '\n\nNext step: ' +
        (checkout.routingInstruction ||
         'Check item status management.')
      );

    } catch (error) {
      setError(error.message);
    } finally {
      if (submitButton) submitButton.disabled = false;
    }
  });

  resetDetails();
  loadBranches();
})();