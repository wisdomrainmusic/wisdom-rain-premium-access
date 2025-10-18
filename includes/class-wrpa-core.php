<?php
/**
 * WRPA - Core Module
 *
 * @package WisdomRain\PremiumAccess
 */

namespace WRPA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Core bootstrap for the Wisdom Rain Premium Access plugin.
 */
class WRPA_Core {
    /**
     * Bootstraps the plugin by defining constants, loading dependencies and wiring hooks.
     *
     * @return void
     */
    public static function init() {
        if ( function_exists( 'error_log' ) ) {
            error_log( 'WRPA Core initialized' );
        }

        self::define_constants();
        self::load_dependencies();
        \WRPA\WRPA_Access::init();
        \WRPA\WRPA_Email::init();
        \WRPA\WRPA_Email_Admin::init();
        \WRPA\WRPA_Email_Cron::init();
        \WRPA\WRPA_Cron::init();
        self::init_hooks();
    }

    /**
     * Defines plugin wide constants when they are not set already.
     *
     * @return void
     */
    protected static function define_constants() {
        if ( ! defined( 'WRPA_VERSION' ) ) {
            define( 'WRPA_VERSION', '2.0.0' );
        }

        if ( ! defined( 'WRPA_PATH' ) ) {
            define( 'WRPA_PATH', plugin_dir_path( dirname( __FILE__ ) ) );
        }

        if ( ! defined( 'WRPA_URL' ) ) {
            define( 'WRPA_URL', plugin_dir_url( dirname( __FILE__ ) ) );
        }

        if ( ! defined( 'WRPA_META_EXPIRY' ) ) {
            define( 'WRPA_META_EXPIRY', DAY_IN_SECONDS * 30 );
        }
    }

    /**
     * Loads the plugin modules and triggers their initialisation routines.
     *
     * @return void
     */
    private static function load_dependencies() {
        require_once WRPA_PATH . 'includes/class-wrpa-access.php';
        require_once WRPA_PATH . 'includes/class-wrpa-admin.php';
        require_once WRPA_PATH . 'includes/class-wrpa-email-log.php';
        require_once WRPA_PATH . 'includes/class-wrpa-email.php';
        require_once WRPA_PATH . 'includes/class-wrpa-email-admin.php';
        require_once WRPA_PATH . 'includes/class-wrpa-email-cron.php';
        require_once WRPA_PATH . 'includes/class-wrpa-cron.php';
    }

    /**
     * Registers the WordPress hooks used by the plugin.
     *
     * @return void
     */
    private static function init_hooks() {
        add_action( 'plugins_loaded', [ 'WRPA\WRPA_Admin', 'init' ] );
        add_action( 'admin_init', [ __CLASS__, 'admin_init' ] );
        add_action( 'template_redirect', [ __CLASS__, 'frontend_init' ] );
    }

    /**
     * Runs during the WordPress admin initialisation sequence.
     *
     * @return void
     */
    public static function admin_init() {
        do_action( 'wrpa/admin_init' );
    }

    /**
     * Runs when the front-end template redirect hook fires.
     *
     * @return void
     */
    public static function frontend_init() {
        do_action( 'wrpa/frontend_init' );
    }

    /**
     * Provides commonly used WRPA URLs for templates and modules.
     *
     * @return array
     */
    public static function urls() : array {
        return [
            'subscribe_url'           => home_url( '/subscribe/' ),
            'dashboard_url'           => home_url( '/dashboard/' ),
            'manage_subscription_url' => home_url( '/account/subscriptions/' ),
            'verify_email_url'        => home_url( '/verify-email/' ),
        ];
    }

    /**
     * Executes on plugin activation.
     *
     * @return void
     */
    public static function activate() {
        self::define_constants();
        self::load_dependencies();

        WRPA_Email_Log::install_table();

        do_action( 'wrpa/activate' );
    }

    /**
     * Executes on plugin deactivation.
     *
     * @return void
     */
    public static function deactivate() {
        wp_clear_scheduled_hook( 'wrpa_cron_event' );

        if ( function_exists( 'error_log' ) ) {
            error_log( 'WRPA plugin deactivated — scheduled hooks cleared.' );
        }

        do_action( 'wrpa/deactivate' );
    }
}
