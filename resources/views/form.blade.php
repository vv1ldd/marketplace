@extends('layouts.app')

@section('title', 'Form')

@section('content')
    <div class="w-full max-w-xl bg-zinc-800 border border-zinc-700 rounded-2xl shadow-xl p-8">
        <h2 class="text-2xl font-bold text-white mb-6 text-center">Ваш заказ почти готов, остался один шаг</h2>
        <form class="space-y-5">
            <div class="flex sm:flex-row justify-between gap-3 flex-col">
                <div class="w-full">
                    <label class="block text-sm text-zinc-300 mb-1" for="first_name">Имя<span
                            class="text-red-500">*</span></label>
                    <input
                        id="first_name"
                        type="text"
                        placeholder="Ваше имя"
                        autocomplete="first_name"
                        autofocus
                        required
                        tabindex="1"
                        class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                    />
                </div>
                <div class="w-full">
                    <label class="block text-sm text-zinc-300 mb-1" for="last_name">Фамилия<span
                            class="text-red-500">*</span></label>
                    <input
                        id="last_name"
                        type="text"
                        placeholder="Ваша фамилия"
                        autocomplete="last_name"
                        required
                        tabindex="2"
                        class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                    />
                </div>
            </div>

            <div>
                <div id="checkbox-group" class="space-y-4 mx-auto p-4 bg-zinc-900 rounded-xl">
                    <div>
                        <label class="flex items-center gap-3 cursor-pointer select-none mb-1">
                            <input type="checkbox" name="option[0]" class="sr-only peer"/>
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
                                    <path d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <span class="text-zinc-300 hover:text-white transition-colors">Оформить покупку на имеющийся PlayStation Network ID</span>
                        </label>
                        <p class="text-zinc-400 text-sm">Активируйте пункт если желаете оформить покупку на имеющийся
                            аккаунт PlayStation Network ID.</p>
                    </div>

                    <div>
                        <label class="flex items-center gap-3 cursor-pointer select-none mb-1">
                            <input type="checkbox" name="option[1]" class="sr-only peer"/>
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
                                    <path d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <span class="text-zinc-300 hover:text-white transition-colors">Сгенерировать аккаунт</span>
                        </label>
                        <p class="text-zinc-400 text-sm">Активируйте пункт если желаете создать новый аккаунт
                            PlayStation
                            Network ID. Мы создаем исключительно персональный аккаунт, на
                            основе вашего имени, фамилии и даты рождения.</p>
                    </div>
                </div>
            </div>

            <div id="option[0]" class="hidden space-y-5">
                <div>
                    <label class="block text-sm text-zinc-300 mb-1" for="option[0][ps_network_id]">Идентификатор
                        PlayStation Network ID</label>
                    <input
                        name="option[0][ps_network_id]"
                        type="email"
                        placeholder="Введите ваш PSN ID (email)"
                        class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                    />
                </div>
                <div>
                    <label class="block text-sm text-zinc-300 mb-1" for="option[0][ps_network_password]">Пароль
                        PlayStation Network ID</label>
                    <input
                        name="option[0][ps_network_password]"
                        type="password"
                        placeholder="Введите ваш пароль"
                        class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                    />
                </div>
                <div>
                    <label class="block text-sm text-zinc-300 mb-1" for="option[0][ps_network_password]">Резервный код
                        2fа</label>
                    <input
                        name="option[0][ps_2fa_code]"
                        type="text"
                        placeholder="Введите резервный код 2fа"
                        class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                    />
                </div>
            </div>

            <div id="option[1]" class="hidden space-y-5">
                <div >
                    <div class="mb-1">
                        <label class="block text-sm text-zinc-300" for="option[0][ps_network_id]">Дата рождения</label>
                        <p class="text-zinc-400 text-sm">Является ответом на секретный вопрос восстановления
                            аккаунта PlayStation Network. Используйте свою реальную дату
                            рождения.</p>
                    </div>

                    <input
                        name="option[1][ps_birthday]"
                        type="date"
                        placeholder="дд.мм.гггг"
                        class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                    />
                </div>
                <p class="text-zinc-400 text-sm border-1 border-zinc-500 rounded-xl px-4 py-2">
                    Сгенерируем для вас защищенный аккаунт PlayStation Network и отправим идентификационные данные
                    на ваш указанный Email
                </p>
            </div>

            <div class="flex sm:flex-row justify-between gap-3 flex-col">
                <div class="w-full">
                    <div>
                        <label class="block text-sm text-zinc-300 mb-1" for="email">Телефон<span
                                class="text-red-500">*</span></label>
                        <input
                            id="phone"
                            type="text"
                            placeholder="+7 (999) 999-99-99"
                            autocomplete="tel"
                            tabindex="3"
                            required
                            class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                        />
                    </div>
                </div>
                <div class="w-full">
                    <div>
                        <label class="block text-sm text-zinc-300 mb-1" for="email">Email<span
                                class="text-red-500">*</span></label>
                        <input
                            id="email"
                            type="email"
                            placeholder="you@example.com"
                            autocomplete="email"
                            tabindex="4"
                            required
                            class="w-full rounded-xl border border-zinc-600 bg-zinc-700 text-white placeholder-zinc-400 focus:ring-2 focus:ring-blue-600 focus:outline-none px-4 py-2"
                        />
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
        const checkboxes = group.querySelectorAll('input[type="checkbox"]');

        const option_0 = document.getElementById('option[0]');
        const option_1 = document.getElementById('option[1]');

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('click', () => {
                if (checkbox.checked) {
                    checkboxes.forEach(cb => {
                        if (cb !== checkbox) {
                            cb.checked = false;
                            cb.dispatchEvent(new Event('change'));
                        }
                    });

                    if (checkbox.getAttribute('name') === 'option[0]') {
                        option_0.classList.remove('hidden');
                        option_1.classList.add('hidden');
                    } else {
                        option_0.classList.add('hidden');
                        option_1.classList.remove('hidden');
                    }

                } else {
                    option_0.classList.add('hidden');
                    option_1.classList.add('hidden');
                }
            });
        });
    </script>
@endsection
