<?php
/**
 * Plugin Name: Formularios CodePTY
 * Description: Formulario de contacto verificado con envío directo por email.
 * Version: 0.6.8
 * Author: CodePTY
 * Text Domain: formularios-pw
 * Update URI: https://github.com/dr7tbien/plugin_formularios_
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FORMULARIOS_PW_VERSION', '0.6.8');
define('FORMULARIOS_PW_FILE', __FILE__);
define('FORMULARIOS_PW_DIR', plugin_dir_path(__FILE__));
define('FORMULARIOS_PW_URL', plugin_dir_url(__FILE__));
define('FORMULARIOS_PW_BASENAME', plugin_basename(__FILE__));

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
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-rate-limit.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-contact-buttons.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-contact-form.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-updater.php',
        FORMULARIOS_PW_DIR . 'includes/class-formularios-pw-plugin.php',
    );

    foreach ($files as $file) {
        if (is_file($file)) {
            require_once $file;
        }
    }
}

Formularios_PW_Plugin::instance()->run();
