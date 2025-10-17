diff --git a/includes/class-wrpa-admin.php b/includes/class-wrpa-admin.php
index 677d1adbbb5eef5730027ea9bfaed7d57b3d0289..7bcc19cfdae588a33d937fed1c21c24ec2681ec1 100644
--- a/includes/class-wrpa-admin.php
+++ b/includes/class-wrpa-admin.php
@@ -64,74 +64,273 @@ class WRPA_Admin {
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
-        $users = get_users(array('fields'=>array('ID','user_email','display_name')));
-        echo '<div class="wrap"><h1>Members</h1><table class="widefat striped"><thead>
-        <tr><th>Name</th><th>Email</th><th>Start Date</th><th>Expiry</th><th>Days Left</th><th>Status</th></tr></thead><tbody>';
-        foreach ($users as $u) {
-            $start = (int) get_user_meta($u->ID,'wr_premium_access_start', true);
-            $exp   = (int) get_user_meta($u->ID,'wr_premium_access_expiry', true);
-            $days  = $exp ? floor( max(0, $exp - time()) / DAY_IN_SECONDS ) : 0;
-            $status = ($exp && time() < $exp) ? 'Active' : 'Expired';
-            printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td></tr>',
-                esc_html($u->display_name),
-                esc_html($u->user_email),
-                esc_html( WRPA_Core::fmt($start) ),
-                esc_html( WRPA_Core::fmt($exp) ),
-                (int)$days,
-                esc_html($status)
+
+        if ( isset($_GET['wrpa_export']) ) {
+            check_admin_referer('wrpa_export_expired', 'wrpa_export_nonce');
+            self::export_expired_members();
+            return;
+        }
+
+        $status_filter = isset($_GET['wrpa_status']) ? sanitize_key(wp_unslash($_GET['wrpa_status'])) : 'all';
+        $allowed_statuses = array('all', 'active', 'expired', 'none');
+        if ( ! in_array($status_filter, $allowed_statuses, true) ) {
+            $status_filter = 'all';
+        }
+
+        $rows = self::get_member_rows($status_filter);
+        $status_options = array_merge(
+            array('all' => __('All', 'wrpa')),
+            self::get_status_labels()
+        );
+
+        echo '<div class="wrap wrpa-members">';
+        echo '<h1>' . esc_html__('Members', 'wrpa') . '</h1>';
+
+        echo '<div class="wrpa-members-toolbar">';
+        $action = esc_url( admin_url('admin.php') );
+
+        echo '<form method="get" action="' . $action . '" class="wrpa-members-filter">';
+        echo '<input type="hidden" name="page" value="wrpa-members" />';
+        echo '<label for="wrpa_status" class="screen-reader-text">' . esc_html__('Filter by status', 'wrpa') . '</label>';
+        echo '<select name="wrpa_status" id="wrpa_status">';
+        foreach ( $status_options as $value => $label ) {
+            printf(
+                '<option value="%s" %s>%s</option>',
+                esc_attr($value),
+                selected($status_filter, $value, false),
+                esc_html($label)
             );
         }
-        echo '</tbody></table></div>';
+        echo '</select>';
+        echo '<button type="submit" class="button button-secondary">' . esc_html__('Filter', 'wrpa') . '</button>';
+        echo '</form>';
+
+        echo '<form method="get" action="' . $action . '" class="wrpa-members-export">';
+        echo '<input type="hidden" name="page" value="wrpa-members" />';
+        echo '<input type="hidden" name="wrpa_export" value="1" />';
+        wp_nonce_field('wrpa_export_expired', 'wrpa_export_nonce');
+        echo '<button type="submit" class="button button-secondary">' . esc_html__('Export Expired CSV', 'wrpa') . '</button>';
+        echo '</form>';
+        echo '</div>';
+
+        if ( empty($rows) ) {
+            echo '<p>' . esc_html__('No members found for the selected filter.', 'wrpa') . '</p>';
+        } else {
+            echo '<table class="widefat striped wrpa-table"><thead><tr>';
+            echo '<th>' . esc_html__('Name', 'wrpa') . '</th>';
+            echo '<th>' . esc_html__('Username', 'wrpa') . '</th>';
+            echo '<th class="wrpa-col-plan">' . esc_html__('Plan Type', 'wrpa') . '</th>';
+            echo '<th>' . esc_html__('First Subscription', 'wrpa') . '</th>';
+            echo '<th>' . esc_html__('Start Date', 'wrpa') . '</th>';
+            echo '<th>' . esc_html__('Expiry', 'wrpa') . '</th>';
+            echo '<th>' . esc_html__('Days Left', 'wrpa') . '</th>';
+            echo '<th>' . esc_html__('Status', 'wrpa') . '</th>';
+            echo '</tr></thead><tbody>';
+
+            foreach ( $rows as $row ) {
+                printf(
+                    '<tr><td>%s</td><td>%s</td><td class="wrpa-col-plan">%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><span class="wrpa-badge %s">%s</span></td></tr>',
+                    esc_html($row['name']),
+                    esc_html($row['username']),
+                    esc_html($row['plan_label']),
+                    esc_html($row['first_subscription']),
+                    esc_html($row['start']),
+                    esc_html($row['expiry']),
+                    esc_html($row['days_left_display']),
+                    esc_attr($row['status_class']),
+                    esc_html($row['status_label'])
+                );
+            }
+            echo '</tbody></table>';
+        }
+
+        echo '</div>';
+    }
+
+    private static function get_member_rows($status_filter = 'all') {
+        $users = get_users(array('fields' => array('ID', 'display_name', 'user_login')));
+        $status_labels = self::get_status_labels();
+        $plan_labels   = self::get_plan_labels();
+        $rows = array();
+        $now = current_time('timestamp');
+        foreach ( $users as $user ) {
+            $start       = (int) get_user_meta($user->ID, 'wr_premium_access_start', true);
+            $expiry      = (int) get_user_meta($user->ID, 'wr_premium_access_expiry', true);
+            $first       = (int) get_user_meta($user->ID, '_wrpa_first_subscription_date', true);
+            $plan_raw    = sanitize_key( get_user_meta($user->ID, 'wrpa_plan_type', true) );
+            $plan_type   = array_key_exists($plan_raw, $plan_labels) ? $plan_raw : 'none';
+
+            if ( $expiry > 0 ) {
+                $status_value = ( $now < $expiry ) ? 'active' : 'expired';
+            } else {
+                $status_value = 'none';
+            }
+
+            if ( 'all' !== $status_filter && $status_value !== $status_filter ) {
+                continue;
+            }
+
+            $days_left = $expiry ? max(0, floor(($expiry - $now) / DAY_IN_SECONDS)) : 0;
+            $days_left_display = $expiry ? number_format_i18n($days_left) : '-';
+
+            $rows[] = array(
+                'name'               => $user->display_name,
+                'username'           => $user->user_login,
+                'plan'               => $plan_type,
+                'plan_slug'          => $plan_type,
+                'plan_label'         => $plan_labels[$plan_type] ?? ucfirst($plan_type),
+                'first_subscription' => WRPA_Core::fmt($first),
+                'start'              => WRPA_Core::fmt($start),
+                'expiry'             => WRPA_Core::fmt($expiry),
+                'days_left'          => $days_left,
+                'days_left_display'  => $days_left_display,
+                'status'             => $status_value,
+                'status_label'       => $status_labels[$status_value] ?? ucfirst($status_value),
+                'status_class'       => 'wrpa-status-' . $status_value,
+            );
+        }
+
+        return $rows;
+    }
+
+    private static function get_status_labels() {
+        return array(
+            'active'  => __('Active', 'wrpa'),
+            'expired' => __('Expired', 'wrpa'),
+            'none'    => __('None', 'wrpa'),
+        );
+    }
+
+    private static function get_plan_labels() {
+        return array(
+            'trial'   => __('Trial', 'wrpa'),
+            'monthly' => __('Monthly', 'wrpa'),
+            'yearly'  => __('Yearly', 'wrpa'),
+            'none'    => __('None', 'wrpa'),
+        );
+    }
+
+    private static function export_expired_members() {
+        $rows = self::get_member_rows('expired');
+        $filename = sprintf('wrpa-expired-members-%s.csv', date_i18n('Y-m-d'));
+        $filename = sanitize_file_name($filename);
+
+        nocache_headers();
+        header('Content-Type: text/csv; charset=utf-8');
+        header('Content-Disposition: attachment; filename="' . $filename . '"');
+
+        $output = fopen('php://output', 'w');
+        if ( false === $output ) {
+            wp_die(esc_html__('Unable to generate export file.', 'wrpa'));
+        }
+        fputcsv($output, array(
+            __('Name', 'wrpa'),
+            __('Username', 'wrpa'),
+            __('Plan Type', 'wrpa'),
+            __('First Subscription', 'wrpa'),
+            __('Start Date', 'wrpa'),
+            __('Expiry', 'wrpa'),
+            __('Days Left', 'wrpa'),
+            __('Status', 'wrpa'),
+        ));
+
+        foreach ( $rows as $row ) {
+            fputcsv($output, array(
+                $row['name'],
+                $row['username'],
+                $row['plan_slug'],
+                $row['first_subscription'],
+                $row['start'],
+                $row['expiry'],
+                $row['days_left'],
+                $row['status_label'],
+            ));
+        }
+
+        fclose($output);
+        exit;
     }
 
     public static function emails_page() {
         if ( isset($_POST['wrpa_save_emails']) && check_admin_referer('wrpa_save_emails_nonce') ) {
             $s = WRPA_Core::get_settings();
-            $s['tpl_welcome'] = wp_kses_post( wp_unslash($_POST['tpl_welcome'] ?? '') );
-            $s['tpl_thanks']  = wp_kses_post( wp_unslash($_POST['tpl_thanks'] ?? '') );
-            $s['tpl_expiry']  = wp_kses_post( wp_unslash($_POST['tpl_expiry'] ?? '') );
-            $s['tpl_holiday'] = wp_kses_post( wp_unslash($_POST['tpl_holiday'] ?? '') );
+            $fields = array('tpl_welcome', 'tpl_thanks', 'tpl_expiry', 'tpl_expired', 'tpl_renewal');
+
+            foreach ( $fields as $field_key ) {
+                $s[ $field_key ] = wp_kses_post( wp_unslash( $_POST[ $field_key ] ?? '' ) );
+            }
+
             WRPA_Core::update_settings($s);
-            echo '<div class="updated"><p>Templates saved.</p></div>';
+            echo '<div class="updated"><p>' . esc_html__('Templates saved.', 'wrpa') . '</p></div>';
+        }
+
+        $settings     = WRPA_Core::get_settings();
+        $placeholders = apply_filters('wrpa_email_placeholders', array());
+        if ( ! empty($placeholders) && is_array($placeholders) ) {
+            ksort($placeholders);
         }
-        $s = WRPA_Core::get_settings();
         ?>
         <div class="wrap">
-            <h1>Email Templates</h1>
-            <p>Kullanılabilir yer tutucular: <code>{name}</code>, <code>{email}</code>, <code>{start_date}</code>, <code>{expiry_date}</code>, <code>{days_left}</code></p>
+            <h1><?php esc_html_e('Email Templates', 'wrpa'); ?></h1>
+
+            <?php if ( ! empty($placeholders) ) : ?>
+                <p><?php esc_html_e('Available placeholders:', 'wrpa'); ?></p>
+                <ul>
+                    <?php foreach ( $placeholders as $tag => $description ) : ?>
+                        <li><code><?php echo esc_html($tag); ?></code> &mdash; <?php echo esc_html($description); ?></li>
+                    <?php endforeach; ?>
+                </ul>
+            <?php endif; ?>
+
             <form method="post">
                 <?php wp_nonce_field('wrpa_save_emails_nonce'); ?>
                 <table class="form-table">
-                    <tr><th>Welcome</th><td><textarea name="tpl_welcome" rows="6" class="large-text"><?php echo esc_textarea($s['tpl_welcome'] ?? ''); ?></textarea></td></tr>
-                    <tr><th>Thank You</th><td><textarea name="tpl_thanks" rows="6" class="large-text"><?php echo esc_textarea($s['tpl_thanks'] ?? ''); ?></textarea></td></tr>
-                    <tr><th>Expiry Reminder</th><td><textarea name="tpl_expiry" rows="6" class="large-text"><?php echo esc_textarea($s['tpl_expiry'] ?? ''); ?></textarea></td></tr>
-                    <tr><th>Holiday Greeting</th><td><textarea name="tpl_holiday" rows="6" class="large-text"><?php echo esc_textarea($s['tpl_holiday'] ?? ''); ?></textarea></td></tr>
+                    <tr>
+                        <th scope="row"><?php esc_html_e('Welcome Email', 'wrpa'); ?></th>
+                        <td><textarea name="tpl_welcome" rows="8" class="large-text"><?php echo esc_textarea($settings['tpl_welcome'] ?? ''); ?></textarea></td>
+                    </tr>
+                    <tr>
+                        <th scope="row"><?php esc_html_e('Thank You Email', 'wrpa'); ?></th>
+                        <td><textarea name="tpl_thanks" rows="8" class="large-text"><?php echo esc_textarea($settings['tpl_thanks'] ?? ''); ?></textarea></td>
+                    </tr>
+                    <tr>
+                        <th scope="row"><?php esc_html_e('Expiry Reminder Email', 'wrpa'); ?></th>
+                        <td><textarea name="tpl_expiry" rows="8" class="large-text"><?php echo esc_textarea($settings['tpl_expiry'] ?? ''); ?></textarea></td>
+                    </tr>
+                    <tr>
+                        <th scope="row"><?php esc_html_e('Expired Access Email', 'wrpa'); ?></th>
+                        <td><textarea name="tpl_expired" rows="8" class="large-text"><?php echo esc_textarea($settings['tpl_expired'] ?? ''); ?></textarea></td>
+                    </tr>
+                    <tr>
+                        <th scope="row"><?php esc_html_e('Renewal Reminder Email', 'wrpa'); ?></th>
+                        <td><textarea name="tpl_renewal" rows="8" class="large-text"><?php echo esc_textarea($settings['tpl_renewal'] ?? ''); ?></textarea></td>
+                    </tr>
                 </table>
-                <p><button class="button button-primary" name="wrpa_save_emails" value="1">Save Templates</button></p>
+                <p><button class="button button-primary" name="wrpa_save_emails" value="1"><?php esc_html_e('Save Templates', 'wrpa'); ?></button></p>
             </form>
         </div>
         <?php
     }
 }
