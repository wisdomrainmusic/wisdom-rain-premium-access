<?php
/**
 * WRPA_Access
 * Access control and redirect helpers for Wisdom Rain Premium Access
 *
 * v2.1 – Checkout Redirect + Verify Enforcement
 */

namespace WRPA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( __NAMESPACE__ . '\\WRPA_Access' ) ) {
    return;
}

class WRPA_Access {

    /**
     * Initialize access hooks.
     */
    public static function init() {
        // Early intercept for guest checkout redirect
        add_action( 'template_redirect', [ __CLASS__, 'redirect_checkout_for_guests' ], 5 );

        // Verify-required protection for unverified users
        add_action( 'template_redirect', [ __CLASS__, 'protect_unverified_users' ], 10 );

        do_action( 'wrpa/access_initialized' );
    }

    /**
     * Redirect unauthenticated guests trying to access checkout -> /sign-up/
     */
    public static function redirect_checkout_for_guests() {
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        if ( headers_sent() ) {
            return;
        }

        $is_checkout = false;
        if ( function_exists( 'is_checkout' ) ) {
            try {
                $is_checkout = (bool) is_checkout();
            } catch ( \Throwable $e ) {
                $is_checkout = false;
            }
        } elseif ( function_exists( 'is_page' ) && is_page( 'checkout' ) ) {
            $is_checkout = true;
        }

        if ( $is_checkout && ! is_user_logged_in() ) {
            $current = ( isset( $_SERVER['REQUEST_URI'] ) ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/checkout/';
            $signup_url = site_url( '/sign-up/' );
            $redirect_to = rawurlencode( $current );

            // Only add redirect_to for checkout
            if ( strpos( $current, 'checkout' ) !== false ) {
                $redirect_url = $signup_url . '?redirect_to=' . $redirect_to;
            } else {
                $redirect_url = $signup_url;
            }

            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    /**
     * Prevent unverified users from reaching dashboard or my-account
     * Redirect them to /verify-required/
     */
    public static function protect_unverified_users() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        $verified = get_user_meta( $user_id, 'wrpa_verified', true );

        // Only apply to frontend, not admin or ajax
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        // List of restricted pages for unverified users
        $restricted_slugs = array( 'dashboard', 'my-account', 'subscribe', 'checkout' );

        if ( ! $verified || intval( $verified ) !== 1 ) {
            foreach ( $restricted_slugs as $slug ) {
                if ( is_page( $slug ) ) {
                    wp_safe_redirect( site_url( '/verify-required/' ) );
                    exit;
                }
            }
        }
    }

    /**
     * Identify premium CPTs
     */
    public static function is_premium_cpt( $post_type ) {
        $premium = array(
            'library',
            'music',
            'meditations',
            'sleep_stories',
            'magazines',
            'children_stories',
        );

        return in_array( $post_type, $premium, true );
    }

    /**
     * Helper redirect to signup
     */
    public static function redirect_to_signup( $custom_path = null ) {
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        $current_path = '/';
        if ( $custom_path ) {
            $current_path = wp_unslash( $custom_path );
        } elseif ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $current_path = wp_unslash( $_SERVER['REQUEST_URI'] );
        }

        $signup_url = site_url( '/sign-up/' );
        $redirect_url = $signup_url . '?redirect_to=' . rawurlencode( $current_path );

        wp_safe_redirect( $redirect_url );
        exit;
    }
}

// Auto-init if plugin core not handling
add_action( 'plugins_loaded', function() {
    if ( class_exists( __NAMESPACE__ . '\\WRPA_Access' ) ) {
        \WRPA\WRPA_Access::init();
    }
}, 20 );
