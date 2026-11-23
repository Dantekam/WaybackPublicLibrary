(function () {
    'use strict';

    const searchForm    = document.getElementById('patron-search-form');
    const patronIdInput = document.getElementById('patron-id');
    const message       = document.getElementById('patron-message');
    const actionMessage = document.getElementById('patron-action-message');
    const record        = document.getElementById('patron-record');
    if (!searchForm) return;

    // --- Header / summary ---
    const nameEl         = document.getElementById('patron-name');
    const idEl           = document.getElementById('patron-id-value');
    const eligibilityEl  = document.getElementById('patron-eligibility');
    const memberStatusEl = document.getElementById('patron-membership-status');
    const memberExpEl    = document.getElementById('patron-membership-expiration');
    const checkoutCountEl= document.getElementById('patron-checkout-count');
    const fineBalanceEl  = document.getElementById('patron-fine-balance');
    const reasonsEl      = document.getElementById('patron-eligibility-reasons');

    // --- Checkouts ---
    const loansWrap      = document.getElementById('patron-checkouts-wrap');
    const loansEmpty     = document.getElementById('patron-checkouts-empty');
    const loansBody      = document.getElementById('patron-checkouts-body');
    const loanCountEl    = document.getElementById('patron-loan-count');

    // --- Fines ---
    const finesWrap      = document.getElementById('patron-fines-wrap');
    const finesEmpty     = document.getElementById('patron-fines-empty');
    const finesBody      = document.getElementById('patron-fines-body');
    const finesTotalEl   = document.getElementById('patron-fines-total');
    const payAllBtn      = document.getElementById('pay-all-fines-btn');

    // --- Membership ---
    const renewalCountEl = document.getElementById('patron-renewal-count');
    const renewMonths    = document.getElementById('patron-renew-months');
    const renewBtn       = document.getElementById('renew-membership-btn');

    let currentPatron = null;

    const money = n => '$' + Number(n).toFixed(2);

    function setMessage(el, text, isError = false) {
        if (!el) return;
        el.textContent = text;
        el.classList.toggle('is-error', isError);
    }

    async function lookupPatron(idOrName) {
        const res = await fetch(
            'patron-lookup.php?q=' + encodeURIComponent(idOrName),
            { credentials: 'same-origin' }
        );
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Lookup failed.');
        return data.patron;
    }

    function renderPatron(p) {
        currentPatron = p;
        record.hidden = false;

        // Header
        nameEl.textContent = p.name;
        idEl.textContent   = p.id;

        // Membership
        const expired = p.expiration &&
            new Date(p.expiration) < new Date(new Date().toDateString());
        memberStatusEl.textContent = expired ? 'Expired' : 'Active';
        memberStatusEl.classList.toggle('is-expired', expired);
        memberExpEl.textContent = p.expiration || '—';

        // Eligibility badge
        const hasBlockingFines = p.totalBalance > 0;
        const eligible = !expired && !hasBlockingFines;
        eligibilityEl.textContent = eligible ? 'Eligible' : 'Not eligible';
        eligibilityEl.classList.toggle('is-blocked', !eligible);

        // Eligibility reasons
        const reasons = [];
        if (expired) reasons.push('Membership expired');
        if (hasBlockingFines) reasons.push('Outstanding fines');
        if (reasons.length) {
            reasonsEl.textContent = reasons.join(' · ');
            reasonsEl.hidden = false;
        } else {
            reasonsEl.hidden = true;
        }

        // Counts / balance
        const activeCount = (p.checkouts || []).length;
        checkoutCountEl.textContent = activeCount;
        fineBalanceEl.textContent   = money(p.totalBalance);
        fineBalanceEl.classList.toggle('is-error', p.totalBalance > 0);

        // Renewal count
        renewalCountEl.textContent =
            `Renewed ${p.renewalCount} time${p.renewalCount === 1 ? '' : 's'}`;

        renderCheckouts(p.checkouts || []);
        renderFines(p.fines || []);
    }

    function renderCheckouts(list) {
        loansBody.innerHTML = '';
        loansWrap.hidden  = list.length === 0;
        loansEmpty.hidden = list.length !== 0;
        loanCountEl.textContent =
            list.length + (list.length === 1 ? ' item' : ' items');

        list.forEach(c => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td></td>
                <td>${c.copyId}</td>
                <td>${c.checkoutDate || '—'}</td>
                <td>${c.dueDate || '—'}</td>
                <td>—</td>
                <td>${c.homeBranch ?? '—'}</td>`;
            tr.querySelector('td:first-child').textContent = c.item || 'Untitled item';
            loansBody.appendChild(tr);
        });
    }

    function renderFines(fines) {
        finesBody.innerHTML = '';
        const open = fines.filter(f => f.balance > 0);
        finesWrap.hidden  = open.length === 0;
        finesEmpty.hidden = open.length !== 0;

        const total = open.reduce((s, f) => s + f.balance, 0);
        finesTotalEl.textContent = money(total);
        payAllBtn.disabled = open.length === 0;

        open.forEach(f => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td></td>
                <td>${money(f.assessed)}</td>
                <td>${money(f.paid)}</td>
                <td>${money(f.balance)}</td>
                <td><button type="button" class="patron-action-button"
                            data-fine-id="${f.id}">Pay…</button></td>`;
            tr.querySelector('td:first-child').textContent = f.item || 'Untitled item';

            tr.querySelector('button').addEventListener('click', () => {
                const amount = prompt(
                    `Amount to pay (balance ${money(f.balance)}):`,
                    f.balance.toFixed(2)
                );
                if (amount === null) return;
                const value = parseFloat(amount);
                if (!value || value <= 0) return;
                payFine(f.id, value);
            });

            finesBody.appendChild(tr);
        });
    }

    async function payFine(fineId, amount) {
        setMessage(actionMessage, 'Processing payment…');
        try {
            const res = await fetch('process-patron-action.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action:    'pay_fine',
                    patron_id: currentPatron.id,
                    fine_id:   fineId,
                    amount
                })
            });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Payment failed.');
            setMessage(actionMessage, `Paid ${money(data.amount_paid)}.`);
            await refresh();
        } catch (err) {
            setMessage(actionMessage, err.message, true);
        }
    }

    payAllBtn.addEventListener('click', async () => {
        if (!confirm('Mark all fines as paid in full?')) return;
        setMessage(actionMessage, 'Processing payment…');
        try {
            const res = await fetch('process-patron-action.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action:    'pay_all_fines',
                    patron_id: currentPatron.id
                })
            });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Payment failed.');
            setMessage(
                actionMessage,
                `Collected ${money(data.amount_paid)} across ${data.fines_paid} fine(s).`
            );
            await refresh();
        } catch (err) {
            setMessage(actionMessage, err.message, true);
        }
    });

    renewBtn.addEventListener('click', async () => {
        const months = parseInt(renewMonths.value, 10) || 12;
        setMessage(actionMessage, 'Renewing…');
        try {
            const res = await fetch('process-patron-action.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action:    'renew_membership',
                    patron_id: currentPatron.id,
                    months
                })
            });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Renewal failed.');
            setMessage(
                actionMessage,
                `Membership now expires ${data.new_expiration} ` +
                `(renewal #${data.renewal_count}).`
            );
            await refresh();
        } catch (err) {
            setMessage(actionMessage, err.message, true);
        }
    });

    async function refresh() {
        if (!currentPatron) return;
        const p = await lookupPatron(String(currentPatron.id));
        renderPatron(p);
    }

    searchForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = patronIdInput.value.trim();
        if (!id) return;

        setMessage(message, 'Loading…');
        record.hidden = true;
        setMessage(actionMessage, '');

        try {
            const p = await lookupPatron(id);
            setMessage(message, '');
            renderPatron(p);
        } catch (err) {
            setMessage(message, err.message, true);
        }
    });
})();