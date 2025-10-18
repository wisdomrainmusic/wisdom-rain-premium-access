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
                            <h1 style="margin:0 0 16px; font-size:26px; color:#111827;">Siparişiniz tamamlandı</h1>
                            <p style="margin:0 0 16px; font-size:16px; line-height:1.6; color:#4b5563;">
                                Merhaba {user_first_name}, {plan_name} planı için verdiğiniz sipariş başarıyla tamamlandı. Artık içeriklerimize sınırsız erişimin tadını çıkarabilirsiniz.
                            </p>
                            <p style="margin:0 0 24px; font-size:16px; line-height:1.6; color:#4b5563;">
                                Üyelik detaylarınızı ve ödeme geçmişinizi kontrol panelinizden inceleyebilir, aboneliğinizi dilediğiniz zaman yönetebilirsiniz.
                            </p>
                            <p style="text-align:center; margin:0 0 32px;">
                                <a href="{manage_subscription_url}" style="display:inline-block; padding:14px 32px; background:#16a34a; color:#ffffff; font-size:16px; font-weight:600; border-radius:8px; text-decoration:none;">Aboneliğimi Yönet</a>
                            </p>
                            <p style="margin:0; font-size:14px; line-height:1.6; color:#6b7280;">
                                Faturanıza her zaman {invoice_url} bağlantısından ulaşabilirsiniz. Yardıma ihtiyaç duyarsanız {support_email} adresinden bize yazmanız yeterli.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f3f4f6; padding:20px 40px; text-align:center;">
                            <p style="margin:0; font-size:13px; color:#6b7280;">© {site_name} · <a href="{dashboard_url}" style="color:#2563eb; text-decoration:none;">Kontrol Paneli</a> · <a href="{invoice_url}" style="color:#2563eb; text-decoration:none;">Faturayı Görüntüle</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
