<?php

namespace App\Domain\Routing;

class ProviderRuntimeSignals
{
    public function __construct(
        public readonly float $successRate,
        public readonly int $p50LatencyMs,
        public readonly float $stockStatus = 1.0,
    ) {}
}

