<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_DB — Define y crea las tablas mínimas de índice, tokens y auditoría.
 */
final class Formularios_PW_DB
{
    /**
     * table_cases — Devuelve el nombre completo de la tabla de expedientes.
     */
    public static function table_cases(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'codepty_pw_cases';
    }

    /**
     * table_tokens — Devuelve el nombre completo de la tabla de tokens.
     */
    public static function table_tokens(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'codepty_pw_tokens';
    }

    /**
     * table_audit — Devuelve el nombre completo de la tabla de auditoría.
     */
    public static function table_audit(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'codepty_pw_audit';
    }

    /**
     * create_tables — Crea o actualiza las tablas necesarias del plugin.
     */
    public static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $cases_sql = 'CREATE TABLE ' . self::table_cases() . " (\n"
            . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "case_uid VARCHAR(40) NOT NULL,\n"
            . "storage_ref VARCHAR(80) NOT NULL,\n"
            . "client_label VARCHAR(190) NOT NULL,\n"
            . "status VARCHAR(20) NOT NULL DEFAULT 'pendiente',\n"
            . "owner_user_id BIGINT UNSIGNED NULL,\n"
            . "created_at DATETIME NOT NULL,\n"
            . "updated_at DATETIME NOT NULL,\n"
            . "sent_at DATETIME NULL,\n"
            . "received_at DATETIME NULL,\n"
            . "interview_at DATETIME NULL,\n"
            . "completed_at DATETIME NULL,\n"
            . "closed_at DATETIME NULL,\n"
            . "deleted_at DATETIME NULL,\n"
            . "last_activity_at DATETIME NULL,\n"
            . "PRIMARY KEY  (id),\n"
            . "UNIQUE KEY case_uid (case_uid),\n"
            . "UNIQUE KEY storage_ref (storage_ref),\n"
            . "KEY status (status),\n"
            . "KEY owner_user_id (owner_user_id),\n"
            . "KEY updated_at (updated_at)\n"
            . ') ' . $charset . ';';

        $tokens_sql = 'CREATE TABLE ' . self::table_tokens() . " (\n"
            . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "case_uid VARCHAR(40) NOT NULL,\n"
            . "token_hash CHAR(64) NOT NULL,\n"
            . "token_prefix VARCHAR(16) NOT NULL,\n"
            . "expires_at DATETIME NULL,\n"
            . "revoked_at DATETIME NULL,\n"
            . "created_by_user_id BIGINT UNSIGNED NULL,\n"
            . "created_at DATETIME NOT NULL,\n"
            . "last_used_at DATETIME NULL,\n"
            . "use_count INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "PRIMARY KEY  (id),\n"
            . "UNIQUE KEY token_hash (token_hash),\n"
            . "KEY case_uid (case_uid),\n"
            . "KEY expires_at (expires_at),\n"
            . "KEY revoked_at (revoked_at)\n"
            . ') ' . $charset . ';';

        $audit_sql = 'CREATE TABLE ' . self::table_audit() . " (\n"
            . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "case_uid VARCHAR(40) NOT NULL,\n"
            . "actor_type VARCHAR(20) NOT NULL,\n"
            . "actor_user_id BIGINT UNSIGNED NULL,\n"
            . "event_type VARCHAR(60) NOT NULL,\n"
            . "event_meta LONGTEXT NULL,\n"
            . "created_at DATETIME NOT NULL,\n"
            . "PRIMARY KEY  (id),\n"
            . "KEY case_uid (case_uid),\n"
            . "KEY event_type (event_type),\n"
            . "KEY created_at (created_at)\n"
            . ') ' . $charset . ';';

        dbDelta($cases_sql);
        dbDelta($tokens_sql);
        dbDelta($audit_sql);
    }
}
