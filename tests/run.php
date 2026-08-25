<?php

define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);
define('FORMULARIOS_PW_VERSION', '0.6.8');
define('FORMULARIOS_PW_BASENAME', 'formularios_/formularios_.php');

$test_cache = array();
$test_response = null;
$test_requests = 0;
$test_passed = 0;
$test_mail = array();

class WP_Error
{
}

function add_filter()
{
}

function add_action()
{
}

function get_site_transient($key)
{
    global $test_cache;
    return $test_cache[$key] ?? false;
}

function set_site_transient($key, $value, $ttl)
{
    global $test_cache;
    $test_cache[$key] = array('value' => $value, 'ttl' => $ttl);
    $test_cache[$key] = $value;
    return true;
}

function delete_site_transient($key)
{
    global $test_cache;
    unset($test_cache[$key]);
    return true;
}

function wp_remote_get($url, $args)
{
    global $test_requests, $test_response;
    $test_requests++;
    return $test_response;
}

function is_wp_error($value)
{
    return $value instanceof WP_Error;
}

function wp_remote_retrieve_response_code($response)
{
    return is_array($response) ? (int) ($response['response']['code'] ?? 0) : 0;
}

function wp_remote_retrieve_body($response)
{
    return is_array($response) ? (string) ($response['body'] ?? '') : '';
}

function sanitize_text_field($value)
{
    return trim(strip_tags((string) $value));
}

function sanitize_textarea_field($value)
{
    return trim(strip_tags((string) $value));
}

function sanitize_email($value)
{
    return filter_var((string) $value, FILTER_SANITIZE_EMAIL);
}

function is_email($value)
{
    return filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false;
}

function apply_filters($tag, $value)
{
    unset($tag);
    return $value;
}

function wp_mail($recipient, $subject, $body, $headers = array())
{
    global $test_mail;
    $test_mail = compact('recipient', 'subject', 'body', 'headers');
    return true;
}

function esc_url_raw($url, $protocols = null)
{
    unset($protocols);
    return filter_var($url, FILTER_VALIDATE_URL) ? (string) $url : '';
}

function wp_parse_url($url)
{
    return parse_url($url);
}

function wpautop($text)
{
    return '<p>' . $text . '</p>';
}

function esc_html($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_attr($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_url($url, $protocols = null)
{
    unset($protocols);
    return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');
}

function test_release(string $version, array $overrides = array()): array
{
    return array_merge(
        array(
            'tag_name' => 'v' . $version,
            'draft' => false,
            'prerelease' => false,
            'html_url' => 'https://github.com/dr7tbien/plugin_formularios_/releases/tag/v' . $version,
            'published_at' => '2026-08-24T10:00:00Z',
            'body' => 'Cambios de prueba.',
            'assets' => array(
                array(
                    'name' => 'formularios_.zip',
                    'state' => 'uploaded',
                    'size' => 1024,
                    'browser_download_url' => 'https://github.com/dr7tbien/plugin_formularios_/releases/download/v' . $version . '/formularios_.zip',
                ),
            ),
        ),
        $overrides
    );
}

function test_response($body, int $code = 200): array
{
    return array('response' => array('code' => $code), 'body' => json_encode($body));
}

function reset_test_state($response): void
{
    global $test_cache, $test_requests, $test_response;
    $test_cache = array();
    $test_requests = 0;
    $test_response = $response;
}

function expect_true($condition, string $message): void
{
    global $test_passed;
    if (!$condition) {
        fwrite(STDERR, "FALLO: {$message}\n");
        exit(1);
    }
    $test_passed++;
}

require dirname(__DIR__) . '/includes/class-formularios-pw-updater.php';
require dirname(__DIR__) . '/includes/class-formularios-pw-contact-buttons.php';

$updater = new Formularios_PW_Updater();

reset_test_state(test_response(test_release('0.6.8')));
expect_true($updater->filter_update(false, array('Version' => '0.6.8'), FORMULARIOS_PW_BASENAME, array()) === false, 'Una versión igual no debe actualizar.');

reset_test_state(test_response(test_release('0.6.7')));
expect_true($updater->filter_update(false, array('Version' => '0.6.8'), FORMULARIOS_PW_BASENAME, array()) === false, 'Una versión inferior no debe actualizar.');

reset_test_state(test_response(test_release('0.6.9')));
$update = $updater->filter_update(false, array('Version' => '0.6.8'), FORMULARIOS_PW_BASENAME, array());
expect_true(is_array($update) && $update['new_version'] === '0.6.9', 'Una versión superior debe actualizar.');
expect_true($update['plugin'] === FORMULARIOS_PW_BASENAME, 'La actualización debe apuntar al plugin correcto.');

reset_test_state(test_response(array('message' => 'Not Found'), 404));
expect_true($updater->get_release() === null, 'Un repositorio sin releases no debe producir una actualización.');

reset_test_state(new WP_Error());
expect_true($updater->get_release() === null, 'Un timeout o error remoto no debe romper el plugin.');

expect_true(Formularios_PW_Updater::normalize_release(test_release('1.1.0', array('draft' => true))) === null, 'Los drafts deben ignorarse.');
expect_true(Formularios_PW_Updater::normalize_release(test_release('1.1.0', array('prerelease' => true))) === null, 'Las prereleases deben ignorarse.');
expect_true(Formularios_PW_Updater::normalize_release(test_release('1.1.0', array('tag_name' => 'release-1.1'))) === null, 'Las etiquetas inválidas deben ignorarse.');

$unsafe = test_release('1.1.0');
$unsafe['assets'][0]['browser_download_url'] = 'https://example.com/formularios_.zip';
expect_true(Formularios_PW_Updater::normalize_release($unsafe) === null, 'Las descargas ajenas a GitHub deben rechazarse.');
expect_true(!Formularios_PW_Updater::is_allowed_package_url('https://github.com/otro/repositorio/releases/download/v1.1.0/formularios_.zip'), 'Debe rechazarse otro repositorio de GitHub.');

$mismatched_tag = test_release('1.1.0');
$mismatched_tag['assets'][0]['browser_download_url'] = 'https://github.com/dr7tbien/plugin_formularios_/releases/download/v9.9.9/formularios_.zip';
expect_true(Formularios_PW_Updater::normalize_release($mismatched_tag) === null, 'El asset debe pertenecer a la etiqueta publicada.');

reset_test_state(test_response(test_release('0.6.9')));
expect_true($updater->get_release() !== null && $updater->get_release() !== null, 'Una release válida debe poder reutilizarse desde caché.');
expect_true($test_requests === 1, 'La caché debe evitar consultas repetidas.');

$updater->clear_cache_after_upgrade(null, array('type' => 'plugin', 'action' => 'update', 'plugins' => array(FORMULARIOS_PW_BASENAME)));
expect_true(get_site_transient('formularios_pw_github_release') === false, 'La caché debe limpiarse después de actualizar.');

reset_test_state(test_response(test_release('0.6.9')));
$updater->get_release();
$updater->clear_release_cache();
expect_true(get_site_transient('formularios_pw_github_release') === false, 'La comprobación manual de WordPress debe limpiar la caché.');

$valid_numbers = array('whatsapp' => '+507 6672 6470', 'phone' => '+507 6123 4567');
for ($combination = 0; $combination < 8; $combination++) {
    $flags = array(
        'whatsapp' => (bool) ($combination & 1),
        'phone' => (bool) ($combination & 2),
        'combined' => (bool) ($combination & 4),
    );
    $resolved = Formularios_PW_Contact_Buttons::resolve_configuration($flags, $valid_numbers);
    $expected_count = (int) $flags['whatsapp'] + (int) $flags['phone'] + (int) $flags['combined'];
    expect_true(count($resolved['buttons']) === $expected_count && !$resolved['errors'], 'La combinación ' . $combination . ' debe respetar las tres constantes independientemente.');
}

$current_configuration = Formularios_PW_Contact_Buttons::configuration();
expect_true(!$current_configuration['buttons'], 'Las constantes no definidas deben equivaler a false.');

$combined = Formularios_PW_Contact_Buttons::resolve_configuration(
    array('whatsapp' => false, 'phone' => false, 'combined' => true),
    $valid_numbers
);
expect_true(count($combined['buttons']) === 1 && $combined['buttons'][0]['type'] === 'combined', 'La configuración actual debe mostrar solo el botón combinado.');
expect_true($combined['buttons'][0]['tel_url'] === 'tel:+50766726470', 'El combinado debe llamar al número de WhatsApp.');
expect_true($combined['buttons'][0]['whatsapp_url'] === 'https://wa.me/50766726470', 'El combinado debe abrir wa.me con el número normalizado.');

foreach (array('', 'abc', '+123', '050766726470', '+1234567890123456') as $invalid_number) {
    expect_true(Formularios_PW_Contact_Buttons::normalize_number($invalid_number) === null, 'Debe rechazarse el número inválido: ' . $invalid_number);
}

$invalid_combined = Formularios_PW_Contact_Buttons::resolve_configuration(
    array('whatsapp' => false, 'phone' => false, 'combined' => true),
    array('whatsapp' => '', 'phone' => '+50761234567')
);
expect_true(!$invalid_combined['buttons'] && count($invalid_combined['errors']) === 1, 'El combinado inválido no debe generar enlaces y debe producir un aviso administrativo.');

$all_invalid = Formularios_PW_Contact_Buttons::resolve_configuration(
    array('whatsapp' => true, 'phone' => true, 'combined' => true),
    array('whatsapp' => 'inválido', 'phone' => '')
);
expect_true(!$all_invalid['buttons'] && count($all_invalid['errors']) === 3, 'Cada botón activo debe validar de forma independiente su número requerido.');

define('CODEPTY_SHOW_WHATSAPP_BUTTON_ON_SMARTPHONES', false);
define('CODEPTY_SHOW_PHONE_BUTTON_ON_SMARTPHONES', false);
define('CODEPTY_SHOW_PHONE_WHATSAPP_BUTTON_ON_SMARTPHONES', true);
define('CODEPTY_CONTACT_WHATSAPP', '+507 6672 6470');
define('CODEPTY_CONTACT_PHONE', '+507 6123 4567');
$combined_html = Formularios_PW_Contact_Buttons::render();
expect_true(substr_count($combined_html, '<a ') === 2, 'El combinado debe contener exactamente dos enlaces hermanos.');
expect_true(strpos($combined_html, 'tel:+50766726470') !== false, 'La zona telefónica combinada debe usar CODEPTY_CONTACT_WHATSAPP.');
expect_true(strpos($combined_html, 'https://wa.me/50766726470') !== false, 'La zona restante combinada debe abrir WhatsApp.');
$first_anchor = strpos($combined_html, '<a ');
$first_close = strpos($combined_html, '</a>', $first_anchor);
$second_anchor = strpos($combined_html, '<a ', $first_anchor + 1);
expect_true($first_close !== false && $second_anchor !== false && $first_close < $second_anchor, 'El combinado no debe anidar enlaces.');
expect_true(strpos($combined_html, 'También puedes contactarnos aquí:') !== false, 'Debe explicar amablemente que existen otras formas de contacto.');

$contact_css = file_get_contents(dirname(__DIR__) . '/assets/css/contact-form.css');
$contact_js = file_get_contents(dirname(__DIR__) . '/assets/js/contact-form.js');
expect_true(is_string($contact_css) && preg_match('/\.codepty-contact__alternatives\s*\{[^}]*display:\s*none;/s', $contact_css), 'Las alternativas deben permanecer ocultas por defecto en escritorio.');
expect_true(is_string($contact_css) && preg_match('/\.codepty-contact\.is-smartphone \.codepty-contact__alternatives\s*\{[^}]*display:\s*block;/s', $contact_css), 'Las alternativas solo deben revelarse con la clase de smartphone.');
expect_true(is_string($contact_js) && strpos($contact_js, "contact.classList.add('is-smartphone')") !== false, 'JavaScript debe habilitar las alternativas tras detectar un smartphone.');

define('CODEPTY_CONTACT_EMAIL', 'contacto@example.com');
require dirname(__DIR__) . '/includes/class-formularios-pw-contact-form.php';
$contact_form = new Formularios_PW_Contact_Form();
$identifier_method = new ReflectionMethod(Formularios_PW_Contact_Form::class, 'generate_message_identifier');
$identifier_method->setAccessible(true);
$identifiers = array();
for ($index = 0; $index < 1000; $index++) {
    $identifier = $identifier_method->invoke($contact_form);
    expect_true((bool) preg_match('/^CODEPTY-[0-9]{8}-[0-9]{6}-[A-F0-9]{16}$/', $identifier), 'El identificador debe conservar timestamp y sufijo seguro.');
    $identifiers[$identifier] = true;
}
expect_true(count($identifiers) === 1000, 'Mil identificadores generados en el mismo proceso deben ser únicos.');

$message_identifier = $identifier_method->invoke($contact_form);
$send_method = new ReflectionMethod(Formularios_PW_Contact_Form::class, 'send_email');
$send_method->setAccessible(true);
$sent = $send_method->invoke(
    $contact_form,
    array(
        'message_identifier' => $message_identifier,
        'name' => 'Cliente',
        'phone' => '+50760000000',
        'email' => 'cliente@example.com',
        'message' => 'Consulta de prueba',
        'origin_title' => 'Inicio',
        'origin_url' => 'https://codepty.com/',
        'submitted_at' => '2026-08-25 16:30:45 UTC',
    )
);
expect_true($sent === true, 'El correo final debe conservar su funcionamiento.');
expect_true($test_mail['subject'] === '[' . $message_identifier . '] Nueva consulta general en CodePTY', 'El asunto debe comenzar con el mismo identificador generado y conservar el asunto anterior.');
expect_true(strpos($test_mail['body'], 'Consulta de prueba') !== false && strpos($test_mail['body'], $message_identifier) === false, 'El cuerpo debe permanecer sin cambios y no duplicar el identificador.');

echo "OK: {$test_passed} comprobaciones del plugin\n";
