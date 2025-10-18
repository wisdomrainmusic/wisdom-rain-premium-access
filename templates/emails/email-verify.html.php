<?php
/**
 * Email verification template used by WRPA_Email::send_verification().
 *
 * @var array $vars Context provided by WRPA_Email::send_email().
 */

$first_name = $vars['user_first_name'] ?? '';
$site_name  = $vars['site_name'] ?? get_bloginfo( 'name' );
$verify_url = $vars['verify_url'] ?? '';
$dashboard  = $vars['dashboard_url'] ?? home_url( '/my-account/' );
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title><?php echo esc_html( $site_name ); ?></title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:'Helvetica Neue', Helvetica, Arial, sans-serif;">
        <tr>
            <td align="center" style="padding:24px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="padding:32px 40px;">
                            <h1 style="font-size:24px; margin:0 0 16px; color:#111827;">
                                <?php
                                echo sprintf(
                                    esc_html__( 'Confirm your email address, %s', 'wrpa' ),
                                    esc_html( $first_name )
                                );
                                ?>
                            </h1>
                            <p style="font-size:16px; line-height:1.5; color:#4b5563; margin:0 0 24px;">
                                <?php esc_html_e( 'Thanks for joining Wisdom Rain Premium Access. Please verify your email address to activate your membership benefits.', 'wrpa' ); ?>
                            </p>
                            <?php if ( ! empty( $verify_url ) ) : ?>
                                <p style="text-align:center; margin:0 0 24px;">
                                    <a href="<?php echo esc_url( $verify_url ); ?>" style="display:inline-block; padding:14px 28px; background:#2563eb; color:#ffffff; font-weight:600; border-radius:8px; text-decoration:none;">
                                        <?php esc_html_e( 'Verify email address', 'wrpa' ); ?>
                                    </a>
                                </p>
                                <p style="font-size:14px; line-height:1.6; color:#6b7280; margin:0 0 16px;">
                                    <?php esc_html_e( 'If the button above does not work, copy and paste this link into your browser:', 'wrpa' ); ?><br />
                                    <a href="<?php echo esc_url( $verify_url ); ?>" style="color:#2563eb; word-break:break-all;">
                                        <?php echo esc_html( $verify_url ); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                            <p style="font-size:14px; line-height:1.6; color:#9ca3af; margin:0;">
                                <?php
                                echo sprintf(
                                    wp_kses_post( __( 'Need help? Visit your <a href="%1$s">account dashboard</a> or reply to this message.', 'wrpa' ) ),
                                    esc_url( $dashboard )
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#111827; padding:20px 40px;">
                            <p style="margin:0; font-size:13px; color:#f9fafb;">
                                <?php echo esc_html( $site_name ); ?> · <?php echo esc_html( $vars['site_url'] ?? home_url( '/' ) ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
