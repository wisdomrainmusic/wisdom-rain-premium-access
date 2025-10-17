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

        self::field('product_trial_id', 'Deneme Ürün (Trial) ID', 'number');
        self::field('trial_days', 'Süre (gün)', 'number');
        self::field('product_monthly_id', 'Aylık Ürün (Monthly) ID', 'number');
        self::field('monthly_days', 'Süre (gün)', 'number');
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
     🧩 MEMBERS PAGE – v1.4.9 FINAL
    ====================================================== */
    public static function members_page() {
        if ( ! current_user_can('manage_options') ) return;

        ob_start(); // HTML sızıntısını önle
        $users = get_users(['fields' => ['ID', 'user_email', 'display_name', 'user_login']]);
        $members = [];

        foreach ($users as $u) {
            $start  = (int) get_user_meta($u->ID, 'wr_premium_access_start', true);
            $exp    = (int) get_user_meta($u->ID, 'wr_premium_access_expiry', true);
            $plan   = trim((string) get_user_meta($u->ID, 'wrpa_plan_name', true));

            // Alternatif plan meta anahtar kontrolü
            if (!$plan) {
                $plan = get_user_meta($u->ID, 'wr_premium_access_plan', true);
            }
            if (!$plan) {
                $plan = 'Unknown';
            }

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

        // Filtre
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

        // CSV Export – sade & düzgün
        if (isset($_POST['wrpa_export_csv'])) {
            check_admin_referer('wrpa_members_export', 'wrpa_members_nonce');
            nocache_headers();
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="wrpa_members.csv"');
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM

            // Başlıklar
            fputcsv($out, ['Name', 'Email', 'Register Date']);

            // Kullanıcı verileri
            foreach ($members as $m) {
                $user = get_userdata($m['ID']);
                $register_date = $user ? $user->user_registered : '';
                fputcsv($out, [
                    $m['name'],
                    $m['email'],
                    $register_date
                ]);
            }

            fclose($out);
            exit;
        }

        // HTML Çıktı
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Members', 'wrpa'); ?></h1>

            <form method="get" action="" style="margin-bottom:15px;">
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
}
