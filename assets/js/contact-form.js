(function () {
    'use strict';

    document.querySelectorAll('.codepty-contact__form').forEach(function (form) {
        form.addEventListener('submit', function () {
            var button = form.querySelector('.codepty-contact__submit');
            var progress = form.querySelector('.codepty-contact__progress');

            if (button) {
                button.disabled = true;
            }
            if (progress) {
                progress.textContent = 'Enviando consulta…';
            }
        });
    });
}());
