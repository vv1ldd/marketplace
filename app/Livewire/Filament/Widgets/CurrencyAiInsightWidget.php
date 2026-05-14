<?php

namespace App\Livewire\Filament\Widgets;

use App\Models\Currency;
use App\Services\Ai\LedgerAnalystService;
use Livewire\Component;

class CurrencyAiInsightWidget extends Component
{
    public ?Currency $record = null;
    public ?string $report = null;
    public bool $loading = false;

    public function mount(Currency $record)
    {
        $this->record = $record;
    }

    public function generateReport()
    {
        $this->loading = true;
        $this->report = null;
        
        // Use the LedgerAnalystService to get AI insight
        $this->report = app(LedgerAnalystService::class)->analyzeCurrencyNode($this->record);
        
        $this->loading = false;
    }

    public function render()
    {
        return view('livewire.filament.widgets.currency-ai-insight-widget');
    }
}
