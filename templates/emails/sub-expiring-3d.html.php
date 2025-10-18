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
              <h2 style='margin:0 0 15px 0;font-size:22px;color:#000000;'>Your Subscription Ends in 3 Days</h2>
              <p style='margin:0 0 10px 0;font-size:16px;'>Hi {user_first_name},</p>
              <p style='margin:0 0 18px 0;font-size:15px;line-height:1.5;'>This is a friendly reminder that your {plan_name} subscription will end on {expire_date_human}. Renew now to avoid losing access to premium classes, live gatherings, and the member community.</p>
              <p style='margin:0 0 18px 0;font-size:15px;line-height:1.5;'>When you renew before the deadline, all of your saved playlists and progress remain intact. It only takes a moment.</p>
              <p style='margin:0 0 18px 0;font-size:15px;line-height:1.5;'>Need support? Email <a href='mailto:{support_email}' style='color:#d50000;text-decoration:none;'>{support_email}</a> or send us a message from <a href='{dashboard_url}' style='color:#d50000;text-decoration:none;'>your dashboard</a>.</p>
              <p style='margin:26px 0;'>
                <a href='{manage_subscription_url}' style='display:inline-block;padding:12px 24px;text-decoration:none;border-radius:6px;
                   background-color:#d50000;color:#ffffff;font-weight:bold;'>
                  Renew Now
                </a>
              </p>
              <p style='margin:0 0 20px 0;font-size:14px;color:#333;'>Warm regards,<br><strong>Wisdom Rain</strong></p>
              <hr style='border:0;border-top:1px solid #ddd;margin:30px 0;'>
              <small style='font-size:12px;color:#666;'>You are receiving this email from {site_name}. 
                <a href='{dashboard_url}' style='color:#d50000;text-decoration:none;'>My Account</a> · 
                <a href='{unsubscribe_url}' style='color:#d50000;text-decoration:none;'>Unsubscribe</a>
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
