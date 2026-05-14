<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Analytics extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected string $view = 'filament.pages.analytics';

    protected static ?string $navigationLabel = 'Аналитика';

    protected static ?string $title = 'Центр Системной Аналитики';

    protected static string | \UnitEnum | null $navigationGroup = 'Система';

    protected static ?int $navigationSort = 1;
}
