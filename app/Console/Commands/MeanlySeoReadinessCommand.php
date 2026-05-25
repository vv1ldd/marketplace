<?php

namespace App\Console\Commands;

use App\Models\CanonicalProductIdentity;
use App\Models\SeoCollection;
use App\Services\CanonicalStorefrontHomepageService;
use App\Services\DiscoveryEntityGraphService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

class MeanlySeoReadinessCommand extends Command
{
    protected $signature = 'meanly:seo-readiness {--json : Output machine-readable JSON}';

    protected $description = 'Run local Gate 1 SEO, Discovery, LLM, sitemap, metadata, structured data, and search readiness checks.';

    /** @var array<int, array{name:string,status:string,detail:string}> */
    private array $checks = [];

    public function handle(): int
    {
        if (! $this->option('json')) {
            $this->info('SEO READINESS');
            $this->line('-------------');
        }

        $samples = $this->sampleUrls();

        $this->checkUrlHealth($samples);
        $this->checkMetadata($samples);
        $this->checkStructuredData($samples);
        $this->checkSitemaps();
        $this->checkLlmLayer();
        $this->checkDiscoveryGraph();
        $this->checkSearchHealth();

        if ($this->option('json')) {
            $this->line(json_encode([
                'status' => $this->resultStatus(),
                'checks' => $this->checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $this->hasFailures() ? self::FAILURE : self::SUCCESS;
        }

        $this->newLine();
        $this->table(['Check', 'Status', 'Detail'], $this->checks);

        $status = $this->resultStatus();
        if ($status === 'NO GO') {
            $this->error('RESULT: NO GO');

            return self::FAILURE;
        }

        if ($status === 'CONDITIONAL GO') {
            $this->warn('RESULT: CONDITIONAL GO');

            return self::SUCCESS;
        }

        $this->info('RESULT: READY');

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function sampleUrls(): array
    {
        $catalog = app(CanonicalStorefrontHomepageService::class);
        $graph = app(DiscoveryEntityGraphService::class);

        $product = CanonicalProductIdentity::query()
            ->whereNotNull('best_offer_product_id')
            ->orderByDesc('seller_offers_count')
            ->first();

        $brand = $graph->brands(1)->first();
        $region = $graph->regions(1)->first();
        $brandRegion = $graph->brandRegions(1)->first();
        $category = $catalog->publicCategorySummaries()
            ->first(fn (array $row): bool => (int) ($row['product_count'] ?? 0) > 0);
        $collection = SeoCollection::query()->where('is_active', true)->first();

        return array_filter([
            'home' => route('home'),
            'catalog' => route('meanly.catalog.index'),
            'product' => $product ? route('meanly.canonical-products.show', $product->identity_slug) : null,
            'brand' => $brand['url'] ?? null,
            'region' => $region['url'] ?? null,
            'brand_region' => $brandRegion['url'] ?? null,
            'category' => isset($category['slug']) ? route('meanly.catalog.categories.show', $category['slug']) : null,
            'tag' => $collection ? route('meanly.catalog.collections.show', $collection->slug) : null,
        ]);
    }

    /**
     * @param array<string, string> $samples
     */
    private function checkUrlHealth(array $samples): void
    {
        $failed = [];
        foreach (['product', 'brand', 'region', 'brand_region', 'category'] as $name) {
            $url = $samples[$name] ?? null;
            if (! $url) {
                $failed[] = "{$name}: missing sample";
                continue;
            }

            $response = $this->get($url);
            if (! $response || $response->status() !== 200) {
                $failed[] = "{$name}: ".($response?->status() ?? 'request_failed');
            }
        }

        foreach ([
            'invalid_product' => url('/catalog/products/__missing_seo_readiness__'),
            'invalid_brand' => url('/catalog/brands/__missing_seo_readiness__'),
            'invalid_region' => url('/catalog/regions/__missing_seo_readiness__'),
            'invalid_brand_region' => url('/catalog/brands/__missing__/regions/__missing__'),
            'invalid_category' => url('/catalog/__missing_seo_readiness__'),
        ] as $name => $url) {
            $response = $this->get($url);
            if (! $response || $response->status() !== 404) {
                $failed[] = "{$name}: expected 404, got ".($response?->status() ?? 'request_failed');
            }
        }

        $this->addCheck(
            'URL health',
            $failed === [] ? 'pass' : 'fail',
            $failed === [] ? 'Valid URL samples return 200 and invalid samples return 404.' : implode('; ', $failed),
        );
    }

    /**
     * @param array<string, string> $samples
     */
    private function checkMetadata(array $samples): void
    {
        $failed = [];
        foreach (array_filter($samples) as $name => $url) {
            $response = $this->get($url);
            if (! $response || $response->status() !== 200) {
                $failed[] = "{$name}: unavailable";
                continue;
            }

            $html = $response->body();
            if (! preg_match('/<title>\\s*([^<]{3,})\\s*<\\/title>/iu', $html)) {
                $failed[] = "{$name}: missing title";
            }
            if (! preg_match('/<meta\\s+name=["\\\']description["\\\'][^>]+content=["\\\'][^"\\\']{20,}["\\\']/iu', $html)) {
                $failed[] = "{$name}: missing description";
            }
            if (! preg_match('/<link\\s+rel=["\\\']canonical["\\\'][^>]+href=["\\\']https?:\\/\\/[^"\\\']+["\\\']/iu', $html)) {
                $failed[] = "{$name}: missing canonical";
            }
        }

        $this->addCheck(
            'Metadata',
            $failed === [] ? 'pass' : 'fail',
            $failed === [] ? 'Sample pages expose title, description, and canonical URL.' : implode('; ', $failed),
        );
    }

    /**
     * @param array<string, string> $samples
     */
    private function checkStructuredData(array $samples): void
    {
        $types = collect();
        $failed = [];

        foreach (array_filter($samples) as $name => $url) {
            $response = $this->get($url);
            if (! $response || $response->status() !== 200) {
                continue;
            }

            $pageTypes = $this->jsonLdTypes($response->body());
            if ($pageTypes === []) {
                $failed[] = "{$name}: missing JSON-LD";
            }

            $types = $types->merge($pageTypes);
        }

        $required = ['Product', 'BreadcrumbList', 'Organization', 'WebSite'];
        $missing = collect($required)
            ->reject(fn (string $type): bool => $types->contains($type))
            ->values()
            ->all();

        $this->addCheck(
            'JSON-LD',
            $failed === [] && $missing === [] ? 'pass' : 'fail',
            $failed === [] && $missing === []
                ? 'Structured data includes Product, BreadcrumbList, Organization, and WebSite.'
                : trim('Missing types: '.implode(', ', $missing).($failed ? '; '.implode('; ', $failed) : '')),
        );
    }

    private function checkSitemaps(): void
    {
        $failed = [];
        foreach ([
            'sitemap.xml' => route('sitemap.index'),
            'sitemap-products.xml' => route('sitemap.products'),
            'sitemap-brands.xml' => route('sitemap.brands'),
            'sitemap-regions.xml' => route('sitemap.regions'),
            'sitemap-brand-regions.xml' => route('sitemap.brand-regions'),
        ] as $name => $url) {
            $response = $this->get($url);
            if (! $response || $response->status() !== 200) {
                $failed[] = "{$name}: ".($response?->status() ?? 'request_failed');
                continue;
            }

            $xml = @simplexml_load_string($response->body());
            if (! $xml) {
                $failed[] = "{$name}: invalid XML";
                continue;
            }

            $count = $xml->getName() === 'sitemapindex'
                ? count($xml->sitemap)
                : count($xml->url);
            if ($count <= 0) {
                $failed[] = "{$name}: empty";
            }
        }

        $this->addCheck(
            'Sitemap XML',
            $failed === [] ? 'pass' : 'fail',
            $failed === [] ? 'Sitemaps are valid XML and contain URLs.' : implode('; ', $failed),
        );
    }

    private function checkLlmLayer(): void
    {
        $failed = [];
        $notes = [];

        $text = $this->get(route('llms.txt'));
        if (! $text || $text->status() !== 200 || ! str_contains($text->body(), 'Meanly')) {
            $failed[] = 'llms.txt';
        }

        foreach ([
            'catalog' => route('llms.catalog.index'),
            'commerce' => route('llms.commerce.opportunities'),
        ] as $name => $url) {
            $response = $this->get($url);
            if (! $response || $response->status() !== 200) {
                $failed[] = "{$name}: ".($response?->status() ?? 'request_failed');
                continue;
            }

            $json = json_decode($response->body(), true);
            if (! is_array($json)) {
                $failed[] = "{$name}: invalid JSON";
                continue;
            }

            if ($name === 'catalog' && count((array) ($json['products'] ?? [])) === 0) {
                $failed[] = 'catalog: empty products';
            }

            if ($name === 'commerce' && count((array) ($json['opportunities'] ?? [])) === 0) {
                $notes[] = 'commerce opportunities empty';
            }
        }

        $this->addCheck(
            'LLM layer',
            $failed === [] ? 'pass' : 'fail',
            $failed === [] && $notes === []
                ? 'llms.txt and core LLM JSON endpoints are valid and populated.'
                : implode('; ', array_merge($failed, $notes)),
        );
    }

    private function checkDiscoveryGraph(): void
    {
        try {
            $stats = app(DiscoveryHealthCommand::class)->stats();
            $ok = (int) $stats['brands'] > 0
                && (int) $stats['regions'] > 0
                && (int) $stats['intersections'] > 0
                && (int) $stats['broken'] === 0;

            $this->addCheck(
                'Discovery Graph',
                $ok ? 'pass' : 'fail',
                "Brands: {$stats['brands']}; Regions: {$stats['regions']}; Intersections: {$stats['intersections']}; Broken: {$stats['broken']}",
            );
        } catch (Throwable $e) {
            $this->addCheck('Discovery Graph', 'fail', $e->getMessage());
        }
    }

    private function checkSearchHealth(): void
    {
        $failed = [];
        $queries = app(DiscoveryEntityGraphService::class)
            ->brandRegions(5)
            ->map(fn (array $edge): string => trim(($edge['brand'] ?? '').' '.($edge['region'] ?? '')))
            ->filter()
            ->unique()
            ->take(4)
            ->values();

        if ($queries->isEmpty()) {
            $this->addCheck('Search Health', 'fail', 'No brand-region intersections available for search smoke queries.');

            return;
        }

        foreach ($queries as $query) {
            $response = $this->get(route('llms.catalog.retrieve', [
                'query' => $query,
                'auto_understand' => 1,
                'limit' => 5,
            ]));

            if (! $response || $response->status() !== 200) {
                $failed[] = "{$query}: ".($response?->status() ?? 'request_failed');
                continue;
            }

            $json = json_decode($response->body(), true);
            if (! is_array($json) || (int) ($json['match_count'] ?? 0) <= 0) {
                $failed[] = "{$query}: no matches";
            }
        }

        $this->addCheck(
            'Search Health',
            $failed === [] ? 'pass' : 'fail',
            $failed === [] ? 'Query normalization and catalog retrieval return matches for top brand-region smoke queries: '.$queries->implode(', ') : implode('; ', $failed),
        );
    }

    private function get(string $url): ?Response
    {
        try {
            return Http::withoutVerifying()
                ->timeout(20)
                ->accept('*/*')
                ->get($url);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    private function jsonLdTypes(string $html): array
    {
        preg_match_all('/<script\\s+type=["\\\']application\\/ld\\+json["\\\'][^>]*>(.*?)<\\/script>/is', $html, $matches);

        return collect($matches[1] ?? [])
            ->flatMap(function (string $json): array {
                $decoded = json_decode(html_entity_decode($json), true);

                return is_array($decoded) ? $this->typesFromJsonLd($decoded) : [];
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function typesFromJsonLd(array $node): array
    {
        $types = [];

        if (isset($node['@type'])) {
            $types = array_merge($types, (array) $node['@type']);
        }

        foreach ($node as $child) {
            if (! is_array($child)) {
                continue;
            }

            foreach ($this->isAssoc($child) ? [$child] : $child as $value) {
                if (is_array($value)) {
                    $types = array_merge($types, $this->typesFromJsonLd($value));
                }
            }
        }

        return $types;
    }

    private function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function addCheck(string $name, string $status, string $detail): void
    {
        $this->checks[] = [
            'name' => $name,
            'status' => $status,
            'detail' => Str::limit($detail, 260),
        ];
    }

    private function hasFailures(): bool
    {
        return collect($this->checks)->contains(fn (array $check): bool => $check['status'] === 'fail');
    }

    private function resultStatus(): string
    {
        if ($this->hasFailures()) {
            return 'NO GO';
        }

        return collect($this->checks)->contains(fn (array $check): bool => $check['status'] === 'warn')
            ? 'CONDITIONAL GO'
            : 'READY';
    }
}
