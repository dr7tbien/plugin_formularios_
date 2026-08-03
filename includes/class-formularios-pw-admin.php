<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Admin — Gestiona panel interno, expedientes, tokens y formulario de entrevista.
 */
final class Formularios_PW_Admin
{
    /**
     * PAGE_SLUG — Identifica la pantalla administrativa principal del plugin.
     */
    private const PAGE_SLUG = 'formularios-pw';

    /**
     * register — Conecta menús, acciones POST internas y estilos administrativos.
     */
    public function register(): void
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        add_action('admin_post_formularios_pw_create_case', array($this, 'handle_create_case'));
        add_action('admin_post_formularios_pw_save_internal', array($this, 'handle_save_internal'));
        add_action('admin_post_formularios_pw_change_status', array($this, 'handle_change_status'));
        add_action('admin_post_formularios_pw_regenerate_token', array($this, 'handle_regenerate_token'));
        add_action('admin_post_formularios_pw_revoke_token', array($this, 'handle_revoke_token'));
    }

    /**
     * register_menu — Registra la entrada de menú del módulo de expedientes.
     */
    public function register_menu(): void
    {
        add_menu_page(
            'Presencia Web',
            'Presencia Web',
            FORMULARIOS_PW_CAPABILITY,
            self::PAGE_SLUG,
            array($this, 'render_page'),
            'dashicons-portfolio',
            27
        );
    }

    /**
     * enqueue_assets — Carga estilos para hacer legible el panel administrativo del plugin.
     */
    public function enqueue_assets(string $hook): void
    {
        if (strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }

        wp_enqueue_style(
            'formularios-pw-admin',
            FORMULARIOS_PW_URL . 'assets/css/admin.css',
            array(),
            FORMULARIOS_PW_VERSION
        );
    }

    /**
     * render_page — Muestra listado de expedientes o detalle según parámetros de consulta.
     */
    public function render_page(): void
    {
        if (!Formularios_PW_Permissions::current_user_can_manage()) {
            wp_die('No tienes permisos para acceder a este panel.');
        }

        $this->render_header();
        $this->render_token_notice_if_any();

        $case_uid = isset($_GET['case_uid']) ? sanitize_text_field((string) wp_unslash($_GET['case_uid'])) : '';

        if ($case_uid !== '') {
            $case = Formularios_PW_Repository::get_case_by_uid($case_uid);
            if (!$case) {
                echo '<div class="notice notice-error"><p>No se encontró el expediente solicitado.</p></div>';
                $this->render_list();
                $this->render_footer();

                return;
            }

            $this->render_detail($case);
            $this->render_footer();

            return;
        }

        $this->render_create_form();
        $this->render_list();
        $this->render_footer();
    }

    /**
     * handle_create_case — Procesa alta de expediente, genera enlace secreto y estado enviado.
     */
    public function handle_create_case(): void
    {
        $this->guard_admin_post('formularios_pw_create_case');

        $client_label = sanitize_text_field((string) wp_unslash($_POST['client_label'] ?? ''));
        $owner_user_id = (int) ($_POST['owner_user_id'] ?? 0);
        $expiry_days = (int) ($_POST['expiry_days'] ?? 0);

        if ($client_label === '') {
            $this->redirect_admin(array('error' => 'Cliente obligatorio'));
        }

        try {
            Formularios_PW_Storage::ensure_storage_ready();
            Formularios_PW_Crypto::master_key();
        } catch (Throwable $e) {
            $this->redirect_admin(array('error' => $e->getMessage()));
        }

        $case = Formularios_PW_Repository::create_case($client_label, $owner_user_id);

        $expires_at = null;
        if ($expiry_days > 0) {
            $expires_at = gmdate('Y-m-d H:i:s', time() + ($expiry_days * DAY_IN_SECONDS));
        }

        $token = Formularios_PW_Repository::create_token((string) $case['case_uid'], $expires_at, get_current_user_id());
        Formularios_PW_Repository::update_status((string) $case['case_uid'], 'enviado');

        Formularios_PW_Audit::log(
            (string) $case['case_uid'],
            'case_created',
            array(
                'owner_user_id' => $owner_user_id,
                'expires_at' => $expires_at,
            ),
            'operator',
            get_current_user_id()
        );

        Formularios_PW_Audit::log(
            (string) $case['case_uid'],
            'token_created',
            array('token_prefix' => substr((string) $token['token_plain'], 0, 10)),
            'operator',
            get_current_user_id()
        );

        $link = home_url('/presencia-web-form/' . rawurlencode((string) $token['token_plain']) . '/');
        set_transient(
            $this->token_notice_key(),
            array(
                'case_uid' => (string) $case['case_uid'],
                'token' => (string) $token['token_plain'],
                'link' => $link,
            ),
            MINUTE_IN_SECONDS * 10
        );

        $this->redirect_admin(array('case_uid' => (string) $case['case_uid'], 'created' => 1));
    }

    /**
     * handle_save_internal — Guarda formulario interno de entrevista y metadatos operativos.
     */
    public function handle_save_internal(): void
    {
        $this->guard_admin_post('formularios_pw_save_internal');

        $case_uid = sanitize_text_field((string) wp_unslash($_POST['case_uid'] ?? ''));
        $case = Formularios_PW_Repository::get_case_by_uid($case_uid);

        if (!$case) {
            $this->redirect_admin(array('error' => 'Expediente no encontrado'));
        }

        $payload = Formularios_PW_Repository::get_case_payload($case);

        $internal_form = array(
            'info_negocio' => sanitize_textarea_field((string) wp_unslash($_POST['info_negocio'] ?? '')),
            'servicios_productos' => sanitize_textarea_field((string) wp_unslash($_POST['servicios_productos'] ?? '')),
            'cliente_ideal' => sanitize_textarea_field((string) wp_unslash($_POST['cliente_ideal'] ?? '')),
            'objetivo_web' => sanitize_textarea_field((string) wp_unslash($_POST['objetivo_web'] ?? '')),
            'diferenciacion' => sanitize_textarea_field((string) wp_unslash($_POST['diferenciacion'] ?? '')),
            'confianza_pruebas' => sanitize_textarea_field((string) wp_unslash($_POST['confianza_pruebas'] ?? '')),
            'marca_material_visual' => sanitize_textarea_field((string) wp_unslash($_POST['marca_material_visual'] ?? '')),
            'seo_mercados' => sanitize_textarea_field((string) wp_unslash($_POST['seo_mercados'] ?? '')),
            'competencia' => sanitize_textarea_field((string) wp_unslash($_POST['competencia'] ?? '')),
            'informacion_legal' => sanitize_textarea_field((string) wp_unslash($_POST['informacion_legal'] ?? '')),
            'cuentas_accesos' => sanitize_textarea_field((string) wp_unslash($_POST['cuentas_accesos'] ?? '')),
            'updated_at' => gmdate('c'),
            'updated_by_user_id' => get_current_user_id(),
        );

        $payload['internal_form'] = $internal_form;
        $payload['timeline'][] = array(
            'event' => 'internal_saved',
            'at' => gmdate('c'),
            'by' => get_current_user_id(),
        );

        Formularios_PW_Repository::save_case_payload($case, $payload);

        $new_owner = (int) ($_POST['owner_user_id'] ?? 0);
        Formularios_PW_Repository::update_owner($case_uid, $new_owner);

        $new_status = sanitize_key((string) ($_POST['status'] ?? ''));
        Formularios_PW_Repository::update_status($case_uid, $new_status);

        Formularios_PW_Audit::log(
            $case_uid,
            'internal_saved',
            array('status' => $new_status, 'owner_user_id' => $new_owner),
            'operator',
            get_current_user_id()
        );

        $this->redirect_admin(array('case_uid' => $case_uid, 'saved' => 1));
    }

    /**
     * handle_change_status — Cambia estado desde acción rápida del listado administrativo.
     */
    public function handle_change_status(): void
    {
        $this->guard_admin_post('formularios_pw_change_status');

        $case_uid = sanitize_text_field((string) wp_unslash($_POST['case_uid'] ?? ''));
        $status = sanitize_key((string) wp_unslash($_POST['status'] ?? ''));

        Formularios_PW_Repository::update_status($case_uid, $status);
        Formularios_PW_Audit::log(
            $case_uid,
            'status_change',
            array('status' => $status),
            'operator',
            get_current_user_id()
        );

        $this->redirect_admin(array('case_uid' => $case_uid, 'status' => 1));
    }

    /**
     * handle_regenerate_token — Revoca tokens activos y crea un nuevo enlace secreto.
     */
    public function handle_regenerate_token(): void
    {
        $this->guard_admin_post('formularios_pw_regenerate_token');

        $case_uid = sanitize_text_field((string) wp_unslash($_POST['case_uid'] ?? ''));
        $expiry_days = (int) ($_POST['expiry_days'] ?? 0);

        $case = Formularios_PW_Repository::get_case_by_uid($case_uid);
        if (!$case) {
            $this->redirect_admin(array('error' => 'Expediente no encontrado'));
        }

        Formularios_PW_Repository::revoke_active_tokens($case_uid);

        $expires_at = null;
        if ($expiry_days > 0) {
            $expires_at = gmdate('Y-m-d H:i:s', time() + ($expiry_days * DAY_IN_SECONDS));
        }

        $token = Formularios_PW_Repository::create_token($case_uid, $expires_at, get_current_user_id());
        Formularios_PW_Audit::log(
            $case_uid,
            'token_regenerated',
            array('expires_at' => $expires_at, 'token_prefix' => substr((string) $token['token_plain'], 0, 10)),
            'operator',
            get_current_user_id()
        );

        $link = home_url('/presencia-web-form/' . rawurlencode((string) $token['token_plain']) . '/');
        set_transient(
            $this->token_notice_key(),
            array(
                'case_uid' => $case_uid,
                'token' => (string) $token['token_plain'],
                'link' => $link,
            ),
            MINUTE_IN_SECONDS * 10
        );

        $this->redirect_admin(array('case_uid' => $case_uid, 'token' => 1));
    }

    /**
     * handle_revoke_token — Revoca un token concreto por hash sin exponer su valor original.
     */
    public function handle_revoke_token(): void
    {
        $this->guard_admin_post('formularios_pw_revoke_token');

        $case_uid = sanitize_text_field((string) wp_unslash($_POST['case_uid'] ?? ''));
        $token_hash = sanitize_text_field((string) wp_unslash($_POST['token_hash'] ?? ''));

        if ($token_hash === '') {
            $this->redirect_admin(array('case_uid' => $case_uid, 'error' => 'Token inválido'));
        }

        Formularios_PW_Repository::revoke_token_by_hash($token_hash);
        Formularios_PW_Audit::log(
            $case_uid,
            'token_revoked',
            array('token_hash_prefix' => substr($token_hash, 0, 10)),
            'operator',
            get_current_user_id()
        );

        $this->redirect_admin(array('case_uid' => $case_uid, 'revoked' => 1));
    }

    /**
     * render_header — Abre contenedor y título principal del área del plugin.
     */
    private function render_header(): void
    {
        echo '<div class="wrap formularios-pw">';
        echo '<h1>Expedientes Presencia Web</h1>';
        echo '<p>Panel interno autorizado para Administrador y EquipoCodePTY.</p>';

        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error"><p>' . esc_html((string) wp_unslash($_GET['error'])) . '</p></div>';
        }

        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success"><p>Expediente guardado correctamente.</p></div>';
        }
    }

    /**
     * render_footer — Cierra contenedor principal de la interfaz administrativa.
     */
    private function render_footer(): void
    {
        echo '</div>';
    }

    /**
     * render_create_form — Dibuja formulario rápido para crear ficha y enlace secreto.
     */
    private function render_create_form(): void
    {
        echo '<div class="fpw-card">';
        echo '<h2>Crear expediente</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="formularios_pw_create_case">';
        wp_nonce_field('formularios_pw_create_case');

        echo '<table class="form-table" role="presentation">';
        echo '<tr><th><label for="client_label">Cliente</label></th><td><input id="client_label" name="client_label" type="text" class="regular-text" required></td></tr>';
        echo '<tr><th><label for="owner_user_id">Responsable</label></th><td>' . $this->render_owner_select('owner_user_id', 0) . '</td></tr>';
        echo '<tr><th><label for="expiry_days">Caducidad del enlace (días)</label></th><td><input id="expiry_days" name="expiry_days" type="number" min="0" step="1" value="0"> <p class="description">0 = sin caducidad.</p></td></tr>';
        echo '</table>';

        echo '<p><button class="button button-primary" type="submit">Crear expediente y generar enlace</button></p>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * render_list — Muestra tabla resumida con cliente, estado, responsable y fechas.
     */
    private function render_list(): void
    {
        $cases = Formularios_PW_Repository::list_cases();

        echo '<div class="fpw-card">';
        echo '<h2>Listado</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Cliente</th><th>Estado</th><th>Responsable</th><th>Actualizado</th><th>Acciones</th></tr></thead><tbody>';

        if (empty($cases)) {
            echo '<tr><td colspan="5">No hay expedientes todavía.</td></tr>';
        }

        foreach ($cases as $case) {
            $detail_url = add_query_arg(
                array(
                    'page' => self::PAGE_SLUG,
                    'case_uid' => (string) $case['case_uid'],
                ),
                admin_url('admin.php')
            );

            echo '<tr>';
            echo '<td>' . esc_html((string) $case['client_label']) . '</td>';
            echo '<td><span class="fpw-status fpw-status-' . esc_attr((string) $case['status']) . '">' . esc_html($this->status_label((string) $case['status'])) . '</span></td>';
            echo '<td>' . esc_html((string) ($case['owner_name'] ?? 'Sin asignar')) . '</td>';
            echo '<td>' . esc_html((string) $case['updated_at']) . '</td>';
            echo '<td><a class="button" href="' . esc_url($detail_url) . '">Abrir</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    /**
     * render_detail — Muestra vista completa de un expediente con formularios y acciones.
     */
    private function render_detail(array $case): void
    {
        $payload = Formularios_PW_Repository::get_case_payload($case);
        $external = is_array($payload['external_form'] ?? null) ? $payload['external_form'] : array();
        $internal = is_array($payload['internal_form'] ?? null) ? $payload['internal_form'] : array();
        $tokens = Formularios_PW_Repository::list_case_tokens((string) $case['case_uid']);
        $audit = $this->recent_audit((string) $case['case_uid']);

        echo '<p><a href="' . esc_url(add_query_arg(array('page' => self::PAGE_SLUG), admin_url('admin.php'))) . '">&larr; Volver al listado</a></p>';

        echo '<div class="fpw-grid">';
        echo '<section class="fpw-card">';
        echo '<h2>Resumen</h2>';
        echo '<p><strong>Cliente:</strong> ' . esc_html((string) $case['client_label']) . '</p>';
        echo '<p><strong>Estado:</strong> ' . esc_html($this->status_label((string) $case['status'])) . '</p>';
        echo '<p><strong>Creado:</strong> ' . esc_html((string) $case['created_at']) . '</p>';
        echo '<p><strong>Actualizado:</strong> ' . esc_html((string) $case['updated_at']) . '</p>';
        echo '</section>';

        echo '<section class="fpw-card">';
        echo '<h2>Acciones de enlace secreto</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="formularios_pw_regenerate_token">';
        echo '<input type="hidden" name="case_uid" value="' . esc_attr((string) $case['case_uid']) . '">';
        wp_nonce_field('formularios_pw_regenerate_token');
        echo '<p><label>Caducidad nuevo enlace (días): <input type="number" name="expiry_days" min="0" step="1" value="0"></label></p>';
        echo '<p><button class="button" type="submit">Regenerar enlace</button></p>';
        echo '</form>';
        echo '</section>';
        echo '</div>';

        echo '<div class="fpw-card">';
        echo '<h2>Formulario externo del cliente (solo lectura interna)</h2>';
        $this->render_key_value('Negocio', (string) ($external['business_name'] ?? ''));
        $this->render_key_value('Contacto', (string) ($external['contact_name'] ?? ''));
        $this->render_key_value('Email', (string) ($external['contact_email'] ?? ''));
        $this->render_key_value('Teléfono', (string) ($external['contact_phone'] ?? ''));
        $this->render_key_value('Servicios', (string) ($external['services'] ?? ''));
        $this->render_key_value('Cliente ideal', (string) ($external['ideal_customer'] ?? ''));
        $this->render_key_value('Objetivo', (string) ($external['goal'] ?? ''));
        $this->render_key_value('Competencia', (string) ($external['competition'] ?? ''));
        $this->render_key_value('Identidad/materiales', (string) ($external['identity_materials'] ?? ''));
        $this->render_attachments(is_array($payload['attachments_meta'] ?? null) ? $payload['attachments_meta'] : array());
        echo '</div>';

        echo '<div class="fpw-card">';
        echo '<h2>Formulario interno de entrevista</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="formularios_pw_save_internal">';
        echo '<input type="hidden" name="case_uid" value="' . esc_attr((string) $case['case_uid']) . '">';
        wp_nonce_field('formularios_pw_save_internal');

        echo '<table class="form-table" role="presentation">';
        echo $this->textarea_row('1. Información del negocio', 'info_negocio', (string) ($internal['info_negocio'] ?? ''));
        echo $this->textarea_row('2. Servicios o productos', 'servicios_productos', (string) ($internal['servicios_productos'] ?? ''));
        echo $this->textarea_row('3. Cliente ideal', 'cliente_ideal', (string) ($internal['cliente_ideal'] ?? ''));
        echo $this->textarea_row('4. Objetivo de la presencia web', 'objetivo_web', (string) ($internal['objetivo_web'] ?? ''));
        echo $this->textarea_row('5. Diferenciación', 'diferenciacion', (string) ($internal['diferenciacion'] ?? ''));
        echo $this->textarea_row('6. Confianza y pruebas', 'confianza_pruebas', (string) ($internal['confianza_pruebas'] ?? ''));
        echo $this->textarea_row('7. Marca y material visual', 'marca_material_visual', (string) ($internal['marca_material_visual'] ?? ''));
        echo $this->textarea_row('8. SEO y mercados objetivo', 'seo_mercados', (string) ($internal['seo_mercados'] ?? ''));
        echo $this->textarea_row('9. Competencia', 'competencia', (string) ($internal['competencia'] ?? ''));
        echo $this->textarea_row('10. Información comercial y legal', 'informacion_legal', (string) ($internal['informacion_legal'] ?? ''));
        echo $this->textarea_row('11. Cuentas y accesos técnicos', 'cuentas_accesos', (string) ($internal['cuentas_accesos'] ?? ''));

        echo '<tr><th><label for="owner_user_id">Responsable</label></th><td>' . $this->render_owner_select('owner_user_id', (int) ($case['owner_user_id'] ?? 0)) . '</td></tr>';
        echo '<tr><th><label for="status">Estado</label></th><td>' . $this->render_status_select('status', (string) $case['status']) . '</td></tr>';
        echo '</table>';

        echo '<p><button class="button button-primary" type="submit">Guardar entrevista interna</button></p>';
        echo '</form>';
        echo '</div>';

        echo '<div class="fpw-card">';
        echo '<h2>Tokens del expediente</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Prefijo</th><th>Creado</th><th>Caduca</th><th>Revocado</th><th>Usos</th><th>Acción</th></tr></thead><tbody>';

        if (empty($tokens)) {
            echo '<tr><td colspan="6">No hay tokens registrados.</td></tr>';
        }

        foreach ($tokens as $token) {
            echo '<tr>';
            echo '<td>' . esc_html((string) $token['token_prefix']) . '...</td>';
            echo '<td>' . esc_html((string) $token['created_at']) . '</td>';
            echo '<td>' . esc_html((string) ($token['expires_at'] ?: 'Sin caducidad')) . '</td>';
            echo '<td>' . esc_html((string) ($token['revoked_at'] ?: 'Activo')) . '</td>';
            echo '<td>' . esc_html((string) $token['use_count']) . '</td>';
            echo '<td>';

            if (empty($token['revoked_at'])) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                echo '<input type="hidden" name="action" value="formularios_pw_revoke_token">';
                echo '<input type="hidden" name="case_uid" value="' . esc_attr((string) $case['case_uid']) . '">';
                echo '<input type="hidden" name="token_hash" value="' . esc_attr((string) $token['token_hash']) . '">';
                wp_nonce_field('formularios_pw_revoke_token');
                echo '<button class="button button-small" type="submit">Revocar</button>';
                echo '</form>';
            } else {
                echo 'Revocado';
            }

            echo '</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';

        echo '<div class="fpw-card">';
        echo '<h2>Auditoría reciente</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Fecha</th><th>Actor</th><th>Evento</th><th>Meta</th></tr></thead><tbody>';

        if (empty($audit)) {
            echo '<tr><td colspan="4">Sin eventos registrados.</td></tr>';
        }

        foreach ($audit as $event) {
            echo '<tr>';
            echo '<td>' . esc_html((string) $event['created_at']) . '</td>';
            echo '<td>' . esc_html((string) $event['actor_type']) . '</td>';
            echo '<td>' . esc_html((string) $event['event_type']) . '</td>';
            echo '<td><code>' . esc_html((string) $event['event_meta']) . '</code></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * render_owner_select — Construye selector de usuario responsable para el expediente.
     */
    private function render_owner_select(string $name, int $selected): string
    {
        $users = get_users(
            array(
                'role__in' => array('administrator', 'equipocodepty'),
                'orderby' => 'display_name',
                'order' => 'ASC',
                'fields' => array('ID', 'display_name'),
            )
        );

        $html = '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '">';
        $html .= '<option value="0">Sin asignar</option>';

        foreach ($users as $user) {
            $html .= '<option value="' . esc_attr((string) $user->ID) . '" ' . selected($selected, (int) $user->ID, false) . '>' . esc_html((string) $user->display_name) . '</option>';
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * render_status_select — Construye selector para transición manual de estado.
     */
    private function render_status_select(string $name, string $selected): string
    {
        $html = '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '">';

        foreach ($this->status_labels() as $value => $label) {
            $html .= '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * render_key_value — Imprime fila simple de etiqueta y contenido textual escapado.
     */
    private function render_key_value(string $label, string $value): void
    {
        echo '<p><strong>' . esc_html($label) . ':</strong><br>' . nl2br(esc_html($value)) . '</p>';
    }

    /**
     * render_attachments — Muestra metadatos de adjuntos externos sin exponer archivos directamente.
     */
    private function render_attachments(array $attachments): void
    {
        echo '<h3>Adjuntos del cliente</h3>';

        if (empty($attachments)) {
            echo '<p>No hay adjuntos registrados.</p>';

            return;
        }

        echo '<ul>';
        foreach ($attachments as $file) {
            $name = (string) ($file['original_name'] ?? 'archivo');
            $mime = (string) ($file['mime'] ?? '');
            $size = (int) ($file['size'] ?? 0);
            echo '<li>' . esc_html($name . ' (' . $mime . ', ' . size_format($size) . ')') . '</li>';
        }
        echo '</ul>';
    }

    /**
     * recent_audit — Devuelve últimos eventos de auditoría del expediente actual.
     */
    private function recent_audit(string $case_uid): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT created_at, actor_type, event_type, event_meta FROM ' . Formularios_PW_DB::table_audit() . ' WHERE case_uid = %s ORDER BY id DESC LIMIT 50',
                $case_uid
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    /**
     * status_labels — Define etiquetas legibles de los estados operativos del expediente.
     */
    private function status_labels(): array
    {
        return array(
            'pendiente' => 'Pendiente',
            'enviado' => 'Enviado',
            'recibido' => 'Recibido',
            'entrevista' => 'Entrevista',
            'completo' => 'Completo',
            'cerrado' => 'Cerrado',
        );
    }

    /**
     * status_label — Obtiene etiqueta legible para un valor de estado concreto.
     */
    private function status_label(string $status): string
    {
        $labels = $this->status_labels();

        return $labels[$status] ?? $status;
    }

    /**
     * textarea_row — Genera una fila de tabla con textarea para formularios internos extensos.
     */
    private function textarea_row(string $title, string $name, string $value): string
    {
        return '<tr><th><label for="' . esc_attr($name) . '">' . esc_html($title) . '</label></th><td><textarea id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" rows="4" class="large-text">' . esc_textarea($value) . '</textarea></td></tr>';
    }

    /**
     * guard_admin_post — Verifica permisos y nonce antes de procesar acciones internas.
     */
    private function guard_admin_post(string $action): void
    {
        if (!Formularios_PW_Permissions::current_user_can_manage()) {
            wp_die('No tienes permisos para ejecutar esta acción.');
        }

        check_admin_referer($action);
    }

    /**
     * redirect_admin — Redirige al panel del plugin con argumentos de resultado.
     */
    private function redirect_admin(array $args): void
    {
        $url = add_query_arg(
            array_merge(array('page' => self::PAGE_SLUG), $args),
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }

    /**
     * token_notice_key — Calcula clave transient por usuario para mostrar token recién generado.
     */
    private function token_notice_key(): string
    {
        return 'formularios_pw_token_notice_' . get_current_user_id();
    }

    /**
     * render_token_notice_if_any — Muestra token/plink generado y lo elimina del almacenamiento temporal.
     */
    private function render_token_notice_if_any(): void
    {
        $notice = get_transient($this->token_notice_key());
        if (!is_array($notice) || empty($notice['token']) || empty($notice['link'])) {
            return;
        }

        delete_transient($this->token_notice_key());

        echo '<div class="notice notice-success"><p><strong>Enlace secreto generado</strong></p>';
        echo '<p>Token: <code>' . esc_html((string) $notice['token']) . '</code></p>';
        echo '<p>URL: <a href="' . esc_url((string) $notice['link']) . '" target="_blank" rel="noopener noreferrer">' . esc_html((string) $notice['link']) . '</a></p>';
        echo '<p>Guárdalo en un canal seguro. El token no se volverá a mostrar completo.</p></div>';
    }
}
