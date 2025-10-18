<?php
/**
 * WRPA - Email Core (Phase III - 4.1)
 * Responsible for HTML email headers, subject/body filters, template rendering,
 * placeholder replacement, and the main send_email() API.
 *
 * @package WisdomRain\PremiumAccess
 */

namespace WRPA;

if ( ! defined( 'ABSPATH' ) ) exit;

class WRPA_Email {

    const META_VERIFIED = '_wrpa_email_verified';
    const META_TOKEN    = '_wrpa_email_token';

    /**
     * Bootstraps filters and actions for email handling.
     */
    public static function init() : void {
        // Ensure HTML emails.
        add_filter( 'wp_mail_content_type', [ __CLASS__, 'force_html_content_type' ] );

        // Default From headers (FluentSMTP will deliver; we standardize here).
        add_filter( 'wp_mail_from', [ __CLASS__, 'mail_from' ] );
        add_filter( 'wp_mail_from_name', [ __CLASS__, 'mail_from_name' ] );

        // Account verification handler.
        add_action( 'init', [ __CLASS__, 'maybe_verify_account' ] );
    }

    /**
     * Global content type for all WRPA emails.
     */
    public static function force_html_content_type( $content_type ) : string {
        return 'text/html; charset=UTF-8';
    }

    /**
     * Standardize the From address.
     */
    public static function mail_from( $email ) : string {
        return apply_filters( 'wrpa_mail_from', 'no-reply@wisdomrainbookmusic.com' );
    }

    /**
     * Standardize the From name.
     */
    public static function mail_from_name( $name ) : string {
        return apply_filters( 'wrpa_mail_from_name', 'Wisdom Rain' );
    }

    /**
     * Main public API for sending an email to a user via a named template slug.
     *
     * @param int    $user_id       WordPress user ID.
     * @param string $template_type Template slug (e.g. 'welcome', 'order-completed').
     * @param array  $data          Extra variables available to the template/subject.
     * @return bool                 True when wp_mail() reports success.
     */
    public static function send_email( int $user_id, string $template_type, array $data = [] ) : bool {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user || empty( $user->user_email ) ) {
            self::log( 'WRPA email send aborted — user missing or email empty.', [ 'user_id' => $user_id ] );
            return false;
        }

        $vars   = array_merge( self::get_user_context( $user_id ), $data );
        $slug   = sanitize_key( $template_type );
        $body   = self::render_template( $slug, $vars );

        if ( '' === $body ) {
            /**
             * Allow implementers to handle missing templates.
             */
            do_action( 'wrpa_email_failed', $user_id, $slug, 'missing_template' );
            self::log( 'WRPA email send aborted — template missing.', [ 'user_id' => $user_id, 'slug' => $slug ] );
            return false;
        }

        $subject = self::subject_for( $slug, $vars );
        $subject = apply_filters( 'wrpa_email_subject', $subject, $slug, $vars );

        // Final HTML body filter.
        $body = apply_filters( 'wrpa_email_body_html', $body, $slug, $vars );

        $to        = $user->user_email;
        $to        = apply_filters( 'wrpa_email_recipients', $to, $slug, $vars );
        $headers   = [];
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        $sent = wp_mail( $to, $subject, $body, $headers );

        if ( $sent ) {
            do_action( 'wrpa_email_sent', $user_id, $slug, [
                'recipient' => $to,
                'subject'   => $subject,
                'vars'      => $vars,
            ] );

            self::log(
                'WRPA email sent.',
                [
                    'user_id'   => $user_id,
                    'slug'      => $slug,
                    'recipient' => $to,
                ]
            );

            return true;
        }

        do_action( 'wrpa_email_failed', $user_id, $slug, 'wp_mail_failed' );
        self::log(
            'WRPA email failed to send.',
            [
                'user_id'   => $user_id,
                'slug'      => $slug,
                'recipient' => $to,
            ]
        );
        return false;
    }

    /**
     * Compute a default subject for a template slug.
     */
    public static function subject_for( string $slug, array $vars ) : string {
        $first = isset( $vars['user_first_name'] ) ? $vars['user_first_name'] : ( $vars['user_name'] ?? '' );
        $order_id = $vars['order_id'] ?? '';
        $expire   = $vars['expire_date_human'] ?? '';

        switch ( $slug ) {
            case 'welcome':
                return sprintf( __( 'Aramıza hoş geldin, %s!', 'wrpa' ), $first );
            case 'order-completed':
                return sprintf( __( 'Siparişiniz tamamlandı • #%s', 'wrpa' ), $order_id );
            case 'sub-expiring-3d':
                return sprintf( __( 'Hatırlatma: Aboneliğiniz %s tarihinde bitiyor', 'wrpa' ), $expire );
            case 'trial-ended-today':
                return __( 'Trial bitti — avantajı kaçırmayın', 'wrpa' );
            case 'sub-expired-3d':
            case 'sub-expired-3m':
            case 'sub-expired-6m':
                return __( 'Hâlâ sensiziz… Geri dönmek ister misiniz?', 'wrpa' );
            case 'campaign-valentines-13feb':
            case 'campaign-newyear-31dec':
            case 'campaign-november':
                return __( 'Kampanya başlıyor!', 'wrpa' );
            case 'blast-now':
                return isset( $vars['custom_message'] ) ? wp_strip_all_tags( (string) $vars['custom_message'] ) : __( 'Duyuru', 'wrpa' );
            case 'email-verify':
                return __( 'E-posta adresinizi doğrulayın', 'wrpa' );
            default:
                return get_bloginfo( 'name' );
        }
    }

    /**
     * Render a template file to HTML and perform placeholder replacement.
     */
    protected static function render_template( string $slug, array $vars ) : string {
        $file = self::locate_template( $slug );
        if ( ! $file || ! file_exists( $file ) ) {
            return '';
        }

        // Make $vars available in template scope.
        extract( [ 'vars' => $vars ], EXTR_SKIP );

        ob_start();
        include $file;
        $html = (string) ob_get_clean();

        return self::replace_placeholders( $html, $vars );
    }

    /**
     * Locate a template with theme override support.
     * Priority:
     *   1) yourtheme/wrpa/emails/{$slug}.html.php
     *   2) plugin/templates/emails/{$slug}.html.php
     */
    public static function locate_template( string $slug ) : ?string {
        $slug    = sanitize_file_name( $slug );
        $theme   = trailingslashit( get_stylesheet_directory() ) . 'wrpa/emails/' . $slug . '.html.php';

        // Plugin base = one level up from /includes/ (this file).
        $plugin_base = trailingslashit( dirname( dirname( __FILE__ ) ) );
        $plugin_tmpl = $plugin_base . 'templates/emails/' . $slug . '.html.php';

        if ( file_exists( $theme ) ) {
            return $theme;
        }
        if ( file_exists( $plugin_tmpl ) ) {
            return $plugin_tmpl;
        }
        return null;
    }

    /**
     * Replace {placeholders} in the rendered HTML with provided variables.
     */
    public static function replace_placeholders( string $html, array $vars ) : string {
        $defaults = [
            'site_name'               => get_bloginfo( 'name' ),
            'site_url'                => home_url( '/' ),
            'dashboard_url'           => home_url( '/my-account/' ),
            'subscribe_url'           => home_url( '/subscribe/' ),
            'manage_subscription_url' => home_url( '/account/subscriptions/' ),
        ];
        $vars = array_merge( $defaults, $vars );

        // Build search/replace arrays for simple {key} tokens.
        $search  = [];
        $replace = [];
        foreach ( $vars as $key => $val ) {
            $search[]  = '{' . $key . '}';
            $replace[] = is_scalar( $val ) ? (string) $val : wp_json_encode( $val );
        }

        return str_replace( $search, $replace, $html );
    }

    /**
     * Compose a user-centric context array for templates.
     */
    public static function get_user_context( int $user_id ) : array {
        $user = get_user_by( 'id', $user_id );
        $first = $user ? get_user_meta( $user_id, 'first_name', true ) : '';

        $context = [
            'user_id'         => $user_id,
            'user_email'      => $user ? $user->user_email : '',
            'user_name'       => $user ? $user->display_name : '',
            'user_first_name' => $first ?: ( $user ? $user->display_name : '' ),
        ];

        // Optional integrations (stubs): plan/expiry/trial could come from WRPA_Access.
        if ( method_exists( '\\WRPA\\WRPA_Access', 'get_user_plan' ) ) {
            $plan = WRPA_Access::get_user_plan( $user_id );
            if ( is_array( $plan ) ) {
                $context['plan_name']     = $plan['name']  ?? '';
                $context['plan_price']    = $plan['price'] ?? '';
                $context['plan_interval'] = $plan['interval'] ?? '';
            }
        }
        if ( method_exists( '\\WRPA\\WRPA_Access', 'get_expire_date' ) ) {
            $exp = WRPA_Access::get_expire_date( $user_id ); // Y-m-d expected
            if ( $exp ) {
                $context['expire_date'] = $exp;
                $context['expire_date_human'] = date_i18n( get_option( 'date_format' ), strtotime( $exp ) );
            }
        }
        if ( method_exists( '\\WRPA\\WRPA_Access', 'get_trial_end' ) ) {
            $trial = WRPA_Access::get_trial_end( $user_id );
            if ( $trial ) {
                $context['trial_end'] = $trial;
            }
        }

        return $context;
    }

    /**
     * Sends an email verification link to the specified user.
     *
     * @param int $user_id User identifier.
     * @return bool Whether a verification email was attempted.
     */
    public static function send_verification( $user_id ) {
        $user_id = absint( $user_id );

        if ( ! $user_id ) {
            self::log( 'WRPA email verification skipped — invalid user id provided.' );
            return false;
        }

        $user = get_user_by( 'id', $user_id );

        if ( ! $user || empty( $user->user_email ) ) {
            self::log(
                'WRPA email verification skipped — user or email address missing.',
                [
                    'user_id' => $user_id,
                ]
            );
            return false;
        }

        $verified_flag = get_user_meta( $user_id, self::META_VERIFIED, true );

        if ( $verified_flag ) {
            self::log(
                'WRPA email verification not sent — user already verified.',
                [ 'user_id' => $user_id ]
            );
            return false;
        }

        if ( ! function_exists( 'wp_generate_password' ) ) {
            self::log( 'WRPA email verification failed — password generator unavailable.', [ 'user_id' => $user_id ] );
            return false;
        }

        $token = wp_generate_password( 32, false, false );

        update_user_meta( $user_id, self::META_TOKEN, $token );
        update_user_meta( $user_id, self::META_VERIFIED, '' );

        $verify_url = add_query_arg( 'wrpa_verify', $token, home_url( '/' ) );

        self::log(
            'WRPA email verification generated.',
            [
                'user_id'    => $user_id,
                'token_hash' => md5( $token ),
            ]
        );

        $sent = self::send_email(
            $user_id,
            'email-verify',
            [
                'verify_url' => $verify_url,
            ]
        );

        if ( $sent ) {
            return true;
        }

        // Fallback to a plain text email if templated delivery failed.
        $subject = __( 'Confirm your email address', 'wrpa' );
        /* translators: %s: verification URL */
        $message = sprintf(
            __( 'Please confirm your email address by visiting the following link: %s', 'wrpa' ),
            esc_url( $verify_url )
        );

        $fallback_sent = wp_mail( $user->user_email, $subject, $message );

        if ( $fallback_sent ) {
            self::log(
                'WRPA email verification email dispatched via fallback.',
                [
                    'user_id' => $user_id,
                    'email'   => $user->user_email,
                ]
            );
            return true;
        }

        self::log(
            'WRPA email verification email failed to send even after fallback.',
            [
                'user_id' => $user_id,
                'email'   => $user->user_email,
            ]
        );

        return false;
    }

    /**
     * Attempts to validate a verification request from the current request.
     *
     * @return void
     */
    public static function maybe_verify_account() {
        if ( empty( $_GET['wrpa_verify'] ) ) {
            return;
        }

        $raw_token = wp_unslash( $_GET['wrpa_verify'] );
        $token     = sanitize_text_field( $raw_token );

        if ( '' === $token ) {
            self::log( 'WRPA email verification rejected — token missing from request.' );
            return;
        }

        $users = get_users(
            [
                'meta_key'   => self::META_TOKEN,
                'meta_value' => $token,
                'fields'     => 'ids',
                'number'     => 1,
            ]
        );

        if ( empty( $users ) ) {
            self::log(
                'WRPA email verification rejected — no matching token found.',
                [ 'token_hash' => md5( $token ) ]
            );
            return;
        }

        $user_id = (int) $users[0];

        update_user_meta( $user_id, self::META_VERIFIED, '1' );
        delete_user_meta( $user_id, self::META_TOKEN );

        self::log(
            'WRPA email verification confirmed.',
            [ 'user_id' => $user_id ]
        );
    }

    /**
     * Proxy logging helper so the email module can share WRPA logging.
     *
     * @param string $message Log entry message.
     * @param array  $context Additional context data.
     * @return void
     */
    protected static function log( $message, array $context = [] ) {
        if ( class_exists( '\\WRPA\\WRPA_Access' ) && method_exists( '\\WRPA\\WRPA_Access', 'log' ) ) {
            WRPA_Access::log( $message, $context );
            return;
        }

        if ( ! empty( $context ) ) {
            $context_string = function_exists( 'wp_json_encode' ) ? wp_json_encode( $context ) : json_encode( $context );

            if ( $context_string ) {
                $message .= ' ' . $context_string;
            }
        }

        if ( function_exists( 'error_log' ) ) {
            error_log( $message );
        }
    }
}

// Expectation: WRPA_Core::init() will call WRPA_Email::init().
