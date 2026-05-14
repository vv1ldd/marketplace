<?php

namespace App\Filament\Kernel\Widgets;

use Filament\Widgets\Widget;

class KernelBannerWidget extends Widget
{
    protected string $view = 'filament.kernel.widgets.kernel-banner-widget';
    
    protected static ?int $sort = -100; // Show at the very top

    public int|string|array $columnSpan = 'full';
}
