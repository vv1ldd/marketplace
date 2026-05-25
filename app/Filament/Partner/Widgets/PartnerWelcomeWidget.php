<?php

namespace App\Filament\Partner\Widgets;

use Filament\Widgets\Widget;
use Filament\Facades\Filament;

class PartnerWelcomeWidget extends Widget
{
    protected static ?int $sort = 0;

    protected string $view = 'filament.partner.widgets.partner-welcome-widget';

    public function getLegalEntity()
    {
        return Filament::getTenant();
    }

    public function getFinanceUrl(): string
    {
        return \App\Filament\Partner\Pages\Finance::getUrl();
    }

    public function getIntegrationsUrl(): string
    {
        return \App\Filament\Partner\Pages\Integrations::getUrl();
    }

    public function getShopsUrl(): string
    {
        return \App\Filament\Partner\Resources\ShopResource::getUrl('index');
    }
}
