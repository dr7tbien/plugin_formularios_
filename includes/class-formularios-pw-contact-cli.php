<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Contact_CLI - Gestiona consultas generales concretas mediante WP-CLI.
 */
final class Formularios_PW_Contact_CLI
{
    /**
     * listar - Muestra UID y metadatos operativos de las consultas recientes.
     *
     * ## OPTIONS
     *
     * [--limit=<numero>]
     * : Número de consultas, entre 1 y 200. Predeterminado: 100.
     *
     * @param array $args Argumentos posicionales no utilizados.
     * @param array $assoc_args Opciones con el límite solicitado.
     * @return void
     */
    public function listar(array $args, array $assoc_args): void
    {
        unset($args);
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 100;
        $contacts = Formularios_PW_Contact_Repository::list_recent($limit);
        $items = array();

        foreach ($contacts as $contact) {
            $items[] = array(
                'uid' => (string) ($contact['contact_uid'] ?? ''),
                'fecha' => (string) ($contact['created_at'] ?? ''),
                'canal' => (string) ($contact['delivery_channel'] ?? ''),
                'estado' => (string) ($contact['delivery_status'] ?? ''),
                'origen' => $this->origin_label($contact),
            );
        }

        if (!$items) {
            WP_CLI::success('No hay consultas generales.');
            return;
        }

        WP_CLI\Utils\format_items('table', $items, array('uid', 'fecha', 'canal', 'estado', 'origen'));
    }

    /**
     * ver - Descifra y muestra una consulta identificada por UID.
     *
     * ## OPTIONS
     *
     * <uid>
     * : Identificador hexadecimal de 24 caracteres.
     *
     * @param array $args UID recibido como primer argumento posicional.
     * @param array $assoc_args Opciones no utilizadas.
     * @return void
     */
    public function ver(array $args, array $assoc_args): void
    {
        unset($assoc_args);
        $uid = $this->required_uid($args[0] ?? '');
        $contact = Formularios_PW_Contact_Repository::get($uid);
        if (!$contact) {
            WP_CLI::error('No existe una consulta con el UID indicado.');
        }

        try {
            $payload = Formularios_PW_Storage::read_contact_payload((string) $contact['storage_ref']);
        } catch (Throwable $e) {
            WP_CLI::error('No se pudo descifrar el payload de la consulta.');
        }

        $fields = array(
            'UID' => $uid,
            'Fecha' => (string) ($contact['created_at'] ?? ''),
            'Canal' => (string) ($contact['delivery_channel'] ?? ''),
            'Estado' => (string) ($contact['delivery_status'] ?? ''),
            'Página de origen' => $this->origin_label($contact, $payload),
            'Nombre' => (string) ($payload['name'] ?? ''),
            'Teléfono' => (string) ($payload['phone'] ?? ''),
            'Email' => (string) ($payload['email'] ?? ''),
            'Mensaje' => (string) ($payload['message'] ?? ''),
        );

        foreach ($fields as $label => $value) {
            WP_CLI::line($label . ': ' . $this->terminal_text($value));
        }
    }

    /**
     * eliminar - Elimina por UID una o varias consultas generales de forma explícita.
     *
     * ## OPTIONS
     *
     * <uid>...
     * : Uno o varios identificadores hexadecimales de 24 caracteres.
     *
     * [--dry-run]
     * : Comprueba los UID y muestra el resultado sin eliminar datos.
     *
     * [--yes]
     * : Confirma el borrado definitivo; es obligatorio salvo con --dry-run.
     *
     * @param array $args Lista de UID solicitados.
     * @param array $assoc_args Indicadores dry-run y yes.
     * @return void
     */
    public function eliminar(array $args, array $assoc_args): void
    {
        if (!$args) {
            WP_CLI::error('Indica al menos un UID de consulta.');
        }

        $dry_run = isset($assoc_args['dry-run']);
        if (!$dry_run && !isset($assoc_args['yes'])) {
            WP_CLI::error('El borrado es definitivo. Revisa primero con --dry-run y confirma con --yes.');
        }

        $uids = array_values(array_unique(array_map('strtolower', $args)));
        $found = 0;
        $deleted = 0;
        $missing = 0;
        $invalid = 0;
        $failed = 0;

        foreach ($uids as $uid) {
            if (!Formularios_PW_Contact_Repository::is_valid_uid($uid)) {
                WP_CLI::warning($uid . ': UID inválido.');
                $invalid++;
                continue;
            }

            $contact = Formularios_PW_Contact_Repository::get($uid);
            if (!$contact) {
                WP_CLI::warning($uid . ': consulta no encontrada.');
                $missing++;
                continue;
            }

            $found++;
            if ($dry_run) {
                WP_CLI::line($uid . ': se eliminaría.');
                continue;
            }

            if (Formularios_PW_Contact_Repository::delete($uid)) {
                WP_CLI::success($uid . ': eliminada.');
                $deleted++;
            } else {
                WP_CLI::warning($uid . ': no pudo eliminarse.');
                $failed++;
            }
        }

        if ($dry_run) {
            WP_CLI::success(sprintf(
                'Vista previa terminada: %d encontradas, %d no encontradas y %d inválidas.',
                $found,
                $missing,
                $invalid
            ));
            return;
        }

        $summary = sprintf(
            'Borrado terminado: %d eliminadas, %d no encontradas, %d inválidas y %d errores.',
            $deleted,
            $missing,
            $invalid,
            $failed
        );
        if ($failed > 0) {
            WP_CLI::error($summary);
        }

        WP_CLI::success($summary);
    }

    /**
     * required_uid - Valida el UID obligatorio de un comando individual.
     *
     * @param mixed $uid Valor recibido desde WP-CLI.
     * @return string UID hexadecimal normalizado.
     */
    private function required_uid($uid): string
    {
        $uid = is_string($uid) ? strtolower(trim($uid)) : '';
        if (!Formularios_PW_Contact_Repository::is_valid_uid($uid)) {
            WP_CLI::error('El UID debe contener exactamente 24 caracteres hexadecimales.');
        }

        return $uid;
    }

    /**
     * origin_label - Obtiene una ruta interna segura para la salida de WP-CLI.
     *
     * @param array      $contact Fila del índice de consultas.
     * @param array|null $payload Payload ya descifrado o null para cargarlo.
     * @return string Ruta interna o No identificado.
     */
    private function origin_label(array $contact, ?array $payload = null): string
    {
        if (null === $payload && !empty($contact['storage_ref'])) {
            try {
                $payload = Formularios_PW_Storage::read_contact_payload((string) $contact['storage_ref']);
            } catch (Throwable $e) {
                $payload = array();
            }
        }

        $payload = is_array($payload) ? $payload : array();
        $url = isset($payload['origin_url']) && is_string($payload['origin_url']) ? $payload['origin_url'] : '';
        if ('' === $url && !empty($contact['origin_post_id'])) {
            $permalink = get_permalink((int) $contact['origin_post_id']);
            $url = is_string($permalink) ? $permalink : '';
        }

        $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $url_host = wp_parse_url($url, PHP_URL_HOST);
        if (!is_string($site_host) || !is_string($url_host) || strtolower($site_host) !== strtolower($url_host)) {
            return 'No identificado';
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        return is_string($path) && '' !== $path ? $this->terminal_text(rawurldecode($path)) : '/';
    }

    /**
     * terminal_text - Elimina controles que podrían alterar la salida de una terminal.
     *
     * @param string $value Texto procedente de datos almacenados.
     * @return string Texto de una sola línea seguro para WP-CLI.
     */
    private function terminal_text(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value);
        return trim((string) preg_replace('/\s+/', ' ', (string) $value));
    }
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('formularios consultas', 'Formularios_PW_Contact_CLI');
}
