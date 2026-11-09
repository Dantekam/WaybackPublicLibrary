
(() => {
    'use strict';

    const $ = id => document.getElementById(id);

    const searchForm = $('statusSearchForm');
    const actionForm = $('statusActionForm');
    const tableBody = $('statusTableBody');
    const message = $('statusMessage');
    const selectAll = $('selectAllCopies');

    let inventory = [];

    function showMessage(text, error = false) {
        message.textContent = text;
        message.style.color = error ? '#b42318' : '#176b38';
    }

    async function requestJSON(url, options = {}) {
        const response = await fetch(url, {
            cache: 'no-store',
            ...options
        });

        const text = await response.text();
        let data;

        try {
            data = JSON.parse(text);
        } catch {
            throw new Error('Server returned an invalid response.');
        }

        if (!data.ok) {
            throw new Error(data.error || 'Request failed.');
        }

        return data;
    }

    function addCell(row, value) {
        const cell = document.createElement('td');
        cell.textContent = value ?? '—';
        row.appendChild(cell);
    }

    function fillBranches(branches) {
        for (const select of [
            $('statusBranch'),
            $('destinationFilter')
        ]) {
            const previous = select.value;
            const first = select.options[0].cloneNode(true);

            select.replaceChildren(first);

            for (const branch of branches) {
                const option = document.createElement('option');
                option.value = branch.id;
                option.textContent = branch.name;
                select.appendChild(option);
            }

            select.value = previous;
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
            checkbox.value = item.itemCopyID;
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

        tableBody.innerHTML =
            '<tr><td colspan="9">Loading inventory...</td></tr>';

        try {
            const data = await requestJSON(
                'script/itemStatuses.php?' + params.toString()
            );

            inventory = data.items || [];
            fillBranches(data.branches || []);
            renderInventory();

        } catch (error) {
            inventory = [];
            renderInventory();
            showMessage(error.message, true);
        }
    }

    searchForm.addEventListener('submit', event => {
        event.preventDefault();
        showMessage('');
        loadInventory();
    });

    $('resetStatusFilters').addEventListener('click', () => {
        searchForm.reset();
        showMessage('');
        loadInventory();
    });

    selectAll.addEventListener('change', () => {
        for (const checkbox of document.querySelectorAll(
            '.copy-checkbox'
        )) {
            checkbox.checked = selectAll.checked;
        }
    });

    actionForm.addEventListener('submit', async event => {
        event.preventDefault();

        const action = $('bulkAction').value;
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

        const actionName =
            $('bulkAction').selectedOptions[0].textContent;

        if (!confirm(
            `${actionName} for ${selected.length} selected copy/copies? ` +
            'Only confirm physical actions that staff have completed.'
        )) {
            return;
        }

        const formData = new FormData();
        formData.append('action', action);

        for (const id of selected) {
            formData.append('itemCopyIDs[]', id);
        }

        const button = $('applyStatusAction');
        button.disabled = true;

        try {
            const data = await requestJSON(
                'script/itemStatuses.php',
                {
                    method: 'POST',
                    body: formData
                }
            );

            await loadInventory();
            showMessage(data.message);
            $('bulkAction').value = '';

        } catch (error) {
            showMessage(error.message, true);

        } finally {
            button.disabled = false;
        }
    });

    loadInventory();
})();
