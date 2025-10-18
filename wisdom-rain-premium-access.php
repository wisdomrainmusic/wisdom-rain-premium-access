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

// === Eklenti Dosya Sabiti === //
if ( ! defined( 'WRPA_PLUGIN_FILE' ) ) {
    define( 'WRPA_PLUGIN_FILE', __FILE__ );
}

// === Dosyaları Dahil Et (ileride eklenecek modüller) === //
$includes = array(
    __DIR__ . '/includes/class-wrpa-core.php',
);

foreach ( $includes as $path ) {
    if ( file_exists( $path ) ) {
        require_once $path;
    }
}

// === Ana Başlatma === //
$core_class = 'WRPA\\WRPA_Core';

if ( class_exists( $core_class ) ) {
    call_user_func( array( $core_class, 'init' ) );

    register_activation_hook( WRPA_PLUGIN_FILE, array( $core_class, 'activate' ) );
    register_deactivation_hook( WRPA_PLUGIN_FILE, array( $core_class, 'deactivate' ) );
}
