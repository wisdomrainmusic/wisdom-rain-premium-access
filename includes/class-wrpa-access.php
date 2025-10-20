<?php
/**
 * WRPA - Access Module
 * @package WisdomRain\PremiumAccess
 */

namespace WRPA;

if ( ! defined( 'ABSPATH' ) ) exit;

class WRPA_Access {

    /**
     * Initialize module hooks.
     */
    public static function init() {
        // Main redirect protection
        add_action( 'template_redirect', [ __CLASS__, 'restrict_premium_pages' ], 5 );

        // WooCommerce purchase restriction
        add_filter( 'woocommerce_add_to_cart_validation', [ __CLASS__, 'prevent_non_subscriber_checkout' ], 10, 3 );

        // Redirect verified users to dashboard
        add_action( 'init', [ __CLASS__, 'handle_email_verification_redirect' ] );

        // Persist membership metadata for brand-new signups.
        add_action( 'user_register', [ __CLASS__, 'handle_user_registered' ], 10, 1 );
    }

    /**
     * Restrict access to premium CPTs and taxonomies.
     */
    public static function restrict_premium_pages() {
        // Bypass admin, AJAX, REST
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        // 🧠 Whitelisted paths (no redirect protection)
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        $allowed_paths = [
            '/subscribe/',
            '/sign-up/',
            '/login/',
            '/my-account/',
            '/cart/',
            '/checkout/',
            '/wisdom-rain-dashboard/',
            '/verify-required/',
            '/wp-login.php',
            '/wp-json/',
            '/wp-admin/admin-ajax.php'
        ];

        foreach ( $allowed_paths as $path ) {
            if ( stripos( $request_uri, $path ) !== false ) {
                return; // whitelist match → no redirect
            }
        }

        if ( self::should_require_verification() ) {
            $protected_paths = [
                '/subscribe/',
                '/dashboard/',
                '/wisdom-rain-dashboard/',
            ];

            foreach ( $protected_paths as $path ) {
                if ( self::request_matches_path( $request_uri, $path ) ) {
                    self::redirect_to_verify_required();
                }
            }
        }

        if ( is_front_page() ) {
            return; // homepage allowed
        }

        // Detect premium CPTs or taxonomies
        $is_premium_page = false;

        if ( is_singular( [ 'library', 'music', 'meditation', 'magazine', 'sleep_story', 'children_story' ] ) ) {
            $is_premium_page = true;
        }

        if ( is_post_type_archive( [ 'library', 'music', 'meditation', 'magazine', 'sleep_story', 'children_story' ] ) ) {
            $is_premium_page = true;
        }

        if ( is_tax( [ 'library_category', 'music_category', 'meditation_category', 'magazine_category', 'children_story_category' ] ) ) {
            $is_premium_page = true;
        }

        // Redirect non-subscribers only if on premium content
        if ( $is_premium_page ) {
            $user_id = get_current_user_id();

            if ( ! is_user_logged_in() || ! self::user_has_active_subscription( $user_id ) ) {
                wp_redirect( home_url( '/subscribe/' ) );
                exit;
            }
        }
    }

    /**
     * Prevent WooCommerce checkout for users without subscription.
     */
    public static function prevent_non_subscriber_checkout( $passed, $product_id, $quantity ) {
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return $passed;
        }

        if ( ! is_user_logged_in() || ! self::user_has_active_subscription( get_current_user_id() ) ) {
            wc_add_notice( __( 'Please sign up for a subscription before purchasing.', 'wrpa' ), 'error' );
            wp_safe_redirect( home_url( '/sign-up/' ) );
            exit;
        }

        return $passed;
    }

    /**
     * After email verification, redirect to dashboard.
     */
    public static function handle_email_verification_redirect() {
        if ( isset( $_GET['wrpa_verified'] ) && $_GET['wrpa_verified'] === 'true' && is_user_logged_in() ) {
            wp_safe_redirect( home_url( '/wisdom-rain-dashboard/' ) );
            exit;
        }
    }

    /**
     * Records baseline membership metadata when a new user account is created.
     *
     * @param int $user_id The identifier of the user that just registered.
     * @return void
     */
    public static function handle_user_registered( $user_id ) {
        $user_id = absint( $user_id );

        if ( ! $user_id ) {
            return;
        }

        $registered_at = current_time( 'mysql', true );

        update_user_meta( $user_id, 'wrpa_access_registered_at', $registered_at );

        if ( ! metadata_exists( 'user', $user_id, 'wrpa_active_subscription' ) ) {
            update_user_meta( $user_id, 'wrpa_active_subscription', '' );
        }

        if ( class_exists( __NAMESPACE__ . '\WRPA_Email_Verify' ) ) {
            WRPA_Email_Verify::generate_token( $user_id );
            delete_user_meta( $user_id, WRPA_Email_Verify::META_FLAG );
        }

        if ( class_exists( __NAMESPACE__ . '\WRPA_Email' ) ) {
            WRPA_Email::send_verification( $user_id, true );
        }

        do_action( 'wrpa/access_user_registered', $user_id );
    }

    /**
     * Placeholder: checks if user has active subscription.
     */
    private static function user_has_active_subscription( $user_id ) {
        $active = get_user_meta( $user_id, 'wrpa_active_subscription', true );
        return (bool) $active;
    }

    /**
     * Determines whether the logged-in user must verify their email before proceeding.
     */
    protected static function should_require_verification() : bool {
        if ( ! is_user_logged_in() || ! class_exists( __NAMESPACE__ . '\WRPA_Email_Verify' ) ) {
            return false;
        }

        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return false;
        }

        return ! WRPA_Email_Verify::is_verified( $user_id );
    }

    /**
     * Basic comparison helper that checks whether the current request targets a given path.
     */
    protected static function request_matches_path( string $request_uri, string $path ) : bool {
        $request_path = parse_url( $request_uri, PHP_URL_PATH );
        if ( ! is_string( $request_path ) ) {
            $request_path = '';
        }

        $request_path = trailingslashit( $request_path );
        $path         = trailingslashit( $path );

        return $request_path === $path;
    }

    /**
     * Redirects the visitor to the verification required holding page.
     */
    protected static function redirect_to_verify_required() : void {
        $destination = home_url( '/verify-required/' );

        if ( class_exists( __NAMESPACE__ . '\WRPA_Core' ) && method_exists( WRPA_Core::class, 'urls' ) ) {
            $urls = WRPA_Core::urls();

            if ( ! empty( $urls['verify_required_url'] ) ) {
                $destination = $urls['verify_required_url'];
            }
        }

        $destination = apply_filters( 'wrpa_verify_required_url', $destination );

        wp_safe_redirect( $destination );
        exit;
    }
}
