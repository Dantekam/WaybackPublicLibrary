
(() => {
    'use strict';

    const $ = id => document.getElementById(id);

    const form = $('libraryItemForm');
    if (!form) return;

    const existingFields = $('existingFields');
    const newFields = $('newFields');
    const searchInput = $('catalogSearch');
    const searchButton = $('searchCatalogBtn');
    const results = $('catalogResults');
    const details = $('selectedCatalogDetails');
    const branchSelect = $('branchSelect');
    const branchNote = $('branchNote');
    const authorEntries = $('authorEntries');
    const addAuthorButton = $('addAuthorBtn');
    const message = $('libraryMessage');
    const saveButton = $('saveLibraryBtn');

    let catalogItems = [];
    let saving = false;

    function currentMode() {
        return form.querySelector(
            'input[name="mode"]:checked'
        )?.value || 'existing';
    }

    function showMessage(text, error = false) {
        message.textContent = text;
        message.className = error
            ? 'msg item-feedback error'
            : 'msg item-feedback success';
    }

    function localDate() {
        const now = new Date();

        return [
            now.getFullYear(),
            String(now.getMonth() + 1).padStart(2, '0'),
            String(now.getDate()).padStart(2, '0')
        ].join('-');
    }

    $('acquisitionDate').value = localDate();

    function updateMode() {
        const isNew = currentMode() === 'new';

        existingFields.hidden = isNew;
        newFields.hidden = !isNew;

        results.required = !isNew;

        const requiredNames = [
            'newItemID',
            'title',
            'itemType',
            'publisher',
            'libraryOfCongressCode',
            'cost'
        ];

        for (const name of requiredNames) {
            const input = newFields.querySelector(
                `[name="${name}"]`
            );

            if (input) input.required = isNew;
        }

        showMessage('');
    }

    for (const radio of form.querySelectorAll(
        'input[name="mode"]'
    )) {
        radio.addEventListener('change', updateMode);
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
            console.error('Invalid server response:', raw);
            throw new Error(
                'The server returned an invalid response.'
            );
        }

        if (!response.ok || !data.ok) {
            throw new Error(
                data.error || 'The operation failed.'
            );
        }

        return data;
    }

    async function loadBranches() {
        try {
            const data = await fetchJSON(
                'script/catalogData.php'
            );

            branchSelect.replaceChildren(
                new Option('Select branch', '')
            );

            for (const branch of data.branches || []) {
                branchSelect.add(
                    new Option(
                        `${branch.BranchName} (#${branch.BranchID})`,
                        branch.BranchID
                    )
                );
            }

            branchNote.textContent =
                'Select the branch receiving the physical copy. ' +
                'The new copy will begin in Processing at that branch.';

        } catch (error) {
            showMessage(error.message, true);
        }
    }

    async function searchCatalog() {
        const query = searchInput.value.trim();

        if (!query) {
            showMessage(
                'Enter a title or catalog Item ID.',
                true
            );
            return;
        }

        searchButton.disabled = true;

        try {
            const data = await fetchJSON(
                'script/catalogData.php?q=' +
                encodeURIComponent(query)
            );

            catalogItems = data.items || [];

            results.replaceChildren(
                new Option(
                    catalogItems.length
                        ? 'Select an item'
                        : 'No matching items',
                    ''
                )
            );

            for (const item of catalogItems) {
                results.add(
                    new Option(
                        `#${item.ItemID} — ${item.Title} (${item.ItemType})`,
                        item.ItemID
                    )
                );
            }

            details.textContent = catalogItems.length
                ? `${catalogItems.length} matching catalog item(s).`
                : 'No match found. You can create a new catalog item.';

            showMessage('');

        } catch (error) {
            showMessage(error.message, true);

        } finally {
            searchButton.disabled = false;
        }
    }

    searchButton.addEventListener(
        'click',
        searchCatalog
    );

    searchInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchCatalog();
        }
    });

    results.addEventListener('change', () => {
        const item = catalogItems.find(
            candidate =>
                String(candidate.ItemID) === results.value
        );

        if (!item) {
            details.textContent = '';
            return;
        }

        const publicationDate = item.PublicationDate
            ? String(item.PublicationDate).slice(0, 10)
            : 'Not recorded';

        details.textContent =
            `Title: ${item.Title} | ` +
            `Type: ${item.ItemType} | ` +
            `Publisher: ${item.Publisher || 'Unknown'} | ` +
            `Published: ${publicationDate}`;
    });

    function addAuthor(first = '', last = '') {
        const row = document.createElement('div');
        row.className = 'author-entry';

        const firstLabel = document.createElement('label');
        firstLabel.textContent = 'First Name';

        const firstInput = document.createElement('input');
        firstInput.type = 'text';
        firstInput.name = 'authorFirstName[]';
        firstInput.maxLength = 50;
        firstInput.value = first;

        firstLabel.appendChild(firstInput);

        const lastLabel = document.createElement('label');
        lastLabel.textContent = 'Last Name';

        const lastInput = document.createElement('input');
        lastInput.type = 'text';
        lastInput.name = 'authorLastName[]';
        lastInput.maxLength = 50;
        lastInput.value = last;

        lastLabel.appendChild(lastInput);

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'remove-author';
        removeButton.textContent = 'Remove';

        row.append(
            firstLabel,
            lastLabel,
            removeButton
        );

        authorEntries.appendChild(row);
    }

    addAuthorButton.addEventListener('click', () => {
        addAuthor();
    });

    authorEntries.addEventListener('click', event => {
        if (!event.target.classList.contains(
            'remove-author'
        )) {
            return;
        }

        const row = event.target.closest('.author-entry');

        if (row) row.remove();
    });

    function prepareSubmission() {
        const data = new FormData(form);

        if (currentMode() === 'existing') {
            if (!results.value) {
                throw new Error(
                    'Select an existing catalog item first.'
                );
            }

            // Ignore new-catalog fields in existing-copy mode.
            for (const input of newFields.querySelectorAll(
                'input, select, textarea'
            )) {
                if (input.name) data.delete(input.name);
            }

        } else {
            // Ignore catalog search selection in new-item mode.
            data.delete('existingItemID');
        }

        return data;
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();

        if (saving) return;

        let data;

        try {
            data = prepareSubmission();
        } catch (error) {
            showMessage(error.message, true);
            return;
        }

        saving = true;
        saveButton.disabled = true;
        saveButton.textContent = 'Saving...';

        try {
            const result = await fetchJSON(
                'script/saveLibraryItem.php',
                {
                    method: 'POST',
                    body: data
                }
            );

            showMessage(
                `Saved physical copy #${result.itemCopyID} ` +
                `for "${result.title}" ` +
                `(catalog item #${result.itemID}). ` +
                `Branch: ${result.branch}. ` +
                `Status: ${result.status}. ` +
                'Use Item Statuses to confirm shelving ' +
                'before checkout.'
            );

            form.elements.namedItem('itemCopyID').value = '';
            form.elements.namedItem('itemCopyID').focus();

        } catch (error) {
            showMessage(error.message, true);

        } finally {
            saving = false;
            saveButton.disabled = false;
            saveButton.textContent = 'Save Library Item';
        }
    });

    updateMode();
    loadBranches();
})();
