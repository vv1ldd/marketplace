<?php

namespace App\Filament\Audit\Widgets;

use Filament\Widgets\Widget;

class TribunalBannerWidget extends Widget
{
    protected string $view = 'filament.audit.widgets.tribunal-banner-widget';

    // Stretch full-width
    protected int | string | array $columnSpan = 'full';
}
