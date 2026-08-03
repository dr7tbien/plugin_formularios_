<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Rate_Limit — Aplica límites temporales para reducir abuso automatizado.
 */
final class Formularios_PW_Rate_Limit
{
    /**
     * allow — Evalúa si una clave supera el umbral dentro de una ventana temporal.
     */
    public static function allow(string $key, int $max_attempts, int $window_seconds): bool
    {
        $transient_key = 'fpw_rl_' . md5($key);
        $count = (int) get_transient($transient_key);

        if ($count >= $max_attempts) {
            return false;
        }

        $count++;
        set_transient($transient_key, $count, $window_seconds);

        return true;
    }

    /**
     * fingerprint_from_request — Construye huella de cliente a partir de IP y token hash.
     */
    public static function fingerprint_from_request(string $token_hash): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';

        return hash('sha256', $ip . '|' . $token_hash . '|' . wp_salt('nonce'));
    }
}
