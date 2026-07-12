<?php

namespace Aipex_DGP;

defined( 'ABSPATH' ) || exit;

final class Plugin {
    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        return self::$instance ??= new self();
    }

    public static function activate(): void {
        self::register_post_types();
        if ( ! wp_next_scheduled( 'aipex_dgp_scheduled_sync' ) ) {
            wp_schedule_event( time() + 300, 'hourly', 'aipex_dgp_scheduled_sync' );
        }
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook( 'aipex_dgp_scheduled_sync' );
        flush_rewrite_rules();
    }

    public function boot(): void {
        add_action( 'init', array( self::class, 'register_post_types' ) );
        add_action( 'add_meta_boxes', array( $this, 'register_gallery_meta_box' ) );
        add_action( 'save_post_aipex_gallery', array( $this, 'save_gallery_settings' ) );
        add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widgets' ) );
        add_action( 'aipex_dgp_scheduled_sync', array( $this, 'scheduled_sync' ) );
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'capture_photo_cart_data' ), 10, 3 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_photo_cart_data' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'persist_photo_order_data' ), 10, 4 );
        add_action( 'template_redirect', array( $this, 'enforce_purchase_access' ) );
    }

    public static function register_post_types(): void {
        register_post_type( 'aipex_gallery', array(
            'labels' => array( 'name' => __( 'Dropbox Galleries', 'aipex-dropbox-gallery-print' ), 'singular_name' => __( 'Dropbox Gallery', 'aipex-dropbox-gallery-print' ) ),
            'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_icon' => 'dashicons-format-gallery',
            'supports' => array( 'title' ), 'show_in_rest' => true,
        ) );
        register_post_type( 'aipex_photo', array(
            'labels' => array( 'name' => __( 'Gallery Photos', 'aipex-dropbox-gallery-print' ), 'singular_name' => __( 'Gallery Photo', 'aipex-dropbox-gallery-print' ) ),
            'public' => false, 'show_ui' => true, 'show_in_menu' => 'edit.php?post_type=aipex_gallery',
            'supports' => array( 'title', 'thumbnail', 'excerpt' ), 'show_in_rest' => true,
        ) );
    }

    public function register_gallery_meta_box(): void {
        add_meta_box( 'aipex-dgp-gallery-settings', __( 'Dropbox and purchase settings', 'aipex-dropbox-gallery-print' ), array( $this, 'render_gallery_meta_box' ), 'aipex_gallery', 'normal', 'high' );
    }

    public function render_gallery_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'aipex_dgp_save_gallery', 'aipex_dgp_nonce' );
        $folder = (string) get_post_meta( $post->ID, '_aipex_dropbox_folder', true );
        $sort = (string) get_post_meta( $post->ID, '_aipex_sort', true ) ?: 'name_asc';
        $product_id = (int) get_post_meta( $post->ID, '_aipex_product_id', true );
        $access = (string) get_post_meta( $post->ID, '_aipex_purchase_access', true ) ?: 'logged_in';
        $last_sync = (string) get_post_meta( $post->ID, '_aipex_last_sync', true );
        ?>
        <p><label><strong><?php esc_html_e( 'Dropbox folder path', 'aipex-dropbox-gallery-print' ); ?></strong></label><br><input class="widefat" name="aipex_dropbox_folder" value="<?php echo esc_attr( $folder ); ?>" placeholder="/Galleries/Event Name"></p>
        <p><label><strong><?php esc_html_e( 'Default sorting', 'aipex-dropbox-gallery-print' ); ?></strong></label><br><select name="aipex_sort">
        <?php foreach ( array( 'name_asc' => 'Name A–Z', 'name_desc' => 'Name Z–A', 'date_desc' => 'Newest first', 'date_asc' => 'Oldest first' ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $sort, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
        </select></p>
        <p><label><strong><?php esc_html_e( 'WooCommerce print product ID', 'aipex-dropbox-gallery-print' ); ?></strong></label><br><input type="number" min="0" name="aipex_product_id" value="<?php echo esc_attr( (string) $product_id ); ?>"></p>
        <p><label><strong><?php esc_html_e( 'Purchase access', 'aipex-dropbox-gallery-print' ); ?></strong></label><br><select name="aipex_purchase_access"><option value="anyone" <?php selected( $access, 'anyone' ); ?>><?php esc_html_e( 'Anyone', 'aipex-dropbox-gallery-print' ); ?></option><option value="logged_in" <?php selected( $access, 'logged_in' ); ?>><?php esc_html_e( 'Logged-in users only', 'aipex-dropbox-gallery-print' ); ?></option></select></p>
        <?php if ( $post->ID && 'auto-draft' !== $post->post_status ) : ?>
            <p><strong><?php esc_html_e( 'Last sync:', 'aipex-dropbox-gallery-print' ); ?></strong> <?php echo esc_html( $last_sync ?: 'Never' ); ?></p>
            <p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aipex_dgp_sync_gallery&gallery_id=' . $post->ID ), 'aipex_dgp_sync_gallery_' . $post->ID ) ); ?>"><?php esc_html_e( 'Sync now', 'aipex-dropbox-gallery-print' ); ?></a> <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aipex_dgp_sync_gallery&force=1&gallery_id=' . $post->ID ), 'aipex_dgp_sync_gallery_' . $post->ID ) ); ?>"><?php esc_html_e( 'Full resync', 'aipex-dropbox-gallery-print' ); ?></a></p>
        <?php endif;
    }

    public function save_gallery_settings( int $post_id ): void {
        if ( ! isset( $_POST['aipex_dgp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aipex_dgp_nonce'] ) ), 'aipex_dgp_save_gallery' ) || ! current_user_can( 'edit_post', $post_id ) ) return;
        $new_folder = sanitize_text_field( wp_unslash( $_POST['aipex_dropbox_folder'] ?? '' ) );
        if ( $new_folder !== get_post_meta( $post_id, '_aipex_dropbox_folder', true ) ) delete_post_meta( $post_id, '_aipex_dropbox_cursor' );
        update_post_meta( $post_id, '_aipex_dropbox_folder', $new_folder );
        update_post_meta( $post_id, '_aipex_sort', sanitize_key( wp_unslash( $_POST['aipex_sort'] ?? 'name_asc' ) ) );
        update_post_meta( $post_id, '_aipex_product_id', absint( $_POST['aipex_product_id'] ?? 0 ) );
        update_post_meta( $post_id, '_aipex_purchase_access', sanitize_key( wp_unslash( $_POST['aipex_purchase_access'] ?? 'logged_in' ) ) );
    }

    public function scheduled_sync(): void {
        if ( ! ( new Dropbox_Client() )->is_connected() ) return;
        foreach ( get_posts( array( 'post_type' => 'aipex_gallery', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $gallery_id ) {
            try { ( new Sync() )->sync_gallery( (int) $gallery_id ); } catch ( \Throwable $e ) { update_post_meta( (int) $gallery_id, '_aipex_last_sync_error', $e->getMessage() ); }
        }
    }

    public function register_elementor_widgets( $widgets_manager ): void {
        if ( ! did_action( 'elementor/loaded' ) ) return;
        require_once AIPEX_DGP_PATH . 'includes/widgets/class-masonry-gallery-widget.php';
        $widgets_manager->register( new Widgets\Masonry_Gallery_Widget() );
    }

    public function capture_photo_cart_data( array $cart_item_data, int $product_id, int $variation_id ): array {
        $photo_id = isset( $_REQUEST['aipex_photo_id'] ) ? absint( $_REQUEST['aipex_photo_id'] ) : 0;
        if ( $photo_id && 'aipex_photo' === get_post_type( $photo_id ) ) {
            $cart_item_data['aipex_photo_id'] = $photo_id;
            $cart_item_data['aipex_photo_ref'] = (string) get_post_meta( $photo_id, '_aipex_dropbox_id', true );
            $cart_item_data['aipex_unique_key'] = wp_generate_uuid4();
        }
        return $cart_item_data;
    }

    public function display_photo_cart_data( array $item_data, array $cart_item ): array {
        if ( ! empty( $cart_item['aipex_photo_id'] ) ) $item_data[] = array( 'key' => __( 'Selected photograph', 'aipex-dropbox-gallery-print' ), 'value' => get_the_title( (int) $cart_item['aipex_photo_id'] ) );
        return $item_data;
    }

    public function persist_photo_order_data( $item, string $cart_item_key, array $values, $order ): void {
        if ( ! empty( $values['aipex_photo_id'] ) ) {
            $item->add_meta_data( '_aipex_photo_id', (int) $values['aipex_photo_id'], true );
            $item->add_meta_data( __( 'Selected photograph', 'aipex-dropbox-gallery-print' ), get_the_title( (int) $values['aipex_photo_id'] ), true );
            $item->add_meta_data( '_aipex_dropbox_id', (string) ( $values['aipex_photo_ref'] ?? '' ), true );
        }
    }

    public function enforce_purchase_access(): void {
        if ( ! function_exists( 'is_product' ) || ! is_product() || is_user_logged_in() ) return;
        $photo_id = isset( $_GET['aipex_photo_id'] ) ? absint( $_GET['aipex_photo_id'] ) : 0;
        if ( ! $photo_id ) return;
        $gallery_id = (int) get_post_meta( $photo_id, '_aipex_gallery_id', true );
        if ( 'logged_in' !== get_post_meta( $gallery_id, '_aipex_purchase_access', true ) ) return;
        $return_url = wp_validate_redirect( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ), home_url( '/' ) );
        wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( $return_url ), wc_get_page_permalink( 'myaccount' ) ) ); exit;
    }
}
