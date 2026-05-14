<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ApiApplicationResource;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

/**
 * Legacy route: /admin/settings → API applications (platform token is edited there).
 */
class Settings extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.redirect-blank';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('page_Settings');
    }

    public function mount(): void
    {
        $this->redirect(ApiApplicationResource::getUrl());
    }
}
