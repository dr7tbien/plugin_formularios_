<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Crypto — Cifra y descifra cargas JSON con Sodium XChaCha20-Poly1305.
 */
final class Formularios_PW_Crypto
{
    /**
     * KEY_BYTES — Define el tamaño requerido de clave en bytes para el algoritmo.
     */
    private const KEY_BYTES = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES;

    /**
     * ALG — Identifica el algoritmo de cifrado aplicado en el sobre.
     */
    private const ALG = 'xchacha20poly1305-ietf';

    /**
     * encrypt_json — Cifra un array y devuelve un sobre serializable en JSON.
     */
    public static function encrypt_json(array $payload): array
    {
        $plaintext = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($plaintext)) {
            throw new RuntimeException('No se pudo serializar el contenido del expediente.');
        }

        $key = self::master_key();
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $aad = self::aad();

        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $key);

        return array(
            'v' => 1,
            'alg' => self::ALG,
            'nonce' => sodium_bin2base64($nonce, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
            'aad' => sodium_bin2base64($aad, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
            'ciphertext' => sodium_bin2base64($ciphertext, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
            'created_at' => gmdate('c'),
        );
    }

    /**
     * decrypt_json — Descifra un sobre y devuelve el array original del expediente.
     */
    public static function decrypt_json(array $envelope): array
    {
        if (!isset($envelope['nonce'], $envelope['ciphertext'])) {
            throw new RuntimeException('Sobre cifrado inválido.');
        }

        $nonce = sodium_base642bin((string) $envelope['nonce'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        $aad = isset($envelope['aad']) ? sodium_base642bin((string) $envelope['aad'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING) : self::aad();
        $ciphertext = sodium_base642bin((string) $envelope['ciphertext'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $aad, $nonce, self::master_key());
        if (!is_string($plaintext)) {
            throw new RuntimeException('No fue posible descifrar el expediente.');
        }

        $data = json_decode($plaintext, true);

        if (!is_array($data)) {
            throw new RuntimeException('El contenido descifrado no tiene formato JSON válido.');
        }

        return $data;
    }

    /**
     * master_key — Obtiene la clave maestra desde constantes o variables de entorno externas.
     */
    public static function master_key(): string
    {
        $raw = self::read_master_key_source();

        if ($raw === '') {
            $raw = self::maybe_create_wordpress_managed_key();
        }

        $binary = self::decode_key($raw);

        if (strlen($binary) !== self::KEY_BYTES) {
            throw new RuntimeException('La clave maestra debe tener exactamente 32 bytes.');
        }

        return $binary;
    }

    /**
     * aad — Construye datos autenticados adicionales para vincular el contexto de cifrado.
     */
    private static function aad(): string
    {
        return 'formularios_pw:v1';
    }

    /**
     * read_master_key_source — Lee la fuente de clave maestra desde constantes o entorno.
     */
    private static function read_master_key_source(): string
    {
        $file_candidate = '';
        $inline_candidate = '';

        if (defined('CODEPTY_PW_MASTER_KEY_FILE')) {
            $file_candidate = (string) CODEPTY_PW_MASTER_KEY_FILE;
        } elseif (getenv('CODEPTY_PW_MASTER_KEY_FILE')) {
            $file_candidate = (string) getenv('CODEPTY_PW_MASTER_KEY_FILE');
        }

        if (defined('CODEPTY_PW_MASTER_KEY')) {
            $inline_candidate = (string) CODEPTY_PW_MASTER_KEY;
        } elseif (getenv('CODEPTY_PW_MASTER_KEY')) {
            $inline_candidate = (string) getenv('CODEPTY_PW_MASTER_KEY');
        }

        if ($file_candidate !== '' && is_file($file_candidate) && is_readable($file_candidate)) {
            return trim((string) file_get_contents($file_candidate));
        }

        if ($inline_candidate !== '') {
            return trim($inline_candidate);
        }

        $option_key = get_option(self::wordpress_option_name(), '');
        if (is_string($option_key) && trim($option_key) !== '') {
            return trim($option_key);
        }

        return '';
    }

    /**
     * maybe_create_wordpress_managed_key — Crea una clave interna en opciones de WordPress si no existe otra fuente.
     */
    private static function maybe_create_wordpress_managed_key(): string
    {
        $existing = get_option(self::wordpress_option_name(), '');
        if (is_string($existing) && trim($existing) !== '') {
            return trim($existing);
        }

        $generated = base64_encode(random_bytes(self::KEY_BYTES));

        if (get_option(self::wordpress_option_name(), null) === null) {
            add_option(self::wordpress_option_name(), $generated, '', false);
        } else {
            update_option(self::wordpress_option_name(), $generated, false);
        }

        return $generated;
    }

    /**
     * wordpress_option_name — Devuelve el nombre de opción usada para la clave interna gestionada por WordPress.
     */
    private static function wordpress_option_name(): string
    {
        return 'formularios_pw_master_key';
    }

    /**
     * decode_key — Interpreta la clave en base64-url, base64 estándar o texto binario literal.
     */
    private static function decode_key(string $raw): string
    {
        $raw = trim($raw);

        // 1) Base64 estándar con o sin padding.
        $decoded_std = base64_decode($raw, true);
        if (is_string($decoded_std) && strlen($decoded_std) === self::KEY_BYTES) {
            return $decoded_std;
        }

        // 2) Base64 URL-safe sin padding.
        try {
            $decoded_url = sodium_base642bin($raw, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING, '');
            if (is_string($decoded_url) && strlen($decoded_url) === self::KEY_BYTES) {
                return $decoded_url;
            }
        } catch (Throwable $e) {
            unset($e);
        }

        // 3) Binario literal (solo en casos controlados).
        return $raw;
    }
}
