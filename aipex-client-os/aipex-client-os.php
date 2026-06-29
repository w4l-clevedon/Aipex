<?php
/**
 * Plugin Name: Aipex Client OS
 * Description: CRM, client portal, workflow engine, secure vault, and industry-pack foundation for the Aipex ecosystem.
 * Version: 0.1.0
 * Author: Aipex
 * Text Domain: aipex-client-os
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AIPEX_CLIENT_OS_VERSION', '0.1.0');
define('AIPEX_CLIENT_OS_FILE', __FILE__);
define('AIPEX_CLIENT_OS_DIR', plugin_dir_path(__FILE__));
define('AIPEX_CLIENT_OS_URL', plugin_dir_url(__FILE__));

require_once AIPEX_CLIENT_OS_DIR . 'includes/class-aipex-client-os.php';
require_once AIPEX_CLIENT_OS_DIR . 'includes/class-activator.php';
require_once AIPEX_CLIENT_OS_DIR . 'includes/class-module-loader.php';
require_once AIPEX_CLIENT_OS_DIR . 'includes/class-admin.php';

register_activation_hook(__FILE__, ['Aipex_Client_OS_Activator', 'activate']);

add_action('plugins_loaded', static function (): void {
    Aipex_Client_OS::instance()->boot();
});
