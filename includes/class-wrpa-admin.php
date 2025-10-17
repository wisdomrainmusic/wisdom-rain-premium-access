<?php
if (!defined('ABSPATH')) exit;

class WRPA_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);

        add_filter('manage_users_columns', [__CLASS__, 'add_first_subscription_column']);
        add_filter('manage_users_custom_column', [__CLASS__, 'render_first_subscription_column'], 10, 3);

        add_action('admin_post_wrpa_export_csv', [__CLASS__, 'export_members_csv']);
    }

    /* ======================================================
     🧭 Menü ve Sayfalar
    ====================================================== */
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

    /* ======================================================
     ⚙️ Ayarlar Alanı
    ====================================================== */
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
        self::field('yearly_minutes', 'Süre (dakika)', 'number');
    }

    private static function field($id, $label, $type) {
        add_settings_field(
            $id,
            esc_html($label),
            function() use ($id, $type) {
                $s = WRPA_Core::get_settings();
                $v = esc_attr($s[$id] ?? '');
                printf(
                    '<input type="%s" name="wrpa_settings[%s]" value="%s" class="regular-text" />',
                    esc_attr($type),
                    esc_attr($id),
                    $v
                );
            },
            'wrpa',
            'wrpa_main'
        );
    }

    /* ======================================================
     👥 Members Sayfası
    ====================================================== */
    public static function members_page() {
        $users = get_users();
        $members = [];

        foreach ($users as $u) {
            // 🧾 WooCommerce Subscriptions
            $subscriptions = function_exists('wcs_get_users_subscriptions') ? wcs_get_users_subscriptions($u->ID) : [];

            $plan = '—';
            if (!empty($subscriptions)) {
                foreach ($subscriptions as $subscription) {
                    if (is_object($subscription) && method_exists($subscription, 'has_status') && $subscription->has_status(['active', 'on-hold', 'pending-cancel', 'expired'])) {
                        $items = $subscription->get_items();
                        if (!empty($items)) {
                            $first_item = reset($items);
                            $plan = (is_object($first_item) && method_exists($first_item, 'get_name')) ? $first_item->get_name() : '—';
                        }
                        break;
                    }
                }
            }
            if ($plan === '—') {
                $plan = get_user_meta($u->ID, 'wrpa_plan_name', true) ?: '—';
            }

            $start = get_user_meta($u->ID, 'wr_premium_access_start', true);
            $exp = get_user_meta($u->ID, 'wr_premium_access_expiry', true);

            $start_fmt = $start ? WRPA_Core::fmt($start) : '—';
            $exp_fmt   = $exp ? WRPA_Core::fmt($exp) : '—';
            $days      = $exp ? floor(($exp - time()) / 86400) : 0;
            $status    = ($exp && time() < $exp) ? 'Active' : 'Expired';

            // 📅 First subscription date (meta fallback)
            $first_subscription_meta = get_user_meta($u->ID, 'wrpa_first_subscription_date', true);
            if (!empty($first_subscription_meta)) {
                $first_subscription = mysql2date(get_option('date_format') . ' H:i', $first_subscription_meta, false);
            } else {
                $first_subscription = '—';
                if (!empty($subscriptions)) {
                    $first_subscription_obj = reset($subscriptions);
                    if (is_object($first_subscription_obj) && method_exists($first_subscription_obj, 'get_date_created')) {
                        $created = $first_subscription_obj->get_date_created();
                        if ($created && method_exists($created, 'date_i18n')) {
                            $first_subscription = $created->date_i18n(get_option('date_format') . ' H:i');
                        }
                    }
                }
            }

            // 🧍 Member array
            $display_name = $u->display_name;
            if (empty($display_name) || filter_var($display_name, FILTER_VALIDATE_EMAIL)) {
                $display_name = ucfirst($u->user_login);
            }

            $members[] = [
                'ID'        => $u->ID,
                'name'      => $display_name,
                'email'     => $u->user_email,
                'plan'      => $plan,
                'start'     => $start_fmt,
                'expiry'    => $exp_fmt,
                'days_left' => $days,
                'status'    => $status,
                'first_subscription' => $first_subscription,
            ];
        }

        $status_filter = '';
        if (isset($_GET['status_filter'])) {
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
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Members', 'wrpa'); ?></h1>

            <form method="get" style="margin-bottom:15px;">
                <?php if (isset($_GET['page'])) : ?>
                    <input type="hidden" name="page" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['page']))); ?>">
                <?php endif; ?>
                <label for="wrpa-status-filter" class="screen-reader-text"><?php echo esc_html__('Filter by status', 'wrpa'); ?></label>
                <select id="wrpa-status-filter" name="status_filter">
                    <option value=""><?php echo esc_html__('All statuses', 'wrpa'); ?></option>
                    <option value="active" <?php selected($status_filter, 'active'); ?>><?php echo esc_html__('Active', 'wrpa'); ?></option>
                    <option value="expired" <?php selected($status_filter, 'expired'); ?>><?php echo esc_html__('Expired', 'wrpa'); ?></option>
                </select>
                <?php submit_button(__('Filter'), 'secondary', '', false); ?>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:15px;">
                <input type="hidden" name="action" value="wrpa_export_csv">
                <?php wp_nonce_field('wrpa_export_csv', 'wrpa_export_csv_nonce'); ?>
                <?php if ($status_filter) : ?>
                    <input type="hidden" name="status_filter" value="<?php echo esc_attr($status_filter); ?>">
                <?php endif; ?>
                <?php submit_button(__('Export CSV', 'wrpa'), 'primary', 'wrpa_export_csv', false); ?>
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
                        <th><?php echo esc_html__('First Subscription Date', 'wrpa'); ?></th>
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
                                <td><?php echo esc_html($m['first_subscription']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="8"><?php echo esc_html__('No members found.', 'wrpa'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /* ======================================================
     🧱 Üyeler Tablosu ve CSV Export
    ====================================================== */
    public static function add_first_subscription_column($columns) {
        $columns['wrpa_first_subscription_date'] = __('First Subscription Date', 'wrpa');
        return $columns;
    }

    public static function render_first_subscription_column($value, $column_name, $user_id) {
        if ($column_name !== 'wrpa_first_subscription_date') return $value;

        $date = get_user_meta($user_id, 'wrpa_first_subscription_date', true);
        if (empty($date)) return '—';

        return esc_html(mysql2date('Y-m-d H:i', $date, false));
    }

    public static function export_members_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export this data.', 'wrpa'));
        }

        check_admin_referer('wrpa_export_csv', 'wrpa_export_csv_nonce');

        $status_filter = '';
        if (isset($_POST['status_filter'])) {
            $status_filter = sanitize_key(wp_unslash($_POST['status_filter']));
        }

        $users = get_users();
        $filename = 'wrpa_members_export_' . gmdate('Y-m-d') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF"); // ✳️ Excel UTF-8 BOM

        fputcsv($output, ['Name', 'Email', 'First Subscription Date']);

        foreach ($users as $user) {
            $first_date = get_user_meta($user->ID, 'wrpa_first_subscription_date', true);
            $formatted = $first_date ? mysql2date('Y-m-d H:i', $first_date, false) : '—';

            if ($status_filter) {
                $expiry = (int) get_user_meta($user->ID, 'wr_premium_access_expiry', true);
                $is_active = ($expiry && time() < $expiry);
                if ($status_filter === 'active' && !$is_active) continue;
                if ($status_filter === 'expired' && $is_active) continue;
            }

            fputcsv($output, [
                $user->display_name ?: $user->user_login,
                $user->user_email,
                $formatted
            ]);
        }

        fclose($output);
        exit;
    }

    /* ======================================================
     ⚙️ SETTINGS & EMAIL PAGES
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
        if (isset($_POST['wrpa_save_emails']) && check_admin_referer('wrpa_save_emails_nonce')) {
            $s = WRPA_Core::get_settings();
            $s['tpl_welcome'] = wp_kses_post(wp_unslash($_POST['tpl_welcome'] ?? ''));
            $s['tpl_thanks']  = wp_kses_post(wp_unslash($_POST['tpl_thanks'] ?? ''));
            $s['tpl_expiry']  = wp_kses_post(wp_unslash($_POST['tpl_expiry'] ?? ''));
            update_option('wrpa_settings', $s);
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Email Templates', 'wrpa'); ?></h1>
            <form method="post">
                <?php wp_nonce_field('wrpa_save_emails_nonce'); ?>
                <textarea name="tpl_welcome" rows="6" class="large-text" placeholder="Welcome Email Template"><?php echo esc_textarea($s['tpl_welcome'] ?? ''); ?></textarea>
                <textarea name="tpl_thanks" rows="6" class="large-text" placeholder="Thank You Email Template"><?php echo esc_textarea($s['tpl_thanks'] ?? ''); ?></textarea>
                <textarea name="tpl_expiry" rows="6" class="large-text" placeholder="Expiry Reminder Email"><?php echo esc_textarea($s['tpl_expiry'] ?? ''); ?></textarea>
                <?php submit_button(__('Save Templates', 'wrpa')); ?>
            </form>
        </div>
        <?php
    }
}
