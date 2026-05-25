<?php

namespace App\Console\Commands;

use App\Models\CanonicalProductIdentity;
use App\Models\CanonicalProductIdentityOverride;
use App\Services\CanonicalProductIdentityCurationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class OverrideCanonicalProductIdentity extends Command
{
    protected $signature = 'catalog:override-identity
                            {fingerprint : Durable canonical identity fingerprint}
                            {--brand= : Override brand}
                            {--family= : Override product family}
                            {--face-value= : Override face value}
                            {--currency= : Override face value currency}
                            {--region= : Override region}
                            {--platform= : Override platform}
                            {--category= : Override canonical category}
                            {--confidence= : Override confidence: low, medium, or high}
                            {--signals= : Override signals as JSON array or comma-separated list}
                            {--clear-signals : Replace identity signals with an empty list}
                            {--notes= : Review notes}
                            {--status= : Review status: pending, approved, rejected, ignored}
                            {--approve : Mark the override approved immediately}
                            {--reject : Mark the override rejected}
                            {--ignore : Mark the identity ignored for review}
                            {--dry-run : Print the override payload without writing}
                            {--json : Emit result as JSON}';

    protected $description = 'Create or update a durable curation override for a canonical product identity';

    public function handle(CanonicalProductIdentityCurationService $curation): int
    {
        $fingerprint = trim((string) $this->argument('fingerprint'));
        $payload = $this->payload();
        $identity = CanonicalProductIdentity::query()
            ->where('fingerprint', $fingerprint)
            ->first(['id', 'fingerprint', 'identity_slug', 'confidence', 'signals']);
        $result = [
            'fingerprint' => $fingerprint,
            'identity_id' => $identity?->id,
            'identity_slug' => $identity?->identity_slug,
            'payload' => $payload,
            'dry_run' => (bool) $this->option('dry-run'),
        ];

        if ((bool) $this->option('dry-run')) {
            return $this->renderResult($result);
        }

        $override = $curation->saveOverride($fingerprint, $payload, Auth::id());
        $result['override'] = $override->only([
            'id',
            'canonical_product_identity_id',
            'fingerprint',
            'brand',
            'product_family',
            'face_value',
            'face_value_currency',
            'region',
            'platform',
            'canonical_category',
            'confidence',
            'review_status',
            'review_notes',
            'reviewed_at',
            'created_at',
            'updated_at',
        ]);

        return $this->renderResult($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $payload = [];
        $optionMap = [
            'brand' => 'brand',
            'family' => 'product_family',
            'face-value' => 'face_value',
            'currency' => 'face_value_currency',
            'region' => 'region',
            'platform' => 'platform',
            'category' => 'canonical_category',
            'confidence' => 'confidence',
            'notes' => 'review_notes',
        ];

        foreach ($optionMap as $option => $field) {
            $value = $this->option($option);
            if ($value !== null && $value !== '') {
                $payload[$field] = $value;
            }
        }

        if ((bool) $this->option('clear-signals')) {
            $payload['signals'] = [];
        } elseif ($this->option('signals') !== null && $this->option('signals') !== '') {
            $payload['signals'] = $this->parseSignals((string) $this->option('signals'));
        }

        $payload['review_status'] = $this->reviewStatus();

        return $payload;
    }

    /**
     * @return array<int, string>
     */
    private function parseSignals(string $value): array
    {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return collect($decoded)
                ->flatten()
                ->map(fn ($signal) => trim((string) $signal))
                ->filter()
                ->values()
                ->all();
        }

        return collect(explode(',', $value))
            ->map(fn (string $signal) => trim($signal))
            ->filter()
            ->values()
            ->all();
    }

    private function reviewStatus(): string
    {
        if ((bool) $this->option('approve')) {
            return CanonicalProductIdentityOverride::STATUS_APPROVED;
        }

        if ((bool) $this->option('reject')) {
            return CanonicalProductIdentityOverride::STATUS_REJECTED;
        }

        if ((bool) $this->option('ignore')) {
            return CanonicalProductIdentityOverride::STATUS_IGNORED;
        }

        $status = $this->option('status');
        if ($status !== null && $status !== '') {
            return (string) $status;
        }

        return CanonicalProductIdentityOverride::STATUS_PENDING;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function renderResult(array $result): int
    {
        if ((bool) $this->option('json')) {
            $this->output->write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);

            return self::SUCCESS;
        }

        if ($result['dry_run']) {
            $this->info('Dry run: override was not written.');
        } else {
            $this->info('Canonical product identity override saved.');
        }

        $this->line('Fingerprint: '.$result['fingerprint']);
        $this->line('Identity: '.($result['identity_id'] ? '#'.$result['identity_id'].' '.$result['identity_slug'] : 'not currently persisted'));
        $this->line('Status: '.data_get($result, 'payload.review_status'));
        $this->line('Payload: '.json_encode($result['payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
