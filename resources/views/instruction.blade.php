@php
    use App\Models\SystemSetting;
    $shop = $shop ?? null;
    $redeemUrl = $shop
        ? $shop->getEffectiveRedeemUrl()
        : rtrim((string) SystemSetting::get('default_redeem_url', 'https://wildcloud.ru/redeem'), '/');
    $redeemDisplay = preg_replace('#^https?://#i', '', $redeemUrl);
    $hubHost = parse_url($redeemUrl, PHP_URL_HOST) ?: 'wildcloud.ru';
    $supportHost = $shop && filled($shop->domain) ? preg_replace('#^https?://#i', '', trim((string) $shop->domain, '/')) : $hubHost;
    $supportUrl = 'https://'.$supportHost.'/support';
    $brand = $shop?->name ?? 'Marketplace';
@endphp
<table width="100%" cellpadding="0" cellspacing="0" role="presentation"
    style="margin:0;padding:0;background-color:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <tr>
        <td align="center" style="padding:24px 16px;">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="max-width:560px;margin:0 auto;background-color:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #e4e4e7;box-shadow:0 10px 40px rgba(0,0,0,0.06);">
                <tr>
                    <td
                        style="padding:28px 24px 22px;text-align:center;background:linear-gradient(135deg,rgba(37,99,235,0.14) 0%,rgba(79,70,229,0.12) 100%);border-bottom:1px solid #e4e4e7;">
                        <p
                            style="margin:0;font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#4338ca;">
                            Активация ваучера</p>
                        <p style="margin:10px 0 0;font-size:22px;font-weight:800;line-height:1.25;color:#18181b;">
                            Как использовать ваучер</p>
                        <p style="margin:10px 0 0;font-size:14px;line-height:1.5;color:#52525b;max-width:420px;margin-left:auto;margin-right:auto;">
                            Скопируйте код выше, затем пройдите по шагам ниже — в том же духе, что и на странице redeem.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px 24px 8px;color:#3f3f46;font-size:15px;line-height:1.65;text-align:left;">
                        <p style="margin:0 0 14px;"><strong style="color:#18181b;">1.</strong> Скопируйте код ваучера
                            (указан выше в интерфейсе Маркета).</p>
                        <p style="margin:0 0 14px;"><strong style="color:#18181b;">2.</strong> Перейдите на страницу
                            активации:<br>
                            <a href="{{ $redeemUrl }}" target="_blank" rel="noopener noreferrer"
                                style="display:inline-block;margin-top:6px;color:#2563eb;font-weight:700;text-decoration:none;">{{ $redeemDisplay }}</a>
                        </p>
                        <p style="margin:0 0 14px;"><strong style="color:#18181b;">3.</strong> Вставьте код ваучера и
                            подтвердите email — откроется страница с кодом для сервиса.</p>
                        <p style="margin:0 0 14px;"><strong style="color:#18181b;">4.</strong> Код пополнения также
                            придёт на вашу почту.</p>
                        <p style="margin:0;"><strong style="color:#18181b;">5.</strong> Активируйте полученный код в
                            сервисе (Apple, Steam и т.д.).</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 24px 24px;">
                        <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                            style="background-color:#fafafa;border:1px solid #e4e4e7;border-radius:16px;">
                            <tr>
                                <td style="padding:16px 18px;font-size:13px;line-height:1.55;color:#71717a;">
                                    Ваучер можно использовать <strong style="color:#52525b;">только один раз</strong>.
                                    Если возникнут вопросы — напишите в поддержку
                                    <a href="{{ $supportUrl }}" target="_blank" rel="noopener noreferrer"
                                        style="color:#2563eb;font-weight:600;text-decoration:none;">{{ $brand }}</a>.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
