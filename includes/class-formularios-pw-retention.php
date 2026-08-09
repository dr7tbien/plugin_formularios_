<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Retention — Prepara política de conservación y purga programada extensible.
 */
final class Formularios_PW_Retention
{
    /**
     * HOOK — Define el nombre del evento cron diario del plugin.
     */
    private const HOOK = 'formularios_pw_retention_cleanup';

    /**
     * register — Conecta el callback de limpieza al evento cron del plugin.
     */
    public static function register(): void
    {
        add_action(self::HOOK, array(__CLASS__, 'run_cleanup'));
    }

    /**
     * schedule — Programa la tarea diaria si aún no existe en el calendario de WordPress.
     */
    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 120, 'daily', self::HOOK);
        }
    }

    /**
     * unschedule — Elimina la tarea programada al desactivar el plugin.
     */
    public static function unschedule(): void
    {
        $timestamp = wp_next_scheduled(self::HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK);
        }
    }

    /**
     * run_cleanup — Ejecuta la purga según política configurada sin borrar por defecto.
     */
    public static function run_cleanup(): void
    {
        $enabled = (bool) apply_filters('formularios_pw_retention_enabled', false);
        $days = (int) apply_filters('formularios_pw_retention_days', 0);

        if (!$enabled || $days <= 0) {
            return;
        }

        global $wpdb;

        $threshold = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $table = Formularios_PW_DB::table_cases();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT case_uid, storage_ref FROM ' . $table . ' WHERE status = %s AND closed_at IS NOT NULL AND closed_at < %s',
                'cerrado',
                $threshold
            ),
            ARRAY_A
        );

        if (is_array($rows)) {
            foreach ($rows as $row) {
                Formularios_PW_Storage::delete_case_artifacts((string) $row['storage_ref']);

                $wpdb->update(
                    $table,
                    array('deleted_at' => current_time('mysql', true)),
                    array('case_uid' => (string) $row['case_uid']),
                    array('%s'),
                    array('%s')
                );

                Formularios_PW_Audit::log((string) $row['case_uid'], 'retention_mark_deleted', array('threshold' => $threshold));
            }
        }

        $contact_days = (int) apply_filters('formularios_pw_contact_retention_days', $days);
        if ($contact_days > 0) {
            Formularios_PW_Contact_Repository::purge_before(
                gmdate('Y-m-d H:i:s', time() - ($contact_days * DAY_IN_SECONDS))
            );
        }
    }

    /**
     * delete_case_files — Elimina archivos cifrados de expediente y adjuntos durante purga aprobada.
     */
    private static function delete_case_files(string $storage_ref): void
    {
        Formularios_PW_Storage::delete_case_artifacts($storage_ref);
    }
}
