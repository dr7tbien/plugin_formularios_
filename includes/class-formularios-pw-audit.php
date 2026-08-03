<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Audit — Registra eventos relevantes para trazabilidad de expedientes.
 */
final class Formularios_PW_Audit
{
    /**
     * log — Inserta un evento de auditoría con actor, tipo y metadatos mínimos.
     */
    public static function log(string $case_uid, string $event_type, array $meta = array(), string $actor_type = 'system', ?int $actor_user_id = null): void
    {
        global $wpdb;

        $wpdb->insert(
            Formularios_PW_DB::table_audit(),
            array(
                'case_uid' => $case_uid,
                'actor_type' => sanitize_key($actor_type),
                'actor_user_id' => $actor_user_id,
                'event_type' => sanitize_key($event_type),
                'event_meta' => wp_json_encode(self::normalize_meta($meta), JSON_UNESCAPED_SLASHES),
                'created_at' => current_time('mysql', true),
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s')
        );
    }

    /**
     * normalize_meta — Reduce metadatos a tipos seguros para almacenamiento en auditoría.
     */
    private static function normalize_meta(array $meta): array
    {
        $normalized = array();

        foreach ($meta as $key => $value) {
            $safe_key = sanitize_key((string) $key);

            if (is_scalar($value) || $value === null) {
                $normalized[$safe_key] = $value;
                continue;
            }

            if (is_array($value)) {
                $normalized[$safe_key] = wp_json_encode($value, JSON_UNESCAPED_SLASHES);
            }
        }

        return $normalized;
    }
}
