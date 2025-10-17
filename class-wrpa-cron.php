<?php
/**
 * Class: WRPA_Cron
 * Version: 0.1.1
 * Description: Premium üyelik zamanlayıcı – erişim kontrolü ve e-posta hatırlatmaları.
 */

if (!defined('ABSPATH')) exit;

class WRPA_Cron {

    public function init() {
        add_action('wr_check_premium_expiry_event', [$this, 'check_expiry']);
    }

    /**
     * 🔁 Her cron çalıştığında abonelikleri kontrol eder
     */
    public function check_expiry() {
        $users = get_users([
            'meta_key' => 'wr_premium_access_expiry',
            'meta_compare' => 'EXISTS'
        ]);

        $reminder_days = intval(get_option('wrpa_reminder_days', 3)); // panelden ayarlanabiliyor
        $now = time();

        foreach ($users as $user) {
            $expiry = intval(get_user_meta($user->ID, 'wr_premium_access_expiry', true));
            if (!$expiry) continue;

            $time_left = $expiry - $now;

            // ⏰ Hatırlatma zamanı geldiyse e-posta gönder
            if ($time_left > 0 && $time_left <= ($reminder_days * DAY_IN_SECONDS)) {
                if (!get_user_meta($user->ID, 'wrpa_reminder_sent', true)) {
                    $email = new WRPA_Email();
                    $email->send_expiry_reminder($user->ID);
                    update_user_meta($user->ID, 'wrpa_reminder_sent', 1);
                }
            }

            // 🚫 Süre dolmuşsa erişimi kaldır
            if ($time_left <= 0) {
                delete_user_meta($user->ID, 'wr_premium_access_expiry');
                update_user_meta($user->ID, 'wrpa_status', 'expired');
            }
        }
    }
}
