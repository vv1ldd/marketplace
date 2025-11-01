@extends('layouts.app')

@section('title', 'Form')

@section('content')
    <div class="w-full @if(!$is_frame) max-w-xl rounded-2xl @endif bg-zinc-800 border border-zinc-700 shadow-xl p-8">
        <h2 class="text-2xl font-bold text-white mb-6 text-center">Ваш заказ почти готов, остался один шаг</h2>
        <form class="space-y-5" method="POST" action="{{ route('form-send') }}">
            @csrf
            <input hidden name="uuid" value="{{ $uuid }}">
            <input hidden name="type_form_id" value="{{ $type_form_id }}">
            @if($is_frame)
                <input hidden name="is_frame" value="1" />
            @endif
            <div class="flex sm:flex-row justify-between gap-3 flex-col">
                <div class="w-full">
                    <label class="block text-sm text-zinc-300 mb-1" for="first_name">Имя<span
                            class="text-red-500">*</span></label>
                    <input
                        id="first_name"
                        type="text"
                        name="first_name"
                        placeholder="Ваше имя"
                        minlength="2"
                        maxlength="100"
                        autocomplete="first_name"
                        value="{{$client_info['firstName'] ?? old('first_name')}}"
                        autofocus
                        required
                        tabindex="1"
                        class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                    />
                    @error('first_name')
                    <p class="text-red-500 text-sm">{{ $message }}</p>
                    @enderror
                </div>
                <div class="w-full">
                    <label class="block text-sm text-zinc-300 mb-1" for="last_name">Фамилия<span
                            class="text-red-500">*</span></label>
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
                        class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                    />
                    @error('last_name')
                    <p class="text-red-500 text-sm">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            @if($type_form_id === 1)
                <div>
                    <div id="checkbox-group" class="space-y-4 mx-auto p-4 bg-zinc-900 rounded-xl">
                        <div>
                            <label class="flex items-center gap-3 cursor-pointer select-none mb-1">
                                <input type="checkbox" name="option[0][check]"
                                       @checked(old('option.0.check')) class="sr-only peer" />
                                <div
                                    class="w-6 min-w-6 h-6 min-h-6 rounded-md border-2 border-zinc-600 bg-zinc-800
                 peer-checked:bg-blue-600 peer-checked:border-blue-400
                 peer-checked:shadow-[0_0_8px_3px_rgba(59,130,246,0.7)]
                 transition-all duration-300 flex items-center justify-center"
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
                                        <path d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-zinc-300 hover:text-white transition-colors">Оформить покупку на имеющийся PlayStation Network ID</span>
                            </label>
                            <p class="text-zinc-400 text-sm">Активируйте пункт если желаете оформить покупку на
                                имеющийся
                                аккаунт PlayStation Network ID.</p>
                            @error('option.0.check')
                            <p class="text-red-500 text-sm">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="flex items-center gap-3 cursor-pointer select-none mb-1">
                                <input type="checkbox" name="option[1][check]"
                                       @checked(old('option.1.check')) class="sr-only peer" />
                                <div
                                    class="w-6 min-w-6 h-6 min-h-6 rounded-md border-2 border-zinc-600 bg-zinc-800
             peer-checked:bg-blue-600 peer-checked:border-blue-400
             peer-checked:shadow-[0_0_8px_3px_rgba(59,130,246,0.7)]
             transition-all duration-300 flex items-center justify-center"
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
                                        <path d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span
                                    class="text-zinc-300 hover:text-white transition-colors">Сгенерировать аккаунт</span>
                            </label>
                            <p class="text-zinc-400 text-sm">Активируйте пункт если желаете создать новый аккаунт
                                PlayStation
                                Network ID. Мы создаем исключительно персональный аккаунт, на
                                основе вашего имени, фамилии и даты рождения.</p>
                            @error('option[1][check]')
                            <p class="text-red-500 text-sm">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div id="option[0][check]" class="@if(!old('option.0.check')) hidden @endif space-y-5">
                    <div>
                        <label class="block text-sm text-zinc-300 mb-1" for="option[0][ps_network_id]">Идентификатор
                            PlayStation Network ID</label>
                        <input
                            name="option[0][ps_network_id]"
                            id="option[0][ps_network_id]"
                            @if(!old('option.0.check')) disabled @endif
                            type="email"
                            value="{{ old('option.0.ps_network_id') }}"
                            placeholder="Введите ваш PSN ID (email)"
                            class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                        />
                        @error('option.0.ps_network_id')
                        <p class="text-red-500 text-sm">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-zinc-300 mb-1" for="option[0][ps_network_password]">Пароль
                            PlayStation Network ID</label>
                        <input
                            name="option[0][ps_network_password]"
                            id="option[0][ps_network_password]"
                            type="password"
                            @if(!old('option.0.check')) disabled @endif
                            value="{{ old('option.0.ps_network_password') }}"
                            placeholder="Введите ваш пароль"
                            class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                        />
                        @error('option.0.ps_network_password')
                        <p class="text-red-500 text-sm">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-zinc-300 mb-1" for="option[0][ps_network_password]">Резервный
                            код
                            2fа</label>
                        <input
                            name="option[0][ps_2fa_code]"
                            type="text"
                            id="option[0][ps_2fa_code]"
                            @if(!old('option.0.check')) disabled @endif
                            value="{{ old('option.0.ps_2fa_code') }}"
                            placeholder="Введите резервный код 2fа"
                            class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                        />
                        @error('option.0.ps_2fa_code')
                        <p class="text-red-500 text-sm">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div id="option[1][check]" class="@if(!old('option.1.check')) hidden @endif space-y-5">
                    <div>
                        <div class="mb-1">
                            <label class="block text-sm text-zinc-300" for="option[0][ps_network_id]">Дата
                                рождения</label>
                            <p class="text-zinc-400 text-sm">Является ответом на секретный вопрос восстановления
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
                            class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                        />
                        @error('option.1.ps_birthday')
                        <p class="text-red-500 text-sm">{{ $message }}</p>
                        @enderror
                    </div>
                    <p class="text-zinc-400 text-sm border-1 border-zinc-500 rounded-xl px-4 py-2">
                        Сгенерируем для вас защищенный аккаунт PlayStation Network и отправим идентификационные данные
                        на ваш указанный Email
                    </p>
                </div>
            @endif

            <div class="flex sm:flex-row justify-between gap-3 flex-col">
                <div class="w-full">
                    <div>
                        <label class="block text-sm text-zinc-300 mb-1" for="phone">Телефон<span
                                class="text-red-500">*</span></label>
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
                            class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                        />
                        @error('phone')
                        <p class="text-red-500 text-sm">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="w-full">
                    <div>
                        <label class="block text-sm text-zinc-300 mb-1" for="email">Email<span
                                class="text-red-500">*</span></label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            placeholder="you@example.com"
                            autocomplete="email"
                            tabindex="4"
                            value="{{ old('email') }}"
                            required
                            class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                        />
                        @error('email')
                        <p class="text-red-500 text-sm">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
            <button
                type="submit"
                class="cursor-pointer w-full bg-blue-600 hover:bg-blue-700 transition-colors duration-200 text-white font-semibold py-2 px-4 rounded-xl shadow-md focus:ring-2 focus:ring-blue-600 focus:outline-none"
                tabindex="5"
            >
                Отправить
            </button>
        </form>
    </div>

@endsection

@section('scripts')
    <script>
        const group = document.getElementById('checkbox-group');

        if(group) {
            const checkboxes = group.querySelectorAll('input[type="checkbox"]');

            const option_0 = document.getElementById('option[0][check]');
            const option_1 = document.getElementById('option[1][check]');

            const option_0_ps_network_id = document.querySelector('[name="option[0][ps_network_id]"]');
            const option_0_ps_network_password = document.querySelector('[name="option[0][ps_network_password]"]');
            const option_0_ps_2fa_code = document.querySelector('[name="option[0][ps_2fa_code]"]');

            const option_1_ps_birthday = document.querySelector('[name="option[1][ps_birthday]"]');

            function toggleOption(num) {
                if (num === 0) {
                    option_0.classList.remove('hidden');
                    option_1.classList.add('hidden');

                    option_0_ps_network_id.removeAttribute('disabled');
                    option_0_ps_network_password.removeAttribute('disabled');
                    option_0_ps_2fa_code.removeAttribute('disabled');
                    option_1_ps_birthday.setAttribute('disabled', 'disabled');

                } else if (num === 1) {
                    option_0.classList.add('hidden');
                    option_1.classList.remove('hidden');

                    option_0_ps_network_id.setAttribute('disabled', 'disabled');
                    option_0_ps_network_password.setAttribute('disabled', 'disabled');
                    option_0_ps_2fa_code.setAttribute('disabled', 'disabled');
                    option_1_ps_birthday.removeAttribute('disabled');
                } else {
                    option_0.classList.add('hidden');
                    option_1.classList.add('hidden');

                    option_1_ps_birthday.setAttribute('disabled', 'disabled');
                    option_0_ps_2fa_code.setAttribute('disabled', 'disabled');
                    option_0_ps_network_id.setAttribute('disabled', 'disabled');
                    option_0_ps_network_password.setAttribute('disabled', 'disabled');
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

        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.getElementById('phone');
            let previousValue = '';

            phoneInput.addEventListener('input', function(e) {
                let value = phoneInput.value.replace(/\D/g, '');

                // Если просто удаление — не форматируем заново
                if (value.length < previousValue.replace(/\D/g, '').length) {
                    previousValue = phoneInput.value;
                    return;
                }

                if (value.startsWith('8')) value = '7' + value.slice(1);
                if (value.length > 11) value = value.slice(0, 11);

                let formatted = '+7';
                if (value.length > 1) formatted += ' (' + value.slice(1, 4);
                if (value.length >= 4) formatted += ') ' + value.slice(4, 7);
                if (value.length >= 7) formatted += '-' + value.slice(7, 9);
                if (value.length >= 9) formatted += '-' + value.slice(9, 11);

                phoneInput.value = formatted;
                previousValue = formatted;
            });

            phoneInput.addEventListener('blur', function() {
                if (phoneInput.value.length < 18) {
                    phoneInput.setCustomValidity('Введите номер полностью');
                } else {
                    phoneInput.setCustomValidity('');
                }
            });
        });
    </script>
@endsection
