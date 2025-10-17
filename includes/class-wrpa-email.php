diff --git a/includes/class-wrpa-email.php b/includes/class-wrpa-email.php
index 3c51c50712b8432f0bbc59fbbeb44a4ad5aa5794..92110580ee99217e1acb1f655c6f1d2623b0aa94 100644
--- a/includes/class-wrpa-email.php
+++ b/includes/class-wrpa-email.php
@@ -1,74 +1,569 @@
 <?php
 if ( ! defined('ABSPATH') ) exit;
 
 class WRPA_Email {
 
+    /** @var array|null */
+    private static $mailer_settings = null;
+
     public static function init() {
-        add_action('wrpa_cron_5min', [__CLASS__,'expiry_reminders']);
-        add_action('wrpa_cron_daily', [__CLASS__,'holiday_greetings']);
+        add_filter('wrpa_email_placeholders', [__CLASS__, 'describe_placeholders']);
+        add_action('init', [__CLASS__, 'ensure_daily_schedule']);
+        add_action('wrpa_cron_daily', [__CLASS__, 'process_daily_cron']);
+    }
+
+    /**
+     * Ensures the daily cron runs at 08:00 site-local time.
+     */
+    public static function ensure_daily_schedule() {
+        $target = self::next_daily_timestamp();
+        $next   = wp_next_scheduled('wrpa_cron_daily');
+
+        if ( ! $next ) {
+            wp_schedule_event($target, 'daily', 'wrpa_cron_daily');
+            return;
+        }
+
+        if ( abs($next - $target) <= HOUR_IN_SECONDS ) {
+            return;
+        }
+
+        while ( $scheduled = wp_next_scheduled('wrpa_cron_daily') ) {
+            wp_unschedule_event($scheduled, 'wrpa_cron_daily');
+        }
+
+        wp_schedule_event($target, 'daily', 'wrpa_cron_daily');
+    }
+
+    /**
+     * Calculates the next 08:00 timestamp using the site timezone.
+     */
+    private static function next_daily_timestamp() {
+        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
+        $now      = new DateTime('now', $timezone);
+        $target   = new DateTime('today 08:00', $timezone);
+
+        if ( $now >= $target ) {
+            $target->modify('+1 day');
+        }
+
+        return $target->getTimestamp();
     }
 
+    /**
+     * Builds email headers using FluentSMTP configuration when available.
+     */
     private static function headers() {
-        $s = WRPA_Core::get_settings();
-        $from = sprintf('From: %s <%s>', $s['sender_name'] ?? 'Wisdom Rain', $s['sender_email'] ?? get_option('admin_email'));
-        return array($from,'Content-Type: text/plain; charset=UTF-8');
+        $mailer  = self::get_mailer_settings();
+        $from    = sprintf('From: %s <%s>', $mailer['sender_name'], $mailer['sender_email']);
+        $headers = array($from, 'Content-Type: text/html; charset=UTF-8');
+
+        return apply_filters('wrpa_email_headers', $headers, $mailer);
     }
 
+    /**
+     * Returns FluentSMTP-aware sender configuration.
+     */
+    private static function get_mailer_settings() {
+        if ( null !== self::$mailer_settings ) {
+            return self::$mailer_settings;
+        }
+
+        $settings = WRPA_Core::get_settings();
+
+        $sender_name  = $settings['sender_name'] ?? wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
+        $sender_email = $settings['sender_email'] ?? get_option('admin_email');
+        $host         = '';
+        $port         = '';
+        $encryption   = '';
+
+        $fluent = get_option('fluentmail_settings');
+        if ( is_array($fluent) ) {
+            $connections        = $fluent['settings']['connections'] ?? array();
+            $default_connection = $fluent['settings']['default_connection'] ?? '';
+
+            if ( $default_connection && isset($connections[ $default_connection ]) ) {
+                $connection = $connections[ $default_connection ];
+            } elseif ( ! empty($connections) ) {
+                $connection = reset($connections);
+            } else {
+                $connection = array();
+            }
+
+            if ( ! empty($connection) && is_array($connection) ) {
+                $sender_name  = $connection['sender_name'] ?? $sender_name;
+                $sender_email = $connection['sender_email'] ?? $sender_email;
+                $host         = $connection['host'] ?? ($connection['smtp_host'] ?? $host);
+                $port         = $connection['port'] ?? ($connection['smtp_port'] ?? $port);
+                $encryption   = $connection['encryption'] ?? ($connection['smtp_encryption'] ?? $encryption);
+            }
+        }
+
+        $encryption = is_string($encryption) ? strtolower($encryption) : '';
+
+        self::$mailer_settings = apply_filters('wrpa_email_mailer_settings', array(
+            'sender_name' => $sender_name,
+            'sender_email'=> $sender_email,
+            'host'        => $host,
+            'port'        => $port,
+            'encryption'  => $encryption,
+            'tls'         => in_array($encryption, array('tls', 'ssl', 'starttls'), true),
+        ));
+
+        return self::$mailer_settings;
+    }
+
+    /**
+     * Sends the welcome template immediately after access is granted.
+     */
     public static function send_welcome($user_id, $order_id = 0) {
-        $s = WRPA_Core::get_settings();
-        $tpl = $s['tpl_welcome'] ?? '';
-        $body = WRPA_Core::fill_placeholders($tpl, $user_id);
-        $u = get_user_by('id',$user_id);
-        wp_mail($u->user_email, 'Welcome to Wisdom Rain', $body, self::headers());
+        $extra = array();
+        if ( $order_id ) {
+            $extra['order_id'] = $order_id;
+        }
+
+        self::send_template($user_id, 'tpl_welcome', 'welcome', $extra);
     }
 
+    /**
+     * Sends the thank you template after purchase confirmation.
+     */
     public static function send_thanks($user_id, $order_id = 0) {
-        $s = WRPA_Core::get_settings();
-        $tpl = $s['tpl_thanks'] ?? '';
-        $body = WRPA_Core::fill_placeholders($tpl, $user_id);
-        $u = get_user_by('id',$user_id);
-        wp_mail($u->user_email, 'Thank you for your order', $body, self::headers());
+        $extra = array();
+        if ( $order_id ) {
+            $extra['order_id'] = $order_id;
+        }
+
+        self::send_template($user_id, 'tpl_thanks', 'thanks', $extra);
     }
 
-    // Her 5 dakikada bir: 10 dakika kala uyar (MVP)
-    public static function expiry_reminders() {
-        $s = WRPA_Core::get_settings();
-        $users = get_users(array('fields'=>array('ID','user_email')));
-        foreach ($users as $u) {
-            $exp = (int) get_user_meta($u->ID,'wr_premium_access_expiry', true);
-            if ( ! $exp ) continue;
-
-            $left = $exp - time();
-            if ( $left > 0 && $left <= 600 ) {
-                $tpl  = $s['tpl_expiry'] ?? '';
-                $body = WRPA_Core::fill_placeholders($tpl, $u->ID);
-                wp_mail($u->user_email, 'Premium Access Reminder', $body, self::headers());
+    /**
+     * Daily cron entry point executing all automated email flows.
+     */
+    public static function process_daily_cron() {
+        $users = self::get_relevant_users();
+
+        self::send_expiry_reminders($users);
+        self::send_expired_notices($users);
+        self::send_renewal_reminders($users);
+    }
+
+    /**
+     * Sends reminders for subscriptions expiring within three days.
+     *
+     * @param WP_User[]|null $users Optional pre-fetched user list.
+     */
+    public static function send_expiry_reminders($users = null) {
+        $now   = current_time('timestamp');
+        if ( null === $users ) {
+            $users = self::get_relevant_users();
+        }
+
+        foreach ( $users as $user ) {
+            $expiry = (int) get_user_meta($user->ID, 'wr_premium_access_expiry', true);
+            if ( ! $expiry || $expiry <= $now ) {
+                continue;
+            }
+
+            $days_left = (int) floor(($expiry - $now) / DAY_IN_SECONDS);
+            if ( $days_left < 0 || $days_left > 3 ) {
+                continue;
+            }
+
+            if ( self::was_email_sent_today($user->ID, '_wrpa_last_expiry_reminder') ) {
+                continue;
+            }
+
+            if ( self::send_template($user->ID, 'tpl_expiry', 'expiry') ) {
+                self::mark_email_sent($user->ID, '_wrpa_last_expiry_reminder');
+            }
+        }
+    }
+
+    /**
+     * Sends an email on the day a subscription expires.
+     *
+     * @param WP_User[]|null $users Optional pre-fetched user list.
+     */
+    public static function send_expired_notices($users = null) {
+        $now   = current_time('timestamp');
+        if ( null === $users ) {
+            $users = self::get_relevant_users();
+        }
+
+        foreach ( $users as $user ) {
+            $expiry = (int) get_user_meta($user->ID, 'wr_premium_access_expiry', true);
+            if ( ! $expiry || $expiry > $now ) {
+                continue;
+            }
+
+            if ( ($now - $expiry) > DAY_IN_SECONDS ) {
+                continue;
+            }
+
+            if ( self::was_email_sent_today($user->ID, '_wrpa_last_expired_notice') ) {
+                continue;
+            }
+
+            if ( self::send_template($user->ID, 'tpl_expired', 'expired') ) {
+                self::mark_email_sent($user->ID, '_wrpa_last_expired_notice');
             }
         }
     }
 
-    // Günlük: belirli tarih aralıklarında tebrik postası (örnek – MVP)
+    /**
+     * Sends a follow-up renewal reminder seven days after expiry.
+     *
+     * @param WP_User[]|null $users Optional pre-fetched user list.
+     */
+    public static function send_renewal_reminders($users = null) {
+        $now   = current_time('timestamp');
+        if ( null === $users ) {
+            $users = self::get_relevant_users();
+        }
+
+        foreach ( $users as $user ) {
+            $expiry = (int) get_user_meta($user->ID, 'wr_premium_access_expiry', true);
+            if ( ! $expiry || $expiry >= $now ) {
+                continue;
+            }
+
+            $days_since_expiry = (int) floor(($now - $expiry) / DAY_IN_SECONDS);
+            if ( 7 !== $days_since_expiry ) {
+                continue;
+            }
+
+            $meta_key = '_wrpa_last_renewal_reminder';
+            if ( get_user_meta($user->ID, $meta_key, true) ) {
+                continue;
+            }
+
+            if ( self::send_template($user->ID, 'tpl_renewal', 'renewal') ) {
+                update_user_meta($user->ID, $meta_key, $now);
+            }
+        }
+    }
+
+    /**
+     * Legacy compatibility wrapper for the old hook name.
+     *
+     * @deprecated 1.4.8 Use process_daily_cron instead.
+     */
+    public static function expiry_reminders() {
+        self::send_expiry_reminders();
+    }
+
+    /**
+     * Legacy compatibility wrapper for the old holiday cron.
+     *
+     * @deprecated 1.4.8 Use process_daily_cron instead.
+     */
     public static function holiday_greetings() {
-        $month = (int) current_time('m');
-        $day   = (int) current_time('d');
-
-        // Basit örnek aralıkları: Yılbaşı (Jan 1), Sevgililer (Feb 14), Cadılar Bayramı (Oct 31), Efsane Kasım (Nov 11)
-        $is_holiday = (
-            ($month==1 && $day==1) ||
-            ($month==2 && $day==14) ||
-            ($month==10 && $day==31) ||
-            ($month==11 && $day==11)
+        self::process_daily_cron();
+    }
+
+    /**
+     * Registers placeholder descriptions for the admin template editor.
+     */
+    public static function describe_placeholders($placeholders) {
+        $defaults = array(
+            '{name}'                    => __('Subscriber name', 'wrpa'),
+            '{email}'                   => __('Subscriber email address', 'wrpa'),
+            '{start_date}'              => __('Subscription start date', 'wrpa'),
+            '{expiry_date}'             => __('Subscription expiry date', 'wrpa'),
+            '{days_left}'               => __('Days remaining until expiry', 'wrpa'),
+            '{first_subscription_date}' => __('First subscription date', 'wrpa'),
+            '{plan_type}'               => __('Plan type slug (trial/monthly/yearly)', 'wrpa'),
+            '{plan_label}'              => __('Plan type label', 'wrpa'),
+            '{subscribe_url}'           => __('Subscription page URL', 'wrpa'),
+            '{site_name}'               => __('Site name', 'wrpa'),
+            '{site_url}'                => __('Site URL', 'wrpa'),
+            '{sender_name}'             => __('Email sender name', 'wrpa'),
+            '{sender_email}'            => __('Email sender address', 'wrpa'),
+            '{smtp_host}'               => __('FluentSMTP host', 'wrpa'),
+            '{smtp_port}'               => __('FluentSMTP port', 'wrpa'),
+            '{smtp_tls}'                => __('FluentSMTP TLS status', 'wrpa'),
+            '{smtp_encryption}'         => __('FluentSMTP encryption value', 'wrpa'),
+            '{order_id}'                => __('Related WooCommerce order ID', 'wrpa'),
         );
 
-        if ( ! $is_holiday ) return;
+        return array_merge($placeholders, $defaults);
+    }
+
+    /**
+     * Retrieves every user with tracked premium access metadata.
+     */
+    private static function get_relevant_users() {
+        return get_users(array(
+            'meta_key'     => 'wr_premium_access_expiry',
+            'meta_compare' => 'EXISTS',
+            'fields'       => array('ID', 'user_email', 'display_name'),
+            'number'       => -1,
+        ));
+    }
+
+    /**
+     * Checks if an email type has already been sent today.
+     */
+    private static function was_email_sent_today($user_id, $meta_key) {
+        $last = (int) get_user_meta($user_id, $meta_key, true);
+        if ( ! $last ) {
+            return false;
+        }
+
+        $now = current_time('timestamp');
+        return wp_date('Y-m-d', $last) === wp_date('Y-m-d', $now);
+    }
+
+    /**
+     * Stores the timestamp for the last email dispatch.
+     */
+    private static function mark_email_sent($user_id, $meta_key) {
+        update_user_meta($user_id, $meta_key, current_time('timestamp'));
+    }
+
+    /**
+     * Sends a template by applying placeholders to both subject and body.
+     */
+    private static function send_template($user_id, $template_key, $subject_key, array $extra = array()) {
+        $user = get_user_by('id', $user_id);
+        if ( ! $user || empty($user->user_email) ) {
+            return false;
+        }
+
+        $replacements = self::placeholder_pairs($user_id, $extra);
+        $subject      = self::render_subject($subject_key, $replacements);
+        $body         = self::render_body($template_key, $replacements);
+
+        if ( '' === $body ) {
+            return false;
+        }
 
-        $s = WRPA_Core::get_settings();
-        $tpl = $s['tpl_holiday'] ?? '';
-        $users = get_users(array('fields'=>array('ID','user_email')));
+        $sent = wp_mail($user->user_email, $subject, $body, self::headers());
 
-        foreach ($users as $u) {
-            $body = WRPA_Core::fill_placeholders($tpl, $u->ID);
-            wp_mail($u->user_email, 'Greetings from Wisdom Rain', $body, self::headers());
+        if ( $sent ) {
+            do_action('wrpa_email_sent', $subject_key, $user_id, $template_key);
         }
+
+        return $sent;
+    }
+
+    /**
+     * Compiles placeholder pairs for template rendering.
+     */
+    private static function placeholder_pairs($user_id, array $extra = array()) {
+        $user      = get_user_by('id', $user_id);
+        $settings  = WRPA_Core::get_settings();
+        $mailer    = self::get_mailer_settings();
+        $now       = current_time('timestamp');
+        $start     = (int) get_user_meta($user_id, 'wr_premium_access_start', true);
+        $expiry    = (int) get_user_meta($user_id, 'wr_premium_access_expiry', true);
+        $first     = (int) get_user_meta($user_id, '_wrpa_first_subscription_date', true);
+        $plan_slug = (string) get_user_meta($user_id, 'wrpa_plan_type', true);
+
+        $plan_labels  = self::plan_labels();
+        $plan_label   = $plan_labels[ $plan_slug ] ?? ( $plan_slug ? ucfirst($plan_slug) : '' );
+        $subscribe    = $settings['subscribe_url'] ?? home_url('/subscribe');
+        $subscribe    = esc_url($subscribe);
+        $site_name    = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
+        $site_url     = home_url('/');
+        $days_left    = $expiry > $now ? (int) floor(($expiry - $now) / DAY_IN_SECONDS) : 0;
+        $tls_label    = $mailer['tls'] ? __('Enabled', 'wrpa') : __('Disabled', 'wrpa');
+
+        $pairs = array(
+            '{name}'                    => $user ? $user->display_name : '',
+            '{email}'                   => $user ? $user->user_email : '',
+            '{start_date}'              => WRPA_Core::fmt($start),
+            '{expiry_date}'             => WRPA_Core::fmt($expiry),
+            '{days_left}'               => $days_left,
+            '{first_subscription_date}' => $first ? WRPA_Core::fmt($first) : '-',
+            '{plan_type}'               => $plan_slug,
+            '{plan_label}'              => $plan_label,
+            '{subscribe_url}'           => $subscribe,
+            '{site_name}'               => $site_name,
+            '{site_url}'                => $site_url,
+            '{sender_name}'             => $mailer['sender_name'],
+            '{sender_email}'            => $mailer['sender_email'],
+            '{smtp_host}'               => $mailer['host'],
+            '{smtp_port}'               => $mailer['port'],
+            '{smtp_tls}'                => $tls_label,
+            '{smtp_encryption}'         => $mailer['encryption'],
+            '{order_id}'                => isset($extra['order_id']) ? (string) $extra['order_id'] : '',
+        );
+
+        if ( isset($extra['custom']) && is_array($extra['custom']) ) {
+            $pairs = array_merge($pairs, $extra['custom']);
+        }
+
+        return apply_filters('wrpa_email_placeholder_values', $pairs, $user_id, $extra);
+    }
+
+    /**
+     * Renders a subject by applying placeholders.
+     */
+    private static function render_subject($subject_key, array $replacements) {
+        $subjects = self::subject_map();
+        $subject  = $subjects[ $subject_key ] ?? '{site_name}';
+
+        return strtr($subject, $replacements);
+    }
+
+    /**
+     * Renders the HTML email body for the provided template key.
+     */
+    private static function render_body($template_key, array $replacements) {
+        $template = self::get_template($template_key);
+        if ( '' === $template ) {
+            return '';
+        }
+
+        $content = strtr($template, $replacements);
+        return self::normalize_html($content);
+    }
+
+    /**
+     * Retrieves a template either from settings or default fallbacks.
+     */
+    private static function get_template($key) {
+        $settings   = WRPA_Core::get_settings();
+        $defaults   = self::default_templates();
+        $template   = $settings[ $key ] ?? '';
+
+        if ( '' === trim($template) && isset($defaults[ $key ]) ) {
+            $template = $defaults[ $key ];
+        }
+
+        return apply_filters('wrpa_email_template', $template, $key, $settings);
+    }
+
+    /**
+     * Wraps plain content in a minimal HTML layout when needed.
+     */
+    private static function normalize_html($content) {
+        $content = trim($content);
+        if ( '' === $content ) {
+            return '';
+        }
+
+        if ( stripos($content, '<html') !== false ) {
+            return $content;
+        }
+
+        if ( function_exists('wpautop') ) {
+            $content = wpautop($content);
+        }
+
+        return '<!DOCTYPE html><html><body>' . $content . '</body></html>';
+    }
+
+    /**
+     * Provides translated subject templates.
+     */
+    private static function subject_map() {
+        return array(
+            'welcome' => __('Welcome to {site_name}', 'wrpa'),
+            'thanks'  => __('Thank you for joining {site_name}', 'wrpa'),
+            'expiry'  => __('Your access ends on {expiry_date}', 'wrpa'),
+            'expired' => __('Your {site_name} access has expired', 'wrpa'),
+            'renewal' => __('We would love to welcome you back to {site_name}', 'wrpa'),
+        );
+    }
+
+    /**
+     * Default plan labels for placeholders.
+     */
+    private static function plan_labels() {
+        return array(
+            'trial'   => __('Trial', 'wrpa'),
+            'monthly' => __('Monthly', 'wrpa'),
+            'yearly'  => __('Yearly', 'wrpa'),
+        );
+    }
+
+    /**
+     * Provides default HTML templates for each email type.
+     */
+    private static function default_templates() {
+        return array(
+            'tpl_welcome' => <<<'HTML'
+<!DOCTYPE html>
+<html>
+<head>
+    <meta charset="UTF-8" />
+    <title>Welcome to {site_name}</title>
+</head>
+<body style="font-family: Arial, Helvetica, sans-serif; color: #202124; line-height: 1.6;">
+    <h1 style="font-size: 24px; margin-bottom: 16px;">Welcome, {name}!</h1>
+    <p>Thank you for joining <strong>{site_name}</strong>. Your premium access has started and you can now explore every session without limits.</p>
+    <p>Your plan: <strong>{plan_label}</strong></p>
+    <p>Access began on <strong>{start_date}</strong> and will renew on <strong>{expiry_date}</strong>.</p>
+    <p>If you ever need help, simply reply to this email.</p>
+    <p style="margin-top: 24px;">Warmly,<br />{sender_name}</p>
+</body>
+</html>
+HTML,
+            'tpl_thanks' => <<<'HTML'
+<!DOCTYPE html>
+<html>
+<head>
+    <meta charset="UTF-8" />
+    <title>Thank you from {site_name}</title>
+</head>
+<body style="font-family: Arial, Helvetica, sans-serif; color: #202124; line-height: 1.6;">
+    <h1 style="font-size: 24px; margin-bottom: 16px;">Thank you, {name}!</h1>
+    <p>Your order ({order_id}) is confirmed. We are thrilled to have you with us.</p>
+    <p>Your current plan is <strong>{plan_label}</strong> and your access is active until <strong>{expiry_date}</strong>.</p>
+    <p>Enjoy the full Wisdom Rain library whenever you need a mindful moment.</p>
+    <p style="margin-top: 24px;">With gratitude,<br />{sender_name}</p>
+</body>
+</html>
+HTML,
+            'tpl_expiry' => <<<'HTML'
+<!DOCTYPE html>
+<html>
+<head>
+    <meta charset="UTF-8" />
+    <title>Your {site_name} access is ending soon</title>
+</head>
+<body style="font-family: Arial, Helvetica, sans-serif; color: #202124; line-height: 1.6;">
+    <h1 style="font-size: 22px; margin-bottom: 16px;">Just {days_left} day(s) left, {name}</h1>
+    <p>Your {plan_label} access will expire on <strong>{expiry_date}</strong>. Keep your daily practice going by renewing before your time runs out.</p>
+    <p><a href="{subscribe_url}" style="display: inline-block; padding: 10px 18px; background: #4a5bd7; color: #ffffff; text-decoration: none; border-radius: 4px;">Renew my access</a></p>
+    <p>We’re always here if you have questions.</p>
+</body>
+</html>
+HTML,
+            'tpl_expired' => <<<'HTML'
+<!DOCTYPE html>
+<html>
+<head>
+    <meta charset="UTF-8" />
+    <title>Your {site_name} access has expired</title>
+</head>
+<body style="font-family: Arial, Helvetica, sans-serif; color: #202124; line-height: 1.6;">
+    <h1 style="font-size: 22px; margin-bottom: 16px;">We miss you already, {name}</h1>
+    <p>Your {plan_label} access ended on <strong>{expiry_date}</strong>. Renew today to jump back into daily calm and inspiration.</p>
+    <p><a href="{subscribe_url}" style="display: inline-block; padding: 10px 18px; background: #4a5bd7; color: #ffffff; text-decoration: none; border-radius: 4px;">Renew membership</a></p>
+    <p>If something went wrong or you need assistance, reply to this email and we’ll help right away.</p>
+</body>
+</html>
+HTML,
+            'tpl_renewal' => <<<'HTML'
+<!DOCTYPE html>
+<html>
+<head>
+    <meta charset="UTF-8" />
+    <title>Come back to {site_name}</title>
+</head>
+<body style="font-family: Arial, Helvetica, sans-serif; color: #202124; line-height: 1.6;">
+    <h1 style="font-size: 22px; margin-bottom: 16px;">Ready to return, {name}?</h1>
+    <p>It’s been a week since your {plan_label} access ended. Restart your practice in just a few clicks and enjoy everything new in the library.</p>
+    <p><a href="{subscribe_url}" style="display: inline-block; padding: 10px 18px; background: #4a5bd7; color: #ffffff; text-decoration: none; border-radius: 4px;">Reactivate my access</a></p>
+    <p>Need help choosing the right plan? Our team is an email away.</p>
+    <p style="margin-top: 24px;">With appreciation,<br />{sender_name}</p>
+</body>
+</html>
+HTML,
+        );
     }
 }
