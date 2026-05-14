<x-filament-panels::page>
    @php
        $entity = Filament\Facades\Filament::getTenant();
    @endphp

    @if($entity)
        <div class="fi-section-content-ctn" style="display: flex; flex-direction: column; gap: 2rem; padding-top: 1rem; padding-bottom: 2rem;">
            {{-- Сетка балансов --}}
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                {{-- Available --}}
                <x-filament::section class="fi-section-balance">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2 text-success-600 dark:text-success-400">
                            <svg style="width: 20px; height: 20px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3" />
                            </svg>
                            <span class="text-xs font-bold uppercase tracking-wider">Доступно</span>
                        </div>
                        <div class="text-3xl font-black tracking-tight text-gray-950 dark:text-white">
                            {{ number_format($entity->available_balance, 2, '.', ' ') }}
                            <span class="text-sm font-medium text-gray-400 uppercase">{{ $entity->currency }}</span>
                        </div>
                        <p class="text-[11px] text-gray-500 leading-none mt-1">Средства, готовые к использованию</p>
                    </div>
                </x-filament::section>

                {{-- Reserved --}}
                <x-filament::section>
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2 text-warning-500">
                            <svg style="width: 20px; height: 20px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25-2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            <span class="text-xs font-bold uppercase tracking-wider">В холде</span>
                        </div>
                        <div class="text-3xl font-black tracking-tight text-gray-950 dark:text-white">
                            {{ number_format($entity->reserved_balance, 2, '.', ' ') }}
                            <span class="text-sm font-bold text-gray-400 uppercase">{{ $entity->currency }}</span>
                        </div>
                        <p class="text-[11px] text-gray-500 leading-none mt-1">Заморожено под активные товары</p>
                    </div>
                </x-filament::section>

                {{-- Total --}}
                <x-filament::section>
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2 text-primary-500">
                            <svg style="width: 20px; height: 20px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-xs font-bold uppercase tracking-wider">Капитал</span>
                        </div>
                        <div class="text-3xl font-black tracking-tight text-gray-950 dark:text-white">
                            {{ number_format($entity->available_balance + $entity->reserved_balance, 2, '.', ' ') }}
                            <span class="text-sm font-bold text-gray-400 uppercase">{{ $entity->currency }}</span>
                        </div>
                        <p class="text-[11px] text-gray-500 leading-none mt-1">Суммарная стоимость активов</p>
                    </div>
                </x-filament::section>
            </div>

            {{-- Описание --}}
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; align-items: start;">
                <x-filament::section heading="Как работает Hold/Capture" icon="heroicon-m-shield-check">
                    <div class="space-y-6 pt-2">
                        <div class="flex items-start gap-4">
                            <div class="w-8 h-8 rounded-lg bg-primary-500/10 text-primary-600 flex items-center justify-center shrink-0 font-bold">1</div>
                            <div>
                                <span class="font-bold text-gray-900 dark:text-white block">Резервирование</span>
                                <p class="text-sm text-gray-500">При добавлении товара в каталог нужная сумма «замораживается». Это гарантирует моментальный выкуп кода у провайдера при продаже.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div class="w-8 h-8 rounded-lg bg-success-500/10 text-success-600 flex items-center justify-center shrink-0 font-bold">2</div>
                            <div>
                                <span class="font-bold text-gray-900 dark:text-white block">Списание</span>
                                <p class="text-sm text-gray-500">Окончательное списание происходит только в момент, когда ваш клиент успешно получил рабочий код ваучера.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div class="w-8 h-8 rounded-lg bg-warning-500/10 text-warning-600 flex items-center justify-center shrink-0 font-bold">3</div>
                            <div>
                                <span class="font-bold text-gray-900 dark:text-white block">Разморозка</span>
                                <p class="text-sm text-gray-500">Если произошла ошибка активации или вы сняли товар с витрины — средства мгновенно возвращаются на доступный баланс.</p>
                            </div>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section heading="История операций" icon="heroicon-m-banknotes">
                    <div class="flex flex-col items-center justify-center py-16 text-center text-gray-400">
                        <div class="w-16 h-16 bg-gray-50 dark:bg-gray-800 rounded-full flex items-center justify-center mb-4">
                            <svg style="width: 32px; height: 32px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-sm font-bold uppercase tracking-wider">История пока пуста</p>
                        <p class="text-xs mt-1">Все движения по вашему счету будут отображаться здесь</p>
                    </div>
                </x-filament::section>
            </div>
        </div>
    @else
        <x-filament::section class="text-center py-20">
            <h3 class="text-xl font-black text-gray-950 dark:text-white">Юридическое лицо не привязано</h3>
            <p class="text-gray-500 mt-2">Пожалуйста, привяжите Юр. лицо в настройках магазина для управления балансом.</p>
        </x-filament::section>
    @endif

    <style>
        .fi-section-balance {
            transition: all 0.2s ease-in-out;
        }
        .fi-section-balance:hover {
            transform: translateY(-2px);
            border-color: rgba(var(--primary-500), 0.3);
        }
    </style>
</x-filament-panels::page>
