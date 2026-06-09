<?php

namespace App\Services\Provider;

use App\Models\Provider;
use Illuminate\Support\Manager;

class ProviderHub extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return 'ezpin';
    }

    /**
     * Legacy Wildflow provider records now route through Meanly's EZPin authority.
     */
    public function createWildflowDriver(): EzpinDriver
    {
        return new EzpinDriver();
    }

    public function createWildflowSandboxDriver(): EzpinDriver
    {
        return new EzpinDriver();
    }

    public function createEzpinDriver(): EzpinDriver
    {
        return new EzpinDriver();
    }

    public function createEzpinSandboxDriver(): EzpinDriver
    {
        return new EzpinDriver();
    }

    public function createFazerDriver(): FazerDriver
    {
        return new FazerDriver();
    }

    /**
     * Get a driver instance for a specific provider model.
     */
    public function forProvider(Provider $provider): ProviderDriverInterface
    {
        $driverName = $provider->type;
        
        /** @var ProviderDriverInterface $driver */
        $driver = $this->driver($driverName);
        
        return $driver->setProvider($provider);
    }
}
