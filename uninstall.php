<?php
/**
 * uninstall.php - Retira el cron heredado sin borrar datos históricos.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * formularios_pw_uninstall_cleanup - Elimina cron de retención para evitar ejecuciones huérfanas.
 */
function formularios_pw_uninstall_cleanup(): void
{
    $hook = 'formularios_pw_retention_cleanup';
    $timestamp = wp_next_scheduled($hook);

    if ($timestamp) {
        wp_unschedule_event($timestamp, $hook);
    }
}

formularios_pw_uninstall_cleanup();
