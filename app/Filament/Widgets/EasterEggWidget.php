<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class EasterEggWidget extends Widget
{
    protected string $view = 'filament.widgets.easter-egg-widget';
    
    protected static ?int $sort = -100; // Always at the top

    public int|string|array $columnSpan = 'full';

    public function isMemorialPeriod(): bool
    {
        $now = now();
        
        // Показываем ТОЛЬКО в апреле и ТОЛЬКО со следующего года (2027+)
        return ($now->year > 2026) && ($now->month === 4);
    }
}
