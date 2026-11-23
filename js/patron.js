(() => {
    const form = document.querySelector('#patron-search-form');
    const message = document.querySelector('#patron-message');
    const record = document.querySelector('#patron-record');
    const submitButton = form?.querySelector('button[type="submit"]');

    if (!form || !message || !record || !submitButton) return;

    const currencyFormatter = new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD'
    });
    const requestInput = form.elements.namedItem('patronID');
    const checkoutBody = document.querySelector('#patron-checkouts-body');
    let activeRequest = 0;

    function setMessage(text, kind = '') {
        message.textContent = text;
        message.className = `patron-message${kind ? ` is-${kind}` : ''}`;
    }

    async function fetchJson(url) {
        const response = await fetch(url, { cache: 'no-store' });
        const data = await response.json();
        if (!response.ok || !data || typeof data !== 'object') {
            throw new Error(data?.error || 'Unable to retrieve patron information.');
        }
        return data;
    }

    function addCell(row, value) {
        const cell = document.createElement('td');
        cell.textContent = value || '--';
        row.append(cell);
    }

    function renderCheckouts(checkouts) {
        const wrapper = document.querySelector('#patron-checkouts-wrap');
        const emptyMessage = document.querySelector('#patron-checkouts-empty');
        const count = document.querySelector('#patron-loan-count');
        if (!checkoutBody || !wrapper || !emptyMessage || !count) return;

        checkoutBody.replaceChildren();
        checkouts.forEach((checkout) => {
            const row = document.createElement('tr');
            addCell(row, checkout.title || 'Untitled item');
            addCell(row, String(checkout.itemCopyID));
            addCell(row, checkout.checkoutDate);
            addCell(row, checkout.dueDate || 'Not set');
            addCell(row, String(checkout.renewalCount));
            addCell(row, checkout.homeBranch);
            checkoutBody.append(row);
        });

        count.textContent = String(checkouts.length);
        wrapper.hidden = checkouts.length === 0;
        emptyMessage.hidden = checkouts.length !== 0;
    }

    function renderPatron(patron, checkouts) {
        document.querySelector('#patron-name').textContent = patron.name;
        document.querySelector('#patron-id-value').textContent = String(patron.patronID);

        const membershipStatus = document.querySelector('#patron-membership-status');
        membershipStatus.textContent = patron.expired ? 'Expired' : 'Active';
        membershipStatus.className = patron.expired ? 'is-expired' : 'is-active';
        document.querySelector('#patron-membership-expiration').textContent =
            patron.expiration || 'Not set';

        document.querySelector('#patron-checkout-count').textContent =
            `${patron.activeCheckouts} / ${patron.checkoutLimit}`;
        document.querySelector('#patron-fine-balance').textContent =
            currencyFormatter.format(Number(patron.outstandingFines) || 0);

        const eligibility = document.querySelector('#patron-eligibility');
        eligibility.textContent = patron.eligible
            ? 'Eligible for checkout'
            : 'Checkout restricted';
        eligibility.className = `patron-status ${patron.eligible ? 'is-eligible' : 'is-restricted'}`;

        const reasons = document.querySelector('#patron-eligibility-reasons');
        reasons.textContent = (patron.eligibilityReasons || []).join(' · ');
        reasons.hidden = !reasons.textContent;

        renderCheckouts(checkouts);
        record.hidden = false;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const patronID = String(requestInput.value).trim();
        const requestID = ++activeRequest;
        submitButton.disabled = true;
        submitButton.textContent = 'Searching...';
        record.hidden = true;
        setMessage('Looking up patron...');

        try {
            const query = `patronID=${encodeURIComponent(patronID)}`;
            const [patronResult, checkoutResult] = await Promise.all([
                fetchJson(`script/findPatron.php?${query}`),
                fetchJson(`script/getPatronCheckouts.php?${query}`)
            ]);
            if (requestID !== activeRequest) return;
            if (!patronResult.ok) {
                throw new Error(patronResult.error || 'Patron not found.');
            }
            if (!checkoutResult.ok || !Array.isArray(checkoutResult.checkouts)) {
                throw new Error(checkoutResult.error || 'Unable to retrieve active checkouts.');
            }

            renderPatron(patronResult.patron, checkoutResult.checkouts);
            setMessage('Patron record loaded.', 'success');
        } catch (error) {
            if (requestID === activeRequest) {
                setMessage(error.message || 'Unable to retrieve patron information.', 'error');
            }
        } finally {
            if (requestID === activeRequest) {
                submitButton.disabled = false;
                submitButton.textContent = 'Find patron';
            }
        }
    });
})();