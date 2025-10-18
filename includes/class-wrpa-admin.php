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
        echo '<p>' . esc_html__( 'Aşağıdaki tablo üyelerinizin temel bilgilerini görüntüler.', 'wrpa' ) . '</p>';

        $filters = [
            'all'     => __( 'All', 'wrpa' ),
            'active'  => __( 'Active', 'wrpa' ),
            'expired' => __( 'Expired', 'wrpa' ),
            'trial'   => __( 'Trial', 'wrpa' ),
        ];

        echo '<div class="wrpa-members-toolbar" style="display:flex; justify-content:space-between; align-items:center; margin:20px 0;">';
        echo '<div class="wrpa-members-filters">';

        $active_filter = 'all';

        foreach ( $filters as $filter_key => $label ) {
            $button_classes = [ 'button' ];
            $button_classes[] = ( $filter_key === $active_filter ) ? 'button-primary' : 'button-secondary';

            printf(
                '<a href="#" class="%1$s" style="margin-right:6px;">%2$s</a>',
                esc_attr( implode( ' ', $button_classes ) ),
                esc_html( $label )
            );
        }

        echo '</div>';
        echo '<div class="wrpa-members-actions">';
        echo '<a href="#" class="button button-secondary" style="margin-left:12px;">' . esc_html__( 'CSV Export', 'wrpa' ) . '</a>';
        echo '</div>';
        echo '</div>';

        echo '<table class="wp-list-table widefat striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th scope="col">' . esc_html__( 'Name', 'wrpa' ) . '</th>';
        echo '<th scope="col">' . esc_html__( 'E-Mail', 'wrpa' ) . '</th>';
        echo '<th scope="col">' . esc_html__( 'Phone', 'wrpa' ) . '</th>';
        echo '<th scope="col">' . esc_html__( 'Country', 'wrpa' ) . '</th>';
        echo '<th scope="col">' . esc_html__( 'Subscription Date', 'wrpa' ) . '</th>';
        echo '<th scope="col">' . esc_html__( 'Plan', 'wrpa' ) . '</th>';
        echo '<th scope="col">' . esc_html__( 'Start Date', 'wrpa' ) . '</th>';
        echo '<th scope="col">' . esc_html__( 'Expiry/Days Left', 'wrpa' ) . '</th>';
        echo '<th scope="col">' . esc_html__( 'Status', 'wrpa' ) . '</th>';
        echo '</tr>';
        echo '</thead>';

        echo '<tbody>';

        $placeholder_rows = [
            [ 'Jane Doe', 'jane@example.com', '+1 555 0100', 'USA', '2023-11-01', 'Premium', '2023-11-01', '2024-11-01 / 180', __( 'Active', 'wrpa' ) ],
            [ 'John Smith', 'john@example.com', '+44 20 7946 0958', 'UK', '2023-09-15', 'Standard', '2023-09-15', '2024-09-15 / 120', __( 'Expiring Soon', 'wrpa' ) ],
            [ 'Ayşe Yılmaz', 'ayse@example.com', '+90 312 555 0101', 'Türkiye', '2023-12-20', 'Trial', '2023-12-20', '2024-01-20 / 30', __( 'Trial', 'wrpa' ) ],
        ];

        foreach ( $placeholder_rows as $row ) {
            echo '<tr>';
            foreach ( $row as $column ) {
                echo '<td>' . esc_html( $column ) . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
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
