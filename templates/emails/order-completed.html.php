<!doctype html>
<html>
<head>
  <meta charset='UTF-8'>
  <title>{site_name}</title>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'/>
</head>
<body style='margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;background-color:#ffffff;color:#111111;'>
  <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background:#ffffff;'>
    <tr>
      <td align='center'>
        <table width='620' cellpadding='24' cellspacing='0' style='max-width:620px;border:1px solid #eee;'>
          <tr>
            <td>
              <!-- Email Content Starts -->
              <h2 style='margin:0 0 15px 0;font-size:22px;color:#000000;'><?php echo esc_html__( 'Your Order Is Complete', 'wrpa' ); ?></h2>
              <p style='margin:0 0 10px 0;font-size:16px;'><?php echo esc_html__( 'Hi {user_first_name},', 'wrpa' ); ?></p>
              <p style='margin:0 0 18px 0;font-size:15px;line-height:1.5;'><?php echo esc_html__( 'Thank you for purchasing the {plan_name} plan. Your payment has been processed successfully and full access to {site_name} is now active on your account.', 'wrpa' ); ?></p>
              <p style='margin:0 0 18px 0;font-size:15px;line-height:1.5;'>
                <?php
                $invoice_link = '<a href="{invoice_url}" style="color:#d50000;text-decoration:none;">' . esc_html__( 'Download invoice', 'wrpa' ) . '</a>';
                echo wp_kses(
                    sprintf(
                        /* translators: %s: invoice link */
                        __( 'You can review billing details and payment history any time. Your latest invoice is available here: %s.', 'wrpa' ),
                        $invoice_link
                    ),
                    [ 'a' => [ 'href' => [], 'style' => [] ] ]
                );
                ?>
              </p>
              <p style='margin:0 0 18px 0;font-size:15px;line-height:1.5;'>
                <?php
                $support_link = '<a href="mailto:{support_email}" style="color:#d50000;text-decoration:none;">{support_email}</a>';
                echo wp_kses(
                    sprintf(
                        /* translators: %s: support email */
                        __( "Have questions or need adjustments? Contact us at %s and we'll be happy to help.", 'wrpa' ),
                        $support_link
                    ),
                    [ 'a' => [ 'href' => [], 'style' => [] ] ]
                );
                ?>
              </p>
              <p style='margin:26px 0;'>
                <a href='{manage_subscription_url}' style='display:inline-block;padding:12px 24px;text-decoration:none;border-radius:6px;
                   background-color:#d50000;color:#ffffff;font-weight:bold;'>
                  <?php echo esc_html__( 'Manage Subscription', 'wrpa' ); ?>
                </a>
              </p>
              <p style='margin:0 0 20px 0;font-size:14px;color:#333;'><?php echo esc_html__( 'Warm regards,', 'wrpa' ); ?><br><strong><?php echo esc_html__( 'Wisdom Rain', 'wrpa' ); ?></strong></p>
              <hr style='border:0;border-top:1px solid #ddd;margin:30px 0;'>
              <small style='font-size:12px;color:#666;'><?php echo esc_html__( 'You are receiving this email from {site_name}.', 'wrpa' ); ?>
                <a href='{dashboard_url}' style='color:#d50000;text-decoration:none;'><?php echo esc_html__( 'My Account', 'wrpa' ); ?></a>
                &bull;
                <a href='{unsubscribe_url}' style='color:#d50000;text-decoration:none;'><?php echo esc_html__( 'Unsubscribe', 'wrpa' ); ?></a>
              </small>
              <!-- Email Content Ends -->
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
