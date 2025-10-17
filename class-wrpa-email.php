<?php
if ( ! defined('ABSPATH') ) exit;

class WRPA_Email {

    public static function init() {
        add_action('wrpa_cron_5min', [__CLASS__,'expiry_reminders']);
        add_action('wrpa_cron_daily', [__CLASS__,'holiday_greetings']);
    }

    private static function headers() {
        $s = WRPA_Core::get_settings();
        $from = sprintf('From: %s <%s>', $s['sender_name'] ?? 'Wisdom Rain', $s['sender_email'] ?? get_option('admin_email'));
        return array($from,'Content-Type: text/plain; charset=UTF-8');
    }

    public static function send_welcome($user_id, $order_id = 0) {
        $s = WRPA_Core::get_settings();
        $tpl = $s['tpl_welcome'] ?? '';
        $body = WRPA_Core::fill_placeholders($tpl, $user_id);
        $u = get_user_by('id',$user_id);
        wp_mail($u->user_email, 'Welcome to Wisdom Rain', $body, self::headers());
    }

    public static function send_thanks($user_id, $order_id = 0) {
        $s = WRPA_Core::get_settings();
        $tpl = $s['tpl_thanks'] ?? '';
        $body = WRPA_Core::fill_placeholders($tpl, $user_id);
        $u = get_user_by('id',$user_id);
        wp_mail($u->user_email, 'Thank you for your order', $body, self::headers());
    }

    // Her 5 dakikada bir: 10 dakika kala uyar (MVP)
    public static function expiry_reminders() {
        $s = WRPA_Core::get_settings();
        $users = get_users(array('fields'=>array('ID','user_email')));
        foreach ($users as $u) {
            $exp = (int) get_user_meta($u->ID,'wr_premium_access_expiry', true);
            if ( ! $exp ) continue;

            $left = $exp - time();
            if ( $left > 0 && $left <= 600 ) {
                $tpl  = $s['tpl_expiry'] ?? '';
                $body = WRPA_Core::fill_placeholders($tpl, $u->ID);
                wp_mail($u->user_email, 'Premium Access Reminder', $body, self::headers());
            }
        }
    }

    // Günlük: belirli tarih aralıklarında tebrik postası (örnek – MVP)
    public static function holiday_greetings() {
        $month = (int) current_time('m');
        $day   = (int) current_time('d');

        // Basit örnek aralıkları: Yılbaşı (Jan 1), Sevgililer (Feb 14), Cadılar Bayramı (Oct 31), Efsane Kasım (Nov 11)
        $is_holiday = (
            ($month==1 && $day==1) ||
            ($month==2 && $day==14) ||
            ($month==10 && $day==31) ||
            ($month==11 && $day==11)
        );

        if ( ! $is_holiday ) return;

        $s = WRPA_Core::get_settings();
        $tpl = $s['tpl_holiday'] ?? '';
        $users = get_users(array('fields'=>array('ID','user_email')));

        foreach ($users as $u) {
            $body = WRPA_Core::fill_placeholders($tpl, $u->ID);
            wp_mail($u->user_email, 'Greetings from Wisdom Rain', $body, self::headers());
        }
    }
}
