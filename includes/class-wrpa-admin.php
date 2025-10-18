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

    /**
     * Outputs the navigation tabs for the WRPA admin sub pages.
     *
     * @param string $active_slug The slug of the currently active tab.
     *
     * @return void
     */
    protected static function render_nav_tabs( $active_slug ) {
        $tabs = [
            'wrpa-members' => __( 'Members', 'wrpa' ),
            'wrpa-emails'  => __( 'Email Templates', 'wrpa' ),
            'wrpa-settings'=> __( 'Settings', 'wrpa' ),
        ];

        echo '<h2 class="nav-tab-wrapper">';

        foreach ( $tabs as $slug => $label ) {
            $url   = esc_url( admin_url( 'admin.php?page=' . $slug ) );
            $class = 'nav-tab';

            if ( $slug === $active_slug ) {
                $class .= ' nav-tab-active';
            }

            printf(
                '<a href="%1$s" class="%2$s">%3$s</a>',
                $url,
                esc_attr( $class ),
                esc_html( $label )
            );
        }

        echo '</h2>';
    }

    // Sayfa içerikleri
    public static function dashboard_page() {
        echo '<div class="wrap"><h1>WRPA Dashboard</h1><p>Yönetim paneline hoş geldiniz.</p></div>';
    }

    public static function members_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Members', 'wrpa' ) . '</h1>';
        self::render_nav_tabs( 'wrpa-members' );
        echo '<p>' . esc_html__( 'Üyeler sekmesi hazırlanıyor.', 'wrpa' ) . '</p>';
        echo '</div>';
    }

    public static function emails_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Email Templates', 'wrpa' ) . '</h1>';
        self::render_nav_tabs( 'wrpa-emails' );
        echo '<p>' . esc_html__( 'E-posta şablonları sekmesi hazırlanıyor.', 'wrpa' ) . '</p>';
        echo '</div>';
    }

    public static function settings_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Settings', 'wrpa' ) . '</h1>';
        self::render_nav_tabs( 'wrpa-settings' );
        echo '<p>' . esc_html__( 'Ayarlar sekmesi hazırlanıyor.', 'wrpa' ) . '</p>';
        echo '</div>';
    }
}
