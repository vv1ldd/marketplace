<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class MeanlyDeploymentReadinessCommand extends Command
{
    protected $signature = 'meanly:deployment-readiness
        {--domain= : Public HTTPS domain to verify, e.g. https://meanly.com}
        {--json : Output machine-readable JSON}';

    protected $description = 'Run Gate 2 deployment readiness checks against a public staging or production domain.';

    /** @var array<int, array{name:string,status:string,detail:string}> */
    private array $checks = [];

    private string $baseUrl = '';

    public function handle(): int
    {
        $domain = trim((string) $this->option('domain'));
        if ($domain === '') {
            $this->addCheck('domain', 'fail', 'Pass --domain=https://your-domain to run deployment readiness.');
            $this->render();

            return self::FAILURE;
        }

        $this->baseUrl = $this->normalizeBaseUrl($domain);

        if (! $this->option('json')) {
            $this->info('DEPLOYMENT READINESS');
            $this->line('--------------------');
            $this->line('Domain: '.$this->baseUrl);
            $this->newLine();
        }

        $this->checkHttpsDomain();
        $this->checkHttpRedirect();
        $this->checkRobots();
        $this->checkSitemap();
        $this->checkCanonicalUrls();
        $this->checkHeaders();
        $this->checkLlmJson();

        $this->render();

        return $this->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    private function checkHttpsDomain(): void
    {
        $this->addCheck(
            'HTTPS domain',
            str_starts_with($this->baseUrl, 'https://') ? 'pass' : 'fail',
            str_starts_with($this->baseUrl, 'https://') ? 'Deployment domain uses HTTPS.' : 'Domain must start with https://.',
        );
    }

    private function checkHttpRedirect(): void
    {
        $httpUrl = 'http://'.parse_url($this->baseUrl, PHP_URL_HOST);
        $response = $this->get($httpUrl, redirects: false);
        $status = $response?->status();
        $location = (string) ($response?->header('Location') ?? '');

        $ok = in_array($status, [301, 302, 307, 308], true)
            && str_starts_with($location, $this->baseUrl);

        $this->addCheck(
            'HTTP -> HTTPS',
            $ok ? 'pass' : 'warn',
            $ok ? "Redirects to {$location}." : 'Expected HTTP to redirect to HTTPS; got '.($status ?? 'request_failed').($location ? " location={$location}" : ''),
        );
    }

    private function checkRobots(): void
    {
        $response = $this->get($this->url('/robots.txt'));
        $body = (string) $response?->body();
        $ok = $response?->status() === 200
            && str_contains($body, 'User-agent')
            && str_contains($body, 'Sitemap:');

        $this->addCheck(
            'robots.txt',
            $ok ? 'pass' : 'fail',
            $ok ? 'robots.txt is reachable and advertises sitemap URLs.' : 'robots.txt missing, invalid, or does not include Sitemap.',
        );
    }

    private function checkSitemap(): void
    {
        $response = $this->get($this->url('/sitemap.xml'));
        if ($response?->status() !== 200) {
            $this->addCheck('sitemap.xml', 'fail', 'Expected 200, got '.($response?->status() ?? 'request_failed').'.');

            return;
        }

        $xml = @simplexml_load_string($response->body());
        $count = $xml && $xml->getName() === 'sitemapindex' ? count($xml->sitemap) : ($xml ? count($xml->url) : 0);

        $this->addCheck(
            'sitemap.xml',
            $xml && $count > 0 ? 'pass' : 'fail',
            $xml && $count > 0 ? "Valid XML with {$count} entries." : 'Invalid or empty XML.',
        );
    }

    private function checkCanonicalUrls(): void
    {
        $failed = [];
        foreach (['/', '/catalog', '/sitemap.xml'] as $path) {
            if ($path === '/sitemap.xml') {
                continue;
            }

            $response = $this->get($this->url($path));
            if ($response?->status() !== 200) {
                $failed[] = "{$path}: ".($response?->status() ?? 'request_failed');
                continue;
            }

            $html = $response->body();
            if (! preg_match('/<link\s+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/iu', $html, $matches)) {
                $failed[] = "{$path}: missing canonical";
                continue;
            }

            $canonical = (string) $matches[1];
            if (! str_starts_with($canonical, $this->baseUrl) || str_contains($canonical, 'localhost')) {
                $failed[] = "{$path}: bad canonical {$canonical}";
            }
        }

        $this->addCheck(
            'Canonical URLs',
            $failed === [] ? 'pass' : 'fail',
            $failed === [] ? 'Canonical URLs point at deployment domain and do not leak localhost.' : implode('; ', $failed),
        );
    }

    private function checkHeaders(): void
    {
        $response = $this->get($this->url('/'));
        if ($response?->status() !== 200) {
            $this->addCheck('Response headers', 'fail', 'Homepage unavailable.');

            return;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        $cacheControl = (string) $response->header('Cache-Control');
        $encoding = (string) ($response->header('Content-Encoding') ?: $response->header('Vary'));
        $failed = [];
        $warnings = [];

        if (! str_contains($contentType, 'text/html')) {
            $failed[] = 'content-type='.$contentType;
        }

        if ($cacheControl === '') {
            $warnings[] = 'missing cache-control';
        }

        if ($encoding === '') {
            $warnings[] = 'missing compression/vary signal';
        }

        $this->addCheck(
            'Response headers',
            $failed === [] ? ($warnings === [] ? 'pass' : 'warn') : 'fail',
            $failed === [] && $warnings === [] ? 'content-type, cache-control, and compression headers look present.' : implode('; ', array_merge($failed, $warnings)),
        );
    }

    private function checkLlmJson(): void
    {
        $failed = [];
        foreach (['/llms/catalog.json', '/llms/commerce/opportunities'] as $path) {
            $response = $this->get($this->url($path));
            $json = $response?->status() === 200 ? json_decode($response->body(), true) : null;
            if (! is_array($json)) {
                $failed[] = "{$path}: ".($response?->status() ?? 'request_failed');
            }
        }

        $this->addCheck(
            'LLM JSON',
            $failed === [] ? 'pass' : 'fail',
            $failed === [] ? 'Core LLM JSON endpoints are reachable and valid JSON.' : implode('; ', $failed),
        );
    }

    private function normalizeBaseUrl(string $domain): string
    {
        $domain = str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')
            ? $domain
            : 'https://'.$domain;

        return rtrim($domain, '/');
    }

    private function url(string $path): string
    {
        return $this->baseUrl.'/'.ltrim($path, '/');
    }

    private function get(string $url, bool $redirects = true): ?Response
    {
        try {
            return Http::withoutVerifying()
                ->timeout(20)
                ->withOptions(['allow_redirects' => $redirects])
                ->accept('*/*')
                ->get($url);
        } catch (Throwable) {
            return null;
        }
    }

    private function addCheck(string $name, string $status, string $detail): void
    {
        $this->checks[] = [
            'name' => $name,
            'status' => $status,
            'detail' => Str::limit($detail, 260),
        ];
    }

    private function render(): void
    {
        if ($this->option('json')) {
            $this->line(json_encode([
                'status' => $this->resultStatus(),
                'domain' => $this->baseUrl,
                'checks' => $this->checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->table(['Check', 'Status', 'Detail'], $this->checks);

        match ($this->resultStatus()) {
            'NO GO' => $this->error('RESULT: NO GO'),
            'CONDITIONAL GO' => $this->warn('RESULT: CONDITIONAL GO'),
            default => $this->info('RESULT: READY'),
        };
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
