<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Updater - Integra releases públicas de GitHub con el actualizador de WordPress.
 */
final class Formularios_PW_Updater
{
    private const REPOSITORY_URL = 'https://github.com/dr7tbien/plugin_formularios_';
    private const API_URL = 'https://api.github.com/repos/dr7tbien/plugin_formularios_/releases/latest';
    private const UPDATE_HOST = 'github.com';
    private const ASSET_NAME = 'formularios_.zip';
    private const SLUG = 'formularios_';
    private const CACHE_KEY = 'formularios_pw_github_release';
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;
    private const FAILURE_CACHE_TTL = 30 * MINUTE_IN_SECONDS;

    /**
     * register - Conecta comprobación, información y limpieza de caché con WordPress.
     *
     * @return void
     */
    public function register(): void
    {
        add_filter('update_plugins_' . self::UPDATE_HOST, array($this, 'filter_update'), 10, 4);
        add_filter('plugins_api', array($this, 'filter_plugin_information'), 20, 3);
        add_action('delete_site_transient_update_plugins', array($this, 'clear_release_cache'));
        add_action('upgrader_process_complete', array($this, 'clear_cache_after_upgrade'), 10, 2);
    }

    /**
     * filter_update - Devuelve una actualización solo cuando la release estable es superior.
     *
     * @param array|false $update Resultado anterior para la URI de actualización.
     * @param array       $plugin_data Cabecera normalizada del plugin instalado.
     * @param string      $plugin_file Ruta relativa del archivo principal.
     * @param array       $locales Idiomas instalados solicitados por WordPress.
     * @return array|false Metadatos seguros de actualización o el resultado anterior.
     */
    public function filter_update($update, array $plugin_data, string $plugin_file, array $locales)
    {
        unset($locales);

        if ($plugin_file !== FORMULARIOS_PW_BASENAME) {
            return $update;
        }

        $installed_version = isset($plugin_data['Version']) ? (string) $plugin_data['Version'] : FORMULARIOS_PW_VERSION;
        $release = $this->get_release();
        if (!$release || !version_compare($release['version'], $installed_version, '>')) {
            return $update;
        }

        return array(
            'id' => self::REPOSITORY_URL,
            'slug' => self::SLUG,
            'plugin' => FORMULARIOS_PW_BASENAME,
            'version' => $release['version'],
            'new_version' => $release['version'],
            'url' => $release['release_url'],
            'package' => $release['package_url'],
            'requires_php' => '7.4',
        );
    }

    /**
     * filter_plugin_information - Muestra información básica de la release en WordPress.
     *
     * @param false|object|array $result Respuesta previa de la API de plugins.
     * @param string             $action Acción solicitada.
     * @param object             $args Argumentos de la consulta.
     * @return false|object|array Información del plugin o la respuesta previa.
     */
    public function filter_plugin_information($result, string $action, object $args)
    {
        if ($action !== 'plugin_information' || !isset($args->slug) || (string) $args->slug !== self::SLUG) {
            return $result;
        }

        $release = $this->get_release();
        if (!$release) {
            return $result;
        }

        return (object) array(
            'name' => 'Formularios CodePTY',
            'slug' => self::SLUG,
            'version' => $release['version'],
            'author' => '<a href="https://codepty.com">CodePTY</a>',
            'homepage' => self::REPOSITORY_URL,
            'download_link' => $release['package_url'],
            'last_updated' => $release['published_at'],
            'requires' => '6.4',
            'requires_php' => '7.4',
            'sections' => array(
                'description' => '<p>Formulario de contacto verificado con envío directo por email.</p>',
                'changelog' => $release['notes_html'],
            ),
        );
    }

    /**
     * clear_cache_after_upgrade - Invalida la release guardada después de actualizar el plugin.
     *
     * @param WP_Upgrader $upgrader Instancia que completó el proceso.
     * @param array       $options Contexto del proceso de instalación o actualización.
     * @return void
     */
    public function clear_cache_after_upgrade($upgrader, array $options): void
    {
        unset($upgrader);

        if (($options['type'] ?? '') !== 'plugin' || ($options['action'] ?? '') !== 'update') {
            return;
        }

        $plugins = isset($options['plugins']) && is_array($options['plugins'])
            ? $options['plugins']
            : array((string) ($options['plugin'] ?? ''));

        if (in_array(FORMULARIOS_PW_BASENAME, $plugins, true)) {
            $this->clear_release_cache();
        }
    }

    /**
     * clear_release_cache - Permite que una comprobación manual consulte nuevamente GitHub.
     *
     * @return void
     */
    public function clear_release_cache(): void
    {
        delete_site_transient(self::CACHE_KEY);
    }

    /**
     * get_release - Obtiene y almacena temporalmente la última release pública válida.
     *
     * @param bool $force Fuerza una consulta nueva ignorando la caché.
     * @return array|null Metadatos normalizados o null si GitHub no ofrece una release usable.
     */
    public function get_release(bool $force = false): ?array
    {
        if (!$force) {
            $cached = get_site_transient(self::CACHE_KEY);
            if (is_array($cached) && isset($cached['status'])) {
                return $cached['status'] === 'ok' && isset($cached['release']) && is_array($cached['release'])
                    ? $cached['release']
                    : null;
            }
        }

        $response = wp_remote_get(
            self::API_URL,
            array(
                'timeout' => 8,
                'redirection' => 3,
                'headers' => array(
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'User-Agent' => 'Formularios-CodePTY/' . FORMULARIOS_PW_VERSION,
                ),
            )
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->cache_failure();
            return null;
        }

        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        $release = is_array($decoded) ? self::normalize_release($decoded) : null;
        if (!$release) {
            $this->cache_failure();
            return null;
        }

        set_site_transient(
            self::CACHE_KEY,
            array('status' => 'ok', 'release' => $release),
            self::CACHE_TTL
        );

        return $release;
    }

    /**
     * normalize_release - Valida y reduce una respuesta remota a campos confiables.
     *
     * @param array $release Release decodificada desde GitHub.
     * @return array|null Metadatos normalizados o null si la respuesta no es segura.
     */
    public static function normalize_release(array $release): ?array
    {
        if (!empty($release['draft']) || !empty($release['prerelease'])) {
            return null;
        }

        $tag = isset($release['tag_name']) ? sanitize_text_field((string) $release['tag_name']) : '';
        if (!preg_match('/^v?([0-9]+\.[0-9]+\.[0-9]+)$/', $tag, $matches)) {
            return null;
        }

        $package_url = '';
        foreach ((array) ($release['assets'] ?? array()) as $asset) {
            if (!is_array($asset)
                || (string) ($asset['name'] ?? '') !== self::ASSET_NAME
                || (string) ($asset['state'] ?? '') !== 'uploaded'
                || (int) ($asset['size'] ?? 0) <= 0) {
                continue;
            }

            $candidate = esc_url_raw((string) ($asset['browser_download_url'] ?? ''), array('https'));
            if (self::is_allowed_package_url($candidate, $tag)) {
                $package_url = $candidate;
                break;
            }
        }

        if ($package_url === '') {
            return null;
        }

        $release_url = esc_url_raw((string) ($release['html_url'] ?? ''), array('https'));
        if (!self::is_repository_url($release_url)) {
            $release_url = self::REPOSITORY_URL . '/releases';
        }

        $published_raw = sanitize_text_field((string) ($release['published_at'] ?? ''));
        $published_timestamp = $published_raw !== '' ? strtotime($published_raw) : false;
        $published_at = $published_timestamp !== false ? gmdate('Y-m-d H:i:s', $published_timestamp) : '';
        $notes = sanitize_textarea_field((string) ($release['body'] ?? ''));

        return array(
            'version' => $matches[1],
            'release_url' => $release_url,
            'package_url' => $package_url,
            'published_at' => $published_at,
            'notes_html' => $notes !== '' ? wpautop(esc_html($notes)) : '<p>Consulta la release de GitHub para conocer los cambios.</p>',
        );
    }

    /**
     * is_allowed_package_url - Limita descargas al ZIP esperado dentro del repositorio.
     *
     * @param string $url URL remota ya saneada.
     * @param string $tag Etiqueta exacta esperada para la release.
     * @return bool Indica si host, ruta y nombre del asset son exactos.
     */
    public static function is_allowed_package_url(string $url, string $tag = ''): bool
    {
        $parts = wp_parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        $prefix = '/dr7tbien/plugin_formularios_/releases/download/';
        $expected_path = $tag !== '' ? $prefix . rawurlencode($tag) . '/' . self::ASSET_NAME : '';

        return $host === self::UPDATE_HOST
            && strpos($path, $prefix) === 0
            && ($expected_path === '' || $path === $expected_path)
            && substr($path, -strlen('/' . self::ASSET_NAME)) === '/' . self::ASSET_NAME;
    }

    /**
     * is_repository_url - Comprueba que una URL informativa pertenece al repositorio público.
     *
     * @param string $url URL informativa saneada.
     * @return bool Indica si la URL está bajo el repositorio esperado.
     */
    private static function is_repository_url(string $url): bool
    {
        $parts = wp_parse_url($url);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === self::UPDATE_HOST
            && preg_match('#^/dr7tbien/plugin_formularios_(?:/|$)#', (string) ($parts['path'] ?? '')) === 1;
    }

    /**
     * cache_failure - Evita repetir inmediatamente una consulta fallida a GitHub.
     *
     * @return void
     */
    private function cache_failure(): void
    {
        set_site_transient(self::CACHE_KEY, array('status' => 'unavailable'), self::FAILURE_CACHE_TTL);
    }
}
