<?php

namespace App\Filament\Resources\B2B\Pages;

use App\Filament\Resources\B2B\QueryNormalizationSuggestionResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use App\Models\QueryNormalizationSuggestion;

class ListQueryNormalizationSuggestions extends ListRecords
{
    protected static string $resource = QueryNormalizationSuggestionResource::class;

    public function getTabs(): array
    {
        return [
            'Pending' => Tab::make('Ожидают')
                ->badge(fn() => QueryNormalizationSuggestion::where('status', 'pending')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn($query) => $query->where('status', 'pending')),

            'Approved' => Tab::make('Утвержденные')
                ->badge(fn() => QueryNormalizationSuggestion::where('status', 'approved')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn($query) => $query->where('status', 'approved')),

            'Rejected' => Tab::make('Отклоненные')
                ->badge(fn() => QueryNormalizationSuggestion::where('status', 'rejected')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn($query) => $query->where('status', 'rejected')),

            'All' => Tab::make('Все')
                ->badge(fn() => QueryNormalizationSuggestion::count())
                ->modifyQueryUsing(fn($query) => $query),
        ];
    }
}
