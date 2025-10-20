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
            '/wp-login.php',
            '/wp-json/',
            '/wp-admin/admin-ajax.php'
        ];

        foreach ( $allowed_paths as $path ) {
            if ( stripos( $request_uri, $path ) !== false ) {
                return; // whitelist match → no redirect
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

        do_action( 'wrpa/access_user_registered', $user_id );
    }

    /**
     * Placeholder: checks if user has active subscription.
     */
    private static function user_has_active_subscription( $user_id ) {
        $active = get_user_meta( $user_id, 'wrpa_active_subscription', true );
        return (bool) $active;
    }
}
