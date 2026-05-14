@extends('layouts.app')

@section('title', 'Ваш заказ почти готов, остался один шаг')

@section('content')
    @php
        $order_item_uuid = session('order_item_info')['uuid'] ?? request()->query('uuid');
        $order_item = $order_item_uuid
            ? \App\Models\Order\OrderItems::where('uuid', $order_item_uuid)->with(['game', 'order.shop'])->first()
            : null;
        $product = $order_item?->game;
        $order = $order_item?->order;
        $showPlaystationRedeemAccountForm = $order_item?->showPlaystationRedeemAccountForm() ?? false;
        $redeemCollectExtendedProfile = $redeemCollectExtendedProfile
            ?? ($order_item?->redeemCollectsExtendedProfile() ?? false);

        // Показываем логотип бренда вместо карточки для маркетплейса
        $catalog = $order_item
            ? \App\Models\WildflowCatalog::findForOrderOfferSku($order_item->sku)?->loadMissing('brand')
            : null;

        $redeemVisualSrc = $catalog?->brand_logo_url;
        $redeemVisualAlt = $catalog?->brand?->name ?? '';
    @endphp

    <x-redeem.panel headline="Почти готово" icon="shield-check">
        <x-slot name="lead">
            Заполните поля и введите <span class="font-medium text-zinc-800 redeem-dark:text-zinc-300">шестизначный код</span> из письма. После
            отправки откроется страница с кодом для сервиса или короткое ожидание, пока провайдер выдаст код.
        </x-slot>

        <form class="space-y-5" method="POST" action="{{ route('redeem.activation.submit') }}">
            @csrf
            <input type="hidden" name="uuid" value="{{ $order_item_uuid }}" />

            @if($redeemVisualSrc)
                <div class="mb-6 flex justify-center">
                    <img src="{{ $redeemVisualSrc }}" alt="{{ $redeemVisualAlt }}" width="256" height="256" class="aspect-square h-64 w-64 rounded-2xl border border-zinc-200 bg-white p-8 object-contain shadow-xl ring-1 ring-zinc-200/80 redeem-dark:border-zinc-700/50 redeem-dark:bg-zinc-950 redeem-dark:shadow-2xl redeem-dark:ring-zinc-600/30">
                </div>
            @endif

            @if(session('is_frame'))
                <input hidden name="is_frame" value="1"/>
            @endif
            @if($redeemCollectExtendedProfile)
            <div class="flex sm:flex-row justify-between gap-3 flex-col">
                <div class="w-full">
                    <label class="block text-sm font-medium text-zinc-700 mb-2 redeem-dark:text-zinc-300" for="first_name">Имя<span
                            class="text-red-500 redeem-dark:text-red-400">*</span></label>
                    <input
                        id="first_name"
                        type="text"
                        name="first_name"
                        placeholder="Ваше имя"
                        minlength="2"
                        maxlength="100"
                        autocomplete="first_name"
                        value="{{$client_info['firstName'] ?? old('first_name')}}"
                        @if($redeemCollectExtendedProfile) autofocus @endif
                        required
                        tabindex="1"
                        class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-zinc-900 placeholder-zinc-400 shadow-inner focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 redeem-dark:border-zinc-600/80 redeem-dark:bg-zinc-800/80 redeem-dark:text-white redeem-dark:placeholder-zinc-500 redeem-dark:shadow-none redeem-dark:focus:border-blue-500/50"
                    />
                    @error('first_name')
                    <p class="text-sm text-red-600 redeem-dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div class="w-full">
                    <label class="block text-sm font-medium text-zinc-700 mb-2 redeem-dark:text-zinc-300" for="last_name">Фамилия<span
                            class="text-red-500 redeem-dark:text-red-400">*</span></label>
                    <input
                        id="last_name"
                        type="text"
                        minlength="2"
                        maxlength="100"
                        name="last_name"
                        placeholder="Ваша фамилия"
                        autocomplete="last_name"
                        value="{{ $client_info['lastName'] ?? old('last_name') }}"
                        required
                        tabindex="2"
                        class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-zinc-900 placeholder-zinc-400 shadow-inner focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 redeem-dark:border-zinc-600/80 redeem-dark:bg-zinc-800/80 redeem-dark:text-white redeem-dark:placeholder-zinc-500 redeem-dark:shadow-none redeem-dark:focus:border-blue-500/50"
                    />
                    @error('last_name')
                    <p class="text-sm text-red-600 redeem-dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            @endif
            @if($showPlaystationRedeemAccountForm)
                <div>
                    <div id="checkbox-group" class="mx-auto space-y-4 rounded-xl border border-zinc-200 bg-zinc-50/90 p-4 redeem-dark:border-zinc-700/50 redeem-dark:bg-zinc-950/60">
                        <div>
                            <label class="flex items-center gap-3 cursor-pointer select-none mb-1">
                                <input type="checkbox" name="option[0][check]" @if(!session('user_exists')) disabled
                                       @endif
                                       @checked(old('option.0.check')) class="sr-only peer"/>
                                <div
                                    class="flex h-6 min-h-6 w-6 min-w-6 items-center justify-center rounded-md border-2 border-zinc-300 bg-white transition-all duration-300 peer-checked:border-blue-500 peer-checked:bg-blue-600 peer-checked:shadow-[0_0_8px_3px_rgba(59,130,246,0.45)] redeem-dark:border-zinc-600 redeem-dark:bg-zinc-800 redeem-dark:peer-checked:border-blue-400 redeem-dark:peer-checked:shadow-[0_0_8px_3px_rgba(59,130,246,0.7)]"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        class="w-4 h-4 text-white opacity-0 scale-75 peer-checked:opacity-100 peer-checked:scale-100
                   transition-all duration-300 ease-in-out"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <span class="text-zinc-800 transition-colors hover:text-zinc-950 redeem-dark:text-zinc-300 redeem-dark:hover:text-white @if(!session('user_exist')) line-through @endif">Оформить покупку на имеющийся PlayStation Network ID</span>
                            </label>
                            <p class="text-sm text-zinc-600 redeem-dark:text-zinc-400">
                                @if(session('user_exist'))
                                    Активируйте пункт если желаете оформить покупку на имеющийся аккаунт PlayStation Network ID.
                                @else
                                    Активация данным способом доступна исключительно для игровых аккаунтов, созданных нашим сервисом.
                                @endif
                            </p>
                            @error('option.0.check')
                            <p class="text-sm text-red-600 redeem-dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="flex items-center gap-3 cursor-pointer select-none mb-1">
                                <input type="checkbox" name="option[1][check]"
                                       @checked(old('option.1.check') || !session('user_exists')) class="sr-only peer"/>
                                <div
                                    class="flex h-6 min-h-6 w-6 min-w-6 items-center justify-center rounded-md border-2 border-zinc-300 bg-white transition-all duration-300 peer-checked:border-blue-500 peer-checked:bg-blue-600 peer-checked:shadow-[0_0_8px_3px_rgba(59,130,246,0.45)] redeem-dark:border-zinc-600 redeem-dark:bg-zinc-800 redeem-dark:peer-checked:border-blue-400 redeem-dark:peer-checked:shadow-[0_0_8px_3px_rgba(59,130,246,0.7)]"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        class="w-4 h-4 text-white opacity-0 scale-75 peer-checked:opacity-100 peer-checked:scale-100
               transition-all duration-300 ease-in-out"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <span
                                    class="text-zinc-800 transition-colors hover:text-zinc-950 redeem-dark:text-zinc-300 redeem-dark:hover:text-white">Сгенерировать аккаунт</span>
                            </label>
                            <p class="text-sm text-zinc-600 redeem-dark:text-zinc-400">Активируйте пункт если желаете создать новый аккаунт
                                PlayStation
                                Network ID. Мы создаем исключительно персональный аккаунт, на
                                основе вашего имени, фамилии и даты рождения.</p>
                            @error('option[1][check]')
                            <p class="text-sm text-red-600 redeem-dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                @if(session('user_exists'))
                    <div id="option[0][check]" class="@if(!old('option.0.check')) hidden @endif space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 mb-2 redeem-dark:text-zinc-300" for="option[0][ps_network_id]">Идентификатор
                                PlayStation Network ID</label>
                            <input
                                name="option[0][ps_network_id]"
                                id="option[0][ps_network_id]"
                                @if(!old('option.0.check')) disabled @endif
                                type="email"
                                value="{{ old('option.0.ps_network_id') }}"
                                placeholder="Введите ваш PSN ID (email)"
                                class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-zinc-900 placeholder-zinc-400 shadow-inner focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 redeem-dark:border-zinc-600/80 redeem-dark:bg-zinc-800/80 redeem-dark:text-white redeem-dark:placeholder-zinc-500 redeem-dark:shadow-none redeem-dark:focus:border-blue-500/50"
                            />
                            @error('option.0.ps_network_id')
                            <p class="text-sm text-red-600 redeem-dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 mb-2 redeem-dark:text-zinc-300" for="option[0][ps_network_password]">Пароль
                                PlayStation Network ID</label>
                            <input
                                name="option[0][ps_network_password]"
                                id="option[0][ps_network_password]"
                                type="password"
                                @if(!old('option.0.check')) disabled @endif
                                value="{{ old('option.0.ps_network_password') }}"
                                placeholder="Введите ваш пароль"
                                class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-zinc-900 placeholder-zinc-400 shadow-inner focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 redeem-dark:border-zinc-600/80 redeem-dark:bg-zinc-800/80 redeem-dark:text-white redeem-dark:placeholder-zinc-500 redeem-dark:shadow-none redeem-dark:focus:border-blue-500/50"
                            />
                            @error('option.0.ps_network_password')
                            <p class="text-sm text-red-600 redeem-dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 mb-2 redeem-dark:text-zinc-300" for="option[0][ps_network_password]">Резервный
                                код
                                2fа<span
                                    class="text-red-500 redeem-dark:text-red-400">*</span></label>
                            <input
                                name="option[0][ps_2fa_code]"
                                type="text"
                                id="option[0][ps_2fa_code]"
                                @if(!old('option.0.check')) disabled @endif
                                value="{{ old('option.0.ps_2fa_code') }}"
                                placeholder="Введите резервный код 2fа"
                                class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-zinc-900 placeholder-zinc-400 shadow-inner focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 redeem-dark:border-zinc-600/80 redeem-dark:bg-zinc-800/80 redeem-dark:text-white redeem-dark:placeholder-zinc-500 redeem-dark:shadow-none redeem-dark:focus:border-blue-500/50"
                            />
                            @error('option.0.ps_2fa_code')
                            <p class="text-sm text-red-600 redeem-dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endif
                <div id="option[1][check]"
                     class="@if(!old('option.1.check') && session('user_exists')) hidden @endif space-y-5">
                    <div>
                        <div class="mb-1">
                            <label class="block text-sm font-medium text-zinc-700 mb-2 redeem-dark:text-zinc-300" for="option[0][ps_network_id]">Дата
                                рождения</label>
                            <p class="text-sm text-zinc-600 redeem-dark:text-zinc-400">Является ответом на секретный вопрос восстановления
                                аккаунта PlayStation Network. Используйте свою реальную дату
                                рождения.</p>
                        </div>

                        <input
                            name="option[1][ps_birthday]"
                            id="option[1][ps_birthday]"
                            type="date"
                            value="{{ old('option.1.ps_birthday') }}"
                            max="{{ date('Y-m-d') }}"
                            @if(!old('option.1.check')) disabled @endif
                            min="1950-01-01"
                            placeholder="дд.мм.гггг"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-zinc-900 placeholder-zinc-400 shadow-inner focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 redeem-dark:border-zinc-600/80 redeem-dark:bg-zinc-800/80 redeem-dark:text-white redeem-dark:placeholder-zinc-500 redeem-dark:shadow-none redeem-dark:focus:border-blue-500/50"
                        />
                        @error('option.1.ps_birthday')
                        <p class="text-sm text-red-600 redeem-dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <p class="rounded-xl border border-zinc-200 bg-zinc-50/80 px-4 py-3 text-sm text-zinc-600 redeem-dark:border-zinc-700/50 redeem-dark:bg-zinc-950/40 redeem-dark:text-zinc-400">
                        Сгенерируем для вас защищенный аккаунт PlayStation Network и отправим идентификационные данные
                        на ваш указанный Email
                    </p>
                </div>
            @endif

            <div class="flex flex-col gap-3">
                <div class="flex sm:flex-row justify-between gap-3 flex-col">
                    @if($redeemCollectExtendedProfile)
                    <div class="w-full">
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 mb-2 redeem-dark:text-zinc-300" for="phone">Телефон<span
                                    class="text-red-500 redeem-dark:text-red-400">*</span></label>
                            <input
                                id="phone"
                                type="tel"
                                placeholder="+7 (___) ___-__-__"
                                pattern="^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$"
                                autocomplete="tel"
                                name="phone"
                                value="{{ old('phone') }}"
                                tabindex="3"
                                required
                                class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-zinc-900 placeholder-zinc-400 shadow-inner focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 redeem-dark:border-zinc-600/80 redeem-dark:bg-zinc-800/80 redeem-dark:text-white redeem-dark:placeholder-zinc-500 redeem-dark:shadow-none redeem-dark:focus:border-blue-500/50"
                            />
                            @error('phone')
                            <p class="text-sm text-red-600 redeem-dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    @endif
                    <div class="w-full">
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 mb-2 redeem-dark:text-zinc-300" for="email">Email<span
                                    class="text-red-500 redeem-dark:text-red-400">*</span></label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                placeholder="you@example.com"
                                autocomplete="email"
                                tabindex="4"
                                value="{{ $client_email ?? old('email') }}"
                                required
                                @if(! $redeemCollectExtendedProfile) autofocus @endif
                                @if(isset($client_email)) readonly @endif
                                class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-zinc-900 placeholder-zinc-400 shadow-inner focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 redeem-dark:border-zinc-600/80 redeem-dark:bg-zinc-800/80 redeem-dark:text-white redeem-dark:placeholder-zinc-500 redeem-dark:shadow-none redeem-dark:focus:border-blue-500/50 @if(isset($client_email)) opacity-60 cursor-not-allowed @endif"
                            />
                            @error('email')
                            <p class="text-sm text-red-600 redeem-dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
                @if(! $showPlaystationRedeemAccountForm)
                    <p class="rounded-xl border border-zinc-200 bg-zinc-50/80 px-4 py-3 text-sm text-zinc-600 redeem-dark:border-zinc-700/50 redeem-dark:bg-zinc-950/40 redeem-dark:text-zinc-400">
                        Отправим подарочную карту на ваш указанный Email
                    </p>
                @endif

                @if($order && $order->chat_id)
                <div class="w-full rounded-xl border border-blue-200 bg-blue-50/80 p-4 redeem-dark:border-blue-500/25 redeem-dark:bg-blue-600/10">
                    <label class="flex items-start gap-3 cursor-pointer group">
                        <div class="relative flex items-center">
                            <input type="checkbox" name="deliver_to_chat" checked class="sr-only peer">
                            <div class="flex h-6 w-6 items-center justify-center rounded-md border-2 border-blue-300 bg-white transition-all peer-checked:border-blue-500 peer-checked:bg-blue-600 redeem-dark:border-blue-600/50 redeem-dark:bg-zinc-800 redeem-dark:peer-checked:border-blue-400">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4 text-white opacity-0 peer-checked:opacity-100 transition-opacity" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        </div>
                        <div class="flex flex-col">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-zinc-900 transition-colors group-hover:text-blue-700 redeem-dark:text-white redeem-dark:group-hover:text-blue-300">Доставить код в чат Яндекс.Маркета</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-green-500/20 text-green-400 font-bold uppercase tracking-wider">Бесплатно</span>
                            </div>
                            <p class="mt-1 text-xs text-zinc-600 redeem-dark:text-zinc-400">
                                Мы также продублируем ваш активированный код прямо в диалог с продавцом на Маркете для быстрого доступа.
                            </p>
                        </div>
                    </label>
                </div>
                @endif
                <div class="w-full">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 mb-2 redeem-dark:text-zinc-300" for="verification_code">Код подтверждения<span
                                class="text-red-500 redeem-dark:text-red-400">*</span></label>
                        <div class="flex gap-2">
                             <input
                                id="verification_code"
                                type="text"
                                name="verification_code"
                                placeholder="123456"
                                autocomplete="off"
                                tabindex="6"
                                value="{{ old('verification_code') }}"
                                required
                                class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-zinc-900 placeholder-zinc-400 shadow-inner focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 redeem-dark:border-zinc-600/80 redeem-dark:bg-zinc-800/80 redeem-dark:text-white redeem-dark:placeholder-zinc-500 redeem-dark:shadow-none redeem-dark:focus:border-blue-500/50"
                            />
                        </div>
                       
                        <div class="flex flex-col gap-1 mt-2">
                            <div class="flex justify-between items-center">
                                <p class="text-xs text-zinc-600 redeem-dark:text-zinc-400">
                                    Мы отправили код подтверждения на <b>{{ session('client_email') }}</b>.
                                </p>
                                <a href="{{ route('redeem.email') }}" class="text-xs text-zinc-500 transition-colors hover:text-zinc-700 redeem-dark:hover:text-zinc-400">
                                    Изменить email
                                </a>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-zinc-600 redeem-dark:text-zinc-400">Не получили код?</span>
                                <button type="submit" form="resend-form" class="cursor-pointer border-0 bg-transparent p-0 text-xs text-blue-700 transition-colors hover:text-blue-900 redeem-dark:text-blue-400 redeem-dark:hover:text-blue-300">
                                    Отправить еще раз
                                </button>
                            </div>
                        </div>
                        
                        @error('verification_code')
                        <p class="mt-1 text-sm text-red-600 redeem-dark:text-red-400">{{ $message }}</p>
                        @enderror

                        @if(session('success'))
                            <p class="mt-1 text-sm text-green-700 redeem-dark:text-green-400">{{ session('success') }}</p>
                        @endif
                    </div>
                </div>
            </div>
            <button
                type="submit"
                class="w-full cursor-pointer rounded-xl bg-blue-600 px-4 py-3 font-semibold text-white shadow-lg shadow-blue-900/15 transition-colors hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-white redeem-dark:shadow-blue-900/20 redeem-dark:focus:ring-offset-zinc-900"
                tabindex="5"
            >
                Отправить
            </button>
        </form>
        <form id="resend-form" method="POST" action="{{ route('redeem.resend') }}" class="hidden">
            @csrf
        </form>
    </x-redeem.panel>

@endsection

@section('scripts')
    <script>
        const group = document.getElementById('checkbox-group');

        if (group) {
            const checkboxes = group.querySelectorAll('input[type="checkbox"]');

            const option_0 = document.getElementById('option[0][check]');
            const option_1 = document.getElementById('option[1][check]');

            const option_0_ps_network_id = document.querySelector('[name="option[0][ps_network_id]"]');
            const option_0_ps_network_password = document.querySelector('[name="option[0][ps_network_password]"]');
            const option_0_ps_2fa_code = document.querySelector('[name="option[0][ps_2fa_code]"]');

            const option_1_ps_birthday = document.querySelector('[name="option[1][ps_birthday]"]');

            function toggleOption(num) {
                if (num === 0) {
                    option_0?.classList.remove('hidden');
                    option_1.classList.add('hidden');

                    option_0_ps_network_id?.removeAttribute('disabled');
                    option_0_ps_network_password?.removeAttribute('disabled');
                    option_0_ps_2fa_code?.removeAttribute('disabled');
                    option_1_ps_birthday.setAttribute('disabled', 'disabled');

                } else if (num === 1) {
                    option_0?.classList.add('hidden');
                    option_1.classList.remove('hidden');

                    option_0_ps_network_id?.setAttribute('disabled', 'disabled');
                    option_0_ps_network_password?.setAttribute('disabled', 'disabled');
                    option_0_ps_2fa_code?.setAttribute('disabled', 'disabled');
                    option_1_ps_birthday.removeAttribute('disabled');
                } else {
                    option_0?.classList.add('hidden');
                    option_1.classList.add('hidden');

                    option_1_ps_birthday.setAttribute('disabled', 'disabled');
                    option_0_ps_2fa_code?.setAttribute('disabled', 'disabled');
                    option_0_ps_network_id?.setAttribute('disabled', 'disabled');
                    option_0_ps_network_password?.setAttribute('disabled', 'disabled');
                }
            }

            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('click', () => {
                    if (checkbox.checked) {
                        checkboxes.forEach(cb => {
                            if (cb !== checkbox) {
                                cb.checked = false;
                                cb.dispatchEvent(new Event('change'));
                            }
                        });

                        if (checkbox.getAttribute('name') === 'option[0][check]') {
                            toggleOption(0);
                        } else {
                            toggleOption(1);
                        }

                    } else {
                        toggleOption(2);
                    }
                });
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const input = document.getElementById('phone');
            if (!input) return;

            function onlyDigits(str) {
                return str.replace(/\D/g, '');
            }

            function formatPhone(raw) {
                let digits = onlyDigits(raw);

                // Если первая цифра — 8, заменяем её на 7
                if (digits.startsWith('8')) {
                    digits = '7' + digits.slice(1);
                }

                // Если начинается с 9 — добавляем 7 в начало
                else if (digits.startsWith('9')) {
                    digits = '7' + digits;
                }

                // Если начинается с чего-то другого, но не 7, добавляем 7 (чтобы было +7)
                else if (!digits.startsWith('7')) {
                    digits = '7' + digits;
                }

                digits = digits.slice(0, 11); // максимум 11 цифр

                const national = digits.slice(1); // 10 цифр после 7
                const nLen = national.length;

                let res = '+7';
                if (nLen > 0) {
                    res += ' (' + national.slice(0, Math.min(3, nLen));
                    if (nLen >= 3) res += ')'; // закрывающая скобка только после 3 цифр
                }
                if (nLen > 3) res += ' ' + national.slice(3, Math.min(6, nLen));
                if (nLen > 6) res += '-' + national.slice(6, Math.min(8, nLen));
                if (nLen > 8) res += '-' + national.slice(8, Math.min(10, nLen));

                return res;
            }

            function onInput(e) {
                const cursor = input.selectionStart;
                const formatted = formatPhone(input.value);
                input.value = formatted;

                // При удалении — не двигаем курсор
                if (e.inputType === 'deleteContentBackward') {
                    input.setSelectionRange(cursor, cursor);
                } else {
                    input.setSelectionRange(input.value.length, input.value.length);
                }
            }

            function onPaste(e) {
                const text = (e.clipboardData || window.clipboardData).getData('text');
                if (!/\d/.test(text)) e.preventDefault();
            }

            function onFocus() {
                // ничего не добавляем, поле пустое при начале ввода
            }

            function onBlur() {
                const digits = onlyDigits(input.value);
                if (digits.length <= 3) {
                    input.value = '';
                } else {
                    input.value = formatPhone(input.value);
                }
            }

            input.addEventListener('input', onInput);
            input.addEventListener('paste', onPaste);
            input.addEventListener('focus', onFocus);
            input.addEventListener('blur', onBlur);
        });
    </script>
@endsection
