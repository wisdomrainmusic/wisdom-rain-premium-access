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

    const DAILY_HOOK = 'wrpa_email_daily';

    /**
     * Bootstraps scheduling hooks for recurring email jobs.
     */
    public static function init() : void {
        add_action( 'init', [ __CLASS__, 'maybe_schedule_daily_event' ] );
        add_action( self::DAILY_HOOK, [ __CLASS__, 'run_daily_jobs' ] );
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
        $timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( wp_timezone_string() );
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

        foreach ( $user_ids as $user_id ) {
            $user_id = absint( $user_id );

            if ( ! $user_id ) {
                continue;
            }

            $sent = WRPA_Email::send_email( $user_id, $template, $data );

            if ( $sent ) {
                $summary['sent']++;
            } else {
                $summary['failures'][] = $user_id;
            }
        }

        return $summary;
    }
}
