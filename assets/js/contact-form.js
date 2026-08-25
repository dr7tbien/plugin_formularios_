(function () {
    'use strict';

    document.querySelectorAll('.codepty-contact__form').forEach(function (form) {
        var contact = form.closest('.codepty-contact');
        var initialView = form.querySelector('.codepty-contact__initial');
        var verificationView = form.querySelector('.codepty-contact__verification');
        var successView = form.querySelector('.codepty-contact__success');
        var startButton = form.querySelector('.codepty-contact__start');
        var confirmButton = form.querySelector('.codepty-contact__confirm');
        var changeEmailButton = form.querySelector('.codepty-contact__change-email');
        var resendButton = form.querySelector('.codepty-contact__resend');
        var restartButton = form.querySelector('.codepty-contact__restart');
        var initialStatus = form.querySelector('.codepty-contact__initial-status');
        var verificationStatus = form.querySelector('.codepty-contact__verification-status');
        var verificationEmail = form.querySelector('.codepty-contact__verification-email');
        var codeRow = form.querySelector('.codepty-contact__code-row');
        var resendHelp = form.querySelector('.codepty-contact__resend-help');
        var codeInputs = Array.prototype.slice.call(form.querySelectorAll('.codepty-contact__code'));
        var email = form.querySelector('input[name="email"]');
        var submissionId = form.querySelector('input[name="submission_id"]');
        var originUrl = form.querySelector('input[name="origin_url"]');
        var authorizedAction = '';

        if (originUrl) {
            originUrl.value = window.location.href;
        }

        if (contact && isSmartphone()) {
            contact.classList.add('is-smartphone');
        }

        /**
         * isSmartphone - Detecta teléfonos sin clasificar tabletas como smartphones.
         *
         * @return {boolean} Indica si pueden mostrarse las alternativas móviles.
         */
        function isSmartphone() {
            if (navigator.userAgentData && navigator.userAgentData.mobile === true) {
                return true;
            }

            return /Android.+Mobile|iPhone|iPod|Windows Phone|IEMobile|Opera Mini|webOS|BlackBerry/i.test(navigator.userAgent || '');
        }

        /**
         * setBusy - Sincroniza el estado ocupado, el texto y la accesibilidad de un botón.
         *
         * @param {HTMLButtonElement} button Botón cuyo estado debe actualizarse.
         * @param {boolean} busy Indica si la operación sigue en curso.
         * @param {string} busyText Texto visible durante la espera.
         * @param {string} normalText Texto restaurado al terminar.
         * @return {void}
         */
        function setBusy(button, busy, busyText, normalText) {
            button.disabled = busy;
            button.classList.toggle('is-loading', busy);
            button.setAttribute('aria-busy', busy ? 'true' : 'false');
            if (busyText && normalText) {
                var label = button.querySelector('.codepty-contact__start-label');
                if (label) {
                    label.textContent = busy ? busyText : normalText;
                } else {
                    button.textContent = busy ? busyText : normalText;
                }
            }
        }

        /**
         * setInitialError - Muestra un error asociado al formulario inicial.
         *
         * @param {string} message Mensaje que debe anunciarse al visitante.
         * @return {void}
         */
        function setInitialError(message) {
            initialStatus.textContent = message || '';
        }

        /**
         * setVerificationStatus - Actualiza el aviso y el aspecto de las casillas de clave.
         *
         * @param {string} message Texto del estado de verificación.
         * @param {string} type Tipo visual: `error`, `success` o vacío.
         * @return {void}
         */
        function setVerificationStatus(message, type) {
            verificationStatus.textContent = message || '';
            verificationStatus.classList.toggle('is-error', type === 'error');
            verificationStatus.classList.toggle('is-success', type === 'success');
            codeInputs.forEach(function (input) {
                input.classList.toggle('is-error', type === 'error');
            });
        }

        /**
         * commonRequestBody - Construye la carga común para las operaciones AJAX del formulario.
         *
         * Incluye identidad del envío, página de origen, nonces, honeypot, marca temporal y datos visibles.
         *
         * @param {string} action Acción AJAX de WordPress que recibirá la petición.
         * @return {URLSearchParams} Carga codificada lista para enviar.
         */
        function commonRequestBody(action) {
            var body = new URLSearchParams();
            body.set('action', action);
            body.set('nonce', formulariosPWContact.nonce);
            body.set('submission_id', submissionId.value);
            ['name', 'phone', 'email', 'message', 'website', 'form_started', 'origin_url', 'codepty_contact_nonce'].forEach(function (name) {
                var field = form.querySelector('[name="' + name + '"]');
                body.set(name, field ? field.value : '');
            });
            return body;
        }

        /**
         * request - Ejecuta una operación AJAX y normaliza las respuestas de WordPress.
         *
         * @param {string} action Acción AJAX solicitada.
         * @param {Object<string, string>} [extra] Campos específicos de la operación.
         * @return {Promise<Object>} Datos de una respuesta satisfactoria.
         */
        function request(action, extra) {
            var body = commonRequestBody(action);
            Object.keys(extra || {}).forEach(function (key) {
                body.set(key, extra[key]);
            });

            return fetch(formulariosPWContact.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: body.toString()
            }).then(function (response) {
                return response.json().catch(function () {
                    throw new Error('El servidor no devolvió una respuesta válida.');
                });
            }).then(function (response) {
                if (!response.success) {
                    var error = new Error(response.data && response.data.message ? response.data.message : 'No se pudo completar la operación.');
                    error.reason = response.data && response.data.reason ? response.data.reason : '';
                    throw error;
                }
                return response.data || {};
            });
        }

        /**
         * clearCode - Vacía y rehabilita las cuatro casillas de verificación.
         *
         * @return {void}
         */
        function clearCode() {
            codeInputs.forEach(function (input) {
                input.value = '';
                input.disabled = false;
                input.classList.remove('is-error');
            });
        }

        /**
         * showVerification - Sustituye el formulario inicial por la pantalla de la clave.
         *
         * Mantiene los datos escritos en el DOM mientras el visitante confirma su email.
         *
         * @return {void}
         */
        function showVerification() {
            initialView.hidden = true;
            successView.hidden = true;
            verificationView.hidden = false;
            verificationEmail.textContent = email.value.trim();
            codeRow.hidden = false;
            resendHelp.hidden = false;
            clearCode();
            setVerificationStatus('', '');
            codeInputs[0].focus();
        }

        /**
         * showSuccess - Presenta la confirmación tras el procesamiento real del servidor.
         *
         * @param {Object} data Datos devueltos por el endpoint final.
         * @return {void}
         */
        function showSuccess(data) {
            initialView.hidden = true;
            verificationView.hidden = true;
            successView.hidden = false;
            successView.focus({preventScroll: true});
        }

        /**
         * sendCode - Solicita por email una clave temporal para esta consulta.
         *
         * @return {Promise<void>} Operación que termina al mostrar verificación o error.
         */
        function sendCode() {
            setBusy(startButton, true, 'Enviando clave…', 'Enviar consulta por email');
            setInitialError('');
            return request(formulariosPWContact.sendCodeAction).then(function (data) {
                showVerification();
            }).catch(function (error) {
                setInitialError(error.message);
            }).finally(function () {
                setBusy(startButton, false, 'Enviando clave…', 'Enviar consulta por email');
            });
        }

        startButton.addEventListener('click', function () {
            if (!form.reportValidity()) {
                return;
            }
            sendCode();
        });

        codeInputs.forEach(function (input, index) {
            input.addEventListener('input', function () {
                var entered = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                if (entered.length > 1) {
                    codeInputs.forEach(function (codeInput, codeIndex) {
                        codeInput.value = entered.charAt(codeIndex) || '';
                    });
                    codeInputs[Math.min(entered.length, 4) - 1].focus();
                    setVerificationStatus('', '');
                    return;
                }
                input.value = entered.slice(0, 1);
                setVerificationStatus('', '');
                if (input.value && codeInputs[index + 1]) {
                    codeInputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Backspace' && !input.value && codeInputs[index - 1]) {
                    codeInputs[index - 1].focus();
                }
                if (event.key === 'ArrowLeft' && codeInputs[index - 1]) {
                    event.preventDefault();
                    codeInputs[index - 1].focus();
                }
                if (event.key === 'ArrowRight' && codeInputs[index + 1]) {
                    event.preventDefault();
                    codeInputs[index + 1].focus();
                }
                if (event.key === 'Enter') {
                    event.preventDefault();
                    confirmButton.click();
                }
            });

            input.addEventListener('paste', function (event) {
                var pasted = (event.clipboardData || window.clipboardData).getData('text').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 4);
                if (!pasted) {
                    return;
                }
                event.preventDefault();
                codeInputs.forEach(function (codeInput, codeIndex) {
                    codeInput.value = pasted.charAt(codeIndex) || '';
                });
                codeInputs[Math.min(pasted.length, 4) - 1].focus();
                setVerificationStatus('', '');
            });
        });

        /**
         * submitAuthorized - Envía la consulta después de que el servidor autorizó el email.
         *
         * @return {Promise<void>} Operación que muestra éxito o permite reintentar el envío.
         */
        function submitAuthorized() {
            setBusy(confirmButton, true, 'Enviando tu consulta…', 'Reintentar envío');
            setVerificationStatus('', '');
            return request(authorizedAction).then(function (data) {
                showSuccess(data);
            }).catch(function (error) {
                setVerificationStatus(error.message, 'error');
                confirmButton.textContent = 'Reintentar envío';
            }).finally(function () {
                setBusy(confirmButton, false, 'Enviando tu consulta…', 'Reintentar envío');
            });
        }

        confirmButton.addEventListener('click', function () {
            if (authorizedAction) {
                submitAuthorized();
                return;
            }

            var code = codeInputs.map(function (input) { return input.value; }).join('');
            if (code.length !== 4) {
                setVerificationStatus('Completa los cuatro caracteres de la clave.', 'error');
                codeInputs.find(function (input) { return !input.value; }).focus();
                return;
            }

            setBusy(confirmButton, true, 'Validando clave…', 'Confirmar y enviar mensaje');
            setVerificationStatus('', '');
            request(formulariosPWContact.verifyCodeAction, {code: code}).then(function (data) {
                var actionField = document.createElement('input');
                actionField.type = 'hidden';
                actionField.name = 'action';
                actionField.value = data.submitAction;
                form.appendChild(actionField);
                authorizedAction = data.submitAction;
                codeInputs.forEach(function (input) { input.disabled = true; });
                return submitAuthorized();
            }).catch(function (error) {
                if (error.reason === 'expired' || error.reason === 'attempts') {
                    setVerificationStatus(error.message, 'error');
                    codeRow.hidden = true;
                    resendHelp.hidden = true;
                    confirmButton.textContent = 'Enviar otra clave';
                    confirmButton.dataset.mode = 'resend';
                } else {
                    clearCode();
                    setVerificationStatus(error.message, 'error');
                    codeInputs[0].focus();
                }
            }).finally(function () {
                if (!authorizedAction) {
                    setBusy(confirmButton, false, 'Validando clave…', confirmButton.dataset.mode === 'resend' ? 'Enviar otra clave' : 'Confirmar y enviar mensaje');
                }
            });
        });

        confirmButton.addEventListener('click', function (event) {
            if (confirmButton.dataset.mode !== 'resend') {
                return;
            }
            event.stopImmediatePropagation();
            resendButton.click();
        }, true);

        resendButton.addEventListener('click', function () {
            resendButton.disabled = true;
            setVerificationStatus('Enviando una clave nueva…', '');
            request(formulariosPWContact.sendCodeAction).then(function (data) {
                authorizedAction = '';
                form.querySelectorAll('input[name="action"]').forEach(function (field) { field.remove(); });
                codeRow.hidden = false;
                resendHelp.hidden = false;
                clearCode();
                confirmButton.dataset.mode = '';
                confirmButton.textContent = 'Confirmar y enviar mensaje';
                setVerificationStatus(data.message, 'success');
                codeInputs[0].focus();
            }).catch(function (error) {
                setVerificationStatus(error.message, 'error');
            }).finally(function () {
                resendButton.disabled = false;
            });
        });

        changeEmailButton.addEventListener('click', function () {
            request(formulariosPWContact.invalidateCodeAction).catch(function () {});
            authorizedAction = '';
            form.querySelectorAll('input[name="action"]').forEach(function (field) { field.remove(); });
            verificationView.hidden = true;
            initialView.hidden = false;
            codeRow.hidden = false;
            resendHelp.hidden = false;
            clearCode();
            setVerificationStatus('', '');
            email.focus();
        });

        restartButton.addEventListener('click', function () {
            window.location.reload();
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
        });

    });
}());
