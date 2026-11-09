(() => {
  'use strict';

  const $ = id => document.getElementById(id);

  const lookupForm = $('checkinLookupForm');
  const resultBox = $('checkinResult');
  const checkinForm = $('checkinForm');
  const branchSelect = $('returnBranchID');
  const submitButton = $('checkinLibraryItem');

  const confirmation = $('checkinConfirmation');
  const confirmationText = $('checkinConfirmationText');
  const confirmButton = $('confirmCheckinBtn');
  const cancelButton = $('cancelCheckinBtn');
  const messageBox = $('checkinMessage');

  if (!lookupForm || !resultBox || !checkinForm ||
      !branchSelect || !confirmation || !messageBox) {
    return;
  }

  const itemInput =
    checkinForm.querySelector('input[name="itemCopyID"]');

  let pendingReturn = null;
  let busy = false;

  function setField(field, value) {
    const target =
      document.querySelector(`[data-field="${field}"]`);

    if (target) {
      target.textContent = value ?? '--';
    }
  }

  function setError(message) {
    const target =
      resultBox.querySelector('[data-field="error"]');

    if (!target) return;

    target.textContent = message || '';
    target.hidden = !message;
  }

  function showMessage(text, error = false) {
    messageBox.textContent = text;
    messageBox.className =
      'operation-message ' + (error ? 'error' : 'success');
    messageBox.hidden = !text;
  }

  function hideConfirmation() {
    pendingReturn = null;
    confirmation.hidden = true;
    confirmationText.textContent = '';
  }

  function setBusy(value) {
    busy = value;

    if (submitButton) submitButton.disabled = value;
    if (confirmButton) confirmButton.disabled = value;
    if (cancelButton) cancelButton.disabled = value;

    branchSelect.disabled = value;
  }

  async function fetchJSON(url, options = {}) {
    const response = await fetch(url, {
      cache: 'no-store',
      ...options
    });

    const raw = await response.text();
    let data;

    try {
      data = JSON.parse(raw);
    } catch {
      throw new Error('Server returned invalid JSON.');
    }

    if (!data || !response.ok || !data.ok) {
      throw new Error(
        data?.error || 'The request could not be completed.'
      );
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
    showMessage('');
    hideConfirmation();
  }

  async function loadBranches() {
    try {
      const data = await fetchJSON(
        'script/catalogData.php'
      );

      branchSelect.replaceChildren(
        new Option('Select a branch', '')
      );

      for (const branch of data.branches || []) {
        branchSelect.add(
          new Option(
            `${branch.BranchName} (#${branch.BranchID})`,
            String(branch.BranchID)
          )
        );
      }

    } catch (error) {
      showMessage(error.message, true);
    }
  }

  lookupForm.addEventListener('submit', async event => {
    event.preventDefault();

    if (busy) return;

    const copyID =
      lookupForm.elements.namedItem('itemCopyID')
        ?.value?.trim() || '';

    resetDetails();

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

      if (item.homeBranchID) {
        branchSelect.value =
          String(item.homeBranchID);
      }

    } catch (error) {
      setError(error.message);
    }
  });

  checkinForm.addEventListener('submit', event => {
    event.preventDefault();

    if (busy) return;

    setError('');
    showMessage('');
    hideConfirmation();

    if (!itemInput.value) {
      setError('Find an active checkout first.');
      return;
    }

    if (!branchSelect.value) {
      setError('Select the actual return branch.');
      return;
    }

    const copyID = itemInput.value;
    const branchID = branchSelect.value;

    const branchName =
      branchSelect.selectedOptions[0]?.textContent ||
      branchID;

    pendingReturn = {
      copyID,
      branchID,
      branchName
    };

    confirmationText.textContent =
      `Return copy #${copyID} at ${branchName}? ` +
      'Confirm only after receiving the physical item.';

    confirmation.hidden = false;
    confirmation.scrollIntoView({
      behavior: 'smooth',
      block: 'nearest'
    });
  });

  cancelButton.addEventListener('click', () => {
    if (!busy) {
      hideConfirmation();
    }
  });

  confirmButton.addEventListener('click', async () => {
    if (busy || !pendingReturn) return;

    const request = { ...pendingReturn };

    // A user may change the selected copy or branch
    // after opening the confirmation.
    if (itemInput.value !== request.copyID ||
        branchSelect.value !== request.branchID) {
      hideConfirmation();
      showMessage(
        'The return details changed. Please confirm again.',
        true
      );
      return;
    }

    const formData = new FormData();
    formData.append('itemCopyID', request.copyID);
    formData.append('returnBranchID', request.branchID);

    setBusy(true);

    try {
      const data = await fetchJSON(
        'script/returnItem.php',
        {
          method: 'POST',
          body: formData
        }
      );

      const checkout = data.checkout || {};

      setField(
        'status',
        checkout.status || 'Processing'
      );

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
        checkout.fineAssessed == null
          ? 'Not assessed'
          : String(checkout.fineAssessed)
      );

      const returnedTo =
        checkout.returnBranchName ||
        request.branchName;

      const nextStep =
        checkout.routingInstruction ||
        'Check Item Status Management.';

      setField('summaryReturnBranch', returnedTo);
      setField('summaryRoutingInstruction', nextStep);

      // Prevent a second submission for the same loan.
      itemInput.value = '';
      hideConfirmation();

      showMessage(
        `Return recorded successfully. ` +
        `Copy #${request.copyID} was returned to ` +
        `${returnedTo}. Next step: ${nextStep}`
      );

    } catch (error) {
      hideConfirmation();

      showMessage(
        error.message ||
        'Could not confirm the return. Verify its status.',
        true
      );

    } finally {
      setBusy(false);
    }
  });

  branchSelect.addEventListener('change', () => {
    if (!busy) hideConfirmation();
  });

  lookupForm.addEventListener('input', () => {
    if (!busy) hideConfirmation();
  });

  resetDetails();
  loadBranches();
})();