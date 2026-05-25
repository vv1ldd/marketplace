<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Shop;
use App\Services\FinanceService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ProductResource extends Resource
{
    use \App\Filament\Concerns\HasYandexCategoryParameters;

    protected static ?string $model = \App\Models\Product::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    public static function getNavigationGroup(): ?string
    {
        return 'Мастер-ключ';
    }

    public static function getNavigationLabel(): string
    {
        return 'Все товары';
    }

    public static function getLabel(): string
    {
        return __('admin.products.product');
    }

    public static function getPluralLabel(): string
    {
        return __('admin.products.products');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    Section::make('Основная информация')
                        ->columnSpan(2)
                        ->schema([
                            TextInput::make('sku')
                                ->label(__('admin.products.sku'))
                                ->required()
                                ->unique(ignoreRecord: true),
                            TextInput::make('name')
                                ->label(__('admin.products.name'))
                                ->required()
                                ->columnSpanFull(),

                            \Filament\Forms\Components\Hidden::make('type')
                                ->default('other'),
                            self::getCategorySelectorField()->columnSpanFull(),

                            Textarea::make('description')
                                ->label(__('admin.products.fields.description'))
                                ->rows(3)
                                ->columnSpanFull(),
                        ]),

                    Section::make('Цены и статус')
                        ->columnSpan(1)
                        ->schema([
                            TextInput::make('price_rub')
                                ->label('Цена')
                                ->numeric()
                                ->prefix('₽')
                                ->required()
                                ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                ->dehydrateStateUsing(fn ($state) => $state ? (int) round($state * 100) : null),
                            TextInput::make('purchase_price_rub')
                                ->label('Закупочная цена')
                                ->numeric()
                                ->prefix('₽')
                                ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                ->dehydrateStateUsing(fn ($state) => $state ? (int) round($state * 100) : null),
                            Toggle::make('is_active')
                                ->label('Активен')
                                ->default(true),
                        ]),
                ]),

                Tabs::make('Details')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Характеристики')
                            ->icon('heroicon-o-tag')
                            ->schema(fn (Get $get) => self::getCategoryParametersSchema($get)),
                        Tabs\Tab::make('Yandex Market Errors')
                            ->visible(fn ($record) => ! empty($record?->ym_errors))
                            ->icon('heroicon-o-exclamation-triangle')
                            ->schema([
                                \Filament\Schemas\Components\View::make('filament.components.ym-errors-list'),
                            ]),
                        Tabs\Tab::make('SEO')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                TextInput::make('slug')
                                    ->unique(Product::class, 'slug', ignoreRecord: true),
                                TextInput::make('meta_title'),
                                Textarea::make('meta_description'),
                                TextInput::make('meta_keywords'),
                            ]),
                        Tabs\Tab::make('Media')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                FileUpload::make('image')
                                    ->image()
                                    ->directory('products'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['brand', 'catalog', 'catalogCategory', 'provider', 'shop'])
                ->withSum('stocks as stocks_sum_count', 'count'))
            ->defaultPaginationPageOption(10)
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->square()
                    ->size(80)
                    ->defaultImageUrl(fn ($record) => \App\Services\BrandLogoService::getLogoUrl($record->category))
                    ->extraImgAttributes(['class' => 'rounded-lg shadow-sm border border-gray-100 dark:border-gray-700']),
                TextColumn::make('brand.name')
                    ->label('Бренд')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('sku')
                    ->label(__('admin.products.sku'))
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('admin.products.name'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('catalogCategory.name')
                    ->label(__('admin.products.fields.category'))
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('catalog.name')
                    ->label('Каталог')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state): string => $state === 'Глобальный каталог' ? 'primary' : 'warning')
                    ->searchable(),
                TextColumn::make('shop.name')
                    ->label(__('admin.users.shop'))
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('selling_price')
                    ->label('Цена на витрине')
                    ->money('RUB', divideBy: 100)
                    ->getStateUsing(function (Product $record) {
                        $shop = static::referenceShopForPricing($record);

                        return $shop
                            ? app(FinanceService::class)->getShopFinalPrice($record, $shop)
                            : (int) ($record->price_rub ?? 0);
                    })
                    ->description(function (Product $record) {
                        $shop = static::referenceShopForPricing($record);

                        return $shop?->name
                            ? 'Магазин: '.$shop->name
                            : 'Без привязки к магазину — базовая цена';
                    })
                    ->color('success')
                    ->weight('bold'),
                TextColumn::make('margin')
                    ->label('Маржа')
                    ->money('RUB', divideBy: 100)
                    ->getStateUsing(function (Product $record) {
                        $shop = static::referenceShopForPricing($record);
                        if (! $shop) {
                            return null;
                        }
                        $final = app(FinanceService::class)->getShopFinalPrice($record, $shop);

                        return $final - (int) ($record->purchase_price_rub ?? 0);
                    })
                    ->color('gray'),
                TextColumn::make('stocks_sum_count')
                    ->label('Остаток')
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'success' : 'danger')
                    ->default(0),
                TextColumn::make('origin')
                    ->label('Источник')
                    ->getStateUsing(fn (Product $record) => $record->catalog_id === null ? 'Витрина' : 'Свой / Яндекс')
                    ->badge()
                    ->color(fn ($state) => $state === 'Витрина' ? 'info' : 'warning'),
                TextColumn::make('ym_status')
                    ->label('Статус на Яндексе')
                    ->state(fn (Product $record) => ! empty($record->ym_errors) ? 'Ошибка' : 'Ок')
                    ->badge()
                    ->color(fn (Product $record) => ! empty($record->ym_errors) ? 'danger' : 'success')
                    ->tooltip(fn (Product $record) => collect($record->ym_errors ?? [])->pluck('message')->filter()->implode('; ')),
                \Filament\Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('admin.products.fields.is_active')),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('catalog_id')
                    ->label('Каталог')
                    ->relationship('catalog', 'name'),
                SelectFilter::make('shop_id')
                    ->label(__('admin.users.shop'))
                    ->options(fn () => Shop::query()->orderBy('name')->pluck('name', 'id'))
                    ->modifyQueryUsing(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->where('shop_id', $data['value']);
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('preview_card')
                    ->label('Карточка')
                    ->icon('heroicon-m-photo')
                    ->color('gray')
                    ->modalHeading('Предпросмотр карточки')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть')
                    ->modalContent(fn (Product $record) => new HtmlString('
                        <div class="flex flex-col items-center justify-center space-y-4">
                            <div class="rounded-xl overflow-hidden shadow-2xl border border-gray-200" style="max-width: 400px;">
                                <img src="/'.$record->image.'?v='.time().'"
                                     alt="Card Preview"
                                     style="width: 100%; height: auto; display: block;"
                                />
                            </div>
                        </div>
                    '))
                    ->visible(fn (Product $record) => filled($record->image)),
                Action::make('regenerate_card')
                    ->label('Обновить')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Product $record) {
                        $shop = static::referenceShopForPricing($record);
                        $catalogItem = $record->catalog_id
                            ? \App\Models\WildflowCatalog::find($record->catalog_id)
                            : null;

                        if (! $catalogItem || ! $shop) {
                            Notification::make()->title('Невозможно обновить')->body('Нужны каталог Wildflow и магазин для генерации.')->danger()->send();

                            return;
                        }

                        $cardService = app(\App\Services\CardImageService::class);
                        $kit = $cardService->generateForCatalogItem($catalogItem, $shop);
                        $mainImage = $kit['images']['main'] ?? $record->image;

                        if ($mainImage) {
                            $record->update([
                                'image' => $mainImage,
                                'pictures' => $kit['images'] ?? [],
                            ]);
                            Notification::make()->title('Карточка обновлена')->success()->send();
                        }
                    })
                    ->visible(fn (Product $record) => $record->catalog_id !== null),
                Action::make('mark_provider_out_of_stock')
                    ->label('Снять с продажи (нет стока у провайдера)')
                    ->icon('heroicon-m-archive-box-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Снять позицию с продажи?')
                    ->modalDescription('Та же логика, что при ошибке 614 EzPayPin: глобальный каталог Wildflow, все товары селлеров с этим SKU, остатки на складах и связи provider_products.')
                    ->action(function (Product $record) {
                        $sku = trim((string) $record->sku);
                        if ($sku === '') {
                            Notification::make()->title('Нет SKU')->danger()->send();

                            return;
                        }
                        $ok = \App\Models\WildflowCatalog::applyProviderOutOfStockToSku($sku, 'manual_admin_product');
                        if ($ok) {
                            Notification::make()
                                ->title('Готово')
                                ->body('Позиция снята с продажи по SKU '.$sku.'.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Ничего не изменено')
                                ->body('Нет строк wildflow_catalogs / products / provider_products по этому SKU.')
                                ->warning()
                                ->send();
                        }
                    })
                    ->visible(fn (Product $record) => $record->provider?->type === 'wildflow' || str_starts_with((string) $record->sku, 'VOUCHER-')),
                Action::make('send_to_market')
                    ->label('В Маркет')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('success')
                    ->action(function (Product $record) {
                        $shop = static::referenceShopForPricing($record);
                        if (! $shop) {
                            Notification::make()->title('Нет магазина')->body('Привяжите товар к магазину или создайте shop.')->danger()->send();

                            return;
                        }
                        $service = new \App\Http\Services\YmService($shop);
                        $resolver = app(\App\Services\CanonicalCategoryResolver::class);
                        $fallbackCategoryId = (int) ($record->market_category_id ?: $shop->ym_category_id ?: \App\Models\Settings::get('YM_CATEGORY_ID', 989939));
                        $categoryId = $resolver->yandexCategoryId($resolver->forProduct($record), $fallbackCategoryId);
                        try {
                            $service->offerMappingsUpdate([['offer' => $record->toYmOffer($categoryId, $shop->id)]]);
                            Notification::make()->title('Товар отправлен')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Ошибка')->body($e->getMessage())->danger()->send();
                        }
                    })
                    ->visible(fn (Product $record) => static::adminCanSendToYm($record)),
                Action::make('post_to_telegram')
                    ->label('В Telegram')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\Select::make('direct_channel_id')
                            ->label('Выберите канал')
                            ->options(\App\Models\DirectChannel::where('type', 'telegram_bot')->where('is_active', true)->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $channel = \App\Models\DirectChannel::find($data['direct_channel_id']);
                        
                        if (!$channel) {
                            Notification::make()->title('Ошибка')->body('Канал не найден')->danger()->send();
                            return;
                        }

                        if (!$record->catalog_id) {
                            Notification::make()->title('Ошибка')->body('У этого товара нет связи с Wildflow Catalog')->danger()->send();
                            return;
                        }

                        \Illuminate\Support\Facades\Artisan::call('telegram:auto-post', [
                            '--channel' => $channel->id,
                            '--product' => $record->catalog_id,
                        ]);
                        
                        Notification::make()
                            ->title('Запощено!')
                            ->body("Товар отправлен в канал {$channel->name}")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Магазин для расчёта «витринной» цены в глобальной админке: первый из привязанных к товару или первый в БД.
     */
    public static function referenceShopForPricing(?Product $record): ?Shop
    {
        if ($record?->shop) {
            return $record->shop;
        }

        return Shop::query()->orderBy('id')->first();
    }

    protected static function adminCanSendToYm(Product $record): bool
    {
        $shop = static::referenceShopForPricing($record);
        if (! $shop) {
            return false;
        }

        return filled($shop->api_key) && filled($shop->campaign_id);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
