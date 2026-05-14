<?php

namespace App\Filament\Concerns;

use App\Http\Services\YmService;
use App\Models\Category;
use App\Models\Shop;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

trait HasYandexCategoryParameters
{
    public static function getCategoryFields(): array
    {
        return [
            self::getCategorySelectorField(),
            self::getCategoryParametersSection(),
        ];
    }

    public static function getCategorySelectorField(): Select
    {
        return Select::make('category_id')
            ->label(__('admin.products.fields.category'))
            ->relationship('catalogCategory', 'name')
            ->searchable()
            ->preload()
            ->live()
            ->afterStateUpdated(function ($state, $set) {
                if (! $state) {
                    return;
                }
                $category = Category::find($state);
                if ($category && empty($category->parameters_schema)) {
                    try {
                        $shop = Filament::getTenant() ?? Shop::where('is_active', true)->whereNotNull('api_key')->first();
                        if ($shop) {
                            $service = new YmService($shop);
                            $params = $service->getCategoryParameters($category->ym_id);
                            $category->update([
                                'parameters_schema' => $params,
                                'parameters_fetched_at' => now(),
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Silently fail
                    }
                }
            })
            ->required();
    }

    public static function getCategoryParametersSection(): Section
    {
        return Section::make('Характеристики категории')
            ->description('Дополнительные поля, требуемые Яндекс.Маркетом для этой категории.')
            ->collapsed(fn (Get $get) => empty(Category::find($get('category_id'))?->parameters_schema))
            ->hidden(fn (Get $get) => empty(Category::find($get('category_id'))?->parameters_schema))
            ->schema(fn (Get $get) => self::getCategoryParametersSchema($get));
    }

    public static function getCategoryParametersSchema(Get $get): array
    {
        $categoryId = $get('category_id');
        if (! $categoryId) {
            return [];
        }

        $category = Category::find($categoryId);
        if (! $category || empty($category->parameters_schema)) {
            return [];
        }

        $fields = [];
        foreach ($category->parameters_schema as $param) {
            $name = "params.{$param['id']}";

            $unitLabel = '';
            if (isset($param['unit']['units']) && isset($param['unit']['defaultUnitId'])) {
                $defaultUnit = collect($param['unit']['units'])->firstWhere('id', $param['unit']['defaultUnitId']);
                if ($defaultUnit) {
                    $unitLabel = " ({$defaultUnit['name']})";
                }
            }
            $label = $param['name'].$unitLabel;

            $field = match ($param['type'] ?? '') {
                'ENUM' => Select::make($name)
                    ->label($label)
                    ->options(collect($param['values'] ?? [])->mapWithKeys(function ($v, $k) {
                        if (is_array($v) && isset($v['id'], $v['value'])) {
                            return [(string) $v['id'] => (string) $v['value']];
                        }
                        if (is_string($v) || is_numeric($v)) {
                            return [(string) $k => (string) $v];
                        }

                        return [];
                    })->filter(fn ($v) => $v !== null && $v !== '')->toArray())
                    ->searchable(),
                'NUMERIC' => TextInput::make($name)
                    ->label($label)
                    ->numeric(),
                default => TextInput::make($name)
                    ->label($label),
            };

            if ($param['required'] ?? false) {
                $field->required();
            }

            $fields[] = $field;
        }

        return [
            Grid::make(3)->schema($fields),
        ];
    }
}
