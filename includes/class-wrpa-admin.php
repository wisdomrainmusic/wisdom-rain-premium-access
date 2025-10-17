<?php
if ( ! defined('ABSPATH') ) exit;

class WRPA_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function menu() {
        add_menu_page(
            'WR Premium',
            'WR Premium',
            'manage_options',
            'wrpa',
            [__CLASS__, 'settings_page'],
            'dashicons-shield-alt',
            3
        );
        add_submenu_page('wrpa', 'Settings', 'Settings', 'manage_options', 'wrpa', [__CLASS__, 'settings_page']);
        add_submenu_page('wrpa', 'Members', 'Members', 'manage_options', 'wrpa-members', [__CLASS__, 'members_page']);
        add_submenu_page('wrpa', 'Email Templates', 'Email Templates', 'manage_options', 'wrpa-emails', [__CLASS__, 'emails_page']);
    }

    public static function register_settings() {
        register_setting('wrpa_group', 'wrpa_settings');

        add_settings_section('wrpa_main', 'Abonelik Süreleri', '__return_false', 'wrpa');

        // 🧩 Trial
        self::field('product_trial_id', 'Deneme Ürün (Trial) ID', 'number');
        self::field('trial_days', 'Süre (gün)', 'number');
        self::field('trial_minutes', 'Süre (dakika)', 'number');

        // 🧩 Monthly
        self::field('product_monthly_id', 'Aylık Ürün (Monthly) ID', 'number');
        self::field('monthly_days', 'Süre (gün)', 'number');
        self::field('monthly_minutes', 'Süre (dakika)', 'number');

        // 🧩 Yearly
        self::field('product_yearly_id', 'Yıllık Ürün (Yearly) ID', 'number');
        self::field('yearly_days', 'Süre (gün)', 'number');

        add_settings_section('wrpa_sender', 'Gönderici Bilgileri', '__return_false', 'wrpa');
        self::field('sender_name', 'Gönderen Adı');
        self::field('sender_email', 'Gönderen E-posta', 'email');
    }

    private static function field($key, $label, $type = 'text') {
        add_settings_field($key, $label, [__CLASS__, 'render_field'], 'wrpa', 'wrpa_main', compact('key', 'type'));
    }

    public static function render_field($args) {
        $s = WRPA_Core::get_settings();
        $key = $args['key'];
        $type = $args['type'] ?? 'text';
        printf(
            '<input type="%s" name="wrpa_settings[%s]" value="%s" class="regular-text" />',
            esc_attr($type),
            esc_attr($key),
            esc_attr($s[$key] ?? '')
        );
    }

    /* ======================================================
     🧩 MEMBERS PAGE – v1.4.9.2 FINAL
    ====================================================== */
    public static function members_page() {
        if ( ! current_user_can('manage_options') ) return;

        ob_start();
        $users = get_users(['fields' => ['ID', 'user_email', 'display_name', 'user_login']]);
        $members = [];

        foreach ($users as $u) {
            $start  = (int) get_user_meta($u->ID, 'wr_premium_access_start', true);
            $exp    = (int) get_user_meta($u->ID, 'wr_premium_access_expiry', true);
            $plan   = trim((string) get_user_meta($u->ID, 'wrpa_plan_name', true));
            if (!$plan) $plan = get_user_meta($u->ID, 'wr_premium_access_plan', true);
            if (!$plan) $plan = 'Unknown';

            $days   = $exp ? floor(max(0, $exp - time()) / DAY_IN_SECONDS) : 0;
            $status = ($exp && time() < $exp) ? 'Active' : 'Expired';

            $members[] = [
                'ID'        => $u->ID,
                'name'      => $u->display_name !== '' ? $u->display_name : $u->user_login,
                'email'     => $u->user_email,
                'plan'      => $plan,
                'start'     => WRPA_Core::fmt($start),
                'expiry'    => WRPA_Core::fmt($exp),
                'days_left' => (int) $days,
                'status'    => $status,
            ];
        }

        $status_filter = '';
        if ( isset($_GET['status_filter']) ) {
            $status_filter = sanitize_key(wp_unslash($_GET['status_filter']));
        }
        $allowed_statuses = ['active', 'expired'];
        if ($status_filter && !in_array($status_filter, $allowed_statuses, true)) {
            $status_filter = '';
        }

        if ($status_filter) {
            $members = array_filter($members, static fn($member) => strtolower($member['status']) === $status_filter);
            $members = array_values($members);
        }

        // 🧾 CSV Export (UTF-8 + sade)
        if (isset($_POST['wrpa_export_csv'])) {
            check_admin_referer('wrpa_members_export', 'wrpa_members_nonce');
            nocache_headers();
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="wrpa_members.csv"');
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Name', 'Email', 'Register Date']);
            foreach ($members as $m) {
                $user = get_userdata($m['ID']);
                $register_date = $user ? $user->user_registered : '';
                fputcsv($out, [$m['name'], $m['email'], $register_date]);
            }
            fclose($out);
            exit;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Members', 'wrpa'); ?></h1>

            <form method="get" style="margin-bottom:15px;">
                <?php
                if (isset($_GET['page'])) {
                    printf('<input type="hidden" name="page" value="%s" />', esc_attr(sanitize_text_field(wp_unslash($_GET['page']))));
                }
                ?>
                <label for="wrpa-status-filter" class="screen-reader-text"><?php echo esc_html__('Filter by status', 'wrpa'); ?></label>
                <select id="wrpa-status-filter" name="status_filter">
                    <option value=""><?php echo esc_html__('All statuses', 'wrpa'); ?></option>
                    <option value="active" <?php selected($status_filter, 'active'); ?>><?php echo esc_html__('Active', 'wrpa'); ?></option>
                    <option value="expired" <?php selected($status_filter, 'expired'); ?>><?php echo esc_html__('Expired', 'wrpa'); ?></option>
                </select>
                <?php submit_button(__('Filter'), 'secondary', '', false); ?>
            </form>

            <form method="post" style="margin-bottom:15px;">
                <?php wp_nonce_field('wrpa_members_export', 'wrpa_members_nonce'); ?>
                <?php if ($status_filter) : ?>
                    <input type="hidden" name="status_filter" value="<?php echo esc_attr($status_filter); ?>">
                <?php endif; ?>
                <?php submit_button(__('Export CSV'), 'primary', 'wrpa_export_csv', false); ?>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Name', 'wrpa'); ?></th>
                        <th><?php echo esc_html__('Email', 'wrpa'); ?></th>
                        <th><?php echo esc_html__('Plan', 'wrpa'); ?></th>
                        <th><?php echo esc_html__('Start Date', 'wrpa'); ?></th>
                        <th><?php echo esc_html__('Expiry', 'wrpa'); ?></th>
                        <th><?php echo esc_html__('Days Left', 'wrpa'); ?></th>
                        <th><?php echo esc_html__('Status', 'wrpa'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($members) : ?>
                    <?php foreach ($members as $m) : ?>
                        <tr>
                            <td><?php echo esc_html($m['name']); ?></td>
                            <td><?php echo esc_html($m['email']); ?></td>
                            <td><?php echo esc_html($m['plan']); ?></td>
                            <td><?php echo esc_html($m['start']); ?></td>
                            <td><?php echo esc_html($m['expiry']); ?></td>
                            <td><?php echo (int) $m['days_left']; ?></td>
                            <td><?php echo esc_html($m['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="7"><?php echo esc_html__('No members found.', 'wrpa'); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        ob_end_flush();
    }

    /* ======================================================
     ⚙️ SETTINGS & EMAIL PAGES (Restored)
    ====================================================== */

    public static function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Wisdom Rain Premium Access – Settings', 'wrpa'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wrpa_group');
                do_settings_sections('wrpa');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function emails_page() {
        if ( isset($_POST['wrpa_save_emails']) && check_admin_referer('wrpa_save_emails_nonce') ) {
            $s = WRPA_Core::get_settings();
            $s['tpl_welcome'] = wp_kses_post( wp_unslash($_POST['tpl_welcome'] ?? '') );
            $s['tpl_thanks']  = wp_kses_post( wp_unslash($_POST['tpl_thanks'] ?? '') );
            $s['tpl_expiry']  = wp_kses_post( wp_unslash($_POST['tpl_expiry'] ?? '') );
            $s['tpl_holiday'] = wp_kses_post( wp_unslash($_POST['tpl_holiday'] ?? '') );
            WRPA_Core::update_settings($s);
            echo '<div class="updated"><p>Templates saved.</p></div>';
        }
        $s = WRPA_Core::get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Email Templates', 'wrpa'); ?></h1>
            <p><?php esc_html_e('Kullanılabilir yer tutucular: {name}, {email}, {start_date}, {expiry_date}, {days_left}', 'wrpa'); ?></p>
            <form method="post">
                <?php wp_nonce_field('wrpa_save_emails_nonce'); ?>
                <table class="form-table">
                    <tr><th>Welcome</th><td><textarea name="tpl_welcome" rows="6" class="large-text"><?php echo esc_textarea($s['tpl_welcome'] ?? ''); ?></textarea></td></tr>
                    <tr><th>Thank You</th><td><textarea name="tpl_thanks" rows="6" class="large-text"><?php echo esc_textarea($s['tpl_thanks'] ?? ''); ?></textarea></td></tr>
                    <tr><th>Expiry Reminder</th><td><textarea name="tpl_expiry" rows="6" class="large-text"><?php echo esc_textarea($s['tpl_expiry'] ?? ''); ?></textarea></td></tr>
                    <tr><th>Holiday Greeting</th><td><textarea name="tpl_holiday" rows="6" class="large-text"><?php echo esc_textarea($s['tpl_holiday'] ?? ''); ?></textarea></td></tr>
                </table>
                <?php submit_button(__('Save Templates', 'wrpa'), 'primary', 'wrpa_save_emails'); ?>
            </form>
        </div>
        <?php
    }
}
