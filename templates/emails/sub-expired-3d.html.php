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
                            <h1 style="margin:0 0 16px; font-size:26px; color:#111827;">Aboneliğiniz sona erdi</h1>
                            <p style="margin:0 0 16px; font-size:16px; line-height:1.6; color:#4b5563;">
                                Merhaba {user_first_name}, {plan_name} aboneliğiniz {expire_date_human} tarihinde sona erdi ve erişiminiz durduruldu. Ayrıcalıklarınızı yeniden kazanmak için aboneliğinizi şimdi yenileyebilirsiniz.
                            </p>
                            <p style="margin:0 0 24px; font-size:16px; line-height:1.6; color:#4b5563;">
                                Yenilediğinizde kişisel öğrenme yolları, indirilebilir kaynaklar ve topluluk etkinlikleri tekrar kullanımınıza açılacak.
                            </p>
                            <p style="text-align:center; margin:0 0 32px;">
                                <a href="{subscribe_url}" style="display:inline-block; padding:14px 32px; background:#2563eb; color:#ffffff; font-size:16px; font-weight:600; border-radius:8px; text-decoration:none;">Tekrar Abone Ol</a>
                            </p>
                            <p style="margin:0; font-size:14px; line-height:1.6; color:#6b7280;">
                                Abonelik seçenekleri hakkında sorularınız varsa {support_email} adresinden bizimle iletişime geçebilirsiniz.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f3f4f6; padding:20px 40px; text-align:center;">
                            <p style="margin:0; font-size:13px; color:#6b7280;">© {site_name} · <a href="{subscribe_url}" style="color:#2563eb; text-decoration:none;">Aboneliği Yenile</a> · <a href="{unsubscribe_url}" style="color:#2563eb; text-decoration:none;">Bildirimleri Kapat</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
