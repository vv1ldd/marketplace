@php
    use App\Models\SystemSetting;
    $redeemUrl = $shop?->getEffectiveRedeemUrl()
        ?? rtrim((string) SystemSetting::get('default_redeem_url', 'https://wildcloud.ru/redeem'), '/');
    $redeemDisplay = preg_replace('#^https?://#i', '', $redeemUrl);
    $hubDomain = parse_url($redeemUrl, PHP_URL_HOST) ?: 'wildcloud.ru';
    $supportHost = $shop && filled($shop->domain)
        ? preg_replace('#^https?://#i', '', trim((string) $shop->domain, '/'))
        : $hubDomain;
    $supportUrl = 'https://'.$supportHost.'/support';
    $name = $shop?->name ?? 'Marketplace';
@endphp
<div
    style="margin:0;padding:0;background-color:#f4f4f5;color:#18181b;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.55;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0;padding:24px 16px;">
        <tr>
            <td align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation"
                    style="max-width:600px;width:100%;background-color:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #e4e4e7;box-shadow:0 10px 40px rgba(0,0,0,0.06);">
                    <tr>
                        <td
                            style="padding:28px 24px 20px;text-align:center;background:linear-gradient(135deg,rgba(37,99,235,0.14) 0%,rgba(79,70,229,0.12) 100%);border-bottom:1px solid #e4e4e7;">
                            <p
                                style="margin:0;font-size:12px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#4338ca;">
                                Цифровой товар</p>
                            <p style="margin:10px 0 0;font-size:26px;font-weight:800;line-height:1.2;color:#18181b;">
                                Активация</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 24px 8px;color:#3f3f46;font-size:16px;line-height:1.6;text-align:left;">
                            Здравствуйте{{ filled($first_name) ? ', '.$first_name : '' }}!
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 20px;color:#52525b;font-size:15px;line-height:1.6;text-align:left;">
                            Заказ <strong style="color:#18181b;">{{ $order_id }}</strong> — ниже коды активации и краткая
                            инструкция в стиле нашей страницы redeem.
                        </td>
                    </tr>

                    @foreach ($keys_data as $key_data)
                        <tr>
                            <td style="padding:12px 24px 0;text-align:left;">
                                <p style="margin:0 0 6px;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#71717a;">
                                    Товар</p>
                                <p style="margin:0;font-size:18px;font-weight:700;color:#18181b;line-height:1.35;">
                                    {{ $key_data['name'] ?? 'Цифровой товар' }}</p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:8px 24px 0;text-align:left;color:#52525b;font-size:14px;">
                                Активируйте до <strong style="color:#18181b;">{{ now()->addYear()->format('d.m.Y') }}</strong>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:12px 24px 20px;text-align:left;">
                                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                                    style="background-color:#fafafa;border:1px solid #e4e4e7;border-radius:16px;">
                                    <tr>
                                        <td style="padding:16px 18px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#71717a;">
                                            Код активации</td>
                                    </tr>
                                    <tr>
                                        <td
                                            style="padding:0 18px 18px;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:22px;font-weight:800;letter-spacing:0.06em;color:#18181b;word-break:break-all;">
                                            {{ $key_data['key'] }}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endforeach

                    <tr>
                        <td style="padding:8px 24px 28px;text-align:left;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                                style="background:linear-gradient(180deg,#fafafa 0%,#ffffff 100%);border:1px solid #e4e4e7;border-radius:16px;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <p
                                            style="margin:0 0 10px;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:0.06em;color:#2563eb;">
                                            Как активировать</p>
                                        <p style="margin:0 0 12px;color:#3f3f46;font-size:15px;line-height:1.65;">
                                            <strong style="color:#18181b;">1.</strong> Откройте страницу активации<br>
                                            <a href="{{ $redeemUrl }}" target="_blank" rel="noopener noreferrer"
                                                style="color:#2563eb;font-weight:700;text-decoration:none;">{{ $redeemDisplay }}</a>
                                        </p>
                                        <p style="margin:0 0 12px;color:#3f3f46;font-size:15px;line-height:1.65;">
                                            <strong style="color:#18181b;">2.</strong> Скопируйте код из этого письма.
                                        </p>
                                        <p style="margin:0 0 12px;color:#3f3f46;font-size:15px;line-height:1.65;">
                                            <strong style="color:#18181b;">3.</strong> Вставьте код и подтвердите email —
                                            дальше откроется страница с кодом для сервиса.
                                        </p>
                                        <p style="margin:0;color:#71717a;font-size:13px;line-height:1.55;">
                                            Нужна помощь?
                                            <a href="{{ $supportUrl }}" target="_blank" rel="noopener noreferrer"
                                                style="color:#2563eb;font-weight:600;text-decoration:none;">Поддержка
                                                {{ $name }}</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
