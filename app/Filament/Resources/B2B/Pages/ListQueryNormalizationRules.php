<?php

namespace App\Filament\Resources\B2B\Pages;

use App\Filament\Resources\B2B\QueryNormalizationRuleResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListQueryNormalizationRules extends ListRecords
{
    protected static string $resource = QueryNormalizationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Добавить правило'),
        ];
    }
}
