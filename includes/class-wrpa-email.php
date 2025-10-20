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

    /**
     * Bootstraps filters and actions for email handling.
     */
    public static function init() : void {
        // Ensure HTML emails.
        add_filter( 'wp_mail_content_type', [ __CLASS__, 'force_html_content_type' ] );

        // Normalize From headers without duplicating FluentSMTP defaults.
        add_filter( 'wp_mail_from', fn() => apply_filters( 'wrpa_mail_from', 'no-reply@wisdomrainbookmusic.com' ) );
        add_filter( 'wp_mail_from_name', fn() => apply_filters( 'wrpa_mail_from_name', 'Wisdom Rain' ) );

        // Allow other modules to trigger verification emails via action.
        add_action( 'wrpa_send_verification_email', [ __CLASS__, 'send_verification' ], 10, 1 );

        // Dispatch welcome email immediately after verification succeeds.
        add_action( 'wrpa_email_verified', [ __CLASS__, 'send_welcome_after_verification' ], 10, 1 );

    }

    /**
     * Global content type for all WRPA emails.
     */
    public static function force_html_content_type( $content_type ) : string {
        return 'text/html; charset=UTF-8';
    }

    /**
     * Normalized headers shared across all WRPA outbound emails.
     */
    public static function get_headers() : array {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Wisdom Rain <no-reply@wisdomrainbookmusic.com>',
        ];

        return apply_filters( 'wrpa_email_headers', $headers );
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

        if ( class_exists( __NAMESPACE__ . '\\WRPA_Email_Unsubscribe' ) && WRPA_Email_Unsubscribe::is_unsubscribed( $user_id ) ) {
            self::debug_log(
                'Email delivery suppressed — user unsubscribed.',
                [
                    'user_id' => $user_id,
                    'slug'    => $template_type,
                ]
            );

            do_action( 'wrpa_email_suppressed', $user_id, $template_type );

            return false;
        }

        $vars   = array_merge( self::get_user_context( $user_id ), $data );
        $vars   = self::get_placeholder_map( $vars );
        $slug   = sanitize_key( $template_type );
        $body   = self::render_template( $slug, $vars );

        if ( '' === $body ) {
            /**
             * Allow implementers to handle missing templates.
             */
            do_action( 'wrpa_mail_failed', $user_id, $slug, 'missing_template' );
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
        $headers = self::get_headers();

        try {
            $sent = wp_mail( $to, $subject, $body, $headers );
        } catch ( \Throwable $exception ) {
            $error = $exception->getMessage();

            do_action( 'wrpa_mail_failed', $user_id, $slug, $error );
            do_action( 'wrpa_email_failed', $user_id, $slug, 'exception' );

            self::debug_log(
                'Exception thrown while attempting to send email.',
                [
                    'user_id' => $user_id,
                    'slug'    => $slug,
                    'error'   => $error,
                ]
            );

            self::log(
                'WRPA email failed to send.',
                [
                    'user_id'   => $user_id,
                    'slug'      => $slug,
                    'recipient' => $to,
                    'error'     => $error,
                ]
            );

            if ( apply_filters( 'wrpa_enable_email_logging', false ) ) {
                WRPA_Email_Log::log( $user_id, $slug, $to, $subject, 'failed', $error, [ 'vars' => $vars ] );
            }

            return false;
        }

        if ( $sent ) {
            do_action( 'wrpa_mail_sent', $user_id, $slug, $subject );
            do_action( 'wrpa_email_sent', $user_id, $slug, [
                'recipient' => $to,
                'subject'   => $subject,
                'vars'      => $vars,
            ] );

            self::debug_log(
                'Email sent successfully.',
                [
                    'user_id'   => $user_id,
                    'slug'      => $slug,
                    'recipient' => $to,
                ]
            );

            self::log(
                'WRPA email sent.',
                [
                    'user_id'   => $user_id,
                    'slug'      => $slug,
                    'recipient' => $to,
                ]
            );

            if ( apply_filters( 'wrpa_enable_email_logging', false ) ) {
                WRPA_Email_Log::log( $user_id, $slug, $to, $subject, 'sent', '', [ 'vars' => $vars ] );
            }

            return true;
        }

        $error = 'wp_mail_failed';

        do_action( 'wrpa_mail_failed', $user_id, $slug, $error );
        do_action( 'wrpa_email_failed', $user_id, $slug, $error );

        self::debug_log(
            'wp_mail() returned false.',
            [
                'user_id'   => $user_id,
                'slug'      => $slug,
                'recipient' => $to,
            ]
        );

        self::log(
            'WRPA email failed to send.',
            [
                'user_id'   => $user_id,
                'slug'      => $slug,
                'recipient' => $to,
                'error'     => $error,
            ]
        );

        if ( apply_filters( 'wrpa_enable_email_logging', false ) ) {
            WRPA_Email_Log::log( $user_id, $slug, $to, $subject, 'failed', $error, [ 'vars' => $vars ] );
        }
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
     * Builds the placeholder map merged with defaults and sanitized URLs.
     *
     * @param array<string,mixed> $vars Runtime variables to merge.
     * @return array<string,mixed>
     */
    public static function get_placeholder_map( array $vars = [] ) : array {
        $defaults = [
            'site_name'     => get_bloginfo( 'name' ),
            'site_url'      => home_url( '/' ),
            'support_email' => 'support@wisdomrainbookmusic.com',
        ];

        $user_id = isset( $vars['user_id'] ) ? absint( $vars['user_id'] ) : 0;

        $url_defaults = [
            'subscribe_url'           => home_url( '/subscribe/' ),
            'dashboard_url'           => home_url( '/dashboard/' ),
            'manage_subscription_url' => home_url( '/account/subscriptions/' ),
            'verify_email_url'        => $user_id ? WRPA_Email_Verify::get_verify_url( $user_id ) : home_url( '/wisdom-rain-dashboard/' ),
        ];

        if ( class_exists( __NAMESPACE__ . '\\WRPA_Core' ) && method_exists( WRPA_Core::class, 'urls' ) ) {
            $url_defaults = array_merge( $url_defaults, WRPA_Core::urls() );
        }

        $account_url = $url_defaults['account_url'] ?? '';

        if ( class_exists( __NAMESPACE__ . '\\WRPA_Urls' ) ) {
            $account_url = WRPA_Urls::account_url();
        }

        if ( '' === $account_url ) {
            $account_url = 'https://wisdomrainbookmusic.com/my-account/';
        }

        $url_defaults['account_url'] = $account_url;

        $unsubscribe_base = $url_defaults['unsubscribe_url'] ?? '';

        if ( class_exists( __NAMESPACE__ . '\\WRPA_Urls' ) ) {
            $unsubscribe_base = WRPA_Urls::unsubscribe_base_url();
        }

        if ( '' === $unsubscribe_base ) {
            $unsubscribe_base = 'https://wisdomrainbookmusic.com/unsubscribe/';
        }

        if ( class_exists( __NAMESPACE__ . '\\WRPA_Email_Unsubscribe' ) ) {
            $url_defaults['unsubscribe_url'] = $user_id
                ? WRPA_Email_Unsubscribe::signed_url_for( $user_id, $unsubscribe_base )
                : $unsubscribe_base;
        } else {
            $url_defaults['unsubscribe_url'] = $unsubscribe_base;
        }

        $map = array_merge( $defaults, $url_defaults, $vars );

        foreach ( $map as $key => $value ) {
            if ( is_string( $value ) && substr( $key, -4 ) === '_url' ) {
                $map[ $key ] = esc_url( $value );
            }
        }

        return $map;
    }

    /**
     * Replace {placeholders} in the rendered HTML with provided variables.
     */
    public static function replace_placeholders( string $html, array $vars ) : string {
        $vars = self::get_placeholder_map( $vars );

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

        $access_defaults = [
            'plan_name'          => '',
            'plan_price'         => '',
            'plan_interval'      => '',
            'expire_date'        => '',
            'expire_date_human'  => '',
            'trial_end'          => '',
        ];

        $context = array_merge( $context, $access_defaults );

        if ( class_exists( __NAMESPACE__ . '\\WRPA_Access' ) ) {
            if ( method_exists( WRPA_Access::class, 'get_user_plan' ) ) {
                $plan = WRPA_Access::get_user_plan( $user_id );
                if ( is_array( $plan ) ) {
                    $context['plan_name']     = $plan['name'] ?? $context['plan_name'];
                    $context['plan_price']    = $plan['price'] ?? $context['plan_price'];
                    $context['plan_interval'] = $plan['interval'] ?? $context['plan_interval'];
                }
            }

            if ( method_exists( WRPA_Access::class, 'get_expire_date' ) ) {
                $exp = WRPA_Access::get_expire_date( $user_id );
                if ( $exp ) {
                    $context['expire_date'] = $exp;

                    $timestamp = strtotime( $exp );
                    if ( $timestamp ) {
                        $context['expire_date_human'] = date_i18n( get_option( 'date_format' ), $timestamp );
                    }
                }
            }

            if ( method_exists( WRPA_Access::class, 'get_trial_end' ) ) {
                $trial = WRPA_Access::get_trial_end( $user_id );
                if ( $trial ) {
                    $context['trial_end'] = $trial;
                }
            }
        }

        return $context;
    }

    /**
     * Sends an email verification link to the specified user.
     *
     * @param int $user_id User identifier.
     * @return bool
     */
    public static function send_verification( $user_id, bool $force = false ) : bool {
        $user_id = absint( $user_id );

        if ( ! $user_id ) {
            return false;
        }

        $user = get_userdata( $user_id );

        if ( ! $user || empty( $user->user_email ) ) {
            return false;
        }

        if ( class_exists( __NAMESPACE__ . '\WRPA_Email_Verify' ) ) {
            $verified_flag = get_user_meta( $user_id, WRPA_Email_Verify::META_FLAG, true );

            if ( $verified_flag ) {
                return false;
            }

            $last_sent = (int) get_user_meta( $user_id, WRPA_Email_Verify::META_LAST_SENT, true );

            if ( ! $force && $last_sent && ( time() - $last_sent ) < 120 ) {
                return false;
            }

            $verify_url = WRPA_Email_Verify::get_verify_url( $user_id );

            if ( '' === $verify_url ) {
                return false;
            }
        } else {
            $verify_url = home_url( '/' );
        }

        $sent = self::send_email(
            $user_id,
            'email-verify',
            [
                'verify_email_url' => $verify_url,
            ]
        );

        if ( $sent && class_exists( __NAMESPACE__ . '\WRPA_Email_Verify' ) ) {
            update_user_meta( $user_id, WRPA_Email_Verify::META_LAST_SENT, time() );
        }

        return $sent;
    }

    /**
     * Sends the welcome email immediately after a user verifies their address.
     */
    public static function send_welcome_after_verification( $user_id ) : void {
        $user_id = absint( $user_id );

        if ( ! $user_id ) {
            return;
        }

        self::send_email( $user_id, 'welcome' );
    }

    /**
     * Writes detailed mail logs to wp-content/debug.log for transport debugging.
     */
    public static function debug_log( $message, array $context = [] ) : void {
        if ( ! defined( 'WP_CONTENT_DIR' ) || ! is_dir( WP_CONTENT_DIR ) ) {
            return;
        }

        $entry = '[WRPA_MAIL] ' . $message;

        if ( ! empty( $context ) ) {
            $context_string = function_exists( 'wp_json_encode' ) ? wp_json_encode( $context ) : json_encode( $context );

            if ( $context_string ) {
                $entry .= ' ' . $context_string;
            }
        }

        $file = WP_CONTENT_DIR . '/debug.log';

        if ( file_exists( $file ) && ! is_writable( $file ) ) {
            return;
        }

        if ( ! file_exists( $file ) && ! is_writable( WP_CONTENT_DIR ) ) {
            return;
        }

        file_put_contents( $file, $entry . PHP_EOL, FILE_APPEND | LOCK_EX );
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
