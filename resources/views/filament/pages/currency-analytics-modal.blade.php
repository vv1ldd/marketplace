<div class="p-6 space-y-8">
    @livewire(\App\Filament\Widgets\CurrencyTruthChart::class, ['record' => $record])
    
    <div class="border-t border-gray-100 dark:border-gray-800 pt-8">
        @livewire(\App\Livewire\Filament\Widgets\CurrencyEvidenceWidget::class, ['record' => $record])
    </div>
</div>
