<?php
/**
 * Class: WRPA_Access
 * Description: Premium erişim süresi, plan kontrolü ve meta yönetimi.
 */

if (!defined('ABSPATH')) exit;

class WRPA_Access {

    // 🔧 Eklenti başlangıç noktası (boş init metodu)
    public static function init() {
        // Gelecekte kullanılacak hook'lar buraya eklenecek
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WRPA_Access::init() çağrıldı.');
        }
    }

    public static function grant_access($user_id, $order, $settings) {
        $has_monthly = isset($settings['monthly_days']) && $settings['monthly_days'] > 0;
        $has_trial   = isset($settings['trial_days']) && $settings['trial_days'] > 0;

        if ($has_monthly) {
            $selected_plan = 'monthly';
            $duration = WRPA_Core::seconds_from($settings['monthly_days'], $settings['monthly_minutes']);
        } elseif ($has_trial) {
            $selected_plan = 'trial';
            $duration = WRPA_Core::seconds_from($settings['trial_days'], $settings['trial_minutes']);
        }

        if (empty($selected_plan) || $duration <= 0) {
            return;
        }

        // 🧠 Her yeni siparişte eski erişimi sıfırla
        delete_user_meta($user_id, 'wr_premium_access_expiry');

        $now    = time();
        $expiry = $now + intval($duration);

        update_user_meta($user_id, 'wr_premium_access_start', $now);
        update_user_meta($user_id, 'wr_premium_access_expiry', $expiry);

        if ($selected_plan === 'trial') {
            update_user_meta($user_id, 'wrpa_trial_used', true);
        }

        // 🚀 İlk abonelik tarihini ve plan adını kaydet
        if (!get_user_meta($user_id, 'wrpa_first_subscription_date', true)) {
            update_user_meta($user_id, 'wrpa_first_subscription_date', current_time('mysql'));
        }

        foreach ($order->get_items() as $item) {
            $plan_name = $item->get_name();
            if (!empty($plan_name)) {
                update_user_meta($user_id, 'wrpa_plan_name', sanitize_text_field($plan_name));
            }
            break;
        }

        // 🔁 Cache & session tazele
        self::purge_user_cache($user_id);
        wp_cache_delete($user_id, 'user_meta');
        wp_set_auth_cookie($user_id);

        // 🚫 Cache bypass cookie (LiteSpeed & Cloudflare uyumlu)
        setcookie('wrpa_nocache', '1', time() + 300, COOKIEPATH, COOKIE_DOMAIN);
        if (function_exists('litespeed_purge_user')) {
            litespeed_purge_user($user_id);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'WRPA: Access granted | user=%d plan=%s start=%s expiry=%s',
                $user_id,
                $selected_plan,
                date('Y-m-d H:i:s', $now),
                date('Y-m-d H:i:s', $expiry)
            ));
        }
    }

    /**
     * Süresi dolan kullanıcıları otomatik yönlendirir
     */
    public static function redirect_expired_users() {
        // ...
    }
}
