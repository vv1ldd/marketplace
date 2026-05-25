@extends('layouts.app')

@section('title', 'Ваш заказ готов')

@php
    $finishCode = filled(data_get($standardized, 'credentials.code'))
        ? (string) data_get($standardized, 'credentials.code')
        : (string) ($order_item->original_code ?? '');
    $hasRichStandardized = $standardized !== null;
    $activationUrl = data_get($standardized, 'redemption.activation_url');
    $instructions = data_get($standardized, 'redemption.instructions');
    $awaitingWildflowPurchase = $order_item->purchase_status === 'pending' && ! filled($order_item->original_code);
    $pollUrl = $redeemFinishPollUrl ?? ($awaitingWildflowPurchase ? route('redeem.finish-status') : null);
    $redeemPurchaseFailed = $order_item->purchase_status === 'failed';
    $redeemPurchaseManualPending = $order_item->purchase_status === 'manual' && ! filled($order_item->original_code);
    $isDevAsyncRedeemDemoOrder = (bool) ($order_item->order?->isDevAsyncRedeemDemo());
    $supportReference = $order_item->supportReference();
@endphp

@section('content')
@if($awaitingWildflowPurchase && $pollUrl)
    <div class="w-full max-w-2xl mx-auto px-4 py-6 sm:py-10" data-redeem-awaiting data-poll-url="{{ $pollUrl }}">
        <div class="overflow-hidden rounded-3xl border border-zinc-200/90 bg-white/95 p-10 text-center shadow-xl shadow-zinc-900/5 backdrop-blur-xl redeem-dark:border-zinc-700/50 redeem-dark:bg-zinc-900/50 redeem-dark:shadow-2xl">
            <div class="mb-6 inline-flex h-14 w-14 items-center justify-center rounded-full border-2 border-blue-200 border-t-blue-600 animate-spin redeem-dark:border-blue-500/30 redeem-dark:border-t-blue-500" aria-hidden="true"></div>
            <h2 class="text-2xl font-extrabold tracking-tight text-zinc-900 redeem-dark:text-white">Получаем код у провайдера</h2>
            <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-zinc-600 redeem-dark:text-zinc-400" data-awaiting-sub>
                Обычно это занимает несколько секунд. Страница обновится сама, как только код будет готов.
                На вашу почту также уйдёт письмо со ссылкой на это окно и самим кодом.
            </p>
            @if($hasRichStandardized)
                <div class="mt-8 flex items-center justify-center gap-4 text-left max-w-md mx-auto">
                    @if(data_get($standardized, 'assets.logo_url'))
                        <div class="w-14 h-14 rounded-xl bg-white p-1.5 shrink-0 flex items-center justify-center">
                            <img src="{{ $standardized['assets']['logo_url'] }}" alt="" class="max-w-full max-h-full object-contain">
                        </div>
                    @endif
                    <div>
                        <p class="font-semibold text-zinc-900 redeem-dark:text-white">{{ data_get($standardized, 'product.title', 'Ваучер') }}</p>
                        <p class="mt-1 font-mono text-xs text-zinc-500">{{ $order_item->sku }}</p>
                    </div>
                </div>
            @endif
            <p class="mt-10 font-mono text-xs uppercase text-zinc-500 redeem-dark:text-zinc-600">Код поддержки: {{ $supportReference }}</p>
            @if(app()->environment('local') && config('queue.default') === 'database')
                <p class="text-xs text-amber-500/90 mt-6 max-w-md mx-auto leading-relaxed">
                    <strong>Local:</strong> при <code class="text-amber-400/90">QUEUE_CONNECTION=database</code> фоновый job не выполнится сам — запустите в терминале
                    <code class="text-amber-400/90">php artisan queue:work</code>
                    (или поставьте в <code class="text-amber-400/90">.env</code> <code class="text-amber-400/90">QUEUE_CONNECTION=sync</code> для разработки).
                </p>
            @endif
        </div>
    </div>
    <script>
    (function () {
        var root = document.querySelector('[data-redeem-awaiting]');
        if (!root) return;
        var url = root.getAttribute('data-poll-url');
        if (!url) return;
        var n = 0, max = 180;
        function tick() {
            n++;
            if (n > max) {
                clearInterval(t);
                var sub = root.querySelector('[data-awaiting-sub]');
                if (sub) sub.textContent = 'Выдача заняла дольше обычного — запрос всё ещё в обработке. Проверьте почту или обновите страницу чуть позже.';
                return;
            }
            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.has_code || d.purchase_status === 'failed' || d.purchase_status === 'manual' || d.purchase_status === 'none') {
                        clearInterval(t);
                        window.location.reload();
                    }
                })
                .catch(function () {});
        }
        tick();
        var t = setInterval(tick, 800);
    })();
    </script>
@elseif($redeemPurchaseFailed)
    <x-redeem.panel headline="Сбой выдачи" icon="alert-triangle" bodyClass="p-8 sm:p-10 text-center space-y-5">
        <x-slot name="lead">
            <span class="text-red-500 font-bold">Что-то пошло не так.</span> Произошла техническая ошибка при получении кода у провайдера. 
        </x-slot>
        
        <div class="my-4 p-4 bg-red-50 border border-red-200 rounded-xl text-left redeem-dark:bg-red-900/20 redeem-dark:border-red-900/50">
            <p class="text-xs font-bold text-red-600 uppercase tracking-wider mb-1 redeem-dark:text-red-400">Техническая информация:</p>
            <p class="font-mono text-sm text-red-800 redeem-dark:text-red-200 break-all">
                {{ $order_item->purchase_error ?: 'Неизвестная ошибка провайдера или таймаут соединения.' }}
            </p>
        </div>

        <x-slot name="sublead">
            Заявка зафиксирована в системе, наши специалисты уже разбираются. Проверьте почту чуть позже.
        </x-slot>
        
        <p class="text-xs text-zinc-600 font-mono uppercase pt-2">Код поддержки: {{ $supportReference }}</p>
        <a href="{{ route('redeem.code') }}"
            class="mx-auto inline-flex w-full min-w-0 items-center justify-center rounded-xl border border-zinc-300 bg-white px-6 py-3 text-sm font-semibold text-zinc-800 shadow-md transition-colors hover:bg-zinc-50 sm:w-auto sm:min-w-[240px] redeem-dark:border-zinc-600 redeem-dark:bg-zinc-800 redeem-dark:text-white redeem-dark:shadow-lg redeem-dark:shadow-black/20 redeem-dark:hover:bg-zinc-700">
            Вернуться на главную
        </a>
    </x-redeem.panel>
@elseif($redeemPurchaseManualPending)
    <x-redeem.panel headline="Запрос обрабатывается" icon="clock" bodyClass="p-8 sm:p-10 text-center space-y-5">
        <x-slot name="lead">
            Для этой позиции выдача займёт чуть больше времени — заявка в работе у операторов. Код пришлём на вашу почту, как только будет готов.
        </x-slot>
        <x-slot name="sublead">
            При необходимости мы свяжемся с вами по указанному email.
        </x-slot>
        <p class="text-xs text-zinc-600 font-mono uppercase pt-2">Код поддержки: {{ $supportReference }}</p>
        <a href="{{ route('redeem.code') }}"
            class="inline-flex items-center justify-center w-full sm:w-auto sm:min-w-[200px] mx-auto bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-xl text-sm font-semibold transition-colors shadow-lg shadow-blue-900/20">
            На главную
        </a>
    </x-redeem.panel>
@elseif($finishCode === '')
    <x-redeem.panel headline="Заказ принят" icon="clock" bodyClass="p-8 sm:p-10 text-center space-y-6">
        <x-slot name="lead">
            Мы обрабатываем ваш заказ. Код будет отправлен на вашу почту, как только он будет готов.
        </x-slot>
        <a href="{{ route('redeem.code') }}"
            class="inline-flex items-center justify-center w-full sm:w-auto sm:min-w-[200px] mx-auto bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-xl text-sm font-semibold transition-colors shadow-lg shadow-blue-900/20">
            На главную
        </a>
    </x-redeem.panel>
@elseif(! $hasRichStandardized)
    {{-- Код уже есть (автозакуп / ручная выдача), но позиции нет в каталоге Wildflow — всё равно показываем код сразу --}}
    <div class="w-full max-w-2xl mx-auto px-4 py-6 sm:py-10 animate-in fade-in duration-700" data-redeem-finish-card>
        <div class="overflow-hidden rounded-3xl border border-zinc-200/90 bg-white/95 shadow-xl shadow-zinc-900/5 backdrop-blur-xl redeem-dark:border-zinc-700/50 redeem-dark:bg-zinc-900/50 redeem-dark:shadow-2xl">
            <div class="border-b border-zinc-200/80 bg-gradient-to-r from-blue-100/90 to-indigo-100/70 p-8 text-center redeem-dark:border-zinc-700/30 redeem-dark:from-blue-600/20 redeem-dark:to-indigo-600/20">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-600 mb-4 shadow-lg shadow-blue-600/30 animate-bounce">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-extrabold tracking-tight text-zinc-900 redeem-dark:text-white">{{ $isDevAsyncRedeemDemoOrder ? 'Экран готов (тест)' : 'Ваш код готов!' }}</h2>
                <p class="mt-2 text-zinc-600 redeem-dark:text-zinc-400">
                    @if($isDevAsyncRedeemDemoOrder)
                        Ниже — <span class="text-amber-800 redeem-dark:text-amber-200/95">условный тестовый код</span>, не код провайдера. В бою здесь будет настоящий секрет; если закуп сразу не пройдёт — покажем, что выдача заняла больше времени, без «фейкового» кода.
                    @else
                        Сохраните код — он также продублирован на email
                    @endif
                </p>
            </div>
            <div class="p-8">
                <p class="text-sm text-zinc-500 mb-6 font-mono break-all">SKU: {{ $order_item->sku }}</p>
                <div class="space-y-4">
                    <div class="relative group">
                        <label class="block text-xs font-bold text-zinc-500 uppercase tracking-widest mb-2 ml-1">{{ $isDevAsyncRedeemDemoOrder ? 'Код (только для проверки UI)' : 'Секретный код' }}</label>
                        <div class="flex items-center gap-3">
                            <div data-code-display class="flex-1 rounded-2xl border-2 border-zinc-200 bg-zinc-50 p-5 text-center transition-all duration-300 group-hover:border-blue-400 group-hover:bg-white redeem-dark:border-zinc-700/50 redeem-dark:bg-zinc-950/50 redeem-dark:group-hover:border-blue-500/50 redeem-dark:group-hover:bg-zinc-950">
                                <span class="font-mono text-3xl font-black tracking-[0.15em] break-all text-zinc-900 selection:bg-blue-500 selection:text-white redeem-dark:text-white">{{ $finishCode }}</span>
                            </div>
                            <button type="button" onclick="copyRedeemFinishCode({{ json_encode($finishCode) }}, this)"
                                    class="rounded-2xl border border-zinc-200 bg-white p-5 text-zinc-500 shadow-md transition-all hover:border-zinc-300 hover:bg-zinc-50 hover:text-zinc-800 active:scale-95 redeem-dark:border-zinc-700 redeem-dark:bg-zinc-800 redeem-dark:text-zinc-400 redeem-dark:shadow-lg redeem-dark:hover:border-zinc-600 redeem-dark:hover:bg-zinc-700 redeem-dark:hover:text-white">
                                <svg class="w-6 h-6 copy-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path>
                                </svg>
                                <svg class="w-6 h-6 hidden check-icon text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="mt-10">
                    <a href="{{ route('redeem.code') }}" class="flex w-full items-center justify-center rounded-2xl border border-zinc-200 bg-zinc-100/80 py-4 font-bold text-zinc-700 transition-all hover:bg-zinc-200 redeem-dark:border-zinc-700 redeem-dark:bg-zinc-800/50 redeem-dark:text-zinc-400 redeem-dark:hover:bg-zinc-800">
                        Активировать другой ваучер
                    </a>
                </div>
            </div>
            <div class="border-t border-zinc-200/90 bg-zinc-50/90 p-6 text-center redeem-dark:border-zinc-800/50 redeem-dark:bg-zinc-950/50">
                <p class="text-xs text-zinc-500">Возникли проблемы? Укажите код поддержки заказа:</p>
                <p class="mt-1 font-mono text-xs uppercase text-zinc-600 redeem-dark:text-zinc-600">{{ $supportReference }}</p>
            </div>
        </div>
    </div>
@else
<div class="w-full max-w-2xl mx-auto px-4 py-6 sm:py-10 animate-in fade-in duration-700" data-redeem-finish-card>
    <!-- Main Card -->
    <div class="overflow-hidden rounded-3xl border border-zinc-200/90 bg-white/95 shadow-xl shadow-zinc-900/5 backdrop-blur-xl redeem-dark:border-zinc-700/50 redeem-dark:bg-zinc-900/50 redeem-dark:shadow-2xl">

        <!-- Success Header -->
        <div class="border-b border-zinc-200/80 bg-gradient-to-r from-blue-100/90 to-indigo-100/70 p-8 text-center redeem-dark:border-zinc-700/30 redeem-dark:from-blue-600/20 redeem-dark:to-indigo-600/20">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-600 mb-4 shadow-lg shadow-blue-600/30 animate-bounce">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h2 class="text-3xl font-extrabold tracking-tight text-zinc-900 redeem-dark:text-white">{{ $isDevAsyncRedeemDemoOrder ? 'Экран готов (тест)' : 'Ваш код готов!' }}</h2>
            <p class="mt-2 text-zinc-600 redeem-dark:text-zinc-400">
                @if($isDevAsyncRedeemDemoOrder)
                    Ниже — <span class="text-amber-800 redeem-dark:text-amber-200/95">условный тестовый код</span> (цепочка как в бою: ожидание → выдача). В продакшене — настоящий код от провайдера; при сбое — сообщение «нужно больше времени», без подстановки кода.
                @else
                    Заказ успешно обработан — код ниже и продублирован на email
                @endif
            </p>
        </div>

        <!-- Product Info Section -->
        <div class="p-8">
            <div class="flex items-start gap-6 mb-8">
                @if($standardized['assets']['logo_url'] ?? null)
                    <div class="w-20 h-20 rounded-2xl bg-white p-2 shrink-0 shadow-inner flex items-center justify-center overflow-hidden">
                        <img src="{{ $standardized['assets']['logo_url'] }}" alt="Brand Logo" class="max-w-full max-h-full object-contain">
                    </div>
                @else
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl border border-zinc-200 bg-zinc-100 text-zinc-500 redeem-dark:border-transparent redeem-dark:bg-zinc-800 redeem-dark:text-zinc-600">
                        <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M21 16.5c0 .38-.21.71-.53.88l-7.9 4.44c-.16.12-.36.18-.57.18s-.41-.06-.57-.18l-7.9-4.44A.991.991 0 013 16.5v-9c0-.38.21-.71.53-.88l7.9-4.44c.16-.12.36-.18.57-.18s.41.06.57.18l7.9 4.44c.32.17.53.5.53.88v9z"></path></svg>
                    </div>
                @endif
                
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-bold uppercase tracking-widest text-blue-500 bg-blue-500/10 px-2 py-0.5 rounded">
                            {{ $standardized['product']['brand'] ?? 'Product' }}
                        </span>
                        <span class="text-xs font-bold uppercase tracking-widest text-zinc-500 bg-zinc-500/10 px-2 py-0.5 rounded">
                            {{ $standardized['geography']['region_code'] ?? 'Global' }} {{ $standardized['geography']['flag'] ?? '' }}
                        </span>
                    </div>
                    <h3 class="text-xl font-bold leading-tight text-zinc-900 redeem-dark:text-white">
                        {{ $standardized['product']['title'] }}
                    </h3>
                    <p class="text-sm text-zinc-500 mt-1">SKU: <span class="font-mono">{{ $standardized['product']['sku'] }}</span></p>
                </div>
            </div>

            <!-- CODE DISPLAY SECTION -->
            <div class="space-y-4">
                <div class="relative group">
                    <label class="block text-xs font-bold text-zinc-500 uppercase tracking-widest mb-2 ml-1">{{ $isDevAsyncRedeemDemoOrder ? 'Код (только для проверки UI)' : 'Секретный код' }}</label>
                    <div class="flex items-center gap-3">
                        <div data-code-display class="flex-1 rounded-2xl border-2 border-zinc-200 bg-zinc-50 p-5 text-center transition-all duration-300 group-hover:border-blue-400 group-hover:bg-white redeem-dark:border-zinc-700/50 redeem-dark:bg-zinc-950/50 redeem-dark:group-hover:border-blue-500/50 redeem-dark:group-hover:bg-zinc-950">
                            <span class="font-mono text-3xl font-black tracking-[0.2em] break-all text-zinc-900 selection:bg-blue-500 selection:text-white redeem-dark:text-white">
                                {{ $finishCode !== '' ? $finishCode : '---' }}
                            </span>
                        </div>
                        <button type="button" onclick="copyRedeemFinishCode({{ json_encode($finishCode) }}, this)" 
                                class="rounded-2xl border border-zinc-200 bg-white p-5 text-zinc-500 shadow-md transition-all hover:border-zinc-300 hover:bg-zinc-50 hover:text-zinc-800 active:scale-95 group-hover:shadow-blue-500/10 redeem-dark:border-zinc-700 redeem-dark:bg-zinc-800 redeem-dark:text-zinc-400 redeem-dark:shadow-lg redeem-dark:hover:border-zinc-600 redeem-dark:hover:bg-zinc-700 redeem-dark:hover:text-white">
                            <svg class="w-6 h-6 copy-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path>
                            </svg>
                            <svg class="w-6 h-6 hidden check-icon text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- SECONDARY CREDENTIALS -->
                @if(($standardized['credentials']['pin'] ?? null) || ($standardized['credentials']['serial'] ?? null) || ($standardized['credentials']['valid_until'] ?? null))
                <div class="grid grid-cols-2 gap-4 mt-6">
                    @if($standardized['credentials']['pin'] ?? null)
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 redeem-dark:border-zinc-700/30 redeem-dark:bg-zinc-800/30">
                        <span class="block text-[10px] font-bold uppercase tracking-tighter text-zinc-500">PIN-код</span>
                        <span class="font-mono text-sm text-zinc-900 redeem-dark:text-white">{{ $standardized['credentials']['pin'] }}</span>
                    </div>
                    @endif
                    @if($standardized['credentials']['serial'] ?? null)
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 redeem-dark:border-zinc-700/30 redeem-dark:bg-zinc-800/30">
                        <span class="block text-[10px] font-bold uppercase tracking-tighter text-zinc-500">Серийный номер</span>
                        <span class="font-mono text-sm text-zinc-900 redeem-dark:text-white">{{ $standardized['credentials']['serial'] }}</span>
                    </div>
                    @endif
                </div>
                @endif
            </div>

            <!-- ACTION BUTTONS: сначала официальный сервис бренда, ниже — другой ваучер -->
            <div class="mt-10 flex flex-col gap-3">
                @if($activationUrl)
                <a href="{{ $activationUrl }}" target="_blank" rel="noopener noreferrer"
                   class="group flex w-full items-center justify-center gap-3 rounded-2xl bg-blue-600 px-5 py-4 text-sm font-black uppercase tracking-wide text-white shadow-lg shadow-blue-600/25 transition-all hover:bg-blue-500 active:scale-[0.98] redeem-dark:bg-blue-600 redeem-dark:hover:bg-blue-500">
                    <span>На сайт сервиса — активация</span>
                    <svg class="h-5 w-5 shrink-0 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                    </svg>
                </a>
                @endif

                <a href="{{ route('redeem.code') }}" class="flex w-full items-center justify-center rounded-2xl border border-zinc-200 bg-zinc-100/80 py-4 text-sm font-bold text-zinc-700 transition-all hover:bg-zinc-200 redeem-dark:border-zinc-700 redeem-dark:bg-zinc-800/50 redeem-dark:text-zinc-400 redeem-dark:hover:bg-zinc-800">
                    Активировать другой ваучер
                </a>
            </div>

            <!-- INSTRUCTIONS SECTION -->
            @if(filled($instructions))
            <div class="mt-12 border-t border-zinc-200 pt-8 redeem-dark:border-zinc-800">
                <h4 class="mb-6 flex items-center gap-2 text-sm font-black uppercase tracking-widest text-zinc-900 redeem-dark:text-white">
                    <svg class="h-4 w-4 text-blue-600 redeem-dark:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Как активировать этот код
                </h4>
                <div class="prose prose-sm max-w-none text-zinc-700 redeem-dark:prose-invert">
                    <div class="mb-6 rounded-r-xl border-l-4 border-blue-500 bg-blue-50/80 p-4 redeem-dark:border-blue-600 redeem-dark:bg-blue-600/5">
                        <p class="m-0 whitespace-pre-line text-sm leading-relaxed text-zinc-700 redeem-dark:text-zinc-300">
                            {{ $instructions }}
                        </p>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Footer Help -->
        <div class="border-t border-zinc-200/90 bg-zinc-50/90 p-6 text-center redeem-dark:border-zinc-800/50 redeem-dark:bg-zinc-950/50">
            <p class="text-xs text-zinc-500">Возникли проблемы? Напишите в нашу службу поддержки, указав код поддержки заказа:</p>
            <p class="mt-1 font-mono text-xs uppercase text-zinc-600 redeem-dark:text-zinc-600">{{ $supportReference }}</p>
        </div>
    </div>
</div>
@endif

<script>
function copyRedeemFinishCode(code, btn) {
    navigator.clipboard.writeText(code).then(() => {
        const card = btn.closest('[data-redeem-finish-card]');
        const copyIcon = btn.querySelector('.copy-icon');
        const checkIcon = btn.querySelector('.check-icon');
        const codeDisplay = card ? card.querySelector('[data-code-display]') : null;
        
        copyIcon?.classList.add('hidden');
        checkIcon?.classList.remove('hidden');
        codeDisplay?.classList.add('border-green-500/50', 'bg-green-500/5');
        
        setTimeout(() => {
            copyIcon?.classList.remove('hidden');
            checkIcon?.classList.add('hidden');
            codeDisplay?.classList.remove('border-green-500/50', 'bg-green-500/5');
        }, 2000);
    });
}
</script>

<style>
@keyframes fade-in {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-in {
    animation: fade-in 0.5s ease-out forwards;
}
</style>
@endsection
