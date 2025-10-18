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

    const SHORTCODE_RESTRICTED = 'wrpa_restricted';

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
        $post_types = apply_filters( 'wrpa/restriction_post_types', [ 'post', 'page' ] );

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
        $post_types = apply_filters( 'wrpa/restriction_post_types', [ 'post', 'page' ] );

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

        $post_types = (array) apply_filters( 'wrpa/restriction_post_types', [ 'post', 'page' ] );
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

        if ( ! is_user_logged_in() || self::subscription_has_expired( get_current_user_id() ) ) {
            $destination  = home_url( '/subscribe/' );
            $current_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';

            if ( untrailingslashit( $current_path ) !== '/subscribe' ) {
                wp_safe_redirect( $destination );
                exit;
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

        $order->update_meta_data( '_wrpa_access_granted', 'yes' );
        $order->save();

        if ( ! $first_subscription_exists ) {
            $first_subscription_timestamp = current_time( 'timestamp' );
            update_user_meta( $user_id, $first_subscription_key, $first_subscription_timestamp );

            if ( method_exists( __CLASS__, 'log' ) ) {
                self::log( sprintf( 'WRPA first subscription date recorded for user %d via order #%d at %s.', $user_id, $order_id, gmdate( 'c', $first_subscription_timestamp ) ) );
            }
        }

        if ( method_exists( __CLASS__, 'log' ) ) {
            $previous_label = $previous_expiry ? gmdate( 'c', $previous_expiry ) : 'none';
            $new_label      = $expires ? gmdate( 'c', $expires ) : 'never';
            $action         = $extended ? 'extended' : 'granted';

            self::log( sprintf( 'WRPA access %s for user %d via order #%d (%s plan). Previous expiry: %s. New expiry: %s.', $action, $user_id, $order_id, $matched_plan['key'], $previous_label, $new_label ) );
        }
    }
}
