<?php

namespace App\Providers;

use App\Contracts\SettlementAdapter;
use App\Services\SettlementAdapterRegistry;
use App\Support\SettlementAdapterConfig;
use Illuminate\Support\ServiceProvider;

class SettlementAdapterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettlementAdapterRegistry::class, function ($app): SettlementAdapterRegistry {
            $adapters = [];

            foreach ((array) config('settlement_adapters', []) as $key => $config) {
                if (! is_array($config)) {
                    continue;
                }

                $adapterClass = SettlementAdapterConfig::adapterClass((string) $key);
                if ($adapterClass === null || ! is_subclass_of($adapterClass, SettlementAdapter::class)) {
                    continue;
                }

                $adapters[(string) $key] = $app->make($adapterClass);
            }

            return new SettlementAdapterRegistry($adapters);
        });
    }
}
