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
              <img src='{fireworks_image_url}' alt='<?php echo esc_attr__( 'Fireworks lighting the night sky', 'wrpa' ); ?>' style='width:100%;max-width:572px;height:auto;border-radius:6px;margin:0 0 18px 0;'>
              <h2 style='margin:0 0 15px 0;font-size:22px;color:#000000;'><?php echo esc_html__( 'Celebrate the New Year with Us', 'wrpa' ); ?></h2>
              <p style='margin:0 0 10px 0;font-size:16px;'><?php echo esc_html__( 'Hi {user_first_name},', 'wrpa' ); ?></p>
              <p style='margin:0 0 18px 0;font-size:15px;line-height:1.5;'><?php echo esc_html__( "You're invited to the {site_name} community gathering on December 31. Join us for intention-setting rituals, live music, and surprise gifts as we welcome a bright new year together.", 'wrpa' ); ?></p>
              <p style='margin:0 0 18px 0;font-size:15px;line-height:1.5;'><?php echo esc_html__( "Seats are limited, so please confirm your participation soon and let us know your goals for 2025. We'll have guided journaling moments and a group toast at midnight.", 'wrpa' ); ?></p>
              <p style='margin:0 0 18px 0;font-size:15px;line-height:1.5;'>
                <?php
                $share_link = '<a href="{share_link}" style="color:#d50000;text-decoration:none;">{share_link}</a>';
                $support_link = '<a href="mailto:{support_email}" style="color:#d50000;text-decoration:none;">{support_email}</a>';
                echo wp_kses(
                    sprintf(
                        /* translators: 1: share link, 2: support email */
                        __( 'Want to bring a friend? Share this link: %1$s. If you have any questions, reach out at %2$s.', 'wrpa' ),
                        $share_link,
                        $support_link
                    ),
                    [ 'a' => [ 'href' => [], 'style' => [] ] ]
                );
                ?>
              </p>
              <p style='margin:26px 0;'>
                <a href='{campaign_event_url}' style='display:inline-block;padding:12px 24px;text-decoration:none;border-radius:6px;
                   background-color:#d50000;color:#ffffff;font-weight:bold;'>
                  <?php echo esc_html__( 'Confirm My Spot', 'wrpa' ); ?>
                </a>
              </p>
              <p style='margin:0 0 20px 0;font-size:14px;color:#333;'><?php echo esc_html__( 'Warm regards,', 'wrpa' ); ?><br><strong><?php echo esc_html__( 'Wisdom Rain', 'wrpa' ); ?></strong></p>
              <hr style='border:0;border-top:1px solid #ddd;margin:30px 0;'>
              <small style='font-size:12px;color:#666;'><?php echo esc_html__( 'You are receiving this email from {site_name}.', 'wrpa' ); ?>
                <a href='{account_url}' style='color:#d50000;text-decoration:none;'><?php echo esc_html__( 'My Account', 'wrpa' ); ?></a> ·
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
