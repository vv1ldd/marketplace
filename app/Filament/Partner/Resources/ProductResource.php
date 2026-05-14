<?php

namespace App\Filament\Partner\Resources;

use App\Models\Product;
use App\Models\Shop;
use App\Support\SalesChannels;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ProductResource extends Resource
{
    use \App\Filament\Concerns\HasYandexCategoryParameters;

    protected static ?string $model = Product::class;

    protected static bool $isScopedToTenant = true;

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
    
    /**
     * 🛡️ OVERRIDE: Disable automatic model-ownership interception on Product creation.
     * Product has a HasOneThrough relationship to LegalEntity, which does not support ->save().
     * We handle explicit owner assignment during our distinct workflows manually.
     */
    public static function observeTenancyModelCreation(\Filament\Panel $panel): void
    {
        // Do nothing: Disable automated creation hooks that crash on HasOneThrough.
    }

    public static function getNavigationLabel(): string
    {
        return 'Мой каталог';
    }

    public static function getLabel(): string
    {
        return 'Товар в каталоге';
    }

    public static function getPluralLabel(): string
    {
        return 'Мой каталог';
    }



    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Forms\Components\Placeholder::make('global_product_warning')
                ->label('')
                ->content(new HtmlString('<div class="p-4 bg-blue-50 border border-blue-200 rounded-xl text-blue-700 text-sm flex items-center gap-3">
                    <span class="text-2xl">ℹ️</span>
                    <div>
                        <strong>Режим Digital Twin:</strong> Вы редактируете локальную копию карточки. 
                        Все изменения будут синхронизированы с Маркетом в фоновом режиме.
                    </div>
                </div>'))
                ->columnSpanFull(),

            Grid::make(3)->schema([
                // LEFT COLUMN: SETTINGS
                Grid::make(1)
                    ->columnSpan(2)
                    ->schema([
                        Section::make('Каналы продаж')
                            ->description('Выберите площадки, на которых должен продаваться этот товар')
                            ->icon('heroicon-o-share')
                            ->schema([
                                CheckboxList::make('active_channels')
                                    ->label('')
                                    ->options(SalesChannels::optionsForUi())
                                    ->descriptions(SalesChannels::descriptionsForUi())
                                    ->columns(2)
                                    ->loadStateFromRelationshipsUsing(function ($component, $record) {
                                        $component->state(
                                            $record->salesChannels()
                                                ->where('shop_id', $record->shop_id)
                                                ->where('is_enabled', true)
                                                ->pluck('channel')
                                                ->toArray()
                                        );
                                    })
                                    ->saveRelationshipsUsing(function ($component, $record, $state) {
                                        $shopId = $record->shop_id;
                                        $allKnown = array_keys(SalesChannels::optionsForUi());

                                        foreach ($allKnown as $channel) {
                                            \App\Models\ProductSalesChannel::updateOrCreate(
                                                [
                                                    'product_id' => $record->id,
                                                    'shop_id' => $shopId,
                                                    'channel' => $channel,
                                                ],
                                                [
                                                    'is_enabled' => in_array($channel, $state),
                                                ]
                                            );
                                        }
                                    }),
                            ]),

                        Section::make('Визуальный контент')
                            ->description('Управляйте тем, что покупатель увидит в первую очередь')
                            ->schema([
                                Grid::make(2)->schema([
                                    FileUpload::make('image')
                                        ->label('Главная обложка (Market)')
                                        ->image()
                                        ->disk('root_public')
                                        ->imageEditor()
                                        ->columnSpan(1),

                                    FileUpload::make('videos')
                                        ->label('Vertical Video')
                                        ->disk('public')
                                        ->directory('videos')
                                        ->multiple()
                                        ->maxFiles(1)
                                        ->acceptedFileTypes(['video/mp4', 'video/quicktime'])
                                        ->columnSpan(1),
                                ]),

                                FileUpload::make('pictures')
                                    ->label('Галерея изображений (Брендированные варианты)')
                                    ->image()
                                    ->multiple(),
                            ]),

                        Section::make('Описание и Характеристики')
                            ->collapsible()
                            ->schema([
                                TextInput::make('name')
                                    ->label('Название товара (YM Name)')
                                    ->live(onBlur: true)
                                    ->required(),

                                RichEditor::make('description')
                                    ->label('Маркетинговое описание')
                                    ->helperText('Будет отправлено на Маркет (рекомендуется использовать шаблон)')
                                    ->columnSpanFull(),

                                Grid::make(2)->schema([
                                    TextInput::make('sku')
                                        ->label('Offer ID (SKU)')
                                        ->disabled(),
                                    TextInput::make('barcode')
                                        ->label('Barcode (EAN)'),
                                ]),
                            ]),

                        Section::make('Характеристики Яндекса (Параметры)')
                            ->description('Эти данные определяют фильтры и поиск в приложении Яндекса.')
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('data.platform')
                                        ->label('Платформа (24915630)')
                                        ->options([
                                            'PlayStation' => 'PlayStation',
                                            'Xbox' => 'Xbox',
                                            'Nintendo Switch' => 'Nintendo Switch',
                                            'ПК' => 'ПК',
                                            'мобильное устройство' => 'Мобильное устройство',
                                        ]),
                                    TextInput::make('data.region')
                                        ->label('Регион (37919810)')
                                        ->placeholder('Напр: США, Турция'),
                                    TextInput::make('data.service')
                                        ->label('Сервис (50882075)')
                                        ->placeholder('Напр: Steam, iTunes'),
                                    TextInput::make('data.nominal')
                                        ->label('Номинал (37821410)')
                                        ->numeric(),
                                    Select::make('data.type')
                                        ->label('Формат (37693330)')
                                        ->options([
                                            'электронный ключ' => 'Электронный ключ',
                                            'подписка' => 'Подписка',
                                        ]),
                                ]),
                            ]),

                        Section::make('Ценообразование')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('price_rub')
                                        ->label('Ваша базовая цена (₽)')
                                        ->numeric()
                                        ->prefix('₽')
                                        ->formatStateUsing(fn ($state) => $state / 100)
                                        ->dehydrateStateUsing(fn ($state) => $state * 100)
                                        ->live(onBlur: true)
                                        ->required(),
                                    TextInput::make('old_price_rub')
                                        ->label('Цена до скидки (₽)')
                                        ->numeric()
                                        ->prefix('₽')
                                        ->formatStateUsing(fn ($state) => $state / 100)
                                        ->dehydrateStateUsing(fn ($state) => $state * 100),
                                ]),

                                Grid::make(3)->schema([
                                    Html::make(fn ($record) => $record ? new HtmlString('<div class="text-sm text-gray-500">Итого на Маркете</div><div class="text-2xl font-black text-primary-600">'.number_format(app(\App\Services\FinanceService::class)->getShopFinalPrice($record, $record->shop) / 100, 2).' ₽</div>') : ''),
                                    Html::make(fn ($record) => $record ? new HtmlString('<div class="text-sm text-gray-500">Закупка</div><div class="font-bold">'.$record->purchase_price.' '.$record->purchase_currency.'</div>') : ''),
                                    Html::make(fn ($record) => $record ? new HtmlString('<div class="text-sm text-gray-500">Ваша маржа</div><div class="font-bold text-success-600">'.number_format((app(\App\Services\FinanceService::class)->getShopFinalPrice($record, $record->shop) - $record->purchase_price_rub) / 100, 2).' ₽</div>') : ''),
                                ]),
                            ]),

                        Section::make('Управление запасами')
                            ->description('Автоматизация стока и уведомления')
                            ->icon('heroicon-o-cube')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('low_stock_notification_threshold')
                                        ->label('Порог уведомления (Email/TG)')
                                        ->numeric()
                                        ->default(10)
                                        ->helperText('Прислать алерт, когда остаток меньше этого числа'),
                                    
                                    \Filament\Forms\Components\Toggle::make('auto_replenish_enabled')
                                        ->label('Автопополнение стока')
                                        ->helperText('Автоматически докупать товар, если баланс позволяет')
                                        ->live(),
                                ]),

                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('auto_replenish_threshold')
                                            ->label('Порог автопополнения')
                                            ->numeric()
                                            ->default(2)
                                            ->helperText('Запустить закупку, когда остаток <= этому числу'),
                                        
                                        TextInput::make('auto_replenish_quantity')
                                            ->label('Количество для закупа')
                                            ->numeric()
                                            ->default(1)
                                            ->helperText('Сколько штук покупать за один раз'),
                                    ])
                                    ->visible(fn ($get) => $get('auto_replenish_enabled')),
                            ]),
                    ]),

                // RIGHT COLUMN: LIVE PREVIEW
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Инструменты Digital Twin')
                            ->schema([
                                \Filament\Schemas\Components\Actions::make([
                                    \Filament\Actions\Action::make('sync_reality')
                                        ->label('Воссоздать реальность Яндекса')
                                        ->icon('heroicon-m-arrow-path')
                                        ->color('warning')
                                        ->requiresConfirmation()
                                        ->action(function (Product $record) {
                                            $offer = $record->toYmOffer(70301474); // Имитируем экспорт

                                            $newData = $record->data;

                                            // Маппим параметры обратно в data
                                            foreach ($offer['parameter_values'] as $param) {
                                                if ($param['parameter_id'] == 24915630) {
                                                    $newData['platform'] = $param['value'];
                                                }
                                                if ($param['parameter_id'] == 37919810) {
                                                    $newData['region'] = $param['value'];
                                                }
                                                if ($param['parameter_id'] == 50882075) {
                                                    $newData['service'] = $param['value'];
                                                }
                                                if ($param['parameter_id'] == 37821410) {
                                                    $newData['nominal'] = $param['value'];
                                                }
                                            }

                                            $record->update([
                                                'description' => $offer['description'],
                                                'data' => $newData,
                                            ]);

                                            Notification::make()->title('Реальность Яндекса воссоздана!')->success()->send();
                                        }),

                                    \Filament\Actions\Action::make('generate_video')
                                        ->label('Обновить Видео')
                                        ->icon('heroicon-m-video-camera')
                                        ->color('success')
                                        ->action(function (Product $record) {
                                            $service = app(\App\Services\VideoInstructionService::class);
                                            $videoUrl = $service->generateForProduct($record);
                                            if ($videoUrl) {
                                                $record->update(['videos' => [$videoUrl]]);
                                                Notification::make()->title('Видео создано!')->success()->send();
                                            }
                                        }),
                                ])->fullWidth(),
                            ]),

                        Section::make('Предпросмотр (Mobile)')
                            ->description('Так товар видят покупатели')
                            ->extraAttributes(['class' => 'sticky top-24'])
                            ->schema([
                                \Filament\Schemas\Components\View::make('filament.components.product-preview-card'),
                            ]),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->square()
                    ->size(80)
                    ->tooltip('Сгенерированная карточка товара')
                    ->extraImgAttributes(['class' => 'rounded-lg shadow-sm border border-gray-100']),
                TextColumn::make('brand.name')
                    ->label('Бренд')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('sku')
                    ->label(__('admin.products.sku'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('name')
                    ->label(__('admin.products.name'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('catalogCategory.name')
                    ->label(__('admin.products.fields.category'))
                    ->badge()
                    ->color('success')
                    ->toggleable(),
                TextColumn::make('salesChannels')
                    ->label('Площадки')
                    ->getStateUsing(function (Product $record) {
                        $shopId = $record->shop_id;
                        if (!$shopId) return null;
                        
                        $channels = \App\Support\SalesChannels::all();
                        $enabledChannels = $record->salesChannels()
                            ->where('shop_id', $shopId)
                            ->where('is_enabled', true)
                            ->pluck('channel')
                            ->toArray();

                        if (empty($enabledChannels)) {
                            return '—';
                        }

                        $html = '<div class="flex items-center gap-2">';
                        foreach ($channels as $key => $meta) {
                            if (!($meta['enabled'] ?? false)) continue;
                            
                            $isActive = in_array($key, $enabledChannels);
                            $opacity = $isActive ? 'opacity-100' : 'opacity-20 grayscale';
                            $title = $meta['label'] ?? $key;
                            $icon = $meta['icon'] ?? '🌐';
                            
                            $html .= "<span title=\"{$title}\" class=\"text-xl {$opacity} cursor-help transition-all hover:scale-110\">{$icon}</span>";
                        }
                        $html .= '</div>';
                        
                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->searchable(false)
                    ->sortable(false),
                TextColumn::make('selling_price')
                    ->label('Ваша цена')
                    ->money('RUB', divideBy: 100)
                    ->getStateUsing(function (Product $record) {
                        return app(\App\Services\FinanceService::class)->getShopFinalPrice($record, $record->shop);
                    })
                    ->color('success')
                    ->weight('bold'),
                TextColumn::make('margin')
                    ->label('Маржа')
                    ->money('RUB', divideBy: 100)
                    ->getStateUsing(function (Product $record) {
                        $finalPrice = app(\App\Services\FinanceService::class)->getShopFinalPrice($record, $record->shop);

                        return $finalPrice - $record->purchase_price_rub;
                    })
                    ->color('gray'),
                TextColumn::make('total_stock')
                    ->label('Остаток')
                    ->getStateUsing(fn ($record) => $record->stocks()->sum('count'))
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('origin')
                    ->label('Источник')
                    ->getStateUsing(fn ($record) => $record->catalog_id === null ? 'Витрина' : 'Свой / Яндекс')
                    ->badge()
                    ->color(fn ($state) => $state === 'Витрина' ? 'info' : 'warning'),
                TextColumn::make('ym_status')
                    ->label('Статус на Яндексе')
                    ->state(fn ($record) => ! empty($record->ym_errors) ? 'Ошибка' : 'Ок')
                    ->badge()
                    ->color(fn ($record) => ! empty($record->ym_errors) ? 'danger' : 'success')
                    ->tooltip(fn ($record) => collect($record->ym_errors)->pluck('message')->implode('; ')),
            ])
            ->recordActions([
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
                    ->visible(fn (Product $record) => $record->image !== null),

                Action::make('regenerate_card')
                    ->label('Обновить')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Product $record) {
                        $shop = $record->shop;
                        $catalogItem = \App\Models\WildflowCatalog::find($record->catalog_id);

                        if (! $catalogItem || ! $shop instanceof Shop) {
                            Notification::make()->title('Невозможно обновить')->body('У этого товара нет связи с каталогом провайдера.')->danger()->send();

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


                Action::make('archive')
                    ->label('В архив')
                    ->icon('heroicon-m-archive-box')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Product $record) {
                        $record->update(['is_active' => false]);
                        Notification::make()->title('Товар перенесен в архив')->success()->send();
                    })
                    ->visible(fn (Product $record) => $record->is_active),

                Action::make('restore')
                    ->label('Восстановить')
                    ->icon('heroicon-m-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Product $record) {
                        $record->update(['is_active' => true]);
                        Notification::make()->title('Товар восстановлен из архива')->success()->send();
                    })
                    ->visible(fn (Product $record) => ! $record->is_active),

                Action::make('send_to_market')
                    ->label('В Маркет')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('success')
                    ->action(function (Product $record) {
                        $shop = $record->shop;

                        // 🎨 Авто-генерация брендированной карточки перед отправкой
                        if ($record->catalog_id) {
                            $catalogItem = \App\Models\WildflowCatalog::find($record->catalog_id);
                            if ($catalogItem) {
                                try {
                                    $cardService = app(\App\Services\CardImageService::class);
                                    $kit = $cardService->generateForCatalogItem($catalogItem, $shop);
                                    if ($mainImage = ($kit['images']['main'] ?? null)) {
                                        $record->update([
                                            'image' => $mainImage,
                                            'pictures' => $kit['images'] ?? [],
                                        ]);
                                    }
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::warning('Auto-card generation failed during send_to_market', ['error' => $e->getMessage()]);
                                }
                            }
                        }

                        $service = new \App\Http\Services\YmService($shop);
                        $categoryId = (int) ($shop->ym_category_id ?? \App\Models\Settings::get('YM_CATEGORY_ID', 70301474));
                        try {
                            $service->offerMappingsUpdate([['offer' => $record->toYmOffer($categoryId, $shop->id)]]);
                            Notification::make()->title('Товар обновлен и отправлен на Маркет')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Ошибка')->body($e->getMessage())->danger()->send();
                        }
                    })
                    ->visible(fn (Product $record) => $record->is_active),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('salesChannels')
                    ->label('Площадки')
                    ->options(\App\Support\SalesChannels::optionsForUi())
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        
                        $tenant = Filament::getTenant();
                        return $query->whereHas('salesChannels', function ($q) use ($tenant, $data) {
                            $q->whereHas('shop', fn($sq) => $sq->where('legal_entity_id', $tenant->id))
                              ->where('is_enabled', true)
                              ->where('channel', $data['value']);
                        });
                    }),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('publish_to_channel')
                        ->label('Выгрузить на площадку...')
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->color('success')
                        ->form([
                            \Filament\Forms\Components\Select::make('channel')
                                ->label('Выберите площадку')
                                ->options(\App\Support\SalesChannels::optionsForUi())
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $channel = $data['channel'];
                            
                            foreach ($records as $record) {
                                \App\Models\ProductSalesChannel::updateOrCreate(
                                    [
                                        'product_id' => $record->id,
                                        'shop_id' => $record->shop_id,
                                        'channel' => $channel,
                                    ],
                                    [
                                        'is_enabled' => true,
                                    ]
                                );
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Товары успешно отправлены на площадку')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                        
                    \Filament\Actions\BulkAction::make('remove_from_channel')
                        ->label('Снять с площадки...')
                        ->icon('heroicon-o-archive-box-x-mark')
                        ->color('danger')
                        ->form([
                            \Filament\Forms\Components\Select::make('channel')
                                ->label('Выберите площадку')
                                ->options(\App\Support\SalesChannels::optionsForUi())
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $channel = $data['channel'];
                            
                            foreach ($records as $record) {
                                \App\Models\ProductSalesChannel::updateOrCreate(
                                    [
                                        'product_id' => $record->id,
                                        'shop_id' => $record->shop_id,
                                        'channel' => $channel,
                                    ],
                                    [
                                        'is_enabled' => false,
                                    ]
                                );
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Товары успешно сняты с площадки')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Partner\Resources\ProductResource\RelationManagers\StocksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ProductResource\Pages\ListProducts::route('/'),
            'edit' => ProductResource\Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            \App\Filament\Partner\Resources\ProductResource\Widgets\ProductStatsOverview::class,
        ];
    }
}
