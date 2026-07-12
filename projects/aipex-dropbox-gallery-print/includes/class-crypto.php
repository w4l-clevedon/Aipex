<?php

namespace Aipex_DGP;

defined( 'ABSPATH' ) || exit;

final class Crypto {
    private static function key(): string {
        return hash( 'sha256', wp_salt( 'auth' ) . AUTH_KEY, true );
    }

    public static function encrypt( string $plaintext ): string {
        if ( '' === $plaintext ) {
            return '';
        }

        $key = self::key();
        if ( function_exists( 'sodium_crypto_secretbox' ) ) {
            $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            return 'sodium:' . base64_encode( $nonce . sodium_crypto_secretbox( $plaintext, $nonce, $key ) );
        }

        $iv = random_bytes( 16 );
        $ciphertext = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
        if ( false === $ciphertext ) {
            throw new \RuntimeException( 'Unable to encrypt Dropbox credentials.' );
        }

        $mac = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );
        return 'openssl:' . base64_encode( $iv . $mac . $ciphertext );
    }

    public static function decrypt( string $payload ): string {
        if ( '' === $payload ) {
            return '';
        }

        $key = self::key();
        if ( str_starts_with( $payload, 'sodium:' ) ) {
            $raw = base64_decode( substr( $payload, 7 ), true );
            if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
                return '';
            }
            $nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $ciphertext = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
            return false === $plaintext ? '' : $plaintext;
        }

        if ( ! str_starts_with( $payload, 'openssl:' ) ) {
            return '';
        }

        $raw = base64_decode( substr( $payload, 8 ), true );
        if ( false === $raw || strlen( $raw ) <= 48 ) {
            return '';
        }
        $iv = substr( $raw, 0, 16 );
        $mac = substr( $raw, 16, 32 );
        $ciphertext = substr( $raw, 48 );
        if ( ! hash_equals( $mac, hash_hmac( 'sha256', $iv . $ciphertext, $key, true ) ) ) {
            return '';
        }
        $plaintext = openssl_decrypt( $ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
        return false === $plaintext ? '' : $plaintext;
    }
}
