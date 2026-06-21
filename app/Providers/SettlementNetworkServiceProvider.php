<?php

namespace App\Providers;

use App\Contracts\SettlementNetworkAdapter;
use App\Services\Networks\EvmNetworkAdapter;
use App\Services\Networks\SimpleLayer1NetworkAdapter;
use App\Services\SettlementNetworkRegistry;
use App\Services\SettlementNetworkResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class SettlementNetworkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettlementNetworkResolver::class);

        $this->app->singleton(SettlementNetworkRegistry::class, function ($app): SettlementNetworkRegistry {
            $resolver = $app->make(SettlementNetworkResolver::class);
            $adapters = [];

            foreach ((array) config('blockchain_networks.networks', []) as $key => $config) {
                $adapterClass = Arr::get($config, 'adapter');
                if (! is_string($adapterClass) || ! is_subclass_of($adapterClass, SettlementNetworkAdapter::class)) {
                    continue;
                }

                $adapters[$key] = $app->make($adapterClass, [
                    'networkKey' => $key,
                ]);
            }

            return new SettlementNetworkRegistry($resolver, $adapters);
        });
    }
}
