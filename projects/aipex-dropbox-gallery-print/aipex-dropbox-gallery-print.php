<?php
/**
 * Plugin Name: Aipex Dropbox Gallery & Print
 * Description: Dropbox-synchronised Elementor galleries with WooCommerce print personalisation.
 * Version: 0.2.0
 * Author: Aipex
 * Text Domain: aipex-dropbox-gallery-print
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'AIPEX_DGP_VERSION', '0.2.0' );
define( 'AIPEX_DGP_FILE', __FILE__ );
define( 'AIPEX_DGP_PATH', plugin_dir_path( __FILE__ ) );
define( 'AIPEX_DGP_URL', plugin_dir_url( __FILE__ ) );

require_once AIPEX_DGP_PATH . 'includes/class-crypto.php';
require_once AIPEX_DGP_PATH . 'includes/class-dropbox-client.php';
require_once AIPEX_DGP_PATH . 'includes/class-sync.php';
require_once AIPEX_DGP_PATH . 'includes/class-admin.php';
require_once AIPEX_DGP_PATH . 'includes/class-plugin.php';
require_once AIPEX_DGP_PATH . 'includes/class-elementor-integration.php';

register_activation_hook( __FILE__, array( 'Aipex_DGP\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Aipex_DGP\\Plugin', 'deactivate' ) );

add_action(
    'plugins_loaded',
    static function (): void {
        Aipex_DGP\Plugin::instance()->boot();
        Aipex_DGP\Elementor_Integration::boot();
        ( new Aipex_DGP\Admin() )->boot();
    }
);
