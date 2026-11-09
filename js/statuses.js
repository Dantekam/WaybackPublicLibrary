
(() => {
    'use strict';

    const $ = id => document.getElementById(id);

    const searchForm = $('statusSearchForm');
    const actionForm = $('statusActionForm');
    const tableBody = $('statusTableBody');
    const message = $('statusMessage');
    const selectAll = $('selectAllCopies');

    const bulkAction = $('bulkAction');
    const destinationSelect = $('routeDestinationBranch');

    const confirmation = $('statusConfirmation');
    const confirmationText = $('statusConfirmationText');
    const confirmButton = $('confirmStatusAction');
    const cancelButton = $('cancelStatusAction');
    const applyButton = $('applyStatusAction');

    let inventory = [];
    let pendingAction = null;
    let busy = false;

    function showMessage(text, error = false) {
        message.textContent = text;
        message.style.color = error ? '#b42318' : '#176b38';
    }

    function hideConfirmation() {
        confirmation.hidden = true;
        pendingAction = null;
    }

    function setBusy(value) {
        busy = value;
        applyButton.disabled = value;
        confirmButton.disabled = value;
        cancelButton.disabled = value;
    }

    async function requestJSON(url, options = {}) {
        const response = await fetch(url, {
            cache: 'no-store',
            ...options
        });

        const responseText = await response.text();
        let data;

        try {
            data = JSON.parse(responseText);
        } catch {
            throw new Error(
                'Server returned an invalid response. ' +
                'Check the PHP script and server logs.'
            );
        }

        if (!response.ok || !data.ok) {
            throw new Error(
                data.error || 'The request could not be completed.'
            );
        }

        return data;
    }

    function addCell(row, value) {
        const cell = document.createElement('td');
        cell.textContent = value ?? '—';
        row.appendChild(cell);
    }

    function fillBranches(branches) {
        const selects = [
            $('statusBranch'),
            $('destinationFilter'),
            destinationSelect
        ];

        for (const select of selects) {
            const previousValue = select.value;
            const firstOption = select.options[0].cloneNode(true);

            select.replaceChildren(firstOption);

            for (const branch of branches) {
                const option = document.createElement('option');
                option.value = String(branch.id);
                option.textContent = branch.name;
                select.appendChild(option);
            }

            if (Array.from(select.options).some(
                option => option.value === previousValue
            )) {
                select.value = previousValue;
            }
        }
    }

    function renderInventory() {
        tableBody.replaceChildren();
        selectAll.checked = false;

        $('statusCount').textContent =
            `${inventory.length} copy/copies displayed`;

        if (inventory.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');

            cell.colSpan = 9;
            cell.textContent = 'No matching copies found.';

            row.appendChild(cell);
            tableBody.appendChild(row);
            return;
        }

        for (const item of inventory) {
            const row = document.createElement('tr');

            const selectCell = document.createElement('td');
            const checkbox = document.createElement('input');

            checkbox.type = 'checkbox';
            checkbox.className = 'copy-checkbox';
            checkbox.value = String(item.itemCopyID);

            checkbox.setAttribute(
                'aria-label',
                `Select copy ${item.itemCopyID}`
            );

            selectCell.appendChild(checkbox);
            row.appendChild(selectCell);

            addCell(row, item.itemCopyID);
            addCell(row, item.title);
            addCell(row, item.currentBranch);
            addCell(row, item.homeBranch);
            addCell(row, item.destinationBranch);
            addCell(row, item.status);
            addCell(row, item.routingStatus);
            addCell(row, item.shelfLocation);

            tableBody.appendChild(row);
        }
    }

    async function loadInventory() {
        const params = new URLSearchParams({
            search: $('statusSearch').value.trim(),
            branchID: $('statusBranch').value,
            status: $('statusFilter').value,
            routingStatus: $('routingFilter').value,
            destinationBranchID: $('destinationFilter').value
        });

        tableBody.replaceChildren();

        const row = document.createElement('tr');
        const cell = document.createElement('td');

        cell.colSpan = 9;
        cell.textContent = 'Loading inventory...';

        row.appendChild(cell);
        tableBody.appendChild(row);

        try {
            const data = await requestJSON(
                'script/itemStatuses.php?' + params.toString()
            );

            inventory = data.items || [];

            fillBranches(data.branches || []);
            renderInventory();

            return true;

        } catch (error) {
            inventory = [];
            renderInventory();
            showMessage(error.message, true);
            return false;
        }
    }

    function updateActionFields() {
        const routing = bulkAction.value === 'routeToBranch';

        destinationSelect.disabled = !routing;
        destinationSelect.required = routing;

        if (!routing) {
            destinationSelect.value = '';
        }

        hideConfirmation();
    }

    searchForm.addEventListener('submit', event => {
        event.preventDefault();

        hideConfirmation();
        showMessage('');
        loadInventory();
    });

    $('resetStatusFilters').addEventListener('click', () => {
        searchForm.reset();
        hideConfirmation();
        showMessage('');
        loadInventory();
    });

    bulkAction.addEventListener('change', updateActionFields);

    destinationSelect.addEventListener('change', () => {
        hideConfirmation();
    });

    selectAll.addEventListener('change', () => {
        hideConfirmation();

        for (const checkbox of document.querySelectorAll(
            '.copy-checkbox'
        )) {
            checkbox.checked = selectAll.checked;
        }
    });

    tableBody.addEventListener('change', event => {
        if (event.target.matches('.copy-checkbox')) {
            hideConfirmation();

            const boxes = Array.from(
                document.querySelectorAll('.copy-checkbox')
            );

            selectAll.checked =
                boxes.length > 0 &&
                boxes.every(box => box.checked);
        }
    });

    actionForm.addEventListener('submit', event => {
        event.preventDefault();

        if (busy) return;

        hideConfirmation();
        showMessage('');

        const action = bulkAction.value;

        const selected = Array.from(
            document.querySelectorAll('.copy-checkbox:checked')
        ).map(input => input.value);

        if (!action || selected.length === 0) {
            showMessage(
                'Choose an action and select at least one copy.',
                true
            );
            return;
        }

        const destinationBranchID = destinationSelect.value;

        if (action === 'routeToBranch' &&
            !destinationBranchID) {
            showMessage(
                'Select a destination branch before routing.',
                true
            );
            return;
        }

        const actionName =
            bulkAction.selectedOptions[0].textContent.trim();

        let description =
            `${actionName} for ${selected.length} selected ` +
            `copy/copies (${selected.join(', ')})?`;

        if (action === 'routeToBranch') {
            const destinationName =
                destinationSelect.selectedOptions[0].textContent;

            description +=
                ` Destination: ${destinationName}. ` +
                'This schedules transport but does not ' +
                'change the current branch yet.';
        } else if (action === 'startTransport') {
            description +=
                ' Confirm only after the items physically depart.';
        } else if (action === 'confirmArrival') {
            description +=
                ' Confirm only after staff receive the items ' +
                'at their destination.';
        } else if (action === 'confirmShelved') {
            description +=
                ' Confirm only after the items are physically shelved.';
        }

        pendingAction = {
            action,
            selected,
            destinationBranchID
        };

        confirmationText.textContent = description;
        confirmation.hidden = false;

        confirmation.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    });

    cancelButton.addEventListener('click', () => {
        if (busy) return;

        hideConfirmation();
        showMessage('Inventory action cancelled.');
    });

    confirmButton.addEventListener('click', async () => {
        if (busy || !pendingAction) return;

        const request = pendingAction;

        const formData = new FormData();
        formData.append('action', request.action);

        if (request.action === 'routeToBranch') {
            formData.append(
                'destinationBranchID',
                request.destinationBranchID
            );
        }

        for (const id of request.selected) {
            formData.append('itemCopyIDs[]', id);
        }

        setBusy(true);
        showMessage('');

        try {
            const data = await requestJSON(
                'script/itemStatuses.php',
                {
                    method: 'POST',
                    body: formData
                }
            );

            hideConfirmation();

            bulkAction.value = '';
            updateActionFields();

            const loaded = await loadInventory();

            if (loaded) {
                showMessage(data.message);
            }

        } catch (error) {
            showMessage(error.message, true);
            hideConfirmation();

        } finally {
            setBusy(false);
        }
    });

    updateActionFields();
    loadInventory();
})();
