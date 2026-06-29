<?php
if (!defined('ABSPATH')) exit;
class Aipex_Podcast_Core {
    public static function init(){
        add_action('init', ['Aipex_Podcast_Post_Types','register'], 5);
        add_action('init', ['Aipex_Podcast_Shortcodes','register'], 20);
        add_action('acf/init', ['Aipex_Podcast_ACF','register_fields']);
        add_action('admin_menu', ['Aipex_Podcast_Admin','menus'], 30);
        add_action('admin_init', ['Aipex_Podcast_Admin','handle_actions']);
        add_action('admin_init', ['Aipex_Podcast_Dropbox','handle_actions']);
        add_action('rest_api_init', ['Aipex_Podcast_Dropbox','register_rest_routes']);
        add_action('admin_init', ['Aipex_Podcast_Migration','handle_actions']);
        add_action('wp_enqueue_scripts', ['Aipex_Podcast_Core','assets']);
        add_action('wp_ajax_aipex_grid_load_more', ['Aipex_Podcast_Shortcodes','ajax_grid_load_more']);
        add_action('wp_ajax_nopriv_aipex_grid_load_more', ['Aipex_Podcast_Shortcodes','ajax_grid_load_more']);
        add_action('wp_ajax_aipex_dropbox_start_scan', ['Aipex_Podcast_Dropbox','ajax_start_scan']);
        add_action('wp_ajax_aipex_dropbox_continue_scan', ['Aipex_Podcast_Dropbox','ajax_continue_scan']);
        add_action('admin_init', ['Aipex_Podcast_Core','maybe_flush_rewrites']);
        Aipex_Podcast_Elementor::init();
    }
    public static function maybe_flush_rewrites(){
        $key='aipex_podcast_rewrite_version';
        if (get_option($key) !== AIPEX_PODCAST_VERSION) {
            Aipex_Podcast_Post_Types::register();
            flush_rewrite_rules(false);
            update_option($key, AIPEX_PODCAST_VERSION, false);
        }
    }
    public static function assets(){
        wp_register_style('aipex-podcast', AIPEX_PODCAST_URL.'assets/podcast.css', [], AIPEX_PODCAST_VERSION);
        wp_register_script('aipex-podcast', AIPEX_PODCAST_URL.'assets/podcast.js', ['jquery'], AIPEX_PODCAST_VERSION, true);
        wp_localize_script('aipex-podcast','AipexPodcast',['ajaxurl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('aipex_podcast')]);
    }
}
