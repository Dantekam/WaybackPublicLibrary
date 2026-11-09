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
  const lookupForm = document.getElementById('checkinLookupForm');
  const lookupInput = lookupForm?.elements.namedItem('itemCopyID');
  const resultBox = document.getElementById('checkinResult');
  const checkinForm = document.getElementById('checkinForm');
  const branchSelect = document.getElementById('returnBranchID');
  const manualMode = document.getElementById('unscannableMode');
  const lookupButton = document.getElementById('findCheckinItem');
  const unscannableHelp = document.getElementById('unscannableHelp');

  if (!lookupForm || !resultBox || !checkinForm ||
      !branchSelect || !confirmation || !messageBox) {
  if (!lookupForm || !lookupInput || !resultBox || !checkinForm || !branchSelect || !manualMode || !lookupButton || !unscannableHelp) {
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

      itemInput.value = String(item.itemCopyID);

      if (item.homeBranchID) {
        branchSelect.value =
          String(item.homeBranchID);
      }


      if (manualMode.checked) {
        setMessage('Item identified manually. Complete the regular check-in to record its return.');
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

    const submitButton = checkinForm.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    try {
      const data = await fetchJSON(
        'script/returnItem.php',
        {
          method: 'POST',
          body: formData
        }
      );
      const data = await fetchJSON('script/returnItem.php', {
        method: 'POST',
        body: new FormData(checkinForm),
      });

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

      setField('status', data.checkout?.status || 'Returned');
      setField('dueDate', data.checkout?.dueDate || '--');
      setField('branchName', data.checkout?.returnBranchID ? `Branch #${data.checkout.returnBranchID}` : '--');
      itemInput.value = '';
      setMessage(data.message || 'Item checked in successfully.');
    } catch (error) {
      hideConfirmation();

      showMessage(
        error.message ||
        'Could not confirm the return. Verify its status.',
        true
      );

    } finally {
      setBusy(false);
      setError(error.message || 'Unable to record the return.');
    } finally {
      submitButton.disabled = false;
    }
  });

  branchSelect.addEventListener('change', () => {
    if (!busy) hideConfirmation();
  });

  lookupForm.addEventListener('input', () => {
    if (!busy) hideConfirmation();
  });

  resetDetails();
  manualMode.addEventListener('change', updateLookupMode);
  updateLookupMode();
  loadBranches();
})();