<?php
/**
 * WRPA - Email Verification tools.
 *
 * @package WisdomRain\PremiumAccess
 */

namespace WRPA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles generation and validation of email verification tokens.
 */
class WRPA_Email_Verify {

    const META_TOKEN     = 'wrpa_verify_token';
    const META_EXPIRES   = 'wrpa_verify_token_expires';
    const META_FLAG      = 'wrpa_email_verified';
    const META_LAST_SENT = 'wrpa_verify_last_sent';

    /**
     * Bootstraps verification endpoint listeners.
     */
    public static function init() : void {
        add_action( 'template_redirect', [ __CLASS__, 'maybe_handle_request' ], 0 );
        add_action( 'init', [ __CLASS__, 'maybe_handle_resend_request' ] );
    }

    /**
     * Generates a unique verification token for the provided user.
     *
     * @param int $user_id User identifier.
     * @return string Generated token or empty string on failure.
     */
    public static function generate_token( int $user_id ) : string {
        $user_id = absint( $user_id );

        if ( ! $user_id ) {
            return '';
        }

        $token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : wp_generate_password( 32, false, false );

        if ( ! $token ) {
            return '';
        }

        update_user_meta( $user_id, self::META_TOKEN, $token );
        update_user_meta( $user_id, self::META_EXPIRES, time() + DAY_IN_SECONDS );

        return $token;
    }

    /**
     * Returns whether the provided user has completed email verification.
     */
    public static function is_verified( int $user_id ) : bool {
        $user_id = absint( $user_id );

        if ( ! $user_id ) {
            return false;
        }

        return (bool) get_user_meta( $user_id, self::META_FLAG, true );
    }

    /**
     * Returns the verification URL for a user, generating a token when required.
     *
     * @param int $user_id User identifier.
     * @return string Verification URL.
     */
    public static function get_verify_url( int $user_id ) : string {
        $user_id = absint( $user_id );

        if ( ! $user_id ) {
            return defined( 'WRPA_DASHBOARD_URL' ) ? WRPA_DASHBOARD_URL : site_url( '/wisdom-rain-dashboard/' );
        }

        $token   = get_user_meta( $user_id, self::META_TOKEN, true );
        $expires = (int) get_user_meta( $user_id, self::META_EXPIRES, true );

        if ( ! $token || $expires < time() ) {
            $token = self::generate_token( $user_id );
        }

        if ( ! $token ) {
            return defined( 'WRPA_DASHBOARD_URL' ) ? WRPA_DASHBOARD_URL : site_url( '/wisdom-rain-dashboard/' );
        }

        $base = self::verify_email_base_url( $user_id );

        return add_query_arg(
            [
                'wrpa-verify' => 1,
                'token'       => $token,
            ],
            $base
        );
    }

    /**
     * Validates verification requests routed through ?wrpa-verify=1.
     */
    public static function maybe_handle_request() : void {
        $is_verify_request = '1' === (string) filter_input( INPUT_GET, 'wrpa-verify', FILTER_SANITIZE_NUMBER_INT );

        if ( ! $is_verify_request && ! self::is_verify_email_path_request() ) {
            return;
        }

        if ( ! $is_verify_request ) {
            self::redirect_with_flag( 'error' );
        }

        self::handle_request();
    }

    /**
     * Handles verification once the request is confirmed for processing.
     */
    protected static function handle_request() : void {
        $raw_token = isset( $_GET['token'] ) ? wp_unslash( $_GET['token'] ) : '';
        $token     = sanitize_text_field( $raw_token );

        if ( '' === $token ) {
            self::redirect_with_flag( 'error' );
            return;
        }

        $users = get_users(
            [
                'fields'     => 'ids',
                'number'     => 1,
                'meta_key'   => self::META_TOKEN,
                'meta_value' => $token,
            ]
        );

        if ( empty( $users ) ) {
            self::redirect_with_flag( 'error' );
            return;
        }

        $user_id = (int) $users[0];
        $expires = (int) get_user_meta( $user_id, self::META_EXPIRES, true );

        if ( $expires < time() ) {
            delete_user_meta( $user_id, self::META_TOKEN );
            delete_user_meta( $user_id, self::META_EXPIRES );
            self::redirect_with_flag( 'expired' );
            return;
        }

        if ( self::is_verified( $user_id ) ) {
            self::redirect_with_flag( 'success' );
        }

        update_user_meta( $user_id, self::META_FLAG, 1 );
        delete_user_meta( $user_id, self::META_TOKEN );
        delete_user_meta( $user_id, self::META_EXPIRES );
        delete_user_meta( $user_id, self::META_LAST_SENT );

        do_action( 'wrpa_email_verified', $user_id );

        self::redirect_with_flag( 'success' );
    }

    /**
     * Handles ?wrpa-resend=1 requests initiated from the verification required screen.
     */
    public static function maybe_handle_resend_request() : void {
        if ( '1' !== (string) filter_input( INPUT_GET, 'wrpa-resend', FILTER_SANITIZE_NUMBER_INT ) ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return;
        }

        if ( self::is_verified( $user_id ) ) {
            self::redirect_with_flag( 'already-verified' );
        }

        if ( class_exists( __NAMESPACE__ . '\WRPA_Email' ) && WRPA_Email::send_verification( $user_id ) ) {
            self::redirect_with_flag( 'resent' );
        }

        self::redirect_with_flag( 'rate-limit' );
    }

    /**
     * Determines the base URL used for email verification links.
     */
    protected static function verify_email_base_url( int $user_id = 0 ) : string {
        $base    = home_url( '/verify-email/' );
        $user_id = absint( $user_id );

        if ( class_exists( __NAMESPACE__ . '\WRPA_Core' ) && method_exists( WRPA_Core::class, 'urls' ) ) {
            $urls = WRPA_Core::urls();

            if ( ! empty( $urls['verify_email_url'] ) ) {
                $base = $urls['verify_email_url'];
            }
        }

        return apply_filters( 'wrpa_verify_email_base_url', $base, $user_id );
    }

    /**
     * Performs a safe redirect with status feedback appended to the dashboard URL.
     *
     * @param string $status Status slug appended to the redirect query.
     */
    protected static function redirect_with_flag( string $status ) : void {
        $status      = sanitize_key( $status );
        $destination = in_array( $status, [ 'success', 'already-verified' ], true )
            ? self::success_redirect_url( $status )
            : self::verify_required_url();

        $destination = add_query_arg( 'wrpa-verify-status', $status, $destination );

        wp_safe_redirect( $destination );
        exit;
    }

    /**
     * Resolves the destination used after successful verification.
     */
    protected static function success_redirect_url( string $status ) : string {
        $destination = self::dashboard_url();

        return apply_filters( 'wrpa_verify_success_redirect', $destination, $status );
    }

    /**
     * Returns the URL used for successful verification redirection.
     */
    protected static function dashboard_url() : string {
        if ( class_exists( __NAMESPACE__ . '\WRPA_Access' ) && method_exists( WRPA_Access::class, 'get_dashboard_url' ) ) {
            return WRPA_Access::get_dashboard_url();
        }

        $dashboard = defined( 'WRPA_DASHBOARD_URL' ) ? WRPA_DASHBOARD_URL : site_url( '/wisdom-rain-dashboard/' );

        if ( class_exists( __NAMESPACE__ . '\WRPA_Core' ) && method_exists( WRPA_Core::class, 'urls' ) ) {
            $urls = WRPA_Core::urls();

            if ( ! empty( $urls['dashboard_url'] ) ) {
                $dashboard = $urls['dashboard_url'];
            }
        }

        return apply_filters( 'wrpa_dashboard_redirect_url', $dashboard );
    }

    /**
     * Returns the holding page for users awaiting email verification.
     */
    protected static function verify_required_url() : string {
        $pending = home_url( '/verify-required/' );

        if ( class_exists( __NAMESPACE__ . '\WRPA_Core' ) && method_exists( WRPA_Core::class, 'urls' ) ) {
            $urls = WRPA_Core::urls();

            if ( ! empty( $urls['verify_required_url'] ) ) {
                $pending = $urls['verify_required_url'];
            }
        }

        return apply_filters( 'wrpa_verify_required_url', $pending );
    }

    /**
     * Checks whether the current request targets the verification endpoint path.
     */
    protected static function is_verify_email_path_request() : bool {
        if ( empty( $_SERVER['REQUEST_URI'] ) ) {
            return false;
        }

        $request_path = strtok( wp_unslash( $_SERVER['REQUEST_URI'] ), '?' );

        if ( ! is_string( $request_path ) || '' === $request_path ) {
            return false;
        }

        $request_path = trailingslashit( '/' . ltrim( wp_normalize_path( $request_path ), '/' ) );

        $base_path = wp_parse_url( self::verify_email_base_url(), PHP_URL_PATH );

        if ( ! is_string( $base_path ) || '' === $base_path ) {
            return false;
        }

        $base_path = trailingslashit( '/' . ltrim( wp_normalize_path( $base_path ), '/' ) );

        return $request_path === $base_path;
    }
}
