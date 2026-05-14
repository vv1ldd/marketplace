<?php

namespace App\Livewire\Filament\Widgets;

use App\Models\Currency;
use App\Models\CurrencyTelemetryEvent;
use Livewire\Component;

class CurrencyEvidenceWidget extends Component
{
    public ?Currency $record = null;

    public function render()
    {
        $lastEvent = CurrencyTelemetryEvent::where('currency_code', $this->record->code)
            ->latest()
            ->first();

        return view('livewire.filament.widgets.currency-evidence-widget', [
            'event' => $lastEvent,
        ]);
    }
}
