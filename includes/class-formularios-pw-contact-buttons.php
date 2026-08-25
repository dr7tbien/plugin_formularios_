<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Contact_Buttons - Genera alternativas de contacto exclusivas para smartphones.
 */
final class Formularios_PW_Contact_Buttons
{
    /**
     * register - Registra avisos administrativos para configuraciones incompletas.
     *
     * @return void
     */
    public static function register(): void
    {
        add_action('admin_notices', array(__CLASS__, 'render_admin_notice'));
    }

    /**
     * render - Devuelve los botones solicitados con enlaces seguros y accesibles.
     *
     * @return string HTML seguro o cadena vacía cuando no hay opciones válidas.
     */
    public static function render(): string
    {
        $configuration = self::configuration();
        if (!$configuration['buttons']) {
            return '';
        }

        ob_start();
        ?>
        <aside class="codepty-contact__alternatives" aria-label="Otras formas de contacto">
            <p class="codepty-contact__alternatives-intro">También puedes contactarnos aquí:</p>
            <div class="codepty-contact__alternatives-list">
                <?php foreach ($configuration['buttons'] as $button) : ?>
                    <?php if ($button['type'] === 'whatsapp') : ?>
                        <a class="codepty-contact__alternative codepty-contact__alternative--whatsapp" href="<?php echo esc_url($button['whatsapp_url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr('Abrir WhatsApp con el número ' . $button['display']); ?>">
                            <?php echo self::whatsapp_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <span>WhatsApp <?php echo esc_html($button['display']); ?></span>
                        </a>
                    <?php elseif ($button['type'] === 'phone') : ?>
                        <a class="codepty-contact__alternative codepty-contact__alternative--phone" href="<?php echo esc_url($button['tel_url'], array('tel')); ?>" aria-label="<?php echo esc_attr('Llamar al número ' . $button['display']); ?>">
                            <?php echo self::phone_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <span>Llamar al <?php echo esc_html($button['display']); ?></span>
                        </a>
                    <?php else : ?>
                        <div class="codepty-contact__alternative codepty-contact__alternative--combined">
                            <a class="codepty-contact__combined-phone" href="<?php echo esc_url($button['tel_url'], array('tel')); ?>" aria-label="<?php echo esc_attr('Llamar al número ' . $button['display']); ?>">
                                <?php echo self::phone_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </a>
                            <a class="codepty-contact__combined-whatsapp" href="<?php echo esc_url($button['whatsapp_url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr('Abrir WhatsApp con el número ' . $button['display']); ?>">
                                <?php echo self::whatsapp_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <span><?php echo esc_html($button['display']); ?></span>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </aside>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * render_admin_notice - Avisa si un botón activo carece de un número válido.
     *
     * @return void
     */
    public static function render_admin_notice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $configuration = self::configuration();
        if (!$configuration['errors']) {
            return;
        }

        echo '<div class="notice notice-warning"><p><strong>'
            . esc_html__('Formularios CodePTY:', 'formularios-pw')
            . '</strong> '
            . esc_html(implode(' ', $configuration['errors']))
            . '</p></div>';
    }

    /**
     * configuration - Lee constantes y construye la configuración efectiva.
     *
     * @return array Botones válidos y avisos administrativos.
     */
    public static function configuration(): array
    {
        return self::resolve_configuration(
            array(
                'whatsapp' => self::constant_is_true('CODEPTY_SHOW_WHATSAPP_BUTTON_ON_SMARTPHONES'),
                'phone' => self::constant_is_true('CODEPTY_SHOW_PHONE_BUTTON_ON_SMARTPHONES'),
                'combined' => self::constant_is_true('CODEPTY_SHOW_PHONE_WHATSAPP_BUTTON_ON_SMARTPHONES'),
            ),
            array(
                'whatsapp' => defined('CODEPTY_CONTACT_WHATSAPP') ? (string) CODEPTY_CONTACT_WHATSAPP : '',
                'phone' => defined('CODEPTY_CONTACT_PHONE') ? (string) CODEPTY_CONTACT_PHONE : '',
            )
        );
    }

    /**
     * resolve_configuration - Resuelve de forma independiente las tres opciones solicitadas.
     *
     * @param array $flags Estados de WhatsApp, teléfono y combinado.
     * @param array $numbers Números configurados para WhatsApp y teléfono.
     * @return array Botones válidos y errores de configuración.
     */
    public static function resolve_configuration(array $flags, array $numbers): array
    {
        $buttons = array();
        $errors = array();
        $whatsapp = self::normalize_number((string) ($numbers['whatsapp'] ?? ''));
        $phone = self::normalize_number((string) ($numbers['phone'] ?? ''));

        if (!empty($flags['whatsapp'])) {
            if ($whatsapp) {
                $buttons[] = self::button_data('whatsapp', $whatsapp);
            } else {
                $errors[] = 'El botón de WhatsApp está activado, pero CODEPTY_CONTACT_WHATSAPP no contiene un número válido.';
            }
        }

        if (!empty($flags['phone'])) {
            if ($phone) {
                $buttons[] = self::button_data('phone', $phone);
            } else {
                $errors[] = 'El botón de teléfono está activado, pero CODEPTY_CONTACT_PHONE no contiene un número válido.';
            }
        }

        if (!empty($flags['combined'])) {
            if ($whatsapp) {
                $buttons[] = self::button_data('combined', $whatsapp);
            } else {
                $errors[] = 'El botón combinado está activado, pero CODEPTY_CONTACT_WHATSAPP no contiene un número válido.';
            }
        }

        return array('buttons' => $buttons, 'errors' => $errors);
    }

    /**
     * normalize_number - Convierte un teléfono internacional a formato seguro.
     *
     * @param string $raw_number Valor definido en wp-config.php.
     * @return array|null Número para mostrar, tel y wa.me, o null si es inválido.
     */
    public static function normalize_number(string $raw_number): ?array
    {
        $compact = preg_replace('/[\s().-]+/', '', trim($raw_number));
        if (!is_string($compact) || !preg_match('/^\+?([1-9][0-9]{7,14})$/', $compact, $matches)) {
            return null;
        }

        $digits = $matches[1];

        return array(
            'display' => '+' . $digits,
            'tel_url' => 'tel:+' . $digits,
            'whatsapp_url' => 'https://wa.me/' . $digits,
        );
    }

    /**
     * constant_is_true - Considera activada solo una constante booleana con valor true.
     *
     * @param string $name Nombre exacto de la constante.
     * @return bool Estado solicitado sin valores predeterminados.
     */
    private static function constant_is_true(string $name): bool
    {
        return defined($name) && constant($name) === true;
    }

    /**
     * button_data - Construye los datos comunes de un botón ya validado.
     *
     * @param string $type Tipo de botón.
     * @param array  $number Número normalizado.
     * @return array Datos listos para renderizar.
     */
    private static function button_data(string $type, array $number): array
    {
        return array_merge(array('type' => $type), $number);
    }

    /**
     * phone_icon - Devuelve el icono vectorial de teléfono.
     *
     * @return string SVG decorativo.
     */
    private static function phone_icon(): string
    {
        return '<svg class="codepty-contact__alternative-icon codepty-contact__alternative-icon--phone" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.24 11.4 11.4 0 0 0 3.6.58 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.61 21 3 13.39 3 4a1 1 0 0 1 1-1h3.46a1 1 0 0 1 1 1 11.4 11.4 0 0 0 .58 3.6 1 1 0 0 1-.24 1Z"/></svg>';
    }

    /**
     * whatsapp_icon - Devuelve el icono vectorial de WhatsApp.
     *
     * @return string SVG decorativo.
     */
    private static function whatsapp_icon(): string
    {
        return '<svg class="codepty-contact__alternative-icon codepty-contact__alternative-icon--whatsapp" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M13.6 2.33A7.85 7.85 0 0 0 7.99 0C3.63 0 .07 3.56.06 7.93c0 1.4.37 2.76 1.06 3.96L0 16l4.2-1.1a7.93 7.93 0 0 0 3.79.96h.01c4.37 0 7.93-3.56 7.93-7.93a7.9 7.9 0 0 0-2.33-5.6ZM8 14.52a6.57 6.57 0 0 1-3.36-.92l-.24-.14-2.5.65.67-2.43-.16-.25A6.56 6.56 0 0 1 1.4 7.92 6.6 6.6 0 0 1 8 1.34a6.56 6.56 0 0 1 4.66 1.93 6.56 6.56 0 0 1 1.93 4.66A6.59 6.59 0 0 1 8 14.52Zm3.61-4.93c-.2-.1-1.17-.58-1.35-.65-.18-.06-.32-.1-.45.1-.13.2-.51.65-.63.78-.11.13-.23.15-.43.05-.2-.1-.84-.31-1.59-.99-.59-.52-.99-1.17-1.1-1.37-.12-.2-.01-.3.08-.4.09-.09.2-.23.3-.35.1-.11.13-.2.2-.33.06-.13.03-.25-.02-.35-.05-.1-.44-1.07-.61-1.47-.16-.39-.32-.33-.44-.34h-.38a.73.73 0 0 0-.53.25c-.18.2-.69.68-.69 1.65 0 .98.71 1.92.81 2.05.1.13 1.4 2.13 3.38 2.99.47.2.84.33 1.13.42.48.15.9.13 1.25.08.38-.06 1.17-.48 1.34-.94.16-.47.16-.86.11-.95-.05-.08-.18-.13-.38-.23Z"/></svg>';
    }
}
