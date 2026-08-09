<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Shortcode y procesamiento público del formulario de contacto general. */
final class Formularios_PW_Contact_Form
{
    private const SHORTCODE = 'codepty_formulario_contacto';
    private const ACTION = 'formularios_pw_submit_contact';
    private const STATE_QUERY = 'codepty_contact_state';
    private static $instance = 0;
    private static $late_styles_printed = false;

    public function register(): void
    {
        add_shortcode(self::SHORTCODE, array($this, 'render'));
        add_action('admin_post_' . self::ACTION, array($this, 'handle_submit'));
        add_action('admin_post_nopriv_' . self::ACTION, array($this, 'handle_submit'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
    }

    public function register_assets(): void
    {
        wp_register_style('formularios-pw-contact', FORMULARIOS_PW_URL . 'assets/css/contact-form.css', array(), FORMULARIOS_PW_VERSION);
        wp_register_script('formularios-pw-contact', FORMULARIOS_PW_URL . 'assets/js/contact-form.js', array(), FORMULARIOS_PW_VERSION, true);

        $post = get_queried_object();
        if ($post instanceof WP_Post && has_shortcode((string) $post->post_content, self::SHORTCODE)) {
            wp_enqueue_style('formularios-pw-contact');
            wp_enqueue_script('formularios-pw-contact');
        }
    }

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
        $whatsapp_number = (string) apply_filters('formularios_pw_contact_whatsapp', CODEPTY_CONTACT_WHATSAPP);
        $whatsapp_digits = preg_replace('/\D+/', '', $whatsapp_number);
        $whatsapp_url = 'https://wa.me/' . $whatsapp_digits;

        ob_start();
        ?>
        <section id="<?php echo esc_attr($id); ?>" class="codepty-contact" aria-labelledby="<?php echo esc_attr($id); ?>-title">
            <div class="codepty-contact__whatsapp">
                <span class="codepty-contact__whatsapp-icon" aria-hidden="true">
                    <svg viewBox="0 0 16 16" focusable="false"><path fill="currentColor" d="M13.6 2.33A7.85 7.85 0 0 0 7.99 0C3.63 0 .07 3.56.06 7.93c0 1.4.37 2.76 1.06 3.96L0 16l4.2-1.1a7.93 7.93 0 0 0 3.79.96h.01c4.37 0 7.93-3.56 7.93-7.93a7.9 7.9 0 0 0-2.33-5.6ZM8 14.52a6.57 6.57 0 0 1-3.36-.92l-.24-.14-2.5.65.67-2.43-.16-.25A6.56 6.56 0 0 1 1.4 7.92 6.6 6.6 0 0 1 8 1.34a6.56 6.56 0 0 1 4.66 1.93 6.56 6.56 0 0 1 1.93 4.66A6.59 6.59 0 0 1 8 14.52Zm3.61-4.93c-.2-.1-1.17-.58-1.35-.65-.18-.06-.32-.1-.45.1-.13.2-.51.65-.63.78-.11.13-.23.15-.43.05-.2-.1-.84-.31-1.59-.99-.59-.52-.99-1.17-1.1-1.37-.12-.2-.01-.3.08-.4.09-.09.2-.23.3-.35.1-.11.13-.2.2-.33.06-.13.03-.25-.02-.35-.05-.1-.44-1.07-.61-1.47-.16-.39-.32-.33-.44-.34h-.38a.73.73 0 0 0-.53.25c-.18.2-.69.68-.69 1.65 0 .98.71 1.92.81 2.05.1.13 1.4 2.13 3.38 2.99.47.2.84.33 1.13.42.48.15.9.13 1.25.08.38-.06 1.17-.48 1.34-.94.16-.47.16-.86.11-.95-.05-.08-.18-.13-.38-.23Z"/></svg>
                </span>
                <a class="codepty-contact__whatsapp-number" href="<?php echo esc_url($whatsapp_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="Abrir WhatsApp para contactar al <?php echo esc_attr($whatsapp_number); ?>">
                    <?php echo esc_html($whatsapp_number); ?>
                </a>
            </div>

            <h2 id="<?php echo esc_attr($id); ?>-title" class="codepty-contact__title">Cuéntanos qué necesitas</h2>

            <?php if ($status === 'success') : ?>
                <div class="codepty-contact__notice codepty-contact__notice--success" role="status">Tu consulta se envió correctamente. Gracias por escribirnos.</div>
            <?php endif; ?>

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

            <form class="codepty-contact__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" novalidate>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <input type="hidden" name="submission_id" value="<?php echo esc_attr(wp_generate_uuid4()); ?>">
                <?php wp_nonce_field(self::ACTION, 'codepty_contact_nonce'); ?>

                <div class="codepty-contact__trap" aria-hidden="true">
                    <label for="<?php echo esc_attr($id); ?>-website">No completar este campo</label>
                    <input id="<?php echo esc_attr($id); ?>-website" name="website" type="text" value="" tabindex="-1" autocomplete="off">
                </div>

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

                <button class="codepty-contact__submit" type="submit">Enviar consulta</button>
                <p class="codepty-contact__progress" role="status" aria-live="polite"></p>
            </form>
        </section>
        <?php
        return $late_styles . (string) ob_get_clean();
    }

    private function input(string $id, string $name, string $label, string $type, array $values, string $autocomplete): void
    {
        ?>
        <div class="codepty-contact__field">
            <label class="codepty-contact__sr-only" for="<?php echo esc_attr($id . '-' . $name); ?>"><?php echo esc_html($label); ?></label>
            <input id="<?php echo esc_attr($id . '-' . $name); ?>" name="<?php echo esc_attr($name); ?>" type="<?php echo esc_attr($type); ?>" value="<?php echo esc_attr((string) ($values[$name] ?? '')); ?>" maxlength="190" autocomplete="<?php echo esc_attr($autocomplete); ?>" placeholder="<?php echo esc_attr($label); ?>" required>
        </div>
        <?php
    }

    public function handle_submit(): void
    {
        $return_url = $this->validated_return_url();

        if (!isset($_POST['codepty_contact_nonce']) || !wp_verify_nonce(sanitize_text_field((string) wp_unslash($_POST['codepty_contact_nonce'])), self::ACTION)) {
            $this->redirect_with_state($return_url, array('errors' => array('La sesión del formulario caducó. Actualiza la página e inténtalo nuevamente.')));
        }

        if (trim((string) wp_unslash($_POST['website'] ?? '')) !== '') {
            $this->redirect_with_state($return_url, array('errors' => array('No se pudo validar el envío.')));
        }

        $submission_id = sanitize_text_field((string) wp_unslash($_POST['submission_id'] ?? ''));
        if (!wp_is_uuid($submission_id)) {
            $this->redirect_with_state($return_url, array('errors' => array('El identificador del envío no es válido.')));
        }

        if (get_transient('fpw_contact_done_' . md5($submission_id))) {
            $this->redirect_with_state($return_url, array('status' => 'success'));
        }

        $fingerprint = Formularios_PW_Rate_Limit::fingerprint_from_request('general-contact');
        if (!Formularios_PW_Rate_Limit::allow('contact|' . $fingerprint, 5, HOUR_IN_SECONDS)) {
            $this->redirect_with_state($return_url, array('errors' => array('Has realizado demasiados intentos. Espera una hora antes de volver a enviar.')));
        }

        $values = array(
            'name' => sanitize_text_field((string) wp_unslash($_POST['name'] ?? '')),
            'phone' => sanitize_text_field((string) wp_unslash($_POST['phone'] ?? '')),
            'email' => sanitize_email((string) wp_unslash($_POST['email'] ?? '')),
            'message' => sanitize_textarea_field((string) wp_unslash($_POST['message'] ?? '')),
        );
        $errors = $this->validate($values);
        if ($errors) {
            $this->redirect_with_state($return_url, array('errors' => $errors, 'values' => $values));
        }

        $origin = $this->resolve_origin($return_url);
        $mobile = wp_is_mobile();
        $channel = $mobile ? 'whatsapp' : 'email';
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

            if ($mobile) {
                Formularios_PW_Contact_Repository::set_delivery_status((string) $contact['contact_uid'], 'whatsapp_redirected');
                wp_redirect($this->whatsapp_url($payload));
                exit;
            }

            $sent = $this->send_email($payload);
            Formularios_PW_Contact_Repository::set_delivery_status((string) $contact['contact_uid'], $sent ? 'email_sent' : 'email_failed');
            if (!$sent) {
                $this->redirect_with_state($return_url, array('errors' => array('La consulta quedó registrada, pero el correo no pudo enviarse. Nuestro equipo podrá revisarla internamente.')));
            }
        } catch (Throwable $e) {
            $this->redirect_with_state($return_url, array('errors' => array('No pudimos registrar tu consulta. Inténtalo nuevamente más tarde.'), 'values' => $values));
        }

        $this->redirect_with_state($return_url, array('status' => 'success'));
    }

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

    private function validated_return_url(): string
    {
        $referer = wp_get_referer();
        if (!$referer || !$this->is_local_url($referer)) {
            return home_url('/');
        }

        return remove_query_arg(self::STATE_QUERY, $referer);
    }

    private function is_local_url(string $url): bool
    {
        $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $url_host = wp_parse_url($url, PHP_URL_HOST);

        return is_string($site_host) && is_string($url_host) && strtolower($site_host) === strtolower($url_host);
    }

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

    private function whatsapp_url(array $payload): string
    {
        $number = preg_replace('/\D+/', '', (string) apply_filters('formularios_pw_contact_whatsapp', CODEPTY_CONTACT_WHATSAPP));
        $message = "Hola, soy {$payload['name']}.\nTeléfono: {$payload['phone']}\nEmail: {$payload['email']}\n\n{$payload['message']}\n\nOrigen: {$payload['origin_url']}";

        return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
    }

    private function redirect_with_state(string $return_url, array $state): void
    {
        $token = bin2hex(random_bytes(16));
        set_transient('fpw_contact_state_' . $token, $state, 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg(self::STATE_QUERY, $token, $return_url) . '#codepty-contact-1');
        exit;
    }

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
