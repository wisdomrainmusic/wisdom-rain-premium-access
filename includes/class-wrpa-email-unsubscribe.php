<?php
/**
 * WRPA - Email Unsubscribe utilities.
 *
 * @package WisdomRain\PremiumAccess
 */

namespace WRPA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles unsubscribe token generation and front-end processing.
 */
class WRPA_Email_Unsubscribe {
    /**
     * Query parameter used for unsubscribe requests.
     */
    const QUERY_KEY = 'wrpa-unsubscribe';

    /**
     * Stores a per-user secret used for signing unsubscribe tokens.
     */
    const META_SECRET = '_wrpa_email_secret';

    /**
     * Stores the timestamp of the last unsubscribe confirmation.
     */
    const META_UNSUBSCRIBED = '_wrpa_email_unsubscribed';

    /**
     * Bootstraps unsubscribe handling hooks.
     */
    public static function init() : void {
        add_action( 'wrpa/frontend_init', [ __CLASS__, 'maybe_handle_request' ] );
    }

    /**
     * Returns whether a user has opted out of email notifications.
     *
     * @param int $user_id WordPress user ID.
     */
    public static function is_unsubscribed( int $user_id ) : bool {
        if ( $user_id <= 0 ) {
            return false;
        }

        $value = get_user_meta( $user_id, self::META_UNSUBSCRIBED, true );
        $is_unsubscribed = ! empty( $value );

        /**
         * Filters whether a user is considered unsubscribed.
         *
         * @param bool       $is_unsubscribed Current status.
         * @param int        $user_id         User identifier.
         * @param string|int $value           Raw meta value stored for the user.
         */
        return (bool) apply_filters( 'wrpa_email_is_unsubscribed', $is_unsubscribed, $user_id, $value );
    }

    /**
     * Generates a secure unsubscribe URL for the provided user.
     *
     * @param int $user_id WordPress user ID.
     * @return string
     */
    public static function get_unsubscribe_url( int $user_id ) : string {
        $base_url = 'https://wisdomrainbookmusic.com/unsubscribe/';

        if ( class_exists( __NAMESPACE__ . '\\WRPA_Urls' ) ) {
            $base_url = WRPA_Urls::unsubscribe_base_url();
        }

        $base_url = trailingslashit( untrailingslashit( $base_url ) );

        if ( $user_id <= 0 ) {
            return $base_url;
        }

        return self::signed_url_for( $user_id, $base_url );
    }

    /**
     * Generates a signed unsubscribe URL for a given base.
     *
     * @param int    $user_id  WordPress user ID.
     * @param string $base_url Canonical unsubscribe base URL.
     * @return string Signed unsubscribe URL.
     */
    public static function signed_url_for( int $user_id, string $base_url ) : string {
        $normalized_base = trailingslashit( untrailingslashit( $base_url ) );

        if ( $user_id <= 0 ) {
            return $normalized_base;
        }

        $token = self::generate_token( $user_id );

        if ( '' === $token ) {
            return $normalized_base;
        }

        return add_query_arg( self::QUERY_KEY, rawurlencode( $token ), $normalized_base );
    }

    /**
     * Attempts to process an unsubscribe request when the query parameter is present.
     */
    public static function maybe_handle_request() : void {
        if ( empty( $_GET[ self::QUERY_KEY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $payload = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_KEY ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $user_id = self::validate_token( $payload );

        if ( ! $user_id ) {
            self::render_response(
                'error',
                __( 'We could not verify your unsubscribe request. Please contact support if you need assistance.', 'wrpa' )
            );
        }

        self::mark_unsubscribed( $user_id );

        $dashboard_url = home_url( '/dashboard/' );

        if ( class_exists( __NAMESPACE__ . '\\WRPA_Core' ) && method_exists( WRPA_Core::class, 'urls' ) ) {
            $urls = WRPA_Core::urls();
            if ( ! empty( $urls['dashboard_url'] ) ) {
                $dashboard_url = $urls['dashboard_url'];
            }
        }

        $message = sprintf(
            /* translators: %s: dashboard URL. */
            __( 'You have been unsubscribed from future emails. You can update your preferences anytime from your <a href="%s">account dashboard</a>.', 'wrpa' ),
            esc_url( $dashboard_url )
        );

        /**
         * Fires after a user successfully unsubscribes from email notifications.
         *
         * @param int $user_id User identifier.
         */
        do_action( 'wrpa_email_unsubscribed', $user_id );

        self::render_response( 'success', $message );
    }

    /**
     * Marks a user as unsubscribed by updating the associated metadata.
     *
     * @param int $user_id WordPress user ID.
     */
    protected static function mark_unsubscribed( int $user_id ) : void {
        update_user_meta( $user_id, self::META_UNSUBSCRIBED, current_time( 'timestamp' ) );
    }

    /**
     * Generates a signed token for the unsubscribe URL.
     *
     * @param int $user_id WordPress user ID.
     * @return string
     */
    protected static function generate_token( int $user_id ) : string {
        $user = get_user_by( 'id', $user_id );

        if ( ! $user || empty( $user->user_email ) ) {
            return '';
        }

        $secret = self::get_or_create_secret( $user_id );

        if ( '' === $secret ) {
            return '';
        }

        $email = strtolower( (string) $user->user_email );
        $hash  = hash_hmac( 'sha256', $user_id . '|' . $email, $secret );

        return base64_encode( $user_id . ':' . $hash );
    }

    /**
     * Validates a token and returns the associated user ID when valid.
     *
     * @param string $token Token payload from the request.
     * @return int
     */
    protected static function validate_token( string $token ) : int {
        $decoded = base64_decode( $token, true );

        if ( false === $decoded ) {
            return 0;
        }

        $parts = explode( ':', $decoded, 2 );

        if ( 2 !== count( $parts ) ) {
            return 0;
        }

        $user_id = absint( $parts[0] );
        $hash    = trim( $parts[1] );

        if ( $user_id <= 0 || '' === $hash ) {
            return 0;
        }

        $user = get_user_by( 'id', $user_id );

        if ( ! $user || empty( $user->user_email ) ) {
            return 0;
        }

        $secret = get_user_meta( $user_id, self::META_SECRET, true );

        if ( empty( $secret ) ) {
            return 0;
        }

        $email    = strtolower( (string) $user->user_email );
        $expected = hash_hmac( 'sha256', $user_id . '|' . $email, $secret );

        if ( ! hash_equals( $expected, $hash ) ) {
            return 0;
        }

        return $user_id;
    }

    /**
     * Retrieves or seeds the secret used to sign unsubscribe tokens.
     *
     * @param int $user_id WordPress user ID.
     * @return string
     */
    protected static function get_or_create_secret( int $user_id ) : string {
        $secret = get_user_meta( $user_id, self::META_SECRET, true );

        if ( ! empty( $secret ) ) {
            return (string) $secret;
        }

        $secret = wp_generate_password( 32, false );

        update_user_meta( $user_id, self::META_SECRET, $secret );

        return $secret;
    }

    /**
     * Renders the unsubscribe response and halts execution.
     *
     * @param string $status  Either "success" or "error".
     * @param string $message Message displayed to the user.
     */
    protected static function render_response( string $status, string $message ) : void {
        $message = apply_filters( 'wrpa_email_unsubscribe_response', $message, $status );

        $allowed = [
            'a'      => [ 'href' => [] ],
            'strong' => [],
            'em'     => [],
            'p'      => [],
        ];

        $body = wp_kses( wpautop( $message ), $allowed );

        wp_die( $body, esc_html__( 'Email Preferences', 'wrpa' ), [ 'response' => 200 ] );
    }
}
