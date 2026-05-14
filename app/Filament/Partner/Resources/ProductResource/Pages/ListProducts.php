<?php

namespace App\Filament\Partner\Resources\ProductResource\Pages;

use App\Filament\Partner\Resources\ProductResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    // Реактивное свойство — изменение вызывает ре-рендер Livewire
    public int $syncProgress = 0;

    // Поддержка обоих механизмов событий Livewire v2/v3
    protected $listeners = ['catalog-sync-updated' => 'handleSyncUpdate'];

    public function mount(): void
    {
        parent::mount();
        $shop = Filament::getTenant();
        $this->syncProgress = $shop ? (int) $shop->import_progress : 0;
    }

    #[On('catalog-sync-updated')]
    public function handleSyncUpdate(): void
    {
        $shop = Filament::getTenant();
        if (! $shop) return;

        $prev = $this->syncProgress;
        $this->syncProgress = (int) $shop->fresh()->import_progress;

        // Уведомление при завершении
        if ($prev > 0 && $prev < 100 && $this->syncProgress === 100) {
            Notification::make()
                ->title('Каталог Яндекс Маркет обновлен')
                ->body('Данные успешно синхронизированы.')
                ->success()
                ->send()
                ->sendToDatabase(auth()->user());
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_yandex')
                ->label(function () {
                    if ($this->syncProgress == 100) {
                        $icon = \Illuminate\Support\Facades\Blade::render('<x-heroicon-o-check-circle class="h-5 w-5 text-white" />');
                        return new \Illuminate\Support\HtmlString('<span style="display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; min-width: max-content;">' . $icon . '<span>Каталог обновлен</span></span>');
                    }
                    if ($this->syncProgress > 0 && $this->syncProgress < 100) {
                        $spinner = \Illuminate\Support\Facades\Blade::render('<x-filament::loading-indicator class="h-5 w-5 animate-spin" />');
                        return new \Illuminate\Support\HtmlString('<span style="display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; min-width: max-content;">' . $spinner . '<span>Синхронизация...</span></span>');
                    }
                    $icon = \Illuminate\Support\Facades\Blade::render('<x-heroicon-o-arrow-path class="h-5 w-5" />');
                    return new \Illuminate\Support\HtmlString('<span style="display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; min-width: max-content;">' . $icon . '<span>Обновить из Яндекса</span></span>');
                })
                ->icon(null) // Мы отрисовываем иконку вручную внутри label для контроля верстки
                ->color(function () {
                    return $this->syncProgress == 100 ? 'success' : 'warning';
                })
                ->disabled(fn () => $this->syncProgress > 0 && $this->syncProgress < 100)
                ->visible(fn () => $this->activeTab === 'yandex_market')
                ->action(function () {
                    $shop = Filament::getTenant();
                    $token = 'im_' . uniqid('', true);

                    $shop->update([
                        'import_status' => 'Запуск...',
                        'import_progress' => 1,
                        'import_token' => $token,
                    ]);

                    $this->syncProgress = 1; // немедленно переводим кнопку в состояние загрузки

                    \App\Jobs\ImportProductsFromYM::dispatch($shop, $token);

                    Notification::make()
                        ->title('Синхронизация запущена')
                        ->info()
                        ->send()
                        ->sendToDatabase(auth()->user());
                }),
        ];
    }

    protected function makeTable(): \Filament\Tables\Table
    {
        $isSyncing = $this->syncProgress > 0 && $this->syncProgress < 100;

        return parent::makeTable()
            ->extraAttributes([
                'class' => $isSyncing 
                    ? 'opacity-60 pointer-events-none select-none blur-sm transition-all duration-700' 
                    : 'transition-all duration-700',
            ]);
    }

    public function getTabs(): array
    {
        $shopId = Filament::getTenant()?->id;

        return [
            'all' => \Filament\Schemas\Components\Tabs\Tab::make('Все товары')
                ->modifyQueryUsing(fn ($query) => $query->where('is_active', true)),
            'storefront_only' => \Filament\Schemas\Components\Tabs\Tab::make('Только витрина')
                ->modifyQueryUsing(function ($query) use ($shopId) {
                    return $query->where('is_active', true)
                        ->whereDoesntHave('salesChannels', function ($q) use ($shopId) {
                            $q->where('shop_id', $shopId)->where('is_enabled', true);
                        });
                }),
            'errors' => \Filament\Schemas\Components\Tabs\Tab::make('С ошибками')
                ->modifyQueryUsing(fn ($query) => $query->where('is_active', true)->whereNotNull('ym_errors')->where('ym_errors', '!=', '[]')),
            'archived' => \Filament\Schemas\Components\Tabs\Tab::make('Архив')
                ->icon('heroicon-m-archive-box')
                ->modifyQueryUsing(fn ($query) => $query->where('is_active', false)),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Partner\Widgets\PollingWidget::class,
        ];
    }
}
