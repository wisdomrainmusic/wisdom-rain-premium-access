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
        add_action( 'init', [ __CLASS__, 'maybe_handle_request' ] );
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
            return home_url( '/' );
        }

        $token   = get_user_meta( $user_id, self::META_TOKEN, true );
        $expires = (int) get_user_meta( $user_id, self::META_EXPIRES, true );

        if ( ! $token || $expires < time() ) {
            $token = self::generate_token( $user_id );
        }

        if ( ! $token ) {
            return home_url( '/' );
        }

        $base = home_url( '/verify-email/' );

        if ( class_exists( __NAMESPACE__ . '\WRPA_Core' ) && method_exists( WRPA_Core::class, 'urls' ) ) {
            $urls = WRPA_Core::urls();

            if ( ! empty( $urls['verify_email_url'] ) ) {
                $base = $urls['verify_email_url'];
            }
        }

        $base = apply_filters( 'wrpa_verify_email_base_url', $base, $user_id );

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
        if ( '1' !== (string) filter_input( INPUT_GET, 'wrpa-verify', FILTER_SANITIZE_NUMBER_INT ) ) {
            return;
        }

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
            self::redirect_with_flag( 'already-verified' );
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
     * Performs a safe redirect with status feedback appended to the dashboard URL.
     *
     * @param string $status Status slug appended to the redirect query.
     */
    protected static function redirect_with_flag( string $status ) : void {
        $status      = sanitize_key( $status );
        $destination = in_array( $status, [ 'success', 'already-verified' ], true )
            ? self::dashboard_url()
            : self::verify_required_url();

        $destination = add_query_arg( 'wrpa-verify-status', $status, $destination );

        wp_safe_redirect( $destination );
        exit;
    }

    /**
     * Returns the URL used for successful verification redirection.
     */
    protected static function dashboard_url() : string {
        $dashboard = home_url( '/dashboard/' );

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
}
