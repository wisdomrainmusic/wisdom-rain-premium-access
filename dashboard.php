<?php
if (!defined('ABSPATH')) { exit; }
/** @var array $rows */
echo '<div class="wrap"><h1>'.esc_html__('Wisdom Rain Dashboard','wrpa').'</h1>';

echo '<h2 class="title">'.esc_html__('Members','wrpa').'</h2>';
echo '<table class="wrpa-table"><thead><tr>';
echo '<th>'.esc_html__('Name','wrpa').'</th>';
echo '<th>'.esc_html__('Email','wrpa').'</th>';
echo '<th>'.esc_html__('Expiry','wrpa').'</th>';
echo '<th>'.esc_html__('Days Left','wrpa').'</th>';
echo '<th>'.esc_html__('Status','wrpa').'</th>';
echo '</tr></thead><tbody>';

foreach ($rows as $r) {
    $class = 'wrpa-status-' . esc_attr($r['status']);
    $label = ucfirst($r['status']);
    echo '<tr>';
    echo '<td>'.esc_html($r['name']).'</td>';
    echo '<td><a href="mailto:'.esc_attr($r['email']).'">'.esc_html($r['email']).'</a></td>';
    echo '<td>'.wp_kses_post($r['expiry']).'</td>';
    echo '<td>'.esc_html($r['days_left']).'</td>';
    echo '<td><span class="wrpa-badge '.$class.'">'.esc_html($label).'</span></td>';
    echo '</tr>';
}

echo '</tbody></table>';
echo '</div>';
