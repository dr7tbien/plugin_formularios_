<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Contact_Form - Coordina renderizado, verificación y envío del contacto público.
 *
 * El formulario verifica el email y entrega la consulta directamente al correo configurado
 * sin conservar el contenido en WordPress.
 */
final class Formularios_PW_Contact_Form
{
    private const SHORTCODE = 'codepty_formulario_contacto';
    private const ACTION = 'formularios_pw_submit_contact';
    private const SEND_CODE_ACTION = 'formularios_pw_send_contact_code';
    private const VERIFY_CODE_ACTION = 'formularios_pw_verify_contact_code';
    private const INVALIDATE_CODE_ACTION = 'formularios_pw_invalidate_contact_code';
    private const VERIFICATION_NONCE_ACTION = 'formularios_pw_contact_verification';
    private const CODE_LIFETIME = 10 * MINUTE_IN_SECONDS;
    private const VERIFICATION_LIFETIME = 15 * MINUTE_IN_SECONDS;
    private const MAX_CODE_ATTEMPTS = 5;
    private const CODE_ALPHABET = '234679ACDEFGHJKMNPQRTUVWXYZ';
    private const MIN_FILL_SECONDS = 3;
    private const MAX_FORM_AGE_SECONDS = 2 * DAY_IN_SECONDS;
    private static $instance = 0;
    private static $late_styles_printed = false;

    /**
     * register - Registra shortcode, endpoints públicos y carga de recursos.
     *
     * @return void
     */
    public function register(): void
    {
        add_shortcode(self::SHORTCODE, array($this, 'render'));
        add_action('wp_ajax_' . self::ACTION, array($this, 'handle_submit'));
        add_action('wp_ajax_nopriv_' . self::ACTION, array($this, 'handle_submit'));
        add_action('wp_ajax_' . self::SEND_CODE_ACTION, array($this, 'handle_send_code'));
        add_action('wp_ajax_nopriv_' . self::SEND_CODE_ACTION, array($this, 'handle_send_code'));
        add_action('wp_ajax_' . self::VERIFY_CODE_ACTION, array($this, 'handle_verify_code'));
        add_action('wp_ajax_nopriv_' . self::VERIFY_CODE_ACTION, array($this, 'handle_verify_code'));
        add_action('wp_ajax_' . self::INVALIDATE_CODE_ACTION, array($this, 'handle_invalidate_code'));
        add_action('wp_ajax_nopriv_' . self::INVALIDATE_CODE_ACTION, array($this, 'handle_invalidate_code'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_notices', array($this, 'render_configuration_notice'));
    }

    /**
     * register_assets - Declara CSS, JavaScript y configuración pública del formulario.
     *
     * Solo encola los recursos anticipadamente cuando el contenido consultado contiene el
     * shortcode; `render()` cubre inserciones tardías desde plantillas u otros constructores.
     *
     * @return void
     */
    public function register_assets(): void
    {
        wp_register_style('formularios-pw-contact', FORMULARIOS_PW_URL . 'assets/css/contact-form.css', array(), FORMULARIOS_PW_VERSION);
        wp_register_script('formularios-pw-contact', FORMULARIOS_PW_URL . 'assets/js/contact-form.js', array(), FORMULARIOS_PW_VERSION, true);
        wp_localize_script(
            'formularios-pw-contact',
            'formulariosPWContact',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(self::VERIFICATION_NONCE_ACTION),
                'sendCodeAction' => self::SEND_CODE_ACTION,
                'verifyCodeAction' => self::VERIFY_CODE_ACTION,
                'invalidateCodeAction' => self::INVALIDATE_CODE_ACTION,
            )
        );

        $post = get_queried_object();
        if ($post instanceof WP_Post && has_shortcode((string) $post->post_content, self::SHORTCODE)) {
            wp_enqueue_style('formularios-pw-contact');
            wp_enqueue_script('formularios-pw-contact');
        }
    }

    /**
     * render - Genera una instancia accesible con estados inicial, verificación y éxito.
     *
     * @return string HTML seguro del formulario y, si hace falta, estilos impresos tarde.
     */
    public function render(): string
    {
        self::$instance++;
        $id = 'codepty-contact-' . self::$instance;
        wp_enqueue_style('formularios-pw-contact');
        wp_enqueue_script('formularios-pw-contact');

        $late_styles = '';
        if (did_action('wp_head') && !wp_style_is('formularios-pw-contact', 'done') && !self::$late_styles_printed) {
            ob_start();
            wp_print_styles('formularios-pw-contact');
            $late_styles = (string) ob_get_clean();
            self::$late_styles_printed = true;
        }

        $values = array();
        $privacy_url = get_privacy_policy_url();
        ob_start();
        ?>
        <section id="<?php echo esc_attr($id); ?>" class="codepty-contact" aria-label="Formulario de contacto">
            <form class="codepty-contact__form" method="post">
                <input type="hidden" name="submission_id" value="<?php echo esc_attr(wp_generate_uuid4()); ?>">
                <input type="hidden" name="form_started" value="<?php echo esc_attr($this->form_started_token()); ?>">
                <input type="hidden" name="origin_url" value="">
                <?php wp_nonce_field(self::ACTION, 'codepty_contact_nonce'); ?>

                <div class="codepty-contact__trap" aria-hidden="true">
                    <label for="<?php echo esc_attr($id); ?>-website">No completar este campo</label>
                    <input id="<?php echo esc_attr($id); ?>-website" name="website" type="text" value="" tabindex="-1" autocomplete="off">
                </div>

                <div class="codepty-contact__initial">
                    <h2 id="<?php echo esc_attr($id); ?>-title" class="codepty-contact__title">Cuéntanos qué necesitas</h2>

                    <?php $this->input($id, 'name', 'Nombre', 'text', $values, 'name'); ?>
                    <?php $this->input($id, 'phone', 'Teléfono', 'tel', $values, 'tel'); ?>
                    <?php $this->input($id, 'email', 'Email', 'email', $values, 'email'); ?>

                    <div class="codepty-contact__field codepty-contact__field--message">
                        <label class="codepty-contact__sr-only" for="<?php echo esc_attr($id); ?>-message">Mensaje</label>
                        <textarea id="<?php echo esc_attr($id); ?>-message" name="message" rows="7" maxlength="4000" placeholder="Mensaje" required><?php echo esc_textarea((string) ($values['message'] ?? '')); ?></textarea>
                    </div>

                    <p class="codepty-contact__privacy">
                        Usaremos estos datos solamente para atender tu consulta.
                        <?php if ($privacy_url) : ?>
                            <a href="<?php echo esc_url($privacy_url); ?>">Consulta nuestra política de privacidad</a>.
                        <?php endif; ?>
                    </p>

                    <button class="codepty-contact__submit codepty-contact__start" type="button">
                        <svg class="codepty-contact__start-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" d="M3 5h18v14H3zM3 6l9 7 9-7"/></svg>
                        <span class="codepty-contact__start-label">Enviar consulta</span>
                    </button>
                    <p class="codepty-contact__initial-status" role="alert" aria-live="polite"></p>
                </div>

                <div class="codepty-contact__verification" hidden>
                    <h2 class="codepty-contact__title">Solo falta confirmar tu email</h2>
                    <p class="codepty-contact__verification-intro">Hemos enviado una clave de 4 caracteres a</p>
                    <strong class="codepty-contact__verification-email"></strong>
                    <button class="codepty-contact__change-email" type="button">Cambiar email</button>

                    <div class="codepty-contact__code-row" role="group" aria-label="Clave de verificación de cuatro caracteres">
                        <?php for ($code_index = 1; $code_index <= 4; $code_index++) : ?>
                            <input class="codepty-contact__code" type="text" inputmode="text" maxlength="<?php echo $code_index === 1 ? '4' : '1'; ?>" autocomplete="<?php echo $code_index === 1 ? 'one-time-code' : 'off'; ?>" autocapitalize="characters" spellcheck="false" aria-label="Carácter <?php echo esc_attr((string) $code_index); ?> de 4" data-code-index="<?php echo esc_attr((string) $code_index); ?>">
                        <?php endfor; ?>
                    </div>

                    <p class="codepty-contact__verification-status" role="alert" aria-live="polite"></p>
                    <button class="codepty-contact__submit codepty-contact__confirm" type="button">Confirmar y enviar mensaje</button>
                    <p class="codepty-contact__resend-help">¿No aparece? Revisa spam o <button class="codepty-contact__resend" type="button">solicita otra clave</button>.</p>
                    <p class="codepty-contact__code-help">La clave es válida durante 10 minutos.</p>
                </div>

                <div class="codepty-contact__success" hidden role="status" tabindex="-1">
                    <span class="codepty-contact__success-icon" aria-hidden="true">✓</span>
                    <h2 class="codepty-contact__title">Consulta enviada correctamente</h2>
                    <p>Hemos recibido tu mensaje. Nos pondremos en contacto contigo lo antes posible.</p>
                    <button class="codepty-contact__submit codepty-contact__restart" type="button">Enviar otra consulta</button>
                </div>
            </form>
            <?php echo Formularios_PW_Contact_Buttons::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>
        <?php
        return $late_styles . (string) ob_get_clean();
    }

    /**
     * input - Imprime un campo de texto común preservando valores devueltos tras un error.
     *
     * @param string $id Prefijo único de la instancia del formulario.
     * @param string $name Nombre del campo enviado al servidor.
     * @param string $label Etiqueta y placeholder visibles.
     * @param string $type Tipo HTML del control.
     * @param array  $values Valores sanitizados que deben restaurarse.
     * @param string $autocomplete Propósito de autocompletado para el navegador.
     * @return void
     */
    private function input(string $id, string $name, string $label, string $type, array $values, string $autocomplete): void
    {
        ?>
        <div class="codepty-contact__field">
            <label class="codepty-contact__sr-only" for="<?php echo esc_attr($id . '-' . $name); ?>"><?php echo esc_html($label); ?></label>
            <input id="<?php echo esc_attr($id . '-' . $name); ?>" name="<?php echo esc_attr($name); ?>" type="<?php echo esc_attr($type); ?>" value="<?php echo esc_attr((string) ($values[$name] ?? '')); ?>" maxlength="190" autocomplete="<?php echo esc_attr($autocomplete); ?>" placeholder="<?php echo esc_attr($label); ?>" required>
        </div>
        <?php
    }

    /**
     * handle_submit - Valida y entrega por email una consulta autorizada sin almacenarla.
     *
     * Requiere nonce, formulario firmado, límites de frecuencia y autorización ligada
     * al email antes de llamar a `wp_mail()`.
     *
     * @return void Finaliza con JSON o redirección segura.
     */
    public function handle_submit(): void
    {
        $fingerprint = Formularios_PW_Rate_Limit::fingerprint_from_request('general-contact');

        // Limita también peticiones inválidas para que no puedan usarse para agotar recursos.
        if (!Formularios_PW_Rate_Limit::allow('contact-attempt|' . $fingerprint, 20, 10 * MINUTE_IN_SECONDS)) {
            $this->submit_failure(array('Se han realizado demasiados intentos. Espera unos minutos antes de volver a intentarlo.'), 429);
        }

        if (!isset($_POST['codepty_contact_nonce']) || !wp_verify_nonce(sanitize_text_field((string) wp_unslash($_POST['codepty_contact_nonce'])), self::ACTION)) {
            $this->submit_failure(array('La sesión del formulario caducó. Actualiza la página e inténtalo nuevamente.'), 403);
        }

        if (trim((string) wp_unslash($_POST['website'] ?? '')) !== '') {
            // Respuesta indistinguible de un envío real para no enseñar al bot a evitar la trampa.
            $this->submit_success();
        }

        $started_token = sanitize_text_field((string) wp_unslash($_POST['form_started'] ?? ''));
        if (!$this->is_valid_form_started_token($started_token)) {
            $this->submit_failure(array('No se pudo validar el envío. Actualiza la página e inténtalo nuevamente.'));
        }

        $submission_id = sanitize_text_field((string) wp_unslash($_POST['submission_id'] ?? ''));
        if (!wp_is_uuid($submission_id)) {
            $this->submit_failure(array('El identificador del envío no es válido.'));
        }

        if (!Formularios_PW_Rate_Limit::allow('contact|' . $fingerprint, 5, HOUR_IN_SECONDS)) {
            $this->submit_failure(array('Has realizado demasiados intentos. Espera una hora antes de volver a enviar.'), 429);
        }

        $values = array(
            'name' => sanitize_text_field((string) wp_unslash($_POST['name'] ?? '')),
            'phone' => sanitize_text_field((string) wp_unslash($_POST['phone'] ?? '')),
            'email' => sanitize_email((string) wp_unslash($_POST['email'] ?? '')),
            'message' => sanitize_textarea_field((string) wp_unslash($_POST['message'] ?? '')),
        );
        $errors = $this->validate($values);
        if ($errors) {
            $this->submit_failure($errors);
        }

        if (!$this->is_submission_verified($submission_id, $values['email'])) {
            $this->submit_failure(array('Debes verificar tu email antes de enviar la consulta.'), 403);
        }

        $email_fingerprint = hash_hmac('sha256', strtolower($values['email']), wp_salt('nonce'));
        if (!Formularios_PW_Rate_Limit::allow('contact-email|' . $email_fingerprint, 3, HOUR_IN_SECONDS)) {
            $this->submit_failure(array('Este email ha realizado demasiados envíos. Espera una hora antes de volver a intentarlo.'), 429);
        }

        $posted_origin = isset($_POST['origin_url']) && is_string($_POST['origin_url'])
            ? wp_unslash($_POST['origin_url'])
            : '';
        $origin = $this->resolve_origin($posted_origin);
        $payload = array_merge(
            $values,
            array(
                'origin_url' => $origin['url'],
                'origin_title' => $origin['title'],
                'origin_post_id' => $origin['post_id'],
                'submitted_at' => wp_date('Y-m-d H:i:s T'),
            )
        );

        try {
            $sent = $this->send_email($payload);
        } catch (Throwable $e) {
            $sent = false;
        }

        if (!$sent) {
            $this->submit_failure(array('No pudimos enviar tu consulta. Inténtalo nuevamente más tarde.'), 500);
        }

        delete_transient($this->verified_transient_key($submission_id));
        $this->submit_success();
    }

    /**
     * handle_send_code - Genera y envía al visitante una clave temporal de cuatro caracteres.
     *
     * Valida todos los campos del recorrido email, aplica honeypot, tiempo mínimo, cooldown y
     * límites por IP/email. Una clave nueva invalida la autorización previa del envío.
     *
     * @return void Finaliza con una respuesta JSON de WordPress.
     */
    public function handle_send_code(): void
    {
        $this->guard_verification_ajax();

        if (!$this->configured_recipient()) {
            wp_send_json_error(array('message' => 'El formulario no está disponible temporalmente. Inténtalo de nuevo más tarde.'), 503);
        }

        $submission_id = $this->posted_submission_id();
        if (trim((string) wp_unslash($_POST['website'] ?? '')) !== '') {
            wp_send_json_success(array('message' => 'Hemos enviado una clave a tu correo.'));
        }

        $values = $this->posted_values();
        $errors = $this->validate($values);
        if ($errors) {
            wp_send_json_error(array('message' => implode(' ', $errors)), 400);
        }

        $started_token = sanitize_text_field((string) wp_unslash($_POST['form_started'] ?? ''));
        if (!$this->is_valid_form_started_token($started_token)) {
            wp_send_json_error(array('message' => 'No se pudo validar el formulario. Actualiza la página e inténtalo nuevamente.'), 400);
        }

        $cooldown_key = 'fpw_contact_code_cooldown_' . md5($submission_id);
        if (get_transient($cooldown_key)) {
            wp_send_json_error(array('message' => 'Espera un minuto antes de solicitar otra clave.'), 429);
        }

        $fingerprint = Formularios_PW_Rate_Limit::fingerprint_from_request('contact-code');
        $email_fingerprint = $this->email_fingerprint($values['email']);
        if (!Formularios_PW_Rate_Limit::allow('contact-code-ip|' . $fingerprint, 10, HOUR_IN_SECONDS)
            || !Formularios_PW_Rate_Limit::allow('contact-code-email|' . $email_fingerprint, 3, HOUR_IN_SECONDS)) {
            wp_send_json_error(array('message' => 'Se han solicitado demasiadas claves. Espera una hora antes de volver a intentarlo.'), 429);
        }

        $code = $this->generate_code();
        $state = array(
            'email_hash' => $email_fingerprint,
            'code_hash' => hash_hmac('sha256', $code, wp_salt('auth')),
            'attempts' => 0,
            'expires_at' => time() + self::CODE_LIFETIME,
        );
        delete_transient($this->verified_transient_key($submission_id));
        set_transient($this->code_transient_key($submission_id), $state, self::CODE_LIFETIME);

        if (!$this->send_verification_email($values['email'], $code)) {
            delete_transient($this->code_transient_key($submission_id));
            wp_send_json_error(array('message' => 'No pudimos enviar la clave. Inténtalo nuevamente más tarde.'), 500);
        }

        set_transient($cooldown_key, 1, MINUTE_IN_SECONDS);
        wp_send_json_success(array('message' => 'Hemos enviado una clave de 4 caracteres a tu correo. Revisa también la carpeta de spam.'));
    }

    /**
     * handle_invalidate_code - Revoca clave y autorización al regresar para cambiar el email.
     *
     * @return void Finaliza con una respuesta JSON de WordPress.
     */
    public function handle_invalidate_code(): void
    {
        $this->guard_verification_ajax();
        $submission_id = $this->posted_submission_id();
        delete_transient($this->code_transient_key($submission_id));
        delete_transient($this->verified_transient_key($submission_id));
        wp_send_json_success(array());
    }

    /**
     * handle_verify_code - Valida la clave y autoriza temporalmente la consulta y el email.
     *
     * La clave es de un solo uso y admite un máximo limitado de intentos. La autorización
     * resultante queda vinculada al UUID del formulario y a la huella del email.
     *
     * @return void Finaliza con una respuesta JSON de WordPress.
     */
    public function handle_verify_code(): void
    {
        $this->guard_verification_ajax();

        $submission_id = $this->posted_submission_id();
        $email = sanitize_email((string) wp_unslash($_POST['email'] ?? ''));
        $code = strtoupper(sanitize_text_field((string) wp_unslash($_POST['code'] ?? '')));
        if (!is_email($email) || !preg_match('/^[A-Z0-9]{4}$/', $code)) {
            wp_send_json_error(array('message' => 'Introduce una clave válida de 4 caracteres.'), 400);
        }

        $fingerprint = Formularios_PW_Rate_Limit::fingerprint_from_request('contact-code-check');
        if (!Formularios_PW_Rate_Limit::allow('contact-code-check|' . $fingerprint, 20, 10 * MINUTE_IN_SECONDS)) {
            wp_send_json_error(array('message' => 'Se han realizado demasiados intentos. Espera unos minutos.'), 429);
        }

        $key = $this->code_transient_key($submission_id);
        $state = get_transient($key);
        if (!is_array($state) || (int) ($state['expires_at'] ?? 0) < time() || !hash_equals((string) ($state['email_hash'] ?? ''), $this->email_fingerprint($email))) {
            delete_transient($key);
            wp_send_json_error(array('message' => 'La clave ha caducado. Solicita una nueva para continuar.', 'reason' => 'expired'), 400);
        }

        $attempts = (int) ($state['attempts'] ?? 0) + 1;
        $expected = hash_hmac('sha256', $code, wp_salt('auth'));
        if (!hash_equals((string) ($state['code_hash'] ?? ''), $expected)) {
            if ($attempts >= self::MAX_CODE_ATTEMPTS) {
                delete_transient($key);
                wp_send_json_error(array('message' => 'Has superado el número de intentos. Solicita una clave nueva.', 'reason' => 'attempts'), 429);
            }
            $state['attempts'] = $attempts;
            set_transient($key, $state, max(1, (int) $state['expires_at'] - time()));
            wp_send_json_error(array('message' => 'La clave no es correcta. Comprueba los cuatro caracteres.'), 400);
        }

        delete_transient($key);
        set_transient(
            $this->verified_transient_key($submission_id),
            array('email_hash' => $this->email_fingerprint($email)),
            self::VERIFICATION_LIFETIME
        );
        wp_send_json_success(
            array(
                'message' => 'Email verificado. Ya puedes enviar la consulta.',
                'submitAction' => self::ACTION,
            )
        );
    }

    /**
     * validate - Comprueba los datos obligatorios del recorrido de email.
     *
     * @param array $values Nombre, teléfono, email y mensaje ya sanitizados.
     * @return array Mensajes de validación; vacío cuando todos los datos son válidos.
     */
    private function validate(array $values): array
    {
        $errors = array();
        if ($values['name'] === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        if ($values['phone'] === '' || !preg_match('/^[0-9+() .-]{7,30}$/', $values['phone'])) {
            $errors[] = 'Introduce un teléfono válido.';
        }
        if ($values['email'] === '' || !is_email($values['email'])) {
            $errors[] = 'Introduce un email válido.';
        }
        if ($values['message'] === '') {
            $errors[] = 'El mensaje es obligatorio.';
        } elseif (strlen($values['message']) > 4000) {
            $errors[] = 'El mensaje no puede superar los 4000 caracteres.';
        }

        return $errors;
    }

    /**
     * posted_values - Extrae y sanitiza los campos públicos recibidos por POST.
     *
     * @return array Nombre, teléfono, email y mensaje normalizados.
     */
    private function posted_values(): array
    {
        return array(
            'name' => sanitize_text_field((string) wp_unslash($_POST['name'] ?? '')),
            'phone' => sanitize_text_field((string) wp_unslash($_POST['phone'] ?? '')),
            'email' => sanitize_email((string) wp_unslash($_POST['email'] ?? '')),
            'message' => sanitize_textarea_field((string) wp_unslash($_POST['message'] ?? '')),
        );
    }

    /**
     * submit_failure - Devuelve un fallo JSON uniforme sin conservar los campos recibidos.
     *
     * @param array  $errors Mensajes seguros destinados al visitante.
     * @param int    $status_code Código HTTP para la respuesta AJAX.
     * @return void Finaliza la petición.
     */
    private function submit_failure(array $errors, int $status_code = 400): void
    {
        wp_send_json_error(array('message' => implode(' ', $errors)), $status_code);
    }

    /**
     * submit_success - Devuelve éxito JSON sin crear estado persistente adicional.
     *
     * @param array  $data Datos adicionales de la respuesta AJAX.
     * @return void Finaliza la petición.
     */
    private function submit_success(array $data = array()): void
    {
        wp_send_json_success($data);
    }

    /**
     * guard_verification_ajax - Rechaza operaciones de clave con nonce ausente o caducado.
     *
     * @return void Finaliza con error JSON si la sesión pública no es válida.
     */
    private function guard_verification_ajax(): void
    {
        $nonce = sanitize_text_field((string) wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, self::VERIFICATION_NONCE_ACTION)) {
            wp_send_json_error(array('message' => 'La sesión ha caducado. Actualiza la página e inténtalo nuevamente.'), 403);
        }
    }

    /**
     * posted_submission_id - Recupera y valida el UUID que identifica esta instancia.
     *
     * @return string UUID válido del formulario.
     */
    private function posted_submission_id(): string
    {
        $submission_id = sanitize_text_field((string) wp_unslash($_POST['submission_id'] ?? ''));
        if (!wp_is_uuid($submission_id)) {
            wp_send_json_error(array('message' => 'No se pudo identificar el formulario. Actualiza la página.'), 400);
        }

        return $submission_id;
    }

    /**
     * generate_code - Crea una clave de cuatro caracteres sin símbolos visualmente ambiguos.
     *
     * @return string Clave aleatoria en mayúsculas.
     * @throws Exception Si el sistema no puede producir aleatoriedad criptográfica.
     */
    private function generate_code(): string
    {
        $code = '';
        $max = strlen(self::CODE_ALPHABET) - 1;
        for ($index = 0; $index < 4; $index++) {
            $code .= self::CODE_ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * send_verification_email - Envía la clave de un solo uso al email del visitante.
     *
     * @param string $email Destinatario previamente validado.
     * @param string $code Clave alfanumérica generada para esta consulta.
     * @return bool Resultado de `wp_mail()`.
     */
    private function send_verification_email(string $email, string $code): bool
    {
        $subject = 'Tu clave para enviar la consulta a CodePTY';
        $body = "Tu clave de verificación es: {$code}\n\nCaduca en 10 minutos y solo puede utilizarse una vez.\nSi no solicitaste esta clave, puedes ignorar este mensaje.";

        return wp_mail($email, $subject, $body);
    }

    /**
     * email_fingerprint - Seudonimiza un email para límites y vinculaciones temporales.
     *
     * @param string $email Dirección ya sanitizada.
     * @return string HMAC SHA-256 no reversible con la sal de WordPress.
     */
    private function email_fingerprint(string $email): string
    {
        return hash_hmac('sha256', strtolower($email), wp_salt('nonce'));
    }

    /**
     * code_transient_key - Deriva la clave de transient que guarda el desafío temporal.
     *
     * @param string $submission_id UUID válido del formulario.
     * @return string Nombre acotado para la API de transients.
     */
    private function code_transient_key(string $submission_id): string
    {
        return 'fpw_contact_code_' . md5($submission_id);
    }

    /**
     * verified_transient_key - Deriva la clave de transient de la autorización verificada.
     *
     * @param string $submission_id UUID válido del formulario.
     * @return string Nombre acotado para la API de transients.
     */
    private function verified_transient_key(string $submission_id): string
    {
        return 'fpw_contact_verified_' . md5($submission_id);
    }

    /**
     * is_submission_verified - Confirma que UUID y email comparten autorización vigente.
     *
     * @param string $submission_id UUID del formulario enviado.
     * @param string $email Email sanitizado incluido en la consulta.
     * @return bool Indica si el servidor autorizó esa pareja.
     */
    private function is_submission_verified(string $submission_id, string $email): bool
    {
        $state = get_transient($this->verified_transient_key($submission_id));

        return is_array($state)
            && isset($state['email_hash'])
            && hash_equals((string) $state['email_hash'], $this->email_fingerprint($email));
    }

    /**
     * form_started_token - Firma la hora de renderizado para detectar envíos instantáneos.
     *
     * @return string Marca Unix y HMAC separados por punto.
     */
    private function form_started_token(): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', self::ACTION . '|' . $timestamp, wp_salt('nonce'));

        return $timestamp . '.' . $signature;
    }

    /**
     * is_valid_form_started_token - Comprueba firma y antigüedad razonable del formulario.
     *
     * @param string $token Marca temporal firmada recibida desde el formulario.
     * @return bool Indica si el formulario no es instantáneo ni excesivamente antiguo.
     */
    private function is_valid_form_started_token(string $token): bool
    {
        if (!preg_match('/^(\d{10})\.([a-f0-9]{64})$/', $token, $matches)) {
            return false;
        }

        $started_at = (int) $matches[1];
        $age = time() - $started_at;
        if ($age < self::MIN_FILL_SECONDS || $age > self::MAX_FORM_AGE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', self::ACTION . '|' . $started_at, wp_salt('nonce'));

        return hash_equals($expected, $matches[2]);
    }

    /**
     * resolve_origin - Valida y describe la página interna declarada por el navegador.
     *
     * Elimina consulta y fragmento antes de conservar exclusivamente esquema, host, puerto
     * opcional y ruta. Un valor vacío, externo o malformado produce un origen no identificado.
     *
     * @param string $url URL absoluta recibida desde el campo oculto del formulario.
     * @return array URL interna limpia, ID opcional y título sanitizado.
     */
    private function resolve_origin(string $url): array
    {
        $url = $this->normalize_origin_url($url);
        if ('' === $url) {
            return array(
                'url' => '',
                'post_id' => null,
                'title' => '',
            );
        }

        $post_id = url_to_postid($url);
        $title = $post_id > 0 ? get_the_title($post_id) : '';

        return array(
            'url' => $url,
            'post_id' => $post_id > 0 ? $post_id : null,
            'title' => is_string($title) ? sanitize_text_field($title) : '',
        );
    }

    /**
     * normalize_origin_url - Reduce una URL al esquema, host, puerto y ruta del sitio actual.
     *
     * @param string $url Valor no confiable recibido desde el navegador.
     * @return string URL interna limpia o cadena vacía cuando no es aceptable.
     */
    private function normalize_origin_url(string $url): string
    {
        $url = trim($url);
        if ('' === $url || strlen($url) > 2048) {
            return '';
        }

        $url = esc_url_raw($url, array('http', 'https'));
        $parts = wp_parse_url($url);
        $site_parts = wp_parse_url(home_url('/'));
        if (!is_array($parts) || !is_array($site_parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $site_host = strtolower((string) ($site_parts['host'] ?? ''));
        if (!in_array($scheme, array('http', 'https'), true) || '' === $host || $host !== $site_host) {
            return '';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $site_port = isset($site_parts['port']) ? (int) $site_parts['port'] : null;
        if ($port !== $site_port) {
            return '';
        }

        $path = isset($parts['path']) && is_string($parts['path']) && '' !== $parts['path']
            ? '/' . ltrim($parts['path'], '/')
            : '/';
        $authority = $scheme . '://' . $host . (null !== $port ? ':' . $port : '');

        return esc_url_raw($authority . $path, array('http', 'https'));
    }

    /**
     * send_email - Entrega la consulta al destinatario operativo configurado.
     *
     * @param array $payload Consulta sanitizada junto con sus datos de origen.
     * @return bool Resultado de `wp_mail()` o `false` si el destinatario no es válido.
     */
    private function send_email(array $payload): bool
    {
        $recipient = $this->configured_recipient();
        if (!$recipient) {
            return false;
        }

        $subject = 'Nueva consulta general en CodePTY';
        $origin_title = $payload['origin_title'] !== '' ? $payload['origin_title'] : 'No identificado';
        $origin_url = $payload['origin_url'] !== '' ? $payload['origin_url'] : 'No identificado';
        $body = "Nombre: {$payload['name']}\n"
            . "Teléfono: {$payload['phone']}\n"
            . "Email: {$payload['email']}\n\n"
            . "Mensaje:\n{$payload['message']}\n\n"
            . "Página de origen: {$origin_title}\n{$origin_url}\n\n"
            . "Fecha y hora: {$payload['submitted_at']}";
        $headers = array('Reply-To: ' . $payload['name'] . ' <' . $payload['email'] . '>');

        return wp_mail($recipient, $subject, $body, $headers);
    }

    /**
     * configured_recipient - Devuelve el destinatario configurado cuando es válido.
     *
     * @return string Email sanitizado o cadena vacía.
     */
    private function configured_recipient(): string
    {
        $recipient = sanitize_email((string) apply_filters('formularios_pw_contact_email', CODEPTY_CONTACT_EMAIL));

        return is_email($recipient) ? $recipient : '';
    }

    /**
     * render_configuration_notice - Avisa a administradores si falta el destinatario.
     *
     * @return void
     */
    public function render_configuration_notice(): void
    {
        if ($this->configured_recipient() || !current_user_can('manage_options')) {
            return;
        }
        echo '<div class="notice notice-error"><p>'
            . esc_html__('Formularios CodePTY: define un email válido en CODEPTY_CONTACT_EMAIL para habilitar el formulario de contacto.', 'formularios-pw')
            . '</p></div>';
    }
}
