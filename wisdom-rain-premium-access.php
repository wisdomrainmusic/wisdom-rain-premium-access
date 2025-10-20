<?php
/**
 * Plugin Name: Wisdom Rain Premium Access (WRPA)
 * Plugin URI: https://wisdomrainbookmusic.com/
 * Description: Abonelik tabanlı içerik erişimi için geliştirilmiş WordPress eklentisi.
 * Version: 2.0.0
 * Author: Wisdom Rain
 * Author URI: https://wisdomrainbookmusic.com/
 * License: MIT
 * Text Domain: wrpa
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Doğrudan erişim engellendi.
}

// === Sabitler === //
if ( ! defined( 'WRPA_VERSION' ) ) {
    define( 'WRPA_VERSION', '2.0.0' );
}

if ( ! defined( 'WRPA_PATH' ) ) {
    define( 'WRPA_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WRPA_URL' ) ) {
    define( 'WRPA_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WRPA_META_EXPIRY' ) ) {
    define( 'WRPA_META_EXPIRY', DAY_IN_SECONDS * 30 );
}

if ( ! defined( 'WRPA_DASHBOARD_URL' ) ) {
    define( 'WRPA_DASHBOARD_URL', site_url( '/wisdom-rain-dashboard/' ) );
}

// === Dosyaları Dahil Et (ileride eklenecek modüller) === //
$includes = array(
    'includes/class-wrpa-core.php'
);

foreach ( $includes as $file ) {
    $path = WRPA_PATH . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
    }
}

// === Ana Başlatma === //
if ( class_exists( 'WRPA\WRPA_Core' ) ) {
    register_activation_hook( __FILE__, array( 'WRPA\WRPA_Core', 'activate' ) );
    register_deactivation_hook( __FILE__, array( 'WRPA\WRPA_Core', 'deactivate' ) );

    add_action( 'plugins_loaded', array( 'WRPA\WRPA_Core', 'init' ) );
}
