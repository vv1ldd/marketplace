<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Exports\WildflowCatalogExporter;
use App\Jobs\AddCatalogItemToShop;
use App\Models\Shop;
use App\Models\WildflowCatalog;
use App\Services\CardImageService;
use App\Services\VideoInstructionService;
use App\Services\WildflowService;
use App\Support\SalesChannels;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Helpers\GenerateSecureCode;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\ProductInventory;
use App\Services\FinanceService;
use App\Services\StandardizationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProviderProductResource extends Resource
{
    protected static ?string $model = \App\Models\ProviderProduct::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Активации';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    public static function getNavigationLabel(): string
    {
        return 'Витрина провайдера';
    }

    public static function getNavigationBadge(): ?string
    {
        $shop = Filament::getTenant();
        if (! $shop) {
            return null;
        }

        return (string) static::getEloquentQuery()->count();
    }

    public static function getLabel(): string
    {
        return 'Товар провайдера';
    }

    public static function getPluralLabel(): string
    {
        return 'Витрина провайдера';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('is_active', true);
        $tenant = Filament::getTenant();

        if ($tenant instanceof \App\Models\LegalEntity) {
            $shops = $tenant->shops;
            
            // 1. Filter by allowed regions across all shops
            $allRegions = $shops->flatMap->allowed_regions->unique()->filter()->toArray();
            if (!empty($allRegions)) {
                $query->whereIn('region_id', function ($q) use ($allRegions) {
                    $q->select('id')
                        ->from('mapping_countries')
                        ->whereIn('code', $allRegions);
                });
            }

            // 2. Filter by allowed categories across all shops
            $allCategories = $shops->flatMap->allowed_categories->unique()->filter()->toArray();
            if (!empty($allCategories)) {
                $query->where(function ($q) use ($allCategories) {
                    foreach ($allCategories as $category) {
                        $q->orWhere('category', 'like', "%{$category}%");
                    }
                });
            }
        } elseif ($tenant instanceof Shop) {
            // Fallback for single shop tenant
            if ($tenant->allowed_regions && count($tenant->allowed_regions) > 0) {
                $query->whereIn('region_id', function ($q) use ($tenant) {
                    $q->select('id')
                        ->from('mapping_countries')
                        ->whereIn('code', $tenant->allowed_regions);
                });
            }

            if ($tenant->allowed_categories && count($tenant->allowed_categories) > 0) {
                $query->where(function ($q) use ($tenant) {
                    foreach ($tenant->allowed_categories as $category) {
                        $q->orWhere('category', 'like', "%{$category}%");
                    }
                });
            }
        }

        return $query;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Информация для активации')
                    ->components([
                        Grid::make(2)
                            ->components([
                                TextEntry::make('reward_type')
                                    ->label('Тип вознаграждения')
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('upc')
                                    ->label('UPC / Баркод')
                                ->getStateUsing(fn ($record) => $record->getUpcForShop(Filament::getTenant()->shops()->first()))
                                    ->copyable(),
                                TextEntry::make('activation_url')
                                    ->label('Ссылка на активацию')
                                    ->color('primary')
                                    ->url(fn ($state) => $state ? (str_starts_with($state, 'http') ? $state : "https://{$state}") : null),
                                TextEntry::make('redemption_instructions')
                                    ->label('Инструкция бренда')
                                    ->prose()
                                    ->getStateUsing(function ($record) {
                                        $text = $record->redemption_instructions;
                                        if (!$text) return null;
                                        
                                        // Remove provider links and emails
                                        $text = preg_replace('/[a-zA-Z0-9._%+-]+@(wildflow|ezpaypin)\.[a-z]{2,}/i', '[support]', $text);
                                        $text = preg_replace('/https?:\/\/(portal|api|www)\.(wildflow|ezpaypin)\.[a-z]{2,}[^\s]*/i', '[link]', $text);
                                        
                                        return $text;
                                    })
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $tenant = Filament::getTenant();
        $shop = ($tenant instanceof Shop && $tenant->getKey())
            ? (Shop::query()->find($tenant->getKey()) ?? $tenant)
            : $tenant;

        $shopHasWildflowProduct = function (\App\Models\ProviderProduct $record) use ($tenant): bool {
            if ($record->min_price && $record->max_price && $record->min_price != $record->max_price) {
                return false;
            }

            $vault = app(\App\Services\VaultTransitService::class);
            $skuBidx = $vault->computeBlindIndex($record->sku);
            $marketSkuBidx = !empty($record->market_sku) ? $vault->computeBlindIndex($record->market_sku) : null;

            return \App\Models\Product::where(function ($q) use ($tenant) {
                if ($tenant instanceof \App\Models\Shop) {
                    $q->where('shop_id', $tenant->id);
                } else {
                    $q->whereHas('shop', fn($sq) => $sq->where('legal_entity_id', $tenant->id));
                }
            })
            ->where(function ($q) use ($record, $skuBidx, $marketSkuBidx) {
                $q->where('products.sku', $record->sku)
                  ->orWhere('products.wildflow_catalog_sku_bidx', $skuBidx);
                
                if ($marketSkuBidx) {
                    $q->orWhere('products.sku', $record->market_sku)
                      ->orWhere('products.wildflow_catalog_sku_bidx', $marketSkuBidx);
                }
            })
            ->exists();
        };

        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                ImageColumn::make('brand_logo_url')
                    ->label('')
                    ->height(40)
                    ->width(40)
                    ->circular(),

                TextColumn::make('name')
                    ->label('Название товара')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn ($record) => 'ID: ' . strtoupper(substr(md5($record->sku), 0, 8)))
                    ->toggleable(),

                TextColumn::make('brand.name')
                    ->label('Бренд')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('purchase_price')
                    ->label('Ваша цена')
                    ->getStateUsing(function ($record) use ($tenant) {
                        $shop = $tenant instanceof \App\Models\LegalEntity ? $tenant->shops()->first() : $tenant;
                        if (!$shop) return '—';

                        $wf = \App\Models\WildflowCatalog::where('sku', $record->market_sku ?? $record->sku)->first();

                        if (!$wf) {
                            return number_format((float) $record->purchase_price, 2).' '.$record->currency;
                        }

                        // Для диапазонных карт — показываем диапазон закупочных цен
                        if ($wf->is_variable_price) {
                            $min = number_format($wf->min_purchase_price, 2);
                            $max = number_format($wf->max_purchase_price, 2);
                            return $min.'–'.$max.' '.$record->currency;
                        }

                        // Для фиксированных — обычная тарифная цена
                        return number_format($wf->getPurchasePriceForShop($shop), 2).' '.$record->currency;
                    })
                    ->sortable()
                    ->color('success')
                    ->weight('bold')
                    ->toggleable(),

                TextColumn::make('retail_price')
                    ->label('Номинал')
                    ->getStateUsing(function ($record) {
                        if ($record->min_price && $record->max_price && $record->min_price != $record->max_price) {
                            return number_format($record->min_price, 2).'–'.number_format($record->max_price, 2).' '.$record->currency;
                        }
                        return number_format((float) $record->retail_price, 2).' '.$record->currency;
                    })
                    ->sortable()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('region.name_ru')
                    ->label('Регион')
                    ->icon('heroicon-m-globe-alt')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(WildflowCatalogExporter::class)
                    ->label('Экспорт CSV')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->options(fn () => [
                        'tenant_id' => Filament::getTenant()?->id,
                    ]),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Бренд')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                \Filament\Tables\Filters\SelectFilter::make('region_id')
                    ->label('Регион')
                    ->multiple()
                    ->relationship('region', 'name_ru')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->flag} {$record->name_ru}")
                    ->searchable()
                    ->preload(),
            ], layout: \Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(2)
            ->actions([
                Action::make('add_to_catalog')
                    ->label(fn ($record) => $shopHasWildflowProduct($record) ? 'Пополнить сток' : 'В каталог')
                    ->icon(fn ($record) => $shopHasWildflowProduct($record) ? 'heroicon-m-archive-box-arrow-down' : 'heroicon-m-plus')
                    ->color(fn ($record) => $shopHasWildflowProduct($record) ? 'success' : 'primary')
                    ->button()
                    ->modalHeading(fn ($record) => $shopHasWildflowProduct($record) ? 'Пополнение стока' : 'Куда публиковать товар')
                    ->schema(function (\App\Models\ProviderProduct $record) use ($shopHasWildflowProduct) {
                        $wf = WildflowCatalog::where('sku', $record->market_sku ?? $record->sku)->first();
                        $isVariable = $wf?->is_variable_price ?? false;

                        $fields = [];

                        $fields[] = \Filament\Forms\Components\Select::make('shop_id')
                            ->label('Выберите магазин')
                            ->options(fn() => Filament::getTenant()->shops()->pluck('name', 'id'))
                            ->required()
                            ->live();

                        if ($isVariable) {
                            $min = (float) $record->min_price;
                            $max = (float) $record->max_price;

                            $fields[] = \Filament\Forms\Components\Placeholder::make('range_hint')
                                ->label('')
                                ->content(new HtmlString(
                                    '<div class="text-sm text-gray-500 dark:text-gray-400">'
                                    .'Это товар с открытым номиналом. Выберите номинал: от <strong>'
                                    .number_format($min, 2).'</strong> до <strong>'
                                    .number_format($max, 2).'</strong> '.e($record->currency).'.'
                                    .'</div>'
                                ));

                            $fields[] = \Filament\Forms\Components\TextInput::make('amount')
                                ->label('Номинал ('.e($record->currency).')')
                                ->numeric()
                                ->required()
                                ->minValue($min)
                                ->maxValue($max)
                                ->step(0.01)
                                ->default($min);
                        }

                        $fields[] = \Filament\Forms\Components\TextInput::make('count')
                            ->label('Количество ваучеров')
                            ->helperText('Укажите сколько кодов закупить и добавить на ваш склад.')
                            ->numeric()
                            ->default($shopHasWildflowProduct($record) ? 1 : 0)
                            ->minValue($shopHasWildflowProduct($record) ? 1 : 0)
                            ->required();

                        $fields[] = CheckboxList::make('sales_channels')
                            ->label('Каналы продаж')
                            ->options(fn($get) => SalesChannels::optionsForUi(Shop::find($get('shop_id'))))
                            ->descriptions(fn($get) => SalesChannels::descriptionsForUi(Shop::find($get('shop_id'))))
                            ->default(fn($get) => array_keys(SalesChannels::optionsForUi(Shop::find($get('shop_id')))))
                            ->columns(2)
                            ->bulkToggleable()
                            ->visible(fn($get) => $get('shop_id') !== null)
                            ->in(fn() => array_keys(SalesChannels::optionsForUi()))
                            ->required();

                        return $fields;
                    })
                    ->action(function (\App\Models\ProviderProduct $record, array $data) {
                        $shop = Shop::find($data['shop_id']);
                        $seller = auth()->user();
                        $selectedChannels = SalesChannels::normalizeSelection($data['sales_channels'] ?? []);
                        $count = (int) ($data['count'] ?? 0);
                        $amount = isset($data['amount']) ? (float) $data['amount'] : null;

                        // Run in background so UI doesn't block
                        try {
                            $job = new AddCatalogItemToShop(
                                $record->id,
                                $shop->id,
                                $seller->id,
                                $selectedChannels,
                                $count,
                                $amount
                            );
                            dispatch($job);

                            Notification::make()
                                ->title('Задача запущена')
                                ->body('Генерация карточки товара и отправка на каналы продаж выполняется в фоновом режиме. Вы получите уведомление по завершении.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Ошибка добавления')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('buy_once')
                    ->label('Купить разово')
                    ->icon('heroicon-m-shopping-cart')
                    ->color('warning')
                    ->button()
                    ->outlined()
                    ->modalHeading(fn ($record) => 'Разовая закупка: '.$record->name)
                    ->modalDescription('Будет создан ваучер, готовый к активации. Ссылка на активацию появится после оплаты.')
                    ->schema(function (\App\Models\ProviderProduct $record) {
                        $wf = WildflowCatalog::where('sku', $record->market_sku)->first();
                        $isVariable = $wf?->is_variable_price ?? false;
                        
                        $shops = Shop::where('is_active', true)->get()->pluck('name', 'id');
                        
                        $fields = [];

                        $fields[] = \Filament\Forms\Components\Select::make('shop_id')
                            ->label('Для магазина')
                            ->options($shops)
                            ->required()
                            ->default(array_key_first($shops->toArray()) ?? null);

                        if ($isVariable) {
                            $min = (float) $record->min_price;
                            $max = (float) $record->max_price;
                            $fields[] = Placeholder::make('range_hint')
                                ->label('')
                                ->content(new HtmlString(
                                    '<div class="text-sm text-gray-500 dark:text-gray-400">'
                                    .'Это карта с открытым номиналом. Введите сумму от <strong>'
                                    .number_format($min, 2).'</strong> до <strong>'
                                    .number_format($max, 2).'</strong> '.e($record->currency).'.'
                                    .'</div>'
                                ));

                            $fields[] = TextInput::make('amount')
                                ->label('Сумма номинала ('.$record->currency.')')
                                ->numeric()
                                ->required()
                                ->minValue($min)
                                ->maxValue($max)
                                ->step(0.01)
                                ->default($min)
                                ->helperText('Диапазон: '.number_format($min, 2).' – '.number_format($max, 2).' '.$record->currency);
                        } else {
                            $fields[] = Placeholder::make('price_hint')
                                ->label('Номинал')
                                ->content(number_format((float) $record->retail_price, 2).' '.$record->currency);
                        }

                        $fields[] = TextInput::make('quantity')
                            ->label('Количество')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(20);

                        return $fields;
                    })
                    ->action(function (\App\Models\ProviderProduct $record, array $data) {
                        $wf = WildflowCatalog::where('sku', $record->market_sku)->first();

                        if (!$wf) {
                            Notification::make()->title('Ошибка')->body('Товар не найден в каталоге Wildflow.')->danger()->send();
                            return;
                        }

                        $shopId = $data['shop_id'] ?? null;
                        $shop = Shop::find($shopId);

                        if (!$shop) {
                             Notification::make()->title('Ошибка')->body('Магазин не найден.')->danger()->send();
                             return;
                        }

                        $legalEntity = $shop->legalEntity;
                        if (!$legalEntity) {
                            Notification::make()->title('Ошибка')->body('Для магазина не настроено юрлицо.')->danger()->send();
                            return;
                        }

                        // 1. Calculate exact finance requirement
                        $isVariable = $wf->is_variable_price;
                        $nominalAmount = $isVariable ? (float) $data['amount'] : (float) $wf->retail_price;
                        
                        $percentageAdjustment = (float) (data_get($wf->data, 'data.percentage_of_buying_price', data_get($wf->data, 'percentage_of_buying_price', -2)));
                        $buyingPrice = $isVariable
                            ? (float) ($nominalAmount * (1 + ($percentageAdjustment / 100)))
                            : (float) $wf->purchase_price;

                        $currency = $wf->currency_code;
                        $financeService = app(FinanceService::class);
                        $rate = $financeService->getRate($currency);

                        $buyingPriceRub = $buyingPrice * $rate;
                        $nominalPriceRub = $nominalAmount * $rate;

                        $standardizer = app(StandardizationService::class);
                        $tariffPriceRub = $standardizer->getPurchasePriceForShop($buyingPriceRub, $nominalPriceRub, $shop);

                        $quantity = (int) ($data['quantity'] ?? 1);
                        $totalCostRub = $tariffPriceRub * $quantity;

                        // 🛡️ UPSTREAM AVAILABILITY SANITY CHECK
                        try {
                            $vault = app(\App\Services\VaultTransitService::class);
                            $serviceSku = $vault->decrypt($wf->service_sku);
                            
                            $wfService = new \App\Services\WildflowService();
                            $availability = $wfService->checkAvailability(
                                service_sku: (string)$serviceSku,
                                quantity: $quantity,
                                price: $isVariable ? (float)$nominalAmount : null
                            );

                              if (!$availability['available']) {
                                 // 📊 INTELLIGENCE HARVESTING: Convert the 'Missed Opportunity' into Auditable Data
                                 try {
                                     app(\App\Services\LedgerService::class)->record($shop, 'PROVIDER_STOCK_DEFICIT', $wf, [
                                         'sku' => $wf?->sku,
                                         'nominal_amount' => $nominalAmount ?? null,
                                         'requested_quantity' => $quantity ?? 1,
                                         'trigger' => 'buy_once_pre_check'
                                     ]);
                                 } catch (\Exception $ledgerEx) {
                                     \Log::warning('Failed to record stock deficit into ledger', ['err' => $ledgerEx->getMessage()]);
                                 }

                                 Notification::make()
                                     ->title('🚫 Нет в наличии')
                                     ->body('Товара временно нет в наличии у поставщика. Он автоматически скрыт.')
                                     ->danger()
                                     ->persistent()
                                     ->send();
                                 return;
                             }
                        } catch (\Exception $apiEx) {
                             \Log::warning("Availability check error: " . $apiEx->getMessage());
                             // We allow failure tolerance OR block? Blocking is safer for direct purchase.
                             Notification::make()->title('⚠️ Ошибка связи')->body('Не удалось проверить наличие товара у поставщика. Повторите попытку позже.')->warning()->send();
                             return;
                        }

                        // Atomic Check & Charge
                        try {
                            DB::beginTransaction();

                            $legalEntity->refresh();
                            if ($legalEntity->available_balance < $totalCostRub) {
                                throw new \Exception("Недостаточно средств. Требуется " . number_format($totalCostRub, 2) . " RUB, доступно " . number_format($legalEntity->available_balance, 2) . " RUB.");
                            }

                            // 2. Direct Finance Capture
                            $legalEntity->decrement('available_balance', $totalCostRub);

                            // 3. Create Mock Order
                            $orderReference = 'DP-' . strtoupper(Str::random(10)); // Direct Purchase
                            
                            $order = Order::create([
                                'order_id'     => $orderReference,
                                'uuid'         => Str::uuid()->toString(),
                                'status'       => 'PROCESSING',
                                'sub_status'   => 'DIRECT_PURCHASE',
                                'shop_id'      => $shop->id,
                                'progress_id'  => 2, // В обработке
                                'sales_channel'=> 'manual',
                                'comment'      => 'Прямая разовая закупка от партнера.',
                            ]);

                            // Record Finance Capture Ledger
                            app(\App\Services\LedgerService::class)->record($shop, 'FINANCE_CAPTURE', $order, [
                                'amount_rub'  => $totalCostRub,
                                'reference'   => $orderReference,
                                'description' => 'Списание за разовую закупку товара ×' . $quantity,
                            ]);

                            $voucherKeys = [];
                            $masterWarehouse = \App\Models\Warehouse::where('shop_id', $shop->id)->where('is_main', true)->first()
                                ?? \App\Models\Warehouse::where('shop_id', $shop->id)->first();

                            // 4. Create Vouchers
                            for ($i = 0; $i < $quantity; $i++) {
                                $voucherToken = GenerateSecureCode::generate($shop->voucher_prefix);
                                
                                // Create OrderItem directly bound to Catalog SKU
                                $item = OrderItems::create([
                                    'key' => $voucherToken,
                                    'uuid' => Str::uuid()->toString(),
                                    'order_id' => $order->id,
                                    'activate_till' => now()->addYear()->format('Y-m-d'),
                                    'sku' => $wf->sku, // 🚀 Используем SKU каталога напрямую!
                                    'nominal_amount' => $nominalAmount,
                                    'nominal_currency' => $currency,
                                    'count' => 1,
                                    'price_rub' => $tariffPriceRub * 100,
                                    'price_try' => 0,
                                    'type_form_id' => 2, // Standard
                                    'purchase_status' => 'pending',
                                ]);

                                if ($masterWarehouse) {
                                    // Register Inventory instance as immediately sold
                                    ProductInventory::create([
                                        'shop_id' => $shop->id,
                                        'warehouse_id' => $masterWarehouse->id,
                                        'sku' => $wf->sku,
                                        'nominal_amount' => $nominalAmount,
                                        'nominal_currency' => $currency,
                                        'voucher' => $voucherToken,
                                        'is_used' => true,
                                        'order_item_id' => $item->id,
                                        'status' => 'sold',
                                    ]);
                                }

                                $redeemUrl = route('redeem.code', ['code' => $voucherToken]);
                                $voucherKeys[] = [
                                    'token' => $voucherToken,
                                    'url'   => $redeemUrl
                                ];
                            }

                            // Sovereign audit log
                            app(\App\Services\LedgerService::class)->recordGlobal('MANUAL_VOUCHER_ISSUE', $order, [
                                'sku'        => $wf->sku,
                                'count'      => $quantity,
                                'total_rub'  => $totalCostRub,
                                'by_user'    => auth()->id(),
                            ]);

                            DB::commit();

                            // Build nice HTML list of redeem links
                            $linksHtml = '<div class="mt-2 space-y-2">';
                            foreach ($voucherKeys as $k) {
                                $linksHtml .= '<div class="flex items-center justify-between p-2 border rounded bg-gray-50 dark:bg-zinc-900 dark:border-zinc-700">'
                                    . '<code class="font-mono font-bold text-primary-600 select-all">' . e($k['token']) . '</code>'
                                    . '<a href="' . e($k['url']) . '" target="_blank" class="ml-4 text-xs text-blue-600 underline font-medium hover:text-blue-800">Перейти к активации &raquo;</a>'
                                    . '</div>';
                            }
                            $linksHtml .= '</div>';

                            Notification::make()
                                ->title('✅ Закупка успешно оформлена')
                                ->body(new HtmlString(
                                    "Списано <strong>" . number_format($totalCostRub, 2) . " RUB</strong> с баланса.<br/>"
                                    . "Создано ваучеров: " . $quantity . ".<br/>"
                                    . $linksHtml
                                ))
                                ->success()
                                ->persistent()
                                ->send();

                        } catch (\Exception $e) {
                            DB::rollBack();
                            \Log::error('Manual Purchase Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                            
                            Notification::make()
                                ->title('❌ Ошибка')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                ViewAction::make()
                    ->label('Инфо')
                    ->icon('heroicon-m-information-circle')
                    ->button()
                    ->outlined(),
            ])
            ->bulkActions([])
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->recordAction(ViewAction::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ProviderProductResource\Pages\ListProviderProducts::route('/'),
        ];
    }
}
