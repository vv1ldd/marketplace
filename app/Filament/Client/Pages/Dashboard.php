<?php

namespace App\Filament\Client\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Html;
use Illuminate\Support\HtmlString;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;

class Dashboard extends BaseDashboard
{
    public function mount()
    {
        if (session('redirect_to_offer')) {
            return redirect()->route('partner.register.offer');
        }
    }
 
    public function content(Schema $schema): Schema
    {
        $user = auth()->user();
        $hasPasskeys = $user->passkeys()->exists();
        $isB2bPartner = $user->hasRole('b2b_partner');
 
        // Fetch stats dynamically
        $totalOrders = Order::where('user_id', $user->id)->count();
        $activeKeysCount = OrderItems::whereHas('order', fn($q) => $q->where('user_id', $user->id))
            ->whereNotNull('original_code')
            ->count();
            
        // Fetch recent decrypted keys
        $recentItems = OrderItems::with(['order', 'game'])
            ->whereHas('order', fn($q) => $q->where('user_id', $user->id))
            ->whereNotNull('original_code')
            ->latest()
            ->take(5)
            ->get();

        $components = [];

        // 1. 🛡️ Welcome & Sovereign Identity Header
        $passkeyStatusHtml = $hasPasskeys 
            ? '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"><i class="ph-bold ph-shield-check" style="font-size: 14px;"></i> Passkey Защита Активна</span>'
            : '<a href="/cabinet/profile" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20 hover:bg-rose-500/20 transition-all"><i class="ph-bold ph-shield-warning" style="font-size: 14px; animation: pulse 2s infinite;"></i> Подключите Passkey для защиты сейфа &rarr;</a>';

        $components[] = Html::make(new HtmlString('
            <div class="relative overflow-hidden rounded-2xl border border-gray-200 dark:border-[#1a1a1a] bg-white dark:bg-[#090909] p-6 sm:p-8 mb-6">
                <!-- Ambient glow background -->
                <div class="absolute -right-24 -top-24 w-48 h-48 bg-[#f53003] rounded-full opacity-[0.05] blur-3xl pointer-events-none"></div>
                
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6 relative z-10">
                    <div class="text-left">
                        <div class="flex flex-wrap items-center gap-3 mb-3">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-xs font-bold bg-[#f53003]/10 text-[#f53003] uppercase tracking-wider">Физическое лицо</span>
                            ' . $passkeyStatusHtml . '
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight leading-tight">
                            Приветствуем, @' . e($user->first_name ?: $user->name) . '! 👋
                        </h2>
                        <p class="text-sm text-gray-400 mt-2 max-w-xl leading-relaxed">
                            Добро пожаловать в ваш суверенный кабинет управления цифровыми активами Meanly. Здесь хранятся ваши защищенные ключи и история транзакций.
                        </p>
                    </div>
                </div>
            </div>
        '));

        // 2. 📊 Dynamic Stats Grid
        $components[] = Html::make(new HtmlString('
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
                <!-- Card 1 -->
                <div class="rounded-xl border border-gray-200 dark:border-[#1a1a1a] bg-white dark:bg-[#090909] p-5 text-left relative overflow-hidden">
                    <div class="absolute right-4 top-4 text-gray-700 dark:text-gray-800 text-3xl"><i class="ph-bold ph-shopping-bag"></i></div>
                    <div class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Всего покупок</div>
                    <div class="text-3xl font-black text-white mt-2">' . $totalOrders . '</div>
                    <div class="text-[11px] text-gray-500 mt-1">Оформлено заказов в ритейле</div>
                </div>
                <!-- Card 2 -->
                <div class="rounded-xl border border-gray-200 dark:border-[#1a1a1a] bg-white dark:bg-[#090909] p-5 text-left relative overflow-hidden">
                    <div class="absolute right-4 top-4 text-emerald-500/20 text-3xl"><i class="ph-bold ph-key"></i></div>
                    <div class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Активные ключи в Сейфе</div>
                    <div class="text-3xl font-black text-white mt-2">' . $activeKeysCount . '</div>
                    <div class="text-[11px] text-gray-500 mt-1">Готовы к моментальной активации</div>
                </div>
                <!-- Card 3 -->
                <div class="rounded-xl border border-gray-200 dark:border-[#1a1a1a] bg-white dark:bg-[#090909] p-5 text-left relative overflow-hidden">
                    <div class="absolute right-4 top-4 text-[#f53003]/20 text-3xl"><i class="ph-bold ph-fingerprint"></i></div>
                    <div class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Сейф-статус</div>
                    <div class="text-3xl font-black text-emerald-400 mt-2">AES-256</div>
                    <div class="text-[11px] text-gray-500 mt-1">Аппаратное шифрование ключей</div>
                </div>
            </div>
        '));

        // 3. 🔑 Sovereign Key Vault Section (Custom Styled)
        $vaultItemsHtml = '';
        if ($recentItems->isEmpty()) {
            $vaultItemsHtml = '
                <div class="text-center py-12 px-6">
                    <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-[#1a1a1a] flex items-center justify-center mx-auto mb-4 text-gray-400 dark:text-gray-600 text-2xl">
                        <i class="ph-bold ph-vault"></i>
                    </div>
                    <h4 class="text-white font-bold text-base mb-1">Ваш личный Сейф пока пуст</h4>
                    <p class="text-xs text-gray-500 max-w-sm mx-auto mb-5 leading-relaxed">
                        Купите лицензионный ключ или подписку на главной витрине, и он мгновенно появится в этом защищенном хранилище.
                    </p>
                    <a href="/" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#f53003] text-white text-xs font-bold hover:bg-[#e22b02] transition-all">
                        Перейти в каталог &rarr;
                    </a>
                </div>
            ';
        } else {
            $vaultItemsHtml = '<div class="space-y-4">';
            foreach ($recentItems as $item) {
                $gameName = $item->game?->name ?: ($item->sku ?? 'Лицензионный ваучер');
                $code = $item->original_code;
                $vendor = $item->game?->vendor ?: 'Digital';
                
                // Truncate code for display, but keep full copyable code
                $displayCode = strlen($code) > 24 ? substr($code, 0, 10) . '...' . substr($code, -10) : $code;
                
                $vaultItemsHtml .= '
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl border border-gray-100 dark:border-[#161616] bg-gray-50/50 dark:bg-[#030303]/40 hover:border-gray-200 dark:hover:border-gray-800 transition-all">
                        <div class="flex items-start gap-3 text-left">
                            <div class="p-2 rounded-lg bg-orange-500/10 text-orange-500 text-xl flex-shrink-0 mt-0.5"><i class="ph-bold ph-cube"></i></div>
                            <div>
                                <h4 class="text-sm font-bold text-white leading-tight">' . e($gameName) . '</h4>
                                <div class="flex items-center gap-2 mt-1.5 text-xs text-gray-500">
                                    <span class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-400 font-bold uppercase tracking-wider text-[10px]">' . e($vendor) . '</span>
                                    <span>' . e($item->transactionReference()) . '</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white dark:bg-[#090909] border border-gray-200 dark:border-[#1a1a1a] font-mono text-sm font-bold text-[#f53003]">
                                <span>' . e($displayCode) . '</span>
                                <button onclick="copyCode(this, \'' . e($code) . '\')" class="text-gray-400 hover:text-white transition-colors ml-2" title="Скопировать ключ">
                                    <i class="ph-bold ph-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                ';
            }
            $vaultItemsHtml .= '</div>';
        }

        $components[] = Section::make('🔑 Защищенный Сейф Лицензий')
            ->description('Здесь хранятся ваши купленные ключи активации. Все данные зашифрованы персональными ключами.')
            ->schema([
                Html::make(new HtmlString('
                    <script>
                        function copyCode(btn, text) {
                            navigator.clipboard.writeText(text).then(() => {
                                const icon = btn.querySelector("i");
                                icon.className = "ph-bold ph-check text-emerald-400";
                                setTimeout(() => {
                                    icon.className = "ph-bold ph-copy";
                                }, 1500);
                            });
                        }
                    </script>
                    ' . $vaultItemsHtml . '
                '))
            ]);

        // 4. 💼 B2B Business Perimeter Expansion Banner
        if (!$isB2bPartner) {
            $components[] = Html::make(new HtmlString('
                <div class="relative overflow-hidden rounded-2xl border border-gray-200 dark:border-[#1a1a1a] bg-gray-50 dark:bg-[#0a0a0a] p-6 sm:p-8 mb-6">
                    <div class="absolute -right-16 -top-16 w-36 h-36 bg-[#f53003] rounded-full opacity-[0.08] blur-2xl pointer-events-none"></div>
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6 relative z-10">
                        <div class="max-w-2xl text-left">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-orange-500/20 bg-orange-500/5 text-orange-500 text-xs font-semibold uppercase tracking-wider mb-4">
                                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                                Бизнес-периметр
                            </div>
                            <h3 class="text-lg sm:text-xl font-bold text-white tracking-tight mb-2">
                                Масштабируйте покупки до B2B-оборота! 💼
                            </h3>
                            <p class="text-sm text-gray-400 leading-relaxed">
                                Подключите юридическое лицо (ООО, ИП или Самозанятый), чтобы разблокировать внешние API-интеграции для автоматических продаж на Ozon, Wildberries и Яндекс Маркет, закупать ключи по оптовым ценам и вести расчеты на расчетный счет.
                            </p>
                        </div>
                        <div class="flex-shrink-0 text-left md:text-right">
                            <a href="/business" class="inline-flex items-center justify-center px-5 py-3 rounded-lg border border-orange-500/30 bg-orange-500/5 hover:bg-orange-500/10 text-white text-sm font-semibold tracking-tight transition-all duration-200 shadow-sm hover:shadow-orange-500/10 hover:-translate-y-0.5">
                                Активировать Бизнес &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            '));
        }

        return $schema->components($components);
    }
}
