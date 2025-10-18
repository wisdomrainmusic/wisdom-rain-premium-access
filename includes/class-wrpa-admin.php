<?php
/**
 * WRPA - Admin Module (Final Fix)
 * @package WisdomRain\PremiumAccess
 */

namespace WRPA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WRPA_Admin' ) ) {
    class WRPA_Admin {
        /**
         * Admin sınıfını başlatır.
         */
        public static function init() {
            // Yalnızca admin panelinde çalış.
            if ( is_admin() ) {
                add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
            }
        }

        /**
         * WRPA menüsünü kaydeder.
         */
        public static function register_menu() {
            // Menü görünürlük ve yetki kontrolü.
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            add_menu_page(
                __( 'WRPA Dashboard', 'wrpa' ),     // Sayfa başlığı
                __( 'WRPA', 'wrpa' ),               // Menü adı
                'manage_options',                   // Yetki
                'wrpa-dashboard',                   // Slug
                [ __CLASS__, 'dashboard_page' ],    // Callback
                'dashicons-star-filled',            // Menü ikonu
                2                                   // Pozisyon (Dashboard'un hemen altı)
            );
        }

        /**
         * Menü sayfası içeriği.
         */
        public static function dashboard_page() {
            echo '<div class="wrap">';
            echo '<h1>WRPA 2.0 Dashboard</h1>';
            echo '<p>Menü başarıyla yüklendi ve görünür durumda ✅</p>';
            echo '</div>';
        }
    }

    // Admin sınıfını başlat.
    add_action( 'plugins_loaded', [ 'WRPA\WRPA_Admin', 'init' ] );
}
