<?php

namespace App\Providers;

use App\Domain\Routing\ExecutionRecordMetricsProvider;
use App\Domain\Routing\ProviderMetricsProviderInterface;
use App\Services\Architecture\ExecutionRecordService;
use App\Services\Architecture\ExecutionRecordServiceInterface;
use App\Services\Architecture\OfferSnapshotService;
use App\Services\Architecture\OfferSnapshotServiceInterface;
use Illuminate\Support\ServiceProvider;

class ArchitectureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderMetricsProviderInterface::class, ExecutionRecordMetricsProvider::class);
        $this->app->singleton(OfferSnapshotServiceInterface::class, OfferSnapshotService::class);
        $this->app->singleton(ExecutionRecordServiceInterface::class, ExecutionRecordService::class);
    }
}
