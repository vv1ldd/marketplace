<?php

namespace App\Filament\Client\Pages;

use Filament\Pages\Page;

class Integrations extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected string $view = 'filament.client.pages.integrations';

    public static function getNavigationLabel(): string
    {
        return 'Бизнес-кабинет';
    }

    public function getTitle(): string
    {
        return 'Активация B2B-возможностей';
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    protected static ?int $navigationSort = 2;

    protected function getViewData(): array
    {
        $user = auth()->user();
        $isB2bPartner = $user && $user->hasRole('b2b_partner');
        $entities = $user ? $user->managedLegalEntities()->where('status', 'active')->get() : collect();

        return [
            'isB2bPartner' => $isB2bPartner,
            'entities' => $entities,
            'user' => $user,
        ];
    }
}
