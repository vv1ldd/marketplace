<?php

namespace App\Filament\Resources\B2B\Pages;

use App\Filament\Resources\B2B\DemandGapResource;
use App\Models\DemandGap;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListDemandGaps extends ListRecords
{
    protected static string $resource = DemandGapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Custom action to recalculate demand gaps in real-time!
            \Filament\Actions\Action::make('recalculate')
                ->label('Пересчитать спрос')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function () {
                    app(\App\Services\DemandGapEngineService::class)->recalculateGaps();
                }),
            \Filament\Actions\Action::make('autoCases')
                ->label('Создать auto-cases')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    app(\App\Services\DemandGapEngineService::class)->recalculateGaps();
                    $cases = app(\App\Services\OpportunityLifecycleService::class)->autoOpenCases(80.0);

                    \Filament\Notifications\Notification::make()
                        ->title('Auto-cases созданы')
                        ->body("Создано {$cases->count()} кейсов с Opportunity Score >= 80.")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'All' => Tab::make('Все')
                ->badge(fn() => DemandGap::count()),

            'Critical' => Tab::make('Критический дефицит')
                ->badge(fn() => DemandGap::where('priority_label', 'critical')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn($query) => $query->where('priority_label', 'critical')),

            'High' => Tab::make('Высокий дефицит')
                ->badge(fn() => DemandGap::where('priority_label', 'high')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn($query) => $query->where('priority_label', 'high')),

            'Medium' => Tab::make('Средний дефицит')
                ->badge(fn() => DemandGap::where('priority_label', 'medium')->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn($query) => $query->where('priority_label', 'medium')),

            'Low' => Tab::make('Низкий дефицит')
                ->badge(fn() => DemandGap::where('priority_label', 'low')->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn($query) => $query->where('priority_label', 'low')),
        ];
    }
}
