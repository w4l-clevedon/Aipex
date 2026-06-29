<?php
/**
 * Plugin Name: Aipex Podcast System v2
 * Description: Modular podcast CMS with ACF fields, shortcodes, Elementor widgets, scanners and Dropbox importer.
 * Version: 2.2.6
 * Author: Aipex
 */
if (!defined('ABSPATH')) exit;

define('AIPEX_PODCAST_VERSION','2.2.6');
define('AIPEX_PODCAST_FILE',__FILE__);
define('AIPEX_PODCAST_DIR',plugin_dir_path(__FILE__));
define('AIPEX_PODCAST_URL',plugin_dir_url(__FILE__));

require_once AIPEX_PODCAST_DIR.'includes/class-core.php';
require_once AIPEX_PODCAST_DIR.'includes/class-post-types.php';
require_once AIPEX_PODCAST_DIR.'includes/class-acf.php';
require_once AIPEX_PODCAST_DIR.'includes/class-utils.php';
require_once AIPEX_PODCAST_DIR.'includes/class-shortcodes.php';
require_once AIPEX_PODCAST_DIR.'includes/class-admin.php';
require_once AIPEX_PODCAST_DIR.'includes/class-dropbox.php';
require_once AIPEX_PODCAST_DIR.'includes/class-elementor.php';
require_once AIPEX_PODCAST_DIR.'includes/class-migration.php';

register_activation_hook(__FILE__, function(){
    Aipex_Podcast_Post_Types::register();
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function(){ flush_rewrite_rules(); });
add_action('plugins_loaded', ['Aipex_Podcast_Core','init']);
