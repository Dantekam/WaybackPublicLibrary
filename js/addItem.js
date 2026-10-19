(function () {
    'use strict';

    const modal   = document.getElementById('addItemModal');
    const form    = document.getElementById('addItemForm');
    const msg     = document.getElementById('addItemMsg');
    const openBtn = document.getElementById('addItemBtn');
    const cancel  = document.getElementById('cancelAddItem');

    if (!modal || !form || !openBtn) {
        console.warn('addItem.js: required elements not found on page.');
        return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');

    function openModal() {
        form.reset();
        msg.textContent = '';
        msg.className = 'msg';
        modal.hidden = false;
        form.elements['itemCopyID'].focus();
    }

    function closeModal() {
        modal.hidden = true;
    }

    openBtn.addEventListener('click', openModal);
    cancel.addEventListener('click', closeModal);

    // Close on backdrop click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        msg.textContent = '';
        msg.className = 'msg';
        submitBtn.disabled = true;

        try {
            const res = await fetch('addItem.php', {
                method: 'POST',
                body: new FormData(form),
            });

            const json = await res.json();

            if (!json.ok) {
                msg.textContent = json.error || 'Failed to add item.';
                msg.classList.add('error');
                return;
            }

            msg.textContent =
                `Added item copy #${json.item.itemCopyID} (${json.item.itemType}, ${json.item.status}).`;
            msg.classList.add('success');

            setTimeout(() => {
                closeModal();
                // e.g. refresh a list here
            }, 1000);
        } catch (err) {
            msg.textContent = 'Network error — could not reach server.';
            msg.classList.add('error');
        } finally {
            submitBtn.disabled = false;
        }
    });
})();