<?php
/**
 * Plugin Name: Wisdom Rain Premium Access
 * Description: WooCommerce abonelik/erişim, CPT koruması, üye listesi ve e-posta otomasyonları.
 * Version: 1.0.0
 * Author: WR Dev
 * Requires at least: 6.0
 */

if ( ! defined('ABSPATH') ) exit;

define('WRPA_VERSION', '1.0.0');
define('WRPA_DIR', plugin_dir_path(__FILE__));
define('WRPA_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, function () {
    // Varsayılan ayarlar
    $defaults = array(
        'product_trial_id'   => '',
        'trial_days'         => 0,
        'trial_minutes'      => 10,
        'product_monthly_id' => '',
        'monthly_days'       => 0,
        'monthly_minutes'    => 10,
        'product_yearly_id'  => '',
        'yearly_days'        => 365,
        'sender_name'        => 'Wisdom Rain',
        'sender_email'       => get_option('admin_email'),
        'protected_cpts'     => array('library','music','meditation','children_story','sleep_story','magazine'),
        'subscribe_url'      => home_url('/subscribe'),
        // Basit şablonlar (kısa yer tutucular)
        'tpl_welcome'        => "Hi {name},\nWelcome to Wisdom Rain! Your access has started.",
        'tpl_thanks'         => "Thanks {name}! Your order is completed.",
        'tpl_expiry'         => "Hi {name}, your premium access will expire in {days_left} days.",
        'tpl_holiday'        => "Warm wishes, {name}! Enjoy special moments with Wisdom Rain."
    );
    add_option('wrpa_settings', $defaults, '', false);

    // Cron tanımları
    if ( ! wp_next_scheduled('wrpa_cron_5min') ) {
        wp_schedule_event(time(), 'wrpa_five_minutes', 'wrpa_cron_5min');
    }
    if ( ! wp_next_scheduled('wrpa_cron_daily') ) {
        wp_schedule_event(time(), 'daily', 'wrpa_cron_daily');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('wrpa_cron_5min');
    wp_clear_scheduled_hook('wrpa_cron_daily');
});

add_filter('cron_schedules', function ($s) {
    $s['wrpa_five_minutes'] = array('interval' => 300, 'display' => __('Every 5 Minutes','wrpa'));
    return $s;
});

// Includes
require_once WRPA_DIR . 'includes/class-wrpa-core.php';
require_once WRPA_DIR . 'includes/class-wrpa-access.php';
require_once WRPA_DIR . 'includes/class-wrpa-admin.php';
require_once WRPA_DIR . 'includes/class-wrpa-email.php';

// Bootstrap
add_action('plugins_loaded', function () {
    if ( ! class_exists('WooCommerce') ) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Wisdom Rain Premium Access</strong> WooCommerce gerektirir.</p></div>';
        });
        return;
    }

    WRPA_Core::init();
    WRPA_Access::init();
    WRPA_Admin::init();
    WRPA_Email::init();
});
