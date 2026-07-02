<?php

namespace Tests\Feature;

use App\Services\MeanlyOperationalAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalOpsAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluate_runs_with_ops_checks_and_returns_collection(): void
    {
        $alerts = app(MeanlyOperationalAlertService::class)->evaluate();

        $this->assertNotNull($alerts);
        // Ops checks must not throw even on a healthy small test environment.
        $this->assertTrue($alerts->every(fn ($alert) => filled($alert->alert_key)));
    }

    public function test_recent_failed_jobs_alert_fires_when_jobs_fail(): void
    {
        for ($i = 0; $i < 12; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'database',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'test failure',
                'failed_at' => now(),
            ]);
        }

        app(MeanlyOperationalAlertService::class)->evaluate();

        $this->assertDatabaseHas('meanly_operational_alerts', [
            'alert_key' => 'ops.failed_jobs_1h',
            'status' => 'open',
            'severity' => 'critical',
        ]);
    }
}
