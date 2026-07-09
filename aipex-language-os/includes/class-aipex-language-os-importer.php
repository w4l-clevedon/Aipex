<?php

defined('ABSPATH') || exit;

class Aipex_Language_OS_Importer {
    private $storage_root;
    private $log = array();

    public function __construct() {
        Aipex_Language_OS_Activator::ensure_storage_protection();
        $upload_dir = wp_upload_dir();
        $this->storage_root = trailingslashit($upload_dir['basedir']) . 'aipex-language-os/protected';
        $this->reset_log();
    }

    public function import_uploaded_zips($language_id, $course_id, $files) {
        $results = array();
        foreach ($this->normalise_uploads($files) as $file) {
            $results[] = $this->import_zip((int) $language_id, (int) $course_id, $file);
        }
        return $results;
    }

    private function import_zip($language_id, $course_id, $file) {
        $this->reset_log();

        if (! class_exists('ZipArchive')) {
            return $this->error_result('ZIP support is not available on this server.');
        }

        if (! isset($file['tmp_name'], $file['name']) || ! is_uploaded_file($file['tmp_name'])) {
            return $this->error_result('Upload could not be read.');
        }

        $zip_filename = sanitize_file_name($file['name']);
        $zip_check = wp_check_filetype_and_ext($file['tmp_name'], $zip_filename);
        if (! isset($zip_check['ext']) || $zip_check['ext'] !== 'zip') {
            return $this->error_result('Only ZIP files are supported.');
        }

        $pack_id = $this->create_course_pack($language_id, $course_id, $zip_filename, 'pending');
        $pack_dir = $this->pack_dir($language_id, $course_id, $pack_id);
        wp_mkdir_p($pack_dir);

        $stored_zip_path = trailingslashit($pack_dir) . $zip_filename;
        if (! move_uploaded_file($file['tmp_name'], $stored_zip_path)) {
            $this->update_course_pack($pack_id, 'failed', $stored_zip_path);
            return $this->error_result('ZIP upload could not be moved into protected storage.');
        }

        $this->update_course_pack($pack_id, 'extracting', $stored_zip_path);

        $zip = new ZipArchive();
        if ($zip->open($stored_zip_path) !== true) {
            $this->update_course_pack($pack_id, 'failed', $stored_zip_path);
            return $this->error_result('ZIP file could not be opened.');
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $entry_name = isset($stat['name']) ? $stat['name'] : '';
            if ($entry_name === '' || substr($entry_name, -1) === '/') {
                continue;
            }
            $this->process_zip_entry($zip, $entry_name, $language_id, $course_id, $pack_id, $pack_dir);
        }

        $zip->close();

        $status = empty($this->log['failed']) ? 'imported' : 'imported_with_errors';
        $this->update_course_pack($pack_id, $status, $stored_zip_path);

        return array(
            'course_pack_id' => $pack_id,
            'zip_filename' => $zip_filename,
            'status' => $status,
            'log' => $this->log,
        );
    }

    private function process_zip_entry($zip, $entry_name, $language_id, $course_id, $pack_id, $pack_dir) {
        $safe_relative = $this->safe_relative_path($entry_name);
        if ($safe_relative === null) {
            $this->log['failed'][] = array('file' => $entry_name, 'reason' => 'Unsafe path skipped.');
            return;
        }

        $detected_type = $this->detect_type($entry_name);
        if ($detected_type === 'ignored') {
            $this->log['ignored'][] = $entry_name;
            return;
        }

        $target_path = trailingslashit($pack_dir) . 'extracted/' . $safe_relative;
        wp_mkdir_p(dirname($target_path));

        $stream = $zip->getStream($entry_name);
        if (! $stream) {
            $this->log['failed'][] = array('file' => $entry_name, 'reason' => 'Could not read file from ZIP.');
            return;
        }

        $contents = stream_get_contents($stream);
        fclose($stream);
        if ($contents === false) {
            $this->log['failed'][] = array('file' => $entry_name, 'reason' => 'Could not extract file contents.');
            return;
        }

        $checksum = hash('sha256', $contents);
        if ($this->checksum_exists($checksum)) {
            $this->log['duplicates'][] = $entry_name;
            return;
        }

        if (file_put_contents($target_path, $contents) === false) {
            $this->log['failed'][] = array('file' => $entry_name, 'reason' => 'Could not write extracted file.');
            return;
        }

        $asset_id = $this->create_asset($language_id, $course_id, $pack_id, $entry_name, $target_path, $detected_type, $checksum);

        if ($detected_type === 'audio') {
            $lesson_id = $this->create_lesson_candidate($course_id, $asset_id, $entry_name, $target_path);
            $this->log['lessons_created'][] = array('lesson_id' => $lesson_id, 'file' => $entry_name);
        }

        if ($detected_type === 'document' || $detected_type === 'image') {
            $material_id = $this->create_supporting_material($course_id, $asset_id, $detected_type, $entry_name);
            $this->log['materials_created'][] = array('material_id' => $material_id, 'file' => $entry_name);
        }

        $this->log['imported'][] = array('asset_id' => $asset_id, 'file' => $entry_name, 'type' => $detected_type);
    }

    private function create_course_pack($language_id, $course_id, $zip_filename, $status) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'aipex_language_os_course_packs', array(
            'language_id' => $language_id,
            'course_id' => $course_id,
            'zip_filename' => $zip_filename,
            'stored_zip_path' => '',
            'import_status' => $status,
            'imported_at' => current_time('mysql'),
            'import_log' => wp_json_encode($this->log),
        ), array('%d', '%d', '%s', '%s', '%s', '%s', '%s'));
        return (int) $wpdb->insert_id;
    }

    private function update_course_pack($pack_id, $status, $stored_zip_path) {
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'aipex_language_os_course_packs', array(
            'stored_zip_path' => $stored_zip_path,
            'import_status' => $status,
            'imported_at' => current_time('mysql'),
            'import_log' => wp_json_encode($this->log),
        ), array('id' => $pack_id), array('%s', '%s', '%s', '%s'), array('%d'));
    }

    private function create_asset($language_id, $course_id, $pack_id, $original_filename, $path, $detected_type, $checksum) {
        global $wpdb;
        $filetype = wp_check_filetype($path);
        $wpdb->insert($wpdb->prefix . 'aipex_language_os_course_assets', array(
            'language_id' => $language_id,
            'course_id' => $course_id,
            'course_pack_id' => $pack_id,
            'original_filename' => $original_filename,
            'stored_file_path' => $path,
            'detected_type' => $detected_type,
            'mime_type' => isset($filetype['type']) ? $filetype['type'] : '',
            'file_size' => filesize($path) ? filesize($path) : 0,
            'checksum' => $checksum,
            'import_status' => 'imported',
        ), array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s'));
        return (int) $wpdb->insert_id;
    }

    private function create_lesson_candidate($course_id, $asset_id, $entry_name, $path) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'aipex_language_os_lessons', array(
            'course_id' => $course_id,
            'primary_asset_id' => $asset_id,
            'lesson_number' => $this->next_lesson_number($course_id),
            'title' => $this->title_from_filename($entry_name),
            'audio_source' => $path,
            'duration' => 0,
            'transcript_status' => 'not_started',
            'processed_status' => 'pending',
        ), array('%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s'));
        return (int) $wpdb->insert_id;
    }

    private function create_supporting_material($course_id, $asset_id, $material_type, $entry_name) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'aipex_language_os_supporting_materials', array(
            'course_id' => $course_id,
            'asset_id' => $asset_id,
            'material_type' => $material_type,
            'title' => $this->title_from_filename($entry_name),
            'processed_status' => 'pending',
        ), array('%d', '%d', '%s', '%s', '%s'));
        return (int) $wpdb->insert_id;
    }

    private function checksum_exists($checksum) {
        global $wpdb;
        $table = $wpdb->prefix . 'aipex_language_os_course_assets';
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE checksum = %s LIMIT 1", $checksum));
    }

    private function next_lesson_number($course_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aipex_language_os_lessons';
        return 1 + (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(lesson_number), 0) FROM {$table} WHERE course_id = %d", $course_id));
    }

    private function detect_type($filename) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === 'mp3') {
            return 'audio';
        }
        if ($ext === 'pdf') {
            return 'document';
        }
        if ($ext === 'jpg' || $ext === 'jpeg') {
            return 'image';
        }
        return 'ignored';
    }

    private function safe_relative_path($path) {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        $parts = array_filter(explode('/', $path));
        $safe = array();

        foreach ($parts as $part) {
            if ($part === '..' || $part === '.') {
                return null;
            }
            $safe[] = sanitize_file_name($part);
        }

        return empty($safe) ? null : implode('/', $safe);
    }

    private function pack_dir($language_id, $course_id, $pack_id) {
        return trailingslashit($this->storage_root) . 'language-' . (int) $language_id . '/course-' . (int) $course_id . '/pack-' . (int) $pack_id;
    }

    private function title_from_filename($filename) {
        $name = pathinfo(basename($filename), PATHINFO_FILENAME);
        return trim(ucwords(str_replace(array('-', '_'), ' ', $name)));
    }

    private function normalise_uploads($files) {
        $normalised = array();
        if (! isset($files['name'])) {
            return $normalised;
        }

        if (is_array($files['name'])) {
            foreach ($files['name'] as $index => $name) {
                if (isset($files['error'][$index]) && $files['error'][$index] === UPLOAD_ERR_OK) {
                    $normalised[] = array(
                        'name' => $name,
                        'type' => isset($files['type'][$index]) ? $files['type'][$index] : '',
                        'tmp_name' => isset($files['tmp_name'][$index]) ? $files['tmp_name'][$index] : '',
                        'error' => $files['error'][$index],
                        'size' => isset($files['size'][$index]) ? $files['size'][$index] : 0,
                    );
                }
            }
            return $normalised;
        }

        if (isset($files['error']) && $files['error'] === UPLOAD_ERR_OK) {
            $normalised[] = $files;
        }

        return $normalised;
    }

    private function reset_log() {
        $this->log = array(
            'imported' => array(),
            'ignored' => array(),
            'duplicates' => array(),
            'failed' => array(),
            'lessons_created' => array(),
            'materials_created' => array(),
        );
    }

    private function error_result($message) {
        return array(
            'status' => 'failed',
            'message' => $message,
            'log' => $this->log,
        );
    }
}
