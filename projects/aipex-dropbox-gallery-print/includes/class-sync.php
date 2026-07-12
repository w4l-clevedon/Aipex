<?php

namespace Aipex_DGP;

defined( 'ABSPATH' ) || exit;

final class Sync {
    private Dropbox_Client $client;

    public function __construct() {
        $this->client = new Dropbox_Client();
    }

    public function sync_gallery( int $gallery_id, bool $force_full = false ): array {
        $path = (string) get_post_meta( $gallery_id, '_aipex_dropbox_folder', true );
        if ( '' === $path ) {
            throw new \RuntimeException( 'No Dropbox folder has been selected.' );
        }

        $cursor = $force_full ? '' : (string) get_post_meta( $gallery_id, '_aipex_dropbox_cursor', true );
        $result = $cursor ? $this->client->list_folder_changes( $cursor ) : $this->client->list_folder( $path, false );
        $stats = array( 'added' => 0, 'updated' => 0, 'removed' => 0, 'ignored' => 0 );

        while ( true ) {
            foreach ( (array) ( $result['entries'] ?? array() ) as $entry ) {
                $this->process_entry( $gallery_id, $entry, $stats );
            }
            $cursor = (string) ( $result['cursor'] ?? $cursor );
            if ( empty( $result['has_more'] ) ) {
                break;
            }
            $result = $this->client->list_folder_continue( $cursor );
        }

        update_post_meta( $gallery_id, '_aipex_dropbox_cursor', $cursor );
        update_post_meta( $gallery_id, '_aipex_last_sync', current_time( 'mysql', true ) );
        update_post_meta( $gallery_id, '_aipex_last_sync_stats', $stats );
        return $stats;
    }

    private function process_entry( int $gallery_id, array $entry, array &$stats ): void {
        $tag = (string) ( $entry['.tag'] ?? '' );
        $path = (string) ( $entry['path_lower'] ?? '' );
        $name = (string) ( $entry['name'] ?? basename( $path ) );

        if ( 'deleted' === $tag ) {
            $photo_id = $this->find_photo_by_path( $gallery_id, $path );
            if ( $photo_id ) {
                wp_update_post( array( 'ID' => $photo_id, 'post_status' => 'draft' ) );
                $stats['removed']++;
            }
            return;
        }
        if ( 'file' !== $tag ) {
            $stats['ignored']++;
            return;
        }

        $extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
        if ( in_array( $extension, array( 'json', 'csv' ), true ) && preg_match( '/\.hotspots\.(json|csv)$/i', $name ) ) {
            $this->import_hotspot_companion( $gallery_id, $entry );
            return;
        }
        if ( ! in_array( $extension, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
            $stats['ignored']++;
            return;
        }

        $dropbox_id = sanitize_text_field( $entry['id'] ?? '' );
        $photo_id = $this->find_photo_by_dropbox_id( $dropbox_id );
        $is_new = ! $photo_id;
        if ( ! $photo_id ) {
            $photo_id = wp_insert_post(
                array(
                    'post_type'   => 'aipex_photo',
                    'post_status' => 'publish',
                    'post_title'  => pathinfo( $name, PATHINFO_FILENAME ),
                ),
                true
            );
            if ( is_wp_error( $photo_id ) ) {
                throw new \RuntimeException( $photo_id->get_error_message() );
            }
        }

        update_post_meta( $photo_id, '_aipex_gallery_id', $gallery_id );
        update_post_meta( $photo_id, '_aipex_dropbox_id', $dropbox_id );
        update_post_meta( $photo_id, '_aipex_dropbox_path', $path );
        update_post_meta( $photo_id, '_aipex_dropbox_rev', sanitize_text_field( $entry['rev'] ?? '' ) );
        update_post_meta( $photo_id, '_aipex_dropbox_modified', sanitize_text_field( $entry['server_modified'] ?? '' ) );
        update_post_meta( $photo_id, '_aipex_width', absint( $entry['media_info']['metadata']['dimensions']['width'] ?? 0 ) );
        update_post_meta( $photo_id, '_aipex_height', absint( $entry['media_info']['metadata']['dimensions']['height'] ?? 0 ) );
        $this->cache_preview( $photo_id, $path, sanitize_text_field( $entry['rev'] ?? '' ) );
        $this->apply_pending_hotspots( $gallery_id, $photo_id, $name );
        $stats[ $is_new ? 'added' : 'updated' ]++;
    }

    private function cache_preview( int $photo_id, string $path, string $rev ): void {
        if ( $rev && $rev === get_post_meta( $photo_id, '_aipex_cached_rev', true ) && get_post_meta( $photo_id, '_aipex_preview_url', true ) ) {
            return;
        }
        $bytes = $this->client->thumbnail( $path );
        $uploads = wp_upload_dir();
        $dir = trailingslashit( $uploads['basedir'] ) . 'aipex-dropbox-gallery';
        wp_mkdir_p( $dir );
        $filename = 'photo-' . $photo_id . '-' . substr( md5( $rev ?: $path ), 0, 10 ) . '.jpg';
        $filepath = trailingslashit( $dir ) . $filename;
        if ( false === file_put_contents( $filepath, $bytes ) ) {
            throw new \RuntimeException( 'Could not write the cached Dropbox preview.' );
        }
        update_post_meta( $photo_id, '_aipex_preview_url', trailingslashit( $uploads['baseurl'] ) . 'aipex-dropbox-gallery/' . $filename );
        update_post_meta( $photo_id, '_aipex_full_url', trailingslashit( $uploads['baseurl'] ) . 'aipex-dropbox-gallery/' . $filename );
        update_post_meta( $photo_id, '_aipex_cached_rev', $rev );
    }

    private function import_hotspot_companion( int $gallery_id, array $entry ): void {
        $name = (string) ( $entry['name'] ?? '' );
        $base = preg_replace( '/\.hotspots\.(json|csv)$/i', '', $name );
        $content = $this->client->download( (string) $entry['path_lower'] );
        $hotspots = str_ends_with( strtolower( $name ), '.json' ) ? $this->parse_json( $content ) : $this->parse_csv( $content );
        update_post_meta( $gallery_id, '_aipex_pending_hotspots_' . md5( strtolower( $base ) ), $hotspots );

        foreach ( get_posts( array( 'post_type' => 'aipex_photo', 'posts_per_page' => -1, 'post_status' => 'any', 'meta_key' => '_aipex_gallery_id', 'meta_value' => $gallery_id ) ) as $photo ) {
            if ( strtolower( $photo->post_title ) === strtolower( $base ) ) {
                update_post_meta( $photo->ID, '_aipex_hotspots', $hotspots );
            }
        }
    }

    private function parse_json( string $content ): array {
        $data = json_decode( $content, true );
        $items = is_array( $data['people'] ?? null ) ? $data['people'] : ( is_array( $data ) ? $data : array() );
        return array_values( array_filter( array_map( array( $this, 'normalise_hotspot' ), $items ) ) );
    }

    private function parse_csv( string $content ): array {
        $rows = array_map( 'str_getcsv', preg_split( '/\r\n|\r|\n/', trim( $content ) ) );
        $headers = array_map( 'sanitize_key', array_shift( $rows ) ?: array() );
        $items = array();
        foreach ( $rows as $row ) {
            if ( count( $row ) !== count( $headers ) ) continue;
            $items[] = array_combine( $headers, $row );
        }
        return array_values( array_filter( array_map( array( $this, 'normalise_hotspot' ), $items ) ) );
    }

    private function normalise_hotspot( $item ): ?array {
        if ( ! is_array( $item ) || empty( $item['name'] ) ) return null;
        return array(
            'name' => sanitize_text_field( $item['name'] ),
            'role' => sanitize_text_field( $item['role'] ?? '' ),
            'row' => absint( $item['row'] ?? 0 ),
            'position_from_left' => absint( $item['position_from_left'] ?? 0 ),
            'x' => (float) ( $item['x'] ?? 0 ),
            'y' => (float) ( $item['y'] ?? 0 ),
            'width' => (float) ( $item['width'] ?? 0.04 ),
            'height' => (float) ( $item['height'] ?? 0.08 ),
            'notes' => sanitize_textarea_field( $item['notes'] ?? '' ),
        );
    }

    private function apply_pending_hotspots( int $gallery_id, int $photo_id, string $filename ): void {
        $base = pathinfo( $filename, PATHINFO_FILENAME );
        $hotspots = get_post_meta( $gallery_id, '_aipex_pending_hotspots_' . md5( strtolower( $base ) ), true );
        if ( is_array( $hotspots ) ) update_post_meta( $photo_id, '_aipex_hotspots', $hotspots );
    }

    private function find_photo_by_dropbox_id( string $id ): int {
        if ( '' === $id ) return 0;
        $posts = get_posts( array( 'post_type' => 'aipex_photo', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_aipex_dropbox_id', 'meta_value' => $id ) );
        return (int) ( $posts[0] ?? 0 );
    }

    private function find_photo_by_path( int $gallery_id, string $path ): int {
        $posts = get_posts( array(
            'post_type' => 'aipex_photo', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids',
            'meta_query' => array(
                array( 'key' => '_aipex_gallery_id', 'value' => $gallery_id ),
                array( 'key' => '_aipex_dropbox_path', 'value' => $path ),
            ),
        ) );
        return (int) ( $posts[0] ?? 0 );
    }
}
