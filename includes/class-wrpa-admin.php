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
        // Ana menü
        add_menu_page(
            'WRPA Dashboard',
            'WRPA',
            'manage_options',
            'wrpa-dashboard',
            [ __CLASS__, 'dashboard_page' ],
            'dashicons-star-filled',
            2
        );

        // Alt menüler
        add_submenu_page(
            'wrpa-dashboard',
            'Members',
            'Members',
            'manage_options',
            'wrpa-members',
            [ __CLASS__, 'members_page' ]
        );

        add_submenu_page(
            'wrpa-dashboard',
            'Email Templates',
            'Email Templates',
            'manage_options',
            'wrpa-emails',
            [ __CLASS__, 'emails_page' ]
        );

        add_submenu_page(
            'wrpa-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'wrpa-settings',
            [ __CLASS__, 'settings_page' ]
        );
    }

    // Sayfa içerikleri
    public static function dashboard_page() {
        echo '<div class="wrap"><h1>WRPA Dashboard</h1><p>Yönetim paneline hoş geldiniz.</p></div>';
    }

    public static function members_page() {
        echo '<div class="wrap"><h1>Members</h1><p>Üyeler sekmesi hazırlanıyor.</p></div>';
    }

    public static function emails_page() {
        echo '<div class="wrap"><h1>Email Templates</h1><p>E-posta şablonları sekmesi hazırlanıyor.</p></div>';
    }

    public static function settings_page() {
        echo '<div class="wrap"><h1>Settings</h1><p>Ayarlar sekmesi hazırlanıyor.</p></div>';
    }
}
