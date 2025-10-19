<?php
/**
 * WRPA - Access Module
 *
 * @package WisdomRain\PremiumAccess
 */

namespace WRPA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Controls content gating and membership validation for WRPA.
 */
class WRPA_Access {
    const META_RESTRICTION_ENABLED = '_wrpa_restriction_enabled';
    const META_REQUIRED_PLAN       = '_wrpa_required_plan';
    const META_CUSTOM_MESSAGE      = '_wrpa_restriction_message';

    const USER_PLAN_META           = '_wrpa_membership_plan';
    const USER_EXPIRY_META         = '_wrpa_membership_expiry';
    const USER_ACCESS_EXPIRES_META = '_wrpa_access_expires';
    const USER_TRIAL_END_META      = '_wrpa_trial_end';
    const USER_TRIAL_FINGERPRINT_META = 'wrpa_trial_fingerprint';

    const SHORTCODE_RESTRICTED = 'wrpa_restricted';

    const OPTION_TRIAL_DEVICE_HASHES = 'wrpa_trial_device_hashes';
    const TRIAL_DEVICE_STORE_LIMIT   = 500;

    /**
     * Wires the module into WordPress.
     *
     * @return void
     */
    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_post_meta' ] );
        add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_box' ] );
        add_action( 'save_post', [ __CLASS__, 'save_restriction_settings' ], 10, 2 );
        add_filter( 'the_content', [ __CLASS__, 'filter_restricted_content' ] );
        add_shortcode( self::SHORTCODE_RESTRICTED, [ __CLASS__, 'render_restricted_shortcode' ] );
        add_action( 'template_redirect', [ __CLASS__, 'handle_access' ], 5 );
        add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'grant_access' ] );
        add_action( 'woocommerce_payment_complete', [ __CLASS__, 'grant_access' ] );
    }

    /**
     * Registers meta keys used to store restriction data.
     *
     * @return void
     */
    public static function register_post_meta() {
        $post_types = self::get_restriction_post_types();

        foreach ( (array) $post_types as $post_type ) {
            register_post_meta(
                $post_type,
                self::META_RESTRICTION_ENABLED,
                [
                    'type'              => 'string',
                    'single'            => true,
                    'show_in_rest'      => false,
                    'sanitize_callback' => [ __CLASS__, 'sanitize_boolean_flag' ],
                    'auth_callback'     => [ __CLASS__, 'can_manage_restrictions' ],
                ]
            );

            register_post_meta(
                $post_type,
                self::META_REQUIRED_PLAN,
                [
                    'type'              => 'string',
                    'single'            => true,
                    'show_in_rest'      => false,
                    'sanitize_callback' => [ __CLASS__, 'sanitize_plan_list' ],
                    'auth_callback'     => [ __CLASS__, 'can_manage_restrictions' ],
                ]
            );

            register_post_meta(
                $post_type,
                self::META_CUSTOM_MESSAGE,
                [
                    'type'              => 'string',
                    'single'            => true,
                    'show_in_rest'      => false,
                    'sanitize_callback' => [ __CLASS__, 'sanitize_custom_message' ],
                    'auth_callback'     => [ __CLASS__, 'can_manage_restrictions' ],
                ]
            );
        }
    }

    /**
     * Determines whether the current user can manage access restrictions.
     *
     * @return bool
     */
    public static function can_manage_restrictions( ...$args ) {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Sanitizes checkbox style flags stored as strings.
     *
     * @param mixed $value Submitted value.
     * @return string
     */
    public static function sanitize_boolean_flag( $value, $meta_key = '', $object_type = '' ) {
        return $value ? '1' : '';
    }

    /**
     * Sanitizes the plan list value saved in post meta.
     *
     * @param mixed $value Submitted value.
     * @return string
     */
    public static function sanitize_plan_list( $value, $meta_key = '', $object_type = '' ) {
        $plans = self::parse_plan_list( $value );

        return implode( "\n", $plans );
    }

    /**
     * Sanitizes the custom restriction message before storing it.
     *
     * @param mixed $value Submitted value.
     * @return string
     */
    public static function sanitize_custom_message( $value, $meta_key = '', $object_type = '' ) {
        return wp_kses_post( $value );
    }

    /**
     * Registers the access restriction meta box on supported post types.
     *
     * @return void
     */
    public static function register_meta_box() {
        $post_types = self::get_restriction_post_types();

        foreach ( (array) $post_types as $post_type ) {
            add_meta_box(
                'wrpa-access-restrictions',
                __( 'WRPA Access Restrictions', 'wrpa' ),
                [ __CLASS__, 'render_meta_box' ],
                $post_type,
                'side'
            );
        }
    }

    /**
     * Outputs the access restriction meta box UI.
     *
     * @param \WP_Post $post Current post instance.
     * @return void
     */
    public static function render_meta_box( $post ) {
        wp_nonce_field( 'wrpa_save_restrictions', 'wrpa_restrictions_nonce' );

        $settings = self::get_restriction_settings( $post->ID );
        $enabled  = ! empty( $settings['enabled'] );
        $plans    = $settings['plan'];
        $message  = $settings['message'];
        ?>
        <p>
            <label for="wrpa_restriction_enabled">
                <input type="checkbox" id="wrpa_restriction_enabled" name="wrpa_restriction_enabled" value="1" <?php checked( $enabled ); ?> />
                <?php esc_html_e( 'Restrict this content to active subscribers', 'wrpa' ); ?>
            </label>
        </p>
        <p>
            <label for="wrpa_required_plan" style="font-weight:600; display:block; margin-bottom:4px;">
                <?php esc_html_e( 'Required plan(s)', 'wrpa' ); ?>
            </label>
            <textarea id="wrpa_required_plan" name="wrpa_required_plan" rows="3" style="width:100%;" placeholder="Premium&#10;Gold"><?php echo esc_textarea( $plans ); ?></textarea>
        </p>
        <p class="description">
            <?php esc_html_e( 'Enter one plan per line. Leave empty to allow any active subscription.', 'wrpa' ); ?>
        </p>
        <p>
            <label for="wrpa_restriction_message" style="font-weight:600; display:block; margin-bottom:4px;">
                <?php esc_html_e( 'Custom denial message', 'wrpa' ); ?>
            </label>
            <textarea id="wrpa_restriction_message" name="wrpa_restriction_message" rows="4" style="width:100%;" placeholder="<?php esc_attr_e( 'Please upgrade your plan to access this content.', 'wrpa' ); ?>"><?php echo esc_textarea( $message ); ?></textarea>
        </p>
        <p class="description">
            <?php esc_html_e( 'HTML is allowed. Leave empty to use the default message.', 'wrpa' ); ?>
        </p>
        <?php
    }

    /**
     * Persists restriction settings when a post is saved.
     *
     * @param int      $post_id Post identifier.
     * @param \WP_Post $post    Post object.
     * @return void
     */
    public static function save_restriction_settings( $post_id, $post ) {
        if ( ! isset( $_POST['wrpa_restrictions_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_key( $_POST['wrpa_restrictions_nonce'] ), 'wrpa_save_restrictions' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( 'revision' === $post->post_type ) {
            return;
        }

        if ( ! self::can_manage_restrictions() || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $post_types = self::get_restriction_post_types();
        if ( ! in_array( $post->post_type, $post_types, true ) ) {
            return;
        }

        $enabled = isset( $_POST['wrpa_restriction_enabled'] ) ? '1' : '';
        $plans   = isset( $_POST['wrpa_required_plan'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wrpa_required_plan'] ) ) : '';
        $message = isset( $_POST['wrpa_restriction_message'] ) ? wp_kses_post( wp_unslash( $_POST['wrpa_restriction_message'] ) ) : '';

        self::update_meta_value( $post_id, self::META_RESTRICTION_ENABLED, $enabled );
        self::update_meta_value( $post_id, self::META_REQUIRED_PLAN, self::sanitize_plan_list( $plans ) );
        self::update_meta_value( $post_id, self::META_CUSTOM_MESSAGE, $message );
    }

    /**
     * Updates or removes a meta value depending on its content.
     *
     * @param int    $post_id  Post identifier.
     * @param string $meta_key Meta key.
     * @param string $value    Meta value.
     * @return void
     */
    protected static function update_meta_value( $post_id, $meta_key, $value ) {
        if ( '' === $value ) {
            delete_post_meta( $post_id, $meta_key );
            return;
        }

        update_post_meta( $post_id, $meta_key, $value );
    }

    /**
     * Retrieves the list of post types that support WRPA restriction settings.
     *
     * @return array
     */
    protected static function get_restriction_post_types() {
        $defaults = [
            'post',
            'page',
            'library',
            'music',
            'meditation',
            'sleep_story',
            'magazine',
            'children_story', // Optional premium content; can be toggled in future phases.
        ];

        $post_types = apply_filters( 'wrpa/restriction_post_types', $defaults );

        return self::normalize_post_type_list( $post_types );
    }

    /**
     * Returns the post types that should trigger the premium access checks.
     *
     * @return array
     */
    protected static function get_protected_post_types() {
        $post_types = apply_filters( 'wrpa/protected_post_types', self::get_restriction_post_types() );

        return self::normalize_post_type_list( $post_types );
    }

    /**
     * Normalizes a list of post types.
     *
     * @param array $post_types Raw post type values.
     * @return array
     */
    protected static function normalize_post_type_list( $post_types ) {
        $post_types = array_filter( array_map( 'sanitize_key', (array) $post_types ) );

        return array_values( array_unique( $post_types ) );
    }

    /**
     * Determines whether the current request targets premium protected content.
     *
     * @param array $post_types Post types requiring premium access.
     * @return bool
     */
    protected static function is_protected_request( array $post_types ) {
        if ( empty( $post_types ) ) {
            return false;
        }

        if ( is_singular( $post_types ) ) {
            return true;
        }

        if ( is_post_type_archive( $post_types ) ) {
            return true;
        }

        $queried_object = get_queried_object();
        if ( $queried_object instanceof \WP_Post && in_array( $queried_object->post_type, $post_types, true ) ) {
            return true;
        }

        return false;
    }

    /**
     * Filters post content for visitors without the required membership.
     *
     * @param string $content Original post content.
     * @return string
     */
    public static function filter_restricted_content( $content ) {
        if ( is_admin() || is_feed() ) {
            return $content;
        }

        if ( ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        $post = get_post();
        if ( ! $post ) {
            return $content;
        }

        $settings = self::get_restriction_settings( $post->ID );
        if ( ! $settings['enabled'] ) {
            return $content;
        }

        if ( self::user_has_access( $post->ID ) ) {
            return $content;
        }

        return self::get_restriction_message( $settings, $post->ID );
    }

    /**
     * Entry point for access enforcement during template rendering.
     *
     * Ensures administrators bypass restrictions before delegating to the
     * redirect logic that enforces membership requirements.
     *
     * @return void
     */
    public static function handle_access() {
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( self::is_checkout_or_account_context() ) {
            return;
        }

        $protected_post_types = self::get_protected_post_types();
        $is_protected_request = self::is_protected_request( $protected_post_types );

        if ( $is_protected_request ) {
            $current_user_id = get_current_user_id();

            if ( ! is_user_logged_in() || self::subscription_has_expired( $current_user_id ) ) {
                $destination  = home_url( '/subscribe/' );
                $current_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';

                if ( untrailingslashit( $current_path ) !== '/subscribe' ) {
                    wp_safe_redirect( $destination );
                    exit;
                }
            }
        }

        self::maybe_redirect_restricted_content();
    }

    /**
     * Redirects visitors away from restricted posts before templates render.
     *
     * @return void
     */
    public static function maybe_redirect_restricted_content() {
        if ( is_admin() || is_feed() ) {
            return;
        }

        if ( self::is_checkout_or_account_context() ) {
            return;
        }

        if ( ! is_singular() ) {
            return;
        }

        $post = get_queried_object();
        if ( ! $post || empty( $post->ID ) ) {
            return;
        }

        $settings = self::get_restriction_settings( $post->ID );
        if ( ! $settings['enabled'] ) {
            return;
        }

        if ( self::can_access( $post->ID ) ) {
            return;
        }

        $destination  = home_url( '/subscribe/' );
        $current_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';

        if ( untrailingslashit( $current_path ) === '/subscribe' ) {
            return;
        }

        wp_safe_redirect( $destination );
        exit;
    }

    /**
     * Determines whether the current request is part of the checkout, cart or account flow.
     *
     * @return bool
     */
    protected static function is_checkout_or_account_context() {
        if ( function_exists( 'is_checkout' ) && is_checkout() ) {
            return true;
        }

        if ( function_exists( 'is_cart' ) && is_cart() ) {
            return true;
        }

        if ( function_exists( 'is_account_page' ) && is_account_page() ) {
            return true;
        }

        if ( function_exists( 'is_page' ) && is_page( [ 'checkout', 'cart', 'my-account' ] ) ) {
            return true;
        }

        return false;
    }

    /**
     * Shortcode handler for [wrpa_restricted].
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Restricted content.
     * @return string
     */
    public static function render_restricted_shortcode( $atts, $content = '' ) {
        $atts = shortcode_atts(
            [
                'plan'    => '',
                'message' => '',
            ],
            $atts,
            self::SHORTCODE_RESTRICTED
        );

        if ( self::user_meets_plan_requirement( $atts['plan'] ) ) {
            return do_shortcode( $content );
        }

        $settings = [
            'enabled' => true,
            'plan'    => $atts['plan'],
            'message' => $atts['message'],
        ];

        return self::get_restriction_message( $settings, get_the_ID() );
    }

    /**
     * Retrieves the restriction settings for a post.
     *
     * @param int $post_id Post identifier.
     * @return array
     */
    public static function get_restriction_settings( $post_id ) {
        $enabled = '1' === get_post_meta( $post_id, self::META_RESTRICTION_ENABLED, true );
        $plan    = (string) get_post_meta( $post_id, self::META_REQUIRED_PLAN, true );
        $message = (string) get_post_meta( $post_id, self::META_CUSTOM_MESSAGE, true );

        return [
            'enabled' => $enabled,
            'plan'    => $plan,
            'message' => $message,
        ];
    }

    /**
     * Checks whether a user has access to the given post.
     *
     * @param int      $post_id Post identifier.
     * @param int|null $user_id Optional user identifier.
     * @return bool
     */
    public static function user_has_access( $post_id, $user_id = null ) {
        $settings = self::get_restriction_settings( $post_id );

        if ( ! $settings['enabled'] ) {
            return true;
        }

        return self::user_meets_plan_requirement( $settings['plan'], $user_id );
    }

    /**
     * Public helper used by access control hooks to determine availability.
     *
     * @param int      $post_id Post identifier.
     * @param int|null $user_id Optional user identifier.
     * @return bool
     */
    public static function can_access( $post_id, $user_id = null ) {
        if ( null !== $user_id ) {
            if ( user_can( $user_id, 'manage_options' ) ) {
                return true;
            }
        } elseif ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        $checked_user_id = $user_id;

        if ( null === $checked_user_id ) {
            $checked_user_id = get_current_user_id();
        }

        if ( ! $checked_user_id ) {
            return false;
        }

        if ( self::subscription_has_expired( $checked_user_id ) ) {
            return false;
        }

        $verification_meta_key = class_exists( '\\WRPA\\WRPA_Email_Verify' ) ? \WRPA\WRPA_Email_Verify::META_FLAG : 'wrpa_email_verified';
        $verified_flag         = get_user_meta( $checked_user_id, $verification_meta_key, true );

        if ( '1' !== (string) $verified_flag ) {
            if ( method_exists( __CLASS__, 'log' ) ) {
                self::log(
                    'WRPA access denied — email address not verified.',
                    [ 'user_id' => $checked_user_id ]
                );
            }

            return false;
        }

        return self::user_has_access( $post_id, $checked_user_id );
    }

    /**
     * Ensures that the user has an active membership and the required plan.
     *
     * @param string   $required_plans Required plan list (newline or comma separated).
     * @param int|null $user_id        Optional user identifier.
     * @return bool
     */
    public static function user_meets_plan_requirement( $required_plans, $user_id = null ) {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( $user_id && user_can( $user_id, 'manage_options' ) ) {
            return true;
        }

        $membership = $user_id ? self::get_user_membership( $user_id ) : null;
        if ( ! $membership || ! self::user_membership_is_active( $membership ) ) {
            return false;
        }

        $required = self::parse_plan_list( $required_plans, true );
        if ( empty( $required ) ) {
            return true;
        }

        $current_plan = strtolower( $membership['plan'] );

        return in_array( $current_plan, $required, true );
    }

    /**
     * Retrieves membership data for a user.
     *
     * @param int $user_id User identifier.
     * @return array|null
     */
    public static function get_user_membership( $user_id ) {
        $plan   = get_user_meta( $user_id, self::USER_PLAN_META, true );
        $expiry = get_user_meta( $user_id, self::USER_EXPIRY_META, true );

        if ( empty( $plan ) && empty( $expiry ) ) {
            return null;
        }

        return [
            'plan'   => is_string( $plan ) ? trim( $plan ) : '',
            'expiry' => $expiry ? (int) $expiry : 0,
        ];
    }

    /**
     * Retrieves the stored plan details for the user.
     *
     * @param int $user_id User identifier.
     * @return array<string,mixed>
     */
    public static function get_user_plan( $user_id ) {
        $user_id  = absint( $user_id );
        $plan_key = $user_id ? get_user_meta( $user_id, self::USER_PLAN_META, true ) : '';

        if ( ! $plan_key ) {
            return [];
        }

        $plan_key = sanitize_key( $plan_key );
        $labels   = [
            'trial'   => __( 'Trial', 'wrpa' ),
            'monthly' => __( 'Monthly', 'wrpa' ),
            'yearly'  => __( 'Yearly', 'wrpa' ),
        ];

        return [
            'key'      => $plan_key,
            'name'     => $labels[ $plan_key ] ?? ucwords( str_replace( '-', ' ', $plan_key ) ),
            'interval' => $plan_key,
        ];
    }

    /**
     * Returns the expiry date in Y-m-d format for the provided user.
     *
     * @param int $user_id User identifier.
     * @return string
     */
    public static function get_expire_date( $user_id ) {
        $timestamp = get_user_meta( $user_id, self::USER_ACCESS_EXPIRES_META, true );
        $timestamp = (int) $timestamp;

        if ( $timestamp <= 0 ) {
            return '';
        }

        return self::format_timestamp_as_date( $timestamp );
    }

    /**
     * Returns the recorded trial end date for the provided user.
     *
     * @param int $user_id User identifier.
     * @return string
     */
    public static function get_trial_end( $user_id ) {
        $timestamp = get_user_meta( $user_id, self::USER_TRIAL_END_META, true );
        $timestamp = (int) $timestamp;

        if ( $timestamp <= 0 ) {
            return '';
        }

        return self::format_timestamp_as_date( $timestamp );
    }

    /**
     * Returns user IDs whose subscriptions expire in the provided number of days.
     *
     * @param int $days  Days in the future (0 = today).
     * @param int $limit Maximum number of user IDs to return.
     * @return array<int,int>
     */
    public static function get_users_expiring_in( $days, $limit = 200 ) {
        $limit = (int) $limit;

        if ( $limit <= 0 || ! function_exists( 'get_users' ) ) {
            return [];
        }

        $timezone = self::get_timezone();
        $base     = new \DateTimeImmutable( 'now', $timezone );
        $modifier = sprintf( '%+d days', (int) $days );
        $target   = $base->setTime( 0, 0, 0 )->modify( $modifier );
        $range    = self::get_day_range( $target );

        return self::query_users_by_meta_range( self::USER_ACCESS_EXPIRES_META, $range['start'], $range['end'], $limit );
    }

    /**
     * Returns user IDs whose subscriptions expired on the day calculated by the modifier.
     *
     * @param string $modifier Date modify string, e.g. '-3 days', '-3 months'.
     * @param int    $limit    Maximum number of user IDs to return.
     * @return array<int,int>
     */
    public static function get_users_expired_since( $modifier, $limit = 200 ) {
        $limit = (int) $limit;

        if ( $limit <= 0 || ! function_exists( 'get_users' ) ) {
            return [];
        }

        $timezone = self::get_timezone();

        try {
            $base   = new \DateTimeImmutable( 'now', $timezone );
            $target = $base->setTime( 0, 0, 0 )->modify( (string) $modifier );
        } catch ( \Exception $e ) {
            return [];
        }

        $range = self::get_day_range( $target );

        return self::query_users_by_meta_range( self::USER_ACCESS_EXPIRES_META, $range['start'], $range['end'], $limit );
    }

    /**
     * Returns user IDs whose trial ends on the specified day.
     *
     * @param string $when  Relative day specification (e.g. 'today', '+1 day').
     * @param int    $limit Maximum number of user IDs to return.
     * @return array<int,int>
     */
    public static function get_users_with_trial_end( $when = 'today', $limit = 200 ) {
        $limit = (int) $limit;

        if ( $limit <= 0 || ! function_exists( 'get_users' ) ) {
            return [];
        }

        $timezone = self::get_timezone();

        try {
            $base   = new \DateTimeImmutable( 'now', $timezone );
            $target = ( 'today' === $when ) ? $base : $base->modify( (string) $when );
            $target = $target->setTime( 0, 0, 0 );
        } catch ( \Exception $e ) {
            return [];
        }

        $range = self::get_day_range( $target );

        return self::query_users_by_meta_range( self::USER_TRIAL_END_META, $range['start'], $range['end'], $limit );
    }

    /**
     * Returns active subscriber IDs (expiry in the future or no expiry).
     *
     * @param int $limit Maximum number of user IDs to return.
     * @return array<int,int>
     */
    public static function get_active_user_ids( $limit = 200 ) {
        $limit = (int) $limit;

        if ( $limit <= 0 || ! function_exists( 'get_users' ) ) {
            return [];
        }

        $now = (int) current_time( 'timestamp' );

        $users = get_users(
            [
                'number'     => $limit,
                'fields'     => 'ids',
                'meta_query' => [
                    'relation' => 'OR',
                    [
                        'key'     => self::USER_ACCESS_EXPIRES_META,
                        'value'   => $now,
                        'compare' => '>=',
                        'type'    => 'NUMERIC',
                    ],
                    [
                        'key'     => self::USER_ACCESS_EXPIRES_META,
                        'value'   => 0,
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ],
                    [
                        'key'     => self::USER_ACCESS_EXPIRES_META,
                        'compare' => 'NOT EXISTS',
                    ],
                ],
            ]
        );

        return self::normalize_user_ids( $users );
    }

    /**
     * Determines if a membership is active based on the expiry timestamp.
     *
     * @param array $membership Membership data array.
     * @return bool
     */
    public static function user_membership_is_active( $membership ) {
        if ( empty( $membership['plan'] ) ) {
            return false;
        }

        $expiry = isset( $membership['expiry'] ) ? (int) $membership['expiry'] : 0;

        if ( $expiry <= 0 ) {
            return true;
        }

        return time() <= $expiry;
    }

    /**
     * Determines whether the user's subscription has expired based on the access meta.
     *
     * @param int $user_id User identifier.
     * @return bool
     */
    protected static function subscription_has_expired( $user_id ) {
        if ( ! $user_id ) {
            return true;
        }

        $expiry = get_user_meta( $user_id, self::USER_ACCESS_EXPIRES_META, true );

        if ( '' === $expiry || null === $expiry ) {
            return false;
        }

        $expiry = (int) $expiry;

        if ( $expiry <= 0 ) {
            return false;
        }

        return time() > $expiry;
    }

    /**
     * Builds the restriction denial message.
     *
     * @param array $settings Restriction settings.
     * @param int   $post_id  Post identifier.
     * @return string
     */
    protected static function get_restriction_message( $settings, $post_id ) {
        $message       = $settings['message'];
        $uses_default  = '' === $message;

        if ( $uses_default ) {
            $message = self::get_default_message( $settings['plan'], $post_id );
        } else {
            $message = wpautop( $message );
        }

        /**
         * Filters the rendered restriction message.
         *
         * @param string $message  The rendered message HTML.
         * @param array  $settings Restriction settings.
         * @param int    $post_id  Post identifier.
         */
        return apply_filters( 'wrpa/restriction_message', $message, $settings, $post_id );
    }

    /**
     * Generates the default restriction denial message.
     *
     * @param string $required_plans Required plans string.
     * @param int    $post_id        Post identifier.
     * @return string
     */
    protected static function get_default_message( $required_plans, $post_id ) {
        $plans = self::parse_plan_list( $required_plans, false );

        if ( ! empty( $plans ) ) {
            $plan_label = implode( ', ', $plans );
            $plan_label = sprintf( __( '%s plan subscribers', 'wrpa' ), $plan_label );
        } else {
            $plan_label = __( 'active subscribers', 'wrpa' );
        }

        $redirect    = $post_id ? get_permalink( $post_id ) : home_url();
        $login_url   = wp_login_url( $redirect );
        $account_url = apply_filters( 'wrpa/account_url', wp_login_url() );

        $message  = '<div class="wrpa-restricted-message">';
        $message .= sprintf(
            /* translators: %s: Required plan names. */
            esc_html__( 'This content is available to %s only.', 'wrpa' ),
            esc_html( $plan_label )
        );
        $message .= ' ';
        $message .= sprintf(
            '<a href="%1$s">%2$s</a> %3$s',
            esc_url( $login_url ),
            esc_html__( 'Log in', 'wrpa' ),
            esc_html__( 'or upgrade your subscription to continue.', 'wrpa' )
        );

        if ( $account_url && $account_url !== $login_url ) {
            $message .= ' ';
            $message .= sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url( $account_url ),
                esc_html__( 'Manage subscription', 'wrpa' )
            );
        }

        $message .= '</div>';

        return $message;
    }

    /**
     * Parses a newline or comma separated list of plans.
     *
     * @param string $value            Raw plan string.
     * @param bool   $normalize_for_db Whether to normalize for comparisons.
     * @return array
     */
    protected static function parse_plan_list( $value, $normalize_for_db = false ) {
        $parts = preg_split( '/[\r\n,]+/', (string) $value );
        $parts = array_map( 'trim', (array) $parts );
        $parts = array_map( 'sanitize_text_field', $parts );
        $parts = array_filter(
            $parts,
            static function ( $plan ) {
                return '' !== $plan;
            }
        );

        if ( $normalize_for_db ) {
            $parts = array_map( 'strtolower', $parts );
        }

        return array_values( array_unique( $parts ) );
    }

    /**
     * Retrieves configured plan/product mappings from stored options.
     *
     * @return array
     */
    protected static function get_plan_configurations() {
        $option_map = [
            'trial'   => [
                'product_id' => 'wrpa_plan_trial_id',
                'days'       => 'wrpa_trial_days',
            ],
            'monthly' => [
                'product_id' => 'wrpa_plan_monthly_id',
                'days'       => 'wrpa_monthly_days',
            ],
            'yearly'  => [
                'product_id' => 'wrpa_plan_yearly_id',
                'days'       => 'wrpa_yearly_days',
            ],
        ];

        $plans = [];

        foreach ( $option_map as $plan_key => $settings_keys ) {
            $plans[ $plan_key ] = [
                'product_id' => absint( get_option( $settings_keys['product_id'], 0 ) ),
                'days'       => absint( get_option( $settings_keys['days'], 0 ) ),
            ];
        }

        return $plans;
    }

    /**
     * Grants or extends user access based on a WooCommerce order.
     *
     * @param int $order_id WooCommerce order identifier.
     * @return void
     */
    public static function grant_access( $order_id ) {
        if ( empty( $order_id ) || ! function_exists( 'wc_get_order' ) ) {
            return;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order instanceof \WC_Order ) {
            return;
        }

        $access_state = $order->get_meta( '_wrpa_access_granted' );

        if ( in_array( $access_state, [ 'yes', 'trial_skipped' ], true ) ) {
            return;
        }

        $user_id = $order->get_user_id();

        if ( ! $user_id ) {
            $billing_email = $order->get_billing_email();

            if ( $billing_email ) {
                $user = get_user_by( 'email', $billing_email );
                if ( $user ) {
                    $user_id = (int) $user->ID;
                }
            }
        }

        if ( ! $user_id ) {
            if ( method_exists( __CLASS__, 'log' ) ) {
                self::log( sprintf( 'WRPA access not granted for order #%d — user could not be resolved.', $order_id ) );
            }
            return;
        }

        $product_ids = [];

        foreach ( $order->get_items( 'line_item' ) as $item ) {
            if ( ! $item instanceof \WC_Order_Item_Product ) {
                continue;
            }

            $product_id = (int) $item->get_product_id();
            $variation_id = (int) $item->get_variation_id();

            if ( $product_id ) {
                $product_ids[] = $product_id;
            }

            if ( $variation_id ) {
                $product_ids[] = $variation_id;
            }
        }

        if ( empty( $product_ids ) ) {
            if ( method_exists( __CLASS__, 'log' ) ) {
                self::log( sprintf( 'WRPA access not granted for order #%d — no purchasable items found.', $order_id ) );
            }
            return;
        }

        $plans = self::get_plan_configurations();

        $matched_plan = null;

        foreach ( $plans as $plan_key => $plan_config ) {
            if ( empty( $plan_config['product_id'] ) ) {
                continue;
            }

            if ( ! in_array( $plan_config['product_id'], $product_ids, true ) ) {
                continue;
            }

            if ( null === $matched_plan || $plan_config['days'] > $matched_plan['days'] ) {
                $matched_plan = [
                    'key'        => $plan_key,
                    'product_id' => $plan_config['product_id'],
                    'days'       => $plan_config['days'],
                ];
            }
        }

        if ( null === $matched_plan ) {
            if ( method_exists( __CLASS__, 'log' ) ) {
                self::log( sprintf( 'WRPA access not granted for order #%d — no matching plan for products: %s.', $order_id, implode( ',', $product_ids ) ) );
            }
            return;
        }

        $first_subscription_key     = '_wrpa_first_subscription_date';
        $first_subscription_exists  = metadata_exists( 'user', $user_id, $first_subscription_key );
        $first_subscription         = $first_subscription_exists ? get_user_meta( $user_id, $first_subscription_key, true ) : '';

        $trial_identifier      = '';
        $trial_identifier_data = [];
        $trial_ip_hash         = '';
        $trial_fingerprint     = '';

        if ( 'trial' === $matched_plan['key'] ) {
            $trial_identifier_data = self::get_trial_identifier_from_order( $order );
            $trial_identifier      = isset( $trial_identifier_data['identifier'] ) ? (string) $trial_identifier_data['identifier'] : '';
            $trial_ip_hash         = isset( $trial_identifier_data['ip_hash'] ) ? (string) $trial_identifier_data['ip_hash'] : '';
            $trial_fingerprint     = isset( $trial_identifier_data['fingerprint'] ) ? (string) $trial_identifier_data['fingerprint'] : '';

            $existing_identifier_record = null;

            if ( ( $trial_identifier || $trial_ip_hash || $trial_fingerprint )
                && self::trial_identifier_used_by_other_user( $trial_identifier, $user_id, $existing_identifier_record, $trial_ip_hash, $trial_fingerprint )
            ) {
                if ( is_array( $existing_identifier_record ) && ! isset( $existing_identifier_record['identifier'] ) && $trial_identifier ) {
                    $existing_identifier_record['identifier'] = $trial_identifier;
                }

                if ( method_exists( __CLASS__, 'log' ) ) {
                    $log_context = [
                        'user_id'    => $user_id,
                        'order_id'   => $order_id,
                        'identifier' => $trial_identifier,
                        'recorded_user' => isset( $existing_identifier_record['user_id'] ) ? (int) $existing_identifier_record['user_id'] : 0,
                    ];

                    if ( $trial_ip_hash ) {
                        $log_context['ip_hash'] = $trial_ip_hash;
                    }

                    if ( $trial_fingerprint ) {
                        $log_context['fingerprint'] = $trial_fingerprint;
                    }

                    if ( isset( $existing_identifier_record['identifier'] ) ) {
                        $log_context['matched_identifier'] = (string) $existing_identifier_record['identifier'];
                    }

                    if ( isset( $existing_identifier_record['match_type'] ) ) {
                        $log_context['match_type'] = (string) $existing_identifier_record['match_type'];
                    }

                    self::log( 'WRPA trial access denied due to device reuse.', $log_context );
                }

                $order->update_meta_data( '_wrpa_access_granted', 'trial_denied_device' );
                $order->save();

                return;
            }
        }

        if ( 'trial' === $matched_plan['key'] && $first_subscription_exists ) {
            if ( method_exists( __CLASS__, 'log' ) ) {
                $recorded_on = is_numeric( $first_subscription ) ? gmdate( 'c', (int) $first_subscription ) : (string) $first_subscription;
                self::log( sprintf( 'WRPA trial access skipped for user %d via order #%d — first subscription already recorded on %s.', $user_id, $order_id, $recorded_on ) );
            }

            $order->update_meta_data( '_wrpa_access_granted', 'trial_skipped' );
            $order->save();

            return;
        }

        $duration_days   = max( 0, (int) $matched_plan['days'] );
        $now             = (int) current_time( 'timestamp' );
        $current_expiry  = absint( get_user_meta( $user_id, self::USER_ACCESS_EXPIRES_META, true ) );
        $previous_expiry = $current_expiry;
        $extended        = false;

        if ( $duration_days > 0 ) {
            if ( $current_expiry > $now ) {
                $expires  = $current_expiry + ( $duration_days * DAY_IN_SECONDS );
                $extended = true;
            } else {
                $expires = $now + ( $duration_days * DAY_IN_SECONDS );
            }
        } else {
            $expires = 0;
        }

        update_user_meta( $user_id, self::USER_ACCESS_EXPIRES_META, $expires ? absint( $expires ) : 0 );
        update_user_meta( $user_id, self::USER_PLAN_META, $matched_plan['key'] );
        update_user_meta( $user_id, self::USER_EXPIRY_META, $expires ? absint( $expires ) : 0 );

        if ( 'trial' === $matched_plan['key'] && $expires ) {
            update_user_meta( $user_id, self::USER_TRIAL_END_META, absint( $expires ) );
        } else {
            delete_user_meta( $user_id, self::USER_TRIAL_END_META );
        }

        if ( 'trial' === $matched_plan['key'] && $trial_fingerprint ) {
            update_user_meta( $user_id, self::USER_TRIAL_FINGERPRINT_META, $trial_fingerprint );
        } else {
            delete_user_meta( $user_id, self::USER_TRIAL_FINGERPRINT_META );
        }

        $order->update_meta_data( '_wrpa_access_granted', 'yes' );
        $order->save();

        if ( 'trial' === $matched_plan['key'] && $trial_identifier ) {
            self::record_trial_identifier( $trial_identifier, $user_id, $trial_ip_hash, $trial_fingerprint );
        }

        if ( ! $first_subscription_exists ) {
            $first_subscription_timestamp = current_time( 'timestamp' );
            update_user_meta( $user_id, $first_subscription_key, $first_subscription_timestamp );

            if ( method_exists( __CLASS__, 'log' ) ) {
                self::log( sprintf( 'WRPA first subscription date recorded for user %d via order #%d at %s.', $user_id, $order_id, gmdate( 'c', $first_subscription_timestamp ) ) );
            }

            do_action( 'wrpa_access_first_granted', $user_id, $order_id, $matched_plan['key'] );
        }

        if ( method_exists( __CLASS__, 'log' ) ) {
            $previous_label = $previous_expiry ? gmdate( 'c', $previous_expiry ) : 'none';
            $new_label      = $expires ? gmdate( 'c', $expires ) : 'never';
            $action         = $extended ? 'extended' : 'granted';

            self::log( sprintf( 'WRPA access %s for user %d via order #%d (%s plan). Previous expiry: %s. New expiry: %s.', $action, $user_id, $order_id, $matched_plan['key'], $previous_label, $new_label ) );
        }

        if ( class_exists( '\\WRPA\\WRPA_Email' ) ) {
            $verification_meta_key = class_exists( '\\WRPA\\WRPA_Email_Verify' ) ? \WRPA\WRPA_Email_Verify::META_FLAG : 'wrpa_email_verified';
            $verified_flag         = get_user_meta( $user_id, $verification_meta_key, true );

            if ( '1' !== (string) $verified_flag ) {
                \WRPA\WRPA_Email::send_verification( $user_id );
            }
        }
    }

    /**
     * Collects network characteristics for a trial order and builds hashes for deduplication.
     *
     * @param \WC_Order $order WooCommerce order instance.
     * @return array{identifier:string,ip:string,ip_hash:string,user_agent:string,fingerprint:string}
     */
    protected static function get_trial_identifier_from_order( \WC_Order $order ) {
        $ip_address = method_exists( $order, 'get_customer_ip_address' ) ? (string) $order->get_customer_ip_address() : '';
        $user_agent = method_exists( $order, 'get_customer_user_agent' ) ? (string) $order->get_customer_user_agent() : '';

        if ( ! $ip_address && isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip_address = (string) ( function_exists( 'wp_unslash' ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : $_SERVER['REMOTE_ADDR'] );
        }

        if ( ! $user_agent && isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $user_agent = (string) ( function_exists( 'wp_unslash' ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : $_SERVER['HTTP_USER_AGENT'] );
        }

        $raw_identifier = trim( $ip_address . '|' . $user_agent, '|' );

        if ( function_exists( 'wp_hash' ) ) {
            $identifier = $raw_identifier !== '' ? wp_hash( $raw_identifier ) : '';
        } else {
            $identifier = $raw_identifier !== '' ? hash( 'sha256', $raw_identifier ) : '';
        }

        $ip_hash = '' !== $ip_address ? md5( $ip_address ) : '';

        $fingerprint_source = '';
        if ( '' !== $user_agent || '' !== $ip_address ) {
            $fingerprint_source = $user_agent . '|' . $ip_address;
        }

        $fingerprint = '' !== $fingerprint_source ? md5( $fingerprint_source ) : '';

        return [
            'identifier'  => (string) $identifier,
            'ip'          => (string) $ip_address,
            'ip_hash'     => (string) $ip_hash,
            'user_agent'  => (string) $user_agent,
            'fingerprint' => (string) $fingerprint,
        ];
    }

    /**
     * Determines whether a trial identifier is already tied to a different user.
     *
     * @param string      $identifier Device/IP hash.
     * @param int         $user_id    Current user identifier.
     * @param array|null  $existing_record Populated with the conflicting record when a match is found.
     * @param string      $ip_hash    Hash of the remote IP address.
     * @param string      $fingerprint Combined fingerprint hash (user agent + IP).
     * @return bool
     */
    protected static function trial_identifier_used_by_other_user( $identifier, $user_id, &$existing_record = null, $ip_hash = '', $fingerprint = '' ) {
        $store = self::get_trial_identifier_store();

        $existing_record = null;
        $user_id         = (int) $user_id;

        if ( $identifier && ! empty( $store[ $identifier ] ) ) {
            $existing_record = $store[ $identifier ];
            $stored_user     = isset( $existing_record['user_id'] ) ? (int) $existing_record['user_id'] : 0;

            if ( $stored_user && $stored_user !== $user_id ) {
                $existing_record['identifier'] = $identifier;
                $existing_record['match_type'] = 'identifier';

                return true;
            }

            // Safe-guard to continue checking other signals when the identifier belongs to the same user.
            $existing_record = null;
        }

        if ( $ip_hash ) {
            foreach ( $store as $store_identifier => $record ) {
                $stored_ip_hash = isset( $record['ip_hash'] ) ? (string) $record['ip_hash'] : '';

                if ( '' === $stored_ip_hash || $stored_ip_hash !== (string) $ip_hash ) {
                    continue;
                }

                $stored_user = isset( $record['user_id'] ) ? (int) $record['user_id'] : 0;

                if ( $stored_user && $stored_user !== $user_id ) {
                    $existing_record              = $record;
                    $existing_record['identifier'] = $store_identifier;
                    $existing_record['match_type'] = 'ip';

                    return true;
                }
            }
        }

        if ( $fingerprint ) {
            foreach ( $store as $store_identifier => $record ) {
                $stored_fingerprint = isset( $record['fingerprint'] ) ? (string) $record['fingerprint'] : '';

                if ( '' === $stored_fingerprint || $stored_fingerprint !== (string) $fingerprint ) {
                    continue;
                }

                $stored_user = isset( $record['user_id'] ) ? (int) $record['user_id'] : 0;

                if ( $stored_user && $stored_user !== $user_id ) {
                    $existing_record              = $record;
                    $existing_record['identifier'] = $store_identifier;
                    $existing_record['match_type'] = 'fingerprint';

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Records a trial identifier for future abuse prevention checks.
     *
     * @param string $identifier Device/IP hash.
     * @param int    $user_id    User identifier.
     * @param string $ip_hash    Hash of the remote IP address.
     * @param string $fingerprint Combined fingerprint hash (user agent + IP).
     * @return void
     */
    protected static function record_trial_identifier( $identifier, $user_id, $ip_hash = '', $fingerprint = '' ) {
        $store          = self::get_trial_identifier_store();
        $timestamp      = (int) current_time( 'timestamp' );
        $store[$identifier] = [
            'user_id'  => (int) $user_id,
            'recorded' => $timestamp > 0 ? $timestamp : time(),
        ];

        if ( $ip_hash ) {
            $store[$identifier]['ip_hash'] = (string) $ip_hash;
        }

        if ( $fingerprint ) {
            $store[$identifier]['fingerprint'] = (string) $fingerprint;
        }

        if ( count( $store ) > self::TRIAL_DEVICE_STORE_LIMIT ) {
            uasort(
                $store,
                static function ( $a, $b ) {
                    $a_time = isset( $a['recorded'] ) ? (int) $a['recorded'] : 0;
                    $b_time = isset( $b['recorded'] ) ? (int) $b['recorded'] : 0;

                    if ( $a_time === $b_time ) {
                        return 0;
                    }

                    return ( $a_time < $b_time ) ? -1 : 1;
                }
            );

            $store = array_slice( $store, -self::TRIAL_DEVICE_STORE_LIMIT, self::TRIAL_DEVICE_STORE_LIMIT, true );
        }

        update_option( self::OPTION_TRIAL_DEVICE_HASHES, $store, false );
    }

    /**
     * Retrieves the stored trial identifiers indexed by hash.
     *
     * @return array
     */
    protected static function get_trial_identifier_store() {
        $store = get_option( self::OPTION_TRIAL_DEVICE_HASHES, [] );

        if ( ! is_array( $store ) ) {
            return [];
        }

        return $store;
    }

    /**
     * Returns the timezone used by the site.
     *
     * @return \DateTimeZone
     */
    protected static function get_timezone() {
        if ( function_exists( 'wp_timezone' ) ) {
            return wp_timezone();
        }

        return new \DateTimeZone( wp_timezone_string() );
    }

    /**
     * Builds a start/end timestamp range for the provided day.
     *
     * @param \DateTimeImmutable $date Target day.
     * @return array{start:int,end:int}
     */
    protected static function get_day_range( \DateTimeImmutable $date ) {
        $start = $date->setTime( 0, 0, 0 );
        $end   = $start->modify( '+1 day' )->modify( '-1 second' );

        return [
            'start' => (int) $start->getTimestamp(),
            'end'   => (int) $end->getTimestamp(),
        ];
    }

    /**
     * Normalizes a list of user IDs to integers.
     *
     * @param mixed $users Raw list of IDs.
     * @return array<int,int>
     */
    protected static function normalize_user_ids( $users ) {
        if ( empty( $users ) || ! is_array( $users ) ) {
            return [];
        }

        $users = array_map( 'absint', $users );
        $users = array_filter( $users );
        $users = array_unique( $users );

        return array_values( $users );
    }

    /**
     * Performs a user query constrained by a meta key range.
     *
     * @param string $meta_key Meta key to inspect.
     * @param int    $start    Start timestamp (inclusive).
     * @param int    $end      End timestamp (inclusive).
     * @param int    $limit    Limit of results.
     * @return array<int,int>
     */
    protected static function query_users_by_meta_range( $meta_key, $start, $end, $limit ) {
        $start = (int) $start;
        $end   = (int) $end;
        $limit = (int) $limit;

        if ( $limit <= 0 || $end < $start || ! function_exists( 'get_users' ) ) {
            return [];
        }

        $users = get_users(
            [
                'number'     => $limit,
                'fields'     => 'ids',
                'meta_query' => [
                    [
                        'key'     => $meta_key,
                        'value'   => [ $start, $end ],
                        'compare' => 'BETWEEN',
                        'type'    => 'NUMERIC',
                    ],
                ],
            ]
        );

        return self::normalize_user_ids( $users );
    }

    /**
     * Formats a timestamp as a Y-m-d string honoring the site timezone.
     *
     * @param int $timestamp Timestamp to format.
     * @return string
     */
    protected static function format_timestamp_as_date( $timestamp ) {
        $timestamp = (int) $timestamp;

        if ( $timestamp <= 0 ) {
            return '';
        }

        if ( function_exists( 'wp_date' ) ) {
            return wp_date( 'Y-m-d', $timestamp );
        }

        $timezone = self::get_timezone();
        $date     = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone );

        return $date->format( 'Y-m-d' );
    }

    /**
     * Records a debug log entry for WRPA operations.
     *
     * Writes to the uploads directory when possible and falls back to the PHP
     * error log as a safety net. Logging can be disabled entirely by defining
     * the WRPA_DISABLE_LOGS constant with a truthy value.
     *
     * @param mixed $message Message to record.
     * @param array $context Optional contextual data to append.
     * @return void
     */
    public static function log( $message, array $context = [] ) {
        if ( defined( 'WRPA_DISABLE_LOGS' ) && WRPA_DISABLE_LOGS ) {
            return;
        }

        $json_flags = 0;

        if ( defined( 'JSON_UNESCAPED_SLASHES' ) ) {
            $json_flags |= JSON_UNESCAPED_SLASHES;
        }

        if ( defined( 'JSON_UNESCAPED_UNICODE' ) ) {
            $json_flags |= JSON_UNESCAPED_UNICODE;
        }

        if ( ! is_scalar( $message ) ) {
            if ( function_exists( 'wp_json_encode' ) ) {
                $encoded = wp_json_encode( $message, $json_flags );
            } else {
                $encoded = json_encode( $message );
            }

            $message = false !== $encoded ? $encoded : print_r( $message, true );
        } else {
            $message = (string) $message;
        }

        if ( ! empty( $context ) ) {
            if ( function_exists( 'wp_json_encode' ) ) {
                $context_string = wp_json_encode( $context, $json_flags );
            } else {
                $context_string = json_encode( $context );
            }

            if ( $context_string ) {
                $message .= ' ' . $context_string;
            }
        }

        $timestamp = current_time( 'mysql' );

        if ( ! $timestamp ) {
            $timestamp = gmdate( 'Y-m-d H:i:s' );
        }

        $entry = sprintf( '[%s] %s', $timestamp, $message );

        $uploads = function_exists( 'wp_get_upload_dir' ) ? wp_get_upload_dir() : [];

        if ( ! empty( $uploads['basedir'] ) ) {
            if ( function_exists( 'trailingslashit' ) ) {
                $directory = trailingslashit( $uploads['basedir'] );
            } else {
                $directory = rtrim( $uploads['basedir'], '/\\' ) . '/';
            }

            if ( ! file_exists( $directory ) && function_exists( 'wp_mkdir_p' ) ) {
                wp_mkdir_p( $directory );
            }

            if ( is_dir( $directory ) && is_writable( $directory ) ) {
                $log_file = $directory . 'wrpa-access-log.txt';
                $result   = @file_put_contents( $log_file, $entry . PHP_EOL, FILE_APPEND | LOCK_EX );

                if ( false !== $result ) {
                    return;
                }
            }
        }

        if ( function_exists( 'error_log' ) ) {
            error_log( $entry );
        }
    }
}
