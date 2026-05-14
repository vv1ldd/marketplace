<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td align="center" style="padding:20px; font-family:Arial, sans-serif; color:#222; font-size:14px; line-height:1.5;">

<div style="max-width:420px; text-align:left;">

<p><b>Ваш код пополнения</b></p>

<p>
Вы успешно обменяли ваучер и получили:
</p>

<p style="margin:10px 0; font-weight:bold;">
{{data_get($order, 'info.items.0.offerName')}}
</p>

<p>
Ваш код:
</p>

<div style="margin:12px 0; padding:12px; background:#f3f4f8;
border-radius:8px; text-align:center; font-size:16px; font-weight:bold; letter-spacing:1px;">
{{$code}}
</div>

<p>
Используйте этот код в соответствующем сервисе для пополнения.
</p>

@if(!empty($viewCodePageUrl))
<p style="margin-top:16px;">
<a href="{{ $viewCodePageUrl }}" style="display:inline-block; padding:12px 20px; background:#1a73e8; color:#fff; text-decoration:none; border-radius:8px; font-weight:bold;">
Открыть страницу с кодом
</a>
</p>
<p style="margin-top:8px; color:#666; font-size:12px;">
Ссылка действует ограниченное время; на странице можно скопировать код и прочитать инструкцию.
</p>
@endif

<p style="margin-top:20px;">
Если возникнут вопросы:<br>
<a href="mailto:{{$support_email}}" style="color:#1a73e8;">
{{$support_email}}
</a>
</p>

<p style="margin-top:20px; color:#666; font-size:12px;">
Сохраните этот код до момента использования.
</p>

</div>

</td>
</tr>
</table>
