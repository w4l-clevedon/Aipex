<?php

namespace Aipex_DGP;

defined( 'ABSPATH' ) || exit;

final class Dropbox_Client {
    private const API = 'https://api.dropboxapi.com/2';
    private const CONTENT = 'https://content.dropboxapi.com/2';
    private const TOKEN_URL = 'https://api.dropboxapi.com/oauth2/token';

    public function is_configured(): bool {
        return '' !== (string) get_option( 'aipex_dgp_app_key', '' )
            && '' !== Crypto::decrypt( (string) get_option( 'aipex_dgp_app_secret', '' ) );
    }

    public function is_connected(): bool {
        return '' !== Crypto::decrypt( (string) get_option( 'aipex_dgp_refresh_token', '' ) );
    }

    public function authorization_url(): string {
        $state = wp_generate_password( 32, false, false );
        set_transient( 'aipex_dgp_oauth_state_' . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS );

        return add_query_arg(
            array(
                'client_id'         => (string) get_option( 'aipex_dgp_app_key', '' ),
                'response_type'     => 'code',
                'token_access_type' => 'offline',
                'redirect_uri'      => $this->redirect_uri(),
                'state'             => $state,
            ),
            'https://www.dropbox.com/oauth2/authorize'
        );
    }

    public function redirect_uri(): string {
        return admin_url( 'admin.php?page=aipex-dgp-settings&aipex_dgp_oauth=callback' );
    }

    public function exchange_code( string $code ): array {
        $response = wp_remote_post(
            self::TOKEN_URL,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode(
                        (string) get_option( 'aipex_dgp_app_key', '' ) . ':' . Crypto::decrypt( (string) get_option( 'aipex_dgp_app_secret', '' ) )
                    ),
                ),
                'body' => array(
                    'code'         => $code,
                    'grant_type'   => 'authorization_code',
                    'redirect_uri' => $this->redirect_uri(),
                ),
            )
        );

        $data = $this->decode_response( $response );
        if ( empty( $data['refresh_token'] ) ) {
            throw new \RuntimeException( 'Dropbox did not return a refresh token.' );
        }

        update_option( 'aipex_dgp_refresh_token', Crypto::encrypt( sanitize_text_field( $data['refresh_token'] ) ), false );
        update_option( 'aipex_dgp_access_token', Crypto::encrypt( sanitize_text_field( $data['access_token'] ?? '' ) ), false );
        update_option( 'aipex_dgp_access_expires', time() + max( 60, absint( $data['expires_in'] ?? 14400 ) - 120 ), false );
        update_option( 'aipex_dgp_account_id', sanitize_text_field( $data['account_id'] ?? '' ), false );
        return $data;
    }

    public function disconnect(): void {
        delete_option( 'aipex_dgp_refresh_token' );
        delete_option( 'aipex_dgp_access_token' );
        delete_option( 'aipex_dgp_access_expires' );
        delete_option( 'aipex_dgp_account_id' );
    }

    public function list_folder( string $path, bool $recursive = false ): array {
        return $this->rpc(
            '/files/list_folder',
            array(
                'path'      => $this->normalise_path( $path ),
                'recursive' => $recursive,
                'include_deleted' => true,
                'include_non_downloadable_files' => false,
                'limit' => 2000,
            )
        );
    }

    public function list_folder_continue( string $cursor ): array {
        return $this->rpc( '/files/list_folder/continue', array( 'cursor' => $cursor ) );
    }

    public function list_folder_changes( string $cursor ): array {
        return $this->list_folder_continue( $cursor );
    }

    public function get_metadata( string $path ): array {
        return $this->rpc( '/files/get_metadata', array( 'path' => $path ) );
    }

    public function download( string $path ): string {
        return $this->content_request( '/files/download', array( 'path' => $path ) );
    }

    public function thumbnail( string $path, string $size = 'w960h640' ): string {
        return $this->content_request(
            '/files/get_thumbnail_v2',
            array(
                'resource' => array( '.tag' => 'path', 'path' => $path ),
                'format'   => 'jpeg',
                'size'     => $size,
                'mode'     => 'bestfit',
            )
        );
    }

    private function rpc( string $endpoint, array $body ): array {
        $response = wp_remote_post(
            self::API . $endpoint,
            array(
                'timeout' => 45,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->access_token(),
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode( $body ),
            )
        );
        return $this->decode_response( $response );
    }

    private function content_request( string $endpoint, array $arg ): string {
        $response = wp_remote_post(
            self::CONTENT . $endpoint,
            array(
                'timeout' => 90,
                'headers' => array(
                    'Authorization'   => 'Bearer ' . $this->access_token(),
                    'Dropbox-API-Arg' => wp_json_encode( $arg ),
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( $response->get_error_message() );
        }
        $status = wp_remote_retrieve_response_code( $response );
        if ( $status < 200 || $status >= 300 ) {
            throw new \RuntimeException( 'Dropbox content request failed: ' . wp_remote_retrieve_body( $response ) );
        }
        return (string) wp_remote_retrieve_body( $response );
    }

    private function access_token(): string {
        $token = Crypto::decrypt( (string) get_option( 'aipex_dgp_access_token', '' ) );
        $expires = (int) get_option( 'aipex_dgp_access_expires', 0 );
        if ( $token && $expires > time() ) {
            return $token;
        }

        $refresh = Crypto::decrypt( (string) get_option( 'aipex_dgp_refresh_token', '' ) );
        if ( '' === $refresh ) {
            throw new \RuntimeException( 'Dropbox is not connected.' );
        }

        $response = wp_remote_post(
            self::TOKEN_URL,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode(
                        (string) get_option( 'aipex_dgp_app_key', '' ) . ':' . Crypto::decrypt( (string) get_option( 'aipex_dgp_app_secret', '' ) )
                    ),
                ),
                'body' => array(
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $refresh,
                ),
            )
        );
        $data = $this->decode_response( $response );
        $token = sanitize_text_field( $data['access_token'] ?? '' );
        if ( '' === $token ) {
            throw new \RuntimeException( 'Dropbox access-token refresh failed.' );
        }
        update_option( 'aipex_dgp_access_token', Crypto::encrypt( $token ), false );
        update_option( 'aipex_dgp_access_expires', time() + max( 60, absint( $data['expires_in'] ?? 14400 ) - 120 ), false );
        return $token;
    }

    private function decode_response( $response ): array {
        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( $response->get_error_message() );
        }
        $status = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $status < 200 || $status >= 300 ) {
            $message = is_array( $data ) ? ( $data['error_summary'] ?? $data['error_description'] ?? wp_json_encode( $data ) ) : 'Unknown Dropbox error';
            throw new \RuntimeException( 'Dropbox API error: ' . $message );
        }
        return is_array( $data ) ? $data : array();
    }

    private function normalise_path( string $path ): string {
        $path = trim( $path );
        if ( '' === $path || '/' === $path ) {
            return '';
        }
        return '/' . ltrim( $path, '/' );
    }
}
