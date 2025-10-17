<?php
if ( ! defined('ABSPATH') ) exit;

class WRPA_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function menu() {
        // Ana menü: Dashboard'un hemen altına
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

        // Fields (IDs & durations)
        self::field('product_trial_id', 'Deneme Ürün (Trial) ID', 'number');
        self::field('trial_days', 'Süre (gün)', 'number');
        self::field('trial_minutes', 'Süre (dakika)', 'number');

        self::field('product_monthly_id', 'Aylık Ürün (Monthly) ID', 'number');
        self::field('monthly_days', 'Süre (gün)', 'number');
        self::field('monthly_minutes', 'Süre (dakika)', 'number');

        self::field('product_yearly_id', 'Yıllık Ürün (Yearly) ID', 'number');
        self::field('yearly_days', 'Süre (gün)', 'number');

        add_settings_section('wrpa_sender', 'Gönderici Bilgileri', '__return_false', 'wrpa');
        self::field('sender_name', 'Gönderen Adı');
        self::field('sender_email', 'Gönderen E-posta', 'email');

        add_settings_section('wrpa_protect', 'Koruma Ayarları', '__return_false', 'wrpa');
        add_settings_field('protected_cpts', 'Korumalı CPT’ler', [__CLASS__, 'field_cpts'], 'wrpa', 'wrpa_protect');
        add_settings_field('subscribe_url', 'Abonelik Sayfası URL', [__CLASS__, 'render_field'], 'wrpa', 'wrpa_protect', ['key' => 'subscribe_url']);
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

    public static function field_cpts() {
        $s = WRPA_Core::get_settings();
        $all = array('library', 'music', 'meditation', 'children_story', 'sleep_story', 'magazine');
        $sel = (array) ($s['protected_cpts'] ?? []);
        foreach ($all as $cpt) {
            printf(
                '<label style="margin-right:10px;"><input type="checkbox" name="wrpa_settings[protected_cpts][]" value="%s" %s /> %s</label>',
                esc_attr($cpt),
                checked(in_array($cpt, $sel, true), true, false),
                esc_html($cpt)
            );
        }
    }

    public static function settings_page() {
        ?>
        <div class="wrap">
            <h1>Wisdom Rain Premium Access – Settings</h1>
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

    public static function members_page() {
        if ( ! current_user_can('manage_options') ) return;

        $users = get_users(array('fields' => array('ID', 'user_email', 'display_name', 'user_login')));
        $members = array();

        foreach ($users as $u) {
            $start  = (int) get_user_meta($u->ID, 'wr_premium_access_start', true);
            $exp    = (int) get_user_meta($u->ID, 'wr_premium_access_expiry', true);
            $plan   = trim((string) get_user_meta($u->ID, 'wrpa_plan_name', true));
            $days   = $exp ? floor(max(0, $exp - time()) / DAY_IN_SECONDS) : 0;
            $status = ($exp && time() < $exp) ? 'Active' : 'Expired';

            printf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td></tr>',
                esc_html($u->display_name),
                esc_html($u->user_email),
                esc_html(WRPA_Core::fmt($start)),
                esc_html(WRPA_Core::fmt($exp)),
                (int) $days,
                esc_html($status)
            ); // ✅ Eksik kapanış eklendi

            $members[] = array(
                'ID'        => $u->ID,
                'name'      => $u->display_name !== '' ? $u->display_name : $u->user_login,
                'email'     => $u->user_email,
                'plan'      => $plan !== '' ? $plan : 'N/A',
                'start_raw' => $start,
                'start'     => WRPA_Core::fmt($start),
                'expiry_raw'=> $exp,
                'expiry'    => WRPA_Core::fmt($exp),
                'days_left' => (int) $days,
                'status'    => $status,
            );
        }

        // Filtre
        $status_filter = '';
        if ( isset($_GET['status_filter']) ) {
            $status_filter = sanitize_key(wp_unslash($_GET['status_filter']));
        } elseif ( isset($_POST['status_filter']) ) {
            $status_filter = sanitize_key(wp_unslash($_POST['status_filter']));
        }
        $allowed_statuses = array('active', 'expired');
        if ($status_filter && !in_array($status_filter, $allowed_statuses, true)) {
            $status_filter = '';
        }

        if ($status_filter) {
            $members = array_filter($members, static function ($member) use ($status_filter) {
                return strtolower($member['status']) === $status_filter;
            });
            $members = array_values($members);
        }

        // CSV Export
        if (isset($_POST['wrpa_export_csv'])) {
            check_admin_referer('wrpa_members_export', 'wrpa_members_nonce');
            nocache_headers();
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="wrpa_members.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, array('Name', 'Email', 'Plan', 'Start Date', 'Expiry', 'Days Left', 'Status'));
            foreach ($members as $member) {
                fputcsv($out, array(
                    $member['name'],
                    $member['email'],
                    $member['plan'],
                    $member['start'],
                    $member['expiry'],
                    $member['days_left'],
                    $member['status'],
                ));
            }
            fclose($out);
            exit;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Members', 'wrpa') . '</h1>';

        // Filtre formu
        echo '<form method="get" style="margin-bottom:15px;">';
        if (isset($_GET['page'])) {
            printf('<input type="hidden" name="page" value="%s" />', esc_attr(sanitize_text_field(wp_unslash($_GET['page']))));
        }
        echo '<label for="wrpa-status-filter" class="screen-reader-text">' . esc_html__('Filter by status', 'wrpa') . '</label>';
        echo '<select id="wrpa-status-filter" name="status_filter">';
        $options = array(
            ''        => __('All statuses', 'wrpa'),
            'active'  => __('Active', 'wrpa'),
            'expired' => __('Expired', 'wrpa'),
        );
        foreach ($options as $value => $label) {
            printf('<option value="%s" %s>%s</option>',
                esc_attr($value),
                selected($status_filter, $value, false),
                esc_html($label)
            );
        }
        echo '</select> ';
        submit_button(__('Filter'), 'secondary', '', false);
        echo '</form>';

        // CSV Export formu
        echo '<form method="post" style="margin-bottom:15px;">';
        wp_nonce_field('wrpa_members_export', 'wrpa_members_nonce');
        if ($status_filter) {
            printf('<input type="hidden" name="status_filter" value="%s" />', esc_attr($status_filter));
        }
        submit_button(__('Export CSV'), 'primary', 'wrpa_export_csv', false);
        echo '</form>';

        // Tablo
        echo '<table class="widefat striped"><thead>';
        printf(
            '<tr><th>%1$s</th><th>%2$s</th><th>%3$s</th><th>%4$s</th><th>%5$s</th><th>%6$s</th><th>%7$s</th></tr></thead><tbody>',
            esc_html__('Name', 'wrpa'),
            esc_html__('Email', 'wrpa'),
            esc_html__('Plan', 'wrpa'),
            esc_html__('Start Date', 'wrpa'),
            esc_html__('Expiry', 'wrpa'),
            esc_html__('Days Left', 'wrpa'),
            esc_html__('Status', 'wrpa')
        );

        if ($members) {
            foreach ($members as $member) {
                printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td></tr>',
                    esc_html($member['name']),
                    esc_html($member['email']),
                    esc_html($member['plan']),
                    esc_html($member['start']),
                    esc_html($member['expiry']),
                    (int) $member['days_left'],
                    esc_html($member['status'])
                );
            }
        } else {
            echo '<tr><td colspan="7">' . esc_html__('No members found.', 'wrpa') . '</td></tr>';
        }

        echo '</tbody></table></div>';
    }

    public static function emails_page() {
        if (isset($_POST['wrpa_save_emails']) && check_admin_referer('wrpa_save_emails_nonce')) {
            $s = WRPA_Core::get_settings();
            $s['tpl_welcome'] = wp_kses_post(wp_unslash($_POST['tpl_welcome'] ?? ''));
            $s['tpl_thanks']  = wp_kses_post(wp_unslash($_POST['tpl_thanks'] ?? ''));
            $s['tpl_expiry']  = wp_kses_post(wp_unslash($_POST['tpl_expiry'] ?? ''));
            $s['tpl_holiday'] = wp_kses_post(wp_unslash($_POST['tpl_holiday'] ?? ''));
            WRPA_Core::update_settings($s);
            echo '<div class="updated"><p>Templates saved.</p></div>';
        }
        $s = WRPA_Core::get_settings();
        ?>
        <div class="wrap">
            <h1>Email Templates</h1>
            <p>Kullanılabilir yer tutucular: <code>{name}</code>, <code>{email}</code>, <code>{start_date}</code>, <code>{expiry_date}</code>, <code>{days_left}</code></p>
            <form method="post">
                <?php wp_nonce_field('wrpa_save_emails_nonce'); ?>
                <table class="form-table">
                    <tr><th>Welcome</th><td><textarea name="tpl_welcome" rows="6" class="large-text"><?php echo esc_textarea($s['tpl_welcome'] ?? ''); ?></textarea></td></tr>
                    <tr><th>Thank You</th><td><textarea name="tpl_thanks" rows="6" class="large-text"><?php echo esc_textarea($s['tpl_thanks'] ?? ''); ?></textarea></td></tr>
                    <tr><th>Expiry Reminder</th><td><textarea name="tpl_expiry" rows="6" class="large-text"><?php echo esc_textarea($s['tpl_expiry'] ?? ''); ?></textarea></td></tr>
                    <tr><th>Holiday Greeting</th><td><textarea name="tpl_holiday" rows="6" class="large-text"><?php echo esc_textarea($s['tpl_holiday'] ?? ''); ?></textarea></td></tr>
                </table>
                <p><button class="button button-primary" name="wrpa_save_emails" value="1">Save Templates</button></p>
            </form>
        </div>
        <?php
    }
}
