<?php

namespace App\Filament\Resources\B2B\Pages;

use App\Filament\Resources\B2B\CatalogSearchLogResource;
use App\Models\CatalogSearchLog;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListCatalogSearchLogs extends ListRecords
{
    protected static string $resource = CatalogSearchLogResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\SearchUnmetDemandWidget::class,
            \App\Filament\Widgets\PopularSearchQueriesWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'All' => Tab::make('Все')
                ->badge(fn() => CatalogSearchLog::count()),

            'UnmetDemand' => Tab::make('Упущенный спрос')
                ->badge(fn() => CatalogSearchLog::where('results_count', 0)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn($query) => $query->where('results_count', 0)),

            'Storefront' => Tab::make('Сайт')
                ->badge(fn() => CatalogSearchLog::where('source', 'storefront')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn($query) => $query->where('source', 'storefront')),

            'LlmRetrieval' => Tab::make('Поиск ИИ')
                ->badge(fn() => CatalogSearchLog::where('source', 'llm_retrieval')->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn($query) => $query->where('source', 'llm_retrieval')),
        ];
    }
}
