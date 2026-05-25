@php
    $tenant = $this->getLegalEntity();
    $inn = $tenant?->inn ?: '—';
    $name = $tenant?->name ?: ($tenant?->short_name ?: 'Консорциум Партнер');
    $status = $tenant?->status ?: 'active';
    $tariff = $tenant?->tariff_type ?: 'standard';
    $signed = $tenant?->agreement_signed_at ? $tenant->agreement_signed_at->format('d.m.Y') : null;
@endphp

<div class="col-span-full mb-6">
    <div class="relative overflow-hidden rounded-2xl border border-white/5 bg-[#0a0a0a] p-6 md:p-8 shadow-2xl">
        <!-- Accent Glow -->
        <div class="absolute -right-24 -top-24 h-64 w-64 rounded-full bg-red-600/10 blur-[60px] pointer-events-none"></div>
        <div class="absolute -left-24 -bottom-24 h-64 w-64 rounded-full bg-red-600/5 blur-[80px] pointer-events-none"></div>

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6 relative z-10">
            <!-- Left Info Pane -->
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-2.5 mb-3">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black tracking-wider uppercase bg-red-600/10 text-red-500 border border-red-600/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                        Consortium Terminal
                    </span>
                    @if($signed)
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            Договор оферты подписан ({{ $signed }})
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                            Требуется подписание оферты
                        </span>
                    @endif
                </div>

                <h2 class="text-xl md:text-2xl font-black tracking-tight text-white mb-2 uppercase">
                    {{ $name }}
                </h2>
                
                <p class="text-xs text-gray-400 max-w-xl leading-relaxed mb-4">
                    Добро пожаловать в B2B консоль управления поставками цифровых продуктов Meanly Systems. Ниже представлены быстрые инструменты интеграции и статус клиринга.
                </p>

                <!-- Monospace details grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-white/5 text-[11px] font-mono text-gray-500">
                    <div>
                        <div class="text-[9px] uppercase tracking-wider text-gray-600 mb-0.5">ИНН Предприятия</div>
                        <div class="font-semibold text-gray-300">{{ $inn }}</div>
                    </div>
                    <div>
                        <div class="text-[9px] uppercase tracking-wider text-gray-600 mb-0.5">Тариф дистрибуции</div>
                        <div class="font-semibold text-gray-300 capitalize">{{ $tariff }}</div>
                    </div>
                    <div>
                        <div class="text-[9px] uppercase tracking-wider text-gray-600 mb-0.5">Юрисдикция расчетов</div>
                        <div class="font-semibold text-gray-300">RU (Российская Федерация)</div>
                    </div>
                    <div>
                        <div class="text-[9px] uppercase tracking-wider text-gray-600 mb-0.5">Валюта баланса</div>
                        <div class="font-semibold text-red-500">RUB (Рубль)</div>
                    </div>
                </div>
            </div>

            <!-- Right Actions Pane (Sleek Shortcuts) -->
            <div class="flex flex-col sm:flex-row md:flex-col gap-3 min-w-[220px]">
                <a href="{{ $this->getFinanceUrl() }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-xs font-bold bg-white text-black hover:bg-white/90 transition-all shadow-lg hover:shadow-white/5 active:scale-95 text-center">
                    <svg class="w-4 h-4" style="width: 16px; height: 16px; flex-shrink: 0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                    Пополнить баланс
                </a>
                <a href="{{ $this->getIntegrationsUrl() }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-xs font-bold bg-[#111] hover:bg-[#181818] text-white border border-white/5 hover:border-white/10 transition-all active:scale-95 text-center">
                    <svg class="w-4 h-4 text-red-500" style="width: 16px; height: 16px; flex-shrink: 0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Настройки API и ключи
                </a>
                <a href="{{ $this->getShopsUrl() }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-xs font-bold bg-[#111] hover:bg-[#181818] text-white border border-white/5 hover:border-white/10 transition-all active:scale-95 text-center">
                    <svg class="w-4 h-4 text-gray-500" style="width: 16px; height: 16px; flex-shrink: 0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    Мои Магазины
                </a>
            </div>
        </div>
    </div>
</div>
