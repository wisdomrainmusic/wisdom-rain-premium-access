diff --git a/templates/dashboard.php b/templates/dashboard.php
index fdc58a5e7e2403cad02438ac92ffde38c918503a..c024fba33fca14fd89f2290eced6bc718e62ea63 100644
--- a/templates/dashboard.php
+++ b/templates/dashboard.php
@@ -1,28 +1,37 @@
 <?php
 if (!defined('ABSPATH')) { exit; }
 /** @var array $rows */
 echo '<div class="wrap"><h1>'.esc_html__('Wisdom Rain Dashboard','wrpa').'</h1>';
 
 echo '<h2 class="title">'.esc_html__('Members','wrpa').'</h2>';
 echo '<table class="wrpa-table"><thead><tr>';
 echo '<th>'.esc_html__('Name','wrpa').'</th>';
-echo '<th>'.esc_html__('Email','wrpa').'</th>';
+echo '<th>'.esc_html__('Username','wrpa').'</th>';
+echo '<th class="wrpa-col-plan">'.esc_html__('Plan Type','wrpa').'</th>';
+echo '<th>'.esc_html__('First Subscription','wrpa').'</th>';
+echo '<th>'.esc_html__('Start Date','wrpa').'</th>';
 echo '<th>'.esc_html__('Expiry','wrpa').'</th>';
 echo '<th>'.esc_html__('Days Left','wrpa').'</th>';
 echo '<th>'.esc_html__('Status','wrpa').'</th>';
 echo '</tr></thead><tbody>';
 
 foreach ($rows as $r) {
-    $class = 'wrpa-status-' . esc_attr($r['status']);
-    $label = ucfirst($r['status']);
+    $status   = isset($r['status']) ? $r['status'] : 'none';
+    $class    = isset($r['status_class']) ? $r['status_class'] : 'wrpa-status-' . $status;
+    $label    = isset($r['status_label']) ? $r['status_label'] : ucfirst($status);
     echo '<tr>';
     echo '<td>'.esc_html($r['name']).'</td>';
-    echo '<td><a href="mailto:'.esc_attr($r['email']).'">'.esc_html($r['email']).'</a></td>';
-    echo '<td>'.wp_kses_post($r['expiry']).'</td>';
-    echo '<td>'.esc_html($r['days_left']).'</td>';
-    echo '<td><span class="wrpa-badge '.$class.'">'.esc_html($label).'</span></td>';
+    echo '<td>'.esc_html($r['username']).'</td>';
+    $plan_label = isset($r['plan_label']) ? $r['plan_label'] : ($r['plan_slug'] ?? ($r['plan'] ?? ''));
+    echo '<td class="wrpa-col-plan">'.esc_html($plan_label).'</td>';
+    echo '<td>'.esc_html($r['first_subscription']).'</td>';
+    echo '<td>'.esc_html($r['start']).'</td>';
+    echo '<td>'.esc_html($r['expiry']).'</td>';
+    $days_left = isset($r['days_left_display']) ? $r['days_left_display'] : number_format_i18n((int) ($r['days_left'] ?? 0));
+    echo '<td>'.esc_html($days_left).'</td>';
+    echo '<td><span class="wrpa-badge '.esc_attr($class).'">'.esc_html($label).'</span></td>';
     echo '</tr>';
 }
 
 echo '</tbody></table>';
 echo '</div>';
