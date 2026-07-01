<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MeanlyProductionReadinessDeployGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_deploy_gate_passes_in_http_fulfillment_mode_without_probing_sidecar(): void
    {
        config([
            'services.dgs.fulfillment_mode' => 'http',
        ]);

        Http::fake();

        $this->artisan('meanly:production-readiness', ['--deploy-gate' => true])
            ->assertSuccessful();
    }

    public function test_deploy_gate_fails_when_split_mode_sidecar_healthchecks_are_down(): void
    {
        config([
            'services.dgs.fulfillment_mode' => 'split',
            'services.dgs.fulfillment_url' => 'http://dgs-node-sidecar:8091',
            'services.dgs_shadow.ingest_url' => 'http://dgs-node-sidecar:8092/shadow/ingest',
        ]);

        Http::fake([
            'http://dgs-node-sidecar:8091/healthcheck' => Http::response('', 503),
            'http://dgs-node-sidecar:8092/healthcheck' => Http::response('', 503),
        ]);

        $this->artisan('meanly:production-readiness', ['--deploy-gate' => true])
            ->assertFailed();
    }

    public function test_deploy_gate_passes_when_split_mode_sidecar_endpoints_are_healthy(): void
    {
        config([
            'services.dgs.fulfillment_mode' => 'split',
            'services.dgs.fulfillment_url' => 'http://dgs-node-sidecar:8091',
            'services.dgs_shadow.ingest_url' => 'http://dgs-node-sidecar:8092/shadow/ingest',
        ]);

        Http::fake([
            'http://dgs-node-sidecar:8091/healthcheck' => Http::response('ok', 200),
            'http://dgs-node-sidecar:8092/healthcheck' => Http::response('ok', 200),
        ]);

        $this->artisan('meanly:production-readiness', ['--deploy-gate' => true])
            ->assertSuccessful();
    }
}
