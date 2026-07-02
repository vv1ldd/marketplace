<?php

namespace App\Domain\Routing;

interface ProviderMetricsProviderInterface
{
    public function getSignalsForProvider(int $providerId): ProviderRuntimeSignals;
}

