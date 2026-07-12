<?php

namespace Aipex_DGP;

defined( 'ABSPATH' ) || exit;

final class Admin {
    private Dropbox_Client $client;

    public function __construct() {
        $this->client = new Dropbox_Client();
    }

    public function boot(): void {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );
        add_action( 'admin_post_aipex_dgp_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_aipex_dgp_disconnect', array( $this, 'disconnect' ) );
        add_action( 'admin_post_aipex_dgp_sync_gallery', array( $this, 'sync_gallery' ) );
        add_action( 'wp_ajax_aipex_dgp_list_folders', array( $this, 'list_folders' ) );
    }

    public function menu(): void {
        add_submenu_page(
            'edit.php?post_type=aipex_gallery',
            __( 'Dropbox Settings', 'aipex-dropbox-gallery-print' ),
            __( 'Dropbox Settings', 'aipex-dropbox-gallery-print' ),
            'manage_options',
            'aipex-dgp-settings',
            array( $this, 'render_settings' )
        );
    }

    public function render_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $connected = $this->client->is_connected();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Aipex Dropbox Gallery & Print', 'aipex-dropbox-gallery-print' ); ?></h1>
            <?php if ( isset( $_GET['aipex_dgp_notice'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['aipex_dgp_notice'] ) ) ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['aipex_dgp_error'] ) ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['aipex_dgp_error'] ) ) ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'aipex_dgp_save_settings' ); ?>
                <input type="hidden" name="action" value="aipex_dgp_save_settings">
                <table class="form-table">
                    <tr><th><label for="aipex_dgp_app_key">Dropbox App key</label></th><td><input class="regular-text" id="aipex_dgp_app_key" name="app_key" value="<?php echo esc_attr( (string) get_option( 'aipex_dgp_app_key', '' ) ); ?>"></td></tr>
                    <tr><th><label for="aipex_dgp_app_secret">Dropbox App secret</label></th><td><input class="regular-text" type="password" id="aipex_dgp_app_secret" name="app_secret" value="" placeholder="<?php echo esc_attr( get_option( 'aipex_dgp_app_secret' ) ? 'Saved — leave blank to keep' : '' ); ?>"></td></tr>
                    <tr><th>OAuth redirect URI</th><td><code><?php echo esc_html( $this->client->redirect_uri() ); ?></code><p class="description">Add this exact URI to the Dropbox app console.</p></td></tr>
                </table>
                <?php submit_button( __( 'Save Dropbox app settings', 'aipex-dropbox-gallery-print' ) ); ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Connection', 'aipex-dropbox-gallery-print' ); ?></h2>
            <?php if ( $connected ) : ?>
                <p><strong><?php esc_html_e( 'Connected', 'aipex-dropbox-gallery-print' ); ?></strong></p>
                <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aipex_dgp_disconnect' ), 'aipex_dgp_disconnect' ) ); ?>"><?php esc_html_e( 'Disconnect Dropbox', 'aipex-dropbox-gallery-print' ); ?></a>
            <?php elseif ( $this->client->is_configured() ) : ?>
                <a class="button button-primary" href="<?php echo esc_url( $this->client->authorization_url() ); ?>"><?php esc_html_e( 'Connect Dropbox', 'aipex-dropbox-gallery-print' ); ?></a>
            <?php else : ?>
                <p><?php esc_html_e( 'Save the app key and secret before connecting.', 'aipex-dropbox-gallery-print' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function save_settings(): void {
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aipex_dgp_save_settings' ) ) wp_die( 'Forbidden' );
        update_option( 'aipex_dgp_app_key', sanitize_text_field( wp_unslash( $_POST['app_key'] ?? '' ) ), false );
        $secret = sanitize_text_field( wp_unslash( $_POST['app_secret'] ?? '' ) );
        if ( '' !== $secret ) update_option( 'aipex_dgp_app_secret', Crypto::encrypt( $secret ), false );
        $this->redirect_notice( 'Dropbox app settings saved.' );
    }

    public function handle_oauth_callback(): void {
        if ( ! is_admin() || 'callback' !== ( $_GET['aipex_dgp_oauth'] ?? '' ) || ! current_user_can( 'manage_options' ) ) return;
        try {
            $state = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );
            $expected = (string) get_transient( 'aipex_dgp_oauth_state_' . get_current_user_id() );
            delete_transient( 'aipex_dgp_oauth_state_' . get_current_user_id() );
            if ( '' === $state || ! hash_equals( $expected, $state ) ) throw new \RuntimeException( 'Invalid OAuth state.' );
            $this->client->exchange_code( sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) ) );
            $this->redirect_notice( 'Dropbox connected successfully.' );
        } catch ( \Throwable $e ) {
            $this->redirect_error( $e->getMessage() );
        }
    }

    public function disconnect(): void {
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aipex_dgp_disconnect' ) ) wp_die( 'Forbidden' );
        $this->client->disconnect();
        $this->redirect_notice( 'Dropbox disconnected.' );
    }

    public function sync_gallery(): void {
        $gallery_id = absint( $_GET['gallery_id'] ?? 0 );
        if ( ! current_user_can( 'edit_post', $gallery_id ) || ! check_admin_referer( 'aipex_dgp_sync_gallery_' . $gallery_id ) ) wp_die( 'Forbidden' );
        try {
            $stats = ( new Sync() )->sync_gallery( $gallery_id, ! empty( $_GET['force'] ) );
            wp_safe_redirect( add_query_arg( 'aipex_dgp_notice', rawurlencode( sprintf( 'Sync complete: %d added, %d updated, %d removed.', $stats['added'], $stats['updated'], $stats['removed'] ) ), get_edit_post_link( $gallery_id, 'url' ) ) );
        } catch ( \Throwable $e ) {
            wp_safe_redirect( add_query_arg( 'aipex_dgp_error', rawurlencode( $e->getMessage() ), get_edit_post_link( $gallery_id, 'url' ) ) );
        }
        exit;
    }

    public function list_folders(): void {
        check_ajax_referer( 'aipex_dgp_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
        try {
            $result = $this->client->list_folder( sanitize_text_field( wp_unslash( $_POST['path'] ?? '' ) ) );
            $folders = array_values( array_filter( $result['entries'] ?? array(), static fn( $entry ) => 'folder' === ( $entry['.tag'] ?? '' ) ) );
            wp_send_json_success( $folders );
        } catch ( \Throwable $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
        }
    }

    private function redirect_notice( string $message ): void {
        wp_safe_redirect( add_query_arg( 'aipex_dgp_notice', rawurlencode( $message ), admin_url( 'edit.php?post_type=aipex_gallery&page=aipex-dgp-settings' ) ) ); exit;
    }
    private function redirect_error( string $message ): void {
        wp_safe_redirect( add_query_arg( 'aipex_dgp_error', rawurlencode( $message ), admin_url( 'edit.php?post_type=aipex_gallery&page=aipex-dgp-settings' ) ) ); exit;
    }
}
