<?php
/**
 * WRPA - Email Log Module
 *
 * @package WisdomRain\PremiumAccess
 */

namespace WRPA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles email delivery logs for future reporting and diagnostics.
 */
class WRPA_Email_Log {
    /**
     * Database table slug without the WordPress table prefix.
     */
    const TABLE_SLUG = 'wrpa_email_log';

    /**
     * Creates or updates the email log database table.
     *
     * @return void
     */
    public static function install_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name      = $wpdb->prefix . self::TABLE_SLUG;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED,
            template_slug VARCHAR(100),
            recipient VARCHAR(255),
            subject TEXT,
            status VARCHAR(20),
            error TEXT,
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            meta LONGTEXT,
            INDEX user_template_sent (user_id, template_slug, sent_at)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Records a new email log entry.
     *
     * Logging is disabled until Phase V enables it via the wrpa_enable_email_logging filter.
     *
     * @param int    $user_id       The associated user ID.
     * @param string $template_slug The email template identifier.
     * @param string $recipient     The recipient email address.
     * @param string $subject       The email subject line.
     * @param string $status        Delivery status.
     * @param string $error         Error details, if any.
     * @param array  $meta          Additional metadata for the log entry.
     *
     * @return bool Whether the log entry was written.
     */
    public static function log( $user_id, $template_slug, $recipient, $subject, $status, $error = '', $meta = [] ) {
        if ( ! apply_filters( 'wrpa_enable_email_logging', false ) ) {
            return false;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_SLUG;
        $data       = [
            'user_id'       => $user_id,
            'template_slug' => $template_slug,
            'recipient'     => $recipient,
            'subject'       => $subject,
            'status'        => $status,
            'error'         => $error,
            'meta'          => maybe_serialize( $meta ),
            'sent_at'       => current_time( 'mysql' ),
        ];

        $formats = [
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
        ];

        return (bool) $wpdb->insert( $table_name, $data, $formats );
    }

    /**
     * Retrieves recent email log entries.
     *
     * @param int $limit Maximum number of rows to return.
     *
     * @return array
     */
    public static function get_logs( $limit = 50 ) {
        if ( ! apply_filters( 'wrpa_enable_email_logging', false ) ) {
            return [];
        }

        global $wpdb;

        $limit      = absint( $limit );
        $limit      = $limit > 0 ? $limit : 50;
        $table_name = $wpdb->prefix . self::TABLE_SLUG;

        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} ORDER BY sent_at DESC LIMIT %d",
            $limit
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        if ( empty( $results ) ) {
            return [];
        }

        foreach ( $results as &$row ) {
            if ( isset( $row['meta'] ) ) {
                $row['meta'] = maybe_unserialize( $row['meta'] );
            }
        }

        return $results;
    }
}

do_action( 'wrpa_email_log_ready' );
