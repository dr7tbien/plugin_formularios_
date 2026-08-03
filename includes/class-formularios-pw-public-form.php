<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Public_Form — Expone el formulario externo por token secreto sin usuarios WordPress.
 */
final class Formularios_PW_Public_Form
{
    /**
     * QUERY_VAR — Define la variable de consulta usada para resolver el token público.
     */
    private const QUERY_VAR = 'codepty_pw_token';

    /**
     * register — Registra rewrite, query var y resolución de la vista pública.
     */
    public function register(): void
    {
        add_action('init', array(__CLASS__, 'register_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_var'));
        add_action('template_redirect', array($this, 'maybe_render'));
    }

    /**
     * register_rewrite_rules — Define la ruta pública amigable para token externo.
     */
    public static function register_rewrite_rules(): void
    {
        add_rewrite_rule('^presencia-web-form/([^/]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top');
    }

    /**
     * add_query_var — Declara la query var personalizada para resolver tokens.
     */
    public function add_query_var(array $vars): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    /**
     * maybe_render — Atiende la solicitud del formulario externo si la URL incluye token.
     */
    public function maybe_render(): void
    {
        $token_plain = get_query_var(self::QUERY_VAR);
        if (!is_string($token_plain) || $token_plain === '') {
            return;
        }

        nocache_headers();

        $binding = Formularios_PW_Repository::find_case_by_token_plain($token_plain);
        if (!$binding) {
            $this->render_error_page(403, 'Enlace no válido, caducado o revocado.');
            exit;
        }

        $fingerprint = Formularios_PW_Rate_Limit::fingerprint_from_request((string) $binding['token_hash']);
        if (!Formularios_PW_Rate_Limit::allow('view|' . $fingerprint, 100, 15 * MINUTE_IN_SECONDS)) {
            $this->render_error_page(429, 'Has superado el límite temporal de solicitudes.');
            exit;
        }

        $case = Formularios_PW_Repository::get_case_by_uid((string) $binding['case_uid']);
        if (!$case) {
            $this->render_error_page(404, 'No se encontró el expediente solicitado.');
            exit;
        }

        $payload = Formularios_PW_Repository::get_case_payload($case);
        $errors = array();
        $success = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->passes_honeypot()) {
                $this->render_error_page(400, 'No se pudo validar el envío.');
                exit;
            }

            if (!Formularios_PW_Rate_Limit::allow('submit|' . $fingerprint, 25, 15 * MINUTE_IN_SECONDS)) {
                $this->render_error_page(429, 'Demasiados intentos de envío. Prueba de nuevo más tarde.');
                exit;
            }

            try {
                $payload = $this->merge_external_form_payload($payload, $case);
                Formularios_PW_Repository::save_case_payload($case, $payload);
                Formularios_PW_Repository::mark_token_used((int) $binding['id']);

                if (in_array((string) $case['status'], array('pendiente', 'enviado'), true)) {
                    Formularios_PW_Repository::update_status((string) $case['case_uid'], 'recibido');
                }

                Formularios_PW_Audit::log(
                    (string) $case['case_uid'],
                    'external_submitted',
                    array('token_id' => (int) $binding['id']),
                    'client',
                    null
                );
                $success = true;
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        $this->render_form_page($case, $payload, $errors, $success);
        exit;
    }

    /**
     * merge_external_form_payload — Sanitiza campos externos y adjuntos antes de persistir expediente.
     */
    private function merge_external_form_payload(array $payload, array $case): array
    {
        $external = array(
            'business_name' => sanitize_text_field((string) $this->post_value('business_name')),
            'contact_name' => sanitize_text_field((string) $this->post_value('contact_name')),
            'contact_email' => sanitize_email((string) $this->post_value('contact_email')),
            'contact_phone' => sanitize_text_field((string) $this->post_value('contact_phone')),
            'services' => sanitize_textarea_field((string) $this->post_value('services')),
            'ideal_customer' => sanitize_textarea_field((string) $this->post_value('ideal_customer')),
            'goal' => sanitize_textarea_field((string) $this->post_value('goal')),
            'competition' => sanitize_textarea_field((string) $this->post_value('competition')),
            'identity_materials' => sanitize_textarea_field((string) $this->post_value('identity_materials')),
            'updated_at' => gmdate('c'),
        );

        if (!is_array($payload['attachments_meta'] ?? null)) {
            $payload['attachments_meta'] = array();
        }

        $new_attachments = $this->process_uploaded_files((string) $case['storage_ref']);
        if (!empty($new_attachments)) {
            $payload['attachments_meta'] = array_merge($payload['attachments_meta'], $new_attachments);
        }

        $payload['external_form'] = $external;
        $payload['timeline'][] = array(
            'event' => 'external_saved',
            'at' => gmdate('c'),
        );

        return $payload;
    }

    /**
     * process_uploaded_files — Recorre archivos del campo materials y almacena adjuntos cifrados.
     */
    private function process_uploaded_files(string $storage_ref): array
    {
        if (empty($_FILES['materials']) || !is_array($_FILES['materials'])) {
            return array();
        }

        $files = $_FILES['materials'];
        if (!isset($files['name']) || !is_array($files['name'])) {
            return array();
        }

        $stored = array();
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            $entry = array(
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            );

            if ((int) $entry['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $stored[] = Formularios_PW_Storage::store_uploaded_file($storage_ref, $entry);
        }

        return $stored;
    }

    /**
     * passes_honeypot — Verifica campo trampa anti-bot para rechazar envíos automatizados.
     */
    private function passes_honeypot(): bool
    {
        $honeypot = isset($_POST['website_url']) ? trim((string) wp_unslash($_POST['website_url'])) : '';

        return $honeypot === '';
    }

    /**
     * post_value — Obtiene valor POST des-escapado para una clave dada.
     */
    private function post_value(string $key): string
    {
        return isset($_POST[$key]) ? (string) wp_unslash($_POST[$key]) : '';
    }

    /**
     * render_error_page — Muestra una salida mínima para token inválido o acceso bloqueado.
     */
    private function render_error_page(int $status, string $message): void
    {
        status_header($status);
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

        echo '<!doctype html><html><head><meta charset="utf-8"><title>Formulario Presencia Web</title>';
        echo '<style>body{font-family:Arial,sans-serif;padding:32px;max-width:820px;margin:0 auto;} .box{background:#fff4f4;border:1px solid #d33;padding:16px;border-radius:8px;}</style>';
        echo '</head><body><h1>Formulario Presencia Web</h1><div class="box">' . esc_html($message) . '</div></body></html>';
    }

    /**
     * render_form_page — Renderiza el formulario externo editable y mensajes de confirmación.
     */
    private function render_form_page(array $case, array $payload, array $errors, bool $success): void
    {
        status_header(200);
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

        $external = is_array($payload['external_form'] ?? null) ? $payload['external_form'] : array();

        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>Formulario Presencia Web</title>';
        echo '<style>';
        echo 'body{font-family:Arial,sans-serif;background:#f6f8fb;margin:0;padding:20px;color:#172026;}';
        echo '.wrap{max-width:860px;margin:0 auto;background:#fff;border:1px solid #dde4ec;border-radius:12px;padding:22px;}';
        echo 'h1{margin-top:0;}label{display:block;font-weight:600;margin-top:14px;}';
        echo 'input,textarea{width:100%;padding:10px;border:1px solid #c7d3e0;border-radius:8px;font:inherit;}';
        echo 'textarea{min-height:120px;}';
        echo '.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}';
        echo '.msg-ok{background:#ebf8ef;border:1px solid #2f9e44;padding:10px;border-radius:8px;margin-bottom:12px;}';
        echo '.msg-err{background:#fff1f1;border:1px solid #db5c5c;padding:10px;border-radius:8px;margin-bottom:12px;}';
        echo '.hp{position:absolute;left:-9999px;opacity:0;}button{margin-top:16px;background:#0b65c2;color:#fff;border:0;padding:10px 16px;border-radius:8px;cursor:pointer;}';
        echo '@media (max-width:700px){.grid{grid-template-columns:1fr;}}';
        echo '</style></head><body><main class="wrap">';
        echo '<h1>Formulario inicial de Presencia Web</h1>';
        echo '<p>Cliente: <strong>' . esc_html((string) $case['client_label']) . '</strong></p>';

        if ($success) {
            echo '<div class="msg-ok">Tu información se ha guardado correctamente. Puedes volver a este enlace para editarla cuando quieras.</div>';
        }

        foreach ($errors as $error) {
            echo '<div class="msg-err">' . esc_html((string) $error) . '</div>';
        }

        echo '<form method="post" enctype="multipart/form-data">';
        echo '<div class="hp"><label>Website</label><input type="text" name="website_url" value=""></div>';

        echo '<div class="grid">';
        echo '<label>Nombre del negocio<input type="text" name="business_name" value="' . esc_attr((string) ($external['business_name'] ?? '')) . '" required></label>';
        echo '<label>Persona de contacto<input type="text" name="contact_name" value="' . esc_attr((string) ($external['contact_name'] ?? '')) . '"></label>';
        echo '<label>Email<input type="email" name="contact_email" value="' . esc_attr((string) ($external['contact_email'] ?? '')) . '"></label>';
        echo '<label>Teléfono<input type="text" name="contact_phone" value="' . esc_attr((string) ($external['contact_phone'] ?? '')) . '"></label>';
        echo '</div>';

        echo '<label>Servicios o productos<textarea name="services">' . esc_textarea((string) ($external['services'] ?? '')) . '</textarea></label>';
        echo '<label>Cliente ideal<textarea name="ideal_customer">' . esc_textarea((string) ($external['ideal_customer'] ?? '')) . '</textarea></label>';
        echo '<label>Objetivo principal<textarea name="goal">' . esc_textarea((string) ($external['goal'] ?? '')) . '</textarea></label>';
        echo '<label>Competencia<textarea name="competition">' . esc_textarea((string) ($external['competition'] ?? '')) . '</textarea></label>';
        echo '<label>Identidad y materiales disponibles<textarea name="identity_materials">' . esc_textarea((string) ($external['identity_materials'] ?? '')) . '</textarea></label>';

        echo '<label>Subir logotipos, fotos, videos y documentos<input type="file" name="materials[]" multiple></label>';

        echo '<button type="submit">Guardar formulario</button>';
        echo '</form></main></body></html>';
    }
}
