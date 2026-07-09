<?php
/**
 * Plugin Name: Aipex Language OS
 * Description: Personalised language recall system for user-supplied course packs.
 * Version: 0.1.0
 * Author: Aipex
 * Text Domain: aipex-language-os
 */

defined('ABSPATH') || exit;

define('AIPEX_LANGUAGE_OS_VERSION', '0.1.0');
define('AIPEX_LANGUAGE_OS_FILE', __FILE__);
define('AIPEX_LANGUAGE_OS_PATH', plugin_dir_path(__FILE__));
define('AIPEX_LANGUAGE_OS_URL', plugin_dir_url(__FILE__));

require_once AIPEX_LANGUAGE_OS_PATH . 'includes/class-aipex-language-os-activator.php';
require_once AIPEX_LANGUAGE_OS_PATH . 'includes/class-aipex-language-os-importer.php';
require_once AIPEX_LANGUAGE_OS_PATH . 'includes/class-aipex-language-os-admin.php';

register_activation_hook(__FILE__, array('Aipex_Language_OS_Activator', 'activate'));

add_action('plugins_loaded', static function () {
    Aipex_Language_OS_Admin::init();
});
