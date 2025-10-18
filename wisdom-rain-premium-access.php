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
define( 'WRPA_VERSION', '2.0.0' );
define( 'WRPA_PATH', plugin_dir_path( __FILE__ ) );
define( 'WRPA_URL', plugin_dir_url( __FILE__ ) );

// === Dosyaları Dahil Et (ileride eklenecek modüller) === //
$includes = array(
    'includes/class-wrpa-core.php',
    'includes/class-wrpa-admin.php',
    'includes/class-wrpa-access.php',
    'includes/class-wrpa-email.php',
    'includes/class-wrpa-cron.php'
);

foreach ( $includes as $file ) {
    $path = WRPA_PATH . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
    }
}

// === Ana Başlatma === //
if ( class_exists( 'WRPA\\WRPA_Core' ) ) {
    add_action( 'plugins_loaded', array( 'WRPA\\WRPA_Core', 'init' ) );
}
