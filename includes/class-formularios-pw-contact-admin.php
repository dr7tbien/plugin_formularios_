<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Contact_Admin — Presenta consultas cifradas al equipo autorizado.
 */
final class Formularios_PW_Contact_Admin
{
    private const PAGE_SLUG = 'formularios-pw-contactos';

    /**
     * register — Registra menú y recursos de la pantalla privada de consultas.
     *
     * @return void
     */
    public function register(): void
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
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
     * render — Comprueba permisos y muestra listado o detalle de una consulta.
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

        if ($uid !== '') {
            $this->render_detail($uid);
        } else {
            $this->render_list();
        }

        echo '</div>';
    }

    /**
     * render_list — Dibuja la tabla de consultas recientes sin descifrar sus payloads.
     *
     * @return void
     */
    private function render_list(): void
    {
        $contacts = Formularios_PW_Contact_Repository::list_recent();
        echo '<div class="fpw-card"><table class="widefat striped"><thead><tr><th>Fecha</th><th>Canal</th><th>Estado</th><th>Origen</th><th>Acción</th></tr></thead><tbody>';

        if (!$contacts) {
            echo '<tr><td colspan="5">Todavía no hay consultas.</td></tr>';
        }

        foreach ($contacts as $contact) {
            $url = add_query_arg(array('page' => self::PAGE_SLUG, 'contact_uid' => $contact['contact_uid']), admin_url('admin.php'));
            $origin = !empty($contact['origin_post_id']) ? get_the_title((int) $contact['origin_post_id']) : 'URL externa o no identificada';
            echo '<tr><td>' . esc_html((string) $contact['created_at']) . '</td>';
            echo '<td>' . esc_html((string) $contact['delivery_channel']) . '</td>';
            echo '<td>' . esc_html((string) $contact['delivery_status']) . '</td>';
            echo '<td>' . esc_html((string) $origin) . '</td>';
            echo '<td><a class="button" href="' . esc_url($url) . '">Abrir</a></td></tr>';
        }

        echo '</tbody></table></div>';
    }

    /**
     * render_detail — Descifra y presenta una consulta seleccionada.
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
        $this->field('Nombre', $payload['name'] ?? '');
        $this->field('Teléfono', $payload['phone'] ?? '');
        $this->field('Email', $payload['email'] ?? '');
        $this->field('Mensaje', $payload['message'] ?? '');
        $this->field('URL de origen', $payload['origin_url'] ?? '');
        $this->field('Título de origen', $payload['origin_title'] ?? '');
        $this->field('Canal', $contact['delivery_channel']);
        $this->field('Estado', $contact['delivery_status']);
        echo '</div>';
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
}
