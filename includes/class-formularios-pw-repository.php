<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Repository — Centraliza operaciones de índice, tokens, estados y consultas del panel.
 */
final class Formularios_PW_Repository
{
    /**
     * VALID_STATUSES — Lista los estados permitidos para un expediente.
     */
    private const VALID_STATUSES = array(
        'pendiente',
        'enviado',
        'recibido',
        'entrevista',
        'completo',
        'cerrado',
    );

    /**
     * create_case — Inserta un expediente nuevo con índice mínimo y estado inicial.
     */
    public static function create_case(string $client_label, int $owner_user_id = 0): array
    {
        global $wpdb;

        $case_uid = self::random_hex(12);
        $storage_ref = self::random_hex(18);
        $now = current_time('mysql', true);

        $wpdb->insert(
            Formularios_PW_DB::table_cases(),
            array(
                'case_uid' => $case_uid,
                'storage_ref' => $storage_ref,
                'client_label' => sanitize_text_field($client_label),
                'status' => 'pendiente',
                'owner_user_id' => $owner_user_id > 0 ? $owner_user_id : null,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );

        Formularios_PW_Storage::write_case_payload($storage_ref, Formularios_PW_Storage::default_payload($storage_ref));

        return self::get_case_by_uid($case_uid);
    }

    /**
     * list_cases — Devuelve la lista de expedientes activos ordenados por actualización.
     */
    public static function list_cases(): array
    {
        global $wpdb;

        $sql = 'SELECT c.*, u.display_name AS owner_name '
            . 'FROM ' . Formularios_PW_DB::table_cases() . ' c '
            . 'LEFT JOIN ' . $wpdb->users . ' u ON u.ID = c.owner_user_id '
            . 'WHERE c.deleted_at IS NULL '
            . 'ORDER BY c.updated_at DESC, c.id DESC';

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : array();
    }

    /**
     * get_case_by_uid — Recupera un expediente por su identificador público interno.
     */
    public static function get_case_by_uid(string $case_uid): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . Formularios_PW_DB::table_cases() . ' WHERE case_uid = %s LIMIT 1', $case_uid),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * update_owner — Actualiza el responsable interno del expediente.
     */
    public static function update_owner(string $case_uid, int $owner_user_id): void
    {
        global $wpdb;

        $wpdb->update(
            Formularios_PW_DB::table_cases(),
            array(
                'owner_user_id' => $owner_user_id > 0 ? $owner_user_id : null,
                'updated_at' => current_time('mysql', true),
            ),
            array('case_uid' => $case_uid),
            array('%d', '%s'),
            array('%s')
        );
    }

    /**
     * update_status — Cambia estado de expediente y sincroniza fechas de hito.
     */
    public static function update_status(string $case_uid, string $status): void
    {
        global $wpdb;

        $status = sanitize_key($status);
        if (!in_array($status, self::VALID_STATUSES, true)) {
            return;
        }

        $now = current_time('mysql', true);
        $data = array(
            'status' => $status,
            'updated_at' => $now,
            'last_activity_at' => $now,
        );

        if ($status === 'enviado') {
            $data['sent_at'] = $now;
        } elseif ($status === 'recibido') {
            $data['received_at'] = $now;
        } elseif ($status === 'entrevista') {
            $data['interview_at'] = $now;
        } elseif ($status === 'completo') {
            $data['completed_at'] = $now;
        } elseif ($status === 'cerrado') {
            $data['closed_at'] = $now;
        }

        $wpdb->update(
            Formularios_PW_DB::table_cases(),
            $data,
            array('case_uid' => $case_uid),
            null,
            array('%s')
        );
    }

    /**
     * create_token — Crea token secreto, almacena solo hash y devuelve token plano una sola vez.
     */
    public static function create_token(string $case_uid, ?string $expires_at, int $created_by_user_id): array
    {
        global $wpdb;

        $token_plain = self::base64url_random(32);
        $token_hash = self::hash_token($token_plain);
        $now = current_time('mysql', true);

        $wpdb->insert(
            Formularios_PW_DB::table_tokens(),
            array(
                'case_uid' => $case_uid,
                'token_hash' => $token_hash,
                'token_prefix' => substr($token_plain, 0, 10),
                'expires_at' => $expires_at,
                'revoked_at' => null,
                'created_by_user_id' => $created_by_user_id > 0 ? $created_by_user_id : null,
                'created_at' => $now,
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );

        return array(
            'token_plain' => $token_plain,
            'token_hash' => $token_hash,
        );
    }

    /**
     * revoke_active_tokens — Revoca todos los tokens vigentes de un expediente.
     */
    public static function revoke_active_tokens(string $case_uid): void
    {
        global $wpdb;

        $now = current_time('mysql', true);
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . Formularios_PW_DB::table_tokens() . ' SET revoked_at = %s WHERE case_uid = %s AND revoked_at IS NULL',
                $now,
                $case_uid
            )
        );
    }

    /**
     * revoke_token_by_hash — Revoca un token específico usando su hash almacenado.
     */
    public static function revoke_token_by_hash(string $token_hash): void
    {
        global $wpdb;

        $wpdb->update(
            Formularios_PW_DB::table_tokens(),
            array('revoked_at' => current_time('mysql', true)),
            array('token_hash' => $token_hash),
            array('%s'),
            array('%s')
        );
    }

    /**
     * list_case_tokens — Lista tokens de un expediente para gestión y revocación.
     */
    public static function list_case_tokens(string $case_uid): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . Formularios_PW_DB::table_tokens() . ' WHERE case_uid = %s ORDER BY id DESC',
                $case_uid
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    /**
     * find_case_by_token_plain — Valida un token plano y devuelve expediente junto a registro de token.
     */
    public static function find_case_by_token_plain(string $token_plain): ?array
    {
        global $wpdb;

        $token_hash = self::hash_token($token_plain);

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT t.*, c.id AS case_id, c.storage_ref, c.client_label, c.status, c.case_uid '
                . 'FROM ' . Formularios_PW_DB::table_tokens() . ' t '
                . 'INNER JOIN ' . Formularios_PW_DB::table_cases() . ' c ON c.case_uid = t.case_uid '
                . 'WHERE t.token_hash = %s AND c.deleted_at IS NULL LIMIT 1',
                $token_hash
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        if (!empty($row['revoked_at'])) {
            return null;
        }

        if (!empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time()) {
            return null;
        }

        return $row;
    }

    /**
     * mark_token_used — Incrementa uso y última fecha de acceso de un token válido.
     */
    public static function mark_token_used(int $token_id): void
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . Formularios_PW_DB::table_tokens() . ' '
                . 'SET use_count = use_count + 1, last_used_at = %s WHERE id = %d',
                current_time('mysql', true),
                $token_id
            )
        );
    }

    /**
     * get_case_payload — Lee y descifra el contenido del expediente desde almacenamiento privado.
     */
    public static function get_case_payload(array $case): array
    {
        return Formularios_PW_Storage::read_case_payload((string) $case['storage_ref']);
    }

    /**
     * save_case_payload — Cifra y guarda el contenido actualizado del expediente.
     */
    public static function save_case_payload(array $case, array $payload): void
    {
        Formularios_PW_Storage::write_case_payload((string) $case['storage_ref'], $payload);

        global $wpdb;
        $wpdb->update(
            Formularios_PW_DB::table_cases(),
            array(
                'updated_at' => current_time('mysql', true),
                'last_activity_at' => current_time('mysql', true),
            ),
            array('case_uid' => (string) $case['case_uid']),
            array('%s', '%s'),
            array('%s')
        );
    }

    /**
     * hash_token — Convierte un token plano en hash irreversible para persistencia.
     */
    public static function hash_token(string $token_plain): string
    {
        return hash_hmac('sha256', $token_plain, wp_salt('auth'));
    }

    /**
     * random_hex — Genera identificadores hexadecimales aleatorios para índice y archivos.
     */
    private static function random_hex(int $bytes): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * base64url_random — Genera token URL-safe de longitud alta para acceso externo.
     */
    private static function base64url_random(int $bytes): string
    {
        $random = random_bytes($bytes);

        return rtrim(strtr(base64_encode($random), '+/', '-_'), '=');
    }
}
