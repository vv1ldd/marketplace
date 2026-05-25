<?php

namespace App\Filament\Resources\B2B\Pages;

use App\Filament\Resources\B2B\OpportunityCaseResource;
use App\Models\OpportunityCase;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListOpportunityCases extends ListRecords
{
    protected static string $resource = OpportunityCaseResource::class;

    public function getTabs(): array
    {
        return [
            'Open' => Tab::make('Открытые')
                ->badge(fn () => OpportunityCase::where('status', OpportunityCase::STATUS_OPEN)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn ($query) => $query->where('status', OpportunityCase::STATUS_OPEN)),

            'InProgress' => Tab::make('В работе')
                ->badge(fn () => OpportunityCase::where('status', OpportunityCase::STATUS_IN_PROGRESS)->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn ($query) => $query->where('status', OpportunityCase::STATUS_IN_PROGRESS)),

            'Resolved' => Tab::make('Закрытые')
                ->badge(fn () => OpportunityCase::where('status', OpportunityCase::STATUS_RESOLVED)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn ($query) => $query->where('status', OpportunityCase::STATUS_RESOLVED)),

            'All' => Tab::make('Все')
                ->badge(fn () => OpportunityCase::count()),
        ];
    }
}
