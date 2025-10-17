diff --git a/includes/class-wrpa-access.php b/includes/class-wrpa-access.php
index 392eda7159b322cd47e16b670e04edc8f5b620e1..874cf7153f1a68274c99f647fa12caa2589c1d27 100644
--- a/includes/class-wrpa-access.php
+++ b/includes/class-wrpa-access.php
@@ -46,126 +46,149 @@ class WRPA_Access {
         }
 
         return $passed;
     }
 
     /**
      * Sipariş tamamlandığında premium erişim verilir.
      * - Siparişteki en güçlü plan belirlenir (Yearly > Monthly > Trial)
      * - Eski expiry mutlaka silinir ve yeni expiry zorla yazılır
      * - Cache bypass cookie eklenir (LiteSpeed & Cloudflare için)
      */
     public static function grant_access($order_id) {
         if ( ! $order_id ) return;
 
         $order = wc_get_order($order_id);
         if ( ! $order ) return;
 
         $user_id = $order->get_user_id();
         if ( ! $user_id ) return;
 
         $settings   = WRPA_Core::get_settings();
         $trial_id   = intval($settings['product_trial_id']);
         $monthly_id = intval($settings['product_monthly_id']);
         $yearly_id  = intval($settings['product_yearly_id']);
 
+        if ( $order->get_meta('_wrpa_access_processed') ) {
+            return;
+        }
+
         $has_trial   = false;
         $has_monthly = false;
         $has_yearly  = false;
 
         foreach ( $order->get_items() as $item ) {
             $product_id = intval($item->get_product_id());
             if ( $product_id === $yearly_id )  { $has_yearly  = true; }
             if ( $product_id === $monthly_id ) { $has_monthly = true; }
             if ( $product_id === $trial_id )   { $has_trial   = true; }
         }
 
         $selected_plan = null;
         $duration      = 0;
 
         if ( $has_yearly ) {
             $selected_plan = 'yearly';
             $duration = WRPA_Core::seconds_from($settings['yearly_days'], $settings['yearly_minutes']);
         } elseif ( $has_monthly ) {
             $selected_plan = 'monthly';
             $duration = WRPA_Core::seconds_from($settings['monthly_days'], $settings['monthly_minutes']);
         } elseif ( $has_trial ) {
             $selected_plan = 'trial';
             $duration = WRPA_Core::seconds_from($settings['trial_days'], $settings['trial_minutes']);
         }
 
         if ( empty($selected_plan) || $duration <= 0 ) {
             return;
         }
 
         // 🧠 Her yeni siparişte eski erişimi sıfırla
         delete_user_meta($user_id, 'wr_premium_access_expiry');
 
-        $now    = time();
+        $now    = current_time('timestamp');
         $expiry = $now + intval($duration);
 
         update_user_meta($user_id, 'wr_premium_access_start', $now);
         update_user_meta($user_id, 'wr_premium_access_expiry', $expiry);
+        update_user_meta($user_id, 'wrpa_plan_type', sanitize_key($selected_plan));
+
+        $first_subscription = get_user_meta($user_id, '_wrpa_first_subscription_date', true);
+        $is_first_subscription = empty($first_subscription);
+        if ( $is_first_subscription ) {
+            update_user_meta($user_id, '_wrpa_first_subscription_date', $now);
+        }
 
         if ( $selected_plan === 'trial' ) {
             update_user_meta($user_id, 'wrpa_trial_used', true);
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
 
         if ( defined('WP_DEBUG') && WP_DEBUG ) {
             error_log(sprintf(
                 'WRPA: Access granted | user=%d plan=%s start=%s expiry=%s',
                 $user_id,
                 $selected_plan,
-                date('Y-m-d H:i:s', $now),
-                date('Y-m-d H:i:s', $expiry)
+                function_exists('wp_date') ? wp_date('Y-m-d H:i:s', $now) : date('Y-m-d H:i:s', $now),
+                function_exists('wp_date') ? wp_date('Y-m-d H:i:s', $expiry) : date('Y-m-d H:i:s', $expiry)
             ));
         }
+
+        if ( class_exists('WRPA_Email') ) {
+            if ( $is_first_subscription ) {
+                WRPA_Email::send_welcome($user_id, $order_id);
+            }
+
+            WRPA_Email::send_thanks($user_id, $order_id);
+        }
+
+        $order->update_meta_data('_wrpa_access_processed', $now);
+        $order->update_meta_data('_wrpa_access_plan', $selected_plan);
+        $order->save();
     }
 
     /**
      * Süresi dolan kullanıcıları otomatik yönlendirir
      */
     public static function restrict_premium_pages() {
         if ( current_user_can('administrator') ) return;
 
         $settings       = WRPA_Core::get_settings();
         $protected_cpts = isset($settings['protected_cpts']) ? $settings['protected_cpts'] : [];
         $subscribe_url  = esc_url($settings['subscribe_url']);
 
         if ( empty($protected_cpts) ) return;
         if ( ! is_singular($protected_cpts) ) return;
 
         if ( ! is_user_logged_in() ) {
             wp_safe_redirect($subscribe_url);
             exit;
         }
 
         $user_id = get_current_user_id();
         $expiry  = get_user_meta($user_id, 'wr_premium_access_expiry', true);
 
-        if ( empty($expiry) || time() > intval($expiry) ) {
+        if ( empty($expiry) || current_time('timestamp') > intval($expiry) ) {
             self::purge_user_cache($user_id);
             wp_safe_redirect($subscribe_url);
             exit;
         }
     }
 
     /**
      * Kullanıcıya ait cache etiketini temizler
      */
     private static function purge_user_cache($user_id) {
         if ( function_exists('do_action') ) {
             do_action('litespeed_tag_purge', 'wr_user_' . intval($user_id));
         }
     }
 }
