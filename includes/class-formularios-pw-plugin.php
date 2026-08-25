<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Plugin - Coordina el formulario público enviado exclusivamente por email.
 */
final class Formularios_PW_Plugin
{
    /**
     * $instance - Mantiene la instancia singleton del coordinador.
     *
     * @var self|null
     */
    private static $instance;

    /**
     * instance - Devuelve la instancia única del coordinador del plugin.
     */
    public static function instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * __construct - Impide instancias externas del coordinador principal.
     */
    private function __construct()
    {
    }

    /**
     * run - Registra el formulario público y el actualizador desde GitHub.
     */
    public function run(): void
    {
        Formularios_PW_Contact_Buttons::register();
        (new Formularios_PW_Contact_Form())->register();
        (new Formularios_PW_Updater())->register();
    }
}
