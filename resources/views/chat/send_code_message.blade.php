Ваш код активации: {{ $code }}

@if($shop && $shop->ym_chat_code_footer)
{{ $shop->ym_chat_code_footer }}
@else
Благодарим за покупку в {{ $shop->name ?? 'нашем магазине' }}! Инструкция по активации также отправлена на ваш Email.
@endif
