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
        // Ensure verification success redirects are not overridden by themes.
        add_action( 'template_redirect', [ __CLASS__, 'maybe_disable_template_redirect_conflicts' ], 0 );

        // Enforce verification as early as possible on template redirect.
        add_action( 'template_redirect', [ __CLASS__, 'enforce_email_verification_gate' ], 1 );

        // Main redirect protection
        add_action( 'template_redirect', [ __CLASS__, 'restrict_premium_pages' ], 5 );

        // WooCommerce purchase restriction
        add_filter( 'woocommerce_add_to_cart_validation', [ __CLASS__, 'prevent_non_subscriber_checkout' ], 10, 3 );

        // Redirect verified users to dashboard
        add_action( 'init', [ __CLASS__, 'handle_email_verification_redirect' ] );

        // Persist membership metadata for brand-new signups.
        add_action( 'user_register', [ __CLASS__, 'handle_user_registered' ], 10, 1 );

        // Force email verification redirect after signup/login flows.
        add_filter( 'registration_redirect', [ __CLASS__, 'force_verify_redirect_after_signup' ], PHP_INT_MAX );
        add_filter( 'woocommerce_registration_redirect', [ __CLASS__, 'force_verify_redirect_after_signup' ], PHP_INT_MAX, 2 );

        // Override login redirects to enforce verification first.
        add_filter( 'login_redirect', [ __CLASS__, 'maybe_redirect_unverified_after_login' ], PHP_INT_MAX, 3 );
        add_filter( 'woocommerce_login_redirect', [ __CLASS__, 'maybe_redirect_unverified_after_login' ], PHP_INT_MAX, 2 );
    }

    /**
     * Restrict access to premium CPTs and taxonomies.
     */
    public static function restrict_premium_pages() {
        // Bypass admin, AJAX, REST
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Skip protection for internal system endpoints.
        $allowed_paths = [
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
     * Enforces the verification required holding pattern for unverified users across key routes.
     */
    public static function enforce_email_verification_gate() : void {
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        if ( ! self::should_require_verification() ) {
            return;
        }

        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $bypass_roles = [ 'administrator', 'editor', 'shop_manager' ];

            if ( $user instanceof \WP_User && array_intersect( $bypass_roles, (array) $user->roles ) ) {
                return;
            }
        }

        $status = isset( $_GET['wrpa-verify-status'] ) ? sanitize_key( wp_unslash( $_GET['wrpa-verify-status'] ) ) : '';

        if ( 'success' === $status ) {
            return;
        }

        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        $allowed_paths = [
            '/verify-required/',
            '/wp-login.php',
            '/wp-json/',
            '/wp-admin/admin-ajax.php'
        ];

        foreach ( $allowed_paths as $path ) {
            if ( self::request_matches_path( $request_uri, $path ) ) {
                return;
            }
        }

        $blocked_paths = [
            '/subscribe/',
            '/dashboard/',
            '/wisdom-rain-dashboard/',
            '/my-account/',
            '/account/',
            '/checkout/',
            '/cart/',
        ];

        foreach ( $blocked_paths as $path ) {
            if ( self::request_matches_path( $request_uri, $path ) ) {
                self::maybe_resend_verification_email();
                self::redirect_to_verify_required();
            }
        }

        // Default catch-all: keep user on verify-required if they try to hit other protected pages directly.
        self::redirect_to_verify_required();
    }

    /**
     * Forces redirect to the verification screen immediately after registration.
     *
     * @param string $redirect_url Original redirect destination.
     * @return string
     */
    public static function force_verify_redirect_after_signup( string $redirect_url, $user = null ) : string {
        return self::resolve_verify_required_url();
    }

    /**
     * Ensures unverified accounts are redirected to verification immediately after login.
     *
     * @param mixed ...$args Redirect arguments from various login filters.
     * @return string
     */
    public static function maybe_redirect_unverified_after_login( ...$args ) : string {
        $redirect_to = $args[0] ?? '';

        $user = null;
        foreach ( array_reverse( $args ) as $arg ) {
            if ( $arg instanceof \WP_User ) {
                $user = $arg;
                break;
            }
        }

        if ( ! $user && is_user_logged_in() ) {
            $user = wp_get_current_user();
        }

        if ( $user instanceof \WP_User && self::user_requires_verification( (int) $user->ID ) ) {
            self::maybe_resend_verification_email( (int) $user->ID );
            return self::resolve_verify_required_url();
        }

        return $redirect_to;
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
        $status = isset( $_GET['wrpa-verify-status'] ) ? sanitize_key( wp_unslash( $_GET['wrpa-verify-status'] ) ) : '';

        if ( in_array( $status, [ 'success', 'already-verified' ], true ) ) {
            return;
        }

        if ( isset( $_GET['wrpa_verified'] ) && 'true' === $_GET['wrpa_verified'] && is_user_logged_in() ) {
            $destination = add_query_arg( 'wrpa-verify-status', 'success', self::get_dashboard_url() );

            wp_safe_redirect( $destination );
            exit;
        }
    }

    /**
     * Resolves the canonical dashboard URL for post-verification redirects.
     */
    public static function get_dashboard_url() : string {
        $dashboard = '';

        if ( function_exists( 'wc_get_page_permalink' ) ) {
            $account_url = wc_get_page_permalink( 'myaccount' );

            if ( $account_url ) {
                $dashboard = $account_url;
            }
        }

        if ( ! $dashboard && defined( 'WRPA_DASHBOARD_URL' ) && WRPA_DASHBOARD_URL ) {
            $dashboard = WRPA_DASHBOARD_URL;
        }

        if ( ! $dashboard && class_exists( __NAMESPACE__ . '\WRPA_Core' ) && method_exists( WRPA_Core::class, 'urls' ) ) {
            $urls = WRPA_Core::urls();

            if ( ! empty( $urls['dashboard_url'] ) ) {
                $dashboard = $urls['dashboard_url'];
            }
        }

        if ( ! $dashboard ) {
            $dashboard = site_url( '/wisdom-rain-dashboard/' );
        }

        return apply_filters( 'wrpa_dashboard_redirect_url', $dashboard );
    }

    /**
     * Prevents theme-level template_redirect logic from hijacking verification success redirects.
     */
    public static function maybe_disable_template_redirect_conflicts() : void {
        $status = isset( $_GET['wrpa-verify-status'] ) ? sanitize_key( wp_unslash( $_GET['wrpa-verify-status'] ) ) : '';

        if ( 'success' !== $status ) {
            return;
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';

        if ( '' === $request_uri ) {
            return;
        }

        $request_path = strtok( $request_uri, '?' );

        if ( ! is_string( $request_path ) || '' === $request_path ) {
            return;
        }

        $request_path = trailingslashit( '/' . ltrim( wp_normalize_path( $request_path ), '/' ) );

        $dashboard_path = wp_parse_url( self::get_dashboard_url(), PHP_URL_PATH );

        if ( ! is_string( $dashboard_path ) || '' === $dashboard_path ) {
            return;
        }

        $dashboard_path = trailingslashit( '/' . ltrim( wp_normalize_path( $dashboard_path ), '/' ) );

        if ( $request_path !== $dashboard_path ) {
            return;
        }

        self::disable_non_wrpa_template_redirects();
    }

    /**
     * Removes non-WRPA callbacks from the template_redirect hook for the current request.
     */
    protected static function disable_non_wrpa_template_redirects() : void {
        global $wp_filter;

        if ( empty( $wp_filter['template_redirect'] ) ) {
            return;
        }

        $hook = $wp_filter['template_redirect'];

        if ( $hook instanceof \WP_Hook ) {
            $callbacks = $hook->callbacks;
        } elseif ( is_array( $hook ) ) {
            $callbacks = $hook;
        } else {
            return;
        }

        if ( empty( $callbacks ) ) {
            return;
        }

        $removals = [];

        foreach ( $callbacks as $priority => $functions ) {
            foreach ( $functions as $data ) {
                if ( empty( $data['function'] ) ) {
                    continue;
                }

                $callback = $data['function'];

                if ( self::is_wrpa_callback( $callback ) ) {
                    continue;
                }

                $removals[] = [ $callback, $priority ];
            }
        }

        foreach ( $removals as $removal ) {
            remove_action( 'template_redirect', $removal[0], $removal[1] );
        }
    }

    /**
     * Determines if the provided callback belongs to the WRPA namespace/classes.
     *
     * @param mixed $callback Callback registered on template_redirect.
     */
    protected static function is_wrpa_callback( $callback ) : bool {
        if ( is_array( $callback ) && isset( $callback[0] ) ) {
            $target = $callback[0];

            if ( is_object( $target ) ) {
                $target = get_class( $target );
            }

            if ( is_string( $target ) ) {
                $target = ltrim( $target, '\\' );

                if ( 0 === strpos( $target, __NAMESPACE__ . '\\' ) ) {
                    return true;
                }

                if ( 0 === strpos( $target, 'WRPA_' ) ) {
                    return true;
                }
            }
        }

        if ( is_string( $callback ) ) {
            $function = ltrim( $callback, '\\' );

            if ( 0 === strpos( $function, __NAMESPACE__ . '\\' ) ) {
                return true;
            }

            if ( 0 === strpos( $function, 'WRPA_' ) || 0 === strpos( $function, 'wrpa_' ) ) {
                return true;
            }
        }

        return false;
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

        return self::user_requires_verification( (int) $user_id );
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

        return strpos( $request_path, $path ) === 0;
    }

    /**
     * Redirects the visitor to the verification required holding page.
     */
    protected static function redirect_to_verify_required() : void {
        $destination = self::resolve_verify_required_url();

        wp_safe_redirect( $destination );
        exit;
    }

    /**
     * Determines whether the provided user id requires email verification.
     */
    protected static function user_requires_verification( int $user_id ) : bool {
        if ( ! $user_id || ! class_exists( __NAMESPACE__ . '\WRPA_Email_Verify' ) ) {
            return false;
        }

        return ! WRPA_Email_Verify::is_verified( $user_id );
    }

    /**
     * Resolve the verification required URL considering custom configuration.
     */
    protected static function resolve_verify_required_url() : string {
        $destination = home_url( '/verify-required/' );

        if ( class_exists( __NAMESPACE__ . '\WRPA_Core' ) && method_exists( WRPA_Core::class, 'urls' ) ) {
            $urls = WRPA_Core::urls();

            if ( ! empty( $urls['verify_required_url'] ) ) {
                $destination = $urls['verify_required_url'];
            }
        }

        return apply_filters( 'wrpa_verify_required_url', $destination );
    }

    /**
     * Dispatches the verification email again while respecting per-user rate limits.
     *
     * @param int|null $user_id Optional user id. Defaults to the current user.
     * @return void
     */
    protected static function maybe_resend_verification_email( ?int $user_id = null ) : void {
        if ( ! class_exists( __NAMESPACE__ . '\WRPA_Email' ) ) {
            return;
        }

        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        $user_id = absint( $user_id );

        if ( ! $user_id || ! self::user_requires_verification( $user_id ) ) {
            return;
        }

        WRPA_Email::send_verification( $user_id );
    }
}
