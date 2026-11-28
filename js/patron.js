(function () {
    'use strict';

    const renewBtn    = document.getElementById('renew-membership-btn');
    const renewMonths = document.getElementById('patron-renew-months');
    const statusEl    = document.getElementById('patron-action-message');
    if (!renewBtn || !renewMonths) return;

    const lastRenewedOn = renewBtn.dataset.lastRenewedOn || '';
    const patronId      = renewBtn.dataset.patronId || '';

    function localToday() {
        const d = new Date();
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    function setStatus(text, isError) {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.classList.toggle('is-error', !!isError);
    }

    renewBtn.addEventListener('click', async () => {
        const today = localToday();

        if (lastRenewedOn === today) {
            setStatus('Membership already renewed today. No changes made.');
            return;
        }

        const months = parseInt(renewMonths.value, 10) || 12;
        if (!patronId) {
            setStatus('Missing patron ID.', true);
            return;
        }

        renewBtn.disabled = true;
        setStatus('Renewing…');

        try {
            const res = await fetch('process-patron-action.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'renew_membership',
                    patron_id: parseInt(patronId, 10),
                    months
                })
            });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Renewal failed.');

            if (data.already_renewed) {
                setStatus('Membership already renewed today. No changes made.');
            } else {
                setStatus(`Membership now expires ${data.new_expiration}. Reloading…`);
                setTimeout(() => window.location.reload(), 800);
            }
        } catch (err) {
            setStatus(err.message, true);
        } finally {
            renewBtn.disabled = false;
        }
    });
})();