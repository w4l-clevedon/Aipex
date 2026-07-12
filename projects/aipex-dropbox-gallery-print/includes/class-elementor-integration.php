<?php

namespace Aipex_DGP;

defined( 'ABSPATH' ) || exit;

final class Elementor_Integration {
    public static function boot(): void {
        add_action( 'wp_enqueue_scripts', array( self::class, 'register_assets' ) );
        add_action( 'elementor/widgets/register', array( self::class, 'register_hotspot_widget' ) );
    }

    public static function register_assets(): void {
        wp_register_style(
            'aipex-dgp-gallery',
            AIPEX_DGP_URL . 'assets/css/gallery.css',
            array(),
            AIPEX_DGP_VERSION
        );
    }

    public static function register_hotspot_widget( $widgets_manager ): void {
        if ( ! did_action( 'elementor/loaded' ) ) {
            return;
        }

        require_once AIPEX_DGP_PATH . 'includes/widgets/class-hotspot-photo-widget.php';
        $widgets_manager->register( new Widgets\Hotspot_Photo_Widget() );
    }
}
