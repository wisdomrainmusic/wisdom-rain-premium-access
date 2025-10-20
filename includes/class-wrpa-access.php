<?php
/**
 * WRPA_Access
 * Access control and redirect helpers for Wisdom Rain Premium Access
 *
 * Replace or merge into your existing class-wrpa-access.php file.
 *
 * - Safe guards for admin/AJAX/REST requests
 * - Adds guest redirect for checkout -> /sign-up/?redirect_to=...
 * - Fires wrpa/access_initialized action for backwards compatibility
 */

namespace WRPA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( __NAMESPACE__ . '\\WRPA_Access' ) ) {
    // If the class already exists somewhere else, do nothing to avoid fatal errors.
    return;
}

class WRPA_Access {

    /**
     * Initialize access hooks.
     * Call this during plugin bootstrap: WRPA_Access::init();
     */
    public static function init() {
        // run early on template_redirect so we can intercept checkout before other redirects
        add_action( 'template_redirect', [ __CLASS__, 'redirect_checkout_for_guests' ], 5 );

        // keep a compatibility hook so other modules/themes can attach after access is ready
        do_action( 'wrpa/access_initialized' );
    }

    /**
     * Redirect unauthenticated guests who try to reach checkout to the sign-up page.
     *
     * - Uses is_checkout() when WooCommerce is active
     * - Falls back to is_page('checkout') if WooCommerce isn't available
     * - Skips admin, AJAX, REST and CLI requests
     * - Adds a `redirect_to` query param so signup/login can send the user back
     */
    public static function redirect_checkout_for_guests() {
        // safety: don't run during admin, AJAX, REST, CLI or when headers already sent
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return;
        }

        // extra guard: avoid running if headers already sent (prevents redirect errors)
        if ( headers_sent() ) {
            return;
        }

        // Determine whether current request is checkout
        $is_checkout = false;
        if ( function_exists( 'is_checkout' ) ) {
            // WooCommerce present - use its helper (covers endpoints too)
            try {
                $is_checkout = (bool) is_checkout();
            } catch ( \Throwable $e ) {
                // swallow any unexpected errors from theme/plugin conflicts
                $is_checkout = false;
            }
        } else {
            // Fallback - check for page with slug 'checkout'
            if ( function_exists( 'is_page' ) && is_page( 'checkout' ) ) {
                $is_checkout = true;
            }
        }

        // If it's checkout and the user is not logged in, redirect to /sign-up/
        if ( $is_checkout && ! is_user_logged_in() ) {
            // Compute the return path so user can come back after sign-up/login
            $current_path = '/checkout/';
            if ( isset( $_SERVER['REQUEST_URI'] ) ) {
                // sanitize and keep path+query
                $current_path = wp_unslash( $_SERVER['REQUEST_URI'] );
            }

            $signup_url = site_url( '/sign-up/' );
            $redirect_to = rawurlencode( $current_path );
            $redirect_url = $signup_url . '?redirect_to=' . $redirect_to;

            // Use wp_safe_redirect for safety (ensures host is same or allowed)
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    /**
     * Convenience wrapper: check if a given post type is considered premium content.
     * Keep this method minimal — if your original file has richer logic, merge accordingly.
     *
     * @param string $post_type
     * @return bool
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
     * Optionally expose a helper to redirect to signup with current URL.
     * Can be used elsewhere in plugin: WRPA_Access::redirect_to_signup();
     *
     * @param string|null $custom_path Optional custom path to include in redirect_to
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

// Boot the access module when plugin core asks for it.
// If your bootstrap already calls WRPA_Access::init(), this won't hurt (init is idempotent).
if ( ! did_action( 'wrpa/access_auto_init' ) ) {
    // We intentionally don't auto-init on file include; plugin core should call WRPA_Access::init()
    // But to be practical for manual replacements, we call init on plugins_loaded at late priority.
    add_action( 'plugins_loaded', function() {
        if ( class_exists( __NAMESPACE__ . '\\WRPA_Access' ) ) {
            \WRPA\WRPA_Access::init();
        }
    }, 20 );
}
