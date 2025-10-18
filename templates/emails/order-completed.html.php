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
              <h2 style='margin:0 0 15px 0;font-size:22px;color:#000000;'>Your Order Is Complete</h2>
              <p style='margin:0 0 10px 0;font-size:16px;'>Hi {user_first_name},</p>
              <p style='margin:0 0 18px 0;font-size:15px;line-height:1.5;'>Thank you for purchasing the {plan_name} plan. Your payment has been processed successfully and full access to {site_name} is now active on your account.</p>
              <p style='margin:0 0 18px 0;font-size:15px;line-height:1.5;'>You can review billing details and payment history any time. Your latest invoice is available here: <a href='{invoice_url}' style='color:#d50000;text-decoration:none;'>Download invoice</a>.</p>
              <p style='margin:0 0 18px 0;font-size:15px;line-height:1.5;'>Have questions or need adjustments? Contact us at <a href='mailto:{support_email}' style='color:#d50000;text-decoration:none;'>{support_email}</a> and we'll be happy to help.</p>
              <p style='margin:26px 0;'>
                <a href='{manage_subscription_url}' style='display:inline-block;padding:12px 24px;text-decoration:none;border-radius:6px;
                   background-color:#d50000;color:#ffffff;font-weight:bold;'>
                  Manage Subscription
                </a>
              </p>
              <p style='margin:0 0 20px 0;font-size:14px;color:#333;'>Warm regards,<br><strong>Wisdom Rain</strong></p>
              <hr style='border:0;border-top:1px solid #ddd;margin:30px 0;'>
              <small style='font-size:12px;color:#666;'>You are receiving this email from {site_name}. 
                <a href='{dashboard_url}' style='color:#d50000;text-decoration:none;'>My Account</a>
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
