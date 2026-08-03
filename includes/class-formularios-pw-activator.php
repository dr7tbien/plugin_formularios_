<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Activator — Ejecuta tareas de instalación, actualización y apagado del plugin.
 */
final class Formularios_PW_Activator
{
    /**
     * activate — Crea tablas, prepara permisos, rutas de reescritura y cron del plugin.
     */
    public static function activate(): void
    {
        Formularios_PW_DB::create_tables();
        Formularios_PW_Permissions::grant_capability();
        Formularios_PW_Public_Form::register_rewrite_rules();
        Formularios_PW_Retention::schedule();
        flush_rewrite_rules();
    }

    /**
     * deactivate — Limpia hooks temporales sin eliminar datos de expedientes.
     */
    public static function deactivate(): void
    {
        Formularios_PW_Retention::unschedule();
        flush_rewrite_rules();
    }
}
