<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Aipex_Client_OS_Activator
{
    public static function activate(): void
    {
        self::create_roles();
        self::create_tables();
        update_option('aipex_client_os_version', AIPEX_CLIENT_OS_VERSION);
    }

    private static function create_roles(): void
    {
        add_role('aipex_client', 'Aipex Client', ['read' => true]);
        add_role('aipex_agent', 'Aipex Agent', ['read' => true, 'aipex_manage_assigned_clients' => true]);
        add_role('aipex_manager', 'Aipex Manager', ['read' => true, 'aipex_manage_all_clients' => true]);
    }

    private static function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'aipex_';

        dbDelta("CREATE TABLE {$prefix}companies (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            type VARCHAR(80) DEFAULT 'client',
            status VARCHAR(40) DEFAULT 'active',
            owner_user_id BIGINT UNSIGNED NULL,
            meta LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY owner_user_id (owner_user_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$prefix}contacts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            first_name VARCHAR(120) NULL,
            last_name VARCHAR(120) NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(80) NULL,
            role_label VARCHAR(120) NULL,
            meta LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY company_id (company_id),
            KEY user_id (user_id),
            KEY email (email)
        ) {$charset};");

        dbDelta("CREATE TABLE {$prefix}workflows (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id BIGINT UNSIGNED NULL,
            title VARCHAR(190) NOT NULL,
            workflow_key VARCHAR(120) NOT NULL,
            status VARCHAR(40) DEFAULT 'active',
            current_stage VARCHAR(120) NULL,
            config LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY company_id (company_id),
            KEY workflow_key (workflow_key),
            KEY status (status)
        ) {$charset};");

        dbDelta("CREATE TABLE {$prefix}audit_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            object_type VARCHAR(80) NOT NULL,
            object_id BIGINT UNSIGNED NULL,
            action VARCHAR(120) NOT NULL,
            actor_user_id BIGINT UNSIGNED NULL,
            details LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY object_lookup (object_type, object_id),
            KEY action (action),
            KEY actor_user_id (actor_user_id)
        ) {$charset};");
    }
}
