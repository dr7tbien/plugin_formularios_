<?php

define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
define('FORMULARIOS_PW_VERSION', '0.6.5');
define('FORMULARIOS_PW_BASENAME', 'formularios_/formularios_.php');

$test_cache = array();
$test_response = null;
$test_requests = 0;
$test_passed = 0;

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

$updater = new Formularios_PW_Updater();

reset_test_state(test_response(test_release('0.6.5')));
expect_true($updater->filter_update(false, array('Version' => '0.6.5'), FORMULARIOS_PW_BASENAME, array()) === false, 'Una versión igual no debe actualizar.');

reset_test_state(test_response(test_release('0.6.4')));
expect_true($updater->filter_update(false, array('Version' => '0.6.5'), FORMULARIOS_PW_BASENAME, array()) === false, 'Una versión inferior no debe actualizar.');

reset_test_state(test_response(test_release('0.6.6')));
$update = $updater->filter_update(false, array('Version' => '0.6.5'), FORMULARIOS_PW_BASENAME, array());
expect_true(is_array($update) && $update['new_version'] === '0.6.6', 'Una versión superior debe actualizar.');
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

reset_test_state(test_response(test_release('0.6.6')));
expect_true($updater->get_release() !== null && $updater->get_release() !== null, 'Una release válida debe poder reutilizarse desde caché.');
expect_true($test_requests === 1, 'La caché debe evitar consultas repetidas.');

$updater->clear_cache_after_upgrade(null, array('type' => 'plugin', 'action' => 'update', 'plugins' => array(FORMULARIOS_PW_BASENAME)));
expect_true(get_site_transient('formularios_pw_github_release') === false, 'La caché debe limpiarse después de actualizar.');

echo "OK: {$test_passed} comprobaciones del actualizador\n";
