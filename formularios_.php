<?php
/**
 * Plugin Name: Formularios Presencia Web CodePTY
 * Description: Gestión de expedientes de Presencia Web con formulario externo por enlace secreto y formulario interno.
 * Version: 0.6.3
 * Author: CodePTY
 * Text Domain: formularios-pw
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FORMULARIOS_PW_VERSION', '0.6.3');
define('FORMULARIOS_PW_FILE', __FILE__);
define('FORMULARIOS_PW_DIR', plugin_dir_path(__FILE__));
define('FORMULARIOS_PW_URL', plugin_dir_url(__FILE__));
define('FORMULARIOS_PW_CAPABILITY', 'manage_codepty_presencia');

// Valores globales provisionales. Pueden definirse antes en wp-config.php para reemplazarlos.
if (!defined('CODEPTY_CONTACT_WHATSAPP')) {
    define('CODEPTY_CONTACT_WHATSAPP', '');
}

if (!defined('CODEPTY_CONTACT_EMAIL')) {
    define('CODEPTY_CONTACT_EMAIL', '');
}

autoload_formularios_pw_files();

/**
 * autoload_formularios_pw_files - Carga manualmente las clases base del plugin.
 *
 * @return void
 */
function autoload_formularios_pw_files(): void
{
    $files = array(
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-db.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-permissions.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-crypto.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-storage.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-audit.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-repository.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-contact-repository.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-rate-limit.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-retention.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-admin.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-public-form.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-contact-form.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-contact-admin.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-contact-cli.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-activator.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-plugin.php',
    );

    foreach ($files as $file) {
        if (is_file($file)) {
            require_once $file;
        }
    }
}

register_activation_hook(FORMULARIOS_PW_FILE, array('Formularios_PW_Activator', 'activate'));
register_deactivation_hook(FORMULARIOS_PW_FILE, array('Formularios_PW_Activator', 'deactivate'));

Formularios_PW_Plugin::instance()->run();
