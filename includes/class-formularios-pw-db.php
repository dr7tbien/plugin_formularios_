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
     * SCHEMA_TABLES — Lista las tablas que el plugin necesita para funcionar.
     */
    private const SCHEMA_TABLES = array('cases', 'tokens', 'audit', 'payloads', 'contacts');

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
     * table_payloads — Devuelve el nombre completo de la tabla de payloads cifrados en DB.
     */
    public static function table_payloads(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'codepty_pw_payloads';
    }

    /**
     * table_contacts — Devuelve el nombre completo de la tabla de consultas generales.
     *
     * @return string
     */
    public static function table_contacts(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'codepty_pw_contacts';
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

        $payloads_sql = 'CREATE TABLE ' . self::table_payloads() . " (\n"
            . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "storage_ref VARCHAR(80) NOT NULL,\n"
            . "object_type VARCHAR(20) NOT NULL,\n"
            . "object_ref VARCHAR(80) NOT NULL DEFAULT '',\n"
            . "payload LONGTEXT NOT NULL,\n"
            . "updated_at DATETIME NOT NULL,\n"
            . "PRIMARY KEY  (id),\n"
            . "UNIQUE KEY storage_object (storage_ref, object_type, object_ref),\n"
            . "KEY updated_at (updated_at)\n"
            . ') ' . $charset . ';';

        $contacts_sql = 'CREATE TABLE ' . self::table_contacts() . " (\n"
            . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "contact_uid VARCHAR(40) NOT NULL,\n"
            . "storage_ref VARCHAR(80) NOT NULL,\n"
            . "delivery_channel VARCHAR(20) NOT NULL,\n"
            . "delivery_status VARCHAR(20) NOT NULL DEFAULT 'stored',\n"
            . "origin_post_id BIGINT UNSIGNED NULL,\n"
            . "created_at DATETIME NOT NULL,\n"
            . "PRIMARY KEY  (id),\n"
            . "UNIQUE KEY contact_uid (contact_uid),\n"
            . "UNIQUE KEY storage_ref (storage_ref),\n"
            . "KEY created_at (created_at),\n"
            . "KEY delivery_status (delivery_status)\n"
            . ') ' . $charset . ';';

        dbDelta($cases_sql);
        dbDelta($tokens_sql);
        dbDelta($audit_sql);
        dbDelta($payloads_sql);
        dbDelta($contacts_sql);
    }

    /**
     * ensure_tables — Comprueba el esquema del plugin y lo crea si falta alguna tabla.
     */
    public static function ensure_tables(): void
    {
        if (self::schema_is_complete()) {
            return;
        }

        self::create_tables();
    }

    /**
     * schema_is_complete — Verifica si todas las tablas del plugin ya existen.
     */
    public static function schema_is_complete(): bool
    {
        foreach (self::SCHEMA_TABLES as $table_name) {
            if (!self::table_exists($table_name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * table_exists — Comprueba si existe una tabla concreta del plugin.
     */
    public static function table_exists(string $table_name): bool
    {
        global $wpdb;

        $map = array(
            'cases' => self::table_cases(),
            'tokens' => self::table_tokens(),
            'audit' => self::table_audit(),
            'payloads' => self::table_payloads(),
            'contacts' => self::table_contacts(),
        );

        if (!isset($map[$table_name])) {
            return false;
        }

        $table = $map[$table_name];
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        return is_string($found) && $found === $table;
    }
}
