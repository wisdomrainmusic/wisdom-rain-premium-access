<?php
if ( ! defined('ABSPATH') ) exit;

class WRPA_Core {

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__,'enqueue']);
        add_action('template_redirect', [__CLASS__,'nocache_for_logged_in'], 0);
        add_action('wrpa_cron_5min', [__CLASS__,'maybe_purge_cache']);

        // 💡 Checkout sayfasına hızlı kayıt formunu ekle
        add_action('woocommerce_before_checkout_form', [__CLASS__, 'add_checkout_register_form'], 5);
    }

    public static function get_settings() {
        return wp_parse_args( get_option('wrpa_settings', array()), array() );
    }

    public static function update_settings($arr) {
        update_option('wrpa_settings', $arr);
    }

    public static function enqueue() {
        wp_enqueue_style('wrpa-parent', get_template_directory_uri().'/style.css', [], null);
    }

    public static function nocache_for_logged_in() {
        if ( is_user_logged_in() ) {
            if ( function_exists('nocache_headers') ) nocache_headers();
            header("Cache-Control: no-cache, no-store, must-revalidate");
            header("Pragma: no-cache");
            header("Expires: 0");
        }
    }

    public static function maybe_purge_cache() {
        if ( function_exists('do_action') ) {
            do_action('litespeed_purge_all'); // varsa çalışır
        }
    }

    // Süre hesaplayıcı (saniye)
    public static function seconds_from($days = 0, $minutes = 0) {
        return (int)$days * DAY_IN_SECONDS + (int)$minutes * MINUTE_IN_SECONDS;
    }

    // Tarih formatlayıcı
    public static function fmt($timestamp) {
        return $timestamp ? date_i18n(get_option('date_format').' H:i', $timestamp) : '-';
    }

    // Yer tutucu değişimi
    public static function fill_placeholders($tpl, $user_id) {
        $u = get_user_by('id', $user_id);
        $name = $u ? $u->display_name : '';
        $email = $u ? $u->user_email : '';
        $start = get_user_meta($user_id,'wr_premium_access_start', true);
        $exp   = get_user_meta($user_id,'wr_premium_access_expiry', true);
        $days_left = $exp ? max(0, floor(($exp - time())/DAY_IN_SECONDS)) : 0;

        $repl = [
            '{name}'       => $name,
            '{email}'      => $email,
            '{start_date}' => self::fmt((int)$start),
            '{expiry_date}'=> self::fmt((int)$exp),
            '{days_left}'  => $days_left
        ];
        return strtr($tpl, $repl);
    }

    // 🧾 Checkout sayfasına "Hızlı Kayıt" formunu dahil eder
    public static function add_checkout_register_form() {
        // Eğer kullanıcı giriş yapmışsa formu gösterme
        if ( is_user_logged_in() ) return;

        $template_path = WRPA_DIR . 'templates/checkout_register.php';

        if ( file_exists($template_path) ) {
            include $template_path;
        } else {
            echo '<div class="notice notice-error"><p>⚠️ Template not found: checkout_register.php</p></div>';
        }
    }
}
