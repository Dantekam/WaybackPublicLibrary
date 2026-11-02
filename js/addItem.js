(function () {
    'use strict';
    const button = document.getElementById('addItemBtn');
    if (button) button.addEventListener('click', () => {
        window.location.href = 'add-library-item.php';
    });
})();
