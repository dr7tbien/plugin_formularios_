<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Contact_Admin - Presenta consultas cifradas al equipo autorizado.
 */
final class Formularios_PW_Contact_Admin
{
    private const PAGE_SLUG = 'formularios-pw-contactos';

    /**
     * register - Registra menú, recursos y eliminación de la pantalla privada de consultas.
     *
     * @return void
     */
    public function register(): void
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_post_formularios_pw_delete_contact', array($this, 'handle_delete'));
    }

    /**
     * register_menu — Añade Consultas generales como subpágina de Presencia Web.
     *
     * @return void
     */
    public function register_menu(): void
    {
        add_submenu_page(
            'formularios-pw',
            'Consultas generales',
            'Consultas generales',
            FORMULARIOS_PW_CAPABILITY,
            self::PAGE_SLUG,
            array($this, 'render')
        );
    }

    /**
     * enqueue_assets — Carga estilos solo dentro de la pantalla de consultas.
     *
     * @param string $hook Identificador de la pantalla administrativa actual.
     * @return void
     */
    public function enqueue_assets(string $hook): void
    {
        if (strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }

        wp_enqueue_style('formularios-pw-admin', FORMULARIOS_PW_URL . 'assets/css/admin.css', array(), FORMULARIOS_PW_VERSION);
    }

    /**
     * render - Comprueba permisos y muestra listado, detalle o resultado de eliminación.
     *
     * @return void
     */
    public function render(): void
    {
        if (!Formularios_PW_Permissions::current_user_can_manage()) {
            wp_die(esc_html__('No tienes permisos para consultar estos datos.', 'formularios-pw'));
        }

        $uid = isset($_GET['contact_uid']) ? sanitize_text_field((string) wp_unslash($_GET['contact_uid'])) : '';
        echo '<div class="wrap formularios-pw"><h1>Consultas generales</h1>';
        echo '<p>Datos cifrados, disponibles solo para Administrador y EquipoCodePTY.</p>';
        $this->render_delete_notice();

        if ($uid !== '') {
            $this->render_detail($uid);
        } else {
            $this->render_list();
        }

        echo '</div>';
    }

    /**
     * render_list - Dibuja la tabla y descifra únicamente el origen de cada consulta reciente.
     *
     * @return void
     */
    private function render_list(): void
    {
        $contacts = Formularios_PW_Contact_Repository::list_recent();
        echo '<div class="fpw-card"><table class="widefat striped"><thead><tr><th>UID</th><th>Fecha</th><th>Canal</th><th>Estado</th><th>Página de origen</th><th>Acción</th></tr></thead><tbody>';

        if (!$contacts) {
            echo '<tr><td colspan="6">Todavía no hay consultas.</td></tr>';
        }

        foreach ($contacts as $contact) {
            $url = add_query_arg(array('page' => self::PAGE_SLUG, 'contact_uid' => $contact['contact_uid']), admin_url('admin.php'));
            $origin = $this->get_origin_presentation($contact);
            echo '<tr><td><code>' . esc_html((string) $contact['contact_uid']) . '</code></td>';
            echo '<td>' . esc_html((string) $contact['created_at']) . '</td>';
            echo '<td>' . esc_html((string) $contact['delivery_channel']) . '</td>';
            echo '<td>' . esc_html((string) $contact['delivery_status']) . '</td>';
            echo '<td>' . $this->origin_html($origin) . '</td>';
            echo '<td><a class="button" href="' . esc_url($url) . '">Abrir</a></td></tr>';
        }

        echo '</tbody></table></div>';
    }

    /**
     * render_detail - Descifra y presenta una consulta seleccionada con su página de origen.
     *
     * @param string $uid Identificador público interno de la consulta.
     * @return void
     */
    private function render_detail(string $uid): void
    {
        $contact = Formularios_PW_Contact_Repository::get($uid);
        if (!$contact) {
            echo '<div class="notice notice-error"><p>Consulta no encontrada.</p></div>';
            return;
        }

        try {
            $payload = Formularios_PW_Storage::read_contact_payload((string) $contact['storage_ref']);
        } catch (Throwable $e) {
            echo '<div class="notice notice-error"><p>No se pudo descifrar la consulta.</p></div>';
            return;
        }

        $back = add_query_arg(array('page' => self::PAGE_SLUG), admin_url('admin.php'));
        echo '<p><a href="' . esc_url($back) . '">&larr; Volver</a></p><div class="fpw-card">';
        $this->field('UID', $contact['contact_uid']);
        $this->field('Nombre', $payload['name'] ?? '');
        $this->field('Teléfono', $payload['phone'] ?? '');
        $this->field('Email', $payload['email'] ?? '');
        $this->field('Mensaje', $payload['message'] ?? '');
        echo $this->origin_detail_html($this->get_origin_presentation($contact, $payload));
        $this->field('Canal', $contact['delivery_channel']);
        $this->field('Estado', $contact['delivery_status']);
        $this->render_delete_form((string) $contact['contact_uid']);
        echo '</div>';
    }

    /**
     * handle_delete - Valida y ejecuta el borrado individual de una consulta desde administración.
     *
     * @return void Finaliza redirigiendo al listado con un resultado no sensible.
     */
    public function handle_delete(): void
    {
        if (!Formularios_PW_Permissions::current_user_can_manage()) {
            wp_die(esc_html__('No tienes permisos para eliminar consultas.', 'formularios-pw'));
        }

        $uid = isset($_POST['contact_uid']) && is_string($_POST['contact_uid'])
            ? sanitize_text_field(wp_unslash($_POST['contact_uid']))
            : '';
        if (!Formularios_PW_Contact_Repository::is_valid_uid($uid)) {
            $this->redirect_after_delete('invalid');
        }

        check_admin_referer('formularios_pw_delete_contact_' . $uid, 'formularios_pw_delete_nonce');
        $deleted = Formularios_PW_Contact_Repository::delete($uid);
        $this->redirect_after_delete($deleted ? 'deleted' : 'missing');
    }

    /**
     * field — Imprime un campo de detalle escapando contenido y saltos de línea.
     *
     * @param string $label Etiqueta visible del dato.
     * @param mixed  $value Valor recuperado del payload o del índice.
     * @return void
     */
    private function field(string $label, $value): void
    {
        echo '<p><strong>' . esc_html($label) . ':</strong><br>' . nl2br(esc_html((string) $value)) . '</p>';
    }

    /**
     * render_delete_form - Muestra la acción destructiva protegida para una consulta concreta.
     *
     * @param string $uid Identificador validado de la consulta mostrada.
     * @return void
     */
    private function render_delete_form(string $uid): void
    {
        echo '<hr><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Esta consulta se eliminará definitivamente. ¿Quieres continuar?\');">';
        echo '<input type="hidden" name="action" value="formularios_pw_delete_contact">';
        echo '<input type="hidden" name="contact_uid" value="' . esc_attr($uid) . '">';
        wp_nonce_field('formularios_pw_delete_contact_' . $uid, 'formularios_pw_delete_nonce');
        submit_button('Eliminar consulta', 'delete', 'submit', false);
        echo '</form>';
    }

    /**
     * render_delete_notice - Presenta el resultado de un intento de eliminación sin revelar datos.
     *
     * @return void
     */
    private function render_delete_notice(): void
    {
        if (!isset($_GET['contact_delete']) || !is_string($_GET['contact_delete'])) {
            return;
        }

        $result = sanitize_key(wp_unslash($_GET['contact_delete']));
        $notices = array(
            'deleted' => array('success', 'La consulta se eliminó definitivamente.'),
            'missing' => array('warning', 'La consulta ya no existe o no pudo eliminarse.'),
            'invalid' => array('error', 'El identificador de la consulta no es válido.'),
        );
        if (!isset($notices[$result])) {
            return;
        }

        echo '<div class="notice notice-' . esc_attr($notices[$result][0]) . ' is-dismissible"><p>'
            . esc_html($notices[$result][1]) . '</p></div>';
    }

    /**
     * redirect_after_delete - Regresa al listado después de una eliminación administrativa.
     *
     * @param string $result Resultado normalizado de la operación.
     * @return void
     */
    private function redirect_after_delete(string $result): void
    {
        $url = add_query_arg(
            array('page' => self::PAGE_SLUG, 'contact_delete' => sanitize_key($result)),
            admin_url('admin.php')
        );
        wp_safe_redirect($url);
        exit;
    }

    /**
     * get_origin_presentation - Obtiene una página interna legible desde el payload o un registro antiguo.
     *
     * @param array      $contact Fila del índice de consultas.
     * @param array|null $payload Payload ya descifrado o null para cargarlo bajo demanda.
     * @return array URL interna limpia y etiqueta visible, o valores vacíos si no se identifica.
     */
    private function get_origin_presentation(array $contact, ?array $payload = null): array
    {
        if (null === $payload && !empty($contact['storage_ref'])) {
            try {
                $payload = Formularios_PW_Storage::read_contact_payload((string) $contact['storage_ref']);
            } catch (Throwable $e) {
                $payload = array();
            }
        }

        $payload = is_array($payload) ? $payload : array();
        $payload_url = isset($payload['origin_url']) && is_string($payload['origin_url'])
            ? $payload['origin_url']
            : '';
        $url = $this->normalize_internal_origin_url($payload_url);

        $post_id = !empty($contact['origin_post_id']) ? (int) $contact['origin_post_id'] : 0;
        if ('' === $url && $post_id > 0) {
            $permalink = get_permalink($post_id);
            $url = is_string($permalink) ? $this->normalize_internal_origin_url($permalink) : '';
        }

        if ('' === $url) {
            return array('url' => '', 'label' => 'No identificado');
        }

        $label = isset($payload['origin_title']) && is_string($payload['origin_title'])
            ? sanitize_text_field($payload['origin_title'])
            : '';
        if ('' === $label && $post_id > 0) {
            $post_title = get_the_title($post_id);
            $label = is_string($post_title) ? sanitize_text_field($post_title) : '';
        }

        if ('' === $label) {
            $path = wp_parse_url($url, PHP_URL_PATH);
            $label = is_string($path) && '' !== $path ? sanitize_text_field(rawurldecode($path)) : '/';
            $label = '' !== $label ? $label : '/';
        }

        return array('url' => $url, 'label' => $label);
    }

    /**
     * normalize_internal_origin_url - Valida una URL almacenada y elimina consulta y fragmento.
     *
     * @param string $url URL recuperada del payload cifrado o de un permalink antiguo.
     * @return string URL interna reducida a esquema, host, puerto y ruta, o cadena vacía.
     */
    private function normalize_internal_origin_url(string $url): string
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

        return esc_url_raw(
            $scheme . '://' . $host . (null !== $port ? ':' . $port : '') . $path,
            array('http', 'https')
        );
    }

    /**
     * origin_html - Genera la celda enlazada de una página de origen válida.
     *
     * @param array $origin URL y etiqueta preparadas para la interfaz.
     * @return string HTML escapado para la tabla de consultas.
     */
    private function origin_html(array $origin): string
    {
        if (empty($origin['url'])) {
            return esc_html('No identificado');
        }

        return '<a href="' . esc_url((string) $origin['url']) . '" target="_blank" rel="noopener noreferrer">'
            . esc_html((string) $origin['label']) . '</a>';
    }

    /**
     * origin_detail_html - Genera el campo de origen del detalle con enlace seguro cuando procede.
     *
     * @param array $origin URL y etiqueta preparadas para la interfaz.
     * @return string HTML escapado para el detalle de la consulta.
     */
    private function origin_detail_html(array $origin): string
    {
        return '<p><strong>Página de origen:</strong><br>' . $this->origin_html($origin) . '</p>';
    }
}
