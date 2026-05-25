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
        return 'wildflow';
    }

    /**
     * Create Wildflow driver.
     */
    public function createWildflowDriver(): WildflowDriver
    {
        return new WildflowDriver();
    }

    public function createWildflowSandboxDriver(): WildflowDriver
    {
        return new WildflowDriver();
    }

    /**
     * Get a driver instance for a specific provider model.
     */
    public function forProvider(Provider $provider): ProviderDriverInterface
    {
        $driverName = $provider->type; // 'wildflow', etc.
        
        /** @var ProviderDriverInterface $driver */
        $driver = $this->driver($driverName);
        
        return $driver->setProvider($provider);
    }
}
