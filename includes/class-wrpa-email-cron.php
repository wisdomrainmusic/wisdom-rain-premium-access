<?php
/**
 * WRPA - Email Cron tools
 *
 * @package WisdomRain\PremiumAccess
 */

namespace WRPA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles recurring email cron tasks for WRPA.
 */
class WRPA_Email_Cron {

    const DAILY_HOOK          = 'wrpa_email_daily';
    const BATCH_LIMIT         = 200;
    const BLAST_QUEUE_OPTION  = 'wrpa_email_blast_queue';
    const ORDER_EMAIL_META    = '_wrpa_order_completed_email_sent';

    /**
     * Bootstraps scheduling hooks for recurring email jobs.
     */
    public static function init() : void {
        // Allow other modules to trigger our registration handler using the fully-qualified class reference.
        add_action( 'user_register', [ '\\WRPA\\WRPA_Email_Cron', 'handle_user_registered' ], 10, 1 );
        add_action( 'init', [ __CLASS__, 'maybe_schedule_daily_event' ] );
        add_action( self::DAILY_HOOK, [ __CLASS__, 'run_daily_jobs' ] );
        add_action( 'wrpa_access_first_granted', [ __CLASS__, 'handle_first_access_granted' ], 10, 3 );
        add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'trigger_order_completed_emails' ], 20, 1 );
    }

    /**
     * Ensures the daily cron event is scheduled for 03:10 server local time.
     */
    public static function maybe_schedule_daily_event() : void {
        if ( wp_next_scheduled( self::DAILY_HOOK ) ) {
            return;
        }

        if ( ! function_exists( 'wp_schedule_event' ) ) {
            return;
        }

        $timestamp = self::next_daily_timestamp();
        wp_schedule_event( $timestamp, 'daily', self::DAILY_HOOK );
    }

    /**
     * Calculates the next timestamp for 03:10 based on the site timezone.
     *
     * @return int Unix timestamp for the next run.
     */
    protected static function next_daily_timestamp() : int {
        $timezone = self::get_timezone();
        $now      = new \DateTimeImmutable( 'now', $timezone );
        $target   = $now->setTime( 3, 10 );

        if ( $target->getTimestamp() <= $now->getTimestamp() ) {
            $target = $target->modify( '+1 day' );
        }

        return $target->getTimestamp();
    }

    /**
     * Runs scheduled email related tasks.
     *
     * @return void
     */
    public static function run_daily_jobs() : void {
        /**
         * Fires when the WRPA daily email cron event runs.
         */
        do_action( 'wrpa/email_daily' );

        $remaining = self::BATCH_LIMIT;

        $dispatch = self::trigger_expiring_soon( $remaining );
        $remaining -= $dispatch['attempted'];

        if ( $remaining > 0 ) {
            $dispatch  = self::trigger_trial_ended_today( $remaining );
            $remaining -= $dispatch['attempted'];
        }

        if ( $remaining > 0 ) {
            foreach ( [ '3d', '3m', '6m' ] as $interval ) {
                $dispatch  = self::trigger_expired_after( $interval, $remaining );
                $remaining -= $dispatch['attempted'];

                if ( $remaining <= 0 ) {
                    break;
                }
            }
        }

        if ( $remaining > 0 ) {
            $dispatch  = self::trigger_campaign_dates( $remaining );
            $remaining -= $dispatch['attempted'];
        }

        if ( $remaining > 0 ) {
            $dispatch  = self::trigger_blast_queue( $remaining );
            $remaining -= $dispatch['attempted'];
        }
    }

    /**
     * Reacts to WordPress user registration and sends onboarding emails.
     *
     * @param int $user_id Newly created user identifier.
     * @return void
     */
    public static function handle_user_registered( $user_id ) : void {
        self::trigger_welcome_emails( $user_id, true );
    }

    /**
     * Sends welcome emails when access is granted for the first time.
     *
     * @param int    $user_id  User identifier.
     * @param int    $order_id WooCommerce order identifier.
     * @param string $plan_key Granted plan key.
     * @return void
     */
    public static function handle_first_access_granted( $user_id, $order_id = 0, $plan_key = '' ) : void {
        unset( $order_id, $plan_key );
        self::trigger_welcome_emails( $user_id, false );
    }

    /**
     * Dispatches a welcome email and optional verification mail.
     *
     * @param int  $user_id           Recipient user identifier.
     * @param bool $send_verification Whether to send verification mail as well.
     * @return bool
     */
    public static function trigger_welcome_emails( $user_id, $send_verification = false ) : bool {
        $user_id = absint( $user_id );

        if ( ! $user_id ) {
            do_action( 'wrpa_email_job_executed', 'welcome', 0 );
            return false;
        }

        $sent = WRPA_Email::send_email( $user_id, 'welcome' );
        do_action( 'wrpa_email_job_executed', 'welcome', $sent ? 1 : 0 );

        if ( $send_verification && method_exists( '\\WRPA\\WRPA_Email', 'send_verification' ) ) {
            WRPA_Email::send_verification( $user_id );
        }

        return (bool) $sent;
    }

    /**
     * Sends an order completion email when WooCommerce marks the order complete.
     *
     * @param int|\WC_Order $order Order identifier or object.
     * @return bool
     */
    public static function trigger_order_completed_emails( $order ) : bool {
        if ( ! function_exists( 'wc_get_order' ) ) {
            do_action( 'wrpa_email_job_executed', 'order-completed', 0 );
            return false;
        }

        $order = is_object( $order ) ? $order : wc_get_order( $order );

        if ( ! $order instanceof \WC_Order ) {
            do_action( 'wrpa_email_job_executed', 'order-completed', 0 );
            return false;
        }

        if ( '1' === (string) $order->get_meta( self::ORDER_EMAIL_META ) ) {
            return false;
        }

        $user_id = (int) $order->get_user_id();

        if ( ! $user_id && method_exists( $order, 'get_billing_email' ) ) {
            $billing_email = $order->get_billing_email();

            if ( $billing_email ) {
                $user = get_user_by( 'email', $billing_email );
                if ( $user ) {
                    $user_id = (int) $user->ID;
                }
            }
        }

        if ( ! $user_id ) {
            do_action( 'wrpa_email_job_executed', 'order-completed', 0 );
            return false;
        }

        $order_id   = method_exists( $order, 'get_order_number' ) ? $order->get_order_number() : $order->get_id();
        $order_total = method_exists( $order, 'get_formatted_order_total' ) ? $order->get_formatted_order_total() : $order->get_total();

        $order_date = '';
        if ( method_exists( $order, 'get_date_completed' ) ) {
            $completed = $order->get_date_completed();
            if ( $completed instanceof \WC_DateTime ) {
                $order_date = function_exists( 'wc_format_datetime' ) ? wc_format_datetime( $completed ) : $completed->date_i18n( get_option( 'date_format' ) );
            }
        }

        if ( '' === $order_date && method_exists( $order, 'get_date_created' ) ) {
            $created = $order->get_date_created();
            if ( $created instanceof \WC_DateTime ) {
                $order_date = function_exists( 'wc_format_datetime' ) ? wc_format_datetime( $created ) : $created->date_i18n( get_option( 'date_format' ) );
            }
        }

        $plan_name = '';
        if ( method_exists( $order, 'get_items' ) ) {
            foreach ( $order->get_items() as $item ) {
                if ( is_object( $item ) && method_exists( $item, 'get_name' ) ) {
                    $plan_name = (string) $item->get_name();
                    break;
                }
            }
        }

        $invoice_url = method_exists( $order, 'get_view_order_url' ) ? $order->get_view_order_url() : '';

        $order_data = [
            'order_id'      => $order_id,
            'order_total'   => $order_total,
            'order_date'    => $order_date,
            'invoice_url'   => $invoice_url,
            'support_email' => self::get_support_email(),
        ];

        if ( '' !== $plan_name ) {
            $order_data['plan_name'] = $plan_name;
        }

        $sent = WRPA_Email::send_email( $user_id, 'order-completed', $order_data );

        if ( $sent ) {
            $order->update_meta_data( self::ORDER_EMAIL_META, '1' );
            $order->save();
        }

        do_action( 'wrpa_email_job_executed', 'order-completed', $sent ? 1 : 0 );

        return (bool) $sent;
    }

    /**
     * Sends reminder emails for subscriptions expiring in the next three days.
     *
     * @param int $limit Available email slots.
     * @return array{attempted:int,sent:int}
     */
    public static function trigger_expiring_soon( $limit = self::BATCH_LIMIT ) : array {
        if ( ! class_exists( '\\WRPA\\WRPA_Access' ) || ! method_exists( '\\WRPA\\WRPA_Access', 'get_users_expiring_in' ) ) {
            do_action( 'wrpa_email_job_executed', 'sub-expiring-3d', 0 );
            return self::scenario_result();
        }

        $limit    = max( 0, (int) $limit );
        $user_ids = WRPA_Access::get_users_expiring_in( 3, $limit );

        return self::dispatch_for_slug( $user_ids, 'sub-expiring-3d', [], $limit );
    }

    /**
     * Sends notifications when a user's trial ends today.
     *
     * @param int $limit Available email slots.
     * @return array{attempted:int,sent:int}
     */
    public static function trigger_trial_ended_today( $limit = self::BATCH_LIMIT ) : array {
        if ( ! class_exists( '\\WRPA\\WRPA_Access' ) || ! method_exists( '\\WRPA\\WRPA_Access', 'get_users_with_trial_end' ) ) {
            do_action( 'wrpa_email_job_executed', 'trial-ended-today', 0 );
            return self::scenario_result();
        }

        $limit    = max( 0, (int) $limit );
        $user_ids = WRPA_Access::get_users_with_trial_end( 'today', $limit );

        return self::dispatch_for_slug( $user_ids, 'trial-ended-today', [], $limit );
    }

    /**
     * Sends re-engagement campaigns based on how long ago a subscription expired.
     *
     * @param string $interval Interval token (3d, 3m, 6m).
     * @param int    $limit    Available email slots.
     * @return array{attempted:int,sent:int}
     */
    public static function trigger_expired_after( $interval, $limit = self::BATCH_LIMIT ) : array {
        $map = [
            '3d' => [ 'modifier' => '-3 days',   'slug' => 'sub-expired-3d' ],
            '3m' => [ 'modifier' => '-3 months', 'slug' => 'sub-expired-3m' ],
            '6m' => [ 'modifier' => '-6 months', 'slug' => 'sub-expired-6m' ],
        ];

        if ( ! isset( $map[ $interval ] ) ) {
            return self::scenario_result();
        }

        if ( ! class_exists( '\\WRPA\\WRPA_Access' ) || ! method_exists( '\\WRPA\\WRPA_Access', 'get_users_expired_since' ) ) {
            do_action( 'wrpa_email_job_executed', $map[ $interval ]['slug'], 0 );
            return self::scenario_result();
        }

        $limit    = max( 0, (int) $limit );
        $user_ids = WRPA_Access::get_users_expired_since( $map[ $interval ]['modifier'], $limit );

        return self::dispatch_for_slug( $user_ids, $map[ $interval ]['slug'], [], $limit );
    }

    /**
     * Triggers seasonal campaign emails on predetermined dates.
     *
     * @param int $limit Available email slots.
     * @return array{attempted:int,sent:int}
     */
    public static function trigger_campaign_dates( $limit = self::BATCH_LIMIT ) : array {
        $timezone = self::get_timezone();
        $today    = new \DateTimeImmutable( 'now', $timezone );
        $key      = $today->format( 'm-d' );

        $campaigns = [
            '02-13' => 'campaign-valentines-13feb',
            '12-31' => 'campaign-newyear-31dec',
            '11-11' => 'campaign-november',
        ];

        if ( ! isset( $campaigns[ $key ] ) ) {
            return self::scenario_result();
        }

        $slug = $campaigns[ $key ];

        if ( ! class_exists( '\\WRPA\\WRPA_Access' ) || ! method_exists( '\\WRPA\\WRPA_Access', 'get_active_user_ids' ) ) {
            do_action( 'wrpa_email_job_executed', $slug, 0 );
            return self::scenario_result();
        }

        $limit    = max( 0, (int) $limit );
        $user_ids = WRPA_Access::get_active_user_ids( $limit );

        $data = self::get_campaign_payload( $slug );

        return self::dispatch_for_slug( $user_ids, $slug, $data, $limit );
    }

    /**
     * Processes queued blast emails created by administrators.
     *
     * @param int $limit Available email slots.
     * @return array{attempted:int,sent:int}
     */
    public static function trigger_blast_queue( $limit = self::BATCH_LIMIT ) : array {
        $limit = max( 0, (int) $limit );

        if ( $limit <= 0 ) {
            return self::scenario_result();
        }

        $queue = get_option( self::BLAST_QUEUE_OPTION, [] );

        if ( empty( $queue ) || ! is_array( $queue ) ) {
            return self::scenario_result();
        }

        $result = self::scenario_result();

        foreach ( $queue as $index => &$job ) {
            if ( $limit <= 0 ) {
                break;
            }

            $recipients = self::resolve_blast_recipients( $job, $limit );

            if ( empty( $recipients ) ) {
                unset( $queue[ $index ] );
                continue;
            }

            $payload  = self::normalize_blast_data( isset( $job['data'] ) && is_array( $job['data'] ) ? $job['data'] : [] );
            $dispatch = self::dispatch_for_slug( $recipients, 'blast-now', $payload, $limit );

            $limit                 -= $dispatch['attempted'];
            $result['attempted']   += $dispatch['attempted'];
            $result['sent']        += $dispatch['sent'];

            if ( $dispatch['attempted'] > 0 ) {
                $job['user_ids'] = array_slice( $recipients, $dispatch['attempted'] );

                if ( empty( $job['user_ids'] ) ) {
                    unset( $queue[ $index ] );
                }
            }
        }
        unset( $job );

        update_option( self::BLAST_QUEUE_OPTION, array_values( $queue ) );

        return $result;
    }

    /**
     * Sends a batch of emails using the WRPA email system.
     *
     * @param array<int,int|string> $user_ids List of user identifiers to email.
     * @param string                $template Template slug to send.
     * @param array<string,mixed>   $data     Additional template data shared by all recipients.
     *
     * @return array{sent:int,failures:array<int,int>} Summary of the batch processing.
     */
    public static function queue_and_send( array $user_ids, string $template, array $data = [] ) : array {
        $summary = [
            'sent'     => 0,
            'failures' => [],
        ];

        $normalized = array_filter( array_map( 'absint', $user_ids ) );

        foreach ( array_chunk( $normalized, self::BATCH_LIMIT ) as $chunk ) {
            foreach ( $chunk as $user_id ) {
                $sent = WRPA_Email::send_email( $user_id, $template, $data );

                if ( $sent ) {
                    $summary['sent']++;
                } else {
                    $summary['failures'][] = $user_id;
                }
            }
        }

        return $summary;
    }

    /**
     * Helper for scenario methods to provide consistent result arrays.
     *
     * @param int $attempted Number of attempted deliveries.
     * @param int $sent      Number of successful deliveries.
     * @return array{attempted:int,sent:int}
     */
    protected static function scenario_result( $attempted = 0, $sent = 0 ) : array {
        return [
            'attempted' => max( 0, (int) $attempted ),
            'sent'      => max( 0, (int) $sent ),
        ];
    }

    /**
     * Runs queue_and_send with throttling and logging for a template slug.
     *
     * @param array<int,int>        $user_ids Recipient IDs.
     * @param string                $slug     Template slug.
     * @param array<string,mixed>   $data     Shared template data.
     * @param int                   $limit    Maximum number of recipients to process.
     * @return array{attempted:int,sent:int}
     */
    protected static function dispatch_for_slug( array $user_ids, $slug, array $data, $limit ) : array {
        $limit    = max( 0, (int) $limit );
        $user_ids = array_map( 'absint', $user_ids );
        $user_ids = array_filter( $user_ids );
        $user_ids = array_values( array_unique( $user_ids ) );

        if ( $limit <= 0 || empty( $user_ids ) ) {
            do_action( 'wrpa_email_job_executed', $slug, 0 );
            return self::scenario_result();
        }

        $user_ids = array_slice( $user_ids, 0, $limit );

        $summary  = self::queue_and_send( $user_ids, $slug, $data );
        $attempts = count( $user_ids );
        $sent     = isset( $summary['sent'] ) ? (int) $summary['sent'] : 0;

        do_action( 'wrpa_email_job_executed', $slug, $sent );

        return self::scenario_result( $attempts, $sent );
    }

    /**
     * Provides default data for campaign templates.
     *
     * @param string $slug Campaign template slug.
     * @return array<string,mixed>
     */
    protected static function get_campaign_payload( $slug ) : array {
        $support_email   = self::get_support_email();
        $unsubscribe_url = self::get_unsubscribe_url();
        $payload         = [];

        switch ( $slug ) {
            case 'campaign-valentines-13feb':
                $payload = [
                    'campaign_event_url' => home_url( '/events/valentines/' ),
                    'gift_card_url'      => home_url( '/shop/gift-card/' ),
                    'support_email'      => $support_email,
                    'unsubscribe_url'    => $unsubscribe_url,
                ];
                break;
            case 'campaign-newyear-31dec':
                $payload = [
                    'campaign_event_url' => home_url( '/events/new-year/' ),
                    'share_link'         => home_url( '/share/new-year/' ),
                    'fireworks_image_url' => trailingslashit( WRPA_URL ) . 'assets/images/fireworks.jpg',
                    'support_email'      => $support_email,
                    'unsubscribe_url'    => $unsubscribe_url,
                ];
                break;
            case 'campaign-november':
                $end_date = strtotime( 'last day of november' );
                $payload  = [
                    'discount_percentage' => 40,
                    'campaign_end_date'   => function_exists( 'wp_date' ) ? wp_date( 'd F', $end_date ) : date_i18n( 'd F', $end_date ),
                    'support_email'       => $support_email,
                    'unsubscribe_url'     => $unsubscribe_url,
                ];
                break;
        }

        /**
         * Filters the campaign payload before sending.
         *
         * @param array  $payload Default payload.
         * @param string $slug    Campaign slug.
         */
        return apply_filters( 'wrpa_email_campaign_payload', $payload, $slug );
    }

    /**
     * Normalises blast job payloads with sensible defaults.
     *
     * @param array<string,mixed> $data Raw job payload.
     * @return array<string,mixed>
     */
    protected static function normalize_blast_data( array $data ) : array {
        $support_email   = self::get_support_email();
        $unsubscribe_url = $data['unsubscribe_url'] ?? self::get_unsubscribe_url();

        $defaults = [
            'blast_title'        => __( 'Duyuru', 'wrpa' ),
            'blast_intro'        => __( 'sizinle özel bir haber paylaşmak istiyoruz.', 'wrpa' ),
            'blast_body'         => __( 'Detayları kısa süre sonra paylaşacağız.', 'wrpa' ),
            'primary_cta_url'    => home_url( '/' ),
            'primary_cta_label'  => __( 'Detayları Gör', 'wrpa' ),
            'secondary_cta_text' => __( 'üyelik panelini ziyaret edin', 'wrpa' ),
            'support_email'      => $support_email,
            'unsubscribe_url'    => $unsubscribe_url,
        ];

        $payload = array_merge( $defaults, $data );

        if ( empty( $payload['custom_message'] ) ) {
            $payload['custom_message'] = $payload['blast_title'];
        }

        /**
         * Filters the blast payload data prior to sending.
         *
         * @param array $payload Normalized payload.
         * @param array $data    Original payload.
         */
        return apply_filters( 'wrpa_email_blast_payload', $payload, $data );
    }

    /**
     * Ensures a blast job has recipient IDs available.
     *
     * @param array<string,mixed> $job   Blast job descriptor (modified by reference).
     * @param int                 $limit Maximum number of recipients to resolve.
     * @return array<int,int>
     */
    protected static function resolve_blast_recipients( array &$job, $limit ) : array {
        $limit    = max( 0, (int) $limit );
        $user_ids = [];

        if ( ! empty( $job['user_ids'] ) ) {
            $user_ids = array_map( 'absint', (array) $job['user_ids'] );
            $user_ids = array_values( array_filter( $user_ids ) );

            if ( ! empty( $user_ids ) ) {
                return $user_ids;
            }
        }

        if ( empty( $job['audience'] ) || ! class_exists( '\\WRPA\\WRPA_Access' ) ) {
            return [];
        }

        $audience_limit = $limit > 0 ? max( $limit, self::BATCH_LIMIT ) : self::BATCH_LIMIT;

        switch ( $job['audience'] ) {
            case 'active':
                if ( method_exists( '\\WRPA\\WRPA_Access', 'get_active_user_ids' ) ) {
                    $user_ids = WRPA_Access::get_active_user_ids( $audience_limit );
                }
                break;
            case 'expiring-3d':
                if ( method_exists( '\\WRPA\\WRPA_Access', 'get_users_expiring_in' ) ) {
                    $user_ids = WRPA_Access::get_users_expiring_in( 3, $audience_limit );
                }
                break;
        }

        $job['user_ids'] = $user_ids;

        return $user_ids;
    }

    /**
     * Retrieves the timezone configured for the site.
     *
     * @return \DateTimeZone
     */
    protected static function get_timezone() : \DateTimeZone {
        if ( function_exists( 'wp_timezone' ) ) {
            return wp_timezone();
        }

        return new \DateTimeZone( wp_timezone_string() );
    }

    /**
     * Returns the default support email address.
     *
     * @return string
     */
    protected static function get_support_email() : string {
        $email = get_option( 'admin_email' );

        if ( is_email( $email ) ) {
            return $email;
        }

        $host = parse_url( home_url(), PHP_URL_HOST );

        if ( $host ) {
            return 'support@' . $host;
        }

        return 'support@example.com';
    }

    /**
     * Returns the unsubscribe/manage preferences URL.
     *
     * @return string
     */
    protected static function get_unsubscribe_url() : string {
        if ( class_exists( __NAMESPACE__ . '\\WRPA_Email_Unsubscribe' ) ) {
            return WRPA_Email_Unsubscribe::get_unsubscribe_url( 0 );
        }

        return home_url( '/account/preferences/' );
    }
}
