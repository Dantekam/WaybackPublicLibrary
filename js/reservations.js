
(() => {
    'use strict';

    const $ = id => document.getElementById(id);

    const form = $('createReservationForm');
    const rows = $('reservationRows');
    const message = $('reservationMessage');

    let reservations = [];

    function showMessage(text, error = false) {
        message.textContent = text;
        message.style.color = error ? '#b42318' : '#176b38';
    }

    async function api(url, options = {}) {
        const response = await fetch(url, {
            cache: 'no-store',
            ...options
        });

        const raw = await response.text();
        let data;

        try {
            data = JSON.parse(raw);
        } catch {
            throw new Error('Invalid server response.');
        }

        if (!response.ok || !data.ok) {
            throw new Error(data.error || 'Operation failed.');
        }

        return data;
    }

    function cell(row, value) {
        const td = document.createElement('td');
        td.textContent = value ?? '—';
        row.appendChild(td);
        return td;
    }

    function formatDate(value) {
        return value ? String(value) : '—';
    }

    function makeButton(label, action, reservationID) {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.dataset.action = action;
        button.dataset.id = reservationID;
        return button;
    }

    function render() {
        const query = $('reservationSearch')
            .value.trim().toLowerCase();

        const status = $('reservationStatusFilter').value;

        const filtered = reservations.filter(r => {
            if (status && r.status !== status) return false;

            if (!query) return true;

            return [
                r.reservationID,
                r.patronID,
                r.patronName,
                r.itemCopyID,
                r.title
            ].some(value =>
                String(value ?? '').toLowerCase().includes(query)
            );
        });

        rows.replaceChildren();

        if (!filtered.length) {
            const tr = document.createElement('tr');
            const td = cell(tr, 'No matching reservations.');
            td.colSpan = 11;
            rows.appendChild(tr);
            return;
        }

        for (const r of filtered) {
            const tr = document.createElement('tr');

            cell(tr, r.reservationID);
            cell(tr, `${r.patronName} (#${r.patronID})`);
            cell(tr, r.itemCopyID);
            cell(tr, r.title);
            cell(tr, r.pickupBranch);
            cell(tr, r.currentBranch);
            cell(tr, r.status);
            cell(tr, r.routingStatus);
            cell(tr, formatDate(r.readyAt));
            cell(tr, formatDate(r.expiresAt));

            const actions = document.createElement('td');

            if (r.status === 'Pending') {
                actions.append(
                    makeButton('Mark Ready', 'ready', r.reservationID),
                    makeButton('Cancel', 'cancel', r.reservationID)
                );
            }

            if (r.status === 'Ready') {
                actions.append(
                    makeButton('Fulfill Pickup', 'fulfill',
                        r.reservationID),
                    makeButton('Cancel', 'cancel', r.reservationID)
                );
            }

            if (r.status !== 'Pending' &&
                r.status !== 'Ready') {
                actions.textContent = '—';
            }

            tr.appendChild(actions);
            rows.appendChild(tr);
        }
    }

    async function load() {
        const data = await api('script/reservations.php');

        reservations = data.reservations || [];

        const select = $('reservationPickupBranch');
        const oldValue = select.value;

        select.replaceChildren(
            new Option('Select pickup branch', '')
        );

        for (const branch of data.branches || []) {
            select.add(
                new Option(branch.BranchName, branch.BranchID)
            );
        }

        select.value = oldValue;
        render();
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();

        const button = $('createReservationBtn');
        button.disabled = true;

        try {
            const data = new FormData(form);
            data.append('action', 'create');

            const result = await api(
                'script/reservations.php',
                { method: 'POST', body: data }
            );

            form.reset();
            await load();
            showMessage(result.message);

        } catch (error) {
            showMessage(error.message, true);

        } finally {
            button.disabled = false;
        }
    });

    rows.addEventListener('click', async event => {
        const button = event.target.closest(
            'button[data-action]'
        );

        if (!button) return;

        const action = button.dataset.action;
        const reservationID = button.dataset.id;

        if (!confirm(
            `Perform "${action}" on reservation #${reservationID}?`
        )) {
            return;
        }

        button.disabled = true;

        try {
            const data = new FormData();
            data.append('action', action);
            data.append('reservationID', reservationID);

            const result = await api(
                'script/reservations.php',
                { method: 'POST', body: data }
            );

            await load();
            showMessage(result.message);

        } catch (error) {
            showMessage(error.message, true);
            button.disabled = false;
        }
    });

    $('reservationSearch').addEventListener('input', render);

    $('reservationStatusFilter')
        .addEventListener('change', render);

    $('refreshReservations').addEventListener(
        'click',
        async () => {
            try {
                await load();
                showMessage('Reservations refreshed.');
            } catch (error) {
                showMessage(error.message, true);
            }
        }
    );

    load().catch(error => {
        showMessage(error.message, true);
    });
})();
