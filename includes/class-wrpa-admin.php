<?php
if ( ! defined('ABSPATH') ) exit;

class WRPA_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__,'menu']);
        add_action('admin_init', [__CLASS__,'register_settings']);
    }

    public static function menu() {
        add_menu_page('WR Premium', 'WR Premium', 'manage_options', 'wrpa', [__CLASS__,'settings_page'], 'dashicons-shield-alt', 58);
        add_submenu_page('wrpa','Settings','Settings','manage_options','wrpa',[__CLASS__,'settings_page']);
        add_submenu_page('wrpa','Members','Members','manage_options','wrpa-members',[__CLASS__,'members_page']);
        add_submenu_page('wrpa','Email Templates','Email Templates','manage_options','wrpa-emails',[__CLASS__,'emails_page']);
    }

    public static function register_settings() {
        register_setting('wrpa_group','wrpa_settings');

        add_settings_section('wrpa_main','Abonelik Süreleri','__return_false','wrpa');

        // Fields (IDs & durations)
        self::field('product_trial_id','Deneme Ürün (Trial) ID','number');
        self::field('trial_days','Süre (gün)','number');
        self::field('trial_minutes','Süre (dakika)','number');

        self::field('product_monthly_id','Aylık Ürün (Monthly) ID','number');
        self::field('monthly_days','Süre (gün)','number');
        self::field('monthly_minutes','Süre (dakika)','number');

        self::field('product_yearly_id','Yıllık Ürün (Yearly) ID','number');
        self::field('yearly_days','Süre (gün)','number');

        add_settings_section('wrpa_sender','Gönderici Bilgileri','__return_false','wrpa');
        self::field('sender_name','Gönderen Adı');
        self::field('sender_email','Gönderen E-posta','email');

        add_settings_section('wrpa_protect','Koruma Ayarları','__return_false','wrpa');
        add_settings_field('protected_cpts','Korumalı CPT’ler',[__CLASS__,'field_cpts'],'wrpa','wrpa_protect');
        add_settings_field('subscribe_url','Abonelik Sayfası URL',[__CLASS__,'render_field'],'wrpa','wrpa_protect', ['key'=>'subscribe_url']);

        // Email templates page settings
        register_setting('wrpa_emails_group','wrpa_settings');
    }

    private static function field($key,$label,$type='text') {
        add_settings_field($key,$label,[__CLASS__,'render_field'],'wrpa','wrpa_main', compact('key','type'));
    }

    public static function render_field($args) {
        $s = WRPA_Core::get_settings(); $key = $args['key']; $type = $args['type'] ?? 'text';
        printf('<input type="%s" name="wrpa_settings[%s]" value="%s" class="regular-text" />',
            esc_attr($type), esc_attr($key), esc_attr($s[$key] ?? '')
        );
    }

    public static function field_cpts() {
        $s = WRPA_Core::get_settings();
        $all = array('library','music','meditation','children_story','sleep_story','magazine');
        $sel = (array)($s['protected_cpts'] ?? []);
        foreach ($all as $cpt) {
            printf(
                '<label style="margin-right:10px;"><input type="checkbox" name="wrpa_settings[protected_cpts][]" value="%s" %s /> %s</label>',
                esc_attr($cpt),
                checked(in_array($cpt,$sel,true), true, false),
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
        $users = get_users(array('fields'=>array('ID','user_email','display_name')));
        echo '<div class="wrap"><h1>Members</h1><table class="widefat striped"><thead>
        <tr><th>Name</th><th>Email</th><th>Start Date</th><th>Expiry</th><th>Days Left</th><th>Status</th></tr></thead><tbody>';
        foreach ($users as $u) {
            $start = (int) get_user_meta($u->ID,'wr_premium_access_start', true);
            $exp   = (int) get_user_meta($u->ID,'wr_premium_access_expiry', true);
            $days  = $exp ? floor( max(0, $exp - time()) / DAY_IN_SECONDS ) : 0;
            $status = ($exp && time() < $exp) ? 'Active' : 'Expired';
            printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td></tr>',
                esc_html($u->display_name),
                esc_html($u->user_email),
                esc_html( WRPA_Core::fmt($start) ),
                esc_html( WRPA_Core::fmt($exp) ),
                (int)$days,
                esc_html($status)
            );
        }
        echo '</tbody></table></div>';
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
