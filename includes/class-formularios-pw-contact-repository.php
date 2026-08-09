<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Persistencia cifrada e índice mínimo de consultas generales. */
final class Formularios_PW_Contact_Repository
{
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
