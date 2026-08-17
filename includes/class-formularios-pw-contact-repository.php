<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Contact_Repository — Persiste consultas cifradas y su índice operativo mínimo.
 */
final class Formularios_PW_Contact_Repository
{
    /**
     * create — Cifra una consulta, crea su índice y registra el evento de recepción.
     *
     * @param array    $payload Datos sanitizados de la consulta y su origen.
     * @param string   $channel Canal de entrega utilizado por el formulario.
     * @param int|null $origin_post_id Entrada de WordPress que originó la consulta.
     * @return array Identificadores `contact_uid` y `storage_ref` recién creados.
     * @throws RuntimeException Cuando la fila de índice no puede insertarse.
     */
    public static function create(array $payload, string $channel, ?int $origin_post_id): array
    {
        global $wpdb;

        Formularios_PW_DB::ensure_tables();
        $contact_uid = bin2hex(random_bytes(12));
        $storage_ref = bin2hex(random_bytes(18));
        $now = current_time('mysql', true);

        Formularios_PW_Storage::write_contact_payload($storage_ref, $payload);
        $inserted = $wpdb->insert(
            Formularios_PW_DB::table_contacts(),
            array(
                'contact_uid' => $contact_uid,
                'storage_ref' => $storage_ref,
                'delivery_channel' => sanitize_key($channel),
                'delivery_status' => 'stored',
                'origin_post_id' => $origin_post_id,
                'created_at' => $now,
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s')
        );

        if ($inserted === false) {
            throw new RuntimeException('No se pudo registrar la consulta.');
        }

        Formularios_PW_Audit::log(
            $contact_uid,
            'contact_stored',
            array('channel' => $channel, 'origin_post_id' => $origin_post_id),
            'visitor'
        );

        return array('contact_uid' => $contact_uid, 'storage_ref' => $storage_ref);
    }

    /**
     * set_delivery_status — Actualiza el resultado de entrega y lo deja en auditoría.
     *
     * @param string $contact_uid Identificador de la consulta.
     * @param string $status Estado normalizado de la entrega.
     * @return void
     */
    public static function set_delivery_status(string $contact_uid, string $status): void
    {
        global $wpdb;

        $wpdb->update(
            Formularios_PW_DB::table_contacts(),
            array('delivery_status' => sanitize_key($status)),
            array('contact_uid' => $contact_uid),
            array('%s'),
            array('%s')
        );

        Formularios_PW_Audit::log($contact_uid, 'contact_delivery', array('status' => $status), 'system');
    }

    /**
     * list_recent — Devuelve consultas recientes sin descifrar su contenido.
     *
     * @param int $limit Máximo solicitado, limitado internamente entre 1 y 200.
     * @return array Filas del índice ordenadas de más nueva a más antigua.
     */
    public static function list_recent(int $limit = 100): array
    {
        global $wpdb;

        Formularios_PW_DB::ensure_tables();
        $limit = max(1, min(200, $limit));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . Formularios_PW_DB::table_contacts() . ' ORDER BY id DESC LIMIT %d',
                $limit
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    /**
     * get — Recupera la fila de índice de una consulta concreta.
     *
     * @param string $contact_uid Identificador de la consulta.
     * @return array|null Fila encontrada o `null`.
     */
    public static function get(string $contact_uid): ?array
    {
        global $wpdb;

        Formularios_PW_DB::ensure_tables();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . Formularios_PW_DB::table_contacts() . ' WHERE contact_uid = %s LIMIT 1',
                $contact_uid
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * purge_before — Elimina consultas, payloads cifrados y auditoría anteriores al umbral.
     *
     * @param string $threshold Fecha UTC en formato compatible con MySQL.
     * @return void
     */
    public static function purge_before(string $threshold): void
    {
        global $wpdb;

        Formularios_PW_DB::ensure_tables();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT contact_uid, storage_ref FROM ' . Formularios_PW_DB::table_contacts() . ' WHERE created_at < %s',
                $threshold
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            Formularios_PW_Storage::delete_contact_payload((string) $row['storage_ref']);
            $wpdb->delete(Formularios_PW_DB::table_audit(), array('case_uid' => (string) $row['contact_uid']), array('%s'));
            $wpdb->delete(Formularios_PW_DB::table_contacts(), array('contact_uid' => (string) $row['contact_uid']), array('%s'));
        }
    }
}
