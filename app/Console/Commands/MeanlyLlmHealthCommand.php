<?php

namespace App\Console\Commands;

use App\Services\Llm\LlmProviderManager;
use Illuminate\Console\Command;

class MeanlyLlmHealthCommand extends Command
{
    protected $signature = 'meanly:llm-health {--json : Output machine-readable JSON}';

    protected $description = 'Check local and cloud LLM provider configuration for Meanly AI surfaces.';

    public function handle(LlmProviderManager $manager): int
    {
        $providers = $manager->health();
        $configured = $manager->configuredProviderNames();
        $cloudConfigured = $manager->cloudConfigured();
        $cloudRequired = (bool) config('llm.cloud_required', false);
        $status = $configured === [] || ($cloudRequired && ! $cloudConfigured) ? 'NO GO' : 'READY';

        if ($this->option('json')) {
            $this->line(json_encode([
                'status' => $status,
                'default' => config('llm.default', 'local'),
                'fallback' => config('llm.fallback', []),
                'cloud_required' => $cloudRequired,
                'cloud_configured' => $cloudConfigured,
                'configured' => $configured,
                'providers' => $providers,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $status === 'READY' ? self::SUCCESS : self::FAILURE;
        }

        $this->info('LLM HEALTH');
        $this->line('----------');
        $this->table(
            ['Provider', 'Driver', 'Model', 'Configured', 'Cloud', 'Default', 'Fallback'],
            collect($providers)->map(fn (array $provider): array => [
                $provider['name'],
                $provider['driver'],
                $provider['model'] ?: '-',
                $provider['configured'] ? 'yes' : 'no',
                $provider['cloud'] ? 'yes' : 'no',
                $provider['default'] ? 'yes' : 'no',
                $provider['fallback'] ? 'yes' : 'no',
            ])->all(),
        );

        if ($status === 'READY') {
            $this->info('RESULT: READY');
        } else {
            $this->error('RESULT: NO GO');
        }

        return $status === 'READY' ? self::SUCCESS : self::FAILURE;
    }
}
