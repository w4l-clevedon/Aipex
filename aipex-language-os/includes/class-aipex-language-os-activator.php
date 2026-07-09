<?php

defined('ABSPATH') || exit;

class Aipex_Language_OS_Activator {
    public static function activate(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'aipex_language_os_';

        $sql = array();

        $sql[] = "CREATE TABLE {$prefix}languages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            code VARCHAR(20) NOT NULL,
            native_name VARCHAR(190) DEFAULT '' NOT NULL,
            script VARCHAR(80) DEFAULT '' NOT NULL,
            active TINYINT(1) DEFAULT 1 NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY code (code),
            KEY active (active)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}courses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            language_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(190) NOT NULL,
            source_type VARCHAR(80) DEFAULT 'course_pack' NOT NULL,
            source_location TEXT NULL,
            active TINYINT(1) DEFAULT 1 NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY language_id (language_id),
            KEY active (active)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}course_packs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            language_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED NOT NULL,
            zip_filename VARCHAR(255) NOT NULL,
            stored_zip_path TEXT NOT NULL,
            import_status VARCHAR(40) DEFAULT 'pending' NOT NULL,
            imported_at DATETIME NULL,
            import_log LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY language_id (language_id),
            KEY course_id (course_id),
            KEY import_status (import_status)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}course_assets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            language_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED NOT NULL,
            course_pack_id BIGINT UNSIGNED NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            stored_file_path TEXT NOT NULL,
            detected_type VARCHAR(40) NOT NULL,
            mime_type VARCHAR(120) DEFAULT '' NOT NULL,
            file_size BIGINT UNSIGNED DEFAULT 0 NOT NULL,
            checksum CHAR(64) NOT NULL,
            import_status VARCHAR(40) DEFAULT 'imported' NOT NULL,
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY language_id (language_id),
            KEY course_id (course_id),
            KEY course_pack_id (course_pack_id),
            UNIQUE KEY checksum (checksum),
            KEY detected_type (detected_type)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}lessons (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id BIGINT UNSIGNED NOT NULL,
            primary_asset_id BIGINT UNSIGNED NOT NULL,
            lesson_number INT UNSIGNED DEFAULT 0 NOT NULL,
            title VARCHAR(255) NOT NULL,
            audio_source TEXT NOT NULL,
            duration INT UNSIGNED DEFAULT 0 NOT NULL,
            transcript_status VARCHAR(40) DEFAULT 'not_started' NOT NULL,
            transcript_text LONGTEXT NULL,
            processed_status VARCHAR(40) DEFAULT 'pending' NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY course_id (course_id),
            KEY primary_asset_id (primary_asset_id),
            KEY lesson_number (lesson_number)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}supporting_materials (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id BIGINT UNSIGNED NOT NULL,
            asset_id BIGINT UNSIGNED NOT NULL,
            material_type VARCHAR(40) NOT NULL,
            title VARCHAR(255) NOT NULL,
            extracted_text LONGTEXT NULL,
            processed_status VARCHAR(40) DEFAULT 'pending' NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY course_id (course_id),
            KEY asset_id (asset_id),
            KEY material_type (material_type)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}phrases (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lesson_id BIGINT UNSIGNED NOT NULL,
            language_id BIGINT UNSIGNED NOT NULL,
            source_text TEXT NOT NULL,
            english_text TEXT NULL,
            pronunciation_hint TEXT NULL,
            phrase_type VARCHAR(60) DEFAULT 'phrase' NOT NULL,
            difficulty TINYINT UNSIGNED DEFAULT 1 NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY lesson_id (lesson_id),
            KEY language_id (language_id),
            KEY difficulty (difficulty)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}recall_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            phrase_id BIGINT UNSIGNED NOT NULL,
            prompt_type VARCHAR(80) NOT NULL,
            prompt TEXT NOT NULL,
            answer TEXT NOT NULL,
            interval_days INT UNSIGNED DEFAULT 0 NOT NULL,
            ease_score DECIMAL(5,2) DEFAULT 2.50 NOT NULL,
            due_at DATETIME NOT NULL,
            last_reviewed_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY phrase_id (phrase_id),
            KEY due_at (due_at),
            KEY prompt_type (prompt_type)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}recall_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            recall_item_id BIGINT UNSIGNED NOT NULL,
            result VARCHAR(40) NOT NULL,
            response_text TEXT NULL,
            reviewed_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            notes TEXT NULL,
            PRIMARY KEY  (id),
            KEY recall_item_id (recall_item_id),
            KEY result (result),
            KEY reviewed_at (reviewed_at)
        ) {$charset_collate};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        self::ensure_storage_protection();
    }

    public static function ensure_storage_protection(): void {
        $upload_dir = wp_upload_dir();
        $base = trailingslashit($upload_dir['basedir']) . 'aipex-language-os';
        $protected = trailingslashit($base) . 'protected';

        wp_mkdir_p($protected);

        if (! file_exists($base . '/index.php')) {
            file_put_contents($base . '/index.php', "<?php\n// Silence is golden.\n");
        }

        if (! file_exists($protected . '/index.php')) {
            file_put_contents($protected . '/index.php', "<?php\n// Silence is golden.\n");
        }

        if (! file_exists($protected . '/.htaccess')) {
            file_put_contents($protected . '/.htaccess', "Deny from all\n");
        }
    }
}
