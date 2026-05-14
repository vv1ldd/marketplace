<?php

namespace App\Support;

use Filament\Panel;

final class FilamentPanelDomain
{
    /**
     * @param  list<string>  $hosts  Имена хостов без https://
     */
    public static function apply(Panel $panel, array $hosts): Panel
    {
        $hosts = array_values(array_filter($hosts));

        if ($hosts === []) {
            return $panel;
        }

        if (count($hosts) === 1) {
            return $panel->domain($hosts[0]);
        }

        return $panel->domains($hosts);
    }
}
