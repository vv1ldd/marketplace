<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Provider;
use App\Services\CanonicalCategoryResolver;
use App\Services\MappingService;
use App\Services\VaultTransitService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ImportWildflowCatalogExportCommand extends Command
{
    protected $signature = 'meanly:import-wildflow-export
        {path=wildflow_catalog_export.json : JSON export path relative to the project root}
        {--provider=wildflow : Provider type to import under}
        {--limit= : Limit imported rows}
        {--replace : Soft-disable existing provider catalog rows before importing}
        {--publish : Publish imported provider products to the Meanly storefront}
        {--rebuild-identities : Rebuild canonical identities and commerce entities after publishing}
        {--dry-run : Validate and count rows without writing}';

    protected $description = 'Import a Wildflow catalog JSON export into provider catalog tables.';

    public function handle(
        VaultTransitService $vault,
        CanonicalCategoryResolver $categoryResolver,
    ): int {
        $path = base_path((string) $this->argument('path'));
        if (! is_file($path)) {
            $this->error("Export file not found: {$path}");

            return self::FAILURE;
        }

        $items = json_decode((string) file_get_contents($path), true);
        if (! is_array($items)) {
            $this->error('Export file must contain a JSON array.');

            return self::FAILURE;
        }

        $limit = $this->option('limit') !== null && $this->option('limit') !== ''
            ? max(1, (int) $this->option('limit'))
            : null;
        if ($limit !== null) {
            $items = array_slice($items, 0, $limit);
        }

        $stats = [
            'seen' => 0,
            'valid' => 0,
            'skipped' => 0,
            'catalog_rows' => 0,
            'provider_products' => 0,
        ];

        $catalogRows = [];
        $providerProductRows = [];
        $dryRun = (bool) $this->option('dry-run');
        $providerType = (string) $this->option('provider');
        $provider = $dryRun
            ? Provider::query()->where('type', $providerType)->first()
            : Provider::query()->firstOrCreate(
                ['type' => $providerType],
                ['name' => ucfirst($providerType), 'is_active' => true],
            );
        $providerId = (int) ($provider?->id ?? 0);

        foreach ($items as $item) {
            $stats['seen']++;
            if (! is_array($item)) {
                $stats['skipped']++;
                continue;
            }

            $data = is_array($item['data'] ?? null) ? $item['data'] : [];
            $serviceSku = (string) ($item['service_sku'] ?? data_get($data, 'sku') ?? '');
            $marketSku = (string) ($item['sku'] ?? '');
            $title = trim((string) (data_get($data, 'title') ?? data_get($data, 'product.title') ?? $marketSku));

            if ($serviceSku === '' || $marketSku === '' || $title === '') {
                $stats['skipped']++;
                continue;
            }

            $currency = strtoupper((string) (data_get($data, 'currency.code') ?? 'USD'));
            $minPrice = $this->number(data_get($data, 'min_price'));
            $maxPrice = $this->number(data_get($data, 'max_price')) ?: $minPrice;
            $retailPrice = $minPrice > 0 ? $minPrice : $maxPrice;
            $purchasePrice = $this->purchasePrice($data, $retailPrice);
            if ($retailPrice <= 0 || $purchasePrice <= 0) {
                $stats['skipped']++;
                continue;
            }

            $brandName = trim((string) (data_get($data, 'categories.0.name') ?? $title));
            $brandId = null;
            if (! $dryRun) {
                $brandId = MappingService::resolveBrand($providerId, $brandName, $marketSku, $title, $brandName);
                if (! $brandId) {
                    $brandId = Brand::firstOrCreate(['name' => 'Нет бренда'])->id;
                }
            }

            $regionCode = (string) (data_get($data, 'regions.0.code') ?? '');
            $regionName = (string) (data_get($data, 'regions.0.name') ?? $regionCode);
            $regionId = (! $dryRun && $regionCode !== '') ? MappingService::resolveRegion($regionCode, $regionName) : null;

            $rewardType = $this->nullableString(data_get($data, 'reward_type_text') ?? data_get($data, 'reward_type'), 255);
            $activationUrl = $this->nullableString(data_get($data, 'activation_url') ?? data_get($data, 'redemption_url'), 255);
            $redemptionInstructions = $this->nullableString(data_get($data, 'description'));
            $upc = $this->nullableString(data_get($data, 'upc_string') ?? data_get($data, 'upc'), 255);
            $canonicalCategory = $categoryResolver->fromPayload($data, [$title, $brandName, $rewardType]);

            $payload = $data + [
                'service_sku' => $serviceSku,
                'market_sku' => $marketSku,
                'source' => 'wildflow_catalog_export',
                'provider_purchase' => [
                    'pre_order' => (bool) data_get($data, 'pre_order', false),
                ],
            ];

            $now = now();
            $catalogRows[] = [
                'provider_id' => $providerId,
                'service_sku' => $vault->encrypt($serviceSku),
                'service_sku_bidx' => $vault->computeBlindIndex($serviceSku),
                'sku' => $marketSku,
                'data' => $vault->encrypt(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'type' => 'wildflow_export',
                'brand_id' => $brandId,
                'region_id' => $regionId,
                'retail_price' => $retailPrice,
                'purchase_price' => $purchasePrice,
                'min_price' => $minPrice ?: $retailPrice,
                'max_price' => $maxPrice ?: $retailPrice,
                'redemption_instructions' => $redemptionInstructions,
                'activation_url' => $activationUrl,
                'reward_type' => $rewardType,
                'upc' => $upc,
                'canonical_category' => $canonicalCategory,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $providerProductRows[] = [
                'provider_id' => $providerId,
                'sku' => $vault->encrypt($serviceSku),
                'sku_bidx' => $vault->computeBlindIndex($serviceSku),
                'market_sku' => $vault->encrypt($marketSku),
                'market_sku_bidx' => $vault->computeBlindIndex($marketSku),
                'name' => $title,
                'category' => $brandName,
                'canonical_category' => $canonicalCategory,
                'reward_type' => $rewardType,
                'purchase_price' => $purchasePrice,
                'retail_price' => $retailPrice,
                'min_price' => $minPrice ?: $retailPrice,
                'max_price' => $maxPrice ?: $retailPrice,
                'currency' => $currency,
                'brand_id' => $brandId,
                'region_id' => $regionId,
                'image' => $this->nullableString(data_get($data, 'image'), 255),
                'activation_url' => $activationUrl,
                'redemption_instructions' => $redemptionInstructions,
                'is_active' => true,
                'data' => $vault->encrypt(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $stats['valid']++;
        }

        if ($dryRun) {
            $this->printStats('Dry run complete.', $stats);

            return self::SUCCESS;
        }

        DB::transaction(function () use ($provider, $catalogRows, $providerProductRows, &$stats): void {
            if ((bool) $this->option('replace')) {
                DB::table('wildflow_catalogs')->where('provider_id', $providerId)->update(['is_active' => false]);
                DB::table('provider_products')->where('provider_id', $providerId)->update(['is_active' => false]);
            }

            foreach (array_chunk($catalogRows, 300) as $chunk) {
                DB::table('wildflow_catalogs')->upsert(
                    $chunk,
                    ['service_sku_bidx'],
                    [
                        'provider_id', 'service_sku', 'sku', 'data', 'type', 'brand_id', 'region_id',
                        'retail_price', 'purchase_price', 'min_price', 'max_price',
                        'redemption_instructions', 'activation_url', 'reward_type', 'upc',
                        'canonical_category', 'is_active', 'updated_at',
                    ],
                );
                $stats['catalog_rows'] += count($chunk);
            }

            foreach (array_chunk($providerProductRows, 300) as $chunk) {
                DB::table('provider_products')->upsert(
                    $chunk,
                    ['provider_id', 'sku_bidx'],
                    [
                        'sku', 'market_sku', 'market_sku_bidx', 'name', 'category', 'canonical_category',
                        'reward_type', 'purchase_price', 'retail_price', 'min_price', 'max_price',
                        'currency', 'brand_id', 'region_id', 'image', 'activation_url',
                        'redemption_instructions', 'is_active', 'data', 'updated_at',
                    ],
                );
                $stats['provider_products'] += count($chunk);
            }

            $provider?->forceFill(['last_sync_at' => now()])->save();
        });

        $this->printStats('Wildflow export imported.', $stats);

        if ((bool) $this->option('publish')) {
            Artisan::call('meanly:publish-provider-catalog', [
                '--provider' => $providerType,
                '--rebuild-identities' => (bool) $this->option('rebuild-identities'),
            ]);
            $this->line(trim(Artisan::output()));
        }

        return self::SUCCESS;
    }

    private function purchasePrice(array $data, float $retailPrice): float
    {
        $buyingPrice = $this->number(data_get($data, 'buying_price'));
        if ($buyingPrice > 0) {
            return $buyingPrice;
        }

        $percentage = data_get($data, 'percentage_of_buying_price');
        if ($percentage !== null && $retailPrice > 0) {
            return (float) ($retailPrice * (1 + ((float) $percentage / 100)));
        }

        return $retailPrice;
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function nullableString(mixed $value, int $limit = 1000): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    private function printStats(string $title, array $stats): void
    {
        $this->info($title);
        foreach ($stats as $name => $value) {
            $this->line($name.': '.$value);
        }
    }
}
