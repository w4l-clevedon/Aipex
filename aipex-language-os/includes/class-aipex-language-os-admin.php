<?php

defined('ABSPATH') || exit;

class Aipex_Language_OS_Admin {
    public static function init(): void {
        add_action('admin_menu', array(__CLASS__, 'register_menu'));
        add_action('admin_post_aipex_language_os_create_language', array(__CLASS__, 'handle_create_language'));
        add_action('admin_post_aipex_language_os_create_course', array(__CLASS__, 'handle_create_course'));
        add_action('admin_post_aipex_language_os_import_zips', array(__CLASS__, 'handle_zip_import'));
    }

    public static function register_menu(): void {
        add_menu_page(
            'Aipex Language OS',
            'Language OS',
            'manage_options',
            'aipex-language-os',
            array(__CLASS__, 'render_dashboard'),
            'dashicons-translation',
            58
        );
    }

    public static function render_dashboard(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        $languages = self::languages();
        $courses = self::courses();
        $selected_language_id = isset($_GET['language_id']) ? absint($_GET['language_id']) : (int) ($languages[0]->id ?? 0);
        $selected_course_id = isset($_GET['course_id']) ? absint($_GET['course_id']) : (int) ($courses[0]->id ?? 0);
        $packs = self::course_packs($selected_course_id);
        $assets = self::assets($selected_course_id);
        $lessons = self::lessons($selected_course_id);

        echo '<div class="wrap aipex-language-os-admin">';
        echo '<h1>Aipex Language OS</h1>';
        echo '<p>Import user-supplied language course packs and turn MP3 files into lesson candidates.</p>';

        self::render_notices();
        self::render_language_form();
        self::render_course_form($languages);
        self::render_import_form($languages, $courses, $selected_language_id, $selected_course_id);
        self::render_import_summary($packs);
        self::render_assets_table($assets);
        self::render_lessons_table($lessons);

        echo '</div>';
    }

    private static function render_language_form(): void {
        echo '<hr><h2>Add pilot language</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('aipex_language_os_create_language');
        echo '<input type="hidden" name="action" value="aipex_language_os_create_language">';
        echo '<p><input required name="name" placeholder="Language name"> <input required name="code" placeholder="Code"> <input name="native_name" placeholder="Native name"> <input name="script" placeholder="Script"></p>';
        submit_button('Add language', 'secondary', 'submit', false);
        echo '</form>';
    }

    private static function render_course_form(array $languages): void {
        echo '<hr><h2>Add course</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('aipex_language_os_create_course');
        echo '<input type="hidden" name="action" value="aipex_language_os_create_course">';
        echo '<p><select required name="language_id">';
        foreach ($languages as $language) {
            echo '<option value="' . esc_attr($language->id) . '">' . esc_html($language->name) . '</option>';
        }
        echo '</select> <input required name="title" placeholder="Course title"> <input name="source_location" placeholder="Optional source location"></p>';
        submit_button('Add course', 'secondary', 'submit', false);
        echo '</form>';
    }

    private static function render_import_form(array $languages, array $courses, int $selected_language_id, int $selected_course_id): void {
        echo '<hr><h2>Import ZIP course packs</h2>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('aipex_language_os_import_zips');
        echo '<input type="hidden" name="action" value="aipex_language_os_import_zips">';

        echo '<p><label>Language<br><select required name="language_id">';
        foreach ($languages as $language) {
            echo '<option value="' . esc_attr($language->id) . '" ' . selected($selected_language_id, (int) $language->id, false) . '>' . esc_html($language->name) . '</option>';
        }
        echo '</select></label></p>';

        echo '<p><label>Course<br><select required name="course_id">';
        foreach ($courses as $course) {
            echo '<option value="' . esc_attr($course->id) . '" ' . selected($selected_course_id, (int) $course->id, false) . '>' . esc_html($course->title) . '</option>';
        }
        echo '</select></label></p>';

        echo '<p><label>ZIP files<br><input required type="file" name="course_packs[]" accept=".zip,application/zip" multiple></label></p>';
        submit_button('Import ZIP packs');
        echo '</form>';
    }

    private static function render_import_summary(array $packs): void {
        echo '<hr><h2>Import log</h2>';

        if (empty($packs)) {
            echo '<p>No course packs imported yet.</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>ZIP file</th><th>Status</th><th>Imported</th><th>Summary</th></tr></thead><tbody>';
        foreach ($packs as $pack) {
            $log = json_decode($pack->import_log ?: '{}', true);
            $summary = sprintf(
                'Imported: %d | Ignored: %d | Duplicates: %d | Failed: %d | Lessons: %d | Materials: %d',
                count($log['imported'] ?? array()),
                count($log['ignored'] ?? array()),
                count($log['duplicates'] ?? array()),
                count($log['failed'] ?? array()),
                count($log['lessons_created'] ?? array()),
                count($log['materials_created'] ?? array())
            );
            echo '<tr><td>' . esc_html($pack->zip_filename) . '</td><td>' . esc_html($pack->import_status) . '</td><td>' . esc_html($pack->imported_at) . '</td><td>' . esc_html($summary) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_assets_table(array $assets): void {
        echo '<hr><h2>Detected assets</h2>';

        if (empty($assets)) {
            echo '<p>No assets detected yet.</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>Original filename</th><th>Type</th><th>MIME</th><th>Size</th><th>Status</th></tr></thead><tbody>';
        foreach ($assets as $asset) {
            echo '<tr><td>' . esc_html($asset->original_filename) . '</td><td>' . esc_html($asset->detected_type) . '</td><td>' . esc_html($asset->mime_type) . '</td><td>' . esc_html(size_format((int) $asset->file_size)) . '</td><td>' . esc_html($asset->import_status) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_lessons_table(array $lessons): void {
        echo '<hr><h2>MP3 lesson candidates</h2>';

        if (empty($lessons)) {
            echo '<p>No lesson candidates yet.</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>Lesson</th><th>Title</th><th>Transcript</th><th>Processing</th></tr></thead><tbody>';
        foreach ($lessons as $lesson) {
            echo '<tr><td>' . esc_html($lesson->lesson_number) . '</td><td>' . esc_html($lesson->title) . '</td><td>' . esc_html($lesson->transcript_status) . '</td><td>' . esc_html($lesson->processed_status) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    public static function handle_create_language(): void {
        self::require_admin_and_nonce('aipex_language_os_create_language');

        global $wpdb;
        $table = $wpdb->prefix . 'aipex_language_os_languages';

        $wpdb->insert($table, array(
            'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            'code' => sanitize_key(wp_unslash($_POST['code'] ?? '')),
            'native_name' => sanitize_text_field(wp_unslash($_POST['native_name'] ?? '')),
            'script' => sanitize_text_field(wp_unslash($_POST['script'] ?? '')),
            'active' => 1,
        ), array('%s', '%s', '%s', '%s', '%d'));

        self::redirect('language_created');
    }

    public static function handle_create_course(): void {
        self::require_admin_and_nonce('aipex_language_os_create_course');

        global $wpdb;
        $table = $wpdb->prefix . 'aipex_language_os_courses';

        $wpdb->insert($table, array(
            'language_id' => absint($_POST['language_id'] ?? 0),
            'title' => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
            'source_type' => 'course_pack',
            'source_location' => sanitize_text_field(wp_unslash($_POST['source_location'] ?? '')),
            'active' => 1,
        ), array('%d', '%s', '%s', '%s', '%d'));

        self::redirect('course_created');
    }

    public static function handle_zip_import(): void {
        self::require_admin_and_nonce('aipex_language_os_import_zips');

        $language_id = absint($_POST['language_id'] ?? 0);
        $course_id = absint($_POST['course_id'] ?? 0);

        if (! $language_id || ! $course_id || empty($_FILES['course_packs'])) {
            self::redirect('import_missing');
        }

        $importer = new Aipex_Language_OS_Importer();
        $importer->import_uploaded_zips($language_id, $course_id, $_FILES['course_packs']);

        self::redirect('import_complete', $language_id, $course_id);
    }

    private static function languages(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aipex_language_os_languages ORDER BY active DESC, name ASC") ?: array();
    }

    private static function courses(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aipex_language_os_courses ORDER BY active DESC, title ASC") ?: array();
    }

    private static function course_packs(int $course_id): array {
        global $wpdb;
        if (! $course_id) {
            return array();
        }
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aipex_language_os_course_packs WHERE course_id = %d ORDER BY id DESC", $course_id)) ?: array();
    }

    private static function assets(int $course_id): array {
        global $wpdb;
        if (! $course_id) {
            return array();
        }
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aipex_language_os_course_assets WHERE course_id = %d ORDER BY id DESC", $course_id)) ?: array();
    }

    private static function lessons(int $course_id): array {
        global $wpdb;
        if (! $course_id) {
            return array();
        }
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aipex_language_os_lessons WHERE course_id = %d ORDER BY lesson_number ASC, id ASC", $course_id)) ?: array();
    }

    private static function render_notices(): void {
        $message = sanitize_key($_GET['message'] ?? '');
        $messages = array(
            'language_created' => 'Language created.',
            'course_created' => 'Course created.',
            'import_complete' => 'ZIP import complete. Review the import log and lesson candidates below.',
            'import_missing' => 'Import could not start. Check language, course and ZIP file selection.',
        );

        if (isset($messages[$message])) {
            echo '<div class="notice notice-success"><p>' . esc_html($messages[$message]) . '</p></div>';
        }
    }

    private static function require_admin_and_nonce(string $action): void {
        if (! current_user_can('manage_options')) {
            wp_die('You do not have permission to manage Language OS.');
        }
        check_admin_referer($action);
    }

    private static function redirect(string $message, int $language_id = 0, int $course_id = 0): void {
        $url = add_query_arg(array('page' => 'aipex-language-os', 'message' => $message), admin_url('admin.php'));
        if ($language_id) {
            $url = add_query_arg('language_id', $language_id, $url);
        }
        if ($course_id) {
            $url = add_query_arg('course_id', $course_id, $url);
        }
        wp_safe_redirect($url);
        exit;
    }
}
