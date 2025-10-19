<?php
/**
 * WRPA - Email Admin tools
 *
 * @package WisdomRain\PremiumAccess
 */

namespace WRPA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides an Email Control Center UI within the WRPA admin menu.
 */
class WRPA_Email_Admin {

    /**
     * Accumulated admin notices for the current request.
     *
     * @var array<int, array{type:string,message:string}>
     */
    protected static $messages = [];

    /**
     * Cached preview HTML rendered during the current request.
     *
     * @var string
     */
    protected static $preview_html = '';

    /**
     * Tracks whether the email submenu routes have already been registered.
     *
     * @var bool
     */
    protected static $submenus_registered = false;

    /**
     * Bootstraps admin hooks for managing email templates.
     */
    public static function init() : void {
        add_action( 'admin_menu', [ __CLASS__, 'register_email_submenus' ], 20 );
        add_action( 'admin_head', [ __CLASS__, 'hide_secondary_submenus' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    /**
     * Registers Email admin sub pages under the WRPA dashboard.
     */
    public static function register_email_submenus() : void {
        if ( self::$submenus_registered ) {
            return;
        }

        if ( ! function_exists( 'add_submenu_page' ) ) {
            return;
        }

        self::$submenus_registered = true;

        add_submenu_page(
            'wrpa-dashboard',
            __( 'Email Control Center', 'wrpa' ),
            __( 'Email', 'wrpa' ),
            'manage_options',
            'wrpa-email-templates',
            [ __CLASS__, 'render_templates_page' ]
        );

        add_submenu_page(
            'wrpa-dashboard',
            __( 'Email ▸ Edit Template', 'wrpa' ),
            '',
            'manage_options',
            'wrpa-email-edit',
            [ __CLASS__, 'render_edit_page' ]
        );

        add_submenu_page(
            'wrpa-dashboard',
            __( 'Email ▸ Preview', 'wrpa' ),
            '',
            'manage_options',
            'wrpa-email-preview',
            [ __CLASS__, 'render_preview_page' ]
        );

        add_submenu_page(
            'wrpa-dashboard',
            __( 'Email ▸ Test Gönder', 'wrpa' ),
            '',
            'manage_options',
            'wrpa-email-test',
            [ __CLASS__, 'render_test_page' ]
        );

        add_submenu_page(
            'wrpa-dashboard',
            __( 'Email ▸ Campaigns', 'wrpa' ),
            '',
            'manage_options',
            'wrpa-email-campaigns',
            [ __CLASS__, 'render_campaign_page' ]
        );
    }

    /**
     * Hides secondary email pages from the submenu UI while keeping them routable.
     */
    public static function hide_secondary_submenus() : void {
        // Ensure the admin menu has finished registering submenu hooks before removing them.
        if ( ! did_action( 'admin_menu' ) ) {
            return;
        }

        $pages = [
            'wrpa-email-edit',
            'wrpa-email-preview',
            'wrpa-email-test',
            'wrpa-email-campaigns',
        ];

        foreach ( $pages as $page ) {
            remove_submenu_page( 'wrpa-dashboard', $page );
        }
    }

    /**
     * Enqueues CodeMirror and layout tweaks for the Email Control Center.
     *
     * @param string $hook Current admin page hook suffix.
     */
    public static function enqueue_assets( $hook ) : void {
        if ( false === strpos( (string) $hook, 'wrpa-email' ) ) {
            return;
        }

        wp_register_style( 'wrpa-email-admin', false );
        wp_enqueue_style( 'wrpa-email-admin' );

        $css = '.wrpa-email-admin .wp-list-table td code{font-size:13px;display:inline-block;padding:2px 6px;background:#f6f7f7;border-radius:4px;}'
            . '.wrpa-email-admin .wrpa-email-preview-frame{border:1px solid #ccd0d4;background:#fff;padding:20px;max-height:600px;overflow:auto;}'
            . '.wrpa-email-admin .wrpa-email-preview-frame iframe{width:100%;height:100%;border:0;}'
            . '.wrpa-email-admin .form-table th{padding-left:0;}'
            . '.wrpa-email-admin .wrpa-subnav{margin:20px 0 24px;display:flex;flex-wrap:wrap;gap:8px;}'
            . '.wrpa-email-admin .wrpa-subnav a{padding:6px 12px;border:1px solid #ccd0d4;border-radius:4px;text-decoration:none;background:#fff;color:#2271b1;}'
            . '.wrpa-email-admin .wrpa-subnav a.wrpa-subnav-active{background:#2271b1;color:#fff;}'
            . '.wrpa-email-admin .wrpa-codemirror-wrapper .CodeMirror{min-height:420px;border:1px solid #ccd0d4;border-radius:4px;}'
            . '.wrpa-email-admin .wrpa-segment-note{margin-top:6px;color:#646970;font-style:italic;}'
            . '.wrpa-email-admin .wrpa-flex{display:flex;gap:24px;flex-wrap:wrap;}'
            . '.wrpa-email-admin .wrpa-flex .wrpa-panel{flex:1 1 320px;max-width:520px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:20px;}'
            . '.wrpa-email-admin .wrpa-preview-html{background:#fff;border:1px solid #ccd0d4;padding:24px;max-width:960px;overflow:auto;}'
            . '.wrpa-email-admin table.widefat td, .wrpa-email-admin table.widefat th{vertical-align:middle;}'
            . '.wrpa-email-admin .description{margin-top:4px;display:block;color:#646970;}';

        wp_add_inline_style( 'wrpa-email-admin', $css );

        if ( false !== strpos( (string) $hook, 'wrpa-email-edit' ) && function_exists( 'wp_enqueue_code_editor' ) ) {
            $settings = wp_enqueue_code_editor( [ 'type' => 'text/html' ] );
            wp_enqueue_script( 'code-editor' );
            wp_enqueue_style( 'code-editor' );

            if ( $settings ) {
                wp_add_inline_script(
                    'code-editor',
                    'jQuery(function($){var el=document.getElementById("wrpa-template-editor");if(el){wp.codeEditor.initialize(el,' . wp_json_encode( $settings ) . ');}});'
                );
            }
        }
    }

    /**
     * Renders the Templates list page.
     */
    public static function render_templates_page() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'wrpa' ) );
        }

        $templates = self::get_templates();

        echo '<div class="wrap wrpa-email-admin">';
        echo '<h1>' . esc_html__( 'Email Control Center', 'wrpa' ) . '</h1>';

        if ( class_exists( '\\WRPA\\WRPA_Admin' ) ) {
            WRPA_Admin::render_nav_tabs( 'wrpa-email-templates' );
        }

        self::render_email_nav( 'templates' );
        self::render_notices();

        echo '<p>' . esc_html__( 'Review templates, locate override files, and jump directly into preview, test, or campaign workflows.', 'wrpa' ) . '</p>';

        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr>';
        echo '<th scope="col">' . esc_html__( 'Slug', 'wrpa' ) . '</th>';
        echo '<th scope="col">' . esc_html__( 'File', 'wrpa' ) . '</th>';
        echo '<th scope="col">' . esc_html__( 'Edit', 'wrpa' ) . '</th>';
        echo '<th scope="col">' . esc_html__( 'Preview', 'wrpa' ) . '</th>';
        echo '<th scope="col">' . esc_html__( 'Test', 'wrpa' ) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if ( empty( $templates ) ) {
            echo '<tr><td colspan="5">' . esc_html__( 'No templates were found in templates/emails/.', 'wrpa' ) . '</td></tr>';
        } else {
            foreach ( $templates as $template ) {
                $slug       = $template['slug'];
                $file_label = $template['override_path'] ? $template['override_path'] : $template['plugin_path'];
                $file_label = str_replace( ABSPATH, '/', $file_label );
                $edit_url   = esc_url( admin_url( 'admin.php?page=wrpa-email-edit&slug=' . urlencode( $slug ) ) );
                $preview_url = esc_url( admin_url( 'admin.php?page=wrpa-email-preview&slug=' . urlencode( $slug ) ) );
                $test_url   = esc_url( admin_url( 'admin.php?page=wrpa-email-test&slug=' . urlencode( $slug ) ) );

                echo '<tr>';
                echo '<td><code>' . esc_html( $slug ) . '</code></td>';
                echo '<td><code>' . esc_html( $file_label ) . '</code></td>';
                echo '<td><a class="button" href="' . $edit_url . '">' . esc_html__( 'Edit Template', 'wrpa' ) . '</a></td>';
                echo '<td><a class="button" href="' . $preview_url . '">' . esc_html__( 'Open Preview', 'wrpa' ) . '</a></td>';
                echo '<td><a class="button" href="' . $test_url . '">' . esc_html__( 'Send Test', 'wrpa' ) . '</a></td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    /**
     * Renders the Edit Template page with CodeMirror integration.
     */
    public static function render_edit_page() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'wrpa' ) );
        }

        $slug = isset( $_REQUEST['slug'] ) ? sanitize_key( wp_unslash( $_REQUEST['slug'] ) ) : '';
        $templates = self::get_templates();

        if ( '' === $slug && ! empty( $templates ) ) {
            $slug = $templates[0]['slug'];
        }

        if ( '' === $slug ) {
            self::add_message( 'error', __( 'No template was selected for editing.', 'wrpa' ) );
        }

        if ( isset( $_POST['wrpa_save_template'] ) ) {
            self::handle_edit_submission();
        }

        $content      = '';
        $plugin_path  = $slug ? self::get_plugin_template_path( $slug ) : null;
        $override     = $slug ? self::get_theme_override_path( $slug ) : null;
        $is_writable  = $plugin_path && file_exists( $plugin_path ) && is_writable( $plugin_path );
        $display_slug = $slug ? $slug : '';

        if ( $plugin_path && file_exists( $plugin_path ) ) {
            $content = (string) file_get_contents( $plugin_path );
        }

        if ( isset( $_POST['wrpa_template_content'] ) && isset( $_POST['wrpa_template_slug'] ) ) {
            $posted_slug = sanitize_key( wp_unslash( $_POST['wrpa_template_slug'] ) );
            if ( $posted_slug === $slug ) {
                $content = (string) wp_unslash( $_POST['wrpa_template_content'] );
            }
        }

        echo '<div class="wrap wrpa-email-admin">';
        echo '<h1>' . esc_html__( 'Edit Email Template', 'wrpa' ) . '</h1>';

        if ( class_exists( '\\WRPA\\WRPA_Admin' ) ) {
            WRPA_Admin::render_nav_tabs( 'wrpa-email-templates' );
        }

        self::render_email_nav( 'edit' );
        self::render_notices();

        if ( $override ) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'A theme override is currently active. Changes here affect the plugin copy only.', 'wrpa' ) . '</p></div>';
        }

        if ( ! $is_writable && $plugin_path ) {
            $theme_hint = trailingslashit( get_stylesheet_directory() ) . 'wrpa/emails/' . $display_slug . '.html.php';
            echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( __( 'Temada override kullanın: %s', 'wrpa' ), str_replace( ABSPATH, '/', $theme_hint ) ) ) . '</p></div>';
        }

        if ( ! $plugin_path || ! file_exists( $plugin_path ) ) {
            echo '<p>' . esc_html__( 'The requested template could not be located.', 'wrpa' ) . '</p>';
            echo '</div>';
            return;
        }

        echo '<form method="post" class="wrpa-email-edit-form">';
        wp_nonce_field( 'wrpa_edit_template', 'wrpa_edit_template_nonce' );
        echo '<input type="hidden" name="wrpa_template_slug" value="' . esc_attr( $display_slug ) . '" />';

        echo '<table class="form-table"><tbody>';
        echo '<tr>';
        echo '<th scope="row">' . esc_html__( 'Template File', 'wrpa' ) . '</th>';
        echo '<td><code>' . esc_html( str_replace( ABSPATH, '/', $plugin_path ) ) . '</code></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">' . esc_html__( 'Editor', 'wrpa' ) . '</th>';
        echo '<td class="wrpa-codemirror-wrapper">';
        echo '<textarea id="wrpa-template-editor" name="wrpa_template_content" rows="20" class="large-text code"' . ( $is_writable ? '' : ' readonly' ) . '>'; 
        echo esc_textarea( $content );
        echo '</textarea>';
        echo '<p class="description">' . esc_html__( 'Only HTML markup is allowed. Dynamic placeholders such as {user_first_name} remain intact.', 'wrpa' ) . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</tbody></table>';

        submit_button( __( 'Save Template', 'wrpa' ), 'primary', 'wrpa_save_template', false, [ 'disabled' => $is_writable ? false : true ] );
        echo '</form>';
        echo '</div>';
    }

    /**
     * Renders the Preview page.
     */
    public static function render_preview_page() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'wrpa' ) );
        }

        $templates = self::get_templates();
        $slug      = isset( $_REQUEST['slug'] ) ? sanitize_key( wp_unslash( $_REQUEST['slug'] ) ) : '';

        if ( $slug ) {
            self::$preview_html = self::generate_preview_html( $slug, self::get_dummy_template_vars() );

            if ( '' === self::$preview_html ) {
                self::add_message( 'error', __( 'The selected template could not be rendered with the provided data.', 'wrpa' ) );
            } elseif ( isset( $_REQUEST['wrpa_generate_preview'] ) ) {
                self::add_message( 'success', __( 'Preview generated successfully.', 'wrpa' ) );
            }
        }

        echo '<div class="wrap wrpa-email-admin">';
        echo '<h1>' . esc_html__( 'Preview Email Template', 'wrpa' ) . '</h1>';

        if ( class_exists( '\\WRPA\\WRPA_Admin' ) ) {
            WRPA_Admin::render_nav_tabs( 'wrpa-email-templates' );
        }

        self::render_email_nav( 'preview' );
        self::render_notices();

        echo '<form method="get" class="wrpa-email-preview-form">';
        echo '<input type="hidden" name="page" value="wrpa-email-preview" />';
        echo '<p><label for="wrpa-preview-slug">' . esc_html__( 'Template', 'wrpa' ) . '</label><br />';
        echo '<select name="slug" id="wrpa-preview-slug" class="regular-text">';
        echo '<option value="">' . esc_html__( 'Select template…', 'wrpa' ) . '</option>';

        foreach ( $templates as $template ) {
            $selected = selected( $slug, $template['slug'], false );
            printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $template['slug'] ), esc_html( $template['slug'] ), $selected );
        }

        echo '</select></p>';
        echo '<p class="description">' . esc_html__( 'Preview uses sample data (name, plan, and URLs) so you can confirm layout quickly.', 'wrpa' ) . '</p>';
        submit_button( __( 'Generate Preview', 'wrpa' ), 'primary', 'wrpa_generate_preview', false );
        echo '</form>';

        if ( self::$preview_html ) {
            echo '<h2>' . esc_html__( 'Preview Output', 'wrpa' ) . '</h2>';
            echo '<div class="wrpa-email-preview-frame">';
            echo '<div class="wrpa-preview-html">' . wp_kses_post( self::$preview_html ) . '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Renders the Test Gönder page.
     */
    public static function render_test_page() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'wrpa' ) );
        }

        if ( isset( $_POST['wrpa_send_test_email'] ) ) {
            self::handle_test_submission();
        }

        $templates = self::get_templates();
        $slug      = isset( $_REQUEST['slug'] ) ? sanitize_key( wp_unslash( $_REQUEST['slug'] ) ) : '';

        echo '<div class="wrap wrpa-email-admin">';
        echo '<h1>' . esc_html__( 'Send Test Email', 'wrpa' ) . '</h1>';

        if ( class_exists( '\\WRPA\\WRPA_Admin' ) ) {
            WRPA_Admin::render_nav_tabs( 'wrpa-email-templates' );
        }

        self::render_email_nav( 'test' );
        self::render_notices();

        echo '<form method="post" class="wrpa-email-test-form">';
        wp_nonce_field( 'wrpa_test_email', 'wrpa_test_email_nonce' );
        echo '<p><label for="wrpa-test-slug">' . esc_html__( 'Template', 'wrpa' ) . '</label><br />';
        echo '<select name="wrpa_test_slug" id="wrpa-test-slug" class="regular-text">';
        echo '<option value="">' . esc_html__( 'Select template…', 'wrpa' ) . '</option>';

        foreach ( $templates as $template ) {
            $selected = selected( $slug, $template['slug'], false );
            printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $template['slug'] ), esc_html( $template['slug'] ), $selected );
        }

        echo '</select></p>';

        echo '<p><label for="wrpa-test-email">' . esc_html__( 'Recipient email', 'wrpa' ) . '</label><br />';
        echo '<input type="email" id="wrpa-test-email" name="wrpa_test_email" class="regular-text" required /></p>';
        echo '<p class="description">' . esc_html__( 'Email must belong to an existing user so WRPA can prepare account data and send via WRPA_Email::send_email().', 'wrpa' ) . '</p>';

        submit_button( __( 'Send Test', 'wrpa' ), 'primary', 'wrpa_send_test_email', false );
        echo '</form>';
        echo '</div>';
    }

    /**
     * Renders the Campaigns (Blast) page.
     */
    public static function render_campaign_page() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'wrpa' ) );
        }

        if ( isset( $_POST['wrpa_send_campaign'] ) ) {
            self::handle_campaign_submission();
        }

        $templates = self::get_templates();

        echo '<div class="wrap wrpa-email-admin">';
        echo '<h1>' . esc_html__( 'Campaign Blast', 'wrpa' ) . '</h1>';

        if ( class_exists( '\\WRPA\\WRPA_Admin' ) ) {
            WRPA_Admin::render_nav_tabs( 'wrpa-email-templates' );
        }

        self::render_email_nav( 'campaigns' );
        self::render_notices();

        echo '<p>' . esc_html__( 'Queue segmented campaigns and let WRPA throttle delivery with the cron-safe queue.', 'wrpa' ) . '</p>';

        echo '<form method="post" class="wrpa-email-campaign-form">';
        wp_nonce_field( 'wrpa_campaign_send', 'wrpa_campaign_send_nonce' );

        echo '<table class="form-table"><tbody>';
        echo '<tr>';
        echo '<th scope="row">' . esc_html__( 'Segment', 'wrpa' ) . '</th>';
        echo '<td>';
        echo '<select name="wrpa_campaign_segment" class="regular-text">';
        $segments = [
            'active'  => __( 'All active subscribers', 'wrpa' ),
            'expired' => __( 'Expired subscribers', 'wrpa' ),
            'plan'    => __( 'By plan key', 'wrpa' ),
            'manual'  => __( 'Manual user ID list', 'wrpa' ),
        ];

        $selected_segment = isset( $_POST['wrpa_campaign_segment'] ) ? sanitize_key( wp_unslash( $_POST['wrpa_campaign_segment'] ) ) : 'active';

        foreach ( $segments as $key => $label ) {
            printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $key ), esc_html( $label ), selected( $selected_segment, $key, false ) );
        }

        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Choose who should receive the blast. Manual lists accept comma or newline separated IDs.', 'wrpa' ) . '</p>';
        echo '</td>';
        echo '</tr>';

        $plan_value = isset( $_POST['wrpa_campaign_plan'] ) ? sanitize_key( wp_unslash( $_POST['wrpa_campaign_plan'] ) ) : '';
        echo '<tr>';
        echo '<th scope="row">' . esc_html__( 'Plan key (for plan segment)', 'wrpa' ) . '</th>';
        echo '<td><input type="text" name="wrpa_campaign_plan" value="' . esc_attr( $plan_value ) . '" class="regular-text" />';
        echo '<span class="wrpa-segment-note">' . esc_html__( 'Example: trial, monthly, yearly', 'wrpa' ) . '</span></td>';
        echo '</tr>';

        $manual_value = isset( $_POST['wrpa_campaign_manual'] ) ? wp_unslash( $_POST['wrpa_campaign_manual'] ) : '';
        echo '<tr>';
        echo '<th scope="row">' . esc_html__( 'Manual user IDs', 'wrpa' ) . '</th>';
        echo '<td><textarea name="wrpa_campaign_manual" rows="4" class="large-text code">' . esc_textarea( $manual_value ) . '</textarea>';
        echo '<span class="wrpa-segment-note">' . esc_html__( 'Enter numeric user IDs separated by comma or newline.', 'wrpa' ) . '</span></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__( 'Template', 'wrpa' ) . '</th>';
        echo '<td><select name="wrpa_campaign_slug" class="regular-text">';
        echo '<option value="">' . esc_html__( 'Select template…', 'wrpa' ) . '</option>';

        $selected_slug = isset( $_POST['wrpa_campaign_slug'] ) ? sanitize_key( wp_unslash( $_POST['wrpa_campaign_slug'] ) ) : '';

        foreach ( $templates as $template ) {
            printf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $template['slug'] ), esc_html( $template['slug'] ), selected( $selected_slug, $template['slug'], false ) );
        }

        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Campaigns send with shared dummy data (name, plan, expiry) unless template overrides placeholders.', 'wrpa' ) . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '</tbody></table>';

        submit_button( __( 'Gönder', 'wrpa' ), 'primary', 'wrpa_send_campaign', false );
        echo '</form>';
        echo '</div>';
    }

    /**
     * Handles template edit submissions.
     */
    protected static function handle_edit_submission() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            self::add_message( 'error', __( 'You are not allowed to modify email templates.', 'wrpa' ) );
            return;
        }

        if ( ! isset( $_POST['wrpa_edit_template_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wrpa_edit_template_nonce'] ) ), 'wrpa_edit_template' ) ) {
            self::add_message( 'error', __( 'Security check failed. Template was not saved.', 'wrpa' ) );
            return;
        }

        $slug = isset( $_POST['wrpa_template_slug'] ) ? sanitize_key( wp_unslash( $_POST['wrpa_template_slug'] ) ) : '';

        if ( '' === $slug ) {
            self::add_message( 'error', __( 'Template slug missing.', 'wrpa' ) );
            return;
        }

        $plugin_path = self::get_plugin_template_path( $slug );

        if ( ! $plugin_path || ! file_exists( $plugin_path ) ) {
            self::add_message( 'error', __( 'Template file not found.', 'wrpa' ) );
            return;
        }

        $plugin_dir = realpath( trailingslashit( dirname( __DIR__ ) ) . 'templates/emails/' );
        $real_path  = realpath( $plugin_path );

        if ( false === $plugin_dir || false === $real_path || 0 !== strpos( $real_path, $plugin_dir ) ) {
            self::add_message( 'error', __( 'Unauthorized template path detected. Edit aborted.', 'wrpa' ) );
            return;
        }

        if ( ! is_writable( $real_path ) ) {
            self::add_message( 'error', __( 'Template file is not writable. Use a theme override.', 'wrpa' ) );
            return;
        }

        $raw_content = isset( $_POST['wrpa_template_content'] ) ? (string) wp_unslash( $_POST['wrpa_template_content'] ) : '';
        $sanitized   = self::sanitize_template_html( $raw_content, $slug );

        if ( '' === trim( $sanitized ) ) {
            self::add_message( 'error', __( 'Template content may not be empty.', 'wrpa' ) );
            return;
        }

        if ( ! self::is_html_valid( $sanitized ) ) {
            self::add_message( 'error', __( 'Template HTML contains structural issues. Please fix validation errors before saving.', 'wrpa' ) );
            return;
        }

        $written = file_put_contents( $real_path, $sanitized, LOCK_EX );

        if ( false === $written ) {
            self::add_message( 'error', __( 'Template file could not be saved.', 'wrpa' ) );
            return;
        }

        self::add_message( 'success', __( 'Template saved successfully.', 'wrpa' ) );
    }

    /**
     * Handles test email submissions.
     */
    protected static function handle_test_submission() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            self::add_message( 'error', __( 'You are not allowed to send test emails.', 'wrpa' ) );
            return;
        }

        if ( ! isset( $_POST['wrpa_test_email_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wrpa_test_email_nonce'] ) ), 'wrpa_test_email' ) ) {
            self::add_message( 'error', __( 'Security check failed. Test email aborted.', 'wrpa' ) );
            return;
        }

        $slug  = isset( $_POST['wrpa_test_slug'] ) ? sanitize_key( wp_unslash( $_POST['wrpa_test_slug'] ) ) : '';
        $email = isset( $_POST['wrpa_test_email'] ) ? sanitize_email( wp_unslash( $_POST['wrpa_test_email'] ) ) : '';

        if ( '' === $slug ) {
            self::add_message( 'error', __( 'Select a template before sending the test email.', 'wrpa' ) );
            return;
        }

        if ( '' === $email || ! is_email( $email ) ) {
            self::add_message( 'error', __( 'Enter a valid email address that belongs to an existing user.', 'wrpa' ) );
            return;
        }

        $user = get_user_by( 'email', $email );

        if ( ! $user ) {
            self::add_message( 'error', __( 'No user was found with that email address. Create the user or use a different address.', 'wrpa' ) );
            return;
        }

        $vars = self::get_dummy_template_vars( (int) $user->ID );
        $sent = WRPA_Email::send_email( (int) $user->ID, $slug, $vars );

        if ( $sent ) {
            self::add_message( 'success', __( 'Test email dispatched successfully.', 'wrpa' ) );
            return;
        }

        self::add_message( 'error', __( 'The test email could not be sent. Check mail configuration or template integrity.', 'wrpa' ) );
    }

    /**
     * Handles campaign blast submissions.
     */
    protected static function handle_campaign_submission() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            self::add_message( 'error', __( 'You are not allowed to queue campaigns.', 'wrpa' ) );
            return;
        }

        if ( ! isset( $_POST['wrpa_campaign_send_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wrpa_campaign_send_nonce'] ) ), 'wrpa_campaign_send' ) ) {
            self::add_message( 'error', __( 'Security check failed. Campaign was not queued.', 'wrpa' ) );
            return;
        }

        $segment = isset( $_POST['wrpa_campaign_segment'] ) ? sanitize_key( wp_unslash( $_POST['wrpa_campaign_segment'] ) ) : '';
        $slug    = isset( $_POST['wrpa_campaign_slug'] ) ? sanitize_key( wp_unslash( $_POST['wrpa_campaign_slug'] ) ) : '';
        $plan    = isset( $_POST['wrpa_campaign_plan'] ) ? sanitize_key( wp_unslash( $_POST['wrpa_campaign_plan'] ) ) : '';
        $manual  = isset( $_POST['wrpa_campaign_manual'] ) ? (string) wp_unslash( $_POST['wrpa_campaign_manual'] ) : '';

        if ( '' === $segment || '' === $slug ) {
            self::add_message( 'error', __( 'Select a segment and template before running the campaign.', 'wrpa' ) );
            return;
        }

        $recipients = self::resolve_recipients_for_segment( $segment, $plan, $manual );

        if ( empty( $recipients ) ) {
            self::add_message( 'error', __( 'No recipients matched the selected segment.', 'wrpa' ) );
            return;
        }

        $vars      = self::get_dummy_template_vars();
        $sent      = 0;
        $failures  = [];
        $chunkSize = class_exists( '\\WRPA\\WRPA_Email_Cron' ) ? WRPA_Email_Cron::BATCH_LIMIT : 200;

        foreach ( array_chunk( $recipients, $chunkSize ) as $chunk ) {
            $result = WRPA_Email_Cron::queue_and_send( $chunk, $slug, $vars );
            $sent  += isset( $result['sent'] ) ? (int) $result['sent'] : 0;
            if ( ! empty( $result['failures'] ) ) {
                $failures = array_merge( $failures, array_map( 'absint', $result['failures'] ) );
            }
        }

        $message = sprintf(
            /* translators: 1: number sent, 2: total recipients */
            __( 'Campaign queued: %1$d of %2$d recipients processed.', 'wrpa' ),
            $sent,
            count( $recipients )
        );

        if ( ! empty( $failures ) ) {
            $message .= ' ' . sprintf(
                /* translators: %s: comma separated user IDs */
                __( 'Failures: %s', 'wrpa' ),
                implode( ', ', array_unique( $failures ) )
            );
            self::add_message( 'error', $message );
            return;
        }

        self::add_message( 'success', $message );
    }

    /**
     * Resolves a list of recipient user IDs for a segment key.
     *
     * @param string $segment Segment key.
     * @param string $plan    Optional plan key.
     * @param string $manual  Manual input list.
     * @return array<int,int>
     */
    protected static function resolve_recipients_for_segment( string $segment, string $plan, string $manual ) : array {
        $segment = sanitize_key( $segment );
        $plan    = sanitize_key( $plan );
        $manual  = (string) $manual;

        switch ( $segment ) {
            case 'active':
                return self::query_users_by_status( 'active' );
            case 'expired':
                return self::query_users_by_status( 'expired' );
            case 'plan':
                if ( '' === $plan ) {
                    return [];
                }
                return self::query_users_by_plan( $plan );
            case 'manual':
                return self::parse_manual_user_ids( $manual );
        }

        return [];
    }

    /**
     * Queries users by membership status.
     *
     * @param string $status Either active or expired.
     * @return array<int,int>
     */
    protected static function query_users_by_status( string $status ) : array {
        $status = 'expired' === $status ? 'expired' : 'active';
        $limit  = (int) apply_filters( 'wrpa_email_admin_segment_limit', 500, $status );

        $meta_query = [
            'relation' => 'AND',
            [
                'key'     => WRPA_Access::USER_PLAN_META,
                'compare' => 'EXISTS',
            ],
        ];

        $now = time();

        if ( 'active' === $status ) {
            $meta_query[] = [
                'key'     => WRPA_Access::USER_ACCESS_EXPIRES_META,
                'value'   => $now,
                'compare' => '>',
                'type'    => 'NUMERIC',
            ];
        } else {
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key'     => WRPA_Access::USER_ACCESS_EXPIRES_META,
                    'value'   => $now,
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'     => WRPA_Access::USER_ACCESS_EXPIRES_META,
                    'value'   => 0,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'     => WRPA_Access::USER_ACCESS_EXPIRES_META,
                    'compare' => 'NOT EXISTS',
                ],
            ];
        }

        $query = new \WP_User_Query(
            [
                'fields'     => 'ID',
                'number'     => $limit,
                'meta_query' => $meta_query,
            ]
        );

        $ids = $query->get_results();

        return array_values( array_unique( array_map( 'absint', (array) $ids ) ) );
    }

    /**
     * Queries users by stored plan key.
     *
     * @param string $plan Plan key.
     * @return array<int,int>
     */
    protected static function query_users_by_plan( string $plan ) : array {
        $limit = (int) apply_filters( 'wrpa_email_admin_segment_limit', 500, 'plan' );

        $query = new \WP_User_Query(
            [
                'fields'     => 'ID',
                'number'     => $limit,
                'meta_key'   => WRPA_Access::USER_PLAN_META,
                'meta_value' => $plan,
            ]
        );

        $ids = $query->get_results();

        return array_values( array_unique( array_map( 'absint', (array) $ids ) ) );
    }

    /**
     * Parses manual user ID input.
     *
     * @param string $manual Manual IDs separated by comma or newline.
     * @return array<int,int>
     */
    protected static function parse_manual_user_ids( string $manual ) : array {
        $manual = preg_replace( '/[^0-9,\n\r\s]+/', '', $manual );
        $manual = str_replace( ["\r", "\n"], ',', $manual );
        $chunks = array_filter( array_map( 'trim', explode( ',', $manual ) ) );

        return array_values( array_unique( array_map( 'absint', $chunks ) ) );
    }

    /**
     * Adds an admin notice message to be displayed on the page.
     *
     * @param string $type    Message type (success|error|info).
     * @param string $message Message text.
     */
    protected static function add_message( string $type, string $message ) : void {
        self::$messages[] = [
            'type'    => $type,
            'message' => $message,
        ];
    }

    /**
     * Outputs accumulated admin notices.
     */
    protected static function render_notices() : void {
        if ( empty( self::$messages ) ) {
            return;
        }

        foreach ( self::$messages as $notice ) {
            $class = 'notice-info';

            if ( 'success' === $notice['type'] ) {
                $class = 'notice-success';
            } elseif ( 'error' === $notice['type'] ) {
                $class = 'notice-error';
            }

            printf(
                '<div class="notice %1$s"><p>%2$s</p></div>',
                esc_attr( $class ),
                esc_html( $notice['message'] )
            );
        }

        self::$messages = [];
    }

    /**
     * Outputs the secondary navigation specific to email tasks.
     *
     * @param string $active Active key.
     */
    protected static function render_email_nav( string $active ) : void {
        $tabs = [
            'templates' => [ __( 'Templates', 'wrpa' ), 'admin.php?page=wrpa-email-templates' ],
            'edit'      => [ __( 'Edit Template', 'wrpa' ), 'admin.php?page=wrpa-email-edit' ],
            'preview'   => [ __( 'Preview', 'wrpa' ), 'admin.php?page=wrpa-email-preview' ],
            'test'      => [ __( 'Test Gönder', 'wrpa' ), 'admin.php?page=wrpa-email-test' ],
            'campaigns' => [ __( 'Campaigns', 'wrpa' ), 'admin.php?page=wrpa-email-campaigns' ],
        ];

        echo '<div class="wrpa-subnav">';

        foreach ( $tabs as $key => $tab ) {
            $url   = esc_url( admin_url( $tab[1] ) );
            $class = 'wrpa-subnav-link';

            if ( $key === $active ) {
                $class .= ' wrpa-subnav-active';
            }

            printf( '<a href="%1$s" class="%2$s">%3$s</a>', $url, esc_attr( $class ), esc_html( $tab[0] ) );
        }

        echo '</div>';
    }

    /**
     * Returns metadata for available template files.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function get_templates() : array {
        $templates  = [];
        $plugin_dir = realpath( trailingslashit( dirname( __DIR__ ) ) . 'templates/emails/' );

        if ( false === $plugin_dir ) {
            return [];
        }

        $plugin_files = glob( trailingslashit( $plugin_dir ) . '*.html.php' );

        if ( ! $plugin_files ) {
            $plugin_files = [];
        }

        foreach ( $plugin_files as $file ) {
            $real = realpath( $file );

            if ( false === $real || 0 !== strpos( $real, $plugin_dir ) ) {
                continue;
            }

            $slug        = basename( $real, '.html.php' );
            $templates[] = [
                'slug'          => $slug,
                'plugin_path'   => $real,
                'override_path' => self::get_theme_override_path( $slug ),
            ];
        }

        usort(
            $templates,
            static function ( $a, $b ) {
                return strcmp( $a['slug'], $b['slug'] );
            }
        );

        return $templates;
    }

    /**
     * Retrieves the plugin template path for a given slug.
     *
     * @param string $slug Template slug.
     * @return string|null
     */
    protected static function get_plugin_template_path( string $slug ) : ?string {
        $slug = sanitize_key( $slug );
        if ( '' === $slug ) {
            return null;
        }

        $plugin_dir = realpath( trailingslashit( dirname( __DIR__ ) ) . 'templates/emails/' );

        if ( false === $plugin_dir ) {
            return null;
        }

        $path = $plugin_dir . DIRECTORY_SEPARATOR . $slug . '.html.php';
        $real = file_exists( $path ) ? realpath( $path ) : false;

        if ( false === $real ) {
            return null;
        }

        if ( 0 === strpos( $real, $plugin_dir ) ) {
            return $real;
        }

        return null;
    }

    /**
     * Attempts to locate a theme override for the provided template slug.
     *
     * @param string $slug Template slug.
     * @return string|null Full path to the override when available.
     */
    protected static function get_theme_override_path( string $slug ) : ?string {
        $slug       = sanitize_key( $slug );
        $theme_path = trailingslashit( get_stylesheet_directory() ) . 'wrpa/emails/' . $slug . '.html.php';

        if ( file_exists( $theme_path ) ) {
            return $theme_path;
        }

        return null;
    }

    /**
     * Sanitizes template HTML content using a wp_kses_post baseline.
     *
     * @param string $content Raw editor content.
     * @param string $slug    Template slug.
     * @return string
     */
    protected static function sanitize_template_html( string $content, string $slug ) : string {
        $allowed = wp_kses_allowed_html( 'post' );

        $extended = [
            'html'  => [ 'lang' => true ],
            'head'  => [],
            'meta'  => [ 'charset' => true, 'name' => true, 'content' => true ],
            'title' => [],
            'body'  => [ 'style' => true ],
            'table' => [ 'role' => true, 'width' => true, 'cellpadding' => true, 'cellspacing' => true, 'style' => true ],
            'tr'    => [ 'style' => true ],
            'td'    => [ 'align' => true, 'style' => true, 'width' => true, 'valign' => true, 'colspan' => true ],
            'th'    => [ 'align' => true, 'style' => true, 'width' => true, 'valign' => true, 'colspan' => true ],
        ];

        $allowed = array_merge( $allowed, $extended );
        $allowed = apply_filters( 'wrpa_email_admin_allowed_html', $allowed, $slug );

        return wp_kses( $content, $allowed, [ 'http', 'https', 'mailto', 'tel' ] );
    }

    /**
     * Validates HTML structure using DOMDocument when available.
     *
     * @param string $html HTML string.
     * @return bool
     */
    protected static function is_html_valid( string $html ) : bool {
        if ( ! class_exists( '\\DOMDocument' ) ) {
            return true;
        }

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;

        $internal = function_exists( 'libxml_use_internal_errors' ) ? libxml_use_internal_errors( true ) : null;
        $loaded   = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
        $errors   = function_exists( 'libxml_get_errors' ) ? libxml_get_errors() : [];

        if ( function_exists( 'libxml_clear_errors' ) ) {
            libxml_clear_errors();
        }

        if ( function_exists( 'libxml_use_internal_errors' ) ) {
            libxml_use_internal_errors( $internal );
        }

        return (bool) $loaded && empty( $errors );
    }

    /**
     * Generates preview HTML for the given template using dummy data.
     *
     * @param string               $slug Template slug.
     * @param array<string, mixed> $vars Placeholder data.
     * @return string Rendered HTML output.
     */
    protected static function generate_preview_html( string $slug, array $vars ) : string {
        $file = WRPA_Email::locate_template( $slug );

        if ( ! $file || ! file_exists( $file ) ) {
            return '';
        }

        extract( [ 'vars' => $vars ], EXTR_SKIP );

        ob_start();
        include $file;
        $html = (string) ob_get_clean();

        if ( '' === $html ) {
            return '';
        }

        return WRPA_Email::replace_placeholders( $html, $vars );
    }

    /**
     * Provides dummy data for previews and manual sends.
     *
     * @param int $user_id Optional user identifier for context.
     * @return array<string,mixed>
     */
    protected static function get_dummy_template_vars( int $user_id = 0 ) : array {
        $site_name = get_bloginfo( 'name' );
        $admin_email = get_option( 'admin_email', 'support@example.com' );
        $plan = WRPA_Access::get_user_plan( $user_id ) ?: [
            'key'      => 'monthly',
            'name'     => __( 'Monthly', 'wrpa' ),
            'interval' => 'monthly',
        ];

        $core_urls = class_exists( __NAMESPACE__ . '\\WRPA_Core' ) ? WRPA_Core::urls() : [];
        $account_url = $core_urls['account_url'] ?? ( class_exists( __NAMESPACE__ . '\\WRPA_Urls' )
            ? WRPA_Urls::account_url()
            : 'https://wisdomrainbookmusic.com/my-account/' );

        $unsubscribe_url = $core_urls['unsubscribe_url'] ?? ( class_exists( __NAMESPACE__ . '\\WRPA_Urls' )
            ? WRPA_Urls::unsubscribe_base_url()
            : 'https://wisdomrainbookmusic.com/unsubscribe/' );

        $vars = [
            'user_first_name'       => __( 'Ada', 'wrpa' ),
            'user_last_name'        => __( 'Lovelace', 'wrpa' ),
            'user_name'             => __( 'Ada Lovelace', 'wrpa' ),
            'plan_name'             => $plan['name'] ?? __( 'Premium', 'wrpa' ),
            'plan_key'              => $plan['key'] ?? 'premium',
            'plan_interval_label'   => __( 'per month', 'wrpa' ),
            'plan_price'            => '₺149,90',
            'expire_date_human'     => date_i18n( get_option( 'date_format', 'Y-m-d' ), strtotime( '+30 days' ) ),
            'site_name'             => $site_name,
            'support_email'         => $admin_email,
            'dashboard_url'         => home_url( '/panel/' ),
            'manage_subscription_url' => home_url( '/abonelik/' ),
            'account_url'           => $account_url,
            'order_id'              => 'WRPA-12345',
            'verify_email_url'      => $user_id ? WRPA_Email_Verify::get_verify_url( $user_id ) : home_url( '/verify-email/' ),
            'unsubscribe_url'       => $unsubscribe_url,
            'gift_card_url'         => home_url( '/hediye-karti/' ),
            'campaign_event_url'    => home_url( '/etkinlik/wrpa/' ),
            'share_link'            => home_url( '/paylas/wrpa/' ),
            'returning_member_guide' => home_url( '/rehber/geri-donus/' ),
            'special_offer_text'    => __( 'Use code WRPA-20 for 20% off', 'wrpa' ),
            'secondary_cta_text'    => __( 'Visit the help center', 'wrpa' ),
            'primary_cta_label'     => __( 'View Details', 'wrpa' ),
            'primary_cta_url'       => home_url( '/kampanya/' ),
            'blast_title'           => __( 'Community Update', 'wrpa' ),
            'blast_intro'           => __( 'Here is a quick update about our latest programs.', 'wrpa' ),
            'blast_body'            => __( 'We have released new guided sessions and community features to explore.', 'wrpa' ),
            'plan_name_plain'       => $plan['name'] ?? __( 'Premium', 'wrpa' ),
        ];

        if ( $user_id ) {
            $vars = array_merge( $vars, WRPA_Email::get_user_context( $user_id ) );

            if ( class_exists( __NAMESPACE__ . '\\WRPA_Email_Unsubscribe' ) ) {
                $vars['unsubscribe_url'] = WRPA_Email_Unsubscribe::get_unsubscribe_url( $user_id );
            }
        }

        return WRPA_Email::get_placeholder_map( $vars );
    }
}
