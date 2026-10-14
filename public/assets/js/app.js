/*
 * Comportements communs : confirmation des actions sensibles, copie des informations de connexion
 */
(function () {
    'use strict';

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form.confirm');
        if (form && !window.confirm(form.dataset.confirm || 'Confirmer ?')) {
            event.preventDefault();
        }
    });

    document.querySelectorAll('code.copy').forEach(function (code) {
        if (!navigator.clipboard) {
            return;
        }
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'button ghost small copy-button';
        button.textContent = 'Copier';
        button.addEventListener('click', function () {
            navigator.clipboard.writeText(code.textContent).then(function () {
                button.textContent = 'Copié';
                setTimeout(function () { button.textContent = 'Copier'; }, 1500);
            });
        });
        code.after(button);
    });
})();
