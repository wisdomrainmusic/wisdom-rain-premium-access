<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{site_name}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:'Helvetica Neue', Helvetica, Arial, sans-serif;">
        <tr>
            <td align="center" style="padding:24px;">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; background:#ffffff; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="padding:32px 40px;">
                            <h1 style="margin:0 0 16px; font-size:26px; color:#111827;">Aboneliğiniz sona ermek üzere</h1>
                            <p style="margin:0 0 16px; font-size:16px; line-height:1.6; color:#4b5563;">
                                Merhaba {user_first_name}, {plan_name} aboneliğinizin bitmesine sadece 3 gün kaldı. {expire_date_human} tarihinde erişiminiz durdurulmadan önce yenilemenizi öneriyoruz.
                            </p>
                            <p style="margin:0 0 24px; font-size:16px; line-height:1.6; color:#4b5563;">
                                Yenileme işlemi ile birlikte canlı oturumlara katılmaya, premium içerikleri indirmeye ve topluluk etkinliklerinde yer almaya devam edebilirsiniz.
                            </p>
                            <p style="text-align:center; margin:0 0 32px;">
                                <a href="{manage_subscription_url}" style="display:inline-block; padding:14px 32px; background:#ef4444; color:#ffffff; font-size:16px; font-weight:600; border-radius:8px; text-decoration:none;">Aboneliği Şimdi Yenile</a>
                            </p>
                            <p style="margin:0; font-size:14px; line-height:1.6; color:#6b7280;">
                                Sorularınız mı var? {support_email} adresine yazın veya <a href="{dashboard_url}" style="color:#2563eb; text-decoration:none;">kontrol panelinizden</a> bize ulaşın.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f3f4f6; padding:20px 40px; text-align:center;">
                            <p style="margin:0; font-size:13px; color:#6b7280;">© {site_name} · <a href="{manage_subscription_url}" style="color:#2563eb; text-decoration:none;">Aboneliği Yönet</a> · <a href="{unsubscribe_url}" style="color:#2563eb; text-decoration:none;">Bildirimleri Kapat</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
