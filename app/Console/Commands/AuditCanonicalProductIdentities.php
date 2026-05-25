<?php

namespace App\Console\Commands;

use App\Models\CanonicalProductIdentity;
use App\Models\CanonicalProductIdentitySource;
use App\Models\Product;
use App\Models\ProviderProduct;
use App\Services\CanonicalProductIdentityService;
use App\Services\MeanlyFirstPartyStorefrontService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditCanonicalProductIdentities extends Command
{
    protected $signature = 'catalog:audit-identities
                            {--limit=20 : Sample rows per report section}
                            {--json : Emit the report as JSON}';

    protected $description = 'Audit persisted canonical product identities for suspicious quality signals';

    private const DENOMINATION_CATEGORIES = [
        'gift_cards',
        'console_payment_cards',
        'game_wallet_topups',
        'mobile_app_store_cards',
        'payment_prepaid_cards',
        'subscriptions',
        'software_licenses',
        'telecom_topups',
    ];

    private const KNOWN_BRANDS = [
        'adobe' => 'Adobe',
        'amex' => 'American Express',
        'american express' => 'American Express',
        'apple' => 'Apple',
        'app store' => 'Apple',
        'battle net' => 'Battle.net',
        'battle.net' => 'Battle.net',
        'bigo' => 'Bigo Live',
        'bigo live' => 'Bigo Live',
        'bitdefender' => 'Bitdefender',
        'blizzard' => 'Battle.net',
        'epic games' => 'Epic Games',
        'free fire' => 'Free Fire',
        'garena' => 'Garena',
        'google play' => 'Google Play',
        'itunes' => 'Apple',
        'kaspersky' => 'Kaspersky',
        'league of legends' => 'Riot Games',
        'microsoft' => 'Microsoft',
        'netflix' => 'Netflix',
        'nintendo' => 'Nintendo',
        'office' => 'Microsoft',
        'play station' => 'PlayStation',
        'playstation' => 'PlayStation',
        'play store' => 'Google Play',
        'psn' => 'PlayStation',
        'pubg' => 'PUBG',
        'razer gold' => 'Razer Gold',
        'riot' => 'Riot Games',
        'roblox' => 'Roblox',
        'spotify' => 'Spotify',
        'steam' => 'Steam',
        'valorant' => 'Riot Games',
        'windows' => 'Microsoft',
        'xbox' => 'Xbox',
    ];

    private const GENERIC_BRAND_TOKENS = [
        'card',
        'code',
        'digital',
        'diamonds',
        'game',
        'gift',
        'global',
        'instant',
        'key',
        'live',
        'payment',
        'prepaid',
        'subscription',
        'topup',
        'topups',
        'voucher',
        'wallet',
    ];

    private CanonicalProductIdentityService $identityService;

    private MeanlyFirstPartyStorefrontService $storefront;

    public function handle(
        CanonicalProductIdentityService $identityService,
        MeanlyFirstPartyStorefrontService $storefront,
    ): int {
        $this->identityService = $identityService;
        $this->storefront = $storefront;

        $limit = max(1, (int) ($this->option('limit') ?: 20));

        if (! $this->tablesExist()) {
            $this->warn('Canonical product identity tables do not exist yet. Run migrations before auditing.');

            return self::FAILURE;
        }

        $allIdentities = CanonicalProductIdentity::query()
            ->select([
                'id',
                'fingerprint',
                'identity_slug',
                'canonical_category',
                'brand',
                'product_family',
                'face_value',
                'face_value_currency',
                'region',
                'platform',
                'confidence',
                'provider_candidates_count',
                'seller_offers_count',
                'best_offer_product_id',
            ])
            ->withCount('sources')
            ->orderBy('id')
            ->get();

        $report = [
            'generated_at' => now()->toIso8601String(),
            'limit' => $limit,
            'summary' => $this->summary($allIdentities),
            'warnings' => [],
            'sections' => [],
        ];
        $report['warnings'] = $this->warnings($report['summary']);

        $this->addConfidenceSection($report);
        $this->addCoverageSections($report, $limit);
        $this->addSuspiciousIdentityTextSection($report, $allIdentities, $limit);
        $this->addMissingDenominationsSection($report, $limit);
        $this->addDuplicateishSections($report, $allIdentities, $limit);
        $this->addTopSourceCountSection($report, $limit);
        $this->addMixedSourceSection($report, $limit);
        $this->addConfidenceSamplesSection($report, $allIdentities, $limit);

        if ((bool) $this->option('json')) {
            $this->output->write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);

            return self::SUCCESS;
        }

        $this->renderReport($report);

        return self::SUCCESS;
    }

    /**
     * @param  EloquentCollection<int, CanonicalProductIdentity>  $identities
     * @return array<string, mixed>
     */
    private function summary(EloquentCollection $identities): array
    {
        $confidence = $identities
            ->groupBy(fn (CanonicalProductIdentity $identity) => (string) ($identity->confidence ?: 'unknown'))
            ->map->count()
            ->sortKeys()
            ->all();

        return [
            'identity_count' => $identities->count(),
            'source_count' => CanonicalProductIdentitySource::query()->count(),
            'confidence_distribution' => $confidence,
            'source_type_distribution' => CanonicalProductIdentitySource::query()
                ->select('source_type', DB::raw('count(*) as total'))
                ->groupBy('source_type')
                ->orderBy('source_type')
                ->pluck('total', 'source_type')
                ->map(fn ($count) => (int) $count)
                ->all(),
            'multi_source_identity_count' => $this->groupedSourceCount('count(*) > 1'),
            'identities_with_seller_offers' => $identities->where('seller_offers_count', '>', 0)->count(),
            'identities_with_best_offer' => $identities->whereNotNull('best_offer_product_id')->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<int, string>
     */
    private function warnings(array $summary): array
    {
        $warnings = [];
        $total = (int) ($summary['identity_count'] ?? 0);
        $confidence = (array) ($summary['confidence_distribution'] ?? []);
        $high = (int) ($confidence['high'] ?? 0);
        $low = (int) ($confidence['low'] ?? 0);

        if ($total > 0 && $high / $total >= 0.98 && $low === 0) {
            $warnings[] = sprintf(
                'Confidence distribution is suspiciously optimistic: %.2f%% high and zero low identities.',
                ($high / $total) * 100,
            );
        }

        if ((int) ($summary['identities_with_seller_offers'] ?? 0) !== (int) ($summary['identities_with_best_offer'] ?? 0)) {
            $warnings[] = 'Some identities have seller sources but no persisted best offer.';
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function addConfidenceSection(array &$report): void
    {
        $total = max(1, (int) $report['summary']['identity_count']);
        $rows = collect($report['summary']['confidence_distribution'])
            ->map(fn (int $count, string $confidence) => [
                'confidence' => $confidence,
                'count' => $count,
                'percent' => round(($count / $total) * 100, 2).'%',
            ])
            ->values()
            ->all();

        $this->section($report, 'confidence_distribution', 'Confidence Distribution', ['confidence', 'count', 'percent'], $rows);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function addCoverageSections(array &$report, int $limit): void
    {
        $marketplaceQuery = $this->storefront->marketplaceProductsQuery();
        $activeStorefrontCount = (clone $marketplaceQuery)->count();
        $missingSourceQuery = (clone $marketplaceQuery)->whereNotExists(function ($query): void {
            $query->selectRaw('1')
                ->from('canonical_product_identity_sources')
                ->where('source_type', CanonicalProductIdentitySource::SOURCE_PRODUCT)
                ->whereColumn('source_id', 'products.id');
        });
        $sellerSourceNoBestOffer = CanonicalProductIdentity::query()
            ->where('seller_offers_count', '>', 0)
            ->whereNull('best_offer_product_id');
        $providerOnly = CanonicalProductIdentity::query()
            ->where('provider_candidates_count', '>', 0)
            ->where('seller_offers_count', 0);

        $rows = [
            ['metric' => 'active_storefront_products', 'count' => $activeStorefrontCount],
            ['metric' => 'active_storefront_products_without_identity_source', 'count' => (clone $missingSourceQuery)->count()],
            ['metric' => 'identities_with_seller_sources_but_no_best_offer', 'count' => (clone $sellerSourceNoBestOffer)->count()],
            ['metric' => 'identities_with_provider_sources_but_no_seller_offers', 'count' => (clone $providerOnly)->count()],
        ];
        $this->section($report, 'seller_source_coverage', 'Seller And Source Coverage', ['metric', 'count'], $rows);

        $missingRows = (clone $missingSourceQuery)
            ->select(['products.id', 'products.name', 'products.sku', 'products.shop_id'])
            ->orderBy('products.id')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'sku' => $this->clip($product->sku, 32),
                'shop_id' => $product->shop_id,
                'name' => $this->clip($product->name, 72),
            ])
            ->all();
        $this->section($report, 'storefront_products_without_identity_source', 'Storefront Products Missing Identity Source', ['id', 'sku', 'shop_id', 'name'], $missingRows);

        $sellerNoBestRows = $sellerSourceNoBestOffer
            ->orderByDesc('seller_offers_count')
            ->limit($limit)
            ->get()
            ->map(fn (CanonicalProductIdentity $identity) => $this->identityRow($identity))
            ->all();
        $this->section($report, 'seller_sources_without_best_offer', 'Seller Source Identities Without Best Offer', $this->identityColumns(), $sellerNoBestRows);

        $providerOnlyRows = $providerOnly
            ->orderByDesc('provider_candidates_count')
            ->limit($limit)
            ->get()
            ->map(fn (CanonicalProductIdentity $identity) => $this->identityRow($identity))
            ->all();
        $this->section($report, 'provider_only_identities', 'Provider Identities Without Seller Offers', $this->identityColumns(), $providerOnlyRows);
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  EloquentCollection<int, CanonicalProductIdentity>  $identities
     */
    private function addSuspiciousIdentityTextSection(array &$report, EloquentCollection $identities, int $limit): void
    {
        $rows = $identities
            ->map(function (CanonicalProductIdentity $identity) {
                $reasons = $this->suspiciousReasons($identity);

                return [
                    'identity' => $identity,
                    'reasons' => $reasons,
                    'score' => count($reasons),
                ];
            })
            ->filter(fn (array $row) => $row['reasons'] !== [])
            ->sortByDesc(fn (array $row) => $row['score'])
            ->values();

        $sample = $rows
            ->take($limit)
            ->map(fn (array $row) => [
                ...$this->identityRow($row['identity']),
                'sources' => $row['identity']->sources_count,
                'reasons' => implode('; ', $row['reasons']),
            ])
            ->all();

        $this->section(
            $report,
            'suspicious_identity_text',
            'Suspicious Slugs And Identity Text',
            [...$this->identityColumns(), 'sources', 'reasons'],
            $sample,
            ['total_suspicious' => $rows->count()],
        );

        $highRows = $rows
            ->filter(fn (array $row) => $row['identity']->confidence === 'high')
            ->take($limit)
            ->map(fn (array $row) => [
                ...$this->identityRow($row['identity']),
                'sources' => $row['identity']->sources_count,
                'reasons' => implode('; ', $row['reasons']),
            ])
            ->all();

        $this->section(
            $report,
            'suspicious_high_confidence_identities',
            'Suspicious High-Confidence Identities',
            [...$this->identityColumns(), 'sources', 'reasons'],
            $highRows,
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function addMissingDenominationsSection(array &$report, int $limit): void
    {
        $query = CanonicalProductIdentity::query()
            ->whereIn('canonical_category', self::DENOMINATION_CATEGORIES)
            ->where(function ($query): void {
                $query->whereNull('face_value')
                    ->orWhere('face_value', '<=', 0)
                    ->orWhereNull('face_value_currency');
            });

        $rows = (clone $query)
            ->orderBy('canonical_category')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (CanonicalProductIdentity $identity) => $this->identityRow($identity))
            ->all();

        $this->section(
            $report,
            'missing_denominations',
            'Missing Denominations In Denomination Categories',
            $this->identityColumns(),
            $rows,
            ['total' => (clone $query)->count()],
        );
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  EloquentCollection<int, CanonicalProductIdentity>  $identities
     */
    private function addDuplicateishSections(array &$report, EloquentCollection $identities, int $limit): void
    {
        $denominationFamilies = $identities
            ->groupBy(fn (CanonicalProductIdentity $identity) => implode('|', [
                $this->keyPart($identity->brand),
                $this->keyPart($identity->product_family),
                $this->keyPart($identity->canonical_category),
                $this->keyPart($identity->face_value_currency),
                $this->keyPart($identity->region),
                $this->keyPart($identity->platform),
            ]))
            ->map(function (Collection $group) {
                $faceValues = $group
                    ->pluck('face_value')
                    ->filter(fn ($value) => $value !== null && (float) $value > 0)
                    ->map(fn ($value) => $this->amountKey((float) $value))
                    ->unique()
                    ->values();

                return [
                    'group' => $group,
                    'face_values' => $faceValues,
                ];
            })
            ->filter(fn (array $row) => $row['group']->count() > 1 && $row['face_values']->count() > 1)
            ->sortByDesc(fn (array $row) => $row['group']->count())
            ->values();

        $familyRows = $denominationFamilies
            ->take($limit)
            ->map(function (array $row) {
                /** @var Collection<int, CanonicalProductIdentity> $group */
                $group = $row['group'];
                /** @var CanonicalProductIdentity $first */
                $first = $group->first();

                return [
                    'brand' => $this->clip($first->brand),
                    'family' => $this->clip($first->product_family, 40),
                    'category' => $first->canonical_category,
                    'currency' => $first->face_value_currency,
                    'region' => $first->region,
                    'platform' => $this->clip($first->platform, 32),
                    'identities' => $group->count(),
                    'face_values' => $this->clip($row['face_values']->take(8)->implode(', '), 48),
                ];
            })
            ->all();

        $this->section(
            $report,
            'same_family_many_face_values',
            'Same Family With Many Face Values',
            ['brand', 'family', 'category', 'currency', 'region', 'platform', 'identities', 'face_values'],
            $familyRows,
            ['total_groups' => $denominationFamilies->count()],
        );

        $slugStemRows = $identities
            ->groupBy(fn (CanonicalProductIdentity $identity) => $this->slugStem((string) $identity->identity_slug))
            ->map(fn (Collection $group, string $stem) => ['stem' => $stem, 'group' => $group])
            ->filter(fn (array $row) => $row['group']->count() >= 8)
            ->sortByDesc(fn (array $row) => $row['group']->count())
            ->take($limit)
            ->map(fn (array $row) => [
                'stem' => $this->clip($row['stem'], 72),
                'identities' => $row['group']->count(),
                'sample_ids' => $row['group']->pluck('id')->take(8)->implode(', '),
                'sample_slugs' => $this->clip($row['group']->pluck('identity_slug')->take(2)->implode(' | '), 96),
            ])
            ->values()
            ->all();

        $this->section(
            $report,
            'slug_stems_with_many_identities',
            'Slug Stems With Many Identities',
            ['stem', 'identities', 'sample_ids', 'sample_slugs'],
            $slugStemRows,
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function addTopSourceCountSection(array &$report, int $limit): void
    {
        $rows = CanonicalProductIdentity::query()
            ->withCount('sources')
            ->orderByDesc('sources_count')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (CanonicalProductIdentity $identity) => [
                ...$this->identityRow($identity),
                'sources' => $identity->sources_count,
            ])
            ->all();

        $this->section($report, 'top_identities_by_source_count', 'Top Identities By Source Count', [...$this->identityColumns(), 'sources'], $rows);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function addMixedSourceSection(array &$report, int $limit): void
    {
        $scanLimit = max(100, $limit * 10);
        $sourceCounts = DB::table('canonical_product_identity_sources')
            ->select('canonical_product_identity_id', DB::raw('count(*) as source_count'))
            ->groupBy('canonical_product_identity_id')
            ->havingRaw('count(*) > 1')
            ->orderByDesc('source_count')
            ->limit($scanLimit)
            ->pluck('source_count', 'canonical_product_identity_id');

        $identities = CanonicalProductIdentity::query()
            ->whereKey($sourceCounts->keys()->all())
            ->with('sources')
            ->get();

        $rows = $identities
            ->map(function (CanonicalProductIdentity $identity) use ($sourceCounts) {
                $sourceIdentities = $identity->sources
                    ->take(25)
                    ->map(fn (CanonicalProductIdentitySource $source) => $this->identityForSource($source))
                    ->filter()
                    ->values();

                $mixed = $this->mixedFields($sourceIdentities);
                if ($mixed === []) {
                    return null;
                }

                return [
                    'id' => $identity->id,
                    'slug' => $this->clip($identity->identity_slug, 64),
                    'sources' => (int) ($sourceCounts[$identity->id] ?? $identity->sources->count()),
                    'mixed_fields' => implode('; ', $mixed),
                    'persisted' => $this->clip($this->fieldSummary([
                        'brand' => $identity->brand,
                        'product_family' => $identity->product_family,
                        'face_value' => $identity->face_value,
                        'face_value_currency' => $identity->face_value_currency,
                        'region' => $identity->region,
                    ]), 96),
                    'sample_sources' => $this->clip($sourceIdentities
                        ->take(3)
                        ->map(fn (array $row) => $this->fieldSummary($row))
                        ->implode(' | '), 120),
                ];
            })
            ->filter()
            ->sortByDesc('sources')
            ->take($limit)
            ->values()
            ->all();

        $this->section(
            $report,
            'mixed_multi_source_identities',
            'Mixed Signals Within Multi-Source Identities',
            ['id', 'slug', 'sources', 'mixed_fields', 'persisted', 'sample_sources'],
            $rows,
            ['scanned_multi_source_identities' => $sourceCounts->count()],
        );
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  EloquentCollection<int, CanonicalProductIdentity>  $identities
     */
    private function addConfidenceSamplesSection(array &$report, EloquentCollection $identities, int $limit): void
    {
        $rows = $identities
            ->filter(fn (CanonicalProductIdentity $identity) => in_array($identity->confidence, ['low', 'medium'], true))
            ->sortBy('confidence')
            ->take($limit)
            ->map(fn (CanonicalProductIdentity $identity) => [
                ...$this->identityRow($identity),
                'sources' => $identity->sources_count,
            ])
            ->values()
            ->all();

        $this->section($report, 'low_medium_confidence_samples', 'Low And Medium Confidence Samples', [...$this->identityColumns(), 'sources'], $rows);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $sourceIdentities
     * @return array<int, string>
     */
    private function mixedFields(Collection $sourceIdentities): array
    {
        $fields = ['brand', 'product_family', 'canonical_category', 'face_value', 'face_value_currency', 'region', 'platform', 'confidence'];
        $mixed = [];

        foreach ($fields as $field) {
            $values = $sourceIdentities
                ->pluck($field)
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($value) => $field === 'face_value' ? $this->amountKey((float) $value) : $this->normalizeText((string) $value))
                ->unique()
                ->values();

            if ($values->count() > 1) {
                $mixed[] = $field.': '.$values->take(4)->implode(' vs ');
            }
        }

        return $mixed;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function identityForSource(CanonicalProductIdentitySource $source): ?array
    {
        try {
            if ($source->source_type === CanonicalProductIdentitySource::SOURCE_PROVIDER_PRODUCT) {
                $product = ProviderProduct::query()
                    ->with(['brand', 'region', 'provider'])
                    ->find($source->source_id);

                return $product ? $this->identityService->forProviderProduct($product) : null;
            }

            if ($source->source_type === CanonicalProductIdentitySource::SOURCE_PRODUCT) {
                $product = Product::query()
                    ->with(['brand', 'provider'])
                    ->find($source->source_id);

                return $product ? $this->identityService->forProduct($product) : null;
            }
        } catch (\Throwable $exception) {
            return [
                'source_type' => $source->source_type,
                'source_id' => $source->source_id,
                'error' => $exception->getMessage(),
            ];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function suspiciousReasons(CanonicalProductIdentity $identity): array
    {
        $reasons = [];
        $slug = (string) $identity->identity_slug;
        $family = (string) $identity->product_family;
        $brand = (string) $identity->brand;
        $text = implode(' ', [$slug, $brand, $family, (string) $identity->platform]);
        $knownBrands = $this->knownBrandsIn($text);

        if (count($knownBrands) > 1) {
            $reasons[] = 'multiple_known_brands: '.implode(', ', $knownBrands);
        }

        if ($this->tokenCount($slug) > 10) {
            $reasons[] = 'long_slug_tokens: '.$this->tokenCount($slug);
        }

        if ($this->tokenCount($family) > 5) {
            $reasons[] = 'long_family_tokens: '.$this->tokenCount($family);
        }

        if ($brand !== '' && $this->tokenCount($brand) >= 4 && $this->knownBrandsIn($brand) === []) {
            $reasons[] = 'long_unknown_brand: '.$this->clip($brand, 40);
        }

        $brandTokens = collect(explode(' ', $this->normalizeText($brand)))->filter()->values();
        if ($brandTokens->intersect(self::GENERIC_BRAND_TOKENS)->isNotEmpty() && $this->knownBrandsIn($brand) === []) {
            $reasons[] = 'generic_tokens_in_brand: '.$this->clip($brand, 40);
        }

        if (
            in_array($identity->canonical_category, self::DENOMINATION_CATEGORIES, true)
            && ((float) ($identity->face_value ?? 0) <= 0 || ! $identity->face_value_currency)
        ) {
            $reasons[] = 'missing_denomination';
        }

        return $reasons;
    }

    /**
     * @return array<int, string>
     */
    private function knownBrandsIn(string $value): array
    {
        $normalized = ' '.$this->normalizeText($value).' ';
        $matches = [];

        foreach (self::KNOWN_BRANDS as $needle => $brand) {
            $needle = ' '.$this->normalizeText($needle).' ';
            if ($needle !== '  ' && str_contains($normalized, $needle)) {
                $matches[$brand] = $brand;
            }
        }

        return array_values($matches);
    }

    /**
     * @return array<int, string>
     */
    private function identityColumns(): array
    {
        return ['id', 'slug', 'category', 'confidence', 'brand', 'family', 'face_value', 'currency', 'region', 'provider_count', 'seller_count', 'best_offer'];
    }

    /**
     * @return array<string, mixed>
     */
    private function identityRow(CanonicalProductIdentity $identity): array
    {
        return [
            'id' => $identity->id,
            'slug' => $this->clip($identity->identity_slug, 56),
            'category' => $identity->canonical_category,
            'confidence' => $identity->confidence,
            'brand' => $this->clip($identity->brand, 28),
            'family' => $this->clip($identity->product_family, 36),
            'face_value' => $identity->face_value !== null ? $this->amountKey((float) $identity->face_value) : null,
            'currency' => $identity->face_value_currency,
            'region' => $identity->region,
            'provider_count' => $identity->provider_candidates_count,
            'seller_count' => $identity->seller_offers_count,
            'best_offer' => $identity->best_offer_product_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<int, string>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $meta
     */
    private function section(array &$report, string $key, string $title, array $columns, array $rows, array $meta = []): void
    {
        $report['sections'][$key] = [
            'title' => $title,
            'columns' => $columns,
            'rows' => array_map(fn (array $row) => $this->stringifyRow($row), $rows),
            'meta' => $meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function stringifyRow(array $row): array
    {
        return collect($row)
            ->map(function ($value) {
                if (is_bool($value)) {
                    return $value ? 'yes' : 'no';
                }

                if ($value instanceof \Stringable) {
                    return (string) $value;
                }

                if (is_array($value)) {
                    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }

                return $value;
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderReport(array $report): void
    {
        $summary = $report['summary'];

        $this->info('Canonical product identity audit');
        $this->line('Generated at: '.$report['generated_at']);
        $this->line('Identities: '.$summary['identity_count'].'; sources: '.$summary['source_count'].'; multi-source identities: '.$summary['multi_source_identity_count']);
        $this->line('Seller identities: '.$summary['identities_with_seller_offers'].'; identities with best offer: '.$summary['identities_with_best_offer']);

        if ($report['warnings'] !== []) {
            $this->newLine();
            foreach ($report['warnings'] as $warning) {
                $this->warn($warning);
            }
        }

        foreach ($report['sections'] as $section) {
            $this->newLine();
            $this->info($section['title']);

            if (($section['meta'] ?? []) !== []) {
                $meta = collect($section['meta'])
                    ->map(fn ($value, string $key) => $key.'='.$value)
                    ->implode('; ');
                $this->line($meta);
            }

            if ($section['rows'] === []) {
                $this->line('No rows.');

                continue;
            }

            $this->table($section['columns'], $section['rows']);
        }
    }

    private function groupedSourceCount(string $having): int
    {
        $subquery = DB::table('canonical_product_identity_sources')
            ->select('canonical_product_identity_id')
            ->groupBy('canonical_product_identity_id')
            ->havingRaw($having);

        return (int) DB::query()->fromSub($subquery, 'source_groups')->count();
    }

    private function tablesExist(): bool
    {
        return Schema::hasTable('canonical_product_identities')
            && Schema::hasTable('canonical_product_identity_sources');
    }

    private function fieldSummary(array $identity): string
    {
        $faceValue = $identity['face_value'] ?? null;
        $faceValue = $faceValue !== null && $faceValue !== '' ? $this->amountKey((float) $faceValue) : null;

        return collect([
            $identity['brand'] ?? null,
            $identity['product_family'] ?? null,
            trim((string) $faceValue.' '.(string) ($identity['face_value_currency'] ?? '')),
            $identity['region'] ?? null,
        ])
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->implode(' / ');
    }

    private function slugStem(string $slug): string
    {
        $slug = preg_replace('/-\d+(?:-\d+)?-(usd|eur|gbp|cad|aud|aed|try|sar|rub|kwd|qar|omr|pln|jpy|inr|brl|mxn)(?=-|$)/i', '-{amount}-$1', $slug) ?? $slug;
        $slug = preg_replace('/-(us|usa|eu|tr|ru|ae|sa|gb|global|glb)(?=-|$)/i', '-{region}', $slug) ?? $slug;

        return $slug;
    }

    private function tokenCount(?string $value): int
    {
        $normalized = $this->normalizeText((string) $value);

        return $normalized === '' ? 0 : count(explode(' ', $normalized));
    }

    private function normalizeText(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function keyPart(?string $value): string
    {
        return $this->normalizeText((string) $value) ?: 'unknown';
    }

    private function amountKey(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function clip(mixed $value, int $limit = 64): ?string
    {
        if ($value === null) {
            return null;
        }

        return Str::limit((string) $value, $limit, '...');
    }
}
