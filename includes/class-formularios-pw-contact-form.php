<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Contact_Form — Coordina renderizado, verificación y envío del contacto público.
 *
 * El formulario recomienda WhatsApp en smartphones sin registrar una recepción. El recorrido
 * de email exige una clave temporal antes de cifrar, indexar y entregar la consulta.
 */
final class Formularios_PW_Contact_Form
{
    private const SHORTCODE = 'codepty_formulario_contacto';
    private const ACTION = 'formularios_pw_submit_contact';
    private const SEND_CODE_ACTION = 'formularios_pw_send_contact_code';
    private const VERIFY_CODE_ACTION = 'formularios_pw_verify_contact_code';
    private const INVALIDATE_CODE_ACTION = 'formularios_pw_invalidate_contact_code';
    private const VERIFICATION_NONCE_ACTION = 'formularios_pw_contact_verification';
    private const STATE_QUERY = 'codepty_contact_state';
    private const CODE_LIFETIME = 10 * MINUTE_IN_SECONDS;
    private const VERIFICATION_LIFETIME = 15 * MINUTE_IN_SECONDS;
    private const MAX_CODE_ATTEMPTS = 5;
    private const CODE_ALPHABET = '234679ACDEFGHJKMNPQRTUVWXYZ';
    private const MIN_FILL_SECONDS = 3;
    private const MAX_FORM_AGE_SECONDS = 2 * DAY_IN_SECONDS;
    private static $instance = 0;
    private static $late_styles_printed = false;

    /**
     * register — Registra shortcode, endpoints públicos y carga de recursos.
     *
     * @return void
     */
    public function register(): void
    {
        add_shortcode(self::SHORTCODE, array($this, 'render'));
        add_action('admin_post_' . self::ACTION, array($this, 'handle_submit'));
        add_action('admin_post_nopriv_' . self::ACTION, array($this, 'handle_submit'));
        add_action('wp_ajax_' . self::ACTION, array($this, 'handle_submit'));
        add_action('wp_ajax_nopriv_' . self::ACTION, array($this, 'handle_submit'));
        add_action('wp_ajax_' . self::SEND_CODE_ACTION, array($this, 'handle_send_code'));
        add_action('wp_ajax_nopriv_' . self::SEND_CODE_ACTION, array($this, 'handle_send_code'));
        add_action('wp_ajax_' . self::VERIFY_CODE_ACTION, array($this, 'handle_verify_code'));
        add_action('wp_ajax_nopriv_' . self::VERIFY_CODE_ACTION, array($this, 'handle_verify_code'));
        add_action('wp_ajax_' . self::INVALIDATE_CODE_ACTION, array($this, 'handle_invalidate_code'));
        add_action('wp_ajax_nopriv_' . self::INVALIDATE_CODE_ACTION, array($this, 'handle_invalidate_code'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
    }

    /**
     * register_assets — Declara CSS, JavaScript y configuración pública del formulario.
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
                'whatsappUrl' => 'https://wa.me/' . preg_replace('/\D+/', '', (string) apply_filters('formularios_pw_contact_whatsapp', CODEPTY_CONTACT_WHATSAPP)),
            )
        );

        $post = get_queried_object();
        if ($post instanceof WP_Post && has_shortcode((string) $post->post_content, self::SHORTCODE)) {
            wp_enqueue_style('formularios-pw-contact');
            wp_enqueue_script('formularios-pw-contact');
        }
    }

    /**
     * render — Genera una instancia accesible con estados inicial, verificación y éxito.
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

        $state = $this->consume_state();
        $values = is_array($state['values'] ?? null) ? $state['values'] : array();
        $errors = is_array($state['errors'] ?? null) ? $state['errors'] : array();
        $status = sanitize_key((string) ($state['status'] ?? ''));
        $privacy_url = get_privacy_policy_url();
        ob_start();
        ?>
        <section id="<?php echo esc_attr($id); ?>" class="codepty-contact" aria-label="Formulario de contacto">
            <div class="codepty-contact__whatsapp">
                <span class="codepty-contact__whatsapp-icon" aria-hidden="true">
                    <svg viewBox="0 0 16 16" focusable="false"><path fill="currentColor" d="M13.6 2.33A7.85 7.85 0 0 0 7.99 0C3.63 0 .07 3.56.06 7.93c0 1.4.37 2.76 1.06 3.96L0 16l4.2-1.1a7.93 7.93 0 0 0 3.79.96h.01c4.37 0 7.93-3.56 7.93-7.93a7.9 7.9 0 0 0-2.33-5.6ZM8 14.52a6.57 6.57 0 0 1-3.36-.92l-.24-.14-2.5.65.67-2.43-.16-.25A6.56 6.56 0 0 1 1.4 7.92 6.6 6.6 0 0 1 8 1.34a6.56 6.56 0 0 1 4.66 1.93 6.56 6.56 0 0 1 1.93 4.66A6.59 6.59 0 0 1 8 14.52Zm3.61-4.93c-.2-.1-1.17-.58-1.35-.65-.18-.06-.32-.1-.45.1-.13.2-.51.65-.63.78-.11.13-.23.15-.43.05-.2-.1-.84-.31-1.59-.99-.59-.52-.99-1.17-1.1-1.37-.12-.2-.01-.3.08-.4.09-.09.2-.23.3-.35.1-.11.13-.2.2-.33.06-.13.03-.25-.02-.35-.05-.1-.44-1.07-.61-1.47-.16-.39-.32-.33-.44-.34h-.38a.73.73 0 0 0-.53.25c-.18.2-.69.68-.69 1.65 0 .98.71 1.92.81 2.05.1.13 1.4 2.13 3.38 2.99.47.2.84.33 1.13.42.48.15.9.13 1.25.08.38-.06 1.17-.48 1.34-.94.16-.47.16-.86.11-.95-.05-.08-.18-.13-.38-.23Z"/></svg>
                </span>
                <span class="codepty-contact__whatsapp-number">Atención directa por WhatsApp</span>
            </div>

            <form class="codepty-contact__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="submission_id" value="<?php echo esc_attr(wp_generate_uuid4()); ?>">
                <input type="hidden" name="form_started" value="<?php echo esc_attr($this->form_started_token()); ?>">
                <?php wp_nonce_field(self::ACTION, 'codepty_contact_nonce'); ?>

                <div class="codepty-contact__trap" aria-hidden="true">
                    <label for="<?php echo esc_attr($id); ?>-website">No completar este campo</label>
                    <input id="<?php echo esc_attr($id); ?>-website" name="website" type="text" value="" tabindex="-1" autocomplete="off">
                </div>

                <div class="codepty-contact__initial"<?php echo $status === 'success' ? ' hidden' : ''; ?>>
                    <h2 id="<?php echo esc_attr($id); ?>-title" class="codepty-contact__title">Cuéntanos qué necesitas</h2>

                    <?php if ($errors) : ?>
                        <div class="codepty-contact__notice codepty-contact__notice--error" role="alert" tabindex="-1">
                            <p>Revisa los siguientes datos:</p>
                            <ul>
                                <?php foreach ($errors as $error) : ?>
                                    <li><?php echo esc_html((string) $error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php $this->input($id, 'name', 'Nombre', 'text', $values, 'name'); ?>
                    <?php $this->input($id, 'phone', 'Teléfono', 'tel', $values, 'tel'); ?>
                    <?php $this->input($id, 'email', 'Email', 'email', $values, 'email'); ?>

                    <div class="codepty-contact__field codepty-contact__field--message">
                        <label class="codepty-contact__sr-only" for="<?php echo esc_attr($id); ?>-message">Mensaje</label>
                        <textarea id="<?php echo esc_attr($id); ?>-message" name="message" rows="7" maxlength="4000" placeholder="Mensaje" required><?php echo esc_textarea((string) ($values['message'] ?? '')); ?></textarea>
                    </div>

                    <p class="codepty-contact__privacy">
                        <span class="codepty-contact__privacy-email">
                            Usaremos estos datos solamente para atender tu consulta.
                            <?php if ($privacy_url) : ?>
                                <a href="<?php echo esc_url($privacy_url); ?>">Consulta nuestra política de privacidad</a>.
                            <?php endif; ?>
                        </span>
                        <span class="codepty-contact__privacy-whatsapp" hidden>
                            Al continuar, abriremos WhatsApp con tu consulta preparada para que puedas revisarla y enviarla.
                            <?php if ($privacy_url) : ?>
                                <a href="<?php echo esc_url($privacy_url); ?>">Consulta nuestra política de privacidad</a>.
                            <?php endif; ?>
                        </span>
                    </p>

                    <button class="codepty-contact__submit codepty-contact__start" type="button">
                        <svg class="codepty-contact__start-icon codepty-contact__start-icon--email" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" d="M3 5h18v14H3zM3 6l9 7 9-7"/></svg>
                        <svg class="codepty-contact__start-icon codepty-contact__start-icon--whatsapp" viewBox="0 0 16 16" aria-hidden="true" hidden><path fill="currentColor" d="M13.6 2.33A7.85 7.85 0 0 0 7.99 0C3.63 0 .07 3.56.06 7.93c0 1.4.37 2.76 1.06 3.96L0 16l4.2-1.1a7.93 7.93 0 0 0 3.79.96h.01c4.37 0 7.93-3.56 7.93-7.93a7.9 7.9 0 0 0-2.33-5.6ZM8 14.52a6.57 6.57 0 0 1-3.36-.92l-.24-.14-2.5.65.67-2.43-.16-.25A6.56 6.56 0 0 1 1.4 7.92 6.6 6.6 0 0 1 8 1.34a6.56 6.56 0 0 1 4.66 1.93 6.56 6.56 0 0 1 1.93 4.66A6.59 6.59 0 0 1 8 14.52Zm3.61-4.93c-.2-.1-1.17-.58-1.35-.65-.18-.06-.32-.1-.45.1-.13.2-.51.65-.63.78-.11.13-.23.15-.43.05-.2-.1-.84-.31-1.59-.99-.59-.52-.99-1.17-1.1-1.37-.12-.2-.01-.3.08-.4.09-.09.2-.23.3-.35.1-.11.13-.2.2-.33.06-.13.03-.25-.02-.35-.05-.1-.44-1.07-.61-1.47-.16-.39-.32-.33-.44-.34h-.38a.73.73 0 0 0-.53.25c-.18.2-.69.68-.69 1.65 0 .98.71 1.92.81 2.05.1.13 1.4 2.13 3.38 2.99.47.2.84.33 1.13.42.48.15.9.13 1.25.08.38-.06 1.17-.48 1.34-.94.16-.47.16-.86.11-.95-.05-.08-.18-.13-.38-.23Z"/></svg>
                        <span class="codepty-contact__start-label">Enviar consulta por email</span>
                    </button>
                    <button class="codepty-contact__channel-switch" type="button" hidden>Prefiero enviar por email</button>
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

                <div class="codepty-contact__success"<?php echo $status === 'success' ? '' : ' hidden'; ?> role="status" tabindex="-1">
                    <span class="codepty-contact__success-icon" aria-hidden="true">✓</span>
                    <h2 class="codepty-contact__title">Consulta enviada correctamente</h2>
                    <p>Hemos recibido tu mensaje. Nos pondremos en contacto contigo lo antes posible.</p>
                    <button class="codepty-contact__submit codepty-contact__restart" type="button">Enviar otra consulta</button>
                </div>
            </form>
        </section>
        <?php
        return $late_styles . (string) ob_get_clean();
    }

    /**
     * input — Imprime un campo de texto común preservando valores devueltos tras un error.
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
     * handle_submit — Valida, almacena cifrada y entrega por email una consulta autorizada.
     *
     * Atiende tanto AJAX como el fallback `admin-post.php`. Requiere nonce, formulario firmado,
     * límites de frecuencia y autorización ligada al email antes de crear la consulta.
     *
     * @return void Finaliza con JSON o redirección segura.
     */
    public function handle_submit(): void
    {
        $return_url = $this->validated_return_url();
        $fingerprint = Formularios_PW_Rate_Limit::fingerprint_from_request('general-contact');

        // Limita también peticiones inválidas para que no puedan usarse para agotar recursos.
        if (!Formularios_PW_Rate_Limit::allow('contact-attempt|' . $fingerprint, 20, 10 * MINUTE_IN_SECONDS)) {
            $this->submit_failure($return_url, array('Se han realizado demasiados intentos. Espera unos minutos antes de volver a intentarlo.'), array(), 429);
        }

        if (!isset($_POST['codepty_contact_nonce']) || !wp_verify_nonce(sanitize_text_field((string) wp_unslash($_POST['codepty_contact_nonce'])), self::ACTION)) {
            $this->submit_failure($return_url, array('La sesión del formulario caducó. Actualiza la página e inténtalo nuevamente.'), array(), 403);
        }

        if (trim((string) wp_unslash($_POST['website'] ?? '')) !== '') {
            // Respuesta indistinguible de un envío real para no enseñar al bot a evitar la trampa.
            $this->submit_success($return_url);
        }

        $started_token = sanitize_text_field((string) wp_unslash($_POST['form_started'] ?? ''));
        if (!$this->is_valid_form_started_token($started_token)) {
            $this->submit_failure($return_url, array('No se pudo validar el envío. Actualiza la página e inténtalo nuevamente.'));
        }

        $submission_id = sanitize_text_field((string) wp_unslash($_POST['submission_id'] ?? ''));
        if (!wp_is_uuid($submission_id)) {
            $this->submit_failure($return_url, array('El identificador del envío no es válido.'));
        }

        if (get_transient('fpw_contact_done_' . md5($submission_id))) {
            $this->submit_success($return_url);
        }

        if (!Formularios_PW_Rate_Limit::allow('contact|' . $fingerprint, 5, HOUR_IN_SECONDS)) {
            $this->submit_failure($return_url, array('Has realizado demasiados intentos. Espera una hora antes de volver a enviar.'), array(), 429);
        }

        $values = array(
            'name' => sanitize_text_field((string) wp_unslash($_POST['name'] ?? '')),
            'phone' => sanitize_text_field((string) wp_unslash($_POST['phone'] ?? '')),
            'email' => sanitize_email((string) wp_unslash($_POST['email'] ?? '')),
            'message' => sanitize_textarea_field((string) wp_unslash($_POST['message'] ?? '')),
        );
        $errors = $this->validate($values);
        if ($errors) {
            $this->submit_failure($return_url, $errors, $values);
        }

        if (!$this->is_submission_verified($submission_id, $values['email'])) {
            $this->submit_failure($return_url, array('Debes verificar tu email antes de enviar la consulta.'), $values, 403);
        }

        $email_fingerprint = hash_hmac('sha256', strtolower($values['email']), wp_salt('nonce'));
        if (!Formularios_PW_Rate_Limit::allow('contact-email|' . $email_fingerprint, 3, HOUR_IN_SECONDS)) {
            $this->submit_failure($return_url, array('Este email ha realizado demasiados envíos. Espera una hora antes de volver a intentarlo.'), $values, 429);
        }

        $origin = $this->resolve_origin($return_url);
        $channel = 'email';
        $payload = array_merge(
            $values,
            array(
                'origin_url' => $origin['url'],
                'origin_title' => $origin['title'],
                'origin_post_id' => $origin['post_id'],
                'submitted_at' => gmdate('c'),
            )
        );

        try {
            $contact = Formularios_PW_Contact_Repository::create($payload, $channel, $origin['post_id']);
            set_transient('fpw_contact_done_' . md5($submission_id), 1, DAY_IN_SECONDS);
            delete_transient($this->verified_transient_key($submission_id));

            $sent = $this->send_email($payload);
            Formularios_PW_Contact_Repository::set_delivery_status((string) $contact['contact_uid'], $sent ? 'email_sent' : 'email_failed');
            if (!$sent) {
                if (wp_doing_ajax()) {
                    $this->submit_success($return_url, array('deliveryWarning' => 'La consulta quedó registrada y nuestro equipo podrá revisarla internamente.'));
                }
                $this->submit_failure($return_url, array('La consulta quedó registrada, pero el correo no pudo enviarse. Nuestro equipo podrá revisarla internamente.'));
            }
        } catch (Throwable $e) {
            $this->submit_failure($return_url, array('No pudimos registrar tu consulta. Inténtalo nuevamente más tarde.'), $values, 500);
        }

        $this->submit_success($return_url);
    }

    /**
     * handle_send_code — Genera y envía al visitante una clave temporal de cuatro caracteres.
     *
     * Valida todos los campos del recorrido email, aplica honeypot, tiempo mínimo, cooldown y
     * límites por IP/email. Una clave nueva invalida la autorización previa del envío.
     *
     * @return void Finaliza con una respuesta JSON de WordPress.
     */
    public function handle_send_code(): void
    {
        $this->guard_verification_ajax();

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
     * handle_invalidate_code — Revoca clave y autorización al regresar para cambiar el email.
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
     * handle_verify_code — Valida la clave y autoriza temporalmente la consulta y el email.
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
     * validate — Comprueba los datos obligatorios del recorrido de email.
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
     * posted_values — Extrae y sanitiza los campos públicos recibidos por POST.
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
     * submit_failure — Devuelve un fallo uniforme por AJAX o mediante estado redirigido.
     *
     * @param string $return_url URL local a la que regresar en el fallback sin JavaScript.
     * @param array  $errors Mensajes seguros destinados al visitante.
     * @param array  $values Valores sanitizados que deben conservarse.
     * @param int    $status_code Código HTTP para la respuesta AJAX.
     * @return void Finaliza la petición.
     */
    private function submit_failure(string $return_url, array $errors, array $values = array(), int $status_code = 400): void
    {
        if (wp_doing_ajax()) {
            wp_send_json_error(array('message' => implode(' ', $errors)), $status_code);
        }

        $this->redirect_with_state($return_url, array('errors' => $errors, 'values' => $values));
    }

    /**
     * submit_success — Devuelve éxito por AJAX o redirige con un estado efímero.
     *
     * @param string $return_url URL local del formulario.
     * @param array  $data Datos adicionales de la respuesta AJAX.
     * @return void Finaliza la petición.
     */
    private function submit_success(string $return_url, array $data = array()): void
    {
        if (wp_doing_ajax()) {
            wp_send_json_success($data);
        }

        $this->redirect_with_state($return_url, array('status' => 'success'));
    }

    /**
     * guard_verification_ajax — Rechaza operaciones de clave con nonce ausente o caducado.
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
     * posted_submission_id — Recupera y valida el UUID que identifica esta instancia.
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
     * generate_code — Crea una clave de cuatro caracteres sin símbolos visualmente ambiguos.
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
     * send_verification_email — Envía la clave de un solo uso al email del visitante.
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
     * email_fingerprint — Seudonimiza un email para límites y vinculaciones temporales.
     *
     * @param string $email Dirección ya sanitizada.
     * @return string HMAC SHA-256 no reversible con la sal de WordPress.
     */
    private function email_fingerprint(string $email): string
    {
        return hash_hmac('sha256', strtolower($email), wp_salt('nonce'));
    }

    /**
     * code_transient_key — Deriva la clave de transient que guarda el desafío temporal.
     *
     * @param string $submission_id UUID válido del formulario.
     * @return string Nombre acotado para la API de transients.
     */
    private function code_transient_key(string $submission_id): string
    {
        return 'fpw_contact_code_' . md5($submission_id);
    }

    /**
     * verified_transient_key — Deriva la clave de transient de la autorización verificada.
     *
     * @param string $submission_id UUID válido del formulario.
     * @return string Nombre acotado para la API de transients.
     */
    private function verified_transient_key(string $submission_id): string
    {
        return 'fpw_contact_verified_' . md5($submission_id);
    }

    /**
     * is_submission_verified — Confirma que UUID y email comparten autorización vigente.
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
     * form_started_token — Firma la hora de renderizado para detectar envíos instantáneos.
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
     * is_valid_form_started_token — Comprueba firma y antigüedad razonable del formulario.
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
     * validated_return_url — Obtiene una URL local limpia para el fallback por redirección.
     *
     * @return string Referer local sin estado anterior o portada del sitio.
     */
    private function validated_return_url(): string
    {
        $referer = wp_get_referer();
        if (!$referer || !$this->is_local_url($referer)) {
            return home_url('/');
        }

        return remove_query_arg(self::STATE_QUERY, $referer);
    }

    /**
     * is_local_url — Comprueba que una URL pertenece al mismo host de WordPress.
     *
     * @param string $url URL absoluta que debe verificarse.
     * @return bool Indica si coincide con el host del sitio.
     */
    private function is_local_url(string $url): bool
    {
        $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $url_host = wp_parse_url($url, PHP_URL_HOST);

        return is_string($site_host) && is_string($url_host) && strtolower($site_host) === strtolower($url_host);
    }

    /**
     * resolve_origin — Describe la página desde la que se inició la consulta.
     *
     * @param string $url URL local validada del formulario.
     * @return array URL segura, ID opcional y título sanitizado.
     */
    private function resolve_origin(string $url): array
    {
        $post_id = url_to_postid($url);
        $title = $post_id > 0 ? get_the_title($post_id) : '';

        return array(
            'url' => esc_url_raw($url),
            'post_id' => $post_id > 0 ? $post_id : null,
            'title' => is_string($title) ? sanitize_text_field($title) : '',
        );
    }

    /**
     * send_email — Entrega la consulta al destinatario operativo configurado.
     *
     * @param array $payload Consulta sanitizada junto con sus datos de origen.
     * @return bool Resultado de `wp_mail()` o `false` si el destinatario no es válido.
     */
    private function send_email(array $payload): bool
    {
        $recipient = sanitize_email((string) apply_filters('formularios_pw_contact_email', CODEPTY_CONTACT_EMAIL));
        if (!is_email($recipient)) {
            return false;
        }

        $subject = 'Nueva consulta general en CodePTY';
        $body = "Nombre: {$payload['name']}\nTeléfono: {$payload['phone']}\nEmail: {$payload['email']}\n\nMensaje:\n{$payload['message']}\n\nOrigen: {$payload['origin_title']}\n{$payload['origin_url']}";
        $headers = array('Reply-To: ' . $payload['name'] . ' <' . $payload['email'] . '>');

        return wp_mail($recipient, $subject, $body, $headers);
    }

    /**
     * redirect_with_state — Conserva un estado breve y redirige sin exponer sus datos.
     *
     * @param string $return_url URL local validada a la que volver.
     * @param array  $state Errores, valores o estado de éxito que deben consumirse una vez.
     * @return void Finaliza la petición tras `wp_safe_redirect()`.
     * @throws Exception Si no puede generarse el token aleatorio.
     */
    private function redirect_with_state(string $return_url, array $state): void
    {
        $token = bin2hex(random_bytes(16));
        set_transient('fpw_contact_state_' . $token, $state, 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg(self::STATE_QUERY, $token, $return_url) . '#codepty-contact-1');
        exit;
    }

    /**
     * consume_state — Recupera y elimina el estado efímero señalado por la URL.
     *
     * @return array Estado consumido una sola vez o un array vacío.
     */
    private function consume_state(): array
    {
        $token = isset($_GET[self::STATE_QUERY]) ? sanitize_text_field((string) wp_unslash($_GET[self::STATE_QUERY])) : '';
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return array();
        }

        $key = 'fpw_contact_state_' . $token;
        $state = get_transient($key);
        delete_transient($key);

        return is_array($state) ? $state : array();
    }
}
