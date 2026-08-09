<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Plugin — Coordina el arranque de componentes administrativos, públicos y de retención.
 */
final class Formularios_PW_Plugin
{
    /**
     * $instance — Mantiene la instancia singleton del coordinador.
     *
     * @var self|null
     */
    private static $instance;

    /**
     * instance — Devuelve la instancia única del coordinador del plugin.
     */
    public static function instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * __construct — Impide instancias externas del coordinador principal.
     */
    private function __construct()
    {
    }

    /**
     * run — Registra los servicios del plugin para panel interno, formulario público y limpieza programada.
     */
    public function run(): void
    {
        Formularios_PW_Retention::register();
        (new Formularios_PW_Public_Form())->register();
        (new Formularios_PW_Contact_Form())->register();

        if (is_admin()) {
            (new Formularios_PW_Admin())->register();
            (new Formularios_PW_Contact_Admin())->register();
        }
    }
}
