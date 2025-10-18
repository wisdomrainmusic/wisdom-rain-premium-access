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
        self::define_constants();
        self::load_dependencies();
        self::init_hooks();

        error_log( 'WRPA Core initialized' );
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
            $plugin_path = rtrim( dirname( __DIR__ ), '/\\' ) . '/';
            define( 'WRPA_PATH', $plugin_path );
        }

        if ( ! defined( 'WRPA_URL' ) ) {
            $plugin_file = WRPA_PATH . 'wisdom-rain-premium-access.php';
            define( 'WRPA_URL', plugin_dir_url( $plugin_file ) );
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
    protected static function load_dependencies() {
        $modules = array(
            'includes/class-wrpa-access.php',
            'includes/class-wrpa-admin.php',
            'includes/class-wrpa-email.php',
            'includes/class-wrpa-cron.php',
        );

        foreach ( $modules as $module ) {
            $path = WRPA_PATH . $module;

            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }

        $autoload_modules = array(
            'WRPA_Access',
            'WRPA_Email',
            'WRPA_Cron',
        );

        foreach ( $autoload_modules as $module_class ) {
            $class = __NAMESPACE__ . '\\' . $module_class;

            if ( class_exists( $class ) && method_exists( $class, 'init' ) ) {
                call_user_func( array( $class, 'init' ) );
            }
        }
    }

    /**
     * Registers the WordPress hooks used by the plugin.
     *
     * @return void
     */
    protected static function init_hooks() {
        add_action( 'plugins_loaded', array( __NAMESPACE__ . '\\WRPA_Admin', 'init' ) );
        add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
        add_action( 'template_redirect', array( __CLASS__, 'frontend_init' ) );
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
     * Executes on plugin activation.
     *
     * @return void
     */
    public static function activate() {
        self::define_constants();
        self::load_dependencies();

        do_action( 'wrpa/activate' );
    }

    /**
     * Executes on plugin deactivation.
     *
     * @return void
     */
    public static function deactivate() {
        do_action( 'wrpa/deactivate' );
    }
}
