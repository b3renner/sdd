/**
 * Captura de campos vazios no cliente, antes do envio.
 * Requisito: o formulário permanece funcional sem JS — o servidor valida novamente.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form    = document.getElementById('login-form');
        if (!form) return;

        var email   = form.querySelector('#email');
        var password = form.querySelector('#password');
        var box     = document.getElementById('client-error');

        function show(msg, focusEl) {
            if (!box) return;
            box.textContent = msg;
            box.hidden = false;
            if (focusEl && typeof focusEl.focus === 'function') focusEl.focus();
        }

        function hide() {
            if (!box) return;
            box.hidden = true;
            box.textContent = '';
        }

        form.addEventListener('submit', function (event) {
            var emailEmpty    = !email || email.value.trim() === '';
            var passwordEmpty = !password || password.value === '';

            if (emailEmpty || passwordEmpty) {
                event.preventDefault();
                if (emailEmpty && passwordEmpty) {
                    show('Preencha e-mail e senha.', email);
                } else if (emailEmpty) {
                    show('Informe seu e-mail.', email);
                } else {
                    show('Informe sua senha.', password);
                }
                return;
            }

            hide();
        });

        // Limpa a mensagem ao começar a digitar
        [email, password].forEach(function (el) {
            if (!el) return;
            el.addEventListener('input', hide);
        });
    });
})();
