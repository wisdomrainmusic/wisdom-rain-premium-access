<?php
/**
 * WRPA - Admin Module
 * @package WisdomRain\PremiumAccess
 */

namespace WRPA;

if ( ! defined( 'ABSPATH' ) ) exit;

class WRPA_Admin {
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
    }

    public static function register_menu() {
        add_menu_page(
            'WRPA Dashboard',         // Sayfa başlığı
            'WRPA',                   // Menü adı
            'manage_options',         // Yetki
            'wrpa-dashboard',         // Slug
            [ __CLASS__, 'dashboard_page' ], // Callback
            'dashicons-star-filled',  // İkon
            2                         // Sıra (Dashboard'un hemen altı)
        );
    }

    public static function dashboard_page() {
        echo '<div class="wrap"><h1>Hello WRPA 2.0</h1><p>Dashboard başarıyla yüklendi.</p></div>';
    }
}
