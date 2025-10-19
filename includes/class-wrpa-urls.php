<?php
/**
 * WRPA - URL helpers.
 *
 * @package WisdomRain\PremiumAccess
 */

namespace WRPA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides canonical URLs used throughout the plugin.
 */
class WRPA_Urls {
    /**
     * Canonical My Account endpoint.
     */
    private const ACCOUNT_CANONICAL = 'https://wisdomrainbookmusic.com/my-account/';

    /**
     * Canonical Unsubscribe endpoint.
     */
    private const UNSUBSCRIBE_CANONICAL = 'https://wisdomrainbookmusic.com/unsubscribe/';

    /**
     * Returns the canonical "My Account" URL with a trailing slash.
     */
    public static function account_url() : string {
        $url = self::canonicalize( self::ACCOUNT_CANONICAL, '/my-account/' );

        /**
         * Filters the WRPA My Account URL.
         *
         * @param string $url Canonical account URL.
         */
        return (string) apply_filters( 'wrpa_account_url', $url );
    }

    /**
     * Returns the base unsubscribe URL with a trailing slash.
     */
    public static function unsubscribe_base_url() : string {
        $url = self::canonicalize( self::UNSUBSCRIBE_CANONICAL, '/unsubscribe/' );

        /**
         * Filters the WRPA unsubscribe base URL.
         *
         * @param string $url Canonical unsubscribe URL.
         */
        return (string) apply_filters( 'wrpa_unsubscribe_base_url', $url );
    }

    /**
     * Normalizes a canonical URL and falls back to site_url() when needed.
     */
    private static function canonicalize( string $candidate, string $fallback_path ) : string {
        $url = self::normalize_url( $candidate );

        if ( '' === $url ) {
            $url = site_url( $fallback_path );
        }

        return trailingslashit( untrailingslashit( $url ) );
    }

    /**
     * Validates and trims a provided URL candidate.
     */
    private static function normalize_url( string $candidate ) : string {
        $candidate = trim( $candidate );

        if ( '' === $candidate ) {
            return '';
        }

        if ( function_exists( 'wp_http_validate_url' ) ) {
            $validated = wp_http_validate_url( $candidate );

            if ( ! $validated ) {
                return '';
            }

            return $validated;
        }

        return filter_var( $candidate, FILTER_VALIDATE_URL ) ? $candidate : '';
    }
}
