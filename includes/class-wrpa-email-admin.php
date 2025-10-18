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
 * Provides an Email Templates management screen within the WRPA admin menu.
 */
class WRPA_Email_Admin {

    /**
     * Cached preview HTML rendered during the current request.
     *
     * @var string
     */
    protected static $preview_html = '';

    /**
     * Accumulated admin notices for the current request.
     *
     * @var array<int, array{type:string,message:string}>
     */
    protected static $messages = [];

    /**
     * Bootstraps admin hooks for managing email templates.
     */
    public static function init() : void {
        add_action( 'admin_menu', [ __CLASS__, 'register_submenu' ] );
    }

    /**
     * Registers the "Email" admin submenus under the WRPA dashboard.
     */
    public static function register_submenu() : void {
        add_submenu_page(
            'wrpa-dashboard',
            __( 'Email Templates', 'wrpa' ),
            __( 'Email Templates', 'wrpa' ),
            'manage_options',
            'wrpa-emails',
            [ __CLASS__, 'render_page' ]
        );

        add_submenu_page(
            'wrpa-dashboard',
            __( 'Email Settings', 'wrpa' ),
            __( 'Email Settings', 'wrpa' ),
            'manage_options',
            'wrpa-email-settings',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    /**
     * Renders the admin screen showing available email templates.
     */
    public static function render_page() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'wrpa' ) );
        }

        self::maybe_handle_actions();

        $templates       = self::get_templates();
        $current_user_id = get_current_user_id();
        $context         = $current_user_id ? WRPA_Email::get_user_context( $current_user_id ) : [];

        echo '<div class="wrap wrpa-email-admin">';
        echo '<h1>' . esc_html__( 'Email Templates', 'wrpa' ) . '</h1>';

        if ( class_exists( '\\WRPA\\WRPA_Admin' ) ) {
            WRPA_Admin::render_nav_tabs( 'wrpa-emails' );
        }

        self::render_notices();

        echo '<p>' . esc_html__( 'Review template files, preview them with sample data, or send test messages to verify delivery.', 'wrpa' ) . '</p>';

        self::render_template_table( $templates, $context );
        self::render_tools_panel( $templates );

        if ( self::$preview_html ) {
            echo '<h2>' . esc_html__( 'Template Preview', 'wrpa' ) . '</h2>';
            echo '<div class="wrpa-email-preview" style="border:1px solid #ccd0d4; background:#fff; padding:20px; max-width:900px;">';
            echo wp_kses_post( self::$preview_html );
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Renders the Email Settings screen with diagnostic tools.
     */
    public static function render_settings_page() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'wrpa' ) );
        }

        self::maybe_handle_settings_actions();

        $fluent_smtp_available = self::is_fluent_smtp_available();
        $schema_stub           = self::get_email_log_schema();

        echo '<div class="wrap wrpa-email-settings">';
        echo '<h1>' . esc_html__( 'Email Settings', 'wrpa' ) . '</h1>';

        if ( class_exists( '\\WRPA\\WRPA_Admin' ) ) {
            WRPA_Admin::render_nav_tabs( 'wrpa-email-settings' );
        }

        self::render_notices();

        echo '<h2>' . esc_html__( 'SMTP Connection Test', 'wrpa' ) . '</h2>';

        $status_label = $fluent_smtp_available
            ? esc_html__( 'FluentSMTP detected. Outbound email will use the configured connection.', 'wrpa' )
            : esc_html__( 'FluentSMTP not detected. Activate the FluentSMTP plugin to enable advanced delivery logs.', 'wrpa' );

        echo '<p>' . $status_label . '</p>';

        echo '<form method="post" class="wrpa-smtp-check" style="max-width:420px;">';
        wp_nonce_field( 'wrpa_check_smtp' );
        echo '<input type="hidden" name="wrpa_check_smtp" value="1" />';
        echo '<p>' . esc_html__( 'Re-run the detector after installing or activating FluentSMTP.', 'wrpa' ) . '</p>';
        echo '<p><button type="submit" class="button">' . esc_html__( 'Check Connection', 'wrpa' ) . '</button></p>';
        echo '</form>';

        echo '<hr />';

        echo '<h2>' . esc_html__( 'Blast Now', 'wrpa' ) . '</h2>';
        echo '<p>' . esc_html__( 'Send a one-off HTML message through the configured mailer to validate deliverability.', 'wrpa' ) . '</p>';

        echo '<form method="post" class="wrpa-blast-now" style="max-width:600px;">';
        wp_nonce_field( 'wrpa_blast_now' );
        echo '<input type="hidden" name="wrpa_blast_now" value="1" />';
        echo '<p><label for="wrpa-blast-email">' . esc_html__( 'Recipient Email', 'wrpa' ) . '</label><br />';
        echo '<input type="email" id="wrpa-blast-email" name="wrpa_blast_email" class="regular-text" required /></p>';
        echo '<p><label for="wrpa-blast-subject">' . esc_html__( 'Subject', 'wrpa' ) . '</label><br />';
        echo '<input type="text" id="wrpa-blast-subject" name="wrpa_blast_subject" class="regular-text" required /></p>';
        echo '<p><label for="wrpa-blast-message">' . esc_html__( 'Message (HTML allowed)', 'wrpa' ) . '</label><br />';
        echo '<textarea id="wrpa-blast-message" name="wrpa_blast_message" class="large-text code" rows="6" required></textarea></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Blast Now', 'wrpa' ) . '</button></p>';
        echo '</form>';

        echo '<hr />';

        echo '<h2>' . esc_html__( 'wrpa_email_log Schema (Preview)', 'wrpa' ) . '</h2>';
        echo '<p>' . esc_html__( 'The following SQL stub documents the planned logging table. It is not executed automatically yet.', 'wrpa' ) . '</p>';
        echo '<textarea readonly class="large-text code" rows="10" style="min-height:160px;">' . esc_textarea( $schema_stub ) . '</textarea>';

        echo '</div>';
    }

    /**
     * Handles preview or test send requests coming from the admin screen.
     */
    protected static function maybe_handle_actions() : void {
        if ( empty( $_POST ) ) {
            return;
        }

        if ( isset( $_POST['wrpa_preview_email'] ) ) {
            self::handle_preview_action();
        }

        if ( isset( $_POST['wrpa_send_test_email'] ) ) {
            self::handle_test_send_action();
        }
    }

    /**
     * Handles POST requests made on the Email Settings screen.
     */
    protected static function maybe_handle_settings_actions() : void {
        if ( empty( $_POST ) ) {
            return;
        }

        if ( isset( $_POST['wrpa_check_smtp'] ) ) {
            self::handle_smtp_check_action();
        }

        if ( isset( $_POST['wrpa_blast_now'] ) ) {
            self::handle_blast_now_action();
        }
    }

    /**
     * Processes a preview request and stores the rendered HTML.
     */
    protected static function handle_preview_action() : void {
        check_admin_referer( 'wrpa_preview_email' );

        $template = isset( $_POST['wrpa_email_template'] ) ? sanitize_key( wp_unslash( $_POST['wrpa_email_template'] ) ) : '';
        $user_id  = isset( $_POST['wrpa_email_user'] ) ? absint( $_POST['wrpa_email_user'] ) : 0;

        if ( ! $template ) {
            self::add_message( 'error', __( 'Please choose a template before generating a preview.', 'wrpa' ) );
            return;
        }

        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            self::add_message( 'error', __( 'A preview requires a valid user account to provide sample data.', 'wrpa' ) );
            return;
        }

        $html = self::generate_preview_html( $template, $user_id );

        if ( '' === $html ) {
            self::add_message( 'error', __( 'The selected template could not be rendered.', 'wrpa' ) );
            return;
        }

        self::$preview_html = $html;

        self::add_message(
            'success',
            sprintf(
                /* translators: %s: template slug */
                __( 'Preview generated for "%s".', 'wrpa' ),
                $template
            )
        );
    }

    /**
     * Sends the chosen template to the specified user as a test email.
     */
    protected static function handle_test_send_action() : void {
        check_admin_referer( 'wrpa_send_test_email' );

        $template = isset( $_POST['wrpa_email_template'] ) ? sanitize_key( wp_unslash( $_POST['wrpa_email_template'] ) ) : '';
        $user_id  = isset( $_POST['wrpa_email_user'] ) ? absint( $_POST['wrpa_email_user'] ) : 0;

        if ( ! $template ) {
            self::add_message( 'error', __( 'Please choose a template before sending a test email.', 'wrpa' ) );
            return;
        }

        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            self::add_message( 'error', __( 'A valid user is required to send a test email.', 'wrpa' ) );
            return;
        }

        $sent = WRPA_Email::send_email( $user_id, $template );

        if ( $sent ) {
            self::add_message(
                'success',
                __( 'Test email dispatched successfully.', 'wrpa' )
            );
            return;
        }

        self::add_message(
            'error',
            __( 'The test email could not be sent. Check the user account and mail configuration.', 'wrpa' )
        );
    }

    /**
     * Runs the FluentSMTP detector and records an admin notice with the result.
     */
    protected static function handle_smtp_check_action() : void {
        check_admin_referer( 'wrpa_check_smtp' );

        if ( self::is_fluent_smtp_available() ) {
            self::add_message(
                'success',
                __( 'FluentSMTP is active. Email delivery should use the configured connection.', 'wrpa' )
            );
            return;
        }

        self::add_message(
            'error',
            __( 'FluentSMTP could not be detected. Install or activate the FluentSMTP plugin to enable SMTP logging.', 'wrpa' )
        );
    }

    /**
     * Sends a manual email using the current mail configuration.
     */
    protected static function handle_blast_now_action() : void {
        check_admin_referer( 'wrpa_blast_now' );

        $recipient = isset( $_POST['wrpa_blast_email'] ) ? sanitize_email( wp_unslash( $_POST['wrpa_blast_email'] ) ) : '';
        $subject   = isset( $_POST['wrpa_blast_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['wrpa_blast_subject'] ) ) : '';
        $message   = isset( $_POST['wrpa_blast_message'] ) ? wp_kses_post( wp_unslash( $_POST['wrpa_blast_message'] ) ) : '';

        if ( ! $recipient || ! is_email( $recipient ) ) {
            self::add_message( 'error', __( 'Enter a valid recipient email address before sending.', 'wrpa' ) );
            return;
        }

        if ( '' === $subject ) {
            self::add_message( 'error', __( 'Enter a subject line for the blast email.', 'wrpa' ) );
            return;
        }

        if ( '' === trim( wp_strip_all_tags( $message ) ) ) {
            self::add_message( 'error', __( 'Provide a message body before sending.', 'wrpa' ) );
            return;
        }

        $sent = wp_mail(
            $recipient,
            $subject,
            wpautop( $message ),
            [ 'Content-Type: text/html; charset=UTF-8' ]
        );

        if ( $sent ) {
            self::add_message( 'success', __( 'Blast email dispatched successfully.', 'wrpa' ) );
            return;
        }

        self::add_message( 'error', __( 'The blast email could not be sent. Check your SMTP configuration.', 'wrpa' ) );
    }

    /**
     * Builds a table listing available templates and their subjects.
     *
     * @param array<int, array<string, mixed>> $templates Template descriptors.
     * @param array<string, mixed>              $context   Preview context derived from the current user.
     */
    protected static function render_template_table( array $templates, array $context ) : void {
        echo '<h2>' . esc_html__( 'Available Templates', 'wrpa' ) . '</h2>';
        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr>';
        echo '<th scope="col">' . esc_html__( 'Slug', 'wrpa' ) . '</th>';
        echo '<th scope="col">' . esc_html__( 'Default Subject', 'wrpa' ) . '</th>';
        echo '<th scope="col">' . esc_html__( 'Source', 'wrpa' ) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if ( empty( $templates ) ) {
            echo '<tr><td colspan="3">' . esc_html__( 'No templates were found in the templates/emails directory.', 'wrpa' ) . '</td></tr>';
        } else {
            foreach ( $templates as $template ) {
                $subject = WRPA_Email::subject_for( $template['slug'], $context );
                $source  = $template['source'];

                printf(
                    '<tr><td><code>%1$s</code></td><td>%2$s</td><td>%3$s</td></tr>',
                    esc_html( $template['slug'] ),
                    esc_html( $subject ),
                    esc_html( $source )
                );
            }
        }

        echo '</tbody>';
        echo '</table>';
    }

    /**
     * Outputs the preview and test send forms.
     *
     * @param array<int, array<string, mixed>> $templates Template descriptors.
     */
    protected static function render_tools_panel( array $templates ) : void {
        $options = '';

        foreach ( $templates as $template ) {
            $options .= sprintf(
                '<option value="%1$s">%2$s</option>',
                esc_attr( $template['slug'] ),
                esc_html( $template['slug'] )
            );
        }

        echo '<div class="wrpa-email-tools" style="display:flex; gap:24px; margin-top:24px; flex-wrap:wrap;">';

        echo '<form method="post" class="wrpa-email-preview-form" style="flex:1 1 280px; max-width:420px;">';
        wp_nonce_field( 'wrpa_preview_email' );
        echo '<input type="hidden" name="wrpa_preview_email" value="1" />';
        echo '<h3>' . esc_html__( 'Preview Template', 'wrpa' ) . '</h3>';
        echo '<p>' . esc_html__( 'Select a template and user to generate a preview with live data.', 'wrpa' ) . '</p>';
        echo '<p><label for="wrpa-preview-template">' . esc_html__( 'Template', 'wrpa' ) . '</label><br />';
        echo '<select id="wrpa-preview-template" name="wrpa_email_template" class="regular-text">';
        echo '<option value="">' . esc_html__( 'Select template…', 'wrpa' ) . '</option>';
        echo $options;
        echo '</select></p>';
        echo '<p><label for="wrpa-preview-user">' . esc_html__( 'User ID', 'wrpa' ) . '</label><br />';
        echo '<input type="number" id="wrpa-preview-user" name="wrpa_email_user" class="small-text" min="1" value="' . esc_attr( get_current_user_id() ) . '" /></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Generate Preview', 'wrpa' ) . '</button></p>';
        echo '</form>';

        echo '<form method="post" class="wrpa-email-test-form" style="flex:1 1 280px; max-width:420px;">';
        wp_nonce_field( 'wrpa_send_test_email' );
        echo '<input type="hidden" name="wrpa_send_test_email" value="1" />';
        echo '<h3>' . esc_html__( 'Send Test Email', 'wrpa' ) . '</h3>';
        echo '<p>' . esc_html__( 'Send the selected template to a specific user for verification.', 'wrpa' ) . '</p>';
        echo '<p><label for="wrpa-test-template">' . esc_html__( 'Template', 'wrpa' ) . '</label><br />';
        echo '<select id="wrpa-test-template" name="wrpa_email_template" class="regular-text">';
        echo '<option value="">' . esc_html__( 'Select template…', 'wrpa' ) . '</option>';
        echo $options;
        echo '</select></p>';
        echo '<p><label for="wrpa-test-user">' . esc_html__( 'User ID', 'wrpa' ) . '</label><br />';
        echo '<input type="number" id="wrpa-test-user" name="wrpa_email_user" class="small-text" min="1" value="' . esc_attr( get_current_user_id() ) . '" /></p>';
        echo '<p><button type="submit" class="button">' . esc_html__( 'Send Test', 'wrpa' ) . '</button></p>';
        echo '</form>';

        echo '</div>';
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
    }

    /**
     * Returns metadata for available template files.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function get_templates() : array {
        $templates = [];
        $plugin_dir = trailingslashit( dirname( __DIR__ ) ) . 'templates/emails/';

        $files = glob( $plugin_dir . '*.html.php' );

        if ( ! $files ) {
            $files = [];
        }

        foreach ( $files as $file ) {
            $slug   = basename( $file, '.html.php' );
            $source = __( 'Plugin', 'wrpa' );
            $override = self::get_theme_override_path( $slug );

            if ( $override ) {
                $source = __( 'Theme Override', 'wrpa' );
            }

            $templates[] = [
                'slug'   => $slug,
                'source' => $source,
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
     * Attempts to locate a theme override for the provided template slug.
     *
     * @param string $slug Template slug.
     * @return string|null Full path to the override when available.
     */
    protected static function get_theme_override_path( string $slug ) : ?string {
        $theme_path = trailingslashit( get_stylesheet_directory() ) . 'wrpa/emails/' . $slug . '.html.php';

        if ( file_exists( $theme_path ) ) {
            return $theme_path;
        }

        return null;
    }

    /**
     * Determines whether the FluentSMTP plugin appears to be available.
     */
    protected static function is_fluent_smtp_available() : bool {
        return defined( 'FLUENTMAIL' )
            || class_exists( '\\FluentMail\\Includes\\Core\\Application' )
            || class_exists( '\\FluentMail\\App\\Services\\Mailer\\FluentMailer' )
            || function_exists( 'fluentsmtp' );
    }

    /**
     * Returns the SQL stub for the upcoming wrpa_email_log table.
     */
    protected static function get_email_log_schema() : string {
        global $wpdb;

        $table_name = 'wp_wrpa_email_log';

        if ( isset( $wpdb ) && is_object( $wpdb ) && isset( $wpdb->prefix ) ) {
            $table_name = $wpdb->prefix . 'wrpa_email_log';
        }

        $collate = 'DEFAULT CHARSET=utf8mb4';

        if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_charset_collate' ) ) {
            $collate = $wpdb->get_charset_collate();
        }

        $schema  = "CREATE TABLE {$table_name} (\n";
        $schema .= "  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n";
        $schema .= "  user_id bigint(20) unsigned NULL,\n";
        $schema .= "  email varchar(190) NOT NULL,\n";
        $schema .= "  template varchar(100) NOT NULL,\n";
        $schema .= "  subject varchar(255) NOT NULL,\n";
        $schema .= "  sent_at datetime NOT NULL,\n";
        $schema .= "  status varchar(50) NOT NULL DEFAULT 'pending',\n";
        $schema .= "  error text NULL,\n";
        $schema .= "  PRIMARY KEY  (id),\n";
        $schema .= "  KEY user_id (user_id),\n";
        $schema .= "  KEY sent_at (sent_at)\n";
        $schema .= ") {$collate};";

        return $schema;
    }

    /**
     * Generates the preview HTML for the given template using live user data.
     *
     * @param string $slug    Template slug.
     * @param int    $user_id User identifier for placeholder replacement.
     * @return string Rendered HTML output.
     */
    protected static function generate_preview_html( string $slug, int $user_id ) : string {
        $file = WRPA_Email::locate_template( $slug );

        if ( ! $file ) {
            return '';
        }

        $vars = WRPA_Email::get_user_context( $user_id );

        if ( empty( $vars ) ) {
            $vars = [];
        }

        $data = [ 'vars' => $vars ];
        extract( $data, EXTR_SKIP );

        ob_start();
        include $file;
        $html = (string) ob_get_clean();

        return WRPA_Email::replace_placeholders( $html, $vars );
    }
}
